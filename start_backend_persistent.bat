@echo off
title Laravel Backend Server - Port 8001
color 0A
echo ===============================================
echo   SERVEUR LARAVEL - FACTURE APP
echo   Port: 8001 | Frontend: http://127.0.0.1:5173
echo   Press Ctrl+C pour arrêter
echo ===============================================
echo.
echo [INFO] Démarrage du serveur...
echo.

cd /d "%~dp0backend"

:LOOP
php artisan serve --port=8001
echo.
echo [WARN] Le serveur s'est arrete. Redemarrage dans 3 secondes...
timeout /t 3 /nobreak > nul
echo [INFO] Redemarrage...
goto LOOP
