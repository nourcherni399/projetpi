@echo off
cd /d "%~dp0"
echo ============================================
echo  Serveur AutiCare - http://127.0.0.1:8000
echo ============================================
echo  Utilisez ce script au lieu de "symfony serve"
echo  pour eviter l'erreur "TerminateProcess: Access is denied".
echo  Arret : Ctrl+C
echo ============================================
echo.
php -S 127.0.0.1:8000 -t public
