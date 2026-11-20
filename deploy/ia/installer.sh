#!/usr/bin/env bash
# Installation du serveur de l'assistant Parema sur un VPS Ubuntu 22.04/24.04.
#
#   sudo ./installer.sh                         # sans domaine : adresse sslip.io
#   sudo ./installer.sh ia.mondomaine.com       # avec un domaine qui pointe ici
#   sudo ./installer.sh "" qwen2.5:7b-instruct  # sans domaine, autre modèle
#
# Sans domaine, l'adresse est construite sur l'IP publique via sslip.io
# (ia.203-0-113-10.sslip.io → 203.0.113.10) : gratuit, sans inscription, et
# compatible avec le certificat HTTPS automatique.
#
# Sur un serveur qui fait déjà tourner d'autres services, le script ne touche
# à rien d'existant : il vérifie que les ports 80 et 443 sont libres et
# s'arrête sinon ; il ajoute des règles de pare-feu sans l'activer.
set -euo pipefail

MODELE="${2:-qwen2.5:3b-instruct}"
DOSSIER="$(cd "$(dirname "$0")" && pwd)"
cd "$DOSSIER"

if [ "$(id -u)" -ne 0 ]; then
  echo "Lancer avec sudo." >&2
  exit 1
fi

if [ -n "${1:-}" ]; then
  DOMAINE="$1"
else
  IP="$(curl -4fsS https://api.ipify.org)"
  DOMAINE="ia.${IP//./-}.sslip.io"
fi
echo "==> Adresse du serveur IA : https://$DOMAINE"

echo "==> Ports 80 et 443"
if [ ! -f .env ] && ss -ltnH '( sport = :80 or sport = :443 )' | grep -q .; then
  echo "Les ports 80/443 sont déjà utilisés par un autre service :" >&2
  ss -ltnp '( sport = :80 or sport = :443 )' >&2 || true
  echo "Rien n'a été modifié. Voir docs/ia-assistant.md, « Serveur déjà équipé d'un proxy »." >&2
  exit 1
fi

echo "==> Docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

echo "==> Pare-feu"
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
  echo "    règles 80/443 ajoutées (les autres règles sont inchangées)"
else
  echo "    pare-feu inactif : non modifié (Ollama reste de toute façon interne à Docker)"
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
