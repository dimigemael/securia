<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\EncryptedFile;

class DownloadFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'passphrase' => ['required', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fileId = $this->route('id');
            if ($fileId) {
                $file = EncryptedFile::find($fileId);

                if (!$file) {
                    $validator->errors()->add('file', 'Ce fichier n\'existe pas.');
                    return;
                }

                if (!$file->hasAccess(auth()->user())) {
                    $validator->errors()->add('file', 'Vous n\'avez pas accès à ce fichier.');
                }
            }
        });
    }
}
