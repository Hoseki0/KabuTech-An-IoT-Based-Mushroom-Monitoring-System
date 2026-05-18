@echo off
title Mushroom Monitoring - Laravel API Server
cd /d "%~dp0"
echo.
echo Starting Laravel API for ESP32 sensor data...
echo Server will listen on ALL interfaces (so ESP32 can reach it).
echo Open in browser: http://192.168.100.104:8000
echo.
echo Press Ctrl+C to stop.
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
