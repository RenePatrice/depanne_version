@echo off
REM Lance l'environnement de developpement complet.
REM Remplace le docker-compose : PostgreSQL en local, puis les cinq processus
REM Laravel/Vite dans une seule console (Ctrl+C arrete l'ensemble).
call "%~dp0pg-start.cmd"
cd /d "%~dp0..\backend-laravel"
composer dev
