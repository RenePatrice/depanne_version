# Brancher l'assistant de diagnostic (IA open source)

L'assistant de l'application propose la bonne prestation à partir de la
description du client. Il s'appuie sur un modèle open source (Qwen 2.5) servi
par **Ollama**, sur un serveur à part : Vercel ne peut pas faire tourner de
modèle. Tant que ce serveur n'est pas branché, l'application utilise son moteur
local par mots-clés, sans rien casser.

```
Application  ──►  API Parema (Vercel)  ──HTTPS + jeton──►  Caddy  ──►  Ollama (modèle)
                         │                                  (serveur IA, port 443 seul)
                         └─ repli : moteur local de l'application si le serveur IA ne répond pas
```

Tout le nécessaire est dans [`deploy/ia/`](../deploy/ia/) :

| Fichier | Rôle |
|---|---|
| `docker-compose.yml` | Ollama (modèle) + Caddy (HTTPS automatique, contrôle du jeton) |
| `Caddyfile` | n'ouvre que `/api/chat` et `/api/tags`, et seulement avec le jeton |
| `installer.sh` | installation complète en une commande sur Ubuntu |

## 1. Louer le serveur

Un VPS Linux **Ubuntu 22.04 ou 24.04**, sans carte graphique :

| Modèle | Machine minimale | Temps de réponse attendu | Précision |
|---|---|---|---|
| `qwen2.5:3b-instruct` (défaut) | 4 vCPU, 8 Go de RAM, 20 Go de disque | 5 à 15 s | bonne sur les pannes courantes |
| `qwen2.5:7b-instruct` | 8 vCPU, 16 Go de RAM, 30 Go de disque | 10 à 25 s | meilleure sur les cas ambigus |

Mesuré sur la machine de développement (8 cœurs partagés, 3B) : 6 à 18 s, 5
bonnes réponses sur 6. Avec une carte graphique (GPU), la réponse tombe sous
deux secondes ; c'est l'option à retenir si le volume grandit.

Choisir un hébergeur proche (Europe de l'Ouest) pour limiter la latence depuis
Vercel. Il faut un accès SSH root ou sudo.

## 2. Une adresse pour le serveur (domaine facultatif)

Le HTTPS est obligatoire (sans lui, le jeton circulerait en clair) et un
certificat HTTPS demande un **nom**, pas une IP. Deux possibilités :

- **Sans domaine** (recommandé pour démarrer) : le script utilise
  **sslip.io**, un service gratuit sans inscription où le nom contient l'IP :
  `ia.203-0-113-10.sslip.io` pointe vers `203.0.113.10`. Rien à configurer.
- **Avec un domaine** à vous : créer un enregistrement DNS **A**
  (`ia.parema-connect.com  A  203.0.113.10`) avant l'installation.

Dans les deux cas, Caddy obtient le certificat tout seul au premier démarrage.

## 3. Installer

Depuis votre poste, copier le dossier sur le serveur puis lancer le script :

```bash
scp -r deploy/ia utilisateur@203.0.113.10:~/parema-ia
ssh utilisateur@203.0.113.10
cd ~/parema-ia
chmod +x installer.sh
sudo ./installer.sh                        # sans domaine (adresse sslip.io)
# ou : sudo ./installer.sh ia.parema-connect.com
# ou : sudo ./installer.sh "" qwen2.5:7b-instruct
```

Le script, dans l'ordre :

1. choisit l'adresse (sslip.io si aucun domaine n'est donné) ;
2. vérifie que les ports **80 et 443 sont libres** — sinon il s'arrête sans
   rien modifier (voir plus bas, « Serveur déjà équipé d'un proxy ») ;
3. installe Docker s'il manque ;
4. si le pare-feu est actif, **ajoute** les règles 80/443 sans toucher aux
   autres ; s'il est inactif, il le laisse tel quel (vos autres services ne
   sont jamais coupés) ;
5. génère un **jeton aléatoire** dans `~/parema-ia/.env` (lisible par root seul) ;
6. démarre Ollama et Caddy, télécharge le modèle et le préchauffe ;
7. vérifie l'accès : **401 sans jeton, 200 avec jeton**.

Il affiche à la fin l'adresse et les variables à poser dans Vercel.

### Serveur déjà équipé d'un proxy

Si un autre service tient déjà les ports 80/443 (nginx, Traefik, Apache…), on
ne démarre pas Caddy : seul Ollama tourne, joignable sur `127.0.0.1:11434`
uniquement, et votre proxy existant fait le HTTPS et vérifie le jeton.

```bash
cd ~/parema-ia
JETON=$(openssl rand -hex 32); echo "OLLAMA_TOKEN=$JETON" | sudo tee .env >/dev/null; sudo chmod 600 .env
sudo docker compose -f docker-compose.yml -f proxy-existant.yml up -d ollama
sudo docker compose exec ollama ollama pull qwen2.5:3b-instruct
```

Avec **nginx** : copier [`nginx-parema-ia.conf`](../deploy/ia/nginx-parema-ia.conf)
dans `/etc/nginx/sites-available/`, y remplacer `IA_DOMAINE` (par exemple
`ia.203-0-113-10.sslip.io`) et `IA_JETON`, l'activer, puis :

```bash
sudo ln -s /etc/nginx/sites-available/nginx-parema-ia.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d IA_DOMAINE --redirect
```

Pour un autre proxy, la règle à reproduire est la même : n'ouvrir que
`/api/chat` et `/api/tags`, exiger `Authorization: Bearer <jeton>`, et
renvoyer vers `http://127.0.0.1:11434`.

### Sur un serveur partagé

Le modèle 3B occupe environ **3 à 4 Go de mémoire** en permanence (il reste
chargé pour répondre vite) et utilise tout le processeur pendant chaque
réponse. Vérifier avec `free -h` qu'il reste cette marge à côté de vos autres
services ; sinon, réduire avec `OLLAMA_NUM_PARALLEL: "1"` dans
`docker-compose.yml`.

## 4. Brancher sur Vercel

Vercel → projet **parema-connect** → **Settings → Environment Variables**
(environnement *Production*) :

| Variable | Valeur |
|---|---|
| `ASSISTANT_DRIVER` | `ollama` |
| `OLLAMA_URL` | l'adresse affichée par le script (`https://ia.203-0-113-10.sslip.io` ou votre domaine) |
| `OLLAMA_MODEL` | `qwen2.5:3b-instruct` (ou le 7B) |
| `OLLAMA_TIMEOUT` | `25` |
| `OLLAMA_TOKEN` | le jeton du fichier `~/parema-ia/.env` du serveur |

Le jeton se lit sur le serveur (`sudo cat ~/parema-ia/.env`) et se colle
directement dans Vercel. **Ne jamais le coller dans un chat, un e-mail ou le
dépôt Git.**

Puis **Deployments → ⋯ → Redeploy** pour que les variables soient prises en
compte. Aucune nouvelle version de l'application mobile n'est nécessaire.

## 5. Vérifier

Depuis l'application, ouvrir l'**Assistant** et décrire une panne
(« l'eau coule sous mon évier »). La pastille en haut passe de **« Hors
ligne »** à **« IA »**, et la réponse donne une explication en une phrase,
avec un conseil de sécurité quand il y a un danger.

Sur le serveur, pour suivre les appels :

```bash
cd ~/parema-ia
docker compose logs -f caddy      # requêtes reçues (code 200 / 401)
docker compose exec ollama ollama ps   # modèle chargé en mémoire
```

Si la pastille reste « Hors ligne » : vérifier les variables Vercel et le
redéploiement, puis tester depuis votre poste :

```bash
curl -H "Authorization: Bearer <jeton>" https://ia.parema-connect.com/api/tags
```

## Exploitation

- **Changer de modèle** : `docker compose exec ollama ollama pull qwen2.5:7b-instruct`,
  puis modifier `OLLAMA_MODEL` dans Vercel et redéployer.
- **Changer le jeton** : éditer `~/parema-ia/.env`, `docker compose up -d`,
  puis mettre à jour `OLLAMA_TOKEN` dans Vercel et redéployer.
- **Mises à jour** : changer la version des images dans `docker-compose.yml`,
  puis `docker compose pull && docker compose up -d`. Le modèle téléchargé est
  conservé (volume `ollama-modeles`).
- **Couper l'IA** sans toucher au serveur : `ASSISTANT_DRIVER=aucun` dans
  Vercel. L'application repasse sur son moteur local.

## Ce qui protège

- Ollama n'a pas d'authentification : il n'écoute que sur le réseau Docker
  interne, jamais sur Internet. Seul Caddy est exposé, en HTTPS.
- Caddy n'ouvre que deux routes (`/api/chat`, `/api/tags`) et seulement avec le
  jeton : impossible de télécharger, supprimer ou remplacer un modèle depuis
  l'extérieur.
- Le modèle ne fait que **choisir** parmi les prestations du catalogue qu'on
  lui envoie ; l'API relit chaque identifiant contre la base. Une réponse
  inventée, ou une description qui tente de détourner le modèle, n'aboutit à
  rien.
- Aucune donnée personnelle n'est envoyée : seulement le texte de la panne et
  le catalogue.
