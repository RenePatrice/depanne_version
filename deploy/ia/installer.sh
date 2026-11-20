#!/usr/bin/env bash
# Installation du serveur de l'assistant Parema sur un VPS Ubuntu 22.04/24.04.
#
#   sudo ./installer.sh ia.parema-connect.com qwen2.5:3b-instruct
#
# Le nom de domaine doit déjà pointer vers l'IP du serveur (enregistrement A).
# Le script installe Docker, ferme le pare-feu sauf SSH/HTTP/HTTPS, génère le
# jeton, démarre Ollama + Caddy, télécharge le modèle et fait un essai.
set -euo pipefail

DOMAINE="${1:?Usage : sudo ./installer.sh <domaine> [modele]}"
MODELE="${2:-qwen2.5:3b-instruct}"
DOSSIER="$(cd "$(dirname "$0")" && pwd)"
cd "$DOSSIER"

if [ "$(id -u)" -ne 0 ]; then
  echo "Lancer avec sudo." >&2
  exit 1
fi

echo "==> Docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

echo "==> Pare-feu (SSH, HTTP, HTTPS seulement)"
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
  ufw --force enable >/dev/null
fi

echo "==> Jeton"
if [ ! -f .env ]; then
  JETON="$(openssl rand -hex 32)"
  printf 'DOMAINE=%s\nOLLAMA_TOKEN=%s\n' "$DOMAINE" "$JETON" > .env
  chmod 600 .env
fi
# shellcheck disable=SC1091
. ./.env

echo "==> Démarrage"
docker compose up -d

echo "==> Téléchargement du modèle $MODELE (quelques minutes)"
docker compose exec -T ollama ollama pull "$MODELE"

echo "==> Préchauffage"
docker compose exec -T ollama ollama run "$MODELE" "Réponds OK." >/dev/null

echo "==> Essai par HTTPS (le certificat peut prendre une minute)"
for _ in $(seq 1 30); do
  if curl -fsS -o /dev/null -H "Authorization: Bearer $OLLAMA_TOKEN" "https://$DOMAINE/api/tags"; then
    break
  fi
  sleep 4
done
CODE_SANS="$(curl -s -o /dev/null -w '%{http_code}' "https://$DOMAINE/api/tags" || true)"
CODE_AVEC="$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $OLLAMA_TOKEN" "https://$DOMAINE/api/tags" || true)"
echo "    sans jeton : $CODE_SANS (attendu 401)"
echo "    avec jeton : $CODE_AVEC (attendu 200)"

cat <<FIN

Serveur prêt. Variables à poser dans Vercel (Settings > Environment Variables) :

  ASSISTANT_DRIVER=ollama
  OLLAMA_URL=https://$DOMAINE
  OLLAMA_MODEL=$MODELE
  OLLAMA_TOKEN=(voir le fichier $DOSSIER/.env — ne pas le copier dans un chat)
  OLLAMA_TIMEOUT=25

FIN
