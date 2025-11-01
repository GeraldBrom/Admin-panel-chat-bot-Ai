import api from './api';

export interface LogEntry {
    timestamp: string;
    level: 'ERROR' | 'WARNING' | 'INFO' | 'DEBUG';
    message: string;
    fullMessage: string;
}

export interface LogsResponse {
    logs: LogEntry[];
    total: number;
    hasMore: boolean;
    offset: number;
    limit: number;
}

export type LogLevel = 'all' | 'error' | 'warning' | 'info';

class LogService {
    /**
     * Получить логи с фильтрацией и пагинацией
     */
    async getLogs(
        level: LogLevel = 'all',
        limit: number = 100,
        offset: number = 0
    ): Promise<LogsResponse> {
        const response = await api.get('/logs', {
            params: { level, limit, offset },
        });
        return response.data;
    }

    /**
     * Скачать полный лог-файл
     */
    async downloadLogs(): Promise<void> {
        const response = await api.get('/logs/download', {
            responseType: 'blob',
        });

        // Создаем blob URL и инициируем скачивание
        const blob = new Blob([response.data], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `laravel-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.log`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Очистить лог-файл
     */
    async clearLogs(): Promise<{ message: string }> {
        const response = await api.post('/logs/clear');
        return response.data;
    }
}

export default new LogService();

