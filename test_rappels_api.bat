@echo off
REM Test de l'API rappels evenements
REM 1. Lancer le serveur : php -S localhost:8000 -t public  (ou symfony serve)
REM 2. Mettre le token ci-dessous (REMINDER_SECRET ou APP_SECRET du .env)

set BASE_URL=http://localhost:8000
REM Mettre ici REMINDER_SECRET (ou APP_SECRET du .env)
set TOKEN=

if "%TOKEN%"=="" (
  echo ERREUR : Editez ce fichier et definissez TOKEN ^= votre REMINDER_SECRET ou APP_SECRET
  pause
  exit /b 1
)

echo Appel GET %BASE_URL%/api/cron/event-reminders
echo.
curl -s "%BASE_URL%/api/cron/event-reminders?token=%TOKEN%"
echo.
pause
