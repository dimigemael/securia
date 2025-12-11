import { Head, usePage, Link, router } from '@inertiajs/react';
import FlashMessages from '../../Components/FlashMessages';
import { formatDateTime } from '../../Utils/formatters';

export default function Index() {
    const { auth, key } = usePage().props;

    const copyToClipboard = (text) => {
        navigator.clipboard.writeText(text);
        alert('Clé publique copiée dans le presse-papiers');
    };

    return (
        <>
            <Head title="Mes clés" />
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
                    <div className="mb-8">
                        <h2 className="text-3xl font-bold text-gray-900 mb-2">
                            Mes clés de chiffrement
                        </h2>
                        <p className="text-gray-600">
                            Gérez vos clés RSA pour le chiffrement et déchiffrement de fichiers
                        </p>
                    </div>

                    {!key ? (
                        /* No Keys State */
                        <div className="bg-white rounded-xl shadow-sm p-12 text-center">
                            <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <span className="text-5xl">🔑</span>
                            </div>
                            <h3 className="text-2xl font-bold text-gray-900 mb-2">
                                Aucune clé de chiffrement
                            </h3>
                            <p className="text-gray-600 mb-8 max-w-md mx-auto">
                                Vous devez générer une paire de clés RSA pour commencer à chiffrer et partager des fichiers de manière sécurisée.
                            </p>
                            <Link
                                href="/keys/generate"
                                className="inline-block px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm hover:shadow-md transition"
                            >
                                🔑 Générer mes clés
                            </Link>
                        </div>
                    ) : (
                        /* Keys Display */
                        <>
                            {/* Key Info Card */}
                            <div className="bg-white rounded-xl shadow-sm p-8 mb-6">
                                <div className="flex items-start justify-between mb-6">
                                    <div>
                                        <div className="flex items-center gap-3 mb-2">
                                            <span className="text-3xl">🔐</span>
                                            <h3 className="text-2xl font-bold text-gray-900">Paire de clés active</h3>
                                        </div>
                                        <p className="text-gray-600">
                                            Vos clés sont prêtes à chiffrer et déchiffrer des fichiers
                                        </p>
                                    </div>
                                    <span className="px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">
                                        ✓ Active
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <p className="text-sm text-gray-600 mb-1">Algorithme</p>
                                        <p className="text-lg font-semibold text-gray-900">{key.algorithm}</p>
                                    </div>
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <p className="text-sm text-gray-600 mb-1">Taille de clé</p>
                                        <p className="text-lg font-semibold text-gray-900">{key.size} bits</p>
                                    </div>
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <p className="text-sm text-gray-600 mb-1">Date de création</p>
                                        <p className="text-lg font-semibold text-gray-900">{formatDateTime(key.created_at)}</p>
                                    </div>
                                </div>

                                {/* Public Key */}
                                <div className="bg-gray-50 p-4 rounded-lg mb-4">
                                    <div className="flex items-center justify-between mb-2">
                                        <label className="text-sm font-medium text-gray-700">Clé publique</label>
                                        <button
                                            onClick={() => copyToClipboard(key.public_key)}
                                            className="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                        >
                                            📋 Copier
                                        </button>
                                    </div>
                                    <textarea
                                        value={key.public_key}
                                        readOnly
                                        rows={8}
                                        className="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg font-mono text-xs text-gray-700 focus:outline-none"
                                    />
                                    <p className="text-xs text-gray-500 mt-2">
                                        Partagez cette clé avec d'autres utilisateurs pour qu'ils puissent chiffrer des fichiers pour vous
                                    </p>
                                </div>

                                {/* Private Key Warning */}
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div className="flex items-start gap-3">
                                        <span className="text-2xl">🔒</span>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-yellow-900 mb-1">Clé privée sécurisée</p>
                                            <p className="text-xs text-yellow-800">
                                                Votre clé privée est stockée de manière chiffrée dans la base de données. Elle n'est jamais exposée et nécessite votre phrase de passe pour être utilisée.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <Link
                                    href="/keys/rotate"
                                    className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition border border-gray-200"
                                >
                                    <div className="flex items-start gap-4">
                                        <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span className="text-2xl">🔄</span>
                                        </div>
                                        <div className="flex-1">
                                            <h4 className="text-lg font-semibold text-gray-900 mb-1">Rotation de clés</h4>
                                            <p className="text-sm text-gray-600">
                                                Générez de nouvelles clés et migrez vos fichiers
                                            </p>
                                        </div>
                                    </div>
                                </Link>

                                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200 opacity-60">
                                    <div className="flex items-start gap-4">
                                        <div className="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span className="text-2xl">🗑️</span>
                                        </div>
                                        <div className="flex-1">
                                            <h4 className="text-lg font-semibold text-gray-900 mb-1">Supprimer les clés</h4>
                                            <p className="text-sm text-gray-600">
                                                Action irréversible - vous ne pourrez plus déchiffrer vos fichiers
                                            </p>
                                            <p className="text-xs text-red-600 mt-2 font-medium">Fonctionnalité à venir</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Security Tips */}
                            <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
                                <div className="flex items-start gap-3">
                                    <span className="text-2xl">💡</span>
                                    <div className="flex-1">
                                        <h4 className="text-sm font-semibold text-blue-900 mb-2">Bonnes pratiques de sécurité</h4>
                                        <ul className="text-xs text-blue-800 space-y-1">
                                            <li>• Ne partagez jamais votre phrase de passe avec qui que ce soit</li>
                                            <li>• Conservez une sauvegarde de votre clé publique dans un endroit sûr</li>
                                            <li>• Effectuez une rotation de vos clés régulièrement </li>
                                            <li>• Si vous pensez que votre phrase de passe est compromise, effectuez une rotation immédiatement</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </main>
            </div>
        </>
    );
}
