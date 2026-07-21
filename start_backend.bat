@echo off
echo ========================================
echo  Démarrage du serveur Laravel Backend
echo  Port: 8001
echo  Press Ctrl+C pour arrêter
echo ========================================
echo.

cd /d "%~dp0backend"
php artisan serve --port=8001

pause
