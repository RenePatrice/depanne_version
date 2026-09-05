@echo off
REM Demarre le cluster PostgreSQL 17 + PostGIS local de Depanne-Moi (port 5433).
set PGROOT=%USERPROFILE%\devtools\pgsql
set PGDATA=%USERPROFILE%\devtools\pgdata
"%PGROOT%\bin\pg_ctl.exe" -D "%PGDATA%" -l "%PGDATA%\server.log" status >nul 2>&1
if %ERRORLEVEL%==0 (
    echo PostgreSQL tourne deja sur le port 5433.
) else (
    start "" /B "%PGROOT%\bin\pg_ctl.exe" -D "%PGDATA%" -l "%PGDATA%\server.log" start
    echo PostgreSQL demarre sur le port 5433.
)
