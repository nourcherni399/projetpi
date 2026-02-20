@echo off
cd /d "%~dp0"
echo Demarrage avec symfony serve sur http://127.0.0.1:8000
echo Pour eviter "TerminateProcess: Access is denied", ne lancez pas le terminal en Administrateur.
echo Arret : Ctrl+C
echo.
symfony serve
