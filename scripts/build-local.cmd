@echo off
REM =====================================================================
REM APK de TEST pointee sur le serveur LOCAL (Docker sur ce PC).
REM
REM - Build PROFILE : code compile comme en release (rapide, ~3 fois plus
REM   leger que le debug), et son manifeste autorise le HTTP, absent du
REM   serveur local (la release l'interdit). Elle contient aussi la version
REM   x86_64 : le meme fichier s'installe sur un telephone ET sur BlueStacks.
REM - Aucune passerelle : l'inscription ne demande pas de code, et pour
REM   « mot de passe oublie » le serveur local renvoie le code a l'appli,
REM   qui le pre-remplit (SMS_EXPOSE_TEST_CODE, reseau prive uniquement).
REM - Temps reel actif : Reverb tourne dans Docker sur le port 8080.
REM
REM Usage :
REM   scripts\build-local.cmd              adresse Wi-Fi de ce PC, detectee
REM   scripts\build-local.cmd 10.0.2.2     emulateur Android Studio
REM   scripts\build-local.cmd 192.168.1.20 adresse imposee
REM
REM Le telephone doit etre sur le MEME reseau Wi-Fi que ce PC, et le serveur
REM demarre : scripts\dev.cmd (ou docker compose up -d).
REM =====================================================================

setlocal
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
for %%I in ("%USERPROFILE%\devtools\android-sdk") do set ANDROID_HOME=%%~sI
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set PATH=%FLUTTER_ROOT%\bin;%ANDROID_HOME%\platform-tools;%PATH%

set HOTE=%~1
if not "%HOTE%"=="" goto hote_ok
REM Carte reseau connectee avec une vraie passerelle : ecarte les VPN (dont la
REM passerelle vaut 0.0.0.0) et les cartes virtuelles sans passerelle.
for /f "usebackq delims=" %%A in (`powershell -NoProfile -Command "(Get-NetIPConfiguration | Where-Object { $_.NetAdapter.Status -eq 'Up' -and $_.IPv4DefaultGateway -and $_.IPv4DefaultGateway.NextHop -ne '0.0.0.0' } | Select-Object -First 1).IPv4Address.IPAddress"`) do set HOTE=%%A
if "%HOTE%"=="" (
    echo Adresse Wi-Fi introuvable. Indiquez-la : scripts\build-local.cmd 192.168.x.x
    exit /b 1
)
:hote_ok

REM Cle Reverb du serveur local (publique par nature, jamais un secret).
set CLE_REVERB=
for /f "usebackq tokens=1,* delims==" %%A in ("%~dp0..\backend-laravel\.env") do if "%%A"=="REVERB_APP_KEY" set CLE_REVERB=%%B

echo Serveur vise : http://%HOTE%:8000/api/v1
if "%CLE_REVERB%"=="" (echo Temps reel : absent, l'application interrogera) else (echo Temps reel : ws://%HOTE%:8080)

cd /d "%~dp0..\mobile-flutter"
set TEMPS_REEL=
if not "%CLE_REVERB%"=="" set TEMPS_REEL=--dart-define=REVERB_URL=ws://%HOTE%:8080 --dart-define=REVERB_APP_KEY=%CLE_REVERB%

call flutter build apk --profile ^
  --dart-define=API_BASE_URL=http://%HOTE%:8000/api/v1 ^
  %TEMPS_REEL%
if errorlevel 1 exit /b 1

if not exist build\livrables mkdir build\livrables
copy /y build\app\outputs\flutter-apk\app-profile.apk build\livrables\parema-connect-local.apk >nul
echo.
echo Livrable : mobile-flutter\build\livrables\parema-connect-local.apk
echo Verifiez depuis le navigateur du telephone : http://%HOTE%:8000/up

endlocal
