@echo off
REM APK de release pour EMULATEUR (BlueStacks, emulateur Android Studio,
REM Chromebook) : processeur x86_64.
REM
REM Meme configuration que l'APK des telephones (production, signature,
REM obfuscation), seul le processeur change. Elle n'est pas publiee sur le
REM site : elle sert aux tests sur ordinateur.
REM
REM Usage :
REM   scripts\build-emulateur.cmd
REM   scripts\build-emulateur.cmd <url-api>

setlocal
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
for %%I in ("%USERPROFILE%\devtools\android-sdk") do set ANDROID_HOME=%%~sI
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set PATH=%FLUTTER_ROOT%\bin;%ANDROID_HOME%\platform-tools;%PATH%
cd /d "%~dp0..\mobile-flutter"

set API_BASE_URL=%~1
if "%API_BASE_URL%"=="" set API_BASE_URL=https://parema-connect.vercel.app/api/v1

echo API : %API_BASE_URL%
echo Processeur : x86_64 (emulateurs)

call flutter build apk --release ^
  --target-platform android-x64 ^
  --obfuscate ^
  --split-debug-info=build/symbols ^
  --dart-define=API_BASE_URL=%API_BASE_URL%
if errorlevel 1 exit /b 1

if not exist build\livrables mkdir build\livrables
copy /y build\app\outputs\flutter-apk\app-release.apk build\livrables\parema-connect-emulateur.apk >nul
echo Livrable : mobile-flutter\build\livrables\parema-connect-emulateur.apk

endlocal
