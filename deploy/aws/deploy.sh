#!/usr/bin/env bash
set -euo pipefail
umask 077
project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$project_dir"
[[ $EUID -eq 0 ]] || { echo 'Utiliser sudo bash deploy/aws/deploy.sh jeu.votre-domaine.fr'; exit 1; }
domain=${1:-}
[[ ${#domain} -le 253 && "$domain" =~ ^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$ ]] || { echo 'Indiquer un nom de domaine valide en minuscules, sans https:// ni chemin.'; exit 1; }
docker compose version >/dev/null
command -v openssl >/dev/null
if [[ ! -f .env.aws ]]; then
    secret_file=$(mktemp "$project_dir/.env.aws.XXXXXX")
    trap '[[ -z ${secret_file:-} ]] || rm -f -- "$secret_file"' EXIT
    {
        printf 'DOMAIN=%s\nAPP_KEY=base64:%s\nDB_PASSWORD=%s\n' "$domain" "$(openssl rand -base64 32)" "$(openssl rand -hex 32)"
        printf 'REVERB_APP_ID=bakalorea\nREVERB_APP_KEY=%s\nREVERB_APP_SECRET=%s\n' "$(openssl rand -hex 24)" "$(openssl rand -hex 32)"
        printf 'SITE_ADDRESS=%s\nHTTP_PORT=80\nHTTPS_PORT=443\nREVERB_PUBLIC_HOST=%s\nREVERB_PUBLIC_PORT=443\nREVERB_PUBLIC_SCHEME=https\nREVERB_ALLOWED_ORIGINS=%s\n' "$domain" "$domain" "$domain"
    } > "$secret_file"
    mv -- "$secret_file" .env.aws
    secret_file=''
else
    grep -Fxq "DOMAIN=$domain" .env.aws || { echo 'Le domaine diffère de .env.aws. Modifier explicitement le domaine et les paramètres REVERB avant de relancer.'; exit 1; }
fi
chmod 600 .env.aws
compose=(docker compose --project-name bakalorea --env-file .env.aws -f compose.yaml -f deploy/aws/compose.aws.yaml)
"${compose[@]}" config --quiet
# Compile before touching the running services.
"${compose[@]}" build backend caddy
"${compose[@]}" up -d --wait postgres redis
# Preserve a pre-migration database dump even on the first installation.
install -d -m 700 backups
dump="backups/before-deploy-$(date -u +%Y%m%dT%H%M%SZ).dump"
"${compose[@]}" exec -T postgres pg_dump -U bakalorea -d bakalorea -Fc > "$dump"
test -s "$dump"
# Always re-run the one-shot migration service; never rely on an old exit status.
"${compose[@]}" up -d --force-recreate migrate
"${compose[@]}" wait migrate
"${compose[@]}" up -d --no-build backend reverb scheduler caddy
"${compose[@]}" ps
for attempt in $(seq 1 30); do
    if curl --fail --silent --show-error --max-time 8 "https://$domain/api/health" >/dev/null 2>&1; then
        echo "Application accessible : https://$domain"
        exit 0
    fi
    sleep 4
done
echo "Les services ont été lancés, mais HTTPS n’est pas encore confirmé. Vérifier le DNS A du domaine, les ports 80/443 et les logs Caddy."
exit 1
