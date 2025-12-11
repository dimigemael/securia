<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class DecryptFileRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        // La vérification des permissions sera faite dans le controller
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
            'passphrase' => ['sometimes', 'string'], // Pour déverrouiller la clé privée si nécessaire
            'save_decrypted' => ['sometimes', 'boolean'], // Sauvegarder le fichier déchiffré sur le serveur ?
        ];
    }

    /**
     * Configure le validateur.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('file_id')) {
                $file = \App\Models\EncryptedFile::find($this->file_id);

                if (!$file) {
                    return;
                }

                // Vérifier que l'utilisateur a accès au fichier
                $hasAccess = $file->owner_id === auth()->id() ||
                    \App\Models\FileAccess::where('file_id', $this->file_id)
                        ->where('user_id', auth()->id())
                        ->where(function($query) {
                            $query->whereNull('expires_at')
                                  ->orWhere('expires_at', '>', now());
                        })
                        ->exists();

                if (!$hasAccess) {
                    $validator->errors()->add('file_id', 'Vous n\'avez pas accès à ce fichier.');
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
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'file_id' => 'identifiant du fichier',
            'passphrase' => 'phrase de passe',
        ];
    }
}
