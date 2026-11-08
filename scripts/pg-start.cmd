@echo off
REM Demarre uniquement la base PostgreSQL 17 + PostGIS (Docker, port 5433).
REM Les donnees vivent dans le volume Docker `parema_db-data`.
cd /d "%~dp0.."
docker version >nul 2>&1 || (echo Docker Desktop ne repond pas : demarre-le d'abord. & exit /b 1)
docker compose up -d db
echo PostgreSQL disponible sur le port 5433 (base depanne_moi).
