<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Service pour la journalisation et l'audit des actions
 * Conforme aux exigences RGPD et ISO 27001
 */
class AuditService
{
    /**
     * Actions disponibles pour la journalisation
     */
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_REGISTER = 'register';
    public const ACTION_FILE_ENCRYPT = 'file_encrypt';
    public const ACTION_FILE_DECRYPT = 'file_decrypt';
    public const ACTION_FILE_SHARE = 'file_share';
    public const ACTION_FILE_REVOKE = 'file_revoke';
    public const ACTION_FILE_DELETE = 'file_delete';
    public const ACTION_KEY_GENERATE = 'key_generate';
    public const ACTION_KEY_CHANGE_PASSWORD = 'key_change_password';
    public const ACTION_PERMISSION_UPDATE = 'permission_update';

    /**
     * Logger une action
     *
     * @param string $action Type d'action
     * @param User|null $user Utilisateur (null si non authentifié)
     * @param string|null $entityType Type d'entité concernée
     * @param int|null $entityId ID de l'entité
     * @param array $details Détails supplémentaires
     * @param string $status Statut (success, failure, warning)
     * @param string|null $errorMessage Message d'erreur si échec
     * @return AuditLog
     */
    public function log(
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        string $status = 'success',
        ?string $errorMessage = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'details' => $details,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Logger une connexion réussie
     *
     * @param User $user
     * @return AuditLog
     */
    public function logLogin(User $user): AuditLog
    {
        return $this->log(
            self::ACTION_LOGIN,
            $user,
            'User',
            $user->id,
            ['email' => $user->email]
        );
    }

    /**
     * Logger une tentative de connexion échouée
     *
     * @param string $email
     * @param string $reason
     * @return AuditLog
     */
    public function logLoginFailure(string $email, string $reason = 'Invalid credentials'): AuditLog
    {
        return $this->log(
            self::ACTION_LOGIN,
            null,
            'User',
            null,
            ['email' => $email, 'reason' => $reason],
            'failure',
            $reason
        );
    }

    /**
     * Logger une déconnexion
     *
     * @param User $user
     * @return AuditLog
     */
    public function logLogout(User $user): AuditLog
    {
        return $this->log(
            self::ACTION_LOGOUT,
            $user,
            'User',
            $user->id
        );
    }

    /**
     * Logger une inscription
     *
     * @param User $user
     * @return AuditLog
     */
    public function logRegistration(User $user): AuditLog
    {
        return $this->log(
            self::ACTION_REGISTER,
            $user,
            'User',
            $user->id,
            ['email' => $user->email, 'name' => $user->name]
        );
    }

    /**
     * Logger le chiffrement d'un fichier
     *
     * @param User $user
     * @param int $fileId
     * @param array $details
     * @return AuditLog
     */
    public function logFileEncryption(User $user, int $fileId, array $details = []): AuditLog
    {
        return $this->log(
            self::ACTION_FILE_ENCRYPT,
            $user,
            'EncryptedFile',
            $fileId,
            $details
        );
    }

    /**
     * Logger le déchiffrement d'un fichier
     *
     * @param User $user
     * @param int $fileId
     * @param array $details
     * @return AuditLog
     */
    public function logFileDecryption(User $user, int $fileId, array $details = []): AuditLog
    {
        return $this->log(
            self::ACTION_FILE_DECRYPT,
            $user,
            'EncryptedFile',
            $fileId,
            $details
        );
    }

    /**
     * Logger le partage d'un fichier
     *
     * @param User $owner
     * @param int $fileId
     * @param int $recipientId
     * @param string $permission
     * @return AuditLog
     */
    public function logFileShare(User $owner, int $fileId, int $recipientId, string $permission): AuditLog
    {
        return $this->log(
            self::ACTION_FILE_SHARE,
            $owner,
            'EncryptedFile',
            $fileId,
            [
                'recipient_id' => $recipientId,
                'permission' => $permission
            ]
        );
    }

    /**
     * Logger la révocation d'accès à un fichier
     *
     * @param User $owner
     * @param int $fileId
     * @param int $userId
     * @return AuditLog
     */
    public function logFileRevoke(User $owner, int $fileId, int $userId): AuditLog
    {
        return $this->log(
            self::ACTION_FILE_REVOKE,
            $owner,
            'EncryptedFile',
            $fileId,
            ['revoked_user_id' => $userId]
        );
    }

    /**
     * Logger la suppression d'un fichier
     *
     * @param User $user
     * @param int $fileId
     * @param array $details
     * @return AuditLog
     */
    public function logFileDelete(User $user, int $fileId, array $details = []): AuditLog
    {
        return $this->log(
            self::ACTION_FILE_DELETE,
            $user,
            'EncryptedFile',
            $fileId,
            $details
        );
    }

    /**
     * Logger la génération de clés
     *
     * @param User $user
     * @param array $details
     * @return AuditLog
     */
    public function logKeyGeneration(User $user, array $details = []): AuditLog
    {
        return $this->log(
            self::ACTION_KEY_GENERATE,
            $user,
            'UserKey',
            $user->id,
            $details
        );
    }

    /**
     * Récupérer les logs pour un utilisateur
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserLogs(User $user, int $limit = 50)
    {
        return AuditLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Récupérer les logs pour un fichier spécifique
     *
     * @param int $fileId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFileLogs(int $fileId, int $limit = 50)
    {
        return AuditLog::where('entity_type', 'EncryptedFile')
            ->where('entity_id', $fileId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Récupérer les logs récents (24h)
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentLogs(int $limit = 100)
    {
        return AuditLog::recent()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Récupérer les logs d'échec
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFailureLogs(int $limit = 50)
    {
        return AuditLog::where('status', 'failure')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtenir des statistiques d'activité
     *
     * @param User|null $user Utilisateur spécifique ou null pour global
     * @return array
     */
    public function getActivityStatistics(?User $user = null): array
    {
        $query = AuditLog::query();

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $total = $query->count();
        $successes = (clone $query)->where('status', 'success')->count();
        $failures = (clone $query)->where('status', 'failure')->count();

        $byAction = (clone $query)->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total_actions' => $total,
            'successful_actions' => $successes,
            'failed_actions' => $failures,
            'success_rate' => $total > 0 ? round(($successes / $total) * 100, 2) : 0,
            'actions_by_type' => $byAction,
        ];
    }

    /**
     * Purger les anciens logs (conformité RGPD - conservation limitée)
     *
     * @param int $days Conserver les logs des X derniers jours
     * @return int Nombre de logs supprimés
     */
    public function purgeLogs(int $days = 90): int
    {
        $cutoffDate = now()->subDays($days);

        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }
}
