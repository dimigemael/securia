import { Head, usePage, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import FlashMessages from '../../Components/FlashMessages';
import { formatDateTime } from '../../Utils/formatters';
import { decryptPrivateKey, reEncryptAESKey } from '../../Utils/crypto';
import { authenticatedFetch } from '../../Utils/api';

export default function Show() {
    const { auth, file } = usePage().props;
    const [showShareModal, setShowShareModal] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [selectedPermission, setSelectedPermission] = useState('read');
    const [isSearching, setIsSearching] = useState(false);

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

    const searchUsers = async (query) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(`/api/users/search?query=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            const data = await response.json();
            setSearchResults(data.users || []);
        } catch (error) {
            console.error('Search error:', error);
        } finally {
            setIsSearching(false);
        }
    };

    const handleSearchChange = (e) => {
        const query = e.target.value;
        setSearchQuery(query);
        searchUsers(query);
    };

    const handleShare = async (userId, userName) => {
        try {
            // 1. Demander la phrase secrète
            const passphrase = prompt('Entrez votre phrase secrète pour partager ce fichier:');
            if (!passphrase) return;

            setIsSearching(true);

            // 2. Récupérer votre clé privée chiffrée
            const keyResponse = await authenticatedFetch('/api/keys/private-key');
            if (!keyResponse.ok) {
                throw new Error('Impossible de récupérer votre clé privée');
            }
            const keyData = await keyResponse.json();

            // 3. Déchiffrer votre clé privée
            const yourPrivateKeyPEM = await decryptPrivateKey(keyData.encrypted_private_key, passphrase);

            // 4. Récupérer la clé publique du destinataire
            const recipientKeyResponse = await authenticatedFetch(`/api/keys/users/${userId}/public-key`);
            if (!recipientKeyResponse.ok) {
                throw new Error('Impossible de récupérer la clé publique du destinataire');
            }
            const recipientKeyData = await recipientKeyResponse.json();

            // 5. Récupérer les détails du fichier avec la clé AES chiffrée
            const fileResponse = await authenticatedFetch(`/api/files/${file.id}`);
            if (!fileResponse.ok) {
                throw new Error('Impossible de récupérer les détails du fichier');
            }
            const fileData = await fileResponse.json();

            // 6. Re-chiffrer la clé AES pour le destinataire
            const reEncryptedKey = await reEncryptAESKey(
                fileData.encrypted_aes_key,
                yourPrivateKeyPEM,
                recipientKeyData.public_key
            );

            // 7. Envoyer la demande de partage au serveur
            const shareResponse = await authenticatedFetch('/api/files/share', {
                method: 'POST',
                body: JSON.stringify({
                    file_id: file.id,
                    user_id: userId,
                    encrypted_key: reEncryptedKey,
                    permissions: [selectedPermission],
                }),
            });

            const shareData = await shareResponse.json();

            if (shareResponse.ok) {
                alert(`Fichier partagé avec succès avec ${userName}`);
                setShowShareModal(false);
                router.reload({ only: ['file'] });
            } else {
                throw new Error(shareData.message || 'Erreur lors du partage');
            }

        } catch (error) {
            console.error('Share error:', error);
            alert('Erreur lors du partage: ' + error.message);
        } finally {
            setIsSearching(false);
        }
    };

    const handleRevoke = async (userId, userName) => {
        if (!confirm(`Révoquer l'accès de ${userName} ?`)) {
            return;
        }

        try {
            const response = await authenticatedFetch('/api/files/revoke-access', {
                method: 'POST',
                body: JSON.stringify({
                    file_id: file.id,
                    user_id: userId,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                alert(`Accès de ${userName} révoqué avec succès`);
                router.reload({ only: ['file'] });
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de révoquer l\'accès'));
            }
        } catch (error) {
            console.error('Revoke error:', error);
            alert('Erreur lors de la révocation');
        }
    };

    return (
        <>
            <Head title={file.original_name} />
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
                                href="/files"
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                ← Mes fichiers
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    {/* File Info */}
                    <div className="bg-white rounded-xl shadow-sm p-8 mb-6">
                        <div className="flex items-start gap-4 mb-6">
                            <span className="text-5xl">{getFileIcon(file.mime_type)}</span>
                            <div className="flex-1">
                                <h2 className="text-2xl font-bold text-gray-900 mb-2">{file.original_name}</h2>
                                <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                                    <span>{formatFileSize(file.file_size)}</span>
                                    <span>•</span>
                                    <span>{file.mime_type}</span>
                                    <span>•</span>
                                    <span>{formatDateTime(file.created_at)}</span>
                                </div>
                            </div>
                            {file.is_owner && (
                                <button
                                    onClick={() => setShowShareModal(true)}
                                    className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition"
                                >
                                    📤 Partager
                                </button>
                            )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="bg-gray-50 p-4 rounded-lg">
                                <p className="text-sm text-gray-600 mb-1">Chiffrement</p>
                                <p className="font-semibold text-gray-900">{file.algorithm}</p>
                            </div>
                            <div className="bg-gray-50 p-4 rounded-lg">
                                <p className="text-sm text-gray-600 mb-1">Statut</p>
                                <p className="font-semibold text-green-700">🔒 Chiffré</p>
                            </div>
                            <div className="bg-gray-50 p-4 rounded-lg">
                                <p className="text-sm text-gray-600 mb-1">Permission</p>
                                <p className="font-semibold text-gray-900 capitalize">{file.permission || 'Propriétaire'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Shared With (Owner only) */}
                    {file.is_owner && (
                        <div className="bg-white rounded-xl shadow-sm p-8">
                            <h3 className="text-xl font-semibold text-gray-900 mb-4">
                                Partagé avec ({file.shared_with?.length || 0})
                            </h3>

                            {!file.shared_with || file.shared_with.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    Ce fichier n'est partagé avec personne
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {file.shared_with.map((user) => (
                                        <div key={user.user_id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold">
                                                    {user.user_name.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-900">{user.user_name}</p>
                                                    <p className="text-sm text-gray-600">{user.user_email}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="px-3 py-1 bg-blue-100 text-blue-700 text-sm font-medium rounded-full">
                                                    {user.permission_level}
                                                </span>
                                                <button
                                                    onClick={() => handleRevoke(user.user_id, user.user_name)}
                                                    className="px-3 py-1 text-sm text-red-600 hover:bg-red-50 rounded-lg transition"
                                                >
                                                    Révoquer
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Owner Info (Non-owner only) */}
                    {!file.is_owner && file.owner && (
                        <div className="bg-white rounded-xl shadow-sm p-8">
                            <h3 className="text-xl font-semibold text-gray-900 mb-4">Propriétaire</h3>
                            <div className="flex items-center gap-3">
                                <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold text-lg">
                                    {file.owner.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="font-medium text-gray-900">{file.owner.name}</p>
                                    <p className="text-sm text-gray-600">Partagé avec vous</p>
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>

            {/* Share Modal */}
            {showShareModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-xl font-bold text-gray-900">Partager le fichier</h3>
                            <button
                                onClick={() => setShowShareModal(false)}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Search Users */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Rechercher un utilisateur
                            </label>
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={handleSearchChange}
                                placeholder="Nom ou email..."
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        {/* Permission Selection */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Permission
                            </label>
                            <select
                                value={selectedPermission}
                                onChange={(e) => setSelectedPermission(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="read">Lecture seule</option>
                                <option value="download">Téléchargement</option>
                            </select>
                        </div>

                        {/* Search Results */}
                        {isSearching ? (
                            <div className="text-center py-4 text-gray-500">Recherche...</div>
                        ) : searchResults.length > 0 ? (
                            <div className="space-y-2 max-h-60 overflow-y-auto">
                                {searchResults.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold text-sm">
                                                {user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900 text-sm">{user.name}</p>
                                                <p className="text-xs text-gray-600">{user.email}</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => handleShare(user.id, user.name)}
                                            disabled={!user.has_keys || isSearching}
                                            className={`px-3 py-1 text-sm rounded-lg transition ${
                                                user.has_keys && !isSearching
                                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                            }`}
                                        >
                                            {isSearching ? 'Partage...' : user.has_keys ? 'Partager' : 'Pas de clés'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : searchQuery.length >= 2 ? (
                            <div className="text-center py-4 text-gray-500">Aucun utilisateur trouvé</div>
                        ) : null}
                    </div>
                </div>
            )}
        </>
    );
}
