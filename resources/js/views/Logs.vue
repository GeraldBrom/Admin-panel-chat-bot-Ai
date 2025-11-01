<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import logService, { type LogEntry, type LogLevel } from '@/services/logService';

// State
const logs = ref<LogEntry[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const selectedLevel = ref<LogLevel>('all');
const total = ref(0);
const hasMore = ref(false);
const offset = ref(0);
const limit = 100;
const downloading = ref(false);
const clearing = ref(false);

// Загрузка логов
const loadLogs = async (append = false) => {
    try {
        loading.value = true;
        error.value = null;

        const response = await logService.getLogs(
            selectedLevel.value,
            limit,
            append ? offset.value : 0
        );

        if (append) {
            logs.value.push(...response.logs);
        } else {
            logs.value = response.logs;
            offset.value = 0;
        }

        total.value = response.total;
        hasMore.value = response.hasMore;
        offset.value = response.offset + response.logs.length;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Ошибка при загрузке логов';
        console.error('Error loading logs:', err);
    } finally {
        loading.value = false;
    }
};

// Обновить логи
const refreshLogs = () => {
    offset.value = 0;
    loadLogs(false);
};

// Загрузить еще
const loadMore = () => {
    loadLogs(true);
};

// Изменение фильтра
const changeLevel = (level: LogLevel) => {
    selectedLevel.value = level;
    refreshLogs();
};

// Скачать полный лог-файл
const downloadFullLog = async () => {
    try {
        downloading.value = true;
        await logService.downloadLogs();
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Ошибка при скачивании лог-файла';
        console.error('Error downloading logs:', err);
    } finally {
        downloading.value = false;
    }
};

// Очистить логи
const clearAllLogs = async () => {
    if (!confirm('Вы уверены, что хотите очистить весь лог-файл? Это действие необратимо.')) {
        return;
    }

    try {
        clearing.value = true;
        error.value = null;
        await logService.clearLogs();
        logs.value = [];
        total.value = 0;
        hasMore.value = false;
        offset.value = 0;
        alert('Лог-файл успешно очищен');
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Ошибка при очистке лог-файла';
        console.error('Error clearing logs:', err);
    } finally {
        clearing.value = false;
    }
};

// Получить класс для уровня лога
const getLevelClass = (level: string): string => {
    switch (level.toUpperCase()) {
        case 'ERROR':
            return 'log-level-error';
        case 'WARNING':
            return 'log-level-warning';
        case 'INFO':
            return 'log-level-info';
        case 'DEBUG':
            return 'log-level-debug';
        default:
            return '';
    }
};

// Форматирование временной метки
const formatTimestamp = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleString('ru-RU');
};

// При монтировании компонента
onMounted(() => {
    loadLogs();
});
</script>

<template>
    <MainLayout>
        <div class="logs-container">
            <div class="logs-header">
                <h1>Логи системы</h1>
                <div class="logs-actions">
                    <button @click="refreshLogs" :disabled="loading" class="btn btn-secondary">
                        <span v-if="!loading">🔄 Обновить</span>
                        <span v-else>⏳ Загрузка...</span>
                    </button>
                    <button @click="downloadFullLog" :disabled="downloading" class="btn btn-info">
                        <span v-if="!downloading">📥 Скачать</span>
                        <span v-else>⏳ Скачивание...</span>
                    </button>
                    <button @click="clearAllLogs" :disabled="clearing" class="btn btn-danger">
                        <span v-if="!clearing">🗑️ Очистить</span>
                        <span v-else>⏳ Очистка...</span>
                    </button>
                </div>
            </div>

            <!-- Фильтры -->
            <div class="logs-filters">
                <button
                    @click="changeLevel('all')"
                    :class="{ active: selectedLevel === 'all' }"
                    class="filter-btn"
                >
                    Все ({{ total }})
                </button>
                <button
                    @click="changeLevel('error')"
                    :class="{ active: selectedLevel === 'error' }"
                    class="filter-btn filter-error"
                >
                    Ошибки
                </button>
                <button
                    @click="changeLevel('warning')"
                    :class="{ active: selectedLevel === 'warning' }"
                    class="filter-btn filter-warning"
                >
                    Предупреждения
                </button>
                <button
                    @click="changeLevel('info')"
                    :class="{ active: selectedLevel === 'info' }"
                    class="filter-btn filter-info"
                >
                    Информация
                </button>
            </div>

            <!-- Ошибки -->
            <div v-if="error" class="error-message">
                {{ error }}
            </div>

            <!-- Логи -->
            <div class="logs-content">
                <div v-if="loading && logs.length === 0" class="loading-message">
                    Загрузка логов...
                </div>

                <div v-else-if="logs.length === 0" class="empty-message">
                    Логов не найдено
                </div>

                <div v-else class="logs-list">
                    <div
                        v-for="(log, index) in logs"
                        :key="index"
                        :class="['log-entry', getLevelClass(log.level)]"
                    >
                        <div class="log-header">
                            <span class="log-timestamp">{{ formatTimestamp(log.timestamp) }}</span>
                            <span :class="['log-level', getLevelClass(log.level)]">
                                {{ log.level }}
                            </span>
                        </div>
                        <div class="log-message">
                            <pre>{{ log.fullMessage }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Пагинация -->
            <div v-if="hasMore && !loading" class="logs-pagination">
                <button @click="loadMore" class="btn btn-primary btn-load-more">
                    Загрузить ещё
                </button>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.logs-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.logs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.logs-header h1 {
    margin: 0;
    font-size: 28px;
    color: #333;
}

.logs-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #4338ca;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #4b5563;
}

.btn-info {
    background: #0891b2;
    color: white;
}

.btn-info:hover:not(:disabled) {
    background: #0e7490;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #b91c1c;
}

.logs-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    color: #374151;
}

.filter-btn:hover {
    border-color: #9ca3af;
}

.filter-btn.active {
    border-color: #4f46e5;
    background: #4f46e5;
    color: white;
}

.filter-error.active {
    border-color: #dc2626;
    background: #dc2626;
}

.filter-warning.active {
    border-color: #f59e0b;
    background: #f59e0b;
}

.filter-info.active {
    border-color: #0891b2;
    background: #0891b2;
}

.error-message {
    padding: 15px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    color: #dc2626;
    margin-bottom: 20px;
}

.loading-message,
.empty-message {
    text-align: center;
    padding: 40px;
    color: #6b7280;
    font-size: 16px;
}

.logs-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.logs-list {
    display: flex;
    flex-direction: column;
}

.log-entry {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    transition: background 0.2s;
}

.log-entry:last-child {
    border-bottom: none;
}

.log-entry:hover {
    background: #f9fafb;
}

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.log-timestamp {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}

.log-level {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.log-level-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.log-level-warning {
    background: #fffbeb;
    color: #f59e0b;
    border: 1px solid #fde68a;
}

.log-level-info {
    background: #ecfeff;
    color: #0891b2;
    border: 1px solid #a5f3fc;
}

.log-level-debug {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.log-message {
    margin-top: 8px;
}

.log-message pre {
    margin: 0;
    padding: 12px;
    background: #f9fafb;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
    color: #1f2937;
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.log-entry.log-level-error .log-message pre {
    background: #fef2f2;
    border-left: 3px solid #dc2626;
}

.log-entry.log-level-warning .log-message pre {
    background: #fffbeb;
    border-left: 3px solid #f59e0b;
}

.log-entry.log-level-info .log-message pre {
    background: #ecfeff;
    border-left: 3px solid #0891b2;
}

.logs-pagination {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.btn-load-more {
    padding: 12px 40px;
    font-size: 16px;
}
</style>

