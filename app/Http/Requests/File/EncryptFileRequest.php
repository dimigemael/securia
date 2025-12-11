<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class EncryptFileRequest extends FormRequest
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
            'key_id' => ['sometimes', 'integer', 'exists:user_keys,id'],
            'delete_original' => ['sometimes', 'boolean'], // Supprimer le fichier original après chiffrement ?
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

            // Vérifier que la clé appartient à l'utilisateur si spécifiée
            if ($this->has('key_id')) {
                $key = \App\Models\UserKey::where('id', $this->key_id)
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->first();

                if (!$key) {
                    $validator->errors()->add('key_id', 'Cette clé ne vous appartient pas ou n\'est pas active.');
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
            'key_id.exists' => 'Cette clé n\'existe pas.',
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'file_id' => 'identifiant du fichier',
            'key_id' => 'identifiant de la clé',
        ];
    }
}
