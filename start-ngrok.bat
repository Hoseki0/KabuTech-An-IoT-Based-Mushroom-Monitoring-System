@echo off
title KabuTech - ngrok Tunnel (port 8000)
cd /d "%~dp0"
echo.
echo ===================================================
echo   KabuTech ngrok HTTPS Tunnel (port 8000)
echo ===================================================
echo.
echo Make sure start-server.bat (port 8000) and XAMPP MySQL are running.
echo Press Ctrl+C to stop the tunnel.
echo.

where ngrok >nul 2>&1
if %errorlevel%==0 (
    ngrok http 8000
) else if exist "C:\Users\Jem\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe" (
    "C:\Users\Jem\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe" http 8000
) else (
    echo ERROR: ngrok.exe not found in PATH or Downloads.
    echo Please place ngrok.exe in PATH or update start-ngrok.bat.
)

pause
