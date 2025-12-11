<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EncryptedFile extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'encrypted_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'filename',
        'original_name',
        'file_path',
        'file_size',
        'encrypted_aes_key',
        'iv',
        'signature',
        'hash',
        'mime_type',
        'encryption_algorithm',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'encrypted_aes_key', // Ne pas exposer la clé chiffrée directement
        'file_path', // Ne pas exposer le chemin physique
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */

    /**
     * Relation N:1 - Propriétaire du fichier
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Relation 1:N - Accès accordés pour ce fichier
     */
    public function fileAccesses(): HasMany
    {
        return $this->hasMany(FileAccess::class, 'file_id');
    }

    /**
     * Relation 1:N - Accès actifs (non révoqués) uniquement
     */
    public function activeAccesses(): HasMany
    {
        return $this->hasMany(FileAccess::class, 'file_id')
            ->whereNull('revoked_at');
    }

    /**
     * Relation N:M - Utilisateurs ayant accès à ce fichier
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'file_accesses',
            'file_id',
            'user_id'
        )
            ->withPivot(['encrypted_aes_key', 'permission_level', 'granted_by', 'granted_at', 'revoked_at'])
            ->withTimestamps()
            ->whereNull('file_accesses.revoked_at'); // Seulement les accès actifs
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Vérifier si un utilisateur a accès à ce fichier
     */
    public function hasAccess(User $user): bool
    {
        // Le propriétaire a toujours accès
        if ($this->owner_id === $user->id) {
            return true;
        }

        // Vérifier si l'utilisateur a un accès actif
        return $this->activeAccesses()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Obtenir le niveau de permission d'un utilisateur
     */
    public function getUserPermission(User $user): ?string
    {
        if ($this->owner_id === $user->id) {
            return 'owner';
        }

        $access = $this->activeAccesses()
            ->where('user_id', $user->id)
            ->first();

        return $access?->permission_level;
    }
}
