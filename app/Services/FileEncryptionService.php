<?php

namespace App\Services;

use App\Models\User;
use App\Models\EncryptedFile;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service pour le chiffrement/déchiffrement hybride de fichiers
 * Utilise AES-256 pour le fichier et RSA pour la clé AES
 */
class FileEncryptionService
{
    public function __construct(
        protected CryptoService $cryptoService,
        protected KeyManagementService $keyManagementService
    ) {}

    /**
     * Chiffrer un fichier pour un utilisateur (hybride AES + RSA)
     *
     * @param UploadedFile $file Fichier à chiffrer
     * @param User $owner Propriétaire du fichier
     * @param string $password Mot de passe de l'utilisateur
     * @return EncryptedFile
     * @throws Exception
     */
    public function encryptFile(UploadedFile $file, User $owner, string $password): EncryptedFile
    {
        try {
            // 1. Lire le contenu du fichier
            $originalContent = file_get_contents($file->getRealPath());

            if ($originalContent === false) {
                throw new Exception("Impossible de lire le fichier");
            }

            // 2. Calculer le hash du fichier original (pour vérification d'intégrité)
            $fileHash = hash('sha256', $originalContent);

            // 3. Générer une clé AES aléatoire
            $aesKey = $this->cryptoService->generateAESKey();

            // 4. Chiffrer le fichier avec AES
            $encryptedData = $this->cryptoService->encryptAES($originalContent, $aesKey);

            // 5. Combiner les données chiffrées avec IV et tag
            $encryptedFileData = json_encode([
                'ciphertext' => $encryptedData['ciphertext'],
                'iv' => $encryptedData['iv'],
                'tag' => $encryptedData['tag'],
            ]);

            // 6. Générer un nom de fichier unique
            $filename = Str::uuid() . '.encrypted';
            $filePath = 'encrypted_files/' . $owner->id . '/' . $filename;

            // 7. Stocker le fichier chiffré
            Storage::put($filePath, $encryptedFileData);

            // 8. Récupérer la clé publique du propriétaire
            $publicKey = $this->keyManagementService->getPublicKey($owner);

            // 9. Chiffrer la clé AES avec la clé publique RSA du propriétaire
            $encryptedAESKey = $this->cryptoService->encryptRSA($aesKey, $publicKey);

            // 10. Récupérer la clé privée pour signer
            $privateKey = $this->keyManagementService->getPrivateKey($owner, $password);

            // 11. Signer le hash du fichier
            $signature = $this->cryptoService->signData($fileHash, $privateKey);

            // 12. Créer l'enregistrement en base de données
            return EncryptedFile::create([
                'owner_id' => $owner->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'encrypted_aes_key' => $encryptedAESKey,
                'signature' => $signature,
                'hash' => $fileHash,
                'mime_type' => $file->getMimeType(),
                'encryption_algorithm' => 'AES-256-GCM',
            ]);
        } catch (Exception $e) {
            // Nettoyer en cas d'erreur
            if (isset($filePath) && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            throw new Exception("Erreur lors du chiffrement du fichier: " . $e->getMessage());
        }
    }

    /**
     * Déchiffrer un fichier pour un utilisateur
     *
     * @param EncryptedFile $file Fichier chiffré
     * @param User $user Utilisateur demandant le déchiffrement
     * @param string $password Mot de passe de l'utilisateur
     * @return string Contenu déchiffré
     * @throws Exception
     */
    public function decryptFile(EncryptedFile $file, User $user, string $password): string
    {
        try {
            // 1. Vérifier que l'utilisateur a accès au fichier
            if (!$file->hasAccess($user)) {
                throw new Exception("Accès refusé à ce fichier");
            }

            // 2. Récupérer le fichier chiffré depuis le stockage
            if (!Storage::exists($file->file_path)) {
                throw new Exception("Fichier chiffré introuvable");
            }

            $encryptedFileData = Storage::get($file->file_path);
            $fileData = json_decode($encryptedFileData, true);

            if (!$fileData) {
                throw new Exception("Format de fichier chiffré invalide");
            }

            // 3. Récupérer la clé AES chiffrée pour cet utilisateur
            $encryptedAESKey = $this->getEncryptedAESKeyForUser($file, $user);

            // 4. Déchiffrer la clé AES avec la clé privée RSA de l'utilisateur
            $privateKey = $this->keyManagementService->getPrivateKey($user, $password);
            $aesKey = $this->cryptoService->decryptRSA($encryptedAESKey, $privateKey);

            // 5. Déchiffrer le fichier avec la clé AES
            $decryptedContent = $this->cryptoService->decryptAES(
                $fileData['ciphertext'],
                $aesKey,
                $fileData['iv'],
                $fileData['tag']
            );

            // 6. Vérifier l'intégrité du fichier
            $currentHash = hash('sha256', $decryptedContent);

            if ($currentHash !== $file->hash) {
                throw new Exception("Intégrité du fichier compromise (hash invalide)");
            }

            // 7. Vérifier la signature si disponible
            if ($file->signature) {
                $ownerPublicKey = $this->keyManagementService->getPublicKey($file->owner);
                $isValid = $this->cryptoService->verifySignature(
                    $file->hash,
                    $file->signature,
                    $ownerPublicKey
                );

                if (!$isValid) {
                    throw new Exception("Signature invalide");
                }
            }

            return $decryptedContent;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du déchiffrement du fichier: " . $e->getMessage());
        }
    }

    /**
     * Récupérer la clé AES chiffrée pour un utilisateur spécifique
     *
     * @param EncryptedFile $file Fichier
     * @param User $user Utilisateur
     * @return string Clé AES chiffrée
     * @throws Exception
     */
    protected function getEncryptedAESKeyForUser(EncryptedFile $file, User $user): string
    {
        // Si l'utilisateur est le propriétaire
        if ($file->owner_id === $user->id) {
            return $file->encrypted_aes_key;
        }

        // Sinon, chercher dans les accès partagés
        $access = $file->activeAccesses()
            ->where('user_id', $user->id)
            ->first();

        if (!$access) {
            throw new Exception("Aucune clé AES disponible pour cet utilisateur");
        }

        return $access->encrypted_aes_key;
    }

    /**
     * Supprimer un fichier chiffré (physiquement et en base)
     *
     * @param EncryptedFile $file Fichier à supprimer
     * @param User $user Utilisateur demandant la suppression
     * @return bool
     * @throws Exception
     */
    public function deleteFile(EncryptedFile $file, User $user): bool
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($file->owner_id !== $user->id) {
            throw new Exception("Seul le propriétaire peut supprimer ce fichier");
        }

        // Supprimer le fichier physique
        if (Storage::exists($file->file_path)) {
            Storage::delete($file->file_path);
        }

        // Supprimer l'enregistrement (soft delete)
        return $file->delete();
    }

    /**
     * Vérifier l'intégrité d'un fichier sans le déchiffrer
     *
     * @param EncryptedFile $file Fichier
     * @return bool True si le fichier existe et n'est pas corrompu
     */
    public function verifyFileIntegrity(EncryptedFile $file): bool
    {
        if (!Storage::exists($file->file_path)) {
            return false;
        }

        $encryptedFileData = Storage::get($file->file_path);
        $fileData = json_decode($encryptedFileData, true);

        return $fileData !== null && isset($fileData['ciphertext'], $fileData['iv'], $fileData['tag']);
    }
}
