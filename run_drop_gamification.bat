@echo off
cd /d "%~dp0"
REM Exécute drop_gamification_manual.sql sur la base pidb (utilisateur root).
REM Si vous avez un mot de passe MySQL, remplacez par : mysql -u root -p pidb < drop_gamification_manual.sql
mysql -u root pidb < drop_gamification_manual.sql
if %ERRORLEVEL% equ 0 (echo OK - Tables gamification supprimees.) else (echo Erreur MySQL. Verifiez que mysql est dans le PATH.)
pause
