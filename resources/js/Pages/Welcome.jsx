import { Head } from '@inertiajs/react';
import FlashMessages from '../Components/FlashMessages';

export default function Welcome() {
    return (
        <>
            <Head title="Bienvenue" />
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
                        <div className="flex gap-3">
                            <a
                                href="/login"
                                className="px-6 py-2 text-blue-600 font-medium hover:bg-blue-50 rounded-lg transition"
                            >
                                Se connecter
                            </a>
                            <a
                                href="/register"
                                className="px-6 py-2 bg-blue-600 text-white font-medium hover:bg-blue-700 rounded-lg shadow-sm transition"
                            >
                                S'inscrire
                            </a>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-16">
                        <h2 className="text-5xl font-bold text-gray-900 mb-4">
                            Chiffrement et partage sécurisé de fichiers
                        </h2>
                        <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                            Protégez vos données sensibles avec un chiffrement de niveau militaire et partagez-les en toute confiance
                        </p>
                        <div className="mt-8 flex gap-4 justify-center">
                            <a
                                href="/register"
                                className="px-8 py-3 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700 hover:shadow-lg transition"
                            >
                                Commencer
                            </a>
                            <a
                                href="/login"
                                className="px-8 py-3 bg-white text-gray-700 font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition"
                            >
                                Se connecter
                            </a>
                        </div>
                    </div>

                    {/* Features */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-16">
                        <div className="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition">
                            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                <span className="text-2xl">🔑</span>
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-2">Chiffrement RSA</h3>
                            <p className="text-gray-600">
                                Clés 2048/4096 bits pour une sécurité maximale de vos données
                            </p>
                        </div>
                        <div className="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition">
                            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                                <span className="text-2xl">🤝</span>
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-2">Partage Sécurisé</h3>
                            <p className="text-gray-600">
                                Partagez vos fichiers chiffrés en toute confidentialité avec vos contacts
                            </p>
                        </div>
                        <div className="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition">
                            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                                <span className="text-2xl">📊</span>
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 mb-2">Audit Complet</h3>
                            <p className="text-gray-600">
                                Traçabilité totale de toutes les actions sur vos fichiers
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
