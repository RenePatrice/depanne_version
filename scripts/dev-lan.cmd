@echo off
REM Environnement de developpement EXPOSE AU RESEAU LOCAL (LAN).
REM Identique a dev.cmd, mais `php artisan serve` ecoute sur 0.0.0.0 pour qu'un
REM telephone du meme Wi-Fi atteigne l'API (http://IP_DU_PC:8000). Reverb ecoute
REM deja sur 0.0.0.0:8080 (REVERB_SERVER_HOST).
REM
REM A n'utiliser que sur un reseau de confiance : le back-office et l'API
REM deviennent joignables depuis les autres machines du reseau.
REM
REM Pense a autoriser les ports 8000 et 8080 dans le pare-feu Windows si une
REM connexion depuis le telephone echoue (voir le README mobile).

REM php/composer/node viennent du PATH utilisateur (comme dev.cmd). On ajoute
REM php84 et composer en tete au cas ou ils n'y seraient pas.
set PATH=%USERPROFILE%\devtools\php84;%USERPROFILE%\devtools\composer;%PATH%

call "%~dp0pg-start.cmd"
cd /d "%~dp0..\backend-laravel"

npx concurrently -c "#1B6FF3,#FF7A1A,#16A34A,#8B5CF6,#64748B" ^
  "php artisan serve --host=0.0.0.0 --port=8000" ^
  "php artisan queue:listen --tries=1 --timeout=0" ^
  "php artisan schedule:work" ^
  "php artisan reverb:start" ^
  "npm run dev" ^
  --names=web,queue,scheduler,reverb,vite --kill-others
