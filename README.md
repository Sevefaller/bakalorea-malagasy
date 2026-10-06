# Bakalorea — Lettres & famille

Application multijoueur construite à partir de **Cahier_des_charges_Jeu_Familial_Phases.pdf**. Vue 3, TypeScript, Pinia, Vue Router, Vue I18n ; Laravel 12, Sanctum, Reverb ; PostgreSQL, Redis et Caddy via Docker.

## Jouer

1. Ouvrir l’application, saisir un pseudo et créer une salle.
2. Communiquer le code aux autres joueurs. Ils choisissent **Rejoindre** depuis leur appareil.
3. Le créateur lance la manche dès que deux joueurs sont connectés.
4. Après le tirage, chacun saisit sa réponse avant STOP. La saisie est enregistrée automatiquement.
5. Voter sur les réponses des autres dans **Mitsara**, puis afficher les résultats.

Le sélecteur MG / FR / EN change uniquement la langue du joueur. Les sons peuvent être désactivés dans la barre supérieure.

## Démarrage complet — Docker

Prérequis : Docker Desktop démarré en mode conteneurs Linux et Node.js 24.

```sh
npm run docker
```

Cette commande génère les secrets locaux s’ils n’existent pas, construit les images, exécute les migrations et démarre les services. Ouvrir **http://localhost:8088**. Les données sont conservées dans les volumes Docker.

Après la première initialisation, `docker compose up -d --build` fonctionne directement. Arrêt : `docker compose stop`. Ne pas supprimer les volumes pour conserver les parties.

## Développement local rapide

Prérequis : PHP 8.5 avec PDO SQLite, Composer et Node.js 24. Sous Windows, le lanceur active les extensions SQLite pour ses processus PHP.

```sh
npm start
```

Ouvrir **http://localhost:5173**. Le lanceur installe les dépendances manquantes, migre SQLite, puis démarre Laravel, Reverb et Vite. Ce mode utilise SQLite et des verrous sur fichiers ; le mode Docker utilise PostgreSQL et Redis. En développement léger, chaque lecture de l’état avance aussi les échéances ; Docker ajoute un ordonnanceur indépendant.

Pour plusieurs joueurs sur le même ordinateur, utiliser des profils de navigateur distincts ou une fenêtre privée : un seul joueur est mémorisé par profil, et un seul onglet actif par joueur.

Sur le même Wi-Fi, ouvrir `http://ADRESSE-IP-DU-PC:8088` pour Docker (ou le port 5173 en développement). Ajouter cette adresse IP à `REVERB_ALLOWED_ORIGINS` dans le `.env` de l’environnement et redémarrer Reverb ; autoriser le port du jeu dans le pare-feu si nécessaire. L’installation PWA sur téléphone nécessite un domaine HTTPS. Les notifications manquées sont rattrapées par une lecture de l’état toutes les 1,5 secondes.

## Fonctionnalités livrées

- Salles de 2 à 12 joueurs, code d’invitation, présence et transfert du rôle de maître du jeu après 45 secondes d’absence.
- Huit catégories, dans l’ordre du PDF, traduites dans les trois langues.
- Tirage serveur et animation, lettres configurables et mode sans répétition.
- Modes 10 / 15 / 30 secondes, compte à rebours fondé sur l’heure du serveur, sons.
- Sauvegarde avec numéros de révision, reprise de session, réponses privées avant STOP.
- Votes valide / incorrect / incertain, arbitrage des égalités et détection des doublons.
- Barème configurable, classement, victoire, manches de départage et historique.
- Journal de sorties d’écran ; modes souple, normal et strict.
- Manifeste PWA, icônes, service worker et page hors connexion. Une partie nécessite Internet.
- Tests automatisés du serveur et du parcours navigateur.

## Tests

```sh
# À la racine
npm run build

# Dans backend (Windows)
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit

# Dans backend (Linux avec PDO SQLite)
php vendor/phpunit/phpunit/phpunit

# Dans frontend, application déjà lancée sur localhost:5173
npm run test:e2e
```

Les tests navigateur utilisent Microsoft Edge installé. Pour viser Docker sous PowerShell : `$env:GAME_URL='http://localhost:8088'; npm run test:e2e`. La suite de tests n’utilise pas les données existantes du joueur ; elle crée ses propres salles. Les tests PHPUnit utilisent une base SQLite en mémoire.

## Documents

- [Règles et décisions fonctionnelles](docs/REGLES.md)
- [Déploiement, sauvegarde et restauration](docs/DEPLOIEMENT.md)
- [État de validation](docs/VALIDATION.md)

La recette familiale, les essais sur de vrais Android/iPhone et la publication sur un domaine HTTPS restent des étapes à réaliser dans l’environnement choisi. Le signalement des sorties d’écran est un indice : un navigateur modifié ou un second appareil ne peuvent pas être détectés de manière fiable.

Références techniques : [Vue et TypeScript](https://vuejs.org/guide/typescript/overview), [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum), [Laravel Reverb](https://laravel.com/docs/12.x/reverb).
