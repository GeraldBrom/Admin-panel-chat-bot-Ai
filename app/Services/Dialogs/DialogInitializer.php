<?php

namespace App\Services\Dialogs;

use App\Services\RemoteDatabaseService;
use App\Services\Extraction\ExtractOwnerNameWithAi;
use App\Services\Messaging\TemplateRenderer;
use App\Services\Messaging\MessageFormatter;
use App\Services\Messaging\MessageSender;
use App\Models\BotConfig;
use App\Models\BotSession;
use App\Models\Dialog;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class DialogInitializer
{
    private const STATE_INITIAL = 'initial';
    private const STATE_ACTIVE = 'active';
    private const STATE_COMPLETED = 'completed';

    public function __construct(
        private RemoteDatabaseService $remoteDbService,
        private ExtractOwnerNameWithAi $extractOwnerNameWithAi,
        private TemplateRenderer $templateRenderer,
        private MessageFormatter $messageFormatter,
        private MessageSender $messageSender,
    ) {}

    public function initializeDialog(string $chatId, int $objectId, ?int $botConfigId = null): void
        {
            Log::info("Инициализация диалога для chatId: {$chatId}, objectId: {$objectId}, configId: {$botConfigId}");

            // Конфигурация: если явно не передана, используем последнюю для whatsapp
            $config = $botConfigId ? BotConfig::find($botConfigId) : null;
            if (!$config) {
                $config = BotConfig::forPlatform('whatsapp')->orderByDesc('id')->first();
                $botConfigId = $config?->id;
            }

            // Получить или создать сессию бота; если уже была сессия, принудительно переводим в running
            $session = BotSession::firstOrCreate(
                [
                    'chat_id' => $chatId,
                    'platform' => 'whatsapp',
                ],
                [
                    'object_id' => $objectId,
                    'bot_config_id' => $botConfigId,
                    'status' => 'running',
                    'dialog_state' => ['state' => self::STATE_INITIAL],
                    'started_at' => now(),
                ]
            );

            // Получить диалог
            $dialog = Dialog::getOrCreate($chatId);

            // Получить данные объекта из удаленной базы данных
            $objectData = $this->remoteDbService->getObjectData($objectId);

            if (!$objectData) {
                Log::error("Ошибка при получении данных объекта для objectId: {$objectId}");
                return;
            }

            // Получаем сырое имя владельца из БД (без очистки)
            $rawOwnerName = $objectData['owner_name'] ?? '';
            
            Log::info("Получено сырое имя из БД", [
                'object_id' => $objectId,
                'raw_owner_name' => $rawOwnerName,
            ]);
            
            // Извлекаем чистое имя с помощью ИИ для использования в kickoff message
            $cleanOwnerName = $this->extractOwnerNameWithAi->extractOwnerNameWithAI($rawOwnerName);
            
            Log::info("Имя после извлечения ИИ", [
                'object_id' => $objectId,
                'clean_owner_name' => $cleanOwnerName,
                'is_empty' => empty($cleanOwnerName),
            ]);

            // Получаем числовое значение deal_count для условной логики
            $dealCount = (int) ($objectData['deal_count'] ?? 0);
            $objectCountWord = $objectData['objectCount'] ?? '0';
            $objectCountWithSuffix = $objectData['objectCountWithSuffix'] ?? '0 раз';
            
            // Условная логика: формируем текст в зависимости от количества сделок
            if ($dealCount === 0) {
                // Если сделок не было - используем другой текст без упоминания количества
                $rentalPhrase = "работали с вами по квартире на";
            } else {
                // Если были сделки - указываем количество со склонением
                $rentalPhrase = "{$objectCountWithSuffix} сдавали вашу квартиру на";
            }

            // Формируем приветствие на основе извлеченного ИИ имени
            if (!empty($cleanOwnerName)) {
                $greeting = "{$cleanOwnerName}, добрый день!";
            } else {
                $greeting = "Добрый день!";
            }
            
            $vars = [
                'greeting' => $greeting,
                'owner_name_clean' => $cleanOwnerName,  // Для использования в шаблонах
                'formattedAddDate' => $objectData['formattedAddDate'] ?? '',
                'objectCount' => $objectCountWord,
                'address' => $objectData['address'] ?? '',
                'price' => $objectData['price'] ?? '',
                'formattedPrice' => $objectData['formattedPrice'] ?? '',
                'rental_phrase' => $rentalPhrase,
            ];

            // Подготовка метаданных для сессии и диалога
            $metadata = [
                'object_id' => $objectId,
                'owner_name_raw' => $rawOwnerName,        // Сырое значение из БД
                'owner_name_clean' => $cleanOwnerName,    // Извлеченное ИИ имя для использования
                'address' => $objectData['address'] ?? '',
                'object_count' => $objectData['objectCount'] ?? '',
                'add_date' => $objectData['formattedAddDate'] ?? '',
                'price' => $objectData['price'] ?? '',
                'formatted_price' => $objectData['formattedPrice'] ?? '',
                'commission_client' => $objectData['commission_client'] ?? '',
                'phone' => $objectData['phone'] ?? '',
                'email' => $objectData['email'] ?? '',
                'initialized_at' => now()->toIso8601String(),
                'bot_config_id' => $botConfigId,
                'platform' => 'whatsapp',
            ];

            // Обновляем статус и основные поля при повторном запуске (включая metadata)
            $session->update([
                'object_id' => $objectId,
                'bot_config_id' => $botConfigId,
                'status' => 'running',
                'dialog_state' => ['state' => self::STATE_ACTIVE],
                'metadata' => $metadata,
                'started_at' => $session->started_at ?: now(),
                'stopped_at' => null,
            ]);

            // Обновляем диалог с теми же метаданными
            $dialog->update([
                'current_state' => self::STATE_ACTIVE,
                'metadata' => $metadata,
            ]);

            Log::info("Диалог инициализирован для chatId: {$chatId}");

            // Проверяем, есть ли уже сообщения в диалоге
            $existingMessagesCount = Message::where('dialog_id', $dialog->dialog_id)->count();
            
            // Отправка стартового сообщения только если диалог пустой (первый запуск)
            if ($existingMessagesCount === 0) {
                try {
                    $config = $botConfigId ? BotConfig::find($botConfigId) : null;

                    // Используем kickoff_message из конфигурации или дефолтное значение (если нет, используем дефолтное)
                    $kickoffMessage = $config?->kickoff_message;
                    
                    // Рендеринг шаблона с переменными
                    $renderedMessage = $this->templateRenderer->render($kickoffMessage, $vars);
                    
                    // Конвертируем Markdown в WhatsApp форматирование (если есть)
                    $renderedMessage = $this->messageFormatter->convertMarkdownToWhatsApp($renderedMessage);

                    // Отправка непосредственно клиенту БЕЗ GPT обработки
                    if (!empty(trim($renderedMessage))) {
                        Log::info('Отправка стартового сообщения непосредственно клиенту', [
                            'chatId' => $chatId,
                            'message_length' => mb_strlen($renderedMessage),
                        ]);
                        
                        $this->messageSender->sendWithDelay($chatId, $renderedMessage, 0);

                        // Сохранение как сообщение помощника (без использования GPT токенов)
                        Message::create([
                            'dialog_id' => $dialog->dialog_id,
                            'role' => 'assistant',
                            'content' => $renderedMessage,
                            'previous_response_id' => null,
                            'tokens_in' => 0,
                            'tokens_out' => 0,
                        ]);
                    } else {
                        Log::warning('Стартовое сообщение пустое после рендеринга, используем fallback');
                        $fallback = $this->templateRenderer->render(
                            "{greeting} Мы ранее работали по вашей квартире на {address}. Подскажите, вы снова её сдаёте?",
                            [
                                'greeting' => $vars['greeting'] ?? 'Добрый день!',
                                'address' => $vars['address'] ?? '',
                            ]
                        );
                        $fallback = $this->messageFormatter->convertMarkdownToWhatsApp($fallback);
                        $this->messageSender->sendWithDelay($chatId, $fallback, 0);
                        Message::create([
                            'dialog_id' => $dialog->dialog_id,
                            'role' => 'assistant',
                            'content' => $fallback,
                            'previous_response_id' => null,
                            'tokens_in' => 0,
                            'tokens_out' => 0,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Ошибка при отправке стартового сообщения', [ 'error' => $e->getMessage() ]);
                }
            } else {
                Log::info('Диалог уже содержит сообщения, пропускаем отправку kickoff-сообщения', [
                    'chatId' => $chatId,
                    'existing_messages_count' => $existingMessagesCount,
                ]);
            }
        }
}
