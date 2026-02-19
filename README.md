# Securia

Securia est une application sécurisée de stockage et de partage de fichiers, développée avec Laravel (backend) et React (frontend) utilisant Inertia.js. Elle intègre un chiffrement hybride robuste (AES-256-GCM + RSA-OAEP) et des signatures numériques pour une sécurité renforcée.

## Table des Matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Lancement de l'Application](#lancement-de-lapplication)
- [Exécution des Tests](#exécution-des-tests)
- [Contribution](#contribution)
- [Licence](#licence)

## Fonctionnalités

- Authentification et autorisation des utilisateurs.
- Chiffrement hybride des fichiers utilisant AES-256-GCM (contenu du fichier) et RSA-OAEP (clé AES).
- Signatures numériques pour l'intégrité des fichiers et la non-répudiation (RSA-PSS).
- Stockage sécurisé des paires de clés RSA des utilisateurs, chiffrées avec la phrase de passe de l'utilisateur (PBKDF2).
- Flux de déchiffrement côté serveur et côté client pour une flexibilité accrue.
- Partage de fichiers avec d'autres utilisateurs, assurant un échange de clés sécurisé.
- Journalisation d'audit pour les activités liées aux fichiers.

## Prérequis

Avant de commencer, assurez-vous d'avoir les éléments suivants installés sur votre système :

- **PHP**: ^8.2 (avec les extensions OpenSSL, Mbstring, PDO, BCMath)
- **Composer**: ^2.0
- **Node.js**: ^18.0 (LTS recommandé)
- **npm**: ^9.0 (ou Yarn)
- **Base de données**: MySQL, PostgreSQL, SQLite (SQLite est suffisant pour le développement local)

## Installation

1.  **Installer les dépendances PHP :**
    ```bash
    composer install
    ```

2.  **Installer les dépendances JavaScript :**
    ```bash
    npm install
    # ou yarn install
    ```

## Configuration

1.  **Fichier d'environnement :**
    Ouvrez le fichier `.env` et configurez votre connexion à la base de données ainsi que les autres variables d'environnement.

    -   **Pour MySQL :**
        ```env
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=securia
        DB_USERNAME=root
        DB_PASSWORD=
        ```
    -   **Pour PostgreSQL :**
        ```env
        DB_CONNECTION=pgsql
        DB_HOST=127.0.0.1
        DB_PORT=5432
        DB_DATABASE=securia
        DB_USERNAME=postgres
        DB_PASSWORD=
        ```
    -   **Pour SQLite :**
        Si vous préférez SQLite pour le développement local, assurez-vous d'avoir un fichier `database.sqlite` vide dans le répertoire `database/`. Ensuite, configurez votre `.env` comme suit :
        ```env
        DB_CONNECTION=sqlite
        # DB_DATABASE=/chemin/vers/votre/base_de_donnees.sqlite (ou laisser vide si vous utilisez le chemin par défaut : database/database.sqlite)
        ```
        (Vous pouvez supprimer les lignes `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` pour SQLite car elles ne sont généralement pas utilisées.)

2.  **Migration de la base de données :**
    Exécutez les migrations de la base de données pour créer les tables nécessaires :
    ```bash
    php artisan migrate
    ```

## Lancement de l'Application

1.  **Démarrer le serveur de développement :**
    ```bash
    php artisan serve
    ```
    Ceci lancera généralement l'application à l'adresse `http://localhost:8000`.

2.  **Lancer le serveur de développement Vite pour le frontend :**
    ```bash
    npm run dev
    # ou yarn dev
    ```
    Ceci compilera vos ressources React et activera le rechargement à chaud.

    Votre application devrait maintenant être accessible dans votre navigateur web à l'adresse `http://localhost:8000`.

## Exécution des Tests

Pour exécuter les tests automatisés de l'application :

```bash
php artisan test
```

## Contribution

N'hésitez pas à contribuer au développement de Securia. Veuillez suivre le flux de travail Git standard : forker le dépôt, créer une nouvelle branche pour vos fonctionnalités/correctifs, et soumettre une Pull Request.

## Licence

Securia est un logiciel open-source sous licence [MIT](https://opensource.org/licenses/MIT).
