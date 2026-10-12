#!/usr/bin/env bash
set -euo pipefail
umask 077

project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$project_dir"

[[ $EUID -eq 0 ]] || {
    echo 'Utiliser sudo bash deploy/aws/deploy.sh bakalorea.duckdns.org'
    exit 1
}

domain=${1:-}

[[ ${#domain} -le 253 && "$domain" =~ ^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$ ]] || {
    echo "Indiquer un nom de domaine valide en minuscules, sans https:// ni chemin."
    exit 1
}

docker compose version >/dev/null
command -v openssl >/dev/null

if [[ ! -f .env.aws ]]; then
    secret_file=$(mktemp "$project_dir/.env.aws.XXXXXX")

    trap '[[ -z ${secret_file:-} ]] || rm -f -- "$secret_file"' EXIT

    {
        printf 'DOMAIN=%s\n' "$domain"
        printf 'APP_KEY=base64:%s\n' "$(openssl rand -base64 32)"
        printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 32)"

        printf 'REVERB_APP_ID=bakalorea\n'
        printf 'REVERB_APP_KEY=%s\n' "$(openssl rand -hex 24)"
        printf 'REVERB_APP_SECRET=%s\n' "$(openssl rand -hex 32)"

        printf 'SITE_ADDRESS=%s\n' "$domain"
        printf 'HTTP_PORT=80\n'
        printf 'HTTPS_PORT=443\n'

        printf 'REVERB_PUBLIC_HOST=%s\n' "$domain"
        printf 'REVERB_PUBLIC_PORT=443\n'
        printf 'REVERB_PUBLIC_SCHEME=https\n'
        printf 'REVERB_ALLOWED_ORIGINS=%s\n' "$domain"

    } > "$secret_file"

    mv -- "$secret_file" .env.aws
    secret_file=''

else

    grep -Fxq "DOMAIN=$domain" .env.aws || {
        echo "L'adresse diffère de celle enregistrée dans .env.aws."
        echo "Modifier explicitement .env.aws avant de relancer."
        exit 1
    }

fi

for expected in \
    "DOMAIN=$domain" \
    "SITE_ADDRESS=$domain" \
    "REVERB_PUBLIC_HOST=$domain" \
    'REVERB_PUBLIC_PORT=443' \
    'REVERB_PUBLIC_SCHEME=https' \
    "REVERB_ALLOWED_ORIGINS=$domain"
do
    grep -Fxq "$expected" .env.aws || {
        echo "Configuration HTTPS manquante dans .env.aws : $expected"
        exit 1
    }
done

chmod 600 .env.aws

compose=(
    docker compose
    --project-name bakalorea
    --env-file .env.aws
    -f compose.yaml
    -f deploy/aws/compose.aws.yaml
)

"${compose[@]}" config --quiet

# Construire avant de modifier les services en cours.
"${compose[@]}" build backend caddy

# PostgreSQL + Redis
"${compose[@]}" up -d --wait postgres redis

# Sauvegarde avant migration
install -d -m 700 backups

dump="backups/before-deploy-$(date -u +%Y%m%dT%H%M%SZ).dump"

"${compose[@]}" exec -T postgres \
    pg_dump -U bakalorea -d bakalorea -Fc > "$dump"

test -s "$dump"

# Migration Laravel
"${compose[@]}" up -d --force-recreate migrate
"${compose[@]}" wait migrate

# Services applicatifs
"${compose[@]}" up -d --no-build backend reverb scheduler caddy

"${compose[@]}" ps

# Vérification HTTPS
for attempt in $(seq 1 30); do

    if curl \
        --fail \
        --silent \
        --show-error \
        --max-time 8 \
        "https://$domain/api/health" >/dev/null 2>&1
    then
        echo
        echo "Application accessible : https://$domain"
        exit 0
    fi

    sleep 4
done

echo
echo "Les services ont été lancés, mais HTTPS n'est pas encore confirmé."
echo "Vérifier :"
echo "- le DNS A du domaine et les ports 80/443 du Security Group"
echo "- les logs Caddy"
echo "- les logs backend"
echo
echo "Commandes utiles :"
echo "${compose[*]} ps"
echo "${compose[*]} logs --tail 100 caddy backend reverb"

exit 1
