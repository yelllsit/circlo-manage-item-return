@echo off
cd /d "%~dp0"
echo Circlo: buka http://127.0.0.1:8000 di browser.
echo Tekan Ctrl+C untuk menghentikan server.
php -S 127.0.0.1:8000 -t public public/index.php
pause
