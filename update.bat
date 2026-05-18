@echo off
echo ===================================================
echo Auto-Updating GitHub Repository...
echo ===================================================
echo.

:: Stage all changes
git add .

:: Commit with the current date and time as the message
git commit -m "Auto-update: %date% %time%"

:: Push to GitHub
echo.
echo Pushing changes to GitHub...
git push

echo.
echo ===================================================
echo Update Complete! You can close this window now.
echo ===================================================
pause
