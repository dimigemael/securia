<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAccess extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'file_accesses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_id',
        'user_id',
        'encrypted_aes_key',
        'permission_level',
        'granted_by',
        'granted_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'encrypted_aes_key', // Ne jamais exposer la clé chiffrée
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */

    /**
     * Relation N:1 - Fichier concerné par cet accès
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(EncryptedFile::class, 'file_id');
    }

    /**
     * Relation N:1 - Utilisateur ayant cet accès
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation N:1 - Utilisateur ayant accordé cet accès
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scopes
     */

    /**
     * Scope pour filtrer les accès actifs (non révoqués)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope pour filtrer les accès révoqués
     */
    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Vérifier si l'accès est actif
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Vérifier si l'accès est révoqué
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Révoquer cet accès
     */
    public function revoke(): bool
    {
        $this->revoked_at = now();
        return $this->save();
    }
}
