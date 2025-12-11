<?php

namespace App\Http\Requests\Key;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKeyRequest extends FormRequest
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
            'key_size' => ['required', 'integer', 'in:2048,4096'],
            'passphrase' => ['sometimes', 'string', 'min:8'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Messages personnalisés pour les erreurs de validation.
     */
    public function messages(): array
    {
        return [
            'key_size.required' => 'La taille de clé est obligatoire.',
            'key_size.in' => 'La taille de clé doit être 2048 ou 4096 bits.',
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
            'key_size' => 'taille de clé',
            'passphrase' => 'phrase de passe',
            'description' => 'description',
        ];
    }
}
