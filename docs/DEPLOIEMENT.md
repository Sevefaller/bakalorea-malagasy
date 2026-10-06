# Déploiement et exploitation

## Environnement local Docker

`npm run docker` lance PostgreSQL 17, Redis 7, Laravel/PHP 8.5, Reverb, le planificateur Laravel et Caddy avec le frontend compilé. `migrate` est un service ponctuel dont les autres services attendent le succès.

Les fichiers `.env` sont ignorés par Git et par Docker. L’initialisation crée de nouveaux secrets aléatoires uniquement lorsqu’ils manquent. Le processus de construction ne copie aucun `.env` dans les images.

## Serveur HTTPS

1. Préparer un serveur avec Docker Compose et un nom de domaine pointant vers lui.
2. Copier le projet sans les fichiers `.env` locaux, puis exécuter `node scripts/init.mjs` pour créer des secrets propres à cet environnement.
3. Dans le `.env` racine, définir `SITE_ADDRESS=jeu.votre-domaine.fr`, `HTTP_PORT=80`, `HTTPS_PORT=443`, `REVERB_PUBLIC_HOST=jeu.votre-domaine.fr`, `REVERB_PUBLIC_PORT=443`, `REVERB_PUBLIC_SCHEME=https`, `REVERB_ALLOWED_ORIGINS=jeu.votre-domaine.fr`.
4. Exécuter `docker compose up -d --build`. Caddy obtient et renouvelle les certificats lorsque les ports publics 80 et 443 sont joignables.
5. Vérifier `/api/health`, les états avec `docker compose ps`, puis jouer une partie réelle à deux appareils. Vérifier l’installation PWA sur HTTPS.

PostgreSQL et Redis n’exposent aucun port sur l’hôte. L’accès public passe par Caddy. Utiliser un environnement de staging et des secrets distincts avant la production. Le proxy doit conserver un routage de confiance pour les adresses clients si une protection réseau supplémentaire est ajoutée.

## Sauvegarde et restauration

La sauvegarde PostgreSQL contient les salles, jetons, réponses, votes et scores. La protéger comme une donnée privée. Redis contient surtout des verrous, sessions et limites de fréquence ; les scores durables sont dans PostgreSQL.

```sh
# Sauvegarde dans le conteneur, puis copie sans redirection binaire PowerShell
docker compose exec -T postgres pg_dump -U bakalorea -d bakalorea -Fc -f /tmp/bakalorea.dump
docker compose cp postgres:/tmp/bakalorea.dump ./bakalorea.dump

# Test de restauration dans une base séparée, sans écraser la base en service
docker compose exec -T postgres createdb -U bakalorea bakalorea_restore_check
docker compose exec -T postgres pg_restore -U bakalorea -d bakalorea_restore_check /tmp/bakalorea.dump
docker compose exec -T postgres psql -U bakalorea -d bakalorea_restore_check -c "SELECT count(*) FROM games;"
```

Pour restaurer une sauvegarde externe, commencer par `docker compose cp ./bakalorea.dump postgres:/tmp/bakalorea.dump`. Valider la base de contrôle avant une bascule planifiée. La restauration de production et la suppression de bases nécessitent une décision explicite de l’exploitant ; aucune commande destructive n’est exécutée automatiquement.

Prévoir une sauvegarde quotidienne chiffrée hors du serveur, une rétention adaptée et un test de restauration périodique. Conserver aussi les secrets `.env` et le volume Caddy de façon sécurisée.

## Supervision et mises à jour

- `docker compose ps` : santé des processus ; le backend teste PostgreSQL et le cache, Reverb teste son port.
- `docker compose logs --tail 100 backend reverb scheduler caddy` : diagnostic. Les réponses refusées tardivement et remplacements de session sont journalisés sans texte de réponse ni jeton.
- Les journaux Docker tournent à 10 Mo × 3 ; les accès Caddy ont une rotation équivalente.
- Avant mise à jour : sauvegarder PostgreSQL, valider sur staging, construire les images et exécuter les migrations contrôlées.
- `docker compose up -d --build` recrée les services en conservant les volumes. Programmer l’opération hors des parties : cette configuration simple ne garantit pas un déploiement sans interruption.
- Ajouter une supervision externe d’URL et une politique de rétention des anciennes parties avant une ouverture publique importante.

## Évolutions prévues au cahier des charges

QR codes, profils persistants, statistiques, équipes, catégories personnalisées et avatars sont des évolutions futures, non des fonctionnalités du MVP. Les initiales de pseudo servent actuellement de repère visuel.
