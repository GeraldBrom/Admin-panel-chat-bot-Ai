<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuth } from '@/composables/useAuth';

const { logout } = useAuth();

const handleLogout = async () => {
  if (confirm('Вы уверены, что хотите выйти?')) {
    await logout();
  }
};

// Состояние для dropdown меню
const isChatsDropdownOpen = ref(false);
const isManagementDropdownOpen = ref(false);

const toggleChatsDropdown = () => {
  isChatsDropdownOpen.value = !isChatsDropdownOpen.value;
  isManagementDropdownOpen.value = false;
};

const toggleManagementDropdown = () => {
  isManagementDropdownOpen.value = !isManagementDropdownOpen.value;
  isChatsDropdownOpen.value = false;
};

const closeAllDropdowns = () => {
  isChatsDropdownOpen.value = false;
  isManagementDropdownOpen.value = false;
};

// Закрытие dropdown при клике вне меню
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.nav-dropdown')) {
    closeAllDropdowns();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div class="main-layout">
    <header class="main-header">
      <div class="container">
        <div class="header-content">
          <nav class="header-nav">
            <router-link to="/dashboard" active-class="active">Главная</router-link>
            
            <!-- Dropdown: Чат-боты -->
            <div class="nav-dropdown" @click="toggleChatsDropdown">
              <span class="nav-dropdown__trigger">
                Чат-боты
                <svg class="nav-dropdown__icon" :class="{ 'nav-dropdown__icon--open': isChatsDropdownOpen }" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
              <div class="nav-dropdown__menu" v-show="isChatsDropdownOpen">
                <router-link to="/chat-bots" active-class="active" @click.stop="closeAllDropdowns">Чат боты Ai</router-link>
                <router-link to="/scenario-bot-sessions" active-class="active" @click.stop="closeAllDropdowns">Сессии сценарных ботов</router-link>
                <router-link to="/chatkit-sessions" active-class="active" @click.stop="closeAllDropdowns">ChatKit сессии</router-link>
              </div>
            </div>
            
            <!-- Dropdown: Управление ботами -->
            <div class="nav-dropdown" @click="toggleManagementDropdown">
              <span class="nav-dropdown__trigger">
                Управление ботами
                <svg class="nav-dropdown__icon" :class="{ 'nav-dropdown__icon--open': isManagementDropdownOpen }" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
              <div class="nav-dropdown__menu" v-show="isManagementDropdownOpen">
                <router-link to="/bot-management-ai" active-class="active" @click.stop="closeAllDropdowns">WhatsApp Ai</router-link>
                <router-link to="/scenario-bot-management" active-class="active" @click.stop="closeAllDropdowns">Сценарий сценарных ботов</router-link>
              </div>
            </div>
            
            <router-link to="/logs" active-class="active">Логи</router-link>
          </nav>
          
          <div class="header-user">
            <button @click="handleLogout" class="btn btn--ghost btn--sm">
              Выход
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="main-content">
      <slot />
    </main>
  </div>
</template>

