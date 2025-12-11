import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function FlashMessages() {
    const page = usePage();
    const flash = page.props?.flash || {};
    const errors = page.props?.errors || {};
    const [visible, setVisible] = useState({});

    useEffect(() => {
        const newVisible = {};

        if (flash?.success) {
            newVisible.success = true;
            setTimeout(() => setVisible(prev => ({ ...prev, success: false })), 5000);
        }
        if (flash?.error) {
            newVisible.error = true;
            setTimeout(() => setVisible(prev => ({ ...prev, error: false })), 5000);
        }
        if (flash?.warning) {
            newVisible.warning = true;
            setTimeout(() => setVisible(prev => ({ ...prev, warning: false })), 5000);
        }
        if (flash?.info) {
            newVisible.info = true;
            setTimeout(() => setVisible(prev => ({ ...prev, info: false })), 5000);
        }
        // Afficher les erreurs globales (non liées à un champ)
        if (errors?.error) {
            newVisible.validationError = true;
            setTimeout(() => setVisible(prev => ({ ...prev, validationError: false })), 5000);
        }

        setVisible(newVisible);
    }, [flash, errors]);

    const types = {
        success: {
            bg: 'bg-green-50',
            border: 'border-green-200',
            text: 'text-green-800',
            icon: '✓',
            iconBg: 'bg-green-100',
        },
        error: {
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-800',
            icon: '✕',
            iconBg: 'bg-red-100',
        },
        warning: {
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            text: 'text-yellow-800',
            icon: '⚠',
            iconBg: 'bg-yellow-100',
        },
        info: {
            bg: 'bg-blue-50',
            border: 'border-blue-200',
            text: 'text-blue-800',
            icon: 'ℹ',
            iconBg: 'bg-blue-100',
        },
    };

    return (
        <div className="fixed top-4 right-4 z-50 space-y-3 max-w-md">
            {Object.entries(types).map(([type, styles]) => {
                if (!flash?.[type] || !visible[type]) return null;

                return (
                    <div
                        key={type}
                        className={`${styles.bg} ${styles.border} border rounded-lg shadow-lg p-4 flex items-start gap-3 animate-slide-in`}
                        role="alert"
                    >
                        <div className={`${styles.iconBg} rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0`}>
                            <span className={`${styles.text} font-bold text-lg`}>{styles.icon}</span>
                        </div>
                        <div className="flex-1">
                            <p className={`${styles.text} font-medium`}>{flash?.[type]}</p>
                        </div>
                        <button
                            onClick={() => setVisible(prev => ({ ...prev, [type]: false }))}
                            className={`${styles.text} hover:opacity-70 transition`}
                        >
                            ✕
                        </button>
                    </div>
                );
            })}

            {/* Afficher les erreurs de validation globales */}
            {errors?.error && visible.validationError && (
                <div
                    className={`${types.error.bg} ${types.error.border} border rounded-lg shadow-lg p-4 flex items-start gap-3 animate-slide-in`}
                    role="alert"
                >
                    <div className={`${types.error.iconBg} rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0`}>
                        <span className={`${types.error.text} font-bold text-lg`}>{types.error.icon}</span>
                    </div>
                    <div className="flex-1">
                        <p className={`${types.error.text} font-medium`}>{errors?.error}</p>
                    </div>
                    <button
                        onClick={() => setVisible(prev => ({ ...prev, validationError: false }))}
                        className={`${types.error.text} hover:opacity-70 transition`}
                    >
                        ✕
                    </button>
                </div>
            )}
        </div>
    );
}
