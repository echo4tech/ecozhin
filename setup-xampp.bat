@echo off
rem EcoZin one-click setup for XAMPP (Windows). Double-click this file from C:\xampp\htdocs\ecozhin
setlocal
cd /d "%~dp0"
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
  echo PHP not found at %PHP%. Edit the PHP= line at the top of this file to your XAMPP path.
  pause & exit /b 1
)
echo.
echo === 1/4  .env ===
if not exist ".env" ( copy ".env.example" ".env" >nul & echo created .env ) else ( echo .env already exists )

echo.
echo === 2/4  Database (make sure MySQL is started in the XAMPP Control Panel) ===
"%PHP%" database\migrate.php --seed
if errorlevel 1 ( echo. & echo Database setup failed. Start MySQL in XAMPP and run this file again. & pause & exit /b 1 )

echo.
echo === 3/4  Admin account ===
set /p APHONE=Admin phone (e.g. +9647500000000): 
set /p APASS=Admin password (min 8 characters): 
"%PHP%" database\create-admin.php "Admin" "%APHONE%" "%APASS%"

echo.
echo === 4/4  Check ===
"%PHP%" database\check.php
echo.
echo Opening http://localhost/ecozhin/ ...
start "" "http://localhost/ecozhin/"
pause
