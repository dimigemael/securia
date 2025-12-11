<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserKey;
use Exception;

/**
 * Service pour la gestion des clés cryptographiques des utilisateurs
 */
class KeyManagementService
{
    public function __construct(
        protected CryptoService $cryptoService
    ) {}

    /**
     * Générer et stocker une paire de clés RSA pour un utilisateur
     *
     * @param User $user Utilisateur
     * @param string $password Mot de passe pour chiffrer la clé privée
     * @param int $keySize Taille de la clé (2048, 4096)
     * @return UserKey
     * @throws Exception
     */
    public function generateKeysForUser(User $user, string $password, int $keySize = 2048): UserKey
    {
        try {
            // Générer une paire RSA
            $keyPair = $this->cryptoService->generateRSAKeyPair($keySize);

            // Dériver une clé de chiffrement à partir du mot de passe
            $derived = $this->cryptoService->deriveKeyFromPassword($password);
            $encryptionKey = $derived['key'];
            $salt = $derived['salt'];

            // Chiffrer la clé privée avec AES
            $encryptedPrivateKey = $this->cryptoService->encryptAES(
                $keyPair['privateKey'],
                $encryptionKey
            );

            // Stocker les métadonnées de chiffrement dans un JSON
            $encryptedPrivateKeyData = json_encode([
                'ciphertext' => $encryptedPrivateKey['ciphertext'],
                'iv' => $encryptedPrivateKey['iv'],
                'tag' => $encryptedPrivateKey['tag'],
                'salt' => $salt,
            ]);

            // Supprimer l'ancienne clé si elle existe
            if ($user->userKey) {
                $user->userKey->delete();
            }

            // Créer et sauvegarder la nouvelle clé
            return UserKey::create([
                'user_id' => $user->id,
                'public_key' => $keyPair['publicKey'],
                'encrypted_private_key' => $encryptedPrivateKeyData,
                'key_algorithm' => 'RSA',
                'key_size' => $keySize,
            ]);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la génération des clés: " . $e->getMessage());
        }
    }

    /**
     * Récupérer et déchiffrer la clé privée d'un utilisateur
     *
     * @param User $user Utilisateur
     * @param string $password Mot de passe pour déchiffrer la clé privée
     * @return string Clé privée en clair (format PKCS8)
     * @throws Exception
     */
    public function getPrivateKey(User $user, string $password): string
    {
        try {
            $userKey = $user->userKey;

            if (!$userKey) {
                throw new Exception("Aucune clé trouvée pour cet utilisateur");
            }

            // Décoder les métadonnées de la clé privée chiffrée
            $encryptedData = json_decode($userKey->encrypted_private_key, true);

            if (!$encryptedData) {
                throw new Exception("Format de clé privée invalide");
            }

            // Dériver la clé de déchiffrement à partir du mot de passe
            $derived = $this->cryptoService->deriveKeyFromPassword(
                $password,
                $encryptedData['salt']
            );
            $decryptionKey = $derived['key'];

            // Déchiffrer la clé privée
            $privateKey = $this->cryptoService->decryptAES(
                $encryptedData['ciphertext'],
                $decryptionKey,
                $encryptedData['iv'],
                $encryptedData['tag']
            );

            return $privateKey;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du déchiffrement de la clé privée: " . $e->getMessage());
        }
    }

    /**
     * Récupérer la clé publique d'un utilisateur
     *
     * @param User $user Utilisateur
     * @return string Clé publique (format PKCS8)
     * @throws Exception
     */
    public function getPublicKey(User $user): string
    {
        $userKey = $user->userKey;

        if (!$userKey) {
            throw new Exception("Aucune clé trouvée pour cet utilisateur");
        }

        return $userKey->public_key;
    }

    /**
     * Vérifier si un utilisateur a des clés générées
     *
     * @param User $user Utilisateur
     * @return bool
     */
    public function hasKeys(User $user): bool
    {
        return $user->userKey !== null;
    }

    /**
     * Changer le mot de passe de chiffrement de la clé privée
     *
     * @param User $user Utilisateur
     * @param string $oldPassword Ancien mot de passe
     * @param string $newPassword Nouveau mot de passe
     * @return UserKey
     * @throws Exception
     */
    public function changeKeyPassword(User $user, string $oldPassword, string $newPassword): UserKey
    {
        try {
            // Déchiffrer la clé privée avec l'ancien mot de passe
            $privateKey = $this->getPrivateKey($user, $oldPassword);

            // Dériver une nouvelle clé à partir du nouveau mot de passe
            $derived = $this->cryptoService->deriveKeyFromPassword($newPassword);
            $newEncryptionKey = $derived['key'];
            $newSalt = $derived['salt'];

            // Re-chiffrer la clé privée avec le nouveau mot de passe
            $encryptedPrivateKey = $this->cryptoService->encryptAES(
                $privateKey,
                $newEncryptionKey
            );

            // Mettre à jour les métadonnées
            $encryptedPrivateKeyData = json_encode([
                'ciphertext' => $encryptedPrivateKey['ciphertext'],
                'iv' => $encryptedPrivateKey['iv'],
                'tag' => $encryptedPrivateKey['tag'],
                'salt' => $newSalt,
            ]);

            $userKey = $user->userKey;
            $userKey->encrypted_private_key = $encryptedPrivateKeyData;
            $userKey->save();

            return $userKey;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du changement de mot de passe: " . $e->getMessage());
        }
    }

    /**
     * Supprimer les clés d'un utilisateur (avec confirmation)
     *
     * @param User $user Utilisateur
     * @return bool
     * @throws Exception
     */
    public function deleteKeys(User $user): bool
    {
        if (!$user->userKey) {
            throw new Exception("Aucune clé à supprimer");
        }

        return $user->userKey->delete();
    }
}
