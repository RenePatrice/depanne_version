@echo off
REM Verification complete de l'application mobile : format, analyse, tests.
REM Equivalent de "composer format && composer analyse && composer test"
REM cote back-end.
REM
REM "call" devant chaque outil : dart et flutter sont eux-memes des .bat, et
REM un .bat appele sans "call" ne rend jamais la main -- le script s'arretait
REM apres le formatage, en annoncant une reussite.
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
for %%I in ("%USERPROFILE%\devtools\android-sdk") do set ANDROID_HOME=%%~sI
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set PATH=%FLUTTER_ROOT%\bin;%ANDROID_HOME%\platform-tools;%PATH%
cd /d "%~dp0..\mobile-flutter"
echo === Format ===
call dart format --set-exit-if-changed lib test
if errorlevel 1 exit /b 1
echo === Analyse ===
call flutter analyze
if errorlevel 1 exit /b 1
echo === Tests ===
call flutter test
