<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\KeyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes API pour les actions lourdes et opérations JSON.
| Ces routes sont préfixées automatiquement par /api.
|
| Utilisées pour :
| - Authentification (login/register)
| - Upload/Download de fichiers
| - Opérations de chiffrement/déchiffrement
| - Actions CRUD qui retournent du JSON
|
*/

// =============================================================================
// Routes publiques (sans authentification)
// =============================================================================

Route::prefix('auth')->name('api.auth.')->group(function () {
    // Inscription
    Route::post('/register', [AuthController::class, 'register'])->name('register');

    // Connexion
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    // Réinitialisation de mot de passe
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

// =============================================================================
// Routes protégées (authentification requise via Sanctum)
// =============================================================================

Route::middleware('auth:sanctum')->group(function () {

    // =========================================================================
    // Authentification
    // =========================================================================

    Route::prefix('auth')->name('api.auth.')->group(function () {
        // Déconnexion
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Utilisateur connecté
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });

    // =========================================================================
    // Gestion des clés cryptographiques
    // =========================================================================

    Route::prefix('keys')->name('api.keys.')->group(function () {
        // Générer une nouvelle paire de clés
        Route::post('/generate', [KeyController::class, 'generate'])->name('generate');

        // Effectuer une rotation de clés
        Route::post('/rotate', [KeyController::class, 'rotate'])->name('rotate');

        // Importer une paire de clés existante
        Route::post('/import', [KeyController::class, 'import'])->name('import');

        // Supprimer les clés de l'utilisateur
        Route::delete('/', [KeyController::class, 'destroy'])->name('destroy');

        // Obtenir la clé privée chiffrée de l'utilisateur connecté (pour déchiffrement côté client)
        Route::get('/private-key', [KeyController::class, 'getEncryptedPrivateKey'])->name('private-key');

        // Obtenir la clé publique d'un autre utilisateur (pour partage)
        Route::get('/users/{userId}/public-key', [KeyController::class, 'getPublicKey'])->name('user-public-key');
    });

    // =========================================================================
    // Gestion des fichiers
    // =========================================================================

    Route::prefix('files')->name('api.files.')->group(function () {
        // Upload de fichier déjà chiffré côté client
        Route::post('/upload-encrypted', [FileController::class, 'uploadEncrypted'])->name('upload-encrypted');

        // Upload de fichier (avec chiffrement optionnel côté serveur - ancien système)
        Route::post('/upload', [FileController::class, 'upload'])->name('upload');

        // Chiffrer un fichier existant
        Route::post('/encrypt', [FileController::class, 'encrypt'])->name('encrypt');

        // Déchiffrer un fichier
        Route::post('/decrypt', [FileController::class, 'decrypt'])->name('decrypt');

        // Obtenir les détails d'un fichier (pour le partage)
        Route::get('/{id}', [FileController::class, 'getFileDetails'])->name('details');

        // Télécharger un fichier chiffré pour déchiffrement côté client
        Route::get('/{id}/download-encrypted', [FileController::class, 'downloadEncrypted'])->name('download-encrypted');

        // Télécharger un fichier déchiffré (ancien système - déchiffrement côté serveur)
        Route::get('/{id}/download', [FileController::class, 'download'])->name('download');

        // Partager un fichier avec un autre utilisateur
        Route::post('/share', [FileController::class, 'share'])->name('share');

        // Révoquer l'accès d'un utilisateur à un fichier
        Route::post('/revoke-access', [FileController::class, 'revokeAccess'])->name('revoke-access');

        // Supprimer un fichier
        Route::delete('/{id}', [FileController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // Recherche d'utilisateurs (pour le partage)
    // =========================================================================

    Route::get('/users/search', function () {
        $query = request('query');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'users' => []
            ]);
        }

        $users = \App\Models\User::where('id', '!=', auth()->id())
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
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

        return response()->json([
            'users' => $users
        ]);
    })->name('api.users.search');
});
