# 🔍 Инструкция по тестированию Webhook

## ✅ Ваш webhook УЖЕ РАБОТАЕТ!

Согласно логам, сообщения успешно принимаются:
```
[2025-11-01 08:29:41] local.INFO: Processing incoming message from chatId: 79034340422@c.us {"message":"Да"}
[2025-11-01 11:51:51] local.INFO: Processing incoming message from chatId: 79034340422@c.us {"message":"Привет"}
```

---

## 📍 Endpoints вашего сервера

### Основной webhook (для Green API):
```
POST https://bot.capitalmars.com/green-api/webhook
```

### Альтернативный endpoint через API:
```
POST https://bot.capitalmars.com/api/greenapi/webhook
```

### Тестовый endpoint (новый):
```
GET/POST https://bot.capitalmars.com/green-api/webhook/test
GET/POST https://bot.capitalmars.com/api/greenapi/webhook/test
```

---

## 🧪 Способы проверки

### 1️⃣ Через браузер (самый простой)
Откройте в браузере:
```
https://bot.capitalmars.com/green-api/webhook/test
```

Вы должны увидеть JSON ответ:
```json
{
  "status": "success",
  "message": "Webhook endpoint работает!",
  "received_at": "2025-11-01T12:00:00Z",
  "your_ip": "XXX.XXX.XXX.XXX"
}
```

### 2️⃣ Используя готовые скрипты

#### **test-webhook.bat**
Запустите этот файл. Он выполнит 3 теста:
- GET запрос на тестовый endpoint
- POST запрос с тестовыми данными
- Реальный webhook запрос

#### **watch-logs.bat**
Мониторинг логов в реальном времени. Запустите его перед отправкой тестовых сообщений.

#### **check-webhook-logs.bat**
Просмотр последних 50 записей webhook в логах.

### 3️⃣ Через curl (в CMD)

#### Простой тест:
```cmd
curl https://bot.capitalmars.com/green-api/webhook/test
```

#### Отправка тестового webhook:
```cmd
curl -X POST https://bot.capitalmars.com/green-api/webhook ^
  -H "Content-Type: application/json" ^
  -d "{\"typeWebhook\":\"incomingMessageReceived\",\"message\":{\"chatId\":\"test@c.us\",\"textMessage\":\"Test\"}}"
```

### 4️⃣ Через Postman/Insomnia
1. Создайте POST запрос на `https://bot.capitalmars.com/green-api/webhook/test`
2. Headers: `Content-Type: application/json`
3. Body (JSON):
```json
{
  "test": true,
  "message": "Hello from Postman"
}
```

### 5️⃣ Отправив реальное сообщение в WhatsApp
Просто отправьте сообщение боту в WhatsApp, затем проверьте логи.

---

## 📊 Проверка логов

### Метод 1: Последние записи
```cmd
cd E:\OSPanel\home\Admin-panel-chat-bot
findstr /C:"GreenAPI Webhook" storage\logs\laravel.log | powershell -Command "$input | Select-Object -Last 20"
```

### Метод 2: В реальном времени
```cmd
cd E:\OSPanel\home\Admin-panel-chat-bot
powershell -Command "Get-Content storage\logs\laravel.log -Wait -Tail 30"
```

### Метод 3: Через Laravel
```cmd
cd E:\OSPanel\home\Admin-panel-chat-bot
php artisan tail
```

---

## 🔧 Настройка Green API

Убедитесь, что в настройках Green API указан правильный URL webhook:

### Вариант 1 (основной):
```
https://bot.capitalmars.com/green-api/webhook
```

### Вариант 2 (через API):
```
https://bot.capitalmars.com/api/greenapi/webhook
```

**⚠️ КРИТИЧЕСКИ ВАЖНО:** 
- URL должен быть указан **БЕЗ** trailing slash (без `/` в конце)
- ✅ Правильно: `https://bot.capitalmars.com/green-api/webhook`
- ❌ Неправильно: `https://bot.capitalmars.com/green-api/webhook/`
- Webhook endpoints исключены из CSRF проверки в `bootstrap/app.php`

---

## 📝 Что искать в логах

### ✅ Успешное получение webhook:
```
[GreenAPI Webhook] Получен webhook
[GreenAPI Webhook TEST] Получен тестовый запрос
```

### ✅ Обработка сообщения:
```
Processing incoming message from chatId: XXXXX@c.us
```

### ✅ Отправка ответа:
```
Message sent to chatId: XXXXX@c.us
```

### ⚠️ Возможные предупреждения:
```
No active session for chatId: XXXXX@c.us
```
Это нормально, если бот не был запущен для этого чата.

---

## 🐛 Troubleshooting

### Проблема: Ошибка 419 Page Expired ⚠️
**Симптомы:** curl возвращает HTML с "419 Page Expired"

**Причина:** Laravel блокирует webhook из-за CSRF защиты

**Решение:**
1. Убедитесь, что в `bootstrap/app.php` добавлено исключение для webhook:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        '/green-api/webhook',
        '/green-api/webhook/*',
        '/api/greenapi/webhook',
        '/api/greenapi/webhook/*',
    ]);
})
```

2. Очистите кеш на сервере:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

3. Используйте URL без trailing slash: `/webhook` а не `/webhook/`

### Проблема: Webhook не приходит
1. Проверьте настройки Green API
2. Проверьте firewall/настройки сервера
3. Убедитесь, что SSL сертификат валиден
4. Проверьте логи веб-сервера (nginx/apache)
5. **Убедитесь, что URL без `/` в конце**

### Проблема: Webhook приходит, но не обрабатывается
1. Проверьте очереди Laravel: `php artisan queue:work`
2. Проверьте конфигурацию очередей в `.env`
3. Посмотрите логи на наличие ошибок

### Проблема: Пустой payload
1. Проверьте Content-Type заголовок (должен быть application/json)
2. Проверьте формат JSON данных

---

## 📞 Диагностический endpoint

Используйте встроенный endpoint для получения последних сообщений от Green API:

```
GET https://bot.capitalmars.com/api/greenapi/last?minutes=5
```

Это вернет последние сообщения за указанное количество минут.

---

## ✨ Улучшения в этой версии

1. ✅ Детальное логирование входящих webhook (IP, URL, headers)
2. ✅ Логирование полного payload для диагностики
3. ✅ Тестовый endpoint для быстрой проверки
4. ✅ Готовые скрипты для тестирования
5. ✅ Временные метки в ответах

---

## 📚 Дополнительная информация

- **Роуты webhook:** `routes/web.php`, `routes/api.php`
- **Контроллер:** `app/Http/Controllers/GreenApiWebhookController.php`
- **Job обработки:** `app/Jobs/ProcessGreenApiWebhook.php`
- **Сервис:** `app/Services/GreenApiService.php`
- **Логи:** `storage/logs/laravel.log`

---

**Последнее обновление:** 1 ноября 2025

