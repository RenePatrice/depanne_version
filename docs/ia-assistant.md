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

## 2. Pointer un nom de domaine

Créer un enregistrement DNS **A** vers l'adresse IP du serveur, par exemple :

```
ia.parema-connect.com   A   203.0.113.10
```

Le certificat HTTPS est obtenu automatiquement par Caddy au premier démarrage :
le domaine doit pointer vers le serveur **avant** l'installation.

## 3. Installer

Depuis votre poste, copier le dossier sur le serveur puis lancer le script :

```bash
scp -r deploy/ia utilisateur@203.0.113.10:~/parema-ia
ssh utilisateur@203.0.113.10
cd ~/parema-ia
chmod +x installer.sh
sudo ./installer.sh ia.parema-connect.com qwen2.5:3b-instruct
```

Le script, dans l'ordre :

1. installe Docker s'il manque ;
2. ferme le pare-feu sauf SSH, HTTP et HTTPS (Ollama n'est jamais exposé) ;
3. génère un **jeton aléatoire** dans `~/parema-ia/.env` (lisible par root seul) ;
4. démarre Ollama et Caddy ;
5. télécharge le modèle (quelques minutes) et le préchauffe ;
6. vérifie l'accès : **401 sans jeton, 200 avec jeton**.

Il affiche à la fin les variables à poser dans Vercel.

## 4. Brancher sur Vercel

Vercel → projet **parema-connect** → **Settings → Environment Variables**
(environnement *Production*) :

| Variable | Valeur |
|---|---|
| `ASSISTANT_DRIVER` | `ollama` |
| `OLLAMA_URL` | `https://ia.parema-connect.com` |
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
