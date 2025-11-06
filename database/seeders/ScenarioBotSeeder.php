<?php

namespace Database\Seeders;

use App\Models\ScenarioBot;
use Illuminate\Database\Seeder;

class ScenarioBotSeeder extends Seeder
{
    public function run(): void
    {
        // Создаем тестовый сценарный бот для WhatsApp
        ScenarioBot::firstOrCreate(
            [
                'name' => 'Сценарный бот WhatsApp',
                'platform' => 'whatsapp',
            ],
            [
                'description' => 'Основной сценарный бот для WhatsApp - проверка аренды квартиры',
                'welcome_message' => "{ownernameclean}, добрый день!\n\nЯ — чат бот Capital Mars. Мы уже {objectCount} сдавали вашу квартиру на {address}. Видим, что объявление снова актуально — верно? Если да, готовы подключиться к сдаче. Ответь пожалуйста Да или нет.",
                'is_active' => true,
                'settings' => [
                    'scenario' => [
                        'step1_question' => 'Вы сдаете еще квартиру? Ответьте Да или Нет',
                        'step1_yes_response' => 'Вы согласны работать с нами? Ответьте Да или Нет',
                        'step1_no_response' => 'К сожалению, мы работаем только со сдаваемыми квартирами. Спасибо за ваше время!',
                        'step2_yes_response' => 'Ваша цена актуальна {formatted_price}? Ответьте Да или Нет',
                        'step2_no_response' => 'Жаль, что вы отказались от работы с нами. Если передумаете - напишите нам!',
                        'step3_yes_response' => "Отлично! Цена подтверждена. Спасибо за информацию!\n\nМы свяжемся с вами в ближайшее время.",
                        'step3_no_response' => 'Укажите верную цену (например: 20000 или 20 тыс)',
                        'step3_1_final_message' => "Спасибо! Новая цена {price} сохранена.\n\nМы свяжемся с вами в ближайшее время.",
                    ],
                ],
            ]
        );

        echo "✅ Сценарный бот создан успешно!\n";
    }
}

