<template>
  <div class="view-container">
    <Card>
      <template #title>Reports</template>
      <template #content>
        <div class="content-wrapper">
          <div class="icon-wrapper">
            <i class="pi pi-chart-bar content-icon"></i>
          </div>
          <p class="placeholder-text">Reports interface coming soon...</p>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
</script>

<style scoped>
.view-container {
  max-width: 1400px;
  margin: 0 auto;
}

.content-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.icon-wrapper {
  width: 80px;
  height: 80px;
  background-color: rgba(107, 142, 127, 0.1);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.content-icon {
  font-size: 2.5rem;
  color: var(--sage-primary);
}

.placeholder-text {
  color: #6b7280;
  font-size: 1rem;
  margin: 0;
  text-align: center;
}
</style>
