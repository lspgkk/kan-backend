@echo off
title Gatensi Queue Worker (generate-dokumen)
cd /d "%~dp0"

echo [%date% %time%] Worker started
echo Queue: generate-dokumen
echo Max jobs before restart: 10
echo Memory limit: 2G
echo.
echo Press Ctrl+C to stop
echo ========================================

:loop
echo [%date% %time%] Starting worker...
php artisan queue:work redis --queue=generate-dokumen --sleep=3 --tries=3 --max-jobs=10 --max-time=3600
echo [%date% %time%] Worker exited (restarting...)
timeout /t 2 /nobreak >nul
goto loop
