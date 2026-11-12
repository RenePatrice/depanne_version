@echo off
REM Build APK de release durci (#11, D5).
REM
REM - R8 (minify + shrink + proguard) est active dans build.gradle.kts.
REM - --obfuscate obscurcit le code Dart ; --split-debug-info sort les symboles
REM   hors du binaire (a garder pour desobscurcir une stack trace plus tard).
REM - Les cles publiques par nature (Reverb, URL de l'API) sont injectees ici,
REM   jamais ecrites dans le code. Aucun secret ne doit figurer sur cette ligne.
REM - La signature vient de android/key.properties (non versionne) ; sans lui,
REM   le build retombe sur la cle de debug et l'APK n'est pas publiable.
REM
REM Usage :
REM   scripts\build-release.cmd https://parema-connect.vercel.app/api/v1
REM   scripts\build-release.cmd <url-api> <url-reverb> <cle-reverb>
REM
REM Sans URL Reverb, le temps reel est absent et l'application interroge le
REM serveur : c'est le comportement voulu sur un hebergeur sans WebSocket
REM (Vercel). Ne JAMAIS passer une adresse Reverb fictive : l'application
REM tenterait de s'y connecter sans fin.

setlocal
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
for %%I in ("%USERPROFILE%\devtools\android-sdk") do set ANDROID_HOME=%%~sI
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set PATH=%FLUTTER_ROOT%\bin;%ANDROID_HOME%\platform-tools;%PATH%
cd /d "%~dp0..\mobile-flutter"

set API_BASE_URL=%~1
if "%API_BASE_URL%"=="" set API_BASE_URL=https://parema-connect.vercel.app/api/v1

set TEMPS_REEL=
if not "%~2"=="" set TEMPS_REEL=--dart-define=REVERB_URL=%~2 --dart-define=REVERB_APP_KEY=%~3

echo API : %API_BASE_URL%
if "%TEMPS_REEL%"=="" (echo Temps reel : aucun, interrogation periodique) else (echo Temps reel : %~2)

call flutter build apk --release ^
  --obfuscate ^
  --split-debug-info=build/symbols ^
  --dart-define=API_BASE_URL=%API_BASE_URL% ^
  %TEMPS_REEL%

endlocal
