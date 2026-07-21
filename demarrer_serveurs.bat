@echo off
title Facture App - Démarrage des Serveurs
color 0A
cls

echo ===============================================
echo   FACTURE APP - DEMARRAGE DES SERVEURS
echo ===============================================
echo.

echo [1/2] Démarrage du serveur Backend Laravel (Port 8001)...
start "Laravel Backend" cmd /k "cd /d %~dp0backend && php artisan serve --port=8001"
echo [OK] Backend démarré dans une fenêtre séparée
echo.

echo [2/2] Vérification des services...
timeout /t 3 /nobreak > nul

echo.
echo ===============================================
echo   SERVEURS ACTIFS
echo ===============================================
echo.
echo   Backend Laravel:  http://127.0.0.1:8001
echo   Frontend Vue:     http://127.0.0.1:5173
echo.
echo   Appuyez sur une touche pour ouvrir le navigateur...
pause > nul

echo.
echo [INFO] Ouverture du navigateur...
start http://127.0.0.1:5173

echo.
echo ===============================================
echo   Serveurs actifs! Ne fermez PAS la fenêtre "Laravel Backend"
echo   Appuyez sur une touche pour quitter...
pause > nul
