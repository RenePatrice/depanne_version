@echo off
REM =====================================================================
REM Parema Connect — environnement de developpement complet (Docker).
REM Demarre PostgreSQL + PostGIS, l'app web, la file, le planificateur et
REM Reverb en une seule console. Ctrl+C arrete l'ensemble.
REM
REM   Acces : web http://localhost:8000 · Reverb ws://localhost:8080 · PG :5433
REM   Assets front : construits sur l'hote, hors Docker, depuis backend-laravel\
REM                  avec `npm run build` (ou `npm run dev` pour le HMR Vite).
REM   Reconstruire l'image apres un changement du Dockerfile ou des
REM   dependances :  scripts\dev.cmd --build
REM =====================================================================
cd /d "%~dp0.."
docker version >nul 2>&1 || (echo Docker Desktop ne repond pas : demarre-le d'abord. & exit /b 1)
docker compose up %*
