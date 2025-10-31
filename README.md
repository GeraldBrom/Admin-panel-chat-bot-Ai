# Админ-панель для управления чат-ботами

Админ-панель на Laravel 12 + Vue 3 + TypeScript + SCSS для управления чат-ботами.

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

## Дальнейшая разработка

Рекомендуется добавить:

1. **Роутинг**: Vue Router для SPA навигации
2. **State Management**: Pinia для управления состоянием
3. **UI Kit**: Element Plus, Vuetify или подобный
4. **Авторизация**: Laravel Sanctum или Breeze
5. **Тестирование**: PHPUnit + Vitest

## Лицензия

MIT
