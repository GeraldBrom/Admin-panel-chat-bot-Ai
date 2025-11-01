@echo off
REM Тестирование webhook на вашем сервере
echo ================================================================
echo Тестирование Green API Webhook
echo ================================================================
echo.

echo 1. Тест endpoint (GET запрос):
echo ----------------------------------------------------------------
curl -X GET https://bot.capitalmars.com/green-api/webhook/test
echo.
echo.

echo 2. Тест endpoint (POST с данными):
echo ----------------------------------------------------------------
curl -X POST https://bot.capitalmars.com/green-api/webhook/test ^
  -H "Content-Type: application/json" ^
  -d "{\"test\":\"true\",\"message\":\"Hello from test script\"}"
echo.
echo.

echo 3. Реальный webhook запрос:
echo ----------------------------------------------------------------
curl -X POST https://bot.capitalmars.com/green-api/webhook ^
  -H "Content-Type: application/json" ^
  -d "{\"typeWebhook\":\"incomingMessageReceived\",\"message\":{\"chatId\":\"test@c.us\",\"textMessage\":\"Test message from script\",\"idMessage\":\"test123\"}}"

echo.
echo.
echo ================================================================
echo Проверьте логи: storage\logs\laravel.log
echo Или выполните: watch-logs.bat
echo ================================================================
pause

