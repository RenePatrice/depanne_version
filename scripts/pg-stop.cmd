@echo off
REM Arrete la base PostgreSQL. Les donnees restent dans le volume Docker
REM (seul `docker compose down -v` les supprimerait).
cd /d "%~dp0.."
docker compose stop db
