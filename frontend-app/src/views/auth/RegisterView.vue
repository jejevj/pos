<template>
  <div class="register-container">
    <Card class="register-card">
      <template #header>
        <div class="card-header">
          <i class="pi pi-user-plus register-icon"></i>
          <h1 class="register-title">Create Account</h1>
          <p class="register-subtitle">Join us and start your journey</p>
        </div>
      </template>

      <template #content>
        <form @submit.prevent="handleRegister" class="register-form">
          <div class="form-field">
            <FloatLabel>
              <InputText 
                id="name" 
                v-model="form.name"
                :invalid="!!errors.name"
                autocomplete="name"
                fluid
                class="text-input"
              />
              <label for="name">{{ $t('auth.name') }}</label>
            </FloatLabel>
            <small v-if="errors.name" class="error-text">{{ errors.name }}</small>
          </div>

          <div class="form-field">
            <FloatLabel>
              <InputText 
                id="email" 
                v-model="form.email" 
                type="email"
                :invalid="!!errors.email"
                autocomplete="email"
                fluid
                class="text-input"
              />
              <label for="email">{{ $t('auth.email') }}</label>
            </FloatLabel>
            <small v-if="errors.email" class="error-text">{{ errors.email }}</small>
          </div>

          <div class="form-field">
            <FloatLabel>
              <Password 
                id="password" 
                v-model="form.password"
                toggleMask
                :invalid="!!errors.password"
                autocomplete="new-password"
                fluid
                inputClass="text-input"
              >
                <template #footer>
                  <Divider />
                  <p class="font-semibold text-sm mb-2">Password Requirements</p>
                  <ul class="pl-5 text-sm list-disc password-requirements">
                    <li>At least one lowercase letter</li>
                    <li>At least one uppercase letter</li>
                    <li>At least one number</li>
                    <li>Minimum 8 characters</li>
                  </ul>
                </template>
              </Password>
              <label for="password">{{ $t('auth.password') }}</label>
            </FloatLabel>
            <small v-if="errors.password" class="error-text">{{ errors.password }}</small>
          </div>

          <div class="form-field">
            <FloatLabel>
              <Password 
                id="password_confirmation" 
                v-model="form.password_confirmation"
                :feedback="false"
                toggleMask
                :invalid="!!errors.password_confirmation"
                autocomplete="new-password"
                fluid
                inputClass="text-input"
              />
              <label for="password_confirmation">{{ $t('auth.confirmPassword') }}</label>
            </FloatLabel>
            <small v-if="errors.password_confirmation" class="error-text">{{ errors.password_confirmation }}</small>
          </div>

          <Message v-if="authStore.error" severity="error" :closable="false" class="error-message">
            {{ authStore.error }}
          </Message>

          <Button 
            type="submit" 
            :label="$t('auth.signUp')" 
            :loading="authStore.loading"
            icon="pi pi-user-plus"
            fluid
            class="register-button"
          />
        </form>
      </template>

      <template #footer>
        <div class="register-footer">
          <p class="footer-text">
            {{ $t('auth.alreadyHaveAccount') }}
            <router-link to="/login" class="login-link">{{ $t('auth.signIn') }}</router-link>
          </p>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import FloatLabel from 'primevue/floatlabel'
import Divider from 'primevue/divider'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const errors = ref({})

const handleRegister = async () => {
  errors.value = {}
  
  if (!form.value.name) {
    errors.value.name = 'Name is required'
    return
  }
  
  if (!form.value.email) {
    errors.value.email = 'Email is required'
    return
  }
  
  if (!form.value.password) {
    errors.value.password = 'Password is required'
    return
  }

  if (form.value.password !== form.value.password_confirmation) {
    errors.value.password_confirmation = 'Passwords do not match'
    return
  }

  try {
    await authStore.register(form.value)
    router.push({ name: 'dashboard' })
  } catch (error) {
    console.error('Registration failed:', error)
  }
}
</script>

<style scoped>
.register-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  width: 100%;
  background-color: #f8f9fa;
  padding: 1.5rem;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: auto;
}

.register-card {
  width: 100%;
  max-width: 480px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-radius: 8px;
  margin: 2rem 0;
}

.card-header {
  text-align: center;
  padding: 2.5rem 2.5rem 1.5rem;
}

.register-icon {
  font-size: 3.5rem;
  color: var(--sage-primary);
}

.register-title {
  font-size: 1.75rem;
  font-weight: 700;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.register-subtitle {
  color: #6b7280;
  font-size: 0.95rem;
  margin: 0;
}

.register-form {
  padding: 1.5rem 2.5rem 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.form-field {
  width: 100%;
  margin-bottom: 1.75rem;
}

.error-text {
  color: #ef4444;
  display: block;
  margin-top: 0.5rem;
  font-size: 0.875rem;
  text-align: left;
}

.password-requirements {
  color: #6b7280;
}

.error-message {
  width: 100%;
  margin-bottom: 1rem;
}

.register-button {
  width: 100%;
  background-color: var(--sage-primary);
  border-color: var(--sage-primary);
}

.register-button:hover {
  background-color: var(--sage-hover);
  border-color: var(--sage-hover);
}

.register-footer {
  text-align: center;
  padding: 1.25rem 2.5rem 1.75rem;
  border-top: 1px solid #e5e7eb;
}

.footer-text {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0;
}

.login-link {
  color: var(--sage-primary);
  font-weight: 600;
  text-decoration: none;
}

.login-link:hover {
  color: var(--sage-hover);
  text-decoration: underline;
}

/* Standard input size for all inputs */
:deep(.p-inputtext) {
  font-size: 1rem;
  padding: 0.75rem 1rem;
  height: 3rem;
}

/* Input text padding fix */
:deep(.text-input) {
  padding-left: 1rem !important;
  padding-right: 1rem !important;
}

/* Password input wrapper fix */
:deep(.p-password) {
  width: 100%;
}

:deep(.p-password-input) {
  width: 100%;
  padding-left: 1rem !important;
  padding-right: 3rem !important; /* Extra space for eye icon */
  height: 3rem;
}

/* Fix password toggle icon position */
:deep(.p-password-toggle-icon) {
  right: 1rem !important;
  font-size: 1rem;
}

/* Standard button size */
:deep(.p-button) {
  font-size: 1rem;
  padding: 0.75rem 1.25rem;
  height: 3rem;
}

:deep(.p-button .p-button-label) {
  font-weight: 600;
}

/* Override PrimeVue input focus color */
:deep(.p-inputtext:enabled:focus) {
  border-color: var(--sage-primary);
  box-shadow: 0 0 0 0.2rem var(--sage-shadow);
}

:deep(.p-password-input:enabled:focus) {
  border-color: var(--sage-primary);
  box-shadow: 0 0 0 0.2rem var(--sage-shadow);
}

/* Override password strength meter colors */
:deep(.p-password-meter) {
  background: #e5e7eb;
}

:deep(.p-password-strength-weak) {
  background: #ef4444;
}

:deep(.p-password-strength-medium) {
  background: #f59e0b;
}

:deep(.p-password-strength-strong) {
  background: var(--sage-primary);
}

/* FloatLabel alignment */
:deep(.p-float-label) {
  width: 100%;
}

/* Responsive */
@media (max-width: 640px) {
  .register-form {
    padding: 1.25rem 1.5rem 1.75rem;
  }

  .card-header {
    padding: 2rem 1.5rem 1.25rem;
  }

  .register-footer {
    padding: 1rem 1.5rem 1.5rem;
  }
}
</style>
