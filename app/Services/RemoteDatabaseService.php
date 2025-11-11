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
            // Основной запрос с подзапросом для получения ПЕРВОЙ непустой записи имени
            // Берем запись с минимальным num где value не пустое
            $result = DB::connection('mysql_remote')
                ->table('objects as o')
                ->leftJoin('houses as h', 'o.house_id', '=', 'h.house_id')
                ->leftJoin('complex as c', 'h.complex_id', '=', 'c.id')
                ->leftJoin(
                    DB::raw('(SELECT object_id, COUNT(*) as deal_count FROM deals GROUP BY object_id) as d'),
                    'o.id', '=', 'd.object_id'
                )
                ->where('o.id', $objectId)
                ->select([
                    'o.id',
                    'o.full_address',
                    'o.price',
                    'o.commission_client',
                    DB::raw("(
                        SELECT value 
                        FROM object_owner_info 
                        WHERE object_id = o.id 
                          AND type = 'name'
                          AND value IS NOT NULL 
                          AND value != ''
                        ORDER BY num ASC 
                        LIMIT 1
                    ) as owner_name"),
                    'c.name as complex_name',
                    DB::raw('COALESCE(d.deal_count, 0) as deal_count')
                ])
                ->first();

            if (!$result) {
                Log::error("Объект не найден: {$objectId}");
                return null;
            }

            // Логируем сырое значение имени из БД
            Log::info("Получены данные объекта из БД", [
                'object_id' => $objectId,
                'owner_name_from_db' => $result->owner_name,
                'is_null' => is_null($result->owner_name),
                'is_empty' => empty($result->owner_name),
            ]);
            
            if (!$result->owner_name) {
                Log::warning("Информация о владельце не найдена для object_id: {$objectId}");
            }

            // Форматировать данные
            $dealCount = (int) $result->deal_count;
            $countWord = $this->getCountWord($dealCount);
            $countWordWithSuffix = $this->getCountWordWithSuffix($dealCount);
            $formattedPrice = number_format($result->price, 0, '.', ',');
            
            // Форматируем адрес: убираем "г Москва," или "г. Москва," из начала
            $address = $result->full_address ?? '';
            
            // Удаляем различные варианты "г Москва" из начала адреса
            $address = preg_replace('/^г\.?\s*Москва,?\s*/iu', '', $address);
            $address = trim($address);

            return [
                'id' => $result->id,
                'address' => $address,
                'price' => $result->price,
                'commission_client' => $result->commission_client,
                'owner_name' => $result->owner_name ?? '',  // Пустая строка вместо 'Клиент'
                'complex_name' => $result->complex_name ?? null,
                'deal_count' => $dealCount, // Числовое значение количества сделок
                'count' => $countWord,
                'objectCount' => $countWord,
                'objectCountWithSuffix' => $countWordWithSuffix, // Со склонением "раз/раза"
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
     * Получить слово-числительное для количества сделок (без склонения "раз")
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
     * Получить слово-числительное со склонением "раз/раза"
     */
    private function getCountWordWithSuffix(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;
        
        // Получаем текстовое представление числа
        $countWord = $this->getCountWord($count);
        
        // Для нуля возвращаем без склонения
        if ($count === 0) {
            return $countWord;
        }
        
        // Определяем правильное склонение слова "раз"
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            $suffix = 'раз';
        } else {
            $suffix = match ($lastDigit) {
                1 => 'раз',
                2, 3, 4 => 'раза',
                default => 'раз',
            };
        }
        
        return "{$countWord} {$suffix}";
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

