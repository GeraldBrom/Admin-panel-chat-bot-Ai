@echo off
REM Просмотр последних webhook логов
echo Последние 50 записей из логов с фильтром "GreenAPI Webhook"
echo ================================================================
echo.

cd /d E:\OSPanel\home\Admin-panel-chat-bot

findstr /C:"GreenAPI Webhook" storage\logs\laravel.log | powershell -Command "$input | Select-Object -Last 50"

echo.
echo ================================================================
echo.
pause

