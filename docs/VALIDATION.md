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
