# Validation — 6 octobre 2026

## Exécuté

- Compilation TypeScript et bundle de production Vue réussis.
- PHPUnit : 15 tests, 138 assertions, aucune erreur. La suite couvre confidentialité avant STOP, échéances, sauvegardes désordonnées, autorisations de lancement, double lancement, auto-vote, doublons normalisés, mode strict, égalités, points transactionnels, victoire, absence du maître du jeu ou de l’arbitre, sessions et salle à 8 joueurs.
- Navigateur Microsoft Edge : changement des trois langues, affichage 390 px sans débordement, partie à deux contextes isolés jusqu’à la victoire, réponse invisible à l’adversaire avant STOP, votes et reprise après rechargement. Deux tests réussis.
- Captures de l’accueil ordinateur/mobile, lobby, manche mobile, jugement et victoire dans `artifacts/`.

## À valider avec les utilisateurs

- Relecture linguistique des traductions malagasy et confort de 15 secondes.
- Vraies parties à 4 et 8 appareils, réseaux mobiles instables et latence élevée.
- Android, iPhone, Safari et Firefox ; clavier virtuel, audio et installation PWA réelle.
- Recette familiale, domaine HTTPS public, sauvegarde automatisée hors serveur et montée en charge avant ouverture publique.

Les tests automatisés n’équivalent pas à une recette familiale. La publication publique n’est pas effectuée sans environnement de destination.

## Mise à jour du 7 octobre 2026

- Thème adapté au [portfolio de Séverin](https://severin-ratiazafy-portfolio.vercel.app/) : palette vert pétrole et gris clair, Inter et Space Grotesk, cartes blanches et bordures fines. Icônes et page hors connexion assorties.
- Compilation de production réussie ; écran d’accueil contrôlé sur ordinateur et à 390 px, avec changement des trois langues.
- Les essais d’intégration du 6 octobre à 4 et 8 joueurs ont validé la synchronisation, la confidentialité, les votes simultanés, les doublons et la victoire sur PostgreSQL/Redis.
