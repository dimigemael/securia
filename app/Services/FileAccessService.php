<?php

namespace App\Services;

use App\Models\EncryptedFile;
use App\Models\FileAccess;
use App\Models\User;
use Exception;

/**
 * Service pour la gestion des accès et partage de fichiers
 */
class FileAccessService
{
    public function __construct(
        protected CryptoService $cryptoService,
        protected KeyManagementService $keyManagementService
    ) {}

    /**
     * Partager un fichier avec un autre utilisateur
     *
     * @param EncryptedFile $file Fichier à partager
     * @param User $owner Propriétaire du fichier
     * @param User $recipient Utilisateur destinataire
     * @param string $ownerPassword Mot de passe du propriétaire
     * @param string $permissionLevel Niveau de permission (read, write)
     * @return FileAccess
     * @throws Exception
     */
    public function shareFile(
        EncryptedFile $file,
        User $owner,
        User $recipient,
        string $ownerPassword,
        string $permissionLevel = 'read'
    ): FileAccess {
        try {
            // 1. Vérifier que l'utilisateur est bien le propriétaire
            if ($file->owner_id !== $owner->id) {
                throw new Exception("Seul le propriétaire peut partager ce fichier");
            }

            // 2. Vérifier que le destinataire n'a pas déjà accès
            $existingAccess = FileAccess::where('file_id', $file->id)
                ->where('user_id', $recipient->id)
                ->whereNull('revoked_at')
                ->first();

            if ($existingAccess) {
                throw new Exception("Cet utilisateur a déjà accès au fichier");
            }

            // 3. Vérifier que le destinataire a des clés
            if (!$this->keyManagementService->hasKeys($recipient)) {
                throw new Exception("Le destinataire n'a pas de clés cryptographiques");
            }

            // 4. Déchiffrer la clé AES avec la clé privée du propriétaire
            $ownerPrivateKey = $this->keyManagementService->getPrivateKey($owner, $ownerPassword);
            $aesKey = $this->cryptoService->decryptRSA($file->encrypted_aes_key, $ownerPrivateKey);

            // 5. Re-chiffrer la clé AES avec la clé publique du destinataire
            $recipientPublicKey = $this->keyManagementService->getPublicKey($recipient);
            $encryptedAESKeyForRecipient = $this->cryptoService->encryptRSA($aesKey, $recipientPublicKey);

            // 6. Créer l'enregistrement d'accès
            return FileAccess::create([
                'file_id' => $file->id,
                'user_id' => $recipient->id,
                'encrypted_aes_key' => $encryptedAESKeyForRecipient,
                'permission_level' => $permissionLevel,
                'granted_by' => $owner->id,
                'granted_at' => now(),
            ]);
        } catch (Exception $e) {
            throw new Exception("Erreur lors du partage du fichier: " . $e->getMessage());
        }
    }

    /**
     * Révoquer l'accès d'un utilisateur à un fichier
     *
     * @param EncryptedFile $file Fichier
     * @param User $owner Propriétaire du fichier
     * @param User $user Utilisateur dont l'accès sera révoqué
     * @return FileAccess
     * @throws Exception
     */
    public function revokeAccess(EncryptedFile $file, User $owner, User $user): FileAccess
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($file->owner_id !== $owner->id) {
            throw new Exception("Seul le propriétaire peut révoquer des accès");
        }

        // Trouver l'accès actif
        $access = FileAccess::where('file_id', $file->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$access) {
            throw new Exception("Aucun accès actif trouvé pour cet utilisateur");
        }

        // Révoquer l'accès
        $access->revoke();

        return $access;
    }

    /**
     * Lister tous les utilisateurs ayant accès à un fichier
     *
     * @param EncryptedFile $file Fichier
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listUsersWithAccess(EncryptedFile $file)
    {
        return $file->sharedWith()->get();
    }

    /**
     * Lister tous les fichiers partagés avec un utilisateur
     *
     * @param User $user Utilisateur
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listFilesSharedWithUser(User $user)
    {
        return $user->accessibleFiles()->get();
    }

    /**
     * Modifier le niveau de permission d'un utilisateur
     *
     * @param EncryptedFile $file Fichier
     * @param User $owner Propriétaire
     * @param User $user Utilisateur dont les permissions seront modifiées
     * @param string $newPermissionLevel Nouveau niveau (read, write)
     * @return FileAccess
     * @throws Exception
     */
    public function updatePermission(
        EncryptedFile $file,
        User $owner,
        User $user,
        string $newPermissionLevel
    ): FileAccess {
        // Vérifier que l'utilisateur est le propriétaire
        if ($file->owner_id !== $owner->id) {
            throw new Exception("Seul le propriétaire peut modifier les permissions");
        }

        // Trouver l'accès actif
        $access = FileAccess::where('file_id', $file->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$access) {
            throw new Exception("Aucun accès actif trouvé pour cet utilisateur");
        }

        // Mettre à jour la permission
        $access->permission_level = $newPermissionLevel;
        $access->save();

        return $access;
    }

    /**
     * Vérifier si un utilisateur a une permission spécifique sur un fichier
     *
     * @param EncryptedFile $file Fichier
     * @param User $user Utilisateur
     * @param string $requiredPermission Permission requise (read, write, owner)
     * @return bool
     */
    public function hasPermission(EncryptedFile $file, User $user, string $requiredPermission): bool
    {
        $userPermission = $file->getUserPermission($user);

        if (!$userPermission) {
            return false;
        }

        // Hiérarchie des permissions: owner > write > read
        $hierarchy = ['read' => 1, 'write' => 2, 'owner' => 3];

        return $hierarchy[$userPermission] >= $hierarchy[$requiredPermission];
    }

    /**
     * Obtenir les statistiques d'accès pour un fichier
     *
     * @param EncryptedFile $file Fichier
     * @return array
     */
    public function getAccessStatistics(EncryptedFile $file): array
    {
        $allAccesses = FileAccess::where('file_id', $file->id)->get();

        return [
            'total_shares' => $allAccesses->count(),
            'active_shares' => $allAccesses->whereNull('revoked_at')->count(),
            'revoked_shares' => $allAccesses->whereNotNull('revoked_at')->count(),
            'users_with_read' => $allAccesses->where('permission_level', 'read')->whereNull('revoked_at')->count(),
            'users_with_write' => $allAccesses->where('permission_level', 'write')->whereNull('revoked_at')->count(),
        ];
    }

    /**
     * Révoquer tous les accès à un fichier
     *
     * @param EncryptedFile $file Fichier
     * @param User $owner Propriétaire
     * @return int Nombre d'accès révoqués
     * @throws Exception
     */
    public function revokeAllAccesses(EncryptedFile $file, User $owner): int
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($file->owner_id !== $owner->id) {
            throw new Exception("Seul le propriétaire peut révoquer tous les accès");
        }

        $activeAccesses = FileAccess::where('file_id', $file->id)
            ->whereNull('revoked_at')
            ->get();

        $count = 0;
        foreach ($activeAccesses as $access) {
            $access->revoke();
            $count++;
        }

        return $count;
    }
}
