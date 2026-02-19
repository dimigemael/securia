<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\DecryptFileRequest;
use App\Http\Requests\File\DownloadFileRequest;
use App\Http\Requests\File\EncryptFileRequest;
use App\Http\Requests\File\RevokeAccessRequest;
use App\Http\Requests\File\ShareFileRequest;
use App\Http\Requests\File\UploadFileRequest;
use App\Models\EncryptedFile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FileAccessService;
use App\Services\FileEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileController extends Controller
{
    public function __construct(
        protected FileEncryptionService $fileEncryptionService,
        protected FileAccessService $fileAccessService,
        protected AuditService $auditService
    ) {}

    /**
     * Uploader un fichier déjà chiffré côté client
     */
    public function uploadEncrypted(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Validation
            $request->validate([
                'encrypted_file' => 'required|file',
                'encrypted_key' => 'required|string',
                'iv' => 'required|string',
                'original_name' => 'required|string',
                'original_size' => 'required|integer',
                'mime_type' => 'required|string',
                'algorithm' => 'required|string',
            ]);

            // Vérifier que l'utilisateur a des clés
            if (!app(\App\Services\KeyManagementService::class)->hasKeys($user)) {
                return response()->json([
                    'message' => 'Vous devez générer des clés avant de pouvoir uploader des fichiers.',
                ], 400);
            }

            // Récupérer le fichier chiffré
            $encryptedFile = $request->file('encrypted_file');

            // Générer un nom de fichier unique
            $filename = uniqid() . '_' . hash('sha256', $request->input('original_name')) . '.enc';

            // Stocker le fichier chiffré
            $filePath = $encryptedFile->storeAs('encrypted_files/' . $user->id, $filename);

            // Calculer le hash du fichier chiffré
            $hash = hash_file('sha256', $encryptedFile->getRealPath());

            // Créer l'enregistrement en base de données
            $encryptedFileRecord = EncryptedFile::create([
                'owner_id' => $user->id,
                'filename' => $filename,
                'original_name' => $request->input('original_name'),
                'file_path' => $filePath,
                'file_size' => $encryptedFile->getSize(),
                'mime_type' => $request->input('mime_type'),
                'hash' => $hash,
                'encryption_algorithm' => $request->input('algorithm'),
                'encrypted_aes_key' => $request->input('encrypted_key'),
                'iv' => $request->input('iv'),
                'signature' => '', // Signature vide pour l'instant
            ]);

            // Logger l'action
            $this->auditService->logFileEncryption($user, $encryptedFileRecord->id, [
                'filename' => $request->input('original_name'),
                'size' => $request->input('original_size'),
                'mime_type' => $request->input('mime_type'),
                'algorithm' => $request->input('algorithm'),
            ]);

            return response()->json([
                'message' => 'Fichier uploadé et stocké avec succès.',
                'file' => [
                    'id' => $encryptedFileRecord->id,
                    'original_name' => $encryptedFileRecord->original_name,
                    'file_size' => $encryptedFileRecord->file_size,
                    'mime_type' => $encryptedFileRecord->mime_type,
                    'encryption_algorithm' => $encryptedFileRecord->encryption_algorithm,
                    'created_at' => $encryptedFileRecord->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'upload du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uploader et chiffrer un fichier (ancien système - chiffrement côté serveur)
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = $request->file('file');

            // Vérifier que l'utilisateur a des clés
            if (!app(\App\Services\KeyManagementService::class)->hasKeys($user)) {
                return response()->json([
                    'message' => 'Vous devez générer des clés avant de pouvoir chiffrer des fichiers.',
                ], 400);
            }

            // Chiffrer le fichier immédiatement si demandé
            if ($request->boolean('encrypt_immediately', true)) {
                $passphrase = $request->input('passphrase');
                $encryptedFile = $this->fileEncryptionService->encryptFile(
                    $file,
                    $user,
                    $passphrase
                );

                // Logger l'action
                $this->auditService->logFileEncryption($user, $encryptedFile->id, [
                    'filename' => $encryptedFile->original_name,
                    'size' => $encryptedFile->file_size,
                    'mime_type' => $encryptedFile->mime_type,
                ]);

                return response()->json([
                    'message' => 'Fichier uploadé et chiffré avec succès.',
                    'file' => [
                        'id' => $encryptedFile->id,
                        'original_name' => $encryptedFile->original_name,
                        'file_size' => $encryptedFile->file_size,
                        'mime_type' => $encryptedFile->mime_type,
                        'encryption_algorithm' => $encryptedFile->encryption_algorithm,
                        'created_at' => $encryptedFile->created_at,
                    ],
                ], 201);
            }

            // Sinon, stocker le fichier sans chiffrement (pour chiffrement ultérieur)
            $filename = $file->hashName();
            $filePath = $file->storeAs('files/' . $user->id, $filename);

            $encryptedFile = EncryptedFile::create([
                'owner_id' => $user->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'hash' => hash_file('sha256', $file->getRealPath()),
            ]);

            return response()->json([
                'message' => 'Fichier uploadé avec succès (non chiffré).',
                'file' => [
                    'id' => $encryptedFile->id,
                    'original_name' => $encryptedFile->original_name,
                    'file_size' => $encryptedFile->file_size,
                    'mime_type' => $encryptedFile->mime_type,
                    'created_at' => $encryptedFile->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'upload du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chiffrer un fichier existant
     */
    public function encrypt(EncryptFileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $fileId = $request->input('file_id');

            $file = EncryptedFile::where('id', $fileId)
                ->where('owner_id', $user->id)
                ->firstOrFail();

            // Créer un UploadedFile temporaire à partir du fichier stocké
            $tempFile = new \Illuminate\Http\UploadedFile(
                Storage::path($file->file_path),
                $file->original_name,
                $file->mime_type,
                null,
                true
            );

            $passphrase = $request->input('passphrase');

            // Chiffrer le fichier
            $encryptedFile = $this->fileEncryptionService->encryptFile(
                $tempFile,
                $user,
                $passphrase
            );

            // Supprimer l'ancien fichier non chiffré si demandé
            if ($request->boolean('delete_original', true)) {
                Storage::delete($file->file_path);
                $file->delete();
            }

            // Logger l'action
            $this->auditService->logFileEncryption($user, $encryptedFile->id, [
                'filename' => $encryptedFile->original_name,
                'original_file_id' => $fileId,
            ]);

            return response()->json([
                'message' => 'Fichier chiffré avec succès.',
                'file' => [
                    'id' => $encryptedFile->id,
                    'original_name' => $encryptedFile->original_name,
                    'file_size' => $encryptedFile->file_size,
                    'encryption_algorithm' => $encryptedFile->encryption_algorithm,
                    'created_at' => $encryptedFile->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chiffrement du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Déchiffrer et télécharger un fichier
     */
    public function decrypt(DecryptFileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $fileId = $request->input('file_id');
            $passphrase = $request->input('passphrase');

            $file = EncryptedFile::findOrFail($fileId);

            // Vérifier l'accès
            if (!$file->hasAccess($user)) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à ce fichier.',
                ], 403);
            }

            if (!$passphrase) {
                return response()->json([
                    'message' => 'La phrase de passe est requise pour le déchiffrement.',
                ], 400);
            }

            // Déchiffrer le fichier
            $decryptedContent = $this->fileEncryptionService->decryptFile(
                $file,
                $user,
                $passphrase
            );

            // Logger l'action
            $this->auditService->logFileDecryption($user, $fileId, [
                'filename' => $file->original_name,
            ]);

            // Sauvegarder le fichier déchiffré si demandé
            if ($request->boolean('save_decrypted', false)) {
                $decryptedPath = 'decrypted_files/' . $user->id . '/' . $file->original_name;
                Storage::put($decryptedPath, $decryptedContent);

                return response()->json([
                    'message' => 'Fichier déchiffré et sauvegardé avec succès.',
                    'file' => [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'decrypted_path' => $decryptedPath,
                    ],
                ]);
            }

            // Retourner le fichier déchiffré pour téléchargement
            return response()->json([
                'message' => 'Fichier déchiffré avec succès.',
                'file' => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'content' => base64_encode($decryptedContent),
                    'mime_type' => $file->mime_type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du déchiffrement du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Partager un fichier avec un autre utilisateur
     */
    public function share(ShareFileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $fileId = $request->input('file_id');
            $recipientId = $request->input('user_id');

            $file = EncryptedFile::where('id', $fileId)
                ->where('owner_id', $user->id)
                ->firstOrFail();

            $recipient = User::findOrFail($recipientId);

            $permissionLevel = implode(',', $request->input('permissions', ['read']));

            // Si encrypted_key fournie (chiffrement côté client), créer l'accès directement
            if ($request->has('encrypted_key')) {
                $fileAccess = \App\Models\FileAccess::create([
                    'file_id' => $fileId,
                    'user_id' => $recipientId,
                    'encrypted_aes_key' => $request->input('encrypted_key'),
                    'permission_level' => $permissionLevel,
                    'granted_by' => $user->id,
                    'granted_at' => now(),
                    'expires_at' => $request->input('expires_at', now()->addDays(30)),
                    'can_reshare' => $request->input('can_reshare', false),
                ]);
            } else {
                // Sinon utiliser le service (chiffrement côté serveur - ancien système)
                $passphrase = $request->input('passphrase');
                $fileAccess = $this->fileAccessService->shareFile(
                    $file,
                    $user,
                    $recipient,
                    $passphrase,
                    $permissionLevel
                );
            }

            // Logger l'action
            $this->auditService->logFileShare(
                $user,
                $fileId,
                $recipientId,
                $fileAccess->permission_level
            );

            return response()->json([
                'message' => 'Fichier partagé avec succès.',
                'access' => [
                    'file_id' => $fileId,
                    'user_id' => $recipientId,
                    'user_name' => $recipient->name,
                    'permission_level' => $fileAccess->permission_level,
                    'expires_at' => $fileAccess->expires_at,
                    'granted_at' => $fileAccess->granted_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du partage du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Révoquer l'accès d'un utilisateur à un fichier
     */
    public function revokeAccess(RevokeAccessRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $fileId = $request->input('file_id');
            $userId = $request->input('user_id');

            $file = EncryptedFile::where('id', $fileId)
                ->where('owner_id', $user->id)
                ->firstOrFail();

            $targetUser = User::findOrFail($userId);

            // Révoquer l'accès
            $this->fileAccessService->revokeAccess($file, $user, $targetUser);

            // Logger l'action
            $this->auditService->logFileRevoke($user, $fileId, $userId);

            return response()->json([
                'message' => 'Accès révoqué avec succès.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la révocation de l\'accès.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher la page de gestion des fichiers (Inertia)
     */
    public function showPage()
    {
        try {
            $user = Auth::user();

            // Fichiers possédés
            $ownedFiles = EncryptedFile::where('owner_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'encryption_algorithm' => $file->encryption_algorithm,
                        'created_at' => $file->created_at,
                        'is_owner' => true,
                    ];
                });

            // Fichiers partagés avec l'utilisateur
            $sharedFiles = $this->fileAccessService->listFilesSharedWithUser($user)
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'encryption_algorithm' => $file->encryption_algorithm,
                        'created_at' => $file->created_at,
                        'is_owner' => false,
                        'owner_name' => $file->owner->name,
                    ];
                });

            return Inertia::render('Files/Index', [
                'ownedFiles' => $ownedFiles,
                'sharedFiles' => $sharedFiles,
            ]);

        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Erreur lors de la récupération des fichiers.');
        }
    }

    /**
     * Lister les fichiers de l'utilisateur (API JSON)
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Fichiers possédés
            $ownedFiles = EncryptedFile::where('owner_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'encryption_algorithm' => $file->encryption_algorithm,
                        'created_at' => $file->created_at,
                        'is_owner' => true,
                    ];
                });

            // Fichiers partagés avec l'utilisateur
            $sharedFiles = $this->fileAccessService->listFilesSharedWithUser($user)
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'encryption_algorithm' => $file->encryption_algorithm,
                        'created_at' => $file->created_at,
                        'is_owner' => false,
                        'owner_name' => $file->owner->name,
                    ];
                });

            return response()->json([
                'owned_files' => $ownedFiles,
                'shared_files' => $sharedFiles,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des fichiers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher les détails d'un fichier
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = EncryptedFile::findOrFail($id);

            // Vérifier l'accès
            if (!$file->hasAccess($user)) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à ce fichier.',
                ], 403);
            }

            $isOwner = $file->owner_id === $user->id;

            $response = [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'encryption_algorithm' => $file->encryption_algorithm,
                'hash' => $file->hash,
                'created_at' => $file->created_at,
                'updated_at' => $file->updated_at,
                'is_owner' => $isOwner,
                'permission' => $file->getUserPermission($user),
            ];

            // Si propriétaire, inclure la clé AES chiffrée (nécessaire pour le partage)
            if ($isOwner) {
                $response['encrypted_aes_key'] = $file->encrypted_aes_key;

                $response['shared_with'] = $file->sharedWith->map(function ($sharedUser) {
                    return [
                        'user_id' => $sharedUser->id,
                        'user_name' => $sharedUser->name,
                        'permission_level' => $sharedUser->pivot->permission_level,
                        'granted_at' => $sharedUser->pivot->granted_at,
                    ];
                });

                $response['access_statistics'] = $this->fileAccessService->getAccessStatistics($file);
            } else {
                $response['owner'] = [
                    'id' => $file->owner->id,
                    'name' => $file->owner->name,
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un fichier
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $file = EncryptedFile::where('id', $id)
                ->where('owner_id', $user->id)
                ->firstOrFail();

            // Supprimer le fichier
            $this->fileEncryptionService->deleteFile($file, $user);

            // Logger l'action
            $this->auditService->logFileDelete($user, $id, [
                'filename' => $file->original_name,
            ]);

            return response()->json([
                'message' => 'Fichier supprimé avec succès.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les détails d'un fichier (pour le partage)
     */
    public function getFileDetails(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = EncryptedFile::findOrFail($id);

            // Vérifier l'accès
            if (!$file->hasAccess($user)) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à ce fichier.',
                ], 403);
            }

            $isOwner = $file->owner_id === $user->id;

            // Récupérer la clé AES chiffrée appropriée
            if ($isOwner) {
                // Si propriétaire, retourner sa propre clé AES chiffrée
                $encryptedAesKey = $file->encrypted_aes_key;
            } else {
                // Si partagé, retourner la clé AES chiffrée pour cet utilisateur
                $fileAccess = \App\Models\FileAccess::where('file_id', $file->id)
                    ->where('user_id', $user->id)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->firstOrFail();

                $encryptedAesKey = $fileAccess->encrypted_aes_key;
            }

            return response()->json([
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'encryption_algorithm' => $file->encryption_algorithm,
                'encrypted_aes_key' => $encryptedAesKey,
                'iv' => $file->iv,
                'hash' => $file->hash,
                'is_owner' => $isOwner,
                'created_at' => $file->created_at,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des détails du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Télécharger un fichier chiffré avec métadonnées pour déchiffrement côté client
     */
    public function downloadEncrypted(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = EncryptedFile::findOrFail($id);

            // Vérifier l'accès
            if (!$file->hasAccess($user)) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à ce fichier.',
                ], 403);
            }

            // Lire le fichier chiffré
            $encryptedContent = Storage::get($file->file_path);

            // Logger l'action
            $this->auditService->logFileDecryption($user, $id, [
                'filename' => $file->original_name,
                'action' => 'download',
            ]);

            // Récupérer la clé AES chiffrée appropriée pour l'utilisateur
            $isOwner = $file->owner_id === $user->id;
            if ($isOwner) {
                $encryptedAesKey = $file->encrypted_aes_key;
            } else {
                $fileAccess = \App\Models\FileAccess::where('file_id', $file->id)
                    ->where('user_id', $user->id)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->firstOrFail();

                $encryptedAesKey = $fileAccess->encrypted_aes_key;
            }

            // Retourner le fichier chiffré avec métadonnées
            return response()->json([
                'file' => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'encrypted_content' => base64_encode($encryptedContent),
                    'encrypted_key' => $encryptedAesKey,
                    'iv' => $file->iv,
                    'algorithm' => $file->encryption_algorithm,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du téléchargement du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Télécharger un fichier déchiffré (ancien système - déchiffrement côté serveur)
     */
    public function download(DownloadFileRequest $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        try {
            $user = Auth::user();
            $file = EncryptedFile::findOrFail($id);
            $passphrase = $request->input('passphrase');

            // Déchiffrer le fichier
            $decryptedContent = $this->fileEncryptionService->decryptFile(
                $file,
                $user,
                $passphrase
            );

            // Logger l'action
            $this->auditService->logFileDecryption($user, $id, [
                'filename' => $file->original_name,
                'action' => 'download',
            ]);

            // Retourner le fichier pour téléchargement
            return response()->streamDownload(
                function () use ($decryptedContent) {
                    echo $decryptedContent;
                },
                $file->original_name,
                [
                    'Content-Type' => $file->mime_type,
                ]
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du téléchargement du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
