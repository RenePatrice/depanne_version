# Parema Connect — développement sous Docker

Le **backend web** (Laravel + PostgreSQL/PostGIS + file + planificateur + Reverb)
tourne désormais dans Docker. L'**application mobile Flutter reste native**
(`devtools\flutter` + `android-sdk`) : la conteneuriser n'apporte rien pour du
build local.

## Démarrer / arrêter

```powershell
docker compose up -d --build     # tout démarrer (1er run : long — pull + build)
docker compose ps                # état des services
docker compose logs -f app       # suivre l'app
docker compose down              # arrêter (les données restent dans le volume)
docker compose down -v           # arrêter ET supprimer la base (repart de zéro)
```

Accès :

| Service | URL / port |
|---|---|
| Web (back-office + site vitrine) | http://localhost:8000 |
| Reverb (WebSocket) | ws://localhost:8080 |
| PostgreSQL (depuis l'hôte) | localhost **:5433** |

Comptes de démo (seedés au 1er lancement) :
`admin@depanne-moi.gn` / `DepanneMoi2026`.

## Services (docker-compose.yml)

- **db** — `postgis/postgis:17-3.5`. Crée `depanne_moi`, et `depanne_moi_test`
  (via `docker/db/init`). Données dans le volume `db-data`.
- **app** — image `docker/php` (PHP 8.4 + extensions + Composer + Node).
  Au 1er démarrage l'`entrypoint` installe les dépendances, construit les assets,
  migre et **sème une seule fois** (sentinelle `storage/.docker-seeded`).
- **queue / scheduler / reverb** — même image, commandes `queue:listen`,
  `schedule:work`, `reverb:start`.

`vendor/` et `node_modules/` sont des **volumes nommés** (binaires Linux),
séparés de ceux de l'hôte Windows. Le code, lui, est monté depuis
`backend-laravel/`.

## Commandes courantes

```powershell
docker compose exec app php artisan migrate --database=pgsql_migrations
docker compose exec app php artisan db:seed
docker compose exec app php artisan test
docker compose exec app npm run build        # reconstruire les assets
docker compose exec db psql -U depanne depanne_moi
```

## Pièges connus

- **On ne lance pas `php artisan serve`** : il réinjecte le fichier `.env`
  (monté, encore réglé pour le natif) dans son sous-processus, ce qui écraserait
  `DB_HOST`. On lance le serveur PHP intégré depuis `public/` avec le routeur de
  Laravel ; l'environnement du conteneur (`DB_HOST=db`) fait foi.
- **Fichier `public/hot`** : s'il traîne (laissé par un `npm run dev` natif), il
  force le mode Vite dev et casse les assets. Le supprimer :
  `del backend-laravel\public\hot`.
- **Lenteur du premier rendu (~20 s)** : le montage de fichiers Windows → Linux
  est lent (Laravel lit beaucoup de petits fichiers). Les vues se compilent une
  fois puis sont mises en cache. Pour de vraies perfs, placer le dépôt **dans le
  système de fichiers WSL2** (ext4) plutôt que sur `C:\…`.

## Migrer les données natives (optionnel)

La base Docker démarre neuve puis est seedée. Pour reprendre l'état exact de la
base native (port 5433 natif) :

```powershell
# avec la stack native arrêtée et Docker up :
# 1) dump natif -> fichier (depuis l'ancien PostgreSQL de devtools)
# 2) docker compose exec -T db psql -U depanne depanne_moi < dump.sql
```

## Natif vs Docker — ne pas lancer les deux

Les deux utilisent les ports 8000 / 8080 / 5433. Avant `docker compose up`,
arrêter la stack native (`scripts\dev.cmd` / `dev-lan.cmd`) et PostgreSQL
(`scripts\pg-stop.cmd`). Les scripts natifs restent utilisables si tu repasses
en natif.
