@echo off
REM Мониторинг логов в реальном времени
echo Мониторинг логов Laravel (Ctrl+C для выхода)
echo ================================================================
echo.

cd /d E:\OSPanel\home\Admin-panel-chat-bot

powershell -Command "Get-Content storage\logs\laravel.log -Wait -Tail 30"

