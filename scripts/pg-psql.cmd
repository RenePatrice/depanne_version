@echo off
REM Ouvre une session psql sur la base de developpement (conteneur Docker `db`).
REM Arguments supplementaires transmis a psql, ex. :
REM   scripts\pg-psql.cmd -c "\dt"
REM La base doit tourner (scripts\pg-start.cmd ou scripts\dev.cmd).
cd /d "%~dp0.."
docker compose exec -e PGPASSWORD=depanne_local db psql -U depanne -d depanne_moi %*
