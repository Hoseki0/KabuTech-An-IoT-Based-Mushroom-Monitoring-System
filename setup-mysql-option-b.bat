@echo off
title KABUTECH — MySQL Option B (migrate)
cd /d "%~dp0"

echo.
echo 1) Open XAMPP Control Panel and START **MySQL**.
echo 2) Then press any key here to create DB `kabutech_iot` and run migrations.
echo.
pause >nul

set MYSQL=%~dp0..\..\mysql\bin\mysql.exe
if not exist "%MYSQL%" set MYSQL=C:\xampp\mysql\bin\mysql.exe
if not exist "%MYSQL%" (
  echo Could not find mysql.exe. Edit this script with your XAMPP MySQL path.
  pause
  exit /b 1
)

echo Creating database kabutech_iot (if needed)...
"%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS kabutech_iot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
  echo MySQL failed. Is the service started? Try password: mysql -u root -p
  pause
  exit /b 1
)

echo.
echo Running Laravel migrations...
php artisan config:clear
php artisan migrate --force
if errorlevel 1 (
  echo Migrate failed. Check .env DB_* and that kabutech_iot exists.
  pause
  exit /b 1
)

echo.
echo Done. App uses MySQL database kabutech_iot.
pause
