@echo off
title KabuTech Mushroom Monitoring - Laravel API Server
cd /d "%~dp0"
echo.
echo ===================================================
echo   KabuTech IoT Mushroom Monitoring API Server
echo ===================================================
echo.
echo Starting Laravel API server for ESP32 sensors ^& web dashboard...
echo Server will listen on ALL interfaces (0.0.0.0:8000).
echo Dashboard local URL: http://localhost:8000
echo.
echo Press Ctrl+C to stop the server.
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
