/**
 * Utilitaires pour les requêtes API authentifiées
 */

/**
 * Récupère le token CSRF depuis la meta tag ou le cookie
 * @returns {string}
 */
function getCsrfToken() {
    // Essayer depuis la meta tag
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        return metaTag.getAttribute('content');
    }

    // Essayer depuis le cookie
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }

    return '';
}

/**
 * Effectue une requête API authentifiée
 * @param {string} url - URL de l'API
 * @param {object} options - Options fetch
 * @returns {Promise<Response>}
 */
export async function authenticatedFetch(url, options = {}) {
    const csrfToken = getCsrfToken();

    const defaultHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    // Ajouter le token CSRF si c'est une requête qui modifie des données
    if (options.method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method.toUpperCase())) {
        defaultHeaders['X-CSRF-TOKEN'] = csrfToken;
    }

    // Ne pas ajouter Content-Type si on envoie du FormData (le navigateur le fera automatiquement)
    if (!(options.body instanceof FormData)) {
        defaultHeaders['Content-Type'] = 'application/json';
    }

    const mergedOptions = {
        ...options,
        credentials: 'include', // Important pour envoyer les cookies de session
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
    };

    return fetch(url, mergedOptions);
}
