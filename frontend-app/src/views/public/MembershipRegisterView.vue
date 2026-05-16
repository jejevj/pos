<template>
  <div class="membership-page" :style="cssVars">
    <!-- Loading -->
    <div v-if="loading" class="state-center">
      <div class="spinner"></div>
      <p>Memuat halaman...</p>
    </div>

    <!-- Error: outlet not found -->
    <div v-else-if="loadError" class="state-center">
      <div class="state-icon error">
        <i class="pi pi-exclamation-triangle"></i>
      </div>
      <h2>Halaman tidak ditemukan</h2>
      <p>{{ loadError }}</p>
    </div>

    <!-- Registration closed -->
    <div v-else-if="!settings.registration_open && !success" class="state-center closed">
      <div class="state-icon">
        <i class="pi pi-lock"></i>
      </div>
      <h2>Pendaftaran Member Sementara Ditutup</h2>
      <p>Hubungi kami untuk informasi lebih lanjut.</p>
      <p v-if="outlet.phone" class="contact"><i class="pi pi-phone"></i> {{ outlet.phone }}</p>
    </div>

    <!-- Main content -->
    <div v-else class="content">
      <!-- Hero -->
      <section class="hero">
        <div class="hero-inner">
          <div class="logo-wrap">
            <img v-if="heroLogo" :src="heroLogo" :alt="outlet.name" class="logo" />
            <div v-else class="logo-fallback">
              <i class="pi pi-star-fill"></i>
            </div>
          </div>
          <div class="outlet-name">{{ outlet.name }}</div>
          <h1 class="page-title">{{ settings.page_title }}</h1>
          <p v-if="settings.page_description" class="page-desc">
            {{ settings.page_description }}
          </p>
        </div>
      </section>

      <!-- Benefits -->
      <section v-if="!success && settings.benefits && settings.benefits.length" class="benefits">
        <div class="benefits-grid">
          <div
            v-for="(b, i) in settings.benefits"
            :key="i"
            class="benefit"
            :style="{ animationDelay: (i * 60) + 'ms' }"
          >
            <i class="pi pi-check-circle"></i>
            <span>{{ b }}</span>
          </div>
        </div>
      </section>

      <!-- Form / Success -->
      <section class="form-section">
        <div class="form-card">
          <!-- Success state -->
          <div v-if="success" class="success-box">
            <div class="check-anim">
              <i class="pi pi-check"></i>
            </div>
            <h2>Selamat, {{ successData.name }}!</h2>
            <p class="success-sub">Anda berhasil terdaftar sebagai member.</p>

            <div class="code-box">
              <div class="code-label">Nomor Member Anda</div>
              <div class="code-row">
                <code>{{ successData.member_code }}</code>
                <button class="copy-btn" @click="copyCode" :aria-label="'Copy ' + successData.member_code">
                  <i :class="copied ? 'pi pi-check' : 'pi pi-copy'"></i>
                </button>
              </div>
            </div>

            <p v-if="settings.welcome_message" class="welcome-msg">
              {{ settings.welcome_message }}
            </p>

            <div class="status-pill" :class="successData.status === 'active' ? 'ok' : 'pending'">
              <i :class="successData.status === 'active' ? 'pi pi-check-circle' : 'pi pi-clock'"></i>
              Status: {{ successData.status === 'active' ? 'Aktif' : 'Menunggu Persetujuan' }}
            </div>
          </div>

          <!-- Form state: Step 1 — profile + send OTP -->
          <form v-else-if="step === 'profile'" @submit.prevent="submitProfile" class="form" novalidate>
            <div class="step-indicator">
              <span class="step active">1. Data Diri</span>
              <span class="step">2. Verifikasi OTP</span>
              <span class="step">3. Password</span>
            </div>

            <div class="field">
              <label for="name">Nama Lengkap <span class="req">*</span></label>
              <input id="name" v-model="form.name" type="text"
                placeholder="Nama sesuai identitas" required autocomplete="name" />
            </div>

            <div class="field">
              <label for="phone">
                No. WhatsApp <span class="req">*</span>
              </label>
              <input id="phone" v-model="form.phone" type="tel"
                placeholder="0812xxxxxxxx" required autocomplete="tel" inputmode="tel" />
              <small class="field-hint">Kami akan mengirim kode OTP ke nomor WhatsApp ini.</small>
            </div>

            <div class="field">
              <label for="email">Email <span class="optional">(opsional)</span></label>
              <input id="email" v-model="form.email" type="email"
                placeholder="email@contoh.com" autocomplete="email" />
            </div>

            <div v-if="settings.require_address" class="field">
              <label for="address">Alamat <span class="req">*</span></label>
              <textarea id="address" v-model="form.address" rows="3"
                placeholder="Alamat lengkap" required></textarea>
            </div>

            <div v-if="formError" class="form-error">
              <i class="pi pi-exclamation-circle"></i>
              <span>{{ formError }}</span>
            </div>

            <button type="submit" class="submit-btn" :disabled="submitting">
              <span v-if="!submitting">KIRIM KODE OTP</span>
              <span v-else class="btn-loading"><span class="dot-spin"></span> Mengirim OTP...</span>
            </button>
          </form>

          <!-- Step 2 — verify OTP + set password -->
          <form v-else-if="step === 'verify'" @submit.prevent="submitVerify" class="form" novalidate>
            <div class="step-indicator">
              <span class="step done">1. Data Diri</span>
              <span class="step active">2. Verifikasi OTP</span>
              <span class="step active">3. Password</span>
            </div>

            <div class="otp-banner">
              <i class="pi pi-whatsapp"></i>
              <div>
                <div class="otp-banner-title">Kode OTP dikirim via WhatsApp</div>
                <div class="otp-banner-sub">Ke nomor <strong>{{ maskedPhone }}</strong>. Berlaku 10 menit.</div>
              </div>
            </div>

            <div class="field">
              <label for="otp">Kode OTP (6 digit) <span class="req">*</span></label>
              <input id="otp" v-model="form.code" type="text" inputmode="numeric"
                maxlength="6" pattern="\d{6}" placeholder="000000" required
                autocomplete="one-time-code" class="otp-input" />
            </div>

            <div class="field">
              <label for="pwd">Password Baru <span class="req">*</span></label>
              <input id="pwd" v-model="form.password" type="password" minlength="6"
                placeholder="Minimal 6 karakter" required autocomplete="new-password" />
            </div>

            <div class="field">
              <label for="pwd2">Konfirmasi Password <span class="req">*</span></label>
              <input id="pwd2" v-model="form.password_confirmation" type="password" minlength="6"
                placeholder="Ketik ulang password" required autocomplete="new-password" />
            </div>

            <div v-if="formError" class="form-error">
              <i class="pi pi-exclamation-circle"></i>
              <span>{{ formError }}</span>
            </div>

            <button type="submit" class="submit-btn" :disabled="submitting">
              <span v-if="!submitting">VERIFIKASI &amp; DAFTAR</span>
              <span v-else class="btn-loading"><span class="dot-spin"></span> Memproses...</span>
            </button>

            <div class="otp-actions">
              <button type="button" class="link-btn" :disabled="resendCooldown > 0 || submitting" @click="resendOtp">
                <i class="pi pi-refresh"></i>
                {{ resendCooldown > 0 ? `Kirim ulang dalam ${resendCooldown}d` : 'Kirim ulang OTP' }}
              </button>
              <button type="button" class="link-btn" @click="backToProfile" :disabled="submitting">
                <i class="pi pi-arrow-left"></i> Ubah nomor
              </button>
            </div>
          </form>
        </div>

        <p v-if="!success" class="hint">
          Sudah punya akun member? Tunjukkan kartu member Anda ke kasir.
        </p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const apiBase = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/+$/, '')
const outletSlug = String(route.params.outletSlug || '')

const loading = ref(true)
const loadError = ref('')
const outlet = ref({ name: '', logo: '', address: '', phone: '' })
const settings = ref({
  registration_open: false,
  page_title: 'Daftar Member',
  page_description: '',
  benefits: [],
  welcome_message: '',
  require_phone: true,
  require_address: false,
  custom_primary_color: '',
  custom_logo_url: '',
})

const form = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  code: '',
  password: '',
  password_confirmation: '',
})
const step = ref('profile')           // 'profile' | 'verify'
const submitting = ref(false)
const formError = ref('')
const success = ref(false)
const successData = ref({ name: '', member_code: '', status: 'active' })
const copied = ref(false)
const verifiedPhone = ref('')
const resendCooldown = ref(0)
let resendTimer = null

const maskedPhone = computed(() => {
  const p = verifiedPhone.value
  if (!p) return ''
  if (p.length <= 4) return p
  return p.slice(0, 3) + '*'.repeat(Math.max(0, p.length - 6)) + p.slice(-3)
})

function startCooldown(seconds) {
  resendCooldown.value = Math.max(0, Math.floor(seconds || 0))
  if (resendTimer) clearInterval(resendTimer)
  resendTimer = setInterval(() => {
    resendCooldown.value -= 1
    if (resendCooldown.value <= 0) {
      clearInterval(resendTimer)
      resendTimer = null
      resendCooldown.value = 0
    }
  }, 1000)
}

const heroLogo = computed(() => settings.value.custom_logo_url || outlet.value.logo || '')

const cssVars = computed(() => {
  const c = (settings.value.custom_primary_color || '').trim()
  if (!c) return {}
  return {
    '--brand-1': c,
    '--brand-2': c,
  }
})

async function fetchPage() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await axios.get(`${apiBase}/public/membership/${encodeURIComponent(outletSlug)}`)
    outlet.value = data.outlet || outlet.value
    settings.value = { ...settings.value, ...(data.settings || {}) }
  } catch (e) {
    if (e.response?.status === 404) {
      loadError.value = 'Outlet ini tidak tersedia.'
    } else {
      loadError.value = 'Gagal memuat halaman. Coba lagi nanti.'
    }
  } finally {
    loading.value = false
  }
}

async function submitProfile() {
  if (submitting.value) return
  formError.value = ''

  if (!form.value.name.trim()) {
    formError.value = 'Nama wajib diisi'
    return
  }
  if (!form.value.phone.trim()) {
    formError.value = 'Nomor WhatsApp wajib diisi'
    return
  }
  if (settings.value.require_address && !form.value.address.trim()) {
    formError.value = 'Alamat wajib diisi'
    return
  }

  submitting.value = true
  try {
    const { data } = await axios.post(
      `${apiBase}/public/membership/${encodeURIComponent(outletSlug)}/otp/request`,
      {
        name: form.value.name,
        phone: form.value.phone,
        email: form.value.email || null,
        address: form.value.address || null,
      }
    )
    verifiedPhone.value = data?.phone || form.value.phone
    step.value = 'verify'
    startCooldown(60)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal mengirim OTP. Coba lagi.'
  } finally {
    submitting.value = false
  }
}

async function submitVerify() {
  if (submitting.value) return
  formError.value = ''

  if (!/^\d{6}$/.test(String(form.value.code || ''))) {
    formError.value = 'Kode OTP harus 6 digit angka'
    return
  }
  if ((form.value.password || '').length < 6) {
    formError.value = 'Password minimal 6 karakter'
    return
  }
  if (form.value.password !== form.value.password_confirmation) {
    formError.value = 'Konfirmasi password tidak sama'
    return
  }

  submitting.value = true
  try {
    const { data } = await axios.post(
      `${apiBase}/public/membership/${encodeURIComponent(outletSlug)}/otp/verify`,
      {
        phone: verifiedPhone.value || form.value.phone,
        code: form.value.code,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
      }
    )
    const m = data?.member || {}
    successData.value = {
      name: m.name || form.value.name,
      member_code: m.member_code || m.card_number || '',
      status: m.status || 'active',
    }
    // Persist member identity locally so the public-order page picks it up.
    try {
      if (m && m.id) {
        const key = `pos_member_${outletSlug}`
        localStorage.setItem(key, JSON.stringify(m))
      }
    } catch (e) { /* ignore */ }
    success.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    formError.value = e.response?.data?.message || 'Verifikasi gagal. Coba lagi.'
  } finally {
    submitting.value = false
  }
}

async function resendOtp() {
  if (resendCooldown.value > 0 || submitting.value) return
  formError.value = ''
  submitting.value = true
  try {
    const { data } = await axios.post(
      `${apiBase}/public/membership/${encodeURIComponent(outletSlug)}/otp/request`,
      {
        name: form.value.name,
        phone: verifiedPhone.value || form.value.phone,
        email: form.value.email || null,
        address: form.value.address || null,
      }
    )
    startCooldown(data?.cooldown_seconds || 60)
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal mengirim ulang OTP.'
    if (e.response?.data?.cooldown_seconds) {
      startCooldown(e.response.data.cooldown_seconds)
    }
  } finally {
    submitting.value = false
  }
}

function backToProfile() {
  step.value = 'profile'
  formError.value = ''
  form.value.code = ''
  form.value.password = ''
  form.value.password_confirmation = ''
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(successData.value.member_code)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch (e) {
    /* ignore */
  }
}

onMounted(fetchPage)
</script>

<style scoped>
.membership-page {
  --brand-1: #667eea;
  --brand-2: #764ba2;
  --text: #1f2937;
  --text-soft: #6b7280;
  --bg: #f8fafc;
  --card: #ffffff;
  --border: #e5e7eb;
  --danger: #dc2626;
  --success: #10b981;

  min-height: 100vh;
  background: var(--bg);
  color: var(--text);
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
}

@media (prefers-color-scheme: dark) {
  .membership-page {
    --text: #f3f4f6;
    --text-soft: #9ca3af;
    --bg: #0f172a;
    --card: #1e293b;
    --border: #334155;
  }
}

.state-center {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem;
}
.state-center .state-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.25rem;
  margin-bottom: 1rem;
}
.state-center .state-icon.error {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
}
.state-center h2 {
  font-size: 1.25rem;
  margin: 0.5rem 0;
}
.state-center p {
  color: var(--text-soft);
  margin: 0.25rem 0;
}
.state-center .contact {
  margin-top: 0.75rem;
  font-weight: 600;
  color: var(--text);
}
.state-center .contact i { margin-right: 0.4rem; }

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border);
  border-top-color: var(--brand-1);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}
@keyframes spin { to { transform: rotate(360deg); } }

.content {
  max-width: 480px;
  margin: 0 auto;
  padding-bottom: 2rem;
}

.hero {
  background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
  color: #fff;
  padding: 2.5rem 1.5rem 3.5rem;
  text-align: center;
  position: relative;
  animation: fade-in 0.5s ease;
}
.hero-inner {
  max-width: 420px;
  margin: 0 auto;
}
.logo-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 1rem;
}
.logo, .logo-fallback {
  width: 84px;
  height: 84px;
  border-radius: 20px;
  object-fit: cover;
  background: rgba(255, 255, 255, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.25);
}
.logo-fallback i { color: #fff; }

.outlet-name {
  font-size: 0.95rem;
  opacity: 0.9;
  margin-bottom: 0.25rem;
  letter-spacing: 0.02em;
}
.page-title {
  font-size: 1.75rem;
  margin: 0.25rem 0 0.5rem;
  font-weight: 700;
  line-height: 1.2;
}
.page-desc {
  margin: 0.5rem 0 0;
  opacity: 0.95;
  font-size: 0.95rem;
  line-height: 1.5;
}

.benefits {
  padding: 1.25rem 1.25rem 0;
  margin-top: -1.5rem;
  position: relative;
}
.benefits-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.6rem;
}
.benefit {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 0.7rem 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  animation: slide-up 0.4s ease both;
}
.benefit i {
  color: var(--success);
  font-size: 1rem;
  flex-shrink: 0;
}

@media (max-width: 380px) {
  .benefits-grid { grid-template-columns: 1fr; }
}

.form-section {
  padding: 1.25rem;
}
.form-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
  animation: slide-up 0.45s ease;
}
.form { display: flex; flex-direction: column; gap: 0.85rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text);
}
.req { color: var(--danger); }
.field input, .field textarea {
  width: 100%;
  padding: 0.75rem 0.9rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: 0.95rem;
  background: var(--card);
  color: var(--text);
  font-family: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field input:focus, .field textarea:focus {
  outline: none;
  border-color: var(--brand-1);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}
.field textarea { resize: vertical; min-height: 80px; }

.form-error {
  background: rgba(220, 38, 38, 0.08);
  color: var(--danger);
  padding: 0.6rem 0.8rem;
  border-radius: 8px;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.submit-btn {
  margin-top: 0.5rem;
  padding: 0.95rem 1rem;
  background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
  letter-spacing: 0.03em;
  cursor: pointer;
  transition: transform 0.1s, box-shadow 0.15s, opacity 0.15s;
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}
.submit-btn:disabled { opacity: 0.7; cursor: wait; }

.btn-loading {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}
.dot-spin {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.45);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.hint {
  text-align: center;
  font-size: 0.8rem;
  color: var(--text-soft);
  margin: 1rem 0 0;
  padding: 0 0.5rem;
}

/* Success */
.success-box {
  text-align: center;
  animation: fade-in 0.5s ease;
}
.check-anim {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--success), #059669);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.5rem;
  margin: 0 auto 1rem;
  box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
  animation: pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.success-box h2 {
  margin: 0.5rem 0 0.25rem;
  font-size: 1.4rem;
}
.success-sub { color: var(--text-soft); margin: 0 0 1.25rem; }

.code-box {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
  border: 1.5px dashed var(--brand-1);
  border-radius: 12px;
  padding: 0.9rem;
  margin: 1rem 0;
}
.code-label {
  font-size: 0.75rem;
  color: var(--text-soft);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 0.4rem;
}
.code-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.code-row code {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--brand-1);
  font-family: ui-monospace, "SF Mono", Menlo, monospace;
}
.copy-btn {
  background: var(--card);
  border: 1px solid var(--border);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text);
  transition: background 0.15s;
}
.copy-btn:hover { background: var(--bg); }

.welcome-msg {
  margin: 1rem 0;
  color: var(--text-soft);
  font-size: 0.9rem;
  line-height: 1.5;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.85rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  margin-top: 0.5rem;
}
.status-pill.ok {
  background: rgba(16, 185, 129, 0.12);
  color: var(--success);
}
.status-pill.pending {
  background: rgba(245, 158, 11, 0.15);
  color: #d97706;
}

@keyframes fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes slide-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes pop {
  0%   { transform: scale(0); opacity: 0; }
  60%  { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); }
}

/* ── OTP flow ───────────────────────────────────────────────── */
.step-indicator {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-soft);
  flex-wrap: wrap;
}
.step-indicator .step {
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--bg);
  border: 1px solid var(--border);
}
.step-indicator .step.active {
  background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
  color: #fff;
  border-color: transparent;
}
.step-indicator .step.done {
  background: rgba(16, 185, 129, 0.15);
  color: var(--success);
  border-color: transparent;
}
.field-hint {
  display: block;
  margin-top: 4px;
  color: var(--text-soft);
  font-size: 11px;
}
.optional {
  font-weight: 400;
  font-size: 11px;
  color: var(--text-soft);
}
.otp-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(37, 211, 102, 0.1);
  border: 1px solid rgba(37, 211, 102, 0.25);
  color: #166534;
  border-radius: 10px;
  padding: 10px 12px;
  margin-bottom: 14px;
  font-size: 13px;
}
.otp-banner i {
  font-size: 22px;
  color: #25d366;
}
.otp-banner-title { font-weight: 700; }
.otp-banner-sub   { color: var(--text-soft); font-size: 12px; }

.otp-input {
  letter-spacing: 0.4em;
  font-weight: 700;
  font-size: 18px;
  text-align: center;
}
.otp-actions {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-top: 10px;
  flex-wrap: wrap;
}
.link-btn {
  background: transparent;
  border: none;
  color: var(--brand-1);
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.link-btn:disabled {
  color: var(--text-soft);
  cursor: not-allowed;
}
</style>
