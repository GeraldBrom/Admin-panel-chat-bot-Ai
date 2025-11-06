<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useScenarioBotStore } from '@/stores/scenarioBotStore';

const scenarioBotStore = useScenarioBotStore();

const bots = computed(() => scenarioBotStore.scenarioBots);
const loading = computed(() => scenarioBotStore.loading);
const loadError = ref<string | null>(null);

// Отдельные поля для каждого шага сценария
const scenarioForm = ref({
    // Приветственное сообщение
    welcome_message: '',
    
    // Шаг 1: Сдается ли квартира
    step1_question: 'Сдается ли квартира? Ответьте Да или Нет',
    step1_yes_response: 'Согласен ли работать с нами? Ответьте Да или Нет',
    step1_no_response: 'К сожалению, мы работаем только со сдаваемыми квартирами. Спасибо за ваше время!',
    
    // Шаг 2: Согласен ли работать
    step2_yes_response: 'Актуальная цена {formatted_price}? Ответьте Да или Нет',
    step2_no_response: 'Жаль, что вы отказались от работы с нами. Если передумаете - напишите нам!',
    
    // Шаг 3: Проверка цены
    step3_yes_response: 'Отлично! Цена подтверждена. Спасибо за информацию!\n\nМы свяжемся с вами в ближайшее время.',
    step3_no_response: 'Укажите верную цену (например: 20000 или 20 тыс)',
    
    // Шаг 3.1: Новая цена
    step3_1_final_message: 'Спасибо! Новая цена {price} сохранена.\n\nМы свяжемся с вами в ближайшее время.',
});

onMounted(async () => {
    try {
        loadError.value = null;
        await scenarioBotStore.fetchAllScenarioBots();
        
        // Загружаем настройки из первого бота
        if (bots.value.length > 0) {
            const bot = bots.value[0];
            scenarioForm.value.welcome_message = bot.welcome_message || '';
            
            // Загружаем настройки сценария если есть
            if (bot.settings?.scenario) {
                Object.assign(scenarioForm.value, bot.settings.scenario);
            }
        }
    } catch (error) {
        console.error('Failed to load bots:', error);
        loadError.value = 'Ошибка загрузки: ' + ((error as any)?.message || 'Неизвестная ошибка');
    }
});

const saveConfig = async () => {
    if (!scenarioForm.value.welcome_message || scenarioForm.value.welcome_message.trim() === '') {
        alert('Заполните приветственное сообщение');
        return;
    }
    
    try {
        // Сохраняем сценарий для всех ботов
        for (const bot of bots.value) {
            await scenarioBotStore.updateScenarioBot(bot.id, {
                welcome_message: scenarioForm.value.welcome_message,
                settings: {
                    scenario: scenarioForm.value,
                },
            });
        }
        
        alert('Сценарий успешно сохранен для всех ботов');
    } catch (err) {
        console.error('Failed to save config:', err);
        alert('Ошибка сохранения сценария: ' + ((err as any)?.response?.data?.message || 'Неизвестная ошибка'));
    }
};
</script>

<template>
  <MainLayout>
    <div class="bot-management-page">
      <div class="page-header">
        <div>
          <h1>🤖 Управление сценарием ботов</h1>
          <p>Единый сценарий для всех сценарных ботов</p>
        </div>
        <div class="page-actions">
          <button class="btn btn--primary" @click="saveConfig" :disabled="loading">
            💾 Сохранить сценарий
          </button>
        </div>
      </div>

      <div v-if="loading && bots.length === 0" class="empty-state">
        <div class="loader"></div>
        <p>Загрузка...</p>
      </div>

      <div v-else-if="loadError" class="error-state">
        <div class="error-icon">⚠️</div>
        <p>{{ loadError }}</p>
      </div>

      <div v-else class="bot-config-container">
        <!-- Приветственное сообщение -->
        <div class="scenario-section">
          <h3 class="section-title">👋 Приветственное сообщение</h3>
          <div class="form-group">
            <label class="form-label">Первое сообщение бота</label>
            <textarea
              v-model="scenarioForm.welcome_message"
              class="form-textarea"
              rows="4"
              placeholder="Здравствуйте! 👋

Я помогу вам с арендой квартиры."
            />
            <small class="form-help">Это сообщение получит клиент при создании сессии. Далее начнется сценарий.</small>
          </div>
        </div>

        <!-- Шаг 1 -->
        <div class="scenario-section">
          <h3 class="section-title">1️⃣ Шаг 1: Проверка сдачи квартиры</h3>
          
          <div class="form-group">
            <label class="form-label">Вопрос</label>
            <input
              v-model="scenarioForm.step1_question"
              class="form-input"
              type="text"
              placeholder="Сдается ли квартира? Ответьте Да или Нет"
            />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">✅ Ответ если ДА</label>
              <textarea
                v-model="scenarioForm.step1_yes_response"
                class="form-textarea"
                rows="3"
                placeholder="Согласен ли работать с нами?"
              />
              <small class="form-help">Переход на Шаг 2</small>
            </div>

            <div class="form-group">
              <label class="form-label">❌ Ответ если НЕТ</label>
              <textarea
                v-model="scenarioForm.step1_no_response"
                class="form-textarea"
                rows="3"
                placeholder="К сожалению, мы работаем только со сдаваемыми квартирами."
              />
              <small class="form-help">Завершение диалога</small>
            </div>
          </div>
        </div>

        <!-- Шаг 2 -->
        <div class="scenario-section">
          <h3 class="section-title">2️⃣ Шаг 2: Согласие на работу</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">✅ Ответ если ДА</label>
              <textarea
                v-model="scenarioForm.step2_yes_response"
                class="form-textarea"
                rows="3"
                placeholder="Актуальная цена {price}?"
              />
              <small class="form-help">Переход на Шаг 3. Используйте {price} для подстановки цены</small>
            </div>

            <div class="form-group">
              <label class="form-label">❌ Ответ если НЕТ</label>
              <textarea
                v-model="scenarioForm.step2_no_response"
                class="form-textarea"
                rows="3"
                placeholder="Жаль, что вы отказались."
              />
              <small class="form-help">Завершение диалога</small>
            </div>
          </div>
        </div>

        <!-- Шаг 3 -->
        <div class="scenario-section">
          <h3 class="section-title">3️⃣ Шаг 3: Проверка цены</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">✅ Ответ если ДА (цена верна)</label>
              <textarea
                v-model="scenarioForm.step3_yes_response"
                class="form-textarea"
                rows="3"
                placeholder="Отлично! Цена подтверждена."
              />
              <small class="form-help">Завершение диалога</small>
            </div>

            <div class="form-group">
              <label class="form-label">❌ Ответ если НЕТ (цена неверна)</label>
              <textarea
                v-model="scenarioForm.step3_no_response"
                class="form-textarea"
                rows="3"
                placeholder="Укажите верную цену"
              />
              <small class="form-help">Переход на Шаг 3.1</small>
            </div>
          </div>
        </div>

        <!-- Шаг 3.1 -->
        <div class="scenario-section">
          <h3 class="section-title">3️⃣.1️⃣ Шаг 3.1: Указание новой цены</h3>
          
          <div class="form-group">
            <label class="form-label">Финальное сообщение после указания цены</label>
            <textarea
              v-model="scenarioForm.step3_1_final_message"
              class="form-textarea"
              rows="3"
              placeholder="Спасибо! Новая цена {price} сохранена."
            />
            <small class="form-help">Используйте {price} для подстановки указанной цены. Завершение диалога</small>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<style scoped lang="scss">
.bot-management-page {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #e0e0e0;

  h1 {
    font-size: 1.875rem;
    font-weight: 600;
    margin: 0.5rem 0;
    color: #1a1a1a;
  }

  p {
    color: #666;
    font-size: 0.95rem;
    margin: 0;
  }
}

.page-actions {
  display: flex;
  gap: 1rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #666;
  font-size: 1.1rem;

  .loader {
    margin: 0 auto 1rem;
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
}

.error-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #dc3545;
  font-size: 1.1rem;

  .error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
  }

  p {
    color: #666;
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.bot-config-container {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.scenario-section {
  padding: 1.5rem;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  border-left: 4px solid #007bff;

  &:last-of-type {
    margin-bottom: 0;
  }
}

.section-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: #333;
  margin: 0 0 1.5rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #e0e0e0;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }
}

.form-group {
  margin-bottom: 1.5rem;

  .form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
  }

  .form-textarea,
  .form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.15s;

    &:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
  }

  .form-textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
  }

  .form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #6c757d;
    line-height: 1.4;
  }
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;

  input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
  }

  span {
    font-weight: 500;
    color: #333;
  }
}

.btn {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &--primary {
    background: #007bff;
    color: white;

    &:hover:not(:disabled) {
      background: #0056b3;
    }
  }

  &--ghost {
    background: transparent;
    color: #6c757d;
    border: 1px solid #ced4da;

    &:hover:not(:disabled) {
      background: #f8f9fa;
      color: #495057;
    }
  }

  &--sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
  }
}
</style>

