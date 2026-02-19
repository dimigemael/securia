<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\KeyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia.js)
|--------------------------------------------------------------------------
|
| Routes pour les pages de l'application utilisant Inertia.js + React.
| Ces routes retournent des composants React via Inertia::render().
|
*/

// =============================================================================
// Routes publiques (Guest)
// =============================================================================

Route::middleware('guest')->group(function () {

    // Page d'accueil / Landing
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    // Authentification
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');

    Route::post('/register', [AuthController::class, 'register'])->name('register.store');

    Route::get('/forgot-password', function () {
        return Inertia::render('Auth/ForgotPassword');
    })->name('password.request');

    Route::get('/reset-password/{token}', function (string $token) {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => request('email'),
        ]);
    })->name('password.reset');
});

// =============================================================================
// Routes protégées (Authentification requise)
// =============================================================================

Route::middleware(['auth'])->group(function () {

    // =========================================================================
    // Authentification
    // =========================================================================

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // =========================================================================
    // Dashboard
    // =========================================================================

    Route::get('/dashboard', function () {
        $user = auth()->user();
        $keyManagementService = app(\App\Services\KeyManagementService::class);
        $auditService = app(\App\Services\AuditService::class);

        return Inertia::render('Dashboard', [
            'hasKeys' => $keyManagementService->hasKeys($user),
            'statistics' => $auditService->getActivityStatistics($user),
            'recentLogs' => $auditService->getUserLogs($user, 10),
        ]);
    })->name('dashboard');

    // =========================================================================
    // Gestion des clés
    // =========================================================================

    Route::prefix('keys')->name('keys.')->group(function () {

        // Liste/Gestion des clés
        Route::get('/', function () {
            $user = auth()->user();
            $key = $user->userKey;

            return Inertia::render('Keys/Index', [
                'key' => $key ? [
                    'id' => $key->id,
                    'algorithm' => $key->key_algorithm,
                    'size' => $key->key_size,
                    'public_key' => $key->public_key,
                    'created_at' => $key->created_at,
                    'updated_at' => $key->updated_at,
                ] : null,
            ]);
        })->name('index');

        // Page de génération de clés
        Route::get('/generate', function () {
            return Inertia::render('Keys/Generate');
        })->name('generate');

        // Page d'import de clés
        Route::get('/import', function () {
            return Inertia::render('Keys/Import');
        })->name('import');

        // Page de rotation de clés
        Route::get('/rotate', function () {
            return Inertia::render('Keys/Rotate');
        })->name('rotate');

        // Actions sur les clés
        Route::post('/generate', [KeyController::class, 'generate'])->name('generate.store');
    });

    // =========================================================================
    // Gestion des fichiers
    // =========================================================================

    Route::prefix('files')->name('files.')->group(function () {

        // Liste des fichiers (possédés + partagés)
        Route::get('/', function () {
            $user = auth()->user();
            $fileAccessService = app(\App\Services\FileAccessService::class);
            $keyManagementService = app(\App\Services\KeyManagementService::class);

            // Fichiers possédés
            $ownedFiles = \App\Models\EncryptedFile::where('owner_id', $user->id)
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

            // Fichiers partagés
            $sharedFiles = $fileAccessService->listFilesSharedWithUser($user)
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

            // Récupérer la clé publique de l'utilisateur
            $publicKey = $keyManagementService->hasKeys($user)
                ? $keyManagementService->getPublicKey($user)
                : null;

            return Inertia::render('Files/Index', [
                'ownedFiles' => $ownedFiles,
                'sharedFiles' => $sharedFiles,
                'publicKey' => $publicKey,
                'hasKeys' => $keyManagementService->hasKeys($user),
            ]);
        })->name('index');

        // Page d'upload
        Route::get('/upload', function () {
            return Inertia::render('Files/Upload');
        })->name('upload');

        // Détails d'un fichier
        Route::get('/{id}', function (int $id) {
            $user = auth()->user();
            $file = \App\Models\EncryptedFile::findOrFail($id);

            // Vérifier l'accès
            if (!$file->hasAccess($user)) {
                abort(403, 'Vous n\'avez pas accès à ce fichier.');
            }

            $isOwner = $file->owner_id === $user->id;
            $fileAccessService = app(\App\Services\FileAccessService::class);

            $fileData = [
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

            // Ajouter les infos de partage si propriétaire
            if ($isOwner) {
                $fileData['shared_with'] = $file->sharedWith->map(function ($sharedUser) {
                    return [
                        'user_id' => $sharedUser->id,
                        'user_name' => $sharedUser->name,
                        'user_email' => $sharedUser->email,
                        'permission_level' => $sharedUser->pivot->permission_level,
                        'granted_at' => $sharedUser->pivot->granted_at,
                    ];
                });

                $fileData['access_statistics'] = $fileAccessService->getAccessStatistics($file);
            } else {
                $fileData['owner'] = [
                    'id' => $file->owner->id,
                    'name' => $file->owner->name,
                ];
            }

            return Inertia::render('Files/Show', [
                'file' => $fileData,
            ]);
        })->name('show');
    });

    // =========================================================================
    // Audit Logs
    // =========================================================================

    Route::get('/audit-logs', function () {
        $user = auth()->user();
        $auditService = app(\App\Services\AuditService::class);

        return Inertia::render('AuditLogs/Index', [
            'logs' => $auditService->getUserLogs($user, 50),
            'statistics' => $auditService->getActivityStatistics($user),
        ]);
    })->name('audit-logs.index');

    // =========================================================================
    // Profil utilisateur
    // =========================================================================

    Route::get('/profile', function () {
        return Inertia::render('Profile/Edit', [
            'user' => auth()->user(),
        ]);
    })->name('profile.edit');

    // =========================================================================
    // API Routes (protégées par session)
    // =========================================================================

    Route::prefix('api')->name('api.')->group(function () {
        // Authentification
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });

        // Gestion des clés cryptographiques
        Route::prefix('keys')->name('keys.')->group(function () {
            Route::post('/generate', [KeyController::class, 'generate'])->name('generate');
            Route::post('/rotate', [KeyController::class, 'rotate'])->name('rotate');
            Route::post('/import', [KeyController::class, 'import'])->name('import');
            Route::delete('/', [KeyController::class, 'destroy'])->name('destroy');
            Route::get('/private-key', [KeyController::class, 'getEncryptedPrivateKey'])->name('private-key');
            Route::get('/users/{userId}/public-key', [KeyController::class, 'getPublicKey'])->name('user-public-key');
        });

        // Gestion des fichiers
        Route::prefix('files')->name('files.')->group(function () {
            Route::post('/upload-encrypted', [FileController::class, 'uploadEncrypted'])->name('upload-encrypted');
            Route::post('/upload', [FileController::class, 'upload'])->name('upload');
            Route::post('/encrypt', [FileController::class, 'encrypt'])->name('encrypt');
            Route::post('/decrypt', [FileController::class, 'decrypt'])->name('decrypt');
            Route::get('/{id}', [FileController::class, 'getFileDetails'])->name('details');
            Route::get('/{id}/download-encrypted', [FileController::class, 'downloadEncrypted'])->name('download-encrypted');
            Route::post('/{id}/download', [FileController::class, 'download'])->name('download');
            Route::post('/share', [FileController::class, 'share'])->name('share');
            Route::post('/revoke-access', [FileController::class, 'revokeAccess'])->name('revoke-access');
            Route::delete('/{id}', [FileController::class, 'destroy'])->name('destroy');
        });

        // Recherche d'utilisateurs
        Route::get('/users/search', function () {
            $query = request('query');
            if (!$query || strlen($query) < 2) {
                return response()->json(['users' => []]);
            }
            $users = \App\Models\User::where('id', '!=', auth()->id())
                ->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email'])
                ->map(function ($user) {
                    $keyManagementService = app(\App\Services\KeyManagementService::class);
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'has_keys' => $keyManagementService->hasKeys($user),
                    ];
                });
            return response()->json(['users' => $users]);
        })->name('users.search');
    });
});
