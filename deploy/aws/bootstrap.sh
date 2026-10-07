#!/usr/bin/env bash
# Amazon Linux 2023 x86_64. Run as root; no application secrets required.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo 'Utiliser sudo bash deploy/aws/bootstrap.sh'; exit 1; }
[[ $(uname -m) == x86_64 ]] || { echo 'Ce paquet cible EC2 x86_64 (t3/t3a).'; exit 1; }
dnf install -y docker unzip openssl
systemctl enable --now docker
plugin_dir=/usr/local/lib/docker/cli-plugins
install -d -m 755 "$plugin_dir"
download_dir=$(mktemp -d)
trap 'rm -rf -- "$download_dir"' EXIT
install_plugin() {
    local repo=$1 version=$2 asset=$3 destination=$4
    local staging="$download_dir/$destination"
    mkdir -p "$staging"
    curl --fail --location --retry 3 "https://github.com/docker/$repo/releases/download/$version/$asset" -o "$staging/$asset"
    curl --fail --location --retry 3 "https://github.com/docker/$repo/releases/download/$version/checksums.txt" -o "$staging/checksums.txt"
    (cd "$staging" && sha256sum --check --ignore-missing checksums.txt)
    install -m 755 "$staging/$asset" "$plugin_dir/$destination"
}
install_plugin compose v2.38.2 docker-compose-linux-x86_64 docker-compose
install_plugin buildx v0.25.0 buildx-v0.25.0.linux-amd64 docker-buildx
docker compose version
docker buildx version
echo 'Docker et ses outils sont prêts.'
