@echo off
REM Arrete proprement le cluster PostgreSQL local.
set PGROOT=%USERPROFILE%\devtools\pgsql
set PGDATA=%USERPROFILE%\devtools\pgdata
"%PGROOT%\bin\pg_ctl.exe" -D "%PGDATA%" -m fast stop
