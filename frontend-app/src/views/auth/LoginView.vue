<template>
  <div class="login-container">
    <!-- Session Expired Dialog -->
    <Dialog 
      v-model:visible="sessionExpiredVisible" 
      :header="$t('auth.sessionExpired')"
      :modal="true"
      :closable="false"
      :style="{ width: '450px' }"
    >
      <div class="session-expired-content">
        <i class="pi pi-clock" style="font-size: 3rem; color: #f59e0b;"></i>
        <p class="session-message">{{ $t('auth.sessionExpiredMessage') }}</p>
        <p class="session-hint">{{ $t('auth.sessionExpiredHint') }}</p>
      </div>

      <template #footer>
        <Button 
          :label="$t('common.ok')" 
          @click="sessionExpiredVisible = false"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          autofocus
        />
      </template>
    </Dialog>

    <Card class="login-card">
      <template #header>
        <div class="card-header">
          <i class="pi pi-box login-icon"></i>
          <h1 class="login-title">Welcome Back</h1>
          <p class="login-subtitle">Sign in to continue to your account</p>
        </div>
      </template>

      <template #content>
        <form @submit.prevent="handleLogin" class="login-form">
          <div class="form-field">
            <FloatLabel>
              <InputText 
                id="login" 
                v-model="form.login" 
                type="text"
                :invalid="!!errors.login"
                autocomplete="username"
                fluid
                class="text-input"
              />
              <label for="login">Username / Email</label>
            </FloatLabel>
            <small v-if="errors.login" class="error-text">{{ errors.login }}</small>
          </div>

          <div class="form-field">
            <FloatLabel>
              <Password 
                id="password" 
                v-model="form.password"
                :feedback="false"
                toggleMask
                :invalid="!!errors.password"
                autocomplete="current-password"
                fluid
                inputClass="text-input"
              />
              <label for="password">{{ $t('auth.password') }}</label>
            </FloatLabel>
            <small v-if="errors.password" class="error-text">{{ errors.password }}</small>
          </div>

          <div class="remember-section">
            <div class="flex items-center gap-2">
              <Checkbox v-model="rememberMe" inputId="remember" :binary="true" />
              <label for="remember" class="remember-label">{{ $t('auth.rememberMe') }}</label>
            </div>
            <a href="#" class="forgot-link">{{ $t('auth.forgotPassword') }}</a>
          </div>

          <Message v-if="authStore.error" severity="error" :closable="false" class="error-message">
            {{ authStore.error }}
          </Message>

          <Button 
            type="submit" 
            :label="$t('auth.signIn')" 
            :loading="authStore.loading"
            icon="pi pi-sign-in"
            fluid
            class="login-button"
          />
        </form>
      </template>

      <template #footer>
        <div class="login-footer">
          <p class="footer-text">
            {{ $t('auth.dontHaveAccount') }}
            <router-link to="/register" class="register-link">{{ $t('auth.signUp') }}</router-link>
          </p>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { encodeOutletId } from '@/utils/outletId'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Checkbox from 'primevue/checkbox'
import FloatLabel from 'primevue/floatlabel'
import Dialog from 'primevue/dialog'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// Session expired dialog
const sessionExpiredVisible = ref(false)

// Check if session expired
onMounted(() => {
  const sessionExpired = localStorage.getItem('session_expired')
  if (sessionExpired === 'true') {
    sessionExpiredVisible.value = true
    localStorage.removeItem('session_expired')
  }
})

const form = ref({
  login: '',
  password: ''
})

const errors = ref({})
const rememberMe = ref(false)

const handleLogin = async () => {
  errors.value = {}
  
  if (!form.value.login) {
    errors.value.login = 'Username atau email wajib diisi'
    return
  }
  
  if (!form.value.password) {
    errors.value.password = 'Password wajib diisi'
    return
  }

  try {
    await authStore.login(form.value)
    
    // Setelah login berhasil, outletMemberships sudah di-populate via setAuth()
    // Cek apakah user ini adalah outlet user (bukan superadmin, punya membership)
    if (authStore.isOutletUser && authStore.outletMemberships.length > 0) {
      // Redirect ke dashboard outlet pertama yang dimiliki user (encoded ID)
      const firstOutlet = authStore.outletMemberships[0]
      const encoded = firstOutlet.encoded_outlet_id || encodeOutletId(firstOutlet.outlet_id)
      router.push(`/outlets/${encoded}/dashboard`)
    } else {
      // Superadmin atau user biasa — redirect ke admin dashboard atau halaman asal
      const redirectPath = route.query.redirect || (authStore.isSuperAdmin ? '/admin/dashboard' : '/dashboard')
      router.push(redirectPath)
    }
  } catch (error) {
    console.error('Login failed:', error)
  }
}
</script>

<style scoped>
.session-expired-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  text-align: center;
}

.session-message {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}

.session-hint {
  margin: 0;
  font-size: 0.95rem;
  color: #6b7280;
}

.login-container {
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

.login-card {
  width: 100%;
  max-width: 480px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-radius: 8px;
}

.card-header {
  text-align: center;
  padding: 2.5rem 2.5rem 1.5rem;
}

.login-icon {
  font-size: 3.5rem;
  color: var(--sage-primary);
}

.login-title {
  font-size: 1.75rem;
  font-weight: 700;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.login-subtitle {
  color: #6b7280;
  font-size: 0.95rem;
  margin: 0;
}

.login-form {
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

.remember-section {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.remember-label {
  cursor: pointer;
  font-size: 0.875rem;
  color: #4b5563;
  user-select: none;
}

.forgot-link {
  font-size: 0.875rem;
  color: var(--sage-primary);
  text-decoration: none;
  font-weight: 500;
}

.forgot-link:hover {
  color: var(--sage-hover);
  text-decoration: underline;
}

.error-message {
  width: 100%;
  margin-bottom: 1rem;
}

.login-button {
  width: 100%;
  background-color: var(--sage-primary);
  border-color: var(--sage-primary);
}

.login-button:hover {
  background-color: var(--sage-hover);
  border-color: var(--sage-hover);
}

.login-footer {
  text-align: center;
  padding: 1.25rem 2.5rem 1.75rem;
  border-top: 1px solid #e5e7eb;
}

.footer-text {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0;
}

.register-link {
  color: var(--sage-primary);
  font-weight: 600;
  text-decoration: none;
}

.register-link:hover {
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

/* Override PrimeVue checkbox color */
:deep(.p-checkbox-checked .p-checkbox-box) {
  background-color: var(--sage-primary);
  border-color: var(--sage-primary);
}

:deep(.p-checkbox:not(.p-disabled):has(.p-checkbox-input:hover) .p-checkbox-box) {
  border-color: var(--sage-primary);
}

:deep(.p-checkbox:not(.p-disabled):has(.p-checkbox-input:focus-visible) .p-checkbox-box) {
  border-color: var(--sage-primary);
  box-shadow: 0 0 0 0.2rem var(--sage-shadow);
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

/* FloatLabel alignment */
:deep(.p-float-label) {
  width: 100%;
}

/* Responsive */
@media (max-width: 640px) {
  .login-form {
    padding: 1.25rem 1.5rem 1.75rem;
  }

  .card-header {
    padding: 2rem 1.5rem 1.25rem;
  }

  .login-footer {
    padding: 1rem 1.5rem 1.5rem;
  }
}
</style>