import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import FlashMessages from '../../Components/FlashMessages';

export default function Generate() {
    const [showPassphrase, setShowPassphrase] = useState(false);
    const [showConfirmPassphrase, setShowConfirmPassphrase] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        key_size: 2048,
        passphrase: '',
        passphrase_confirmation: '',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();

        // Vérifier que les passphrases correspondent
        if (data.passphrase !== data.passphrase_confirmation) {
            alert('Les phrases de passe ne correspondent pas');
            return;
        }

        post('/keys/generate');
    };

    return (
        <>
            <Head title="Générer des clés" />
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
                        <Link
                            href="/dashboard"
                            className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"
                        >
                            ← Retour
                        </Link>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div className="bg-white rounded-xl shadow-md p-8">
                        <div className="mb-8">
                            <h2 className="text-3xl font-bold text-gray-900 mb-2">
                                Générer vos clés de chiffrement
                            </h2>
                            <p className="text-gray-600">
                                Créez une paire de clés RSA pour chiffrer et déchiffrer vos fichiers
                            </p>
                        </div>

                        {/* Important Notice */}
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                            <div className="flex items-start gap-3">
                                <span className="text-2xl">⚠️</span>
                                <div>
                                    <h3 className="font-semibold text-yellow-900 mb-1">Important</h3>
                                    <ul className="text-sm text-yellow-800 space-y-1">
                                        <li>• Votre phrase de passe protège votre clé privée</li>
                                        <li>• Si vous la perdez, vous ne pourrez plus déchiffrer vos fichiers</li>
                                        <li>• Conservez-la en lieu sûr (gestionnaire de mots de passe recommandé)</li>
                                        <li>• La génération peut prendre quelques secondes</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            {/* Key Size Selection */}
                            <div>
                                <label className="block text-sm font-medium text-gray-900 mb-3">
                                    Niveau de sécurité
                                </label>
                                <div className="space-y-3">
                                    <label className="flex items-start gap-3 cursor-pointer p-4 bg-gray-50 rounded-lg border-2 border-transparent hover:border-blue-300 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                        <input
                                            type="radio"
                                            name="key_size"
                                            value="2048"
                                            checked={data.key_size === 2048}
                                            onChange={(e) => setData('key_size', parseInt(e.target.value))}
                                            className="mt-1 w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        />
                                        <div className="flex-1">
                                            <span className="block font-semibold text-gray-900">2048 bits - Recommandé</span>
                                            <span className="text-sm text-gray-600">Équilibre idéal entre sécurité et performance. Suffisant pour la plupart des usages.</span>
                                        </div>
                                    </label>
                                    <label className="flex items-start gap-3 cursor-pointer p-4 bg-gray-50 rounded-lg border-2 border-transparent hover:border-blue-300 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                        <input
                                            type="radio"
                                            name="key_size"
                                            value="4096"
                                            checked={data.key_size === 4096}
                                            onChange={(e) => setData('key_size', parseInt(e.target.value))}
                                            className="mt-1 w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        />
                                        <div className="flex-1">
                                            <span className="block font-semibold text-gray-900">4096 bits - Sécurité maximale</span>
                                            <span className="text-sm text-gray-600">Niveau de sécurité maximal. Plus lent mais plus résistant aux attaques futures.</span>
                                        </div>
                                    </label>
                                </div>
                                {errors.key_size && <p className="mt-1 text-sm text-red-600">{errors.key_size}</p>}
                            </div>

                            {/* Passphrase */}
                            <div>
                                <label htmlFor="passphrase" className="block text-sm font-medium text-gray-700 mb-2">
                                    Phrase de passe <span className="text-red-600">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        id="passphrase"
                                        type={showPassphrase ? 'text' : 'password'}
                                        value={data.passphrase}
                                        onChange={(e) => setData('passphrase', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="Entrez une phrase de passe forte"
                                        required
                                        minLength={8}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassphrase(!showPassphrase)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showPassphrase ? '🙈' : '👁️'}
                                    </button>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    Minimum 8 caractères. Utilisez une phrase longue et mémorable.
                                </p>
                                {errors.passphrase && <p className="mt-1 text-sm text-red-600">{errors.passphrase}</p>}
                            </div>

                            {/* Confirm Passphrase */}
                            <div>
                                <label htmlFor="passphrase_confirmation" className="block text-sm font-medium text-gray-700 mb-2">
                                    Confirmer la phrase de passe <span className="text-red-600">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        id="passphrase_confirmation"
                                        type={showConfirmPassphrase ? 'text' : 'password'}
                                        value={data.passphrase_confirmation}
                                        onChange={(e) => setData('passphrase_confirmation', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="Confirmez votre phrase de passe"
                                        required
                                        minLength={8}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowConfirmPassphrase(!showConfirmPassphrase)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showConfirmPassphrase ? '🙈' : '👁️'}
                                    </button>
                                </div>
                            </div>

                            {/* Description (optional) */}
                            <div>
                                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                                    Description (optionnel)
                                </label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    placeholder="Ex: Clés principales pour mes documents confidentiels"
                                    maxLength={500}
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Ajoutez une note pour vous souvenir de l'usage de ces clés
                                </p>
                                {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                            </div>

                            {/* Submit Button */}
                            <div className="flex gap-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md"
                                >
                                    {processing ? 'Génération en cours...' : '🔑 Générer mes clés'}
                                </button>
                                <Link
                                    href="/dashboard"
                                    className="px-8 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition text-center"
                                >
                                    Annuler
                                </Link>
                            </div>
                        </form>
                    </div>

                    {/* Info Box */}
                    <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">💡</span>
                            <div className="flex-1">
                                <p className="text-sm text-blue-900 font-medium mb-1">Conseils pour une phrase de passe sécurisée</p>
                                <ul className="text-xs text-blue-800 space-y-1">
                                    <li>• Utilisez une phrase longue et mémorable (ex: "J'adore manger des pommes en automne 2024!")</li>
                                    <li>• Évitez les mots de passe courts ou communs</li>
                                    <li>• N'utilisez pas votre mot de passe de compte</li>
                                    <li>• Stockez-la dans un gestionnaire de mots de passe sécurisé</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
