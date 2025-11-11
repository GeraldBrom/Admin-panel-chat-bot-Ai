<?php

namespace App\Services;

use App\Services\Dialogs\DialogInitializer;
use App\Services\Dialogs\IncomingMessageHandler;
use App\Services\Dialogs\DialogFinalizer;
use App\Services\Dialogs\DialogSessionCleaner;

/**
 * DialogService - фасад для управления диалогами
 * 
 * Делегирует работу специализированным сервисам:
 * - DialogInitializer: инициализация диалогов
 * - IncomingMessageHandler: обработка входящих сообщений
 * - DialogFinalizer: финализация диалогов
 * - DialogSessionCleaner: очистка сессий
 */
class DialogService
{
    public function __construct(
        private DialogInitializer $dialogInitializer,
        private IncomingMessageHandler $incomingMessageHandler,
        private DialogFinalizer $dialogFinalizer,
        private DialogSessionCleaner $dialogSessionCleaner,
    ) {}

    /**
     * Инициализация диалога с клиентом
     */
    public function initializeDialog(string $chatId, int $objectId, ?int $botConfigId = null): void
    {
        $this->dialogInitializer->initializeDialog($chatId, $objectId, $botConfigId);
    }

    /**
     * Обработка входящего сообщения с буферизацией
     */
    public function processIncomingMessage(string $chatId, string $messageText, array $meta = []): void
    {
        $this->incomingMessageHandler->handle($chatId, $messageText, $meta);
    }

    /**
     * Финализация диалога при остановке бота
     */
    public function finalizeDialog(string $chatId): void
    {
        $this->dialogFinalizer->finalize($chatId);
    }

    /**
     * Очистка активной сессии чата
     */
    public function clearSession(string $chatId): void
    {
        $this->dialogSessionCleaner->clear($chatId);
    }
}
