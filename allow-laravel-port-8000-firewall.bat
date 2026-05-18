@echo off
:: Run as Administrator — allow inbound TCP 8000 from LAN (php artisan serve)
:: Applies to ALL network profiles (Private AND Public), so Wi-Fi marked "Public" still works.
echo Adding Windows Firewall rule: Laravel8000 (TCP 8000)...
netsh advfirewall firewall delete rule name="Laravel8000" >nul 2>&1
netsh advfirewall firewall add rule name="Laravel8000" dir=in action=allow protocol=TCP localport=8000 enable=yes
if errorlevel 1 (
  echo Failed. Right-click this file -^> Run as administrator.
  pause
  exit /b 1
)
echo Done. Start: php artisan serve --host=0.0.0.0 --port=8000
pause
