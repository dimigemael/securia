<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\KeyManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected KeyManagementService $keyManagementService
    ) {}

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Créer l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Logger l'inscription
            $this->auditService->logRegistration($user);

            // Connecter automatiquement l'utilisateur
            Auth::login($user);

            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return redirect()->route('keys.generate')->with('success', 'Compte créé avec succès ! Veuillez maintenant générer vos clés de chiffrement.');
            }

            // Pour les requêtes API
            return response()->json([
                'message' => 'Utilisateur créé avec succès.',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['error' => 'Erreur lors de la création du compte: ' . $e->getMessage()]);
            }

            return response()->json([
                'message' => 'Erreur lors de la création de l\'utilisateur.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(LoginRequest $request)
    {
        try {
            // Authentifier l'utilisateur
            $request->authenticate();

            // Regénérer la session
            $request->session()->regenerate();

            $user = Auth::user();

            // Logger la connexion réussie
            $this->auditService->logLogin($user);

            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return redirect()->intended(route('dashboard'))->with('success', 'Connexion réussie !');
            }

            // Pour les requêtes API
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $token,
                'has_keys' => $this->keyManagementService->hasKeys($user),
            ]);

        } catch (\Exception $e) {
            // Logger l'échec de connexion
            $this->auditService->logLoginFailure(
                $request->email,
                $e->getMessage()
            );

            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['email' => $e->getMessage()]);
            }

            return response()->json([
                'message' => 'Échec de la connexion.',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Déconnexion d'un utilisateur
     */
    public function logout()
    {
        try {
            $user = Auth::user();

            if ($user) {
                // Révoquer tous les tokens de l'utilisateur (si existants)
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                // Logger la déconnexion
                $this->auditService->logLogout($user);
            }

            // Invalider la session
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            // Pour les requêtes Inertia (web)
            if (request()->header('X-Inertia')) {
                return redirect()->route('home')->with('success', 'Déconnexion réussie.');
            }

            // Pour les requêtes API
            return response()->json([
                'message' => 'Déconnexion réussie.',
            ]);

        } catch (\Exception $e) {
            // Pour les requêtes Inertia (web)
            if (request()->header('X-Inertia')) {
                return redirect()->route('home')->with('error', 'Erreur lors de la déconnexion : ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Erreur lors de la déconnexion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Demande de réinitialisation de mot de passe
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => 'Lien de réinitialisation envoyé par email.',
                ]);
            }

            return response()->json([
                'message' => 'Impossible d\'envoyer le lien de réinitialisation.',
                'error' => __($status),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi du lien de réinitialisation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => 'Mot de passe réinitialisé avec succès.',
                ]);
            }

            return response()->json([
                'message' => 'Impossible de réinitialiser le mot de passe.',
                'error' => __($status),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la réinitialisation du mot de passe.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir l'utilisateur actuellement connecté
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'user' => $user,
            'has_keys' => $this->keyManagementService->hasKeys($user),
        ]);
    }
}
