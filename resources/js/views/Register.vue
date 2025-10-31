<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">A</div>
      
      <div class="auth-header">
        <h1>Создать аккаунт</h1>
        <p>Зарегистрируйтесь для доступа к панели</p>
      </div>

      <form @submit.prevent="handleSubmit" class="auth-form">
        <div class="form-group">
          <label for="name" class="form-label">Имя</label>
          <input
            id="name"
            v-model="formData.name"
            type="text"
            class="form-input"
            :class="{ error: errors.name }"
            placeholder="Ваше имя"
            required
          />
          <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
        </div>

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

        <div class="form-group">
          <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
          <input
            id="password_confirmation"
            v-model="formData.password_confirmation"
            type="password"
            class="form-input"
            :class="{ error: errors.password_confirmation }"
            placeholder="••••••••"
            required
          />
          <span v-if="errors.password_confirmation" class="form-error">{{ errors.password_confirmation }}</span>
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
            {{ loading ? 'Регистрация...' : 'Зарегистрироваться' }}
          </button>
        </div>
      </form>

      <div class="auth-footer">
        <p>Уже есть аккаунт? <router-link to="/login">Войти</router-link></p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { useAuth } from '@/composables/useAuth';
import type { RegisterData } from '@/types';

const { register, loading, error, clearError } = useAuth();

const formData = reactive<RegisterData>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const errors = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const validateForm = (): boolean => {
  errors.name = '';
  errors.email = '';
  errors.password = '';
  errors.password_confirmation = '';
  
  if (!formData.name) {
    errors.name = 'Имя обязательно';
    return false;
  }
  
  if (formData.name.length < 2) {
    errors.name = 'Имя должно содержать минимум 2 символа';
    return false;
  }
  
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
  
  if (!formData.password_confirmation) {
    errors.password_confirmation = 'Подтверждение пароля обязательно';
    return false;
  }
  
  if (formData.password !== formData.password_confirmation) {
    errors.password_confirmation = 'Пароли не совпадают';
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
    await register(formData);
  } catch (err: any) {
    console.error('Registration error:', err);
  }
};
</script>

