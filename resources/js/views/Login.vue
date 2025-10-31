<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useAuth } from '@/composables/useAuth';
import type { LoginCredentials } from '@/types';

const { login, loading, error, clearError } = useAuth();

const formData = reactive<LoginCredentials>({
  email: '',
  password: '',
  remember: false,
});

const errors = reactive({
  email: '',
  password: '',
});

const validateForm = (): boolean => {
  errors.email = '';
  errors.password = '';
  
  if (!formData.email) {
    errors.email = 'Email обязателен';
    return false;
  }
  
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Некорректный email';
    return false;
  }
  
  if (!formData.password) {
    errors.password = 'Пароль обязателен';
    return false;
  }
  
  if (formData.password.length < 8) {
    errors.password = 'Пароль должен содержать минимум 8 символов';
    return false;
  }
  
  return true;
};

const handleSubmit = async () => {
  clearError();
  
  if (!validateForm()) {
    return;
  }

  try {
    await login(formData);
  } catch (err: any) {
    console.error('Login error:', err);
  }
};
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <form @submit.prevent="handleSubmit" class="auth-form">
        <div class="form-group">
          <label for="email" class="form-label">Email</label>
          <input
            id="email"
            v-model="formData.email"
            type="email"
            class="form-input"
            :class="{ error: errors.email }"
            placeholder="your@email.com"
            required
          />
          <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Пароль</label>
          <input
            id="password"
            v-model="formData.password"
            type="password"
            class="form-input"
            :class="{ error: errors.password }"
            placeholder="••••••••"
            required
          />
          <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
        </div>

        <div class="form-checkbox">
          <input
            id="remember"
            v-model="formData.remember"
            type="checkbox"
          />
          <label for="remember">Запомнить меня</label>
        </div>

        <div v-if="error" class="form-error text-center mb-2">
          {{ error }}
        </div>

        <div class="form-actions">
          <button
            type="submit"
            class="btn btn--primary btn--block"
            :disabled="loading"
          >
            {{ loading ? 'Вход...' : 'Войти' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

