@echo off
REM Flutter portable, installe dans %USERPROFILE%\devtools\flutter (bloc D).
REM Aucun droit administrateur, aucune variable d'environnement systeme.
REM
REM FLUTTER_ROOT et ANDROID_HOME passent par le nom court 8.3 : l'outillage de
REM Flutter et celui d'Android compilent les greffons natifs en appelant "dart"
REM et "aapt2" sans guillemets, ce qui echoue des que le chemin contient un
REM espace -- et le profil utilisateur de cette machine en contient un.
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
for %%I in ("%USERPROFILE%\devtools\android-sdk") do set ANDROID_HOME=%%~sI
set ANDROID_SDK_ROOT=%ANDROID_HOME%
set PATH=%FLUTTER_ROOT%\bin;%ANDROID_HOME%\platform-tools;%PATH%
cd /d "%~dp0..\mobile-flutter"
flutter %*
