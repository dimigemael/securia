<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * Indicates if the model should be timestamped.
     * On utilise seulement created_at pour les logs.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'details',
        'status',
        'error_message',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array', // JSON stocké sous forme de tableau
            'created_at' => 'datetime',
        ];
    }

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatiquement définir created_at lors de la création
        static::creating(function ($log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    /**
     * Relations
     */

    /**
     * Relation N:1 - Utilisateur ayant effectué l'action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */

    /**
     * Scope pour filtrer par action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par type d'entité
     */
    public function scopeEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les logs des dernières 24h
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Créer un log d'audit rapidement
     */
    public static function log(
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        string $status = 'success',
        ?string $errorMessage = null
    ): self {
        return self::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => $details,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
