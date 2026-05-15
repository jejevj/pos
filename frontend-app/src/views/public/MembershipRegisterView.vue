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

          <!-- Form state -->
          <form v-else @submit.prevent="submit" class="form" novalidate>
            <div class="field">
              <label for="name">Nama Lengkap <span class="req">*</span></label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="Nama sesuai identitas"
                required
                autocomplete="name"
              />
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="email@contoh.com"
                autocomplete="email"
              />
            </div>

            <div class="field">
              <label for="phone">
                No. Telepon
                <span v-if="settings.require_phone" class="req">*</span>
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                placeholder="0812xxxxxxxx"
                :required="settings.require_phone"
                autocomplete="tel"
              />
            </div>

            <div v-if="settings.require_address" class="field">
              <label for="address">Alamat <span class="req">*</span></label>
              <textarea
                id="address"
                v-model="form.address"
                rows="3"
                placeholder="Alamat lengkap"
                required
              ></textarea>
            </div>

            <div v-if="formError" class="form-error">
              <i class="pi pi-exclamation-circle"></i>
              <span>{{ formError }}</span>
            </div>

            <button type="submit" class="submit-btn" :disabled="submitting">
              <span v-if="!submitting">DAFTAR SEKARANG</span>
              <span v-else class="btn-loading">
                <span class="dot-spin"></span>
                Memproses...
              </span>
            </button>
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

const form = ref({ name: '', email: '', phone: '', address: '' })
const submitting = ref(false)
const formError = ref('')
const success = ref(false)
const successData = ref({ name: '', member_code: '', status: 'active' })
const copied = ref(false)

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

async function submit() {
  if (submitting.value) return
  formError.value = ''

  if (!form.value.name.trim()) {
    formError.value = 'Nama wajib diisi'
    return
  }
  if (settings.value.require_phone && !form.value.phone.trim()) {
    formError.value = 'No. telepon wajib diisi'
    return
  }
  if (settings.value.require_address && !form.value.address.trim()) {
    formError.value = 'Alamat wajib diisi'
    return
  }
  if (!form.value.email.trim() && !form.value.phone.trim()) {
    formError.value = 'Isi email atau no. telepon'
    return
  }

  submitting.value = true
  try {
    const { data } = await axios.post(
      `${apiBase}/public/membership/${encodeURIComponent(outletSlug)}/register`,
      form.value
    )
    successData.value = data.member || { name: form.value.name, member_code: '', status: 'active' }
    success.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal mendaftar. Coba lagi.'
  } finally {
    submitting.value = false
  }
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
</style>
