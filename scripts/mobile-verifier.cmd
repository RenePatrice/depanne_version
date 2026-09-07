@echo off
REM Verification complete de l'application mobile : format, analyse, tests.
REM Equivalent de "composer format && composer analyse && composer test"
REM cote back-end.
for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI
set PATH=%FLUTTER_ROOT%\bin;%PATH%
cd /d "%~dp0..\mobile-flutter"
echo === Format ===
dart format --set-exit-if-changed lib test
if errorlevel 1 exit /b 1
echo === Analyse ===
flutter analyze
if errorlevel 1 exit /b 1
echo === Tests ===
flutter test
