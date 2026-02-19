<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:102400', // 100 MB max
            ],
            'encrypt_immediately' => ['sometimes', 'boolean'],
            'passphrase' => ['required_if:encrypt_immediately,true', 'string'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Messages personnalisés pour les erreurs de validation.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier est obligatoire.',
            'file.file' => 'Le fichier téléchargé est invalide.',
            'file.max' => 'Le fichier ne peut pas dépasser 100 MB.',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères.',
            'tags.array' => 'Les tags doivent être un tableau.',
            'tags.*.max' => 'Chaque tag ne peut pas dépasser 50 caractères.',
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'file' => 'fichier',
            'description' => 'description',
            'tags' => 'tags',
        ];
    }
}
