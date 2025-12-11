/**
 * Module de chiffrement/déchiffrement côté client
 * Utilise un chiffrement hybride : AES-256-GCM pour les fichiers + RSA pour la clé AES
 */

/**
 * Convertit une clé publique PEM en format CryptoKey
 * @param {string} pemKey - Clé publique au format PEM
 * @returns {Promise<CryptoKey>}
 */
async function importPublicKey(pemKey) {
    // Retirer les headers PEM
    const pemContents = pemKey
        .replace('-----BEGIN PUBLIC KEY-----', '')
        .replace('-----END PUBLIC KEY-----', '')
        .replace(/\s/g, '');

    // Convertir base64 en ArrayBuffer
    const binaryString = atob(pemContents);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }

    // Importer la clé
    return await crypto.subtle.importKey(
        'spki',
        bytes.buffer,
        {
            name: 'RSA-OAEP',
            hash: 'SHA-256'
        },
        true,
        ['encrypt']
    );
}

/**
 * Convertit une clé privée PEM en format CryptoKey
 * @param {string} pemKey - Clé privée au format PEM
 * @returns {Promise<CryptoKey>}
 */
async function importPrivateKey(pemKey) {
    // Retirer les headers PEM
    const pemContents = pemKey
        .replace('-----BEGIN PRIVATE KEY-----', '')
        .replace('-----END PRIVATE KEY-----', '')
        .replace('-----BEGIN RSA PRIVATE KEY-----', '')
        .replace('-----END RSA PRIVATE KEY-----', '')
        .replace(/\s/g, '');

    // Convertir base64 en ArrayBuffer
    const binaryString = atob(pemContents);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }

    // Importer la clé
    try {
        return await crypto.subtle.importKey(
            'pkcs8',
            bytes.buffer,
            {
                name: 'RSA-OAEP',
                hash: 'SHA-256'
            },
            true,
            ['decrypt']
        );
    } catch (error) {
        console.error('Erreur lors de l\'import de la clé privée:', error);
        throw new Error('Format de clé privée invalide');
    }
}

/**
 * Génère une clé AES-256 aléatoire
 * @returns {Promise<CryptoKey>}
 */
async function generateAESKey() {
    return await crypto.subtle.generateKey(
        {
            name: 'AES-GCM',
            length: 256
        },
        true,
        ['encrypt', 'decrypt']
    );
}

/**
 * Chiffre un fichier avec un chiffrement hybride AES + RSA
 * @param {File} file - Fichier à chiffrer
 * @param {string} publicKeyPEM - Clé publique RSA au format PEM
 * @param {Function} onProgress - Callback pour la progression
 * @returns {Promise<Object>} - Objet contenant le fichier chiffré et les métadonnées
 */
export async function encryptFile(file, publicKeyPEM, onProgress = null) {
    try {
        // 1. Générer une clé AES aléatoire
        if (onProgress) onProgress(10, 'Génération de la clé AES...');
        const aesKey = await generateAESKey();

        // 2. Lire le contenu du fichier
        if (onProgress) onProgress(20, 'Lecture du fichier...');
        const fileBuffer = await file.arrayBuffer();

        // 3. Générer un IV (Initialization Vector) aléatoire
        const iv = crypto.getRandomValues(new Uint8Array(12)); // 96 bits pour GCM

        // 4. Chiffrer le fichier avec AES-GCM
        if (onProgress) onProgress(40, 'Chiffrement du fichier...');
        const encryptedContent = await crypto.subtle.encrypt(
            {
                name: 'AES-GCM',
                iv: iv,
                tagLength: 128 // 128 bits pour le tag d'authentification
            },
            aesKey,
            fileBuffer
        );

        // 5. Exporter la clé AES en format raw
        if (onProgress) onProgress(60, 'Export de la clé AES...');
        const aesKeyRaw = await crypto.subtle.exportKey('raw', aesKey);

        // 6. Importer la clé publique RSA
        if (onProgress) onProgress(70, 'Import de la clé publique...');
        const publicKey = await importPublicKey(publicKeyPEM);

        // 7. Chiffrer la clé AES avec RSA
        if (onProgress) onProgress(80, 'Chiffrement de la clé...');
        const encryptedAESKey = await crypto.subtle.encrypt(
            {
                name: 'RSA-OAEP'
            },
            publicKey,
            aesKeyRaw
        );

        // 8. Créer un Blob avec le contenu chiffré
        if (onProgress) onProgress(90, 'Finalisation...');
        const encryptedBlob = new Blob([encryptedContent], { type: 'application/octet-stream' });

        // 9. Retourner le résultat
        if (onProgress) onProgress(100, 'Terminé !');
        return {
            encryptedFile: encryptedBlob,
            encryptedKey: arrayBufferToBase64(encryptedAESKey),
            iv: arrayBufferToBase64(iv.buffer),
            originalName: file.name,
            originalSize: file.size,
            mimeType: file.type,
            algorithm: 'AES-256-GCM+RSA-OAEP'
        };

    } catch (error) {
        console.error('Erreur lors du chiffrement:', error);
        throw error;
    }
}

/**
 * Déchiffre un fichier avec un chiffrement hybride AES + RSA
 * @param {Blob} encryptedBlob - Blob du fichier chiffré
 * @param {string} encryptedKeyBase64 - Clé AES chiffrée en base64
 * @param {string} ivBase64 - IV en base64
 * @param {string} privateKeyPEM - Clé privée RSA au format PEM
 * @param {Function} onProgress - Callback pour la progression
 * @returns {Promise<Blob>} - Fichier déchiffré
 */
export async function decryptFile(encryptedBlob, encryptedKeyBase64, ivBase64, privateKeyPEM, onProgress = null) {
    try {
        // 1. Importer la clé privée RSA
        if (onProgress) onProgress(10, 'Import de la clé privée...');
        const privateKey = await importPrivateKey(privateKeyPEM);

        // 2. Déchiffrer la clé AES avec RSA
        if (onProgress) onProgress(30, 'Déchiffrement de la clé...');
        const encryptedKey = base64ToArrayBuffer(encryptedKeyBase64);
        const aesKeyRaw = await crypto.subtle.decrypt(
            {
                name: 'RSA-OAEP'
            },
            privateKey,
            encryptedKey
        );

        // 3. Importer la clé AES
        if (onProgress) onProgress(50, 'Import de la clé AES...');
        const aesKey = await crypto.subtle.importKey(
            'raw',
            aesKeyRaw,
            {
                name: 'AES-GCM',
                length: 256
            },
            true,
            ['decrypt']
        );

        // 4. Lire le contenu chiffré
        if (onProgress) onProgress(60, 'Lecture du fichier chiffré...');
        const encryptedContent = await encryptedBlob.arrayBuffer();

        // 5. Déchiffrer le fichier avec AES-GCM
        if (onProgress) onProgress(80, 'Déchiffrement du fichier...');
        const iv = base64ToArrayBuffer(ivBase64);
        const decryptedContent = await crypto.subtle.decrypt(
            {
                name: 'AES-GCM',
                iv: new Uint8Array(iv),
                tagLength: 128
            },
            aesKey,
            encryptedContent
        );

        // 6. Retourner le fichier déchiffré
        if (onProgress) onProgress(100, 'Terminé !');
        return new Blob([decryptedContent]);

    } catch (error) {
        console.error('Erreur lors du déchiffrement:', error);
        if (error.name === 'OperationError') {
            throw new Error('Clé privée ou phrase secrète incorrecte');
        }
        throw error;
    }
}

/**
 * Convertit un ArrayBuffer en base64
 * @param {ArrayBuffer} buffer
 * @returns {string}
 */
function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

/**
 * Convertit une chaîne base64 en ArrayBuffer
 * @param {string} base64
 * @returns {ArrayBuffer}
 */
function base64ToArrayBuffer(base64) {
    const binaryString = atob(base64);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

/**
 * Dérive une clé de chiffrement à partir d'une phrase secrète
 * @param {string} passphrase - Phrase secrète
 * @param {Uint8Array} salt - Salt (16 bytes)
 * @param {number} iterations - Nombre d'itérations PBKDF2 (par défaut 100000)
 * @returns {Promise<CryptoKey>}
 */
async function deriveKeyFromPassphrase(passphrase, salt, iterations = 100000) {
    // Encoder la phrase secrète
    const encoder = new TextEncoder();
    const passphraseKey = await crypto.subtle.importKey(
        'raw',
        encoder.encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
    );

    // Dériver la clé avec PBKDF2
    return await crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: salt,
            iterations: iterations,
            hash: 'SHA-256'
        },
        passphraseKey,
        {
            name: 'AES-GCM',
            length: 256
        },
        true,
        ['decrypt']
    );
}

/**
 * Déchiffre la clé privée chiffrée avec la phrase secrète
 * @param {string} encryptedPrivateKeyJson - Clé privée chiffrée au format JSON
 * @param {string} passphrase - Phrase secrète
 * @returns {Promise<string>} - Clé privée PEM
 */
export async function decryptPrivateKey(encryptedPrivateKeyJson, passphrase) {
    try {
        // Parser le JSON
        const encrypted = JSON.parse(encryptedPrivateKeyJson);

        // Décoder les données base64
        const ciphertext = base64ToArrayBuffer(encrypted.ciphertext);
        const iv = base64ToArrayBuffer(encrypted.iv);
        const tag = base64ToArrayBuffer(encrypted.tag);
        const salt = base64ToArrayBuffer(encrypted.salt);

        // Combiner ciphertext et tag pour AES-GCM
        const encryptedData = new Uint8Array(ciphertext.byteLength + tag.byteLength);
        encryptedData.set(new Uint8Array(ciphertext), 0);
        encryptedData.set(new Uint8Array(tag), ciphertext.byteLength);

        // Dériver la clé à partir de la phrase secrète
        const key = await deriveKeyFromPassphrase(passphrase, new Uint8Array(salt));

        // Déchiffrer la clé privée
        const decryptedData = await crypto.subtle.decrypt(
            {
                name: 'AES-GCM',
                iv: new Uint8Array(iv),
                tagLength: 128
            },
            key,
            encryptedData
        );

        // Convertir en string
        const decoder = new TextDecoder();
        return decoder.decode(decryptedData);

    } catch (error) {
        console.error('Erreur lors du déchiffrement de la clé privée:', error);
        throw new Error('Phrase secrète incorrecte ou clé privée invalide');
    }
}

/**
 * Re-chiffre une clé AES pour un autre utilisateur
 * @param {string} encryptedAESKeyBase64 - Clé AES chiffrée avec votre clé publique (base64)
 * @param {string} yourPrivateKeyPEM - Votre clé privée (PEM)
 * @param {string} recipientPublicKeyPEM - Clé publique du destinataire (PEM)
 * @returns {Promise<string>} - Clé AES re-chiffrée pour le destinataire (base64)
 */
export async function reEncryptAESKey(encryptedAESKeyBase64, yourPrivateKeyPEM, recipientPublicKeyPEM) {
    try {
        // 1. Importer votre clé privée
        const yourPrivateKey = await importPrivateKey(yourPrivateKeyPEM);

        // 2. Déchiffrer la clé AES avec votre clé privée
        const encryptedAESKey = base64ToArrayBuffer(encryptedAESKeyBase64);
        const aesKeyRaw = await crypto.subtle.decrypt(
            {
                name: 'RSA-OAEP'
            },
            yourPrivateKey,
            encryptedAESKey
        );

        // 3. Importer la clé publique du destinataire
        const recipientPublicKey = await importPublicKey(recipientPublicKeyPEM);

        // 4. Re-chiffrer la clé AES avec la clé publique du destinataire
        const reEncryptedAESKey = await crypto.subtle.encrypt(
            {
                name: 'RSA-OAEP'
            },
            recipientPublicKey,
            aesKeyRaw
        );

        // 5. Retourner en base64
        return arrayBufferToBase64(reEncryptedAESKey);

    } catch (error) {
        console.error('Erreur lors du re-chiffrement:', error);
        throw new Error('Impossible de re-chiffrer la clé pour le destinataire');
    }
}

/**
 * Télécharge un fichier déchiffré
 * @param {Blob} blob - Blob du fichier
 * @param {string} filename - Nom du fichier
 */
export function downloadDecryptedFile(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
