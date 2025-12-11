<?php

namespace App\Services;

use Exception;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * Service pour toutes les opérations cryptographiques
 * Gère le chiffrement/déchiffrement AES et RSA, signatures, hash
 */
class CryptoService
{
    /**
     * Générer une paire de clés RSA
     *
     * @param int $keySize Taille de la clé (2048, 4096)
     * @return array ['publicKey' => string, 'privateKey' => string]
     * @throws Exception
     */
    public function generateRSAKeyPair(int $keySize = 2048): array
    {
        try {
            $private = RSA::createKey($keySize);
            $public = $private->getPublicKey();

            return [
                'publicKey' => $public->toString('PKCS8'),
                'privateKey' => $private->toString('PKCS8'),
            ];
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la génération de la paire RSA: " . $e->getMessage());
        }
    }

    /**
     * Générer une clé AES-256 aléatoire
     *
     * @return string Clé AES en base64
     * @throws Exception
     */
    public function generateAESKey(): string
    {
        try {
            // Générer 32 bytes (256 bits) aléatoires
            $key = random_bytes(32);
            return base64_encode($key);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la génération de la clé AES: " . $e->getMessage());
        }
    }

    /**
     * Chiffrer des données avec AES-256-GCM
     *
     * @param string $data Données à chiffrer
     * @param string $key Clé AES en base64
     * @return array ['ciphertext' => string, 'iv' => string, 'tag' => string]
     * @throws Exception
     */
    public function encryptAES(string $data, string $key): array
    {
        try {
            $aesKey = base64_decode($key);

            // Générer un IV (Initialization Vector) aléatoire
            $iv = random_bytes(16);

            // Chiffrer avec AES-256-GCM
            $ciphertext = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($ciphertext === false) {
                throw new Exception("Échec du chiffrement AES");
            }

            return [
                'ciphertext' => base64_encode($ciphertext),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
            ];
        } catch (Exception $e) {
            throw new Exception("Erreur lors du chiffrement AES: " . $e->getMessage());
        }
    }

    /**
     * Déchiffrer des données avec AES-256-GCM
     *
     * @param string $ciphertext Données chiffrées (base64)
     * @param string $key Clé AES (base64)
     * @param string $iv IV (base64)
     * @param string $tag Tag d'authentification (base64)
     * @return string Données déchiffrées
     * @throws Exception
     */
    public function decryptAES(string $ciphertext, string $key, string $iv, string $tag): string
    {
        try {
            $aesKey = base64_decode($key);
            $ciphertextRaw = base64_decode($ciphertext);
            $ivRaw = base64_decode($iv);
            $tagRaw = base64_decode($tag);

            $plaintext = openssl_decrypt(
                $ciphertextRaw,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $ivRaw,
                $tagRaw
            );

            if ($plaintext === false) {
                throw new Exception("Échec du déchiffrement AES (tag invalide ou données corrompues)");
            }

            return $plaintext;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du déchiffrement AES: " . $e->getMessage());
        }
    }

    /**
     * Chiffrer des données avec une clé publique RSA
     *
     * @param string $data Données à chiffrer
     * @param string $publicKey Clé publique RSA (format PKCS8)
     * @return string Données chiffrées en base64
     * @throws Exception
     */
    public function encryptRSA(string $data, string $publicKey): string
    {
        try {
            $key = PublicKeyLoader::load($publicKey)->withPadding(RSA::ENCRYPTION_OAEP);
            $encrypted = $key->encrypt($data);
            return base64_encode($encrypted);
        } catch (Exception $e) {
            throw new Exception("Erreur lors du chiffrement RSA: " . $e->getMessage());
        }
    }

    /**
     * Déchiffrer des données avec une clé privée RSA
     *
     * @param string $encryptedData Données chiffrées (base64)
     * @param string $privateKey Clé privée RSA (format PKCS8)
     * @return string Données déchiffrées
     * @throws Exception
     */
    public function decryptRSA(string $encryptedData, string $privateKey): string
    {
        try {
            $key = PublicKeyLoader::load($privateKey)->withPadding(RSA::ENCRYPTION_OAEP);
            $encrypted = base64_decode($encryptedData);
            $decrypted = $key->decrypt($encrypted);

            if ($decrypted === false) {
                throw new Exception("Déchiffrement RSA échoué");
            }

            return $decrypted;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du déchiffrement RSA: " . $e->getMessage());
        }
    }

    /**
     * Générer le hash SHA-256 d'un fichier
     *
     * @param string $filePath Chemin du fichier
     * @return string Hash en hexadécimal
     * @throws Exception
     */
    public function hashFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new Exception("Fichier introuvable: {$filePath}");
        }

        $hash = hash_file('sha256', $filePath);

        if ($hash === false) {
            throw new Exception("Impossible de calculer le hash du fichier");
        }

        return $hash;
    }

    /**
     * Signer des données avec une clé privée RSA
     *
     * @param string $data Données à signer
     * @param string $privateKey Clé privée RSA
     * @return string Signature en base64
     * @throws Exception
     */
    public function signData(string $data, string $privateKey): string
    {
        try {
            $key = PublicKeyLoader::load($privateKey)->withPadding(RSA::SIGNATURE_PSS);
            $signature = $key->sign($data);
            return base64_encode($signature);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la signature: " . $e->getMessage());
        }
    }

    /**
     * Vérifier une signature avec une clé publique RSA
     *
     * @param string $data Données originales
     * @param string $signature Signature (base64)
     * @param string $publicKey Clé publique RSA
     * @return bool True si la signature est valide
     * @throws Exception
     */
    public function verifySignature(string $data, string $signature, string $publicKey): bool
    {
        try {
            $key = PublicKeyLoader::load($publicKey)->withPadding(RSA::SIGNATURE_PSS);
            $signatureRaw = base64_decode($signature);
            return $key->verify($data, $signatureRaw);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la vérification de signature: " . $e->getMessage());
        }
    }

    /**
     * Dériver une clé à partir d'un mot de passe avec PBKDF2
     *
     * @param string $password Mot de passe
     * @param string $salt Salt (sera généré si null)
     * @param int $iterations Nombre d'itérations (minimum 100000)
     * @return array ['key' => string, 'salt' => string]
     * @throws Exception
     */
    public function deriveKeyFromPassword(string $password, ?string $salt = null, int $iterations = 100000): array
    {
        try {
            if ($salt === null) {
                $salt = base64_encode(random_bytes(32));
            }

            $saltRaw = base64_decode($salt);

            // Dériver une clé de 32 bytes (256 bits) avec PBKDF2
            $derivedKey = hash_pbkdf2('sha256', $password, $saltRaw, $iterations, 32, true);

            return [
                'key' => base64_encode($derivedKey),
                'salt' => $salt,
            ];
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la dérivation de clé: " . $e->getMessage());
        }
    }
}
