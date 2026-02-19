<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class ShareFileRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        // La vérification de propriété du fichier sera faite dans le controller
        return auth()->check();
    }

    /**
     * Règles de validation pour la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_id' => ['required', 'integer', 'exists:encrypted_files,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'passphrase' => ['required_without:encrypted_key', 'string'],
            'encrypted_key' => ['sometimes', 'string'], // Clé AES re-chiffrée côté client
            'expires_at' => ['sometimes', 'date', 'after:now'],
            'can_reshare' => ['sometimes', 'boolean'], // L'utilisateur peut-il re-partager le fichier ?
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:read,download,decrypt'],
        ];
    }

    /**
     * Prépare les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        // Si pas de date d'expiration, définir à 30 jours par défaut
        if (!$this->has('expires_at')) {
            $this->merge([
                'expires_at' => now()->addDays(30),
            ]);
        }

        // Si pas de permissions, définir 'read' par défaut
        if (!$this->has('permissions')) {
            $this->merge([
                'permissions' => ['read'],
            ]);
        }
    }

    /**
     * Configure le validateur.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Vérifier que le fichier appartient à l'utilisateur
            if ($this->has('file_id')) {
                $file = \App\Models\EncryptedFile::where('id', $this->file_id)
                    ->where('owner_id', auth()->id())
                    ->first();

                if (!$file) {
                    $validator->errors()->add('file_id', 'Ce fichier ne vous appartient pas.');
                }
            }

            // Vérifier que l'utilisateur ne partage pas avec lui-même
            if ($this->has('user_id') && $this->user_id == auth()->id()) {
                $validator->errors()->add('user_id', 'Vous ne pouvez pas partager un fichier avec vous-même.');
            }

            // Vérifier que le partage n'existe pas déjà
            if ($this->has('file_id') && $this->has('user_id')) {
                $existingShare = \App\Models\FileAccess::where('file_id', $this->file_id)
                    ->where('user_id', $this->user_id)
                    ->whereNull('revoked_at')
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->exists();

                if ($existingShare) {
                    $validator->errors()->add('user_id', 'Ce fichier est déjà partagé avec cet utilisateur.');
                }
            }
        });
    }

    /**
     * Messages personnalisés pour les erreurs de validation.
     */
    public function messages(): array
    {
        return [
            'file_id.required' => 'L\'identifiant du fichier est obligatoire.',
            'file_id.exists' => 'Ce fichier n\'existe pas.',
            'user_id.required' => 'L\'identifiant de l\'utilisateur est obligatoire.',
            'user_id.exists' => 'Cet utilisateur n\'existe pas.',
            'expires_at.date' => 'La date d\'expiration doit être une date valide.',
            'expires_at.after' => 'La date d\'expiration doit être dans le futur.',
            'permissions.*.in' => 'Les permissions valides sont: read, download, decrypt.',
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'file_id' => 'identifiant du fichier',
            'user_id' => 'identifiant de l\'utilisateur',
            'expires_at' => 'date d\'expiration',
            'permissions' => 'permissions',
        ];
    }
}
