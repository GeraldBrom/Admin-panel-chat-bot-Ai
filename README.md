# Админ-панель для управления чат-ботами

Админ-панель на Laravel 12 + Vue 3 + TypeScript + SCSS для управления чат-ботами.

🌐 **Production URL**: [https://bot.capitalmars.com](https://bot.capitalmars.com)

## Технологический стек

- **Backend**: Laravel 12 (PHP 8.3)
- **Frontend**: Vue 3 с Composition API
- **TypeScript**: Полная поддержка типизации
- **SCSS**: Препроцессор стилей
- **Сборка**: Vite
- **База данных**: SQLite (по умолчанию)

## Установка и запуск

### Требования

- PHP 8.3+
- Composer
- Node.js 18+ и npm
- OSPanel или аналогичный локальный сервер

### Установка зависимостей

```bash
# Backend зависимости
composer install

# Frontend зависимости
npm install
```

### Настройка окружения

```bash
# Скопировать файл окружения
cp .env.example .env

# Сгенерировать ключ приложения
php artisan key:generate
```

### База данных

Проект настроен на использование двух баз данных:

1. **MySQL локальная** (AdminPanelChatBot) - для админ-панели
   - Требует запущенный MySQL в OSPanel
   - Или можно переключить на SQLite (см. ниже)
   
2. **MySQL удаленная** (myhomeday) - для данных чат-ботов
   - Уже подключена и работает ✓
   - 263 таблицы доступны

**Настройка баз данных в `.env`:**

```env
# Основная БД для админ-панели (требует запущенный MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AdminPanelChatBot
DB_USERNAME=root
DB_PASSWORD=root

# Удаленная БД для данных чат-ботов (работает)
REMOTE_DB_HOST=185.175.46.117
REMOTE_DB_PORT=3306
REMOTE_DB_DATABASE=myhomeday
REMOTE_DB_USERNAME=admin
REMOTE_DB_PASSWORD="A!d2m@in"

# Сессии и кэш используют файлы (не требуют БД)
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

**Альтернатива: использовать SQLite для админ-панели**

Если не хотите запускать MySQL локально, измените в `.env`:
```env
DB_CONNECTION=sqlite
```
База SQLite уже создана в `database/database.sqlite`.

**Использование подключений в коде:**

```php
// Основная БД (по умолчанию - AdminPanelChatBot)
$users = DB::table('users')->get();
User::all();

// Удаленная БД для чат-ботов (myhomeday)
$chats = DB::connection('mysql_remote')->table('chats')->get();

// Модель для таблиц из удаленной БД
class Chat extends Model {
    protected $connection = 'mysql_remote';
    protected $table = 'chats';
}

// Пример запроса
Chat::where('status', 'active')->get();
```

### Сборка фронтенда

Для разработки (с автоперезагрузкой):
```bash
npm run dev
```

Для production:
```bash
npm run build
```

### Запуск сервера

В отдельном терминале запустите Laravel сервер:

```bash
php artisan serve
```

Проект будет доступен по адресу: `http://localhost:8000`

## Структура проекта

```
├── app/
│   ├── Http/Controllers/    # Контроллеры Laravel
│   ├── Models/              # Модели Eloquent
│   └── Providers/           # Service Providers
├── resources/
│   ├── js/
│   │   ├── app.ts           # Точка входа Vue
│   │   ├── App.vue          # Корневой компонент
│   │   └── bootstrap.ts     # Инициализация axios
│   ├── css/
│   │   └── app.scss         # Основные стили
│   └── views/               # Blade шаблоны
├── routes/
│   └── web.php              # Web маршруты
├── vite.config.js           # Конфигурация Vite
├── tsconfig.json            # Конфигурация TypeScript
└── package.json             # Frontend зависимости
```

## Разработка

### Создание компонентов Vue

Создавайте компоненты в `resources/js/components/`:

```vue
<script setup lang="ts">
// TypeScript код
</script>

<template>
  <div>
    <!-- HTML разметка -->
  </div>
</template>

<style scoped>
/* SCSS стили */
</style>
```

### Импорт компонентов

```typescript
import ComponentName from '@/components/ComponentName.vue'
```

### API запросы

Axios доступен глобально через `window.axios`:

```typescript
// GET запрос
const response = await axios.get('/api/endpoint')

// POST запрос
const response = await axios.post('/api/endpoint', { data })
```

## Скрипты npm

- `npm run dev` - Запуск Vite dev сервера с HMR
- `npm run build` - Сборка production версии
- `npm run type-check` - Проверка типов TypeScript

## Полезные команды Laravel

- `php artisan migrate` - Выполнить миграции
- `php artisan make:model` - Создать модель
- `php artisan make:controller` - Создать контроллер
- `php artisan make:migration` - Создать миграцию
- `php artisan tinker` - Интерактивная оболочка Laravel

## 🚀 Деплой на production сервер

### Требования для production сервера

- PHP 8.3+ с расширениями: `openssl`, `pdo`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
- MySQL 5.7+ или MariaDB 10.3+
- Node.js 18+ и npm (для сборки фронтенда)
- Composer 2.0+
- Nginx или Apache с mod_rewrite
- SSL сертификат (Let's Encrypt или коммерческий)

### Предварительная подготовка

1. **Создайте базу данных MySQL для админ-панели:**
```sql
CREATE DATABASE AdminPanelChatBot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON AdminPanelChatBot.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```

2. **Клонируйте проект на сервер:**
```bash
git clone <your-repo-url> /var/www/bot.capitalmars.com
cd /var/www/bot.capitalmars.com
```

3. **Установите зависимости:**
```bash
# Backend
composer install --optimize-autoloader --no-dev

# Frontend
npm install
npm run build
```

4. **Настройте окружение:**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Настройте `.env` для production:**
```env
APP_NAME="WhatsApp Bot Admin Panel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bot.capitalmars.com

# База данных
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AdminPanelChatBot
DB_USERNAME=admin
DB_PASSWORD=your_secure_password

# Удаленная БД для чат-ботов
REMOTE_DB_HOST=185.175.46.117
REMOTE_DB_PORT=3306
REMOTE_DB_DATABASE=myhomeday
REMOTE_DB_USERNAME=admin
REMOTE_DB_PASSWORD="A!d2m@in"

# Логирование
LOG_CHANNEL=daily
LOG_LEVEL=error

# Кэш и сессии (рекомендуется Redis для production)
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis (если используется)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Sanctum domains
SANCTUM_STATEFUL_DOMAINS=bot.capitalmars.com

# Green API (WhatsApp)
API_URL=https://1105.api.green-api.com
ID_INSTANCE=your_instance_id
API_TOKEN_INSTANCE=your_api_token

# OpenAI
OPENAI_API_KEY=your_openai_api_key
VECTOR_STORE_ID=your_vector_store_id
USE_PROXY=false
```

6. **Выполните миграции:**
```bash
php artisan migrate --force
php artisan db:seed --force
```

7. **Оптимизируйте приложение:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

8. **Настройте права доступа:**
```bash
chown -R www-data:www-data /var/www/bot.capitalmars.com
chmod -R 755 /var/www/bot.capitalmars.com
chmod -R 775 /var/www/bot.capitalmars.com/storage
chmod -R 775 /var/www/bot.capitalmars.com/bootstrap/cache
```

### Настройка Nginx

Создайте конфигурационный файл `/etc/nginx/sites-available/bot.capitalmars.com`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name bot.capitalmars.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name bot.capitalmars.com;
    root /var/www/bot.capitalmars.com/public;

    index index.php index.html index.htm;

    charset utf-8;

    # SSL сертификат
    ssl_certificate /etc/letsencrypt/live/bot.capitalmars.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bot.capitalmars.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Безопасность
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Кэширование статических файлов
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Активируйте сайт:
```bash
ln -s /etc/nginx/sites-available/bot.capitalmars.com /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### Настройка Apache (альтернатива)

Если используете Apache, создайте `.htaccess` в `public/` (уже должен быть):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

И настройте виртуальный хост с `DocumentRoot` указывающим на `public/` директорию.

### Настройка Supervisor для очередей (опционально)

Если используете очереди, создайте конфигурацию Supervisor:

```ini
[program:bot-capitalmars-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bot.capitalmars.com/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/bot.capitalmars.com/storage/logs/queue.log
stopwaitsecs=3600
```

### Проверка после деплоя

1. Проверьте доступность сайта: `https://bot.capitalmars.com`
2. Проверьте API: `https://bot.capitalmars.com/api/auth/login`
3. Проверьте логи: `tail -f storage/logs/laravel.log`
4. Проверьте статус очередей (если используются): `php artisan queue:work`

### Команды для обновления на production

```bash
# После обновления кода
git pull origin main
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Очистка и пересборка кэша
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Применение миграций
php artisan migrate --force

# Оптимизация
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Перезапуск очередей (если используются)
php artisan queue:restart
```

### Безопасность

- ✅ Используйте HTTPS (SSL сертификат)
- ✅ Установите `APP_DEBUG=false` в production
- ✅ Используйте сильные пароли для БД
- ✅ Регулярно обновляйте зависимости: `composer update` и `npm update`
- ✅ Настройте firewall (ufw/firewalld)
- ✅ Регулярно создавайте резервные копии базы данных
- ✅ Настройте автоматические логи

### Мониторинг

Рекомендуется настроить мониторинг:
- Логи Laravel: `storage/logs/laravel.log`
- Логи Nginx/Apache
- Мониторинг доступности сервера (UptimeRobot, Pingdom и т.д.)
- Мониторинг производительности (New Relic, DataDog и т.д.)

## Дальнейшая разработка

Рекомендуется добавить:

1. **Роутинг**: Vue Router для SPA навигации ✅ (уже реализован)
2. **State Management**: Pinia для управления состоянием ✅ (уже реализован)
3. **UI Kit**: Element Plus, Vuetify или подобный
4. **Авторизация**: Laravel Sanctum ✅ (уже реализовано)
5. **Тестирование**: PHPUnit + Vitest

## Лицензия

MIT
