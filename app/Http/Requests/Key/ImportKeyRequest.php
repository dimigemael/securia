<?php

namespace App\Http\Requests\Key;

use Illuminate\Foundation\Http\FormRequest;

class ImportKeyRequest extends FormRequest
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
            'private_key' => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'passphrase' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string', 'max:500'],
            'set_as_active' => ['sometimes', 'boolean'], // Définir comme clé active ?
        ];
    }

    /**
     * Configure le validateur.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Vérifier que la clé privée est valide
            if ($this->has('private_key')) {
                $privateKey = openssl_pkey_get_private(
                    $this->private_key,
                    $this->input('passphrase', '')
                );

                if ($privateKey === false) {
                    $validator->errors()->add('private_key', 'La clé privée est invalide ou la phrase de passe est incorrecte.');
                } else {
                    openssl_free_key($privateKey);
                }
            }

            // Vérifier que la clé publique est valide
            if ($this->has('public_key')) {
                $publicKey = openssl_pkey_get_public($this->public_key);

                if ($publicKey === false) {
                    $validator->errors()->add('public_key', 'La clé publique est invalide.');
                } else {
                    openssl_free_key($publicKey);
                }
            }

            // Vérifier que les clés correspondent
            if ($this->has('private_key') && $this->has('public_key')) {
                $privateKey = openssl_pkey_get_private(
                    $this->private_key,
                    $this->input('passphrase', '')
                );
                $publicKey = openssl_pkey_get_public($this->public_key);

                if ($privateKey !== false && $publicKey !== false) {
                    // Extraire la clé publique de la clé privée
                    $details = openssl_pkey_get_details($privateKey);
                    $extractedPublicKey = $details['key'];

                    // Comparer avec la clé publique fournie
                    if (trim($extractedPublicKey) !== trim($this->public_key)) {
                        $validator->errors()->add('public_key', 'La clé publique ne correspond pas à la clé privée.');
                    }

                    openssl_free_key($privateKey);
                    openssl_free_key($publicKey);
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
            'private_key.required' => 'La clé privée est obligatoire.',
            'public_key.required' => 'La clé publique est obligatoire.',
            'description.max' => 'La description ne peut pas dépasser 500 caractères.',
        ];
    }

    /**
     * Attributs personnalisés pour les erreurs de validation.
     */
    public function attributes(): array
    {
        return [
            'private_key' => 'clé privée',
            'public_key' => 'clé publique',
            'passphrase' => 'phrase de passe',
            'description' => 'description',
        ];
    }
}
