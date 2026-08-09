@echo off
title Integra RMA — dev server (8090)

REM --- Start PostgreSQL if not running ---
REM The app runs on Postgres (same as production). MySQL is only a fallback:
REM to use it, restore files\.env.local.mysql-backup and start mysqld instead.
tasklist /FI "IMAGENAME eq postgres.exe" 2>NUL | find /I "postgres.exe" >NUL
if %ERRORLEVEL% NEQ 0 (
    echo Starting PostgreSQL...
    "%USERPROFILE%\pgsql\bin\pg_ctl.exe" -D "%USERPROFILE%\pgsql_data" -l "%USERPROFILE%\pgsql_data\server.log" -o "-p 5432" start
    timeout /t 3 /nobreak >nul
    echo  [OK] PostgreSQL started
) else (
    echo  [OK] PostgreSQL already running
)
echo.

REM --- Launch PHP built-in server for this project on port 8090 ---
cd /d "%~dp0"
echo  Integra RMA dev server
echo  Local:  http://localhost:8090
echo  LAN:    http://192.168.1.200:8090
echo  Press Ctrl+C to stop.
echo.
php -S 0.0.0.0:8090 -t "%~dp0files" "%~dp0router.php"
