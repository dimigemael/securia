<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKey extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_keys';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'public_key',
        'encrypted_private_key',
        'key_algorithm',
        'key_size',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'encrypted_private_key', // Ne jamais exposer la clé privée (même chiffrée)
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_size' => 'integer',
        ];
    }

    /**
     * Relations
     */

    /**
     * Relation N:1 - Utilisateur propriétaire de cette clé
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
