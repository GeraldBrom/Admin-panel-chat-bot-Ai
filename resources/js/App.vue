<template>
  <div id="app" class="admin-panel">
    <header class="header">
      <div class="container">
        <h1>Админ-панель управления чат-ботами</h1>
        <p>Добро пожаловать в систему управления</p>
      </div>
    </header>
    
    <main class="main-content">
      <div class="container">
        <div class="dashboard">
          <div class="stats-grid">
            <div class="stat-card">
              <h3>Всего ботов</h3>
              <p class="stat-number">0</p>
            </div>
            <div class="stat-card">
              <h3>Активных</h3>
              <p class="stat-number">0</p>
            </div>
            <div class="stat-card">
              <h3>Сообщений</h3>
              <p class="stat-number">0</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
// Vue 3 Composition API
console.log('Admin panel initialized');
</script>

<style scoped>
.admin-panel {
  min-height: 100vh;
  background: #f5f7fa;
}

.header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2rem 0;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2rem;
  font-weight: 700;
}

.header p {
  margin: 0;
  font-size: 1.1rem;
  opacity: 0.9;
}

.main-content {
  padding: 2rem 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.stat-card {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-card h3 {
  margin: 0 0 0.5rem 0;
  color: #64748b;
  font-size: 0.9rem;
  text-transform: uppercase;
  font-weight: 600;
}

.stat-number {
  margin: 0;
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
}
</style>
