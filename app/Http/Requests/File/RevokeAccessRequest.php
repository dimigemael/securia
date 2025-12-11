<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class RevokeAccessRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        // La vérification de propriété sera faite dans le controller
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
        ];
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

            // Vérifier que le partage existe
            if ($this->has('file_id') && $this->has('user_id')) {
                $access = \App\Models\FileAccess::where('file_id', $this->file_id)
                    ->where('user_id', $this->user_id)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if (!$access) {
                    $validator->errors()->add('user_id', 'Cet utilisateur n\'a pas accès à ce fichier.');
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
        ];
    }
}
