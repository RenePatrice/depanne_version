@echo off
REM Ouvre une session psql sur la base de developpement.
set PGROOT=%USERPROFILE%\devtools\pgsql
set PGPASSWORD=depanne_local
"%PGROOT%\bin\psql.exe" -h 127.0.0.1 -p 5433 -U depanne -d depanne_moi %*
