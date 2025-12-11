import { Head, usePage, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import FlashMessages from '../../Components/FlashMessages';
import { formatDateTime } from '../../Utils/formatters';
import { encryptFile, decryptFile, decryptPrivateKey, downloadDecryptedFile } from '../../Utils/crypto';
import { authenticatedFetch } from '../../Utils/api';

export default function Index() {
    const { auth, ownedFiles, sharedFiles, publicKey, hasKeys } = usePage().props;
    const [uploadingFile, setUploadingFile] = useState(null);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadStatus, setUploadStatus] = useState('');

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const getFileIcon = (mimeType) => {
        if (!mimeType) return '📄';
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType.startsWith('video/')) return '🎬';
        if (mimeType.startsWith('audio/')) return '🎵';
        if (mimeType.includes('pdf')) return '📕';
        if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📊';
        if (mimeType.includes('zip') || mimeType.includes('compressed')) return '📦';
        return '📄';
    };

    const handleFileUpload = async (event) => {
        const file = event.target.files[0];
        if (!file) return;

        // Vérifier que l'utilisateur a des clés
        if (!hasKeys || !publicKey) {
            alert('Vous devez générer des clés de chiffrement avant de pouvoir uploader des fichiers.');
            router.visit('/keys/generate');
            return;
        }

        setUploadingFile(file.name);
        setUploadProgress(0);
        setUploadStatus('Préparation...');

        try {
            // 1. Chiffrer le fichier côté client
            setUploadStatus('Chiffrement en cours...');
            const encrypted = await encryptFile(file, publicKey, (progress, status) => {
                setUploadProgress(progress);
                setUploadStatus(status);
            });

            // 2. Préparer le FormData pour l'upload
            setUploadStatus('Upload vers le serveur...');
            const formData = new FormData();
            formData.append('encrypted_file', encrypted.encryptedFile, file.name + '.enc');
            formData.append('encrypted_key', encrypted.encryptedKey);
            formData.append('iv', encrypted.iv);
            formData.append('original_name', encrypted.originalName);
            formData.append('original_size', encrypted.originalSize);
            formData.append('mime_type', encrypted.mimeType);
            formData.append('algorithm', encrypted.algorithm);

            // 3. Envoyer au serveur
            const response = await authenticatedFetch('/api/files/upload-encrypted', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (response.ok) {
                setUploadStatus('Upload terminé !');
                setTimeout(() => {
                    setUploadingFile(null);
                    setUploadProgress(0);
                    setUploadStatus('');
                    router.reload({ only: ['ownedFiles'] });
                }, 1000);
            } else {
                throw new Error(data.message || 'Erreur lors de l\'upload');
            }

        } catch (error) {
            console.error('Upload error:', error);
            console.error('Error stack:', error.stack);
            alert('Erreur lors de l\'upload du fichier: ' + error.message);
            setUploadingFile(null);
            setUploadProgress(0);
            setUploadStatus('');
        }
    };

    const handleDownload = async (fileId, fileName) => {
        try {
            // 1. Demander la phrase secrète
            const passphrase = prompt('Entrez votre phrase secrète pour déchiffrer le fichier:');
            if (!passphrase) return;

            setUploadStatus(`Déchiffrement de ${fileName}...`);
            setUploadProgress(0);
            setUploadingFile(fileName);

            // 2. Récupérer la clé privée chiffrée
            setUploadStatus('Récupération de la clé privée...');
            setUploadProgress(10);

            const keyResponse = await authenticatedFetch('/api/keys/private-key');

            if (!keyResponse.ok) {
                throw new Error('Impossible de récupérer la clé privée');
            }

            const keyData = await keyResponse.json();

            // 3. Déchiffrer la clé privée avec la phrase secrète
            setUploadStatus('Déchiffrement de la clé privée...');
            setUploadProgress(20);

            const privateKeyPEM = await decryptPrivateKey(keyData.encrypted_private_key, passphrase);

            // 4. Récupérer le fichier chiffré avec métadonnées
            setUploadStatus('Téléchargement du fichier...');
            setUploadProgress(40);

            const fileResponse = await authenticatedFetch(`/api/files/${fileId}/download-encrypted`);

            if (!fileResponse.ok) {
                throw new Error('Impossible de télécharger le fichier');
            }

            const fileData = await fileResponse.json();
            const file = fileData.file;

            // 5. Convertir le contenu base64 en Blob
            setUploadStatus('Préparation du déchiffrement...');
            setUploadProgress(50);

            const encryptedContent = atob(file.encrypted_content);
            const encryptedBytes = new Uint8Array(encryptedContent.length);
            for (let i = 0; i < encryptedContent.length; i++) {
                encryptedBytes[i] = encryptedContent.charCodeAt(i);
            }
            const encryptedBlob = new Blob([encryptedBytes]);

            // 6. Déchiffrer le fichier
            setUploadStatus('Déchiffrement du fichier...');

            const decryptedBlob = await decryptFile(
                encryptedBlob,
                file.encrypted_key,
                file.iv,
                privateKeyPEM,
                (progress, status) => {
                    setUploadProgress(50 + (progress / 2)); // 50-100%
                    setUploadStatus(status);
                }
            );

            // 7. Télécharger le fichier déchiffré
            setUploadStatus('Téléchargement...');
            setUploadProgress(100);

            downloadDecryptedFile(decryptedBlob, file.original_name);

            // 8. Nettoyer
            setTimeout(() => {
                setUploadingFile(null);
                setUploadProgress(0);
                setUploadStatus('');
            }, 1000);

        } catch (error) {
            console.error('Download error:', error);
            alert('Erreur lors du téléchargement : ' + error.message);
            setUploadingFile(null);
            setUploadProgress(0);
            setUploadStatus('');
        }
    };

    const handleDelete = async (fileId, fileName) => {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer "${fileName}" ?`)) {
            return;
        }

        try {
            const response = await authenticatedFetch(`/api/files/${fileId}`, {
                method: 'DELETE',
            });

            const data = await response.json();

            if (response.ok) {
                router.reload({ only: ['ownedFiles'] });
            } else {
                throw new Error(data.message || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Erreur lors de la suppression du fichier: ' + error.message);
        }
    };

    return (
        <>
            <Head title="Mes fichiers" />
            <FlashMessages />
            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white shadow-sm">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white text-xl font-bold">
                                S
                            </div>
                            <h1 className="text-2xl font-semibold text-gray-900">Securia</h1>
                        </div>
                        <div className="flex gap-3 items-center">
                            <span className="text-gray-600">{auth.user.name}</span>
                            <Link
                                href="/dashboard"
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                ← Dashboard
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h2 className="text-3xl font-bold text-gray-900 mb-2">
                                Mes fichiers
                            </h2>
                            <p className="text-gray-600">
                                Gérez vos fichiers chiffrés de manière sécurisée
                            </p>
                        </div>

                        {/* Upload Button */}
                        <label className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm hover:shadow-md transition cursor-pointer">
                            <input
                                type="file"
                                onChange={handleFileUpload}
                                className="hidden"
                                disabled={uploadingFile !== null}
                            />
                            {uploadingFile ? '⏳ Upload en cours...' : '📤 Uploader un fichier'}
                        </label>
                    </div>

                    {/* Upload Progress */}
                    {uploadingFile && (
                        <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div className="flex items-center gap-3 mb-2">
                                <span className="text-blue-900 font-medium">Chiffrement et upload:</span>
                                <span className="text-blue-700">{uploadingFile}</span>
                            </div>
                            <div className="flex items-center gap-3 mb-2">
                                <span className="text-sm text-blue-800">{uploadStatus}</span>
                                <span className="text-sm text-blue-700 font-mono">{uploadProgress}%</span>
                            </div>
                            <div className="w-full bg-blue-200 rounded-full h-2">
                                <div
                                    className="bg-blue-600 h-2 rounded-full transition-all"
                                    style={{ width: `${uploadProgress}%` }}
                                ></div>
                            </div>
                        </div>
                    )}

                    {/* Owned Files */}
                    <div className="mb-8">
                        <h3 className="text-xl font-semibold text-gray-900 mb-4">
                            Mes fichiers ({ownedFiles?.length || 0})
                        </h3>

                        {!ownedFiles || ownedFiles.length === 0 ? (
                            <div className="bg-white rounded-xl shadow-sm p-12 text-center">
                                <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <span className="text-5xl">📁</span>
                                </div>
                                <h4 className="text-xl font-semibold text-gray-900 mb-2">
                                    Aucun fichier
                                </h4>
                                <p className="text-gray-600 mb-6">
                                    Commencez par uploader votre premier fichier chiffré
                                </p>
                            </div>
                        ) : (
                            <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                                <table className="w-full">
                                    <thead className="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fichier
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Taille
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Chiffrement
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {ownedFiles.map((file) => (
                                            <tr key={file.id} className="hover:bg-gray-50 transition">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-2xl">{getFileIcon(file.mime_type)}</span>
                                                        <div>
                                                            <div className="font-medium text-gray-900">
                                                                {file.original_name}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {file.mime_type}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900">
                                                    {formatFileSize(file.file_size)}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-600">
                                                    {formatDateTime(file.created_at)}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        {file.encryption_algorithm || 'AES-256'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/files/${file.id}`}
                                                            className="px-3 py-1 text-sm bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition"
                                                        >
                                                            👁️ Détails
                                                        </Link>
                                                        <button
                                                            onClick={() => handleDownload(file.id, file.original_name)}
                                                            className="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                                        >
                                                            📥 Télécharger
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(file.id, file.original_name)}
                                                            className="px-3 py-1 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                                        >
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Shared Files */}
                    {sharedFiles && sharedFiles.length > 0 && (
                        <div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-4">
                                Partagés avec moi ({sharedFiles.length})
                            </h3>

                            <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                                <table className="w-full">
                                    <thead className="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fichier
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Propriétaire
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Taille
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {sharedFiles.map((file) => (
                                            <tr key={file.id} className="hover:bg-gray-50 transition">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-2xl">{getFileIcon(file.mime_type)}</span>
                                                        <div>
                                                            <div className="font-medium text-gray-900">
                                                                {file.original_name}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {file.mime_type}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900">
                                                    {file.owner_name}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900">
                                                    {formatFileSize(file.file_size)}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-600">
                                                    {formatDateTime(file.created_at)}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button
                                                        onClick={() => handleDownload(file.id, file.original_name)}
                                                        className="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                                    >
                                                        📥 Télécharger
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Info Box */}
                    <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">🔒</span>
                            <div className="flex-1">
                                <h4 className="text-sm font-semibold text-blue-900 mb-2">Chiffrement end-to-end</h4>
                                <p className="text-xs text-blue-800">
                                    Tous vos fichiers sont chiffrés avec un chiffrement hybride AES-256 + RSA.
                                    Le serveur ne stocke que les versions chiffrées et ne peut pas accéder au contenu.
                                </p>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
