@echo off
REM Flutter portable, installe dans %USERPROFILE%\devtools\flutter (bloc D).
REM Aucun droit administrateur, aucune variable d'environnement systeme.
REM
REM FLUTTER_ROOT passe par le nom court 8.3 : l'outillage de Flutter compile
REM les greffons natifs en appelant "dart" sans guillemets, ce qui echoue des
REM que le chemin contient un espace -- et le profil utilisateur de cette
REM machine en contient un.
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
set PATH=%FLUTTER_ROOT%\bin;%PATH%
cd /d "%~dp0..\mobile-flutter"
flutter %*
