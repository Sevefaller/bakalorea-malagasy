# Déployer Bakalorea sur AWS EC2

Ce paquet contient l’application complète, son logo et son thème, les Dockerfiles, les fichiers de dépendances verrouillées, un modèle CloudFormation et les scripts de déploiement. Il ne contient aucun secret ni donnée de partie locale.

Architecture : une instance **EC2 Amazon Linux 2023 x86_64**, Docker Compose, PostgreSQL, Redis, Laravel/Reverb et Caddy. Caddy sert l’application et gère HTTPS. Cette première configuration utilise un seul serveur ; elle n’est pas à haute disponibilité.

## 1. Créer le serveur

Dans la console AWS, sélectionner la région souhaitée puis **CloudFormation → Créer une pile → Charger un fichier modèle**. Charger `deploy/aws/stack.yaml` (également livré séparément avec l’archive).

- Sélectionner un **VPC existant** et un **sous-réseau public du même VPC**, avec une route vers une Internet Gateway.
- Conserver `t3.medium` pour commencer, ou choisir une des tailles proposées selon les besoins. Le disque gp3 chiffré fait 40 Go.
- Accepter la création des ressources IAM, puis créer la pile.

Les sorties de la pile donnent `InstanceId`, `PublicIp` et `BucketName`. Le modèle crée un serveur, une IPv4 publique fixe et un bucket privé S3. Ces ressources AWS sont facturées ; aucun déploiement n’a été exécuté depuis le poste de développement.

L’administration passe par **EC2 → Instance → Connexion → Session Manager**, sans port SSH ouvert. Les seuls ports entrants sont 80 et 443. Attendre la fin de l’initialisation de l’instance ; les logs sont dans `/var/log/cloud-init-output.log`. Le statut CloudFormation ne valide pas encore l’application.

## 2. Configurer le domaine

Créer un enregistrement DNS **A** pour `jeu.votre-domaine.fr` vers `PublicIp`. Ne pas ajouter d’AAAA sans configurer IPv6. Attendre la résolution du nom vers cette adresse avant le lancement de l’application.

Le domaine peut être géré chez Route 53 ou chez votre fournisseur actuel. Aucun domaine n’est acheté ou modifié par le modèle.

## 3. Transférer l’archive

Dans la console S3, ouvrir le bucket indiqué par `BucketName`. Charger **bakalorea-aws.zip** avec la clé `releases/bakalorea-aws.zip`. Conserver le bucket privé.

Ouvrir une session Session Manager sur l’instance, puis remplacer `NOM-DU-BUCKET` et le domaine dans ces commandes :

```bash
sudo -i
aws s3 cp s3://NOM-DU-BUCKET/releases/bakalorea-aws.zip /tmp/bakalorea-aws.zip
unzip /tmp/bakalorea-aws.zip -d /opt/bakalorea
cd /opt/bakalorea
bash deploy/aws/bootstrap.sh
bash deploy/aws/deploy.sh jeu.votre-domaine.fr
```

`bootstrap.sh` installe Docker Compose 2.38.2 et Buildx 0.25.0 depuis les publications officielles Docker, en vérifiant leurs sommes SHA-256. Cette étape et la construction des images nécessitent un accès Internet sortant (GitHub, Docker Hub et registres de dépendances).

`deploy.sh` génère `.env.aws` avec permissions 600 lors du premier lancement. Il conserve les secrets lors des relances, construit les images, sauvegarde la base avant migration, lance les services et vérifie l’URL HTTPS. Il n’exécute jamais `down -v` ou une réinitialisation de base.

Ouvrir ensuite **https://jeu.votre-domaine.fr** et tester une partie depuis deux appareils. Les WebSockets passent par le même domaine HTTPS. L’installation PWA est disponible depuis un navigateur compatible.

## 4. Commandes d’exploitation

Toutes les commandes doivent employer `.env.aws` et le fichier de surcharge AWS :

```bash
cd /opt/bakalorea
docker compose --env-file .env.aws -f compose.yaml -f deploy/aws/compose.aws.yaml ps
docker compose --env-file .env.aws -f compose.yaml -f deploy/aws/compose.aws.yaml logs --tail 100 backend reverb scheduler caddy
```

Les conteneurs redémarrent après un redémarrage EC2 grâce à Docker et `restart: unless-stopped`. PostgreSQL et Redis ne sont pas exposés sur Internet. Les journaux ont une rotation limitée. Le fichier `.env.aws` doit être sauvegardé séparément dans un emplacement privé sécurisé ; la sauvegarde SQL ne contient pas la clé Laravel ni les secrets Reverb.

## 5. Sauvegardes

Sauvegarde manuelle chiffrée côté S3, accessible seulement au rôle autorisé :

```bash
cd /opt/bakalorea
bash deploy/aws/backup.sh NOM-DU-BUCKET
```

Le bucket conserve les sauvegardes courantes 30 jours ; avec le versionnement S3, les anciennes versions sont également expirées après 30 jours. Les copies locales du dossier `backups/` sont conservées et doivent être surveillées pour éviter de remplir le disque.

Pour automatiser chaque jour à 02:00 UTC, créer les fichiers suivants après avoir remplacé le bucket :

```ini
# /etc/systemd/system/bakalorea-backup.service
[Unit]
Description=Sauvegarde PostgreSQL Bakalorea vers S3
After=docker.service network-online.target
Requires=docker.service
[Service]
Type=oneshot
WorkingDirectory=/opt/bakalorea
ExecStart=/bin/bash /opt/bakalorea/deploy/aws/backup.sh NOM-DU-BUCKET
```

```ini
# /etc/systemd/system/bakalorea-backup.timer
[Unit]
Description=Sauvegarde quotidienne Bakalorea
[Timer]
OnCalendar=*-*-* 02:00:00 UTC
Persistent=true
[Install]
WantedBy=timers.target
```

Puis `systemctl daemon-reload` et `systemctl enable --now bakalorea-backup.timer`. Vérifier `journalctl -u bakalorea-backup.service` et la présence des fichiers sur S3.

Tester une restauration vers **une base séparée**, en suivant `docs/DEPLOIEMENT.md` et en ajoutant aux commandes Compose `--env-file .env.aws -f compose.yaml -f deploy/aws/compose.aws.yaml`. Ne pas restaurer par-dessus la production sans décision explicite et sauvegarde récente.

## 6. Mise à jour et limites

Charger une nouvelle archive dans S3, la télécharger sur EC2, extraire avec `unzip -o` dans `/opt/bakalorea`, puis relancer `bash deploy/aws/deploy.sh jeu.votre-domaine.fr`. L’archive ne contient pas `.env.aws` : la configuration de production est préservée. Prévoir une fenêtre hors des parties ; le déploiement peut interrompre brièvement les connexions.

Ne pas utiliser simultanément le fichier `.env` du développement et `.env.aws` pour piloter les mêmes conteneurs. Ne pas changer les secrets PostgreSQL à la main après initialisation : le volume conserve le mot de passe déjà créé.

La pile conserve volontairement EC2, son disque et S3 lors d’une suppression/remplacement pour éviter une perte accidentelle. Une suppression CloudFormation ne suffit donc pas à arrêter toute facturation et peut nécessiter de traiter les dépendances du serveur conservé. Sauvegarder, puis retirer explicitement les ressources conservées seulement après validation. Une reconstruction automatique du serveur à partir des sauvegardes n’est pas incluse.

Le modèle a été préparé et contrôlé localement ; la création réelle des ressources, l’émission du certificat et la recette dans votre compte AWS restent à effectuer. Aucun identifiant AWS n’est nécessaire pour préparer ce paquet.

## Références officielles

- [Connexion EC2 via Session Manager](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/connect-with-systems-manager-session-manager.html)
- [AMI Amazon Linux 2023](https://docs.aws.amazon.com/linux/al2023/ug/ec2.html)
- [IMDSv2 et CloudFormation](https://docs.aws.amazon.com/AWSCloudFormation/latest/TemplateReference/aws-properties-ec2-instance-metadataoptions.html)
- [Installation du plugin Docker Compose](https://docs.docker.com/compose/install/linux/)
