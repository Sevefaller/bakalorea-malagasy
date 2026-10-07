# Contrôles du paquet — 7 octobre 2026

- Modèle `stack.yaml` : validation **cfn-lint** réussie, sans erreur.
- Scripts `bootstrap.sh`, `deploy.sh` et `backup.sh` : syntaxe Bash vérifiée dans Linux.
- Fusion du fichier Compose principal et de la surcharge AWS : `docker compose config --quiet` réussi avec des valeurs de validation non secrètes.
- Versions Docker Compose 2.38.2 et Buildx 0.25.0 : fichiers de sommes de contrôle officiels accessibles, avec les binaires attendus.
- Archive : présence du code source, des migrations, du logo et des lockfiles ; absence des `.env` locaux, clés, dépendances installées, bases SQLite, logs et sauvegardes. Vérification supplémentaire de l’absence des secrets locaux dans les fichiers textuels.
- Application : les tests fonctionnels locaux précédents sont décrits dans `docs/VALIDATION.md`.

La création de la pile EC2, la résolution DNS, l’émission du certificat HTTPS, les droits effectifs du compte AWS et la sauvegarde/restauration S3 n’ont pas été exécutés dans un compte AWS. Ils sont à vérifier pendant le déploiement. Le paquet prépare ces opérations sans créer de ressource facturée.
