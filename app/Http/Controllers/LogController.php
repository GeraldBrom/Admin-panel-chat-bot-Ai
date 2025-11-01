<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class LogController extends Controller
{
    private const LOG_FILE = 'laravel.log';
    private const LOGS_PATH = 'storage/logs/';
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;

    /**
     * Получить логи с фильтрацией и пагинацией
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $level = $request->input('level', 'all');
            $limit = min((int) $request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $offset = (int) $request->input('offset', 0);

            $logPath = storage_path('logs/' . self::LOG_FILE);

            if (!File::exists($logPath)) {
                return response()->json([
                    'logs' => [],
                    'total' => 0,
                    'hasMore' => false,
                    'message' => 'Лог-файл не найден',
                ]);
            }

            // Чтение файла построчно (более эффективно для больших файлов)
            $allLines = $this->readLogFile($logPath);
            
            // Парсинг логов
            $parsedLogs = $this->parseLogLines($allLines);
            
            // Фильтрация по уровню
            $filteredLogs = $this->filterLogsByLevel($parsedLogs, $level);
            
            // Обратный порядок (новые сверху)
            $filteredLogs = array_reverse($filteredLogs);
            
            $total = count($filteredLogs);
            
            // Пагинация
            $paginatedLogs = array_slice($filteredLogs, $offset, $limit);
            
            $hasMore = ($offset + $limit) < $total;

            return response()->json([
                'logs' => $paginatedLogs,
                'total' => $total,
                'hasMore' => $hasMore,
                'offset' => $offset,
                'limit' => $limit,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при чтении логов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Скачать полный лог-файл
     */
    public function download(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $logPath = storage_path('logs/' . self::LOG_FILE);

        if (!File::exists($logPath)) {
            abort(404, 'Лог-файл не найден');
        }

        return Response::download(
            $logPath,
            'laravel-' . date('Y-m-d_H-i-s') . '.log',
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * Очистить лог-файл
     */
    public function clear(): JsonResponse
    {
        try {
            $logPath = storage_path('logs/' . self::LOG_FILE);

            if (File::exists($logPath)) {
                File::put($logPath, '');
            }

            return response()->json([
                'message' => 'Лог-файл успешно очищен',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при очистке лог-файла',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Прочитать лог-файл
     */
    private function readLogFile(string $path): array
    {
        $content = File::get($path);
        return explode("\n", $content);
    }

    /**
     * Парсинг строк лога
     */
    private function parseLogLines(array $lines): array
    {
        $logs = [];
        $currentLog = null;

        foreach ($lines as $line) {
            // Паттерн для начала новой записи лога
            // Формат: [2025-11-01 13:15:39] local.ERROR: ...
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.*)$/', $line, $matches)) {
                // Сохраняем предыдущий лог, если он был
                if ($currentLog !== null) {
                    $logs[] = $currentLog;
                }

                // Начинаем новый лог
                $currentLog = [
                    'timestamp' => $matches[1],
                    'level' => strtoupper($matches[2]),
                    'message' => $matches[3],
                    'fullMessage' => $line,
                ];
            } elseif ($currentLog !== null && trim($line) !== '') {
                // Дополнительные строки принадлежат текущему логу (многострочные сообщения)
                $currentLog['fullMessage'] .= "\n" . $line;
                $currentLog['message'] .= "\n" . $line;
            }
        }

        // Добавляем последний лог
        if ($currentLog !== null) {
            $logs[] = $currentLog;
        }

        return $logs;
    }

    /**
     * Фильтрация логов по уровню
     */
    private function filterLogsByLevel(array $logs, string $level): array
    {
        if ($level === 'all') {
            return $logs;
        }

        $level = strtoupper($level);

        return array_filter($logs, function ($log) use ($level) {
            return $log['level'] === $level;
        });
    }
}

