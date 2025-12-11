<?php

namespace App\Http\Controllers;

use App\Http\Requests\Key\GenerateKeyRequest;
use App\Http\Requests\Key\ImportKeyRequest;
use App\Http\Requests\Key\RotateKeyRequest;
use App\Models\UserKey;
use App\Services\AuditService;
use App\Services\KeyManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KeyController extends Controller
{
    public function __construct(
        protected KeyManagementService $keyManagementService,
        protected AuditService $auditService
    ) {}

    /**
     * Générer une nouvelle paire de clés pour l'utilisateur
     */
    public function generate(GenerateKeyRequest $request)
    {
        try {
            $user = Auth::user();

            // Vérifier si l'utilisateur a déjà des clés
            if ($this->keyManagementService->hasKeys($user)) {
                if ($request->header('X-Inertia')) {
                    return back()->with('error', 'Vous avez déjà des clés. Utilisez la rotation pour en créer de nouvelles.');
                }
                return response()->json([
                    'message' => 'L\'utilisateur a déjà des clés. Utilisez la rotation pour en créer de nouvelles.',
                ], 400);
            }

            // Générer les clés avec la passphrase fournie
            $userKey = $this->keyManagementService->generateKeysForUser(
                $user,
                $request->input('passphrase'),
                $request->input('key_size', 2048)
            );

            // Logger l'action
            $this->auditService->logKeyGeneration($user, [
                'key_size' => $request->input('key_size', 2048),
                'description' => $request->input('description'),
            ]);

            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return redirect()->route('dashboard')->with('success', 'Clés générées avec succès !');
            }

            // Pour les requêtes API
            return response()->json([
                'message' => 'Clés générées avec succès.',
                'key' => [
                    'id' => $userKey->id,
                    'algorithm' => $userKey->key_algorithm,
                    'size' => $userKey->key_size,
                    'public_key' => $userKey->public_key,
                    'created_at' => $userKey->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            // Pour les requêtes Inertia (web)
            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Erreur lors de la génération des clés : ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Erreur lors de la génération des clés.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Effectuer une rotation de clés
     */
    public function rotate(RotateKeyRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $oldKeyId = $request->input('old_key_id');

            // Récupérer l'ancienne clé
            $oldKey = UserKey::where('id', $oldKeyId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Générer la nouvelle paire de clés
            $newKey = $this->keyManagementService->generateKeysForUser(
                $user,
                $user->password,
                $request->input('new_key_size', 2048)
            );

            // Si re-chiffrement automatique demandé
            if ($request->boolean('re_encrypt_files')) {
                // TODO: Implémenter le re-chiffrement des fichiers
                // Cette fonctionnalité nécessite de déchiffrer tous les fichiers
                // avec l'ancienne clé et les re-chiffrer avec la nouvelle
            }

            // Désactiver l'ancienne clé (ajouter un champ is_active dans la migration)
            // $oldKey->update(['is_active' => false]);

            // Logger l'action
            $this->auditService->log(
                AuditService::ACTION_KEY_GENERATE,
                $user,
                'UserKey',
                $newKey->id,
                [
                    'action' => 'rotation',
                    'old_key_id' => $oldKeyId,
                    'new_key_size' => $request->input('new_key_size', 2048),
                    'description' => $request->input('description'),
                ]
            );

            return response()->json([
                'message' => 'Rotation de clés effectuée avec succès.',
                'old_key_id' => $oldKeyId,
                'new_key' => [
                    'id' => $newKey->id,
                    'algorithm' => $newKey->key_algorithm,
                    'size' => $newKey->key_size,
                    'public_key' => $newKey->public_key,
                    'created_at' => $newKey->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la rotation des clés.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Importer une paire de clés existante
     */
    public function import(ImportKeyRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier si l'utilisateur a déjà des clés
            if ($this->keyManagementService->hasKeys($user)) {
                return response()->json([
                    'message' => 'L\'utilisateur a déjà des clés. Supprimez-les d\'abord.',
                ], 400);
            }

            // Obtenir les détails de la clé privée pour extraire la taille
            $privateKeyResource = openssl_pkey_get_private(
                $request->input('private_key'),
                $request->input('passphrase', '')
            );

            if ($privateKeyResource === false) {
                return response()->json([
                    'message' => 'Impossible de lire la clé privée.',
                ], 400);
            }

            $keyDetails = openssl_pkey_get_details($privateKeyResource);
            $keySize = $keyDetails['bits'];
            openssl_free_key($privateKeyResource);

            // Chiffrer la clé privée pour le stockage
            $cryptoService = app(\App\Services\CryptoService::class);
            $derived = $cryptoService->deriveKeyFromPassword($user->password);
            $encryptedPrivateKey = $cryptoService->encryptAES(
                $request->input('private_key'),
                $derived['key']
            );

            $encryptedPrivateKeyData = json_encode([
                'ciphertext' => $encryptedPrivateKey['ciphertext'],
                'iv' => $encryptedPrivateKey['iv'],
                'tag' => $encryptedPrivateKey['tag'],
                'salt' => $derived['salt'],
            ]);

            // Créer l'enregistrement de clé
            $userKey = UserKey::create([
                'user_id' => $user->id,
                'public_key' => $request->input('public_key'),
                'encrypted_private_key' => $encryptedPrivateKeyData,
                'key_algorithm' => 'RSA',
                'key_size' => $keySize,
            ]);

            // Logger l'action
            $this->auditService->log(
                AuditService::ACTION_KEY_GENERATE,
                $user,
                'UserKey',
                $userKey->id,
                [
                    'action' => 'import',
                    'key_size' => $keySize,
                    'description' => $request->input('description'),
                ]
            );

            return response()->json([
                'message' => 'Clés importées avec succès.',
                'key' => [
                    'id' => $userKey->id,
                    'algorithm' => $userKey->key_algorithm,
                    'size' => $userKey->key_size,
                    'public_key' => $userKey->public_key,
                    'created_at' => $userKey->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'import des clés.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lister toutes les clés de l'utilisateur
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $key = $user->userKey;

            if (!$key) {
                return response()->json([
                    'message' => 'Aucune clé trouvée.',
                    'key' => null,
                ]);
            }

            return response()->json([
                'key' => [
                    'id' => $key->id,
                    'algorithm' => $key->key_algorithm,
                    'size' => $key->key_size,
                    'public_key' => $key->public_key,
                    'created_at' => $key->created_at,
                    'updated_at' => $key->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des clés.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une clé spécifique
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $key = UserKey::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return response()->json([
                'key' => [
                    'id' => $key->id,
                    'algorithm' => $key->key_algorithm,
                    'size' => $key->key_size,
                    'public_key' => $key->public_key,
                    'created_at' => $key->created_at,
                    'updated_at' => $key->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Clé non trouvée.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Supprimer les clés de l'utilisateur
     */
    public function destroy(): JsonResponse
    {
        try {
            $user = Auth::user();

            $this->keyManagementService->deleteKeys($user);

            // Logger l'action
            $this->auditService->log(
                'key_delete',
                $user,
                'UserKey',
                $user->id,
                ['action' => 'delete']
            );

            return response()->json([
                'message' => 'Clés supprimées avec succès.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression des clés.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir la clé privée chiffrée de l'utilisateur connecté
     */
    public function getEncryptedPrivateKey(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->keyManagementService->hasKeys($user)) {
                return response()->json([
                    'message' => 'Vous n\'avez pas de clés.',
                ], 404);
            }

            $userKey = $user->userKey;

            return response()->json([
                'encrypted_private_key' => $userKey->encrypted_private_key,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la clé privée.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir la clé publique d'un autre utilisateur (pour le partage)
     */
    public function getPublicKey(int $userId): JsonResponse
    {
        try {
            $targetUser = \App\Models\User::findOrFail($userId);

            if (!$this->keyManagementService->hasKeys($targetUser)) {
                return response()->json([
                    'message' => 'Cet utilisateur n\'a pas de clés.',
                ], 404);
            }

            $publicKey = $this->keyManagementService->getPublicKey($targetUser);

            return response()->json([
                'user_id' => $targetUser->id,
                'user_name' => $targetUser->name,
                'public_key' => $publicKey,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la clé publique.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
