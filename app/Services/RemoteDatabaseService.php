<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoteDatabaseService
{
    private const OBJECT_NUMBERS = [
        0 => 'ноль',
        1 => 'один',
        2 => 'два',
        3 => 'три',
        4 => 'четыре',
        5 => 'пять',
        6 => 'шесть',
        7 => 'семь',
        8 => 'восемь',
        9 => 'девять',
        10 => 'десять',
        11 => 'одиннадцать',
        12 => 'двенадцать',
        13 => 'тринадцать',
        14 => 'четырнадцать',
        15 => 'пятнадцать',
        16 => 'шестнадцать',
        17 => 'семнадцать',
        18 => 'восемнадцать',
        19 => 'девятнадцать',
        20 => 'двадцать',
    ];

    private const MONTHS = [
        'Январе', 'Феврале', 'Марте', 'Апреле', 'Мае', 'Июне',
        'Июле', 'Августе', 'Сентябре', 'Октябре', 'Ноябре', 'Декабре'
    ];

    /**
     * Получить данные объекта из удаленной базы данных с кэшированием
     */
    public function getObjectData(int $objectId): ?array
    {
        // Кэширование на 5 минут (300 секунд)
        return cache()->remember("object_data_{$objectId}", 300, function () use ($objectId) {
            return $this->fetchObjectDataFromDatabase($objectId);
        });
    }

    /**
     * Очистить кэш для конкретного объекта (например, после обновления данных)
     */
    public function clearObjectCache(int $objectId): void
    {
        cache()->forget("object_data_{$objectId}");
    }

    /**
     * Получить данные объекта напрямую из БД (без кэша)
     */
    private function fetchObjectDataFromDatabase(int $objectId): ?array
    {
        try {
            // Упрощенный запрос без info_on_site (эта таблица может быть повреждена)
            $result = DB::connection('mysql_remote')
                ->table('objects as o')
                ->leftJoin('object_owner_info as ooi', 'o.id', '=', 'ooi.object_id')
                ->leftJoin(
                    DB::raw('(SELECT object_id, COUNT(*) as deal_count FROM deals GROUP BY object_id) as d'),
                    'o.id', '=', 'd.object_id'
                )
                ->where('o.id', $objectId)
                ->select([
                    'o.id',
                    'o.address',
                    'o.price',
                    'o.commission_client',
                    'ooi.value as owner_name',
                    DB::raw('COALESCE(d.deal_count, 0) as deal_count')
                ])
                ->first();

            if (!$result) {
                Log::error("Объект не найден: {$objectId}");
                return null;
            }

            if (!$result->owner_name) {
                Log::warning("Информация о владельце не найдена для object_id: {$objectId}");
            }

            // Форматировать данные
            $dealCount = (int) $result->deal_count;
            $countWord = $this->getCountWord($dealCount);
            $formattedPrice = number_format($result->price, 0, '', ',');

            return [
                'id' => $result->id,
                'address' => $result->address,
                'price' => $result->price,
                'commission_client' => $result->commission_client,
                'owner_name' => $result->owner_name ?? 'Клиент',
                'count' => $countWord, // Для совместимости с ScenarioBotService
                'objectCount' => $countWord,
                'formattedPrice' => $formattedPrice,
            ];
        } catch (\Exception $e) {
            Log::error("Ошибка получения данных объекта для object_id: {$objectId}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить слово-числительное для количества сделок
     */
    private function getCountWord(int $count): string
    {
        // Используем константу для малых чисел
        if (isset(self::OBJECT_NUMBERS[$count])) {
            return self::OBJECT_NUMBERS[$count];
        }

        // Для больших чисел используем склонение
        return $this->getDeclensionForCount($count);
    }

    /**
     * Склонение числительных для больших чисел
     */
    private function getDeclensionForCount(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        // Определяем правильное окончание
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return "{$count} раз";
        }

        return match ($lastDigit) {
            1 => "{$count} раз",
            2, 3, 4 => "{$count} раза",
            default => "{$count} раз",
        };
    }

    /**
     * Форматировать дату в "в [Month] [Year] году"
     */
    private function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            $dateObj = new \DateTime($date);
            $month = (int) $dateObj->format('n') - 1;
            $year = $dateObj->format('Y');
            
            return "в " . self::MONTHS[$month] . " {$year} году";
        } catch (\Exception $e) {
            Log::error("Ошибка форматирования даты: {$date}", [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}

