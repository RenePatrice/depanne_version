# Environnement de développement local

Aucun droit administrateur n'est nécessaire : tout est installé en mode
portable dans `%USERPROFILE%\devtools`, et **aucun service Windows n'est créé**.

## Ce qui est installé

| Outil | Version | Chemin | Origine |
|---|---|---|---|
| PHP NTS x64 | 8.4.25 | `devtools\php84` | windows.php.net |
| Composer | 2.10.3 | `devtools\composer` | getcomposer.org (phar + `composer.bat`) |
| PostgreSQL | 17.11 | `devtools\pgsql` | EnterpriseDB, archive binaire |
| PostGIS | 3.6.2 | fusionné dans `devtools\pgsql` | download.osgeo.org |
| Node / npm | 22.22.2 / 10.9.7 | système | déjà présent |

Ces trois chemins ont été ajoutés au **PATH utilisateur** :
`devtools\php84`, `devtools\composer`, `devtools\pgsql\bin`.
Un terminal ouvert avant l'installation ne les verra pas — il faut le rouvrir.

## Pourquoi PHP 8.4 et pas 8.3

Laravel 13 accepterait PHP 8.3, mais `spatie/laravel-model-states` 2.x — la
machine à états du ticket (§8.1) — exige `php ^8.4`.

Extensions activées dans `devtools\php84\php.ini` : `curl`, `exif`, `fileinfo`,
`gd`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `pdo_sqlite`, `pgsql`,
`sockets`, `sodium`, `zip` (auxquelles s'ajoute `bcmath`, compilée en dur sous
Windows). `sodium` est indispensable : c'est elle qui fournit **argon2id**.

Un `cacert.pem` (curl.se) est référencé par `curl.cainfo` et `openssl.cafile`,
sans quoi Composer et les appels HTTPS sortants échouent.

## Base de données

Le port **5432 est déjà occupé** par une autre instance PostgreSQL sur cette
machine. Notre cluster écoute donc sur le **5433**.

```
Données        %USERPROFILE%\devtools\pgdata
Journal        %USERPROFILE%\devtools\pgdata\server.log
Superutilisat. postgres / depanne_local
Rôle appli     depanne / depanne_local  (CREATEDB, pour la base de test)
Bases          depanne_moi, depanne_moi_test  — PostGIS activé sur les deux
```

```cmd
scripts\pg-start.cmd     :: démarrer
scripts\pg-stop.cmd      :: arrêter
scripts\pg-psql.cmd      :: session psql
```

## Démonstration du temps réel

`composer dev` lance déjà Reverb parmi ses cinq processus. Pour voir la carte
live bouger sans application mobile :

```cmd
composer demo    :: Reverb + simulation de positions pendant 10 minutes
```

`php artisan demo:positions` émet le même événement que l'API mobile émettra en
phase C3. Reverb écoute sur le port 8080 et démarre sans difficulté sous Windows
— contrairement à Horizon, il n'a pas besoin de `ext-pcntl`.

## Ce qui remplace docker-compose

Le cahier des charges prévoyait `docker-compose` (PHP-FPM, Nginx, Redis, worker,
scheduler, Reverb). Docker ayant été écarté, les mêmes processus sont lancés
côte à côte par `composer dev`, via `concurrently` :

| Processus | Rôle |
|---|---|
| `php artisan serve` | serveur web (remplace Nginx + PHP-FPM) |
| `php artisan queue:listen` | worker de queue — matching séquentiel, libération du séquestre |
| `php artisan schedule:work` | scheduler — tâches périodiques |
| `php artisan reverb:start` | WebSocket temps réel |
| `npm run dev` | Vite |

**Redis n'est pas installé.** En local, les files d'attente, le cache et les
sessions passent par PostgreSQL. Le pilote `database` de Laravel fournit des
**verrous atomiques**, donc `Cache::lock()` — sur lequel repose la protection
contre la double attribution d'un ticket — se comporte à l'identique.

Au déploiement (Linux) : basculer `QUEUE_CONNECTION`, `CACHE_STORE` et
`SESSION_DRIVER` sur `redis`, puis installer Horizon
(`composer require laravel/horizon`). Horizon exige `ext-pcntl`, absente des
builds Windows : c'est la raison de son report.

## Bascule vers Supabase

Rien à changer dans le code. Dans `.env` :

```dotenv
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=6543                      # pooler, mode transaction
DB_DATABASE=postgres
DB_USERNAME=postgres.<ref-projet>
DB_PASSWORD=<mot de passe>
DB_SSLMODE=require
DB_DISABLE_NATIVE_PREPARES=true   # obligatoire derrière le pooler

DB_MIGRATIONS_HOST=db.<ref-projet>.supabase.co
DB_MIGRATIONS_PORT=5432           # connexion directe pour les migrations
DB_MIGRATIONS_DATABASE=postgres
DB_MIGRATIONS_USERNAME=postgres
DB_MIGRATIONS_PASSWORD=<mot de passe>
```

Côté Supabase, avant la première migration : activer l'extension **PostGIS**
(`create extension postgis;`) et créer les trois buckets Storage
`identity-docs` (privé), `intervention-photos` (privé), `avatars` (public).

## Réinstaller de zéro

```cmd
scripts\setup.cmd
```

Si `devtools` a été supprimé, il faut retélécharger PHP, Composer et PostgreSQL
aux versions ci-dessus, puis relancer `initdb` sur le port 5433.
