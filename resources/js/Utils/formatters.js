/**
 * Formate une date en format lisible en français
 * @param {string} dateString - Date au format ISO
 * @returns {string} Date formatée
 */
export function formatDate(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMinutes = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    // Il y a moins d'une minute
    if (diffMinutes < 1) {
        return "À l'instant";
    }

    // Il y a moins d'une heure
    if (diffMinutes < 60) {
        return `Il y a ${diffMinutes} minute${diffMinutes > 1 ? 's' : ''}`;
    }

    // Il y a moins de 24 heures
    if (diffHours < 24) {
        return `Il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
    }

    // Il y a moins de 7 jours
    if (diffDays < 7) {
        if (diffDays === 1) {
            return `Hier à ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
        }
        return `Il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
    }

    // Date complète
    return date.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formate une date en format court
 * @param {string} dateString - Date au format ISO
 * @returns {string} Date formatée (ex: "4 déc. 2025")
 */
export function formatDateShort(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

/**
 * Formate une date avec heure
 * @param {string} dateString - Date au format ISO
 * @returns {string} Date formatée (ex: "4 déc. 2025 à 15:30")
 */
export function formatDateTime(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formate le type d'action pour un affichage lisible
 * @param {string} actionType - Type d'action brut
 * @returns {string} Type d'action formaté
 */
export function formatActionType(actionType) {
    const actions = {
        'register': 'Inscription',
        'login': 'Connexion',
        'logout': 'Déconnexion',
        'key_generate': 'Génération de clés',
        'key_change_password': 'Changement de mot de passe des clés',
        'file_encrypt': 'Fichier chiffré',
        'file_decrypt': 'Fichier déchiffré',
        'file_share': 'Fichier partagé',
        'file_revoke': 'Accès révoqué',
        'file_delete': 'Fichier supprimé',
        'permission_update': 'Permissions modifiées',
    };

    return actions[actionType] || actionType;
}

/**
 * Retourne l'icône correspondant au type d'action
 * @param {string} actionType - Type d'action brut
 * @returns {string} Emoji correspondant
 */
export function getActionIcon(actionType) {
    const icons = {
        'register': '👤',
        'login': '🔓',
        'logout': '🔒',
        'key_generate': '🔑',
        'key_change_password': '🔐',
        'file_encrypt': '🔐',
        'file_decrypt': '🔓',
        'file_share': '📤',
        'file_revoke': '🚫',
        'file_delete': '🗑️',
        'permission_update': '⚙️',
    };

    return icons[actionType] || '📝';
}
