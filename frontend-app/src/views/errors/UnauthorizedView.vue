<template>
  <div class="error-container">
    <Card class="error-card">
      <template #content>
        <div class="error-content">
          <i class="pi pi-lock error-icon" style="color: #f59e0b"></i>
          <h1 class="error-code">401</h1>
          <p class="error-title">Unauthorized</p>
          <p class="error-message">You need to be authenticated to access this page. Please log in.</p>
          <div class="error-actions">
            <Button 
              label="Go to Login" 
              icon="pi pi-sign-in" 
              @click="router.push({ name: 'login' })"
              :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
            />
            <Button 
              label="Go Back" 
              icon="pi pi-arrow-left" 
              outlined
              @click="router.back()"
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
