# Déploiement — Vercel + Supabase

Le site public, le back-office et l'API mobile tournent sur **Vercel** ; la base
PostgreSQL + PostGIS et les fichiers sont chez **Supabase**. L'application
mobile Flutter n'est pas concernée : elle pointe simplement vers l'URL de l'API.

## Ce que ce montage change par rapport au développement

Vercel exécute l'application à la demande, requête par requête. Il n'y a ni
processus permanent ni disque inscriptible. Trois conséquences, toutes prévues
dans le code :

| En développement (Docker) | Sur Vercel |
|---|---|
| `queue:listen` tourne en continu | Supabase appelle `/internes/taches` à intervalle régulier |
| Reverb pousse le temps réel | Pas de WebSocket : carte live et app mobile interrogent le serveur (mode dégradé déjà prévu) |
| Fichiers sur le disque | Fichiers sur Supabase Storage (S3) |

Effets visibles : la fenêtre de 45 s d'un technicien peut se fermer avec un
retard allant jusqu'à l'intervalle du déclencheur ; la carte live affiche le
bandeau « Mode dégradé ».

> Le moteur PHP utilisé (`vercel-php`) est un projet communautaire **archivé**,
> non supporté par Vercel. Il fonctionne, mais ne recevra plus de mises à jour.
> Si un jour il casse, la configuration Docker du dépôt tourne telle quelle sur
> un hébergeur à processus permanents (Railway, Render, VPS).

## 1. Supabase

1. **Extensions** (SQL Editor) :
   ```sql
   create extension if not exists postgis;
   create extension if not exists pg_cron;
   create extension if not exists pg_net;
   ```
2. **Chaînes de connexion** (Project Settings → Database) — en relever deux :
   - *Transaction pooler* (port 6543) : utilisée par l'application ;
   - *Direct connection* ou *Session pooler* (port 5432) : utilisée pour les
     migrations uniquement (ADR-0006 : le pooler en mode transaction ne sait pas
     exécuter certains DDL).
3. **Stockage** (Storage) — créer deux buckets :
   - `pieces` : **privé** (pièces d'identité des techniciens) ;
   - `equipe` : **public** (photos de l'équipe du site vitrine).
   Puis Storage → S3 Connection : activer, relever l'*endpoint*, la *région* et
   créer une paire *Access key / Secret key*.

## 2. Migrations et données de base

Depuis la machine de développement, en visant la connexion **directe** :

```cmd
docker compose exec ^
  -e DB_MIGRATIONS_HOST=<hôte direct> -e DB_MIGRATIONS_PORT=5432 ^
  -e DB_MIGRATIONS_DATABASE=postgres -e DB_MIGRATIONS_USERNAME=<utilisateur> ^
  -e DB_MIGRATIONS_PASSWORD=<mot de passe> -e DB_SSLMODE=require ^
  app php artisan migrate --database=pgsql_migrations --force
```

Puis, avec les variables `DB_*` pointant de la même façon sur Supabase :

```cmd
php artisan db:seed --class=Database\Seeders\RolesPermissionsSeeder --force
php artisan admin:create
```

`admin:create` crée le premier compte du back-office (mot de passe saisi
masqué). **Ne pas lancer `db:seed` sans `--class`** : il chargerait les données
de démonstration (faux clients, faux tickets, comptes `@depanne-moi.gn`). Le
catalogue, les zones et les réglages se chargent un par un si on veut partir
des valeurs du pilote :

```cmd
php artisan db:seed --class=Database\Seeders\Demo\AppSettingsSeeder --force
php artisan db:seed --class=Database\Seeders\Demo\CatalogSeeder --force
php artisan db:seed --class=Database\Seeders\Demo\ZoneSeeder --force
```

## 3. Vercel

1. Importer le dépôt GitHub. **Root Directory : `backend-laravel`**. Le reste
   (commande de build, moteur PHP 8.4, routage) est dans `vercel.json`.
2. Choisir la **région de la fonction** la plus proche de celle du projet
   Supabase (Settings → Functions) : chaque requête fait plusieurs
   allers-retours avec la base.
3. Renseigner les variables d'environnement secrètes (Settings → Environment
   Variables). Les valeurs non secrètes sont déjà dans `vercel.json`.

| Variable | Valeur |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` — **à générer une fois, puis ne plus jamais changer** (elle chiffre les sessions et dérive les identifiants opaques des URL) |
| `APP_URL` | l'URL publique, ex. `https://parema.vercel.app` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | le *Transaction pooler* (port `6543`) |
| `AWS_ENDPOINT` | endpoint S3 de Supabase |
| `AWS_DEFAULT_REGION` | région indiquée par Supabase |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | la paire créée à l'étape 1.3 |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` |
| `AWS_BUCKET` | `pieces` |
| `AWS_PUBLIC_BUCKET` | `equipe` |
| `AWS_PUBLIC_URL` | `https://<ref>.supabase.co/storage/v1/object/public/equipe` |
| `TACHES_JETON` | une longue chaîne aléatoire (ex. `openssl rand -hex 32`) |
| `CORS_ALLOWED_ORIGINS` | l'URL publique |

Ne **pas** définir `VITE_REVERB_APP_KEY` : son absence est ce qui fait basculer
le back-office en interrogation périodique.

## 4. Déclencheur de la file d'attente

Dans le SQL Editor de Supabase, une fois l'application en ligne :

```sql
select cron.schedule(
  'parema-file-attente',
  '30 seconds',
  $$
  select net.http_post(
    url := 'https://<votre-domaine>/internes/taches',
    headers := jsonb_build_object('X-Taches-Jeton', '<TACHES_JETON>'),
    timeout_milliseconds := 55000
  );
  $$
);
```

L'adresse répond `{"etat":"ok"}` avec le bon jeton, et 404 sinon — y compris
quand `TACHES_JETON` n'est pas défini. Pour arrêter :
`select cron.unschedule('parema-file-attente');`

## 5. Vérifier

- `https://<domaine>/health` répond 200 et annonce la base joignable ;
- le site s'affiche, `/connexion` ouvre le back-office avec le compte créé ;
- `/systeme` (connecté) liste les pilotes réellement branchés ;
- déposer une photo d'équipe dans « Site vitrine » : elle doit s'afficher sur
  l'accueil (preuve que le stockage public fonctionne).

## Application mobile

Compiler avec l'URL de production :

```cmd
scripts\flutter.cmd build apk --release --dart-define=API_BASE_URL=https://<domaine>/api/v1
```

Sans `REVERB_URL`, l'application interroge le serveur au lieu d'écouter : c'est
le comportement attendu sur ce montage.
