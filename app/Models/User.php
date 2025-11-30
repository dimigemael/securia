<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relations
     */

    /**
     * Relation 1:1 - Clés cryptographiques de l'utilisateur
     */
    public function userKey(): HasOne
    {
        return $this->hasOne(UserKey::class);
    }

    /**
     * Relation 1:N - Fichiers possédés par l'utilisateur
     */
    public function ownedFiles(): HasMany
    {
        return $this->hasMany(EncryptedFile::class, 'owner_id');
    }

    /**
     * Relation N:M - Fichiers auxquels l'utilisateur a accès
     */
    public function accessibleFiles()
    {
        return $this->belongsToMany(
            EncryptedFile::class,
            'file_accesses',
            'user_id',
            'file_id'
        )
            ->withPivot(['encrypted_aes_key', 'permission_level', 'granted_by', 'granted_at', 'revoked_at'])
            ->withTimestamps()
            ->whereNull('file_accesses.revoked_at'); // Seulement les accès actifs
    }

    /**
     * Relation 1:N - Journaux d'audit de l'utilisateur
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Relation 1:N - Accès aux fichiers accordés par cet utilisateur
     */
    public function grantedAccesses(): HasMany
    {
        return $this->hasMany(FileAccess::class, 'granted_by');
    }
}
