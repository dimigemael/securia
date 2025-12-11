<?php

namespace App\Http\Requests\Key;

use Illuminate\Foundation\Http\FormRequest;

class RotateKeyRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
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
            'old_key_id' => ['required', 'integer', 'exists:user_keys,id'],
            'new_key_size' => ['required', 'integer', 'in:2048,4096'],
            'passphrase' => ['sometimes', 'string', 'min:8'],
            'description' => ['sometimes', 'string', 'max:500'],
            're_encrypt_files' => ['sometimes', 'boolean'], // Re-chiffrer automatiquement les fichiers ?
        ];
    }

    /**
     * Prépare les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        // S'assurer que old_key_id appartient bien à l'utilisateur connecté
        if ($this->has('old_key_id')) {
            $this->merge([
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Configure le validateur.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('old_key_id')) {
                $key = \App\Models\UserKey::where('id', $this->old_key_id)
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$key) {
                    $validator->errors()->add('old_key_id', 'Cette clé ne vous appartient pas.');
                } elseif (!$key->is_active) {
                    $validator->errors()->add('old_key_id', 'Cette clé n\'est plus active.');
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
            'old_key_id.required' => 'L\'identifiant de l\'ancienne clé est obligatoire.',
            'old_key_id.exists' => 'Cette clé n\'existe pas.',
            'new_key_size.required' => 'La taille de la nouvelle clé est obligatoire.',
            'new_key_size.in' => 'La taille de clé doit être 2048 ou 4096 bits.',
            'passphrase.min' => 'La phrase de passe doit contenir au moins 8 caractères.',
            'description.max' => 'La description ne peut pas dépasser 500 caractères.',
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'old_key_id' => 'ancienne clé',
            'new_key_size' => 'taille de la nouvelle clé',
            'passphrase' => 'phrase de passe',
            'description' => 'description',
        ];
    }
}
