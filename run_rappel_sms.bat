@echo off
REM Envoie automatiquement les rappels SMS (RDV dans les 22-24h).
REM Planifier ce script avec le Planificateur de tâches Windows (toutes les heures).

cd /d "%~dp0"
php bin/console app:rappel-rdv-sms --hours=24 --window=2
exit /b 0
