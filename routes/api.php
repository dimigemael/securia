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
    // Les routes qui nécessitent une authentification par token (API externe)
    // peuvent être placées ici.
    // Pour l'instant, toutes les routes authentifiées sont gérées via la session
    // dans web.php pour l'application Inertia.
    // =========================================================================

});
