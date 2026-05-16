<template>
  <div class="pml-page">
    <div class="pml-card">
      <div class="pml-header">
        <button class="back-btn" @click="goBack" aria-label="back">
          <i class="pi pi-arrow-left"></i>
        </button>
        <div class="title-block">
          <div class="brand">
            <img v-if="outletLogo" :src="outletLogo" :alt="outletName" class="brand-logo" />
            <div v-else class="brand-fallback"><i class="pi pi-shop"></i></div>
            <div>
              <div class="brand-name">{{ outletName || t('publicMemberLogin.fallbackOutlet') }}</div>
              <div class="brand-sub">{{ t('publicMemberLogin.subtitle') }}</div>
            </div>
          </div>
          <h1>{{ t('publicMemberLogin.title') }}</h1>
          <p class="hint">{{ t('publicMemberLogin.hint') }}</p>
        </div>
      </div>

      <form class="pml-form" @submit.prevent="submit">
        <div class="field">
          <label>{{ t('publicMemberLogin.identifierLabel') }} <span class="req">*</span></label>
          <input
            v-model="identifier"
            type="text"
            autocomplete="username"
            :placeholder="t('publicMemberLogin.identifierPh')"
            required
          />
          <small class="hint-line">{{ t('publicMemberLogin.identifierHint') }}</small>
        </div>

        <div class="field">
          <label>{{ t('publicMemberLogin.passwordLabel') }}</label>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            :placeholder="t('publicMemberLogin.passwordPh')"
          />
          <small class="hint-line">{{ t('publicMemberLogin.passwordHint') }}</small>
        </div>

        <p v-if="errorMsg" class="error">{{ errorMsg }}</p>
        <p v-if="successMsg" class="success">{{ successMsg }}</p>

        <button class="primary big" type="submit" :disabled="submitting">
          <i class="pi" :class="submitting ? 'pi-spin pi-spinner' : 'pi-sign-in'"></i>
          {{ submitting ? t('publicMemberLogin.loggingIn') : t('publicMemberLogin.cta') }}
        </button>

        <div class="divider"><span>{{ t('publicMemberLogin.or') }}</span></div>

        <a class="ghost-btn" :href="`/m/${outletSlug}`">
          <i class="pi pi-user-plus"></i>
          {{ t('publicMemberLogin.registerCta') }}
        </a>
        <button type="button" class="text-btn" @click="goBack">
          <i class="pi pi-arrow-left"></i>
          {{ t('publicMemberLogin.continueGuest') }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const outletSlug = route.params.outletSlug
const tableToken = route.query.t || ''
const returnTo   = route.query.return || ''

const identifier = ref('')
const password = ref('')
const submitting = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const outletLogo = ref('')
const outletName = ref('')

const STORAGE_KEY = computed(() => `pos_member_${outletSlug}`)

onMounted(async () => {
  // Best-effort: pull outlet branding (so the login page feels consistent with
  // the order page the customer just came from). Falls back silently.
  try {
    const res = await api.get(`/public/membership/${outletSlug}`)
    outletLogo.value = res.data?.outlet?.logo || ''
    outletName.value = res.data?.outlet?.name || ''
  } catch (e) {
    // ignore — page still renders
  }
})

function goBack() {
  if (returnTo) {
    router.replace(returnTo)
    return
  }
  if (tableToken) {
    router.replace({ name: 'public-table-order', params: { outletSlug, tableToken } })
  } else {
    router.replace({ name: 'public-takeaway-order', params: { outletSlug } })
  }
}

async function submit() {
  errorMsg.value = ''
  successMsg.value = ''
  if (!identifier.value.trim()) {
    errorMsg.value = t('publicMemberLogin.errIdentifier')
    return
  }
  submitting.value = true
  try {
    const res = await api.post(`/public/outlet/${outletSlug}/member/login`, {
      identifier: identifier.value.trim(),
      password: password.value || '',
    })
    const member = res.data?.member
    if (member && member.id) {
      // Persist member identity on this device so the order page can attribute
      // the next order to this member.
      localStorage.setItem(STORAGE_KEY.value, JSON.stringify(member))
      successMsg.value = t('publicMemberLogin.success', { name: member.nama })
      setTimeout(() => goBack(), 600)
    } else {
      errorMsg.value = t('publicMemberLogin.errUnknown')
    }
  } catch (e) {
    errorMsg.value =
      e.response?.data?.message || t('publicMemberLogin.errUnknown')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.pml-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #eef2ff 0%, #faf5ff 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px 16px;
  font-family: 'Inter', -apple-system, sans-serif;
}
.pml-card {
  width: 100%;
  max-width: 440px;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 10px 30px rgba(99, 102, 241, 0.15);
  padding: 24px 22px 26px;
}
.pml-header { margin-bottom: 16px; }
.back-btn {
  background: #f3f4f6;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  color: #374151;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 10px;
}
.title-block h1 {
  font-size: 22px;
  margin: 12px 0 4px;
  color: #1a1a1a;
}
.title-block .hint {
  color: #6b7280;
  font-size: 13px;
  margin: 0;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-logo {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
}
.brand-fallback {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}
.brand-name { font-weight: 700; font-size: 14px; }
.brand-sub  { font-size: 11px; color: #6b7280; }

.pml-form { display: flex; flex-direction: column; gap: 12px; }
.field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}
.field input {
  width: 100%;
  padding: 11px 12px;
  font-size: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
}
.field input:focus {
  outline: none;
  border-color: #6366f1;
  background: #fff;
}
.field .req { color: #ef4444; }
.field .hint-line {
  display: block;
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
}
.primary {
  background: #6366f1;
  color: #fff;
  border: none;
  padding: 12px 18px;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
}
.primary:disabled { opacity: 0.6; cursor: not-allowed; }
.primary.big { width: 100%; padding: 14px; font-size: 15px; }

.divider {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0;
  color: #9ca3af;
  font-size: 11px;
}
.divider::before, .divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}
.ghost-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border-radius: 10px;
  text-decoration: none;
  color: #6366f1;
  background: #eef2ff;
  font-weight: 600;
  font-size: 14px;
}
.text-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #6b7280;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 6px;
}
.error {
  color: #ef4444;
  font-size: 13px;
  margin: 4px 0 0;
}
.success {
  color: #16a34a;
  font-size: 13px;
  margin: 4px 0 0;
}
</style>
