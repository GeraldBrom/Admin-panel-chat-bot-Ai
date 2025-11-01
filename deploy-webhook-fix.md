# 🔧 Деплой исправления Webhook

## ❌ Проблема
Ошибка **419 Page Expired** - Laravel блокирует webhook из-за CSRF защиты.

## ✅ Решение
Webhook endpoints исключены из CSRF проверки в `bootstrap/app.php`.

---

## 📦 Деплой на сервер

### Вариант 1: Через Git (рекомендуется)

```bash
# На локальной машине
git add bootstrap/app.php
git commit -m "Fix: Exclude webhook endpoints from CSRF verification"
git push origin main

# На сервере
cd /home/u2817882/bot.capitalmars.com
git pull origin main
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Вариант 2: Прямое редактирование на сервере

```bash
# На сервере
cd /home/u2817882/bot.capitalmars.com
nano bootstrap/app.php
```

Найдите строку:
```php
->withMiddleware(function (Middleware $middleware): void {
    //
})
```

Замените на:
```php
->withMiddleware(function (Middleware $middleware): void {
    // Исключаем webhook endpoints из CSRF проверки
    $middleware->validateCsrfTokens(except: [
        '/green-api/webhook',
        '/green-api/webhook/*',
        '/api/greenapi/webhook',
        '/api/greenapi/webhook/*',
    ]);
})
```

Сохраните (Ctrl+O, Enter, Ctrl+X).

Затем очистите кеш:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## ⚠️ ВАЖНО: URL без trailing slash

В настройках Green API используйте URL **БЕЗ** слеша `/` в конце:

### ✅ Правильно:
```
https://bot.capitalmars.com/green-api/webhook
```

### ❌ Неправильно:
```
https://bot.capitalmars.com/green-api/webhook/
```

---

## 🧪 Тест после деплоя

### 1. С сервера (SSH):
```bash
curl -X POST https://bot.capitalmars.com/green-api/webhook \
  -H "Content-Type: application/json" \
  -d '{"typeWebhook":"incomingMessageReceived","senderData":{"chatId":"test@c.us"},"messageData":{"textMessageData":{"textMessage":"Test"}}}'
```

Должны увидеть:
```json
{"status":"ok","queued":true,"received_at":"2025-11-01T..."}
```

### 2. Проверьте логи:
```bash
tail -20 storage/logs/laravel.log
```

Должны увидеть:
```
[GreenAPI Webhook] Получен webhook
```

---

## 📝 Checklist

- [ ] Задеплоить изменения на сервер
- [ ] Очистить кеш (`php artisan cache:clear`)
- [ ] Проверить URL в Green API (без `/` в конце)
- [ ] Протестировать через curl
- [ ] Проверить логи
- [ ] Отправить реальное сообщение боту

---

## 🆘 Если не работает

1. Проверьте права на файлы: `chmod 644 bootstrap/app.php`
2. Проверьте владельца: `chown u2817882:u2817882 bootstrap/app.php`
3. Перезапустите PHP-FPM (если используется)
4. Проверьте веб-сервер логи: `/var/log/nginx/error.log` или `/var/log/apache2/error.log`

