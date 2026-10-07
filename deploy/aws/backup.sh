#!/usr/bin/env bash
set -euo pipefail
umask 077
project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$project_dir"
bucket=${1:-}
[[ "$bucket" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ ]] || { echo 'Indiquer le nom du bucket S3 de sauvegarde.'; exit 1; }
test -f .env.aws
command -v aws >/dev/null
install -d -m 700 backups
dump="backups/bakalorea-$(date -u +%Y%m%dT%H%M%SZ).dump"
docker compose --project-name bakalorea --env-file .env.aws -f compose.yaml -f deploy/aws/compose.aws.yaml exec -T postgres pg_dump -U bakalorea -d bakalorea -Fc > "$dump"
test -s "$dump"
aws s3 cp "$dump" "s3://$bucket/backups/$(basename "$dump")" --sse AES256 --only-show-errors
echo "Sauvegarde envoyée : s3://$bucket/backups/$(basename "$dump")"
# Local dumps are retained. Review their size/retention with the operator.
