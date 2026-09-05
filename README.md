# Dépanne-Moi

Plateforme de mise en relation entre des clients ayant une panne domestique
(plomberie / électricité) et des techniciens vérifiés, à Conakry (Guinée).
Pilote sur la commune de **Ratoma**.

Le client choisit sa panne dans un catalogue à prix fixes, voit le prix total
estimé (prestation + déplacement), publie sa demande ; le technicien vérifié le
plus pertinent est sollicité, se déplace, intervient, et le paiement Mobile
Money in-app est réparti automatiquement **90 % technicien / 10 % plateforme**.

## Structure du dépôt

| Dossier | Contenu |
|---|---|
| `backend-laravel/` | Application Laravel 13 — back-office web **et** API mobile |
| `mobile-flutter/` | Application Flutter (Client / Technicien) — bloc D |
| `docs/` | Architecture, modèle de données, ADR, guide d'environnement |
| `scripts/` | Scripts de développement local (Windows) |

## Démarrage rapide

Prérequis installés en mode portable dans `%USERPROFILE%\devtools`
(voir [docs/environnement-local.md](docs/environnement-local.md)) :
PHP 8.4, Composer, PostgreSQL 17 + PostGIS, Node 22.

```cmd
scripts\setup.cmd     :: dépendances, .env, migrations, build des assets
scripts\dev.cmd       :: PostgreSQL + serveur web + queue + scheduler + Reverb + Vite
```

L'application répond alors sur <http://localhost:8000>, la sonde de santé sur
<http://localhost:8000/health>.

## Documentation

- [CLAUDE.md](CLAUDE.md) — conventions, commandes, décisions d'architecture
- [docs/environnement-local.md](docs/environnement-local.md) — installation détaillée
- [docs/architecture.md](docs/architecture.md) — schéma de la stack
- [docs/adr/](docs/adr/) — décisions d'architecture, une par fichier
