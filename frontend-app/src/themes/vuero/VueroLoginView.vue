<template>
  <div class="modernize-login">

    <!-- Session expired dialog -->
    <Dialog
      v-model:visible="sessionExpiredVisible"
      :header="$t('auth.sessionExpired')"
      modal :closable="false" style="width:420px"
    >
      <div class="session-expired-content">
        <i class="pi pi-clock" style="font-size:2.5rem;color:#f59e0b"></i>
        <p class="session-message">{{ $t('auth.sessionExpiredMessage') }}</p>
        <p class="session-hint">{{ $t('auth.sessionExpiredHint') }}</p>
      </div>
      <template #footer>
        <Button :label="$t('common.ok')" @click="sessionExpiredVisible = false" />
      </template>
    </Dialog>

    <!-- Left panel — illustration -->
    <div class="left-panel">
      <div class="left-inner">
        <div class="brand">
          <i class="pi pi-box brand-icon"></i>
          <span class="brand-name">SaaS App</span>
        </div>
        <img src="/login-bg.svg" alt="Login illustration" class="login-illustration" />
      </div>
    </div>

    <!-- Right panel — form -->
    <div class="right-panel">
      <div class="form-wrap">

        <h2 class="welcome-title">Welcome to SaaS App</h2>
        <p class="welcome-sub">Your Admin Dashboard</p>

        <!-- Email -->
        <div class="field-group">
          <label class="field-label">{{ $t('auth.email') }}</label>
          <div class="input-wrap">
            <input
              v-model="form.email"
              type="email"
              class="mod-input"
              :class="{ error: errors.email }"
              :placeholder="$t('auth.email')"
              autocomplete="email"
            />
          </div>
          <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
        </div>

        <!-- Password -->
        <div class="field-group">
          <label class="field-label">{{ $t('auth.password') }}</label>
          <div class="input-wrap">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="mod-input"
              :class="{ error: errors.password }"
              :placeholder="$t('auth.password')"
              autocomplete="current-password"
            />
            <button type="button" class="eye-btn" @click="showPassword = !showPassword">
              <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
            </button>
          </div>
          <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
        </div>

        <!-- Remember + Forgot -->
        <div class="options-row">
          <label class="remember-label">
            <input v-model="rememberMe" type="checkbox" class="mod-checkbox" />
            <span>{{ $t('auth.rememberMe') }}</span>
          </label>
          <a href="#" class="forgot-link">{{ $t('auth.forgotPassword') }}</a>
        </div>

        <!-- Error -->
        <div v-if="authStore.error" class="error-alert">
          <i class="pi pi-exclamation-circle"></i>
          {{ authStore.error }}
        </div>

        <!-- Submit -->
        <button
          class="submit-btn"
          :disabled="authStore.loading"
          @click="handleLogin"
        >
          <i v-if="authStore.loading" class="pi pi-spin pi-spinner"></i>
          <span v-else>{{ $t('auth.signIn') }}</span>
        </button>

        <!-- Register link -->
        <p class="register-row">
          {{ $t('auth.dontHaveAccount') }}
          <router-link to="/register" class="register-link">{{ $t('auth.signUp') }}</router-link>
        </p>

      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()
const { t }     = useI18n()

const showPassword          = ref(false)
const rememberMe            = ref(false)
const sessionExpiredVisible = ref(false)
const form   = ref({ email: '', password: '' })
const errors = ref({})

onMounted(() => {
  if (localStorage.getItem('session_expired') === 'true') {
    sessionExpiredVisible.value = true
    localStorage.removeItem('session_expired')
  }
})

const handleLogin = async () => {
  errors.value = {}
  if (!form.value.email)    { errors.value.email    = 'Email is required';    return }
  if (!form.value.password) { errors.value.password = 'Password is required'; return }
  try {
    await authStore.login(form.value)
    router.push(route.query.redirect || '/dashboard')
  } catch (e) {
    console.error('Login failed:', e)
  }
}
</script>

<style scoped>
/* ── Layout ───────────────────────────────────────────────────────────────── */
.modernize-login {
  display: flex;
  min-height: 100vh;
  background: #f5f5f5;
}

/* ── Left panel ───────────────────────────────────────────────────────────── */
.left-panel {
  flex: 1;
  background: linear-gradient(135deg, #1a97f5 0%, #0d6efd 100%);
  display: none;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
@media (min-width: 1024px) {
  .left-panel { display: flex; }
}

.left-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
  padding: 3rem;
  width: 100%;
  max-width: 560px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  align-self: flex-start;
}
.brand-icon { font-size: 2rem; color: white; }
.brand-name { font-size: 1.5rem; font-weight: 800; color: white; letter-spacing: -0.02em; }

.login-illustration {
  width: 100%;
  max-width: 480px;
  filter: drop-shadow(0 20px 40px rgba(0,0,0,0.15));
}

/* ── Right panel ──────────────────────────────────────────────────────────── */
.right-panel {
  width: 100%;
  max-width: 480px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  padding: 2rem;
}
@media (min-width: 1024px) {
  .right-panel { min-width: 420px; }
}

.form-wrap {
  width: 100%;
  max-width: 380px;
}

.welcome-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #2a3547;
  margin: 0 0 0.4rem;
  letter-spacing: -0.02em;
}
.welcome-sub {
  color: #7c8fac;
  font-size: 0.95rem;
  margin: 0 0 2rem;
}

/* ── Fields ───────────────────────────────────────────────────────────────── */
.field-group { margin-bottom: 1.25rem; }
.field-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #2a3547;
  margin-bottom: 0.4rem;
}
.input-wrap { position: relative; }
.mod-input {
  width: 100%;
  height: 48px;
  padding: 0 3rem 0 1rem;
  border: 1.5px solid #e5eaef;
  border-radius: 8px;
  font-size: 0.95rem;
  color: #2a3547;
  background: #f8fafc;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.mod-input:focus {
  border-color: #1a97f5;
  box-shadow: 0 0 0 3px rgba(26,151,245,0.12);
  background: #fff;
}
.mod-input.error { border-color: #fa896b; }
.field-error { color: #fa896b; font-size: 0.8rem; margin-top: 0.3rem; display: block; }

.eye-btn {
  position: absolute;
  right: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: #7c8fac; font-size: 1rem; padding: 0;
  transition: color 0.2s;
}
.eye-btn:hover { color: #2a3547; }

/* ── Options row ──────────────────────────────────────────────────────────── */
.options-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.remember-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #5a6a85;
  cursor: pointer;
}
.mod-checkbox { accent-color: #1a97f5; width: 15px; height: 15px; }
.forgot-link {
  font-size: 0.875rem;
  color: #1a97f5;
  text-decoration: none;
  font-weight: 500;
}
.forgot-link:hover { text-decoration: underline; }

/* ── Error alert ──────────────────────────────────────────────────────────── */
.error-alert {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fff5f2;
  border: 1px solid #ffd5c8;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  color: #fa896b;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

/* ── Submit ───────────────────────────────────────────────────────────────── */
.submit-btn {
  width: 100%;
  height: 48px;
  background: #1a97f5;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s, box-shadow 0.2s;
  box-shadow: 0 6px 20px rgba(26,151,245,0.35);
  letter-spacing: 0.01em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.submit-btn:hover:not(:disabled) {
  background: #0d6efd;
  box-shadow: 0 8px 24px rgba(26,151,245,0.45);
}
.submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* ── Register row ─────────────────────────────────────────────────────────── */
.register-row {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 0.875rem;
  color: #7c8fac;
}
.register-link {
  color: #1a97f5;
  font-weight: 600;
  text-decoration: none;
  margin-left: 0.25rem;
}
.register-link:hover { text-decoration: underline; }

/* ── Session expired ──────────────────────────────────────────────────────── */
.session-expired-content {
  display: flex; flex-direction: column;
  align-items: center; gap: 0.75rem;
  padding: 1rem; text-align: center;
}
.session-message { margin: 0; font-weight: 600; color: #2a3547; }
.session-hint { margin: 0; color: #7c8fac; font-size: 0.9rem; }

/* ── Mobile ───────────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
  .modernize-login { background: #fff; }
  .right-panel { max-width: 100%; padding: 3rem 1.5rem; }
}
</style>
