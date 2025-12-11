import { Head, usePage, Link, router } from '@inertiajs/react';
import FlashMessages from '../Components/FlashMessages';
import { formatDate, formatActionType, getActionIcon } from '../Utils/formatters';

export default function Dashboard() {
    const { auth, hasKeys, statistics, recentLogs } = usePage().props;

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <>
            <Head title="Tableau de bord" />
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
                            <span className="text-gray-600">Bonjour, {auth.user.name}</span>
                            <button
                                onClick={handleLogout}
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            >
                                Déconnexion
                            </button>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Welcome Banner */}
                    <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
                        <h2 className="text-3xl font-bold text-gray-900 mb-2">
                            Bienvenue sur Securia !
                        </h2>
                        <p className="text-gray-600">
                            Gérez vos fichiers chiffrés et vos clés de sécurité en toute simplicité.
                        </p>
                    </div>

                    {/* Key Status */}
                    {!hasKeys ? (
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                            <div className="flex items-start gap-4">
                                <span className="text-3xl">⚠️</span>
                                <div className="flex-1">
                                    <h3 className="text-lg font-semibold text-yellow-900 mb-2">
                                        Aucune clé de chiffrement
                                    </h3>
                                    <p className="text-yellow-800 mb-4">
                                        Vous devez générer des clés de chiffrement pour pouvoir utiliser Securia.
                                    </p>
                                    <Link
                                        href="/keys/generate"
                                        className="inline-block px-6 py-2 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 transition"
                                    >
                                        Générer mes clés maintenant
                                    </Link>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
                            <div className="flex items-start gap-4">
                                <span className="text-3xl">✓</span>
                                <div className="flex-1">
                                    <h3 className="text-lg font-semibold text-green-900 mb-2">
                                        Clés de chiffrement actives
                                    </h3>
                                    <p className="text-green-800">
                                        Vos clés de chiffrement sont configurées et prêtes à l'emploi.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Quick Actions */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <Link
                            href="/files"
                            className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition"
                        >
                            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                <span className="text-2xl">📁</span>
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-2">Mes fichiers</h3>
                            <p className="text-gray-600 text-sm">
                                Uploader, chiffrer et gérer vos fichiers sécurisés
                            </p>
                        </Link>

                        <Link
                            href="/keys"
                            className="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition"
                        >
                            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                                <span className="text-2xl">🔑</span>
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-2">Gérer mes clés</h3>
                            <p className="text-gray-600 text-sm">
                                Visualisez et gérez vos clés de chiffrement
                            </p>
                        </Link>
                    </div>

                    {/* Statistics */}
                    {statistics && (
                        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
                            <h3 className="text-xl font-semibold text-gray-900 mb-4">Statistiques</h3>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="text-center p-4 bg-gray-50 rounded-lg">
                                    <p className="text-3xl font-bold text-blue-600">{statistics.total_files || 0}</p>
                                    <p className="text-sm text-gray-600 mt-1">Fichiers chiffrés</p>
                                </div>
                                <div className="text-center p-4 bg-gray-50 rounded-lg">
                                    <p className="text-3xl font-bold text-green-600">{statistics.shared_files || 0}</p>
                                    <p className="text-sm text-gray-600 mt-1">Fichiers partagés</p>
                                </div>
                                <div className="text-center p-4 bg-gray-50 rounded-lg">
                                    <p className="text-3xl font-bold text-purple-600">{statistics.received_files || 0}</p>
                                    <p className="text-sm text-gray-600 mt-1">Fichiers reçus</p>
                                </div>
                                <div className="text-center p-4 bg-gray-50 rounded-lg">
                                    <p className="text-3xl font-bold text-orange-600">{statistics.total_actions || 0}</p>
                                    <p className="text-sm text-gray-600 mt-1">Actions totales</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Recent Activity */}
                    {recentLogs && recentLogs.length > 0 && (
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h3 className="text-xl font-semibold text-gray-900 mb-4">Activité récente</h3>
                            <div className="space-y-3">
                                {recentLogs.map((log, index) => (
                                    <div key={index} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                        <span className="text-2xl">{getActionIcon(log.action)}</span>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-gray-900">{formatActionType(log.action)}</p>
                                            <p className="text-xs text-gray-600">{formatDate(log.created_at)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
