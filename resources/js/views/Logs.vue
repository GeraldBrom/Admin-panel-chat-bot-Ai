<script setup lang="ts">
import { ref, onMounted } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import logService, { type LogEntry, type LogLevel } from '@/services/logService';

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

const refreshLogs = () => {
    offset.value = 0;
    loadLogs(false);
};

const loadMore = () => {
    loadLogs(true);
};

const changeLevel = (level: LogLevel) => {
    selectedLevel.value = level;
    refreshLogs();
};

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

const formatTimestamp = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleString('ru-RU');
};

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

            <div v-if="error" class="error-message">
                {{ error }}
            </div>

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

            <div v-if="hasMore && !loading" class="logs-pagination">
                <button @click="loadMore" class="btn btn-primary btn-load-more">
                    Загрузить ещё
                </button>
            </div>
        </div>
    </MainLayout>
</template>

