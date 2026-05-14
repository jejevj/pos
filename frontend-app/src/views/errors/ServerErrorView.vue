<template>
  <div class="error-container">
    <Card class="error-card">
      <template #content>
        <div class="error-content">
          <i class="pi pi-server error-icon" style="color: #dc2626"></i>
          <h1 class="error-code">500</h1>
          <p class="error-title">Internal Server Error</p>
          <p class="error-message">Something went wrong on our end. Please try again later.</p>
          <div class="error-actions">
            <Button 
              label="Refresh Page" 
              icon="pi pi-refresh" 
              @click="refreshPage"
              :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
            />
            <Button 
              label="Back to Dashboard" 
              icon="pi pi-home" 
              outlined
              @click="router.push({ name: 'dashboard' })"
              :style="{ color: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
            />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'

const router = useRouter()

const refreshPage = () => {
  window.location.reload()
}
</script>

<style scoped>
.error-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #f8f9fa;
  padding: 1rem;
}

.error-card {
  max-width: 500px;
  width: 100%;
}

.error-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 2rem 1rem;
}

.error-icon {
  font-size: 5rem;
  margin-bottom: 1.5rem;
}

.error-code {
  font-size: 4rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
  line-height: 1;
}

.error-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 0.75rem 0;
}

.error-message {
  font-size: 1rem;
  color: #6b7280;
  margin: 0 0 2rem 0;
  max-width: 400px;
}

.error-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  justify-content: center;
}
</style>
