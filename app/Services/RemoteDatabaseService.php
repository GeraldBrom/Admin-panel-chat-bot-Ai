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
     * Get object data from remote database
     */
    public function getObjectData(int $objectId): ?array
    {
        try {
            // Get owner info
            $ownerInfo = DB::connection('mysql_remote')
                ->table('object_owner_info')
                ->where('object_id', $objectId)
                ->first();

            if (!$ownerInfo) {
                Log::error("Owner info not found for object_id: {$objectId}");
                return null;
            }

            // Get object info
            $objectInfo = DB::connection('mysql_remote')
                ->table('objects')
                ->where('id', $objectId)
                ->first(['id', 'address', 'price', 'commission_client']);

            if (!$objectInfo) {
                Log::error("Object not found: {$objectId}");
                return null;
            }

            // Get deal count
            $dealCount = DB::connection('mysql_remote')
                ->table('deals')
                ->where('object_id', $objectId)
                ->count('object_id');

            // Get last advertisement date
            $lastAd = DB::connection('mysql_remote')
                ->table('info_on_site')
                ->where('object_id', $objectId)
                ->orderBy('date_site', 'desc')
                ->first(['date_site']);

            // Format data
            $countWord = self::OBJECT_NUMBERS[$dealCount] ?? $dealCount;
            $formattedDate = $this->formatDate($lastAd->date_site ?? null);
            $formattedPrice = number_format($objectInfo->price, 0, '', ',');

            return [
                'objectInfo' => [
                    [
                        'id' => $objectInfo->id,
                        'address' => $objectInfo->address,
                        'price' => $objectInfo->price,
                        'commission_client' => $objectInfo->commission_client,
                    ]
                ],
                'ownerInfo' => [
                    [
                        'value' => $ownerInfo->value,
                    ]
                ],
                'objectCount' => $countWord,
                'objectAdd' => $lastAd ? [$lastAd] : [],
                'formattedAddDate' => $formattedDate,
                'formattedPrice' => $formattedPrice,
            ];
        } catch (\Exception $e) {
            Log::error("Error fetching object data for object_id: {$objectId}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format date to "в [Month] [Year] году"
     */
    private function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            $dateObj = new \DateTime($date);
            $month = (int) $dateObj->format('n') - 1; // 0-11
            $year = $dateObj->format('Y');
            
            return "в " . self::MONTHS[$month] . " {$year} году";
        } catch (\Exception $e) {
            Log::error("Error formatting date: {$date}", [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}

