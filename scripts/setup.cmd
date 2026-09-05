@echo off
REM Installation initiale du backend (dependances, .env, migrations, assets).
cd /d "%~dp0..\backend-laravel"
call "%~dp0pg-start.cmd"
composer setup
