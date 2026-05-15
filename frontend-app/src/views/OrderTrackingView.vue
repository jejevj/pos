<template>
  <div class="track-page">
    <!-- Loading -->
    <div v-if="loading" class="receipt-wrap">
      <div class="receipt">
        <div class="receipt-section center">
          <div class="skeleton-line w-40 mx-auto mb-2"></div>
          <div class="skeleton-line w-28 mx-auto"></div>
        </div>
        <div class="receipt-divider"></div>
        <div v-for="i in 3" :key="i" class="skeleton-item">
          <div class="skeleton-line w-32 mb-1"></div>
          <div class="skeleton-line w-20"></div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="receipt-wrap">
      <div class="receipt">
        <div class="receipt-section center">
          <div class="brand-icon">
            <i class="pi pi-times-circle" style="font-size:2rem;color:#FA896B"></i>
          </div>
          <h2 class="outlet-name">Pesanan Tidak Ditemukan</h2>
          <p class="muted">{{ error }}</p>
        </div>
        <div class="receipt-divider"></div>
        <p class="muted center" style="padding:1rem 0">
          Pastikan kode pesanan Anda benar, atau hubungi kasir.
        </p>
      </div>
    </div>

    <!-- Data -->
    <div v-else-if="data" class="receipt-wrap">
      <div class="receipt">

        <!-- Outlet header -->
        <div class="receipt-section center">
          <img
            v-if="headerLogo && !logoFailed"
            :src="headerLogo"
            :alt="data.outlet.name || 'Logo'"
            class="outlet-logo"
            @error="logoFailed = true"
          />
          <div v-else class="brand-icon">
            <span v-if="outletInitials" class="outlet-initials">{{ outletInitials }}</span>
            <i v-else class="pi pi-shop"></i>
          </div>
          <h1 class="outlet-name">{{ data.outlet.name || data.site?.name || 'Outlet' }}</h1>
          <p v-if="data.outlet.address" class="outlet-meta">
            <i class="pi pi-map-marker" style="font-size:0.7rem;margin-right:0.2rem"></i>{{ data.outlet.address }}
          </p>
          <p v-if="data.outlet.phone" class="outlet-meta">
            <i class="pi pi-phone" style="font-size:0.7rem;margin-right:0.2rem"></i>
            <a :href="`tel:${data.outlet.phone}`" class="phone-link">{{ data.outlet.phone }}</a>
          </p>
          <p v-if="data.outlet.description || data.site?.tagline" class="outlet-tagline">
            {{ data.outlet.description || data.site?.tagline }}
          </p>
        </div>

        <!-- Custom receipt header -->
        <template v-if="receipt.receipt_header">
          <div class="receipt-divider"></div>
          <div class="receipt-section center custom-text">
            <p v-for="(line, i) in headerLines" :key="'h'+i">{{ line }}</p>
          </div>
        </template>

        <!-- Status -->
        <div class="receipt-divider"></div>
        <div class="receipt-section center">
          <p class="section-title">STATUS PESANAN</p>
          <div class="status-badge" :class="statusClass(data.order.kitchen_status)">
            <i :class="statusIcon(data.order.kitchen_status)"></i>
            {{ statusLabel(data.order.kitchen_status) }}
          </div>
        </div>

        <!-- Timeline -->
        <div class="receipt-divider dashed"></div>
        <div class="receipt-section">
          <p class="section-title center">PERJALANAN PESANAN</p>
          <div class="order-timeline">
            <template v-for="(step, idx) in timelineSteps" :key="step.key">
              <div class="timeline-step" :class="step.state">
                <div class="step-icon">
                  <i v-if="step.state === 'completed'" class="pi pi-check"></i>
                  <i v-else-if="step.state === 'active'" class="pi pi-spin pi-cog"></i>
                  <span v-else>○</span>
                </div>
                <div class="step-body">
                  <div class="step-label">{{ step.label }}</div>
                  <div class="step-time">{{ step.time ? formatTime(step.time) : '-' }}</div>
                </div>
              </div>
              <div
                v-if="idx < timelineSteps.length - 1"
                class="timeline-connector"
                :class="step.state === 'completed' ? 'completed' : 'pending'"
              ></div>
            </template>
          </div>
        </div>

        <!-- Order info -->
        <div class="receipt-divider dashed"></div>
        <div class="receipt-section">
          <div class="info-row">
            <span class="info-label">No. Pesanan</span>
            <span class="info-value">{{ data.order.kode }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Tanggal</span>
            <span class="info-value">{{ formatDate(data.order.created_at) }}</span>
          </div>
          <div class="info-row" v-if="data.order.cashier_name && receipt.receipt_show_cashier !== false">
            <span class="info-label">Kasir</span>
            <span class="info-value">{{ data.order.cashier_name }}</span>
          </div>
          <div class="info-row" v-if="data.order.table_number && receipt.receipt_show_table !== false">
            <span class="info-label">Meja</span>
            <span class="info-value">{{ data.order.table_number }}</span>
          </div>
          <div class="info-row" v-if="data.order.customer_name">
            <span class="info-label">Pelanggan</span>
            <span class="info-value">{{ data.order.customer_name }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Tipe</span>
            <span class="info-value">{{ formatOrderType(data.order.order_type) }}</span>
          </div>
          <div class="info-row" v-if="data.order.notes">
            <span class="info-label">Catatan</span>
            <span class="info-value note-text">{{ data.order.notes }}</span>
          </div>
        </div>

        <!-- Items -->
        <div class="receipt-divider"></div>
        <p class="section-title center">DETAIL PESANAN</p>
        <div class="receipt-section">
          <div v-for="item in allItems" :key="item.id" class="item-row">
            <div class="item-main">
              <span class="item-name">{{ item.menu_name }}</span>
              <span class="item-qty">x{{ item.quantity }}</span>
              <span class="item-price">{{ formatCurrency(item.subtotal || (item.menu_price * item.quantity)) }}</span>
            </div>
            <div v-if="item.notes" class="item-notes">
              <i class="pi pi-comment"></i> {{ item.notes }}
            </div>
            <div class="item-status-line">
              <span class="item-status-dot" :class="itemStatusClass(item.status)">
                {{ itemStatusLabel(item.status) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="receipt-divider dashed"></div>
        <div class="receipt-section">
          <div class="info-row">
            <span class="info-label">Subtotal</span>
            <span class="info-value">{{ formatCurrency(data.order.subtotal) }}</span>
          </div>
          <div class="info-row" v-if="receipt.tax_enabled && data.order.tax_amount > 0">
            <span class="info-label">{{ receipt.tax_label || 'PPN' }}
              <span v-if="data.order.tax_percentage">({{ data.order.tax_percentage }}%)</span>
            </span>
            <span class="info-value">{{ formatCurrency(data.order.tax_amount) }}</span>
          </div>
          <div class="info-row" v-if="receipt.service_charge_enabled && data.order.service_charge_amount > 0">
            <span class="info-label">{{ receipt.service_charge_label || 'Service Charge' }}
              <span v-if="data.order.service_charge_percentage">({{ data.order.service_charge_percentage }}%)</span>
            </span>
            <span class="info-value">{{ formatCurrency(data.order.service_charge_amount) }}</span>
          </div>
          <div class="info-row" v-if="data.order.discount_amount > 0">
            <span class="info-label">Diskon</span>
            <span class="info-value">-{{ formatCurrency(data.order.discount_amount) }}</span>
          </div>
        </div>

        <div class="receipt-divider"></div>
        <div class="receipt-section">
          <div class="info-row total-row">
            <span class="info-label">TOTAL</span>
            <span class="info-value">{{ formatCurrency(data.order.total_amount) }}</span>
          </div>
        </div>

        <!-- Tracking QR -->
        <template v-if="receipt.receipt_show_qr">
          <div class="receipt-divider"></div>
          <div class="receipt-section center">
            <img :src="qrUrl" alt="Tracking QR" class="qr-img" />
            <p class="muted small">Scan untuk tracking pesanan</p>
          </div>
        </template>

        <!-- WiFi -->
        <template v-if="receipt.receipt_wifi_enabled && receipt.receipt_wifi_ssid">
          <div class="receipt-divider dashed"></div>
          <div class="receipt-section center">
            <p class="section-title">WIFI GRATIS</p>
            <img :src="wifiQrUrl" alt="WiFi QR" class="qr-img small" />
            <div class="info-row inline">
              <span class="info-label">SSID</span>
              <span class="info-value">{{ receipt.receipt_wifi_ssid }}</span>
            </div>
            <div class="info-row inline" v-if="receipt.receipt_wifi_password">
              <span class="info-label">Password</span>
              <span class="info-value">{{ receipt.receipt_wifi_password }}</span>
            </div>
          </div>
        </template>

        <!-- Custom footer -->
        <template v-if="receipt.receipt_footer">
          <div class="receipt-divider"></div>
          <div class="receipt-section center custom-text">
            <p v-for="(line, i) in footerLines" :key="'f'+i">{{ line }}</p>
          </div>
        </template>

        <!-- Closing -->
        <div class="receipt-divider dashed"></div>
        <div class="receipt-section center">
          <p class="thanks-text">Terima kasih atas kunjungan Anda</p>
          <div class="refresh-indicator">
            <i class="pi pi-refresh" :class="{ spinning: refreshing }"></i>
            <span>{{ lastUpdated }}</span>
          </div>
          <p class="muted small">Halaman ini otomatis diperbarui setiap 30 detik</p>
        </div>

        <!-- Thermal cut -->
        <div class="receipt-cut">
          <div class="cut-line"></div>
          <div class="cut-circles">
            <span v-for="i in 20" :key="i" class="cut-circle"></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { decodeOutletId } from '@/utils/outletId'

const route   = useRoute()
const loading = ref(true)
const error   = ref(null)
const data    = ref(null)
const refreshing = ref(false)
const lastUpdated = ref('')
const logoFailed = ref(false)
let timer = null

// route param may be the encoded hash; decode to numeric ID for backend
const rawOutletParam = route.params.outletId
const numericOutletId = decodeOutletId(rawOutletParam) || rawOutletParam
const orderCode = route.params.orderCode

// ── Computed ────────────────────────────────────────────────
const allItems = computed(() => {
  if (!data.value) return []
  return data.value.stations.flatMap(s => s.items)
})
const receipt = computed(() => data.value?.receipt_settings || {})
const headerLines = computed(() =>
  (receipt.value.receipt_header || '').split('\n').map(s => s.trim()).filter(Boolean)
)
const footerLines = computed(() =>
  (receipt.value.receipt_footer || '').split('\n').map(s => s.trim()).filter(Boolean)
)
const qrUrl = computed(() => {
  const url = encodeURIComponent(window.location.href)
  return `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${url}`
})
const wifiQrUrl = computed(() => {
  const ssid = receipt.value.receipt_wifi_ssid || ''
  const pass = receipt.value.receipt_wifi_password || ''
  const payload = encodeURIComponent(`WIFI:T:WPA;S:${ssid};P:${pass};H:false;;`)
  return `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${payload}`
})

// Outlet identity helpers (with site_settings fallback)
const headerLogo = computed(() => {
  return data.value?.outlet?.logo || data.value?.site?.logo || ''
})
const outletInitials = computed(() => {
  const name = data.value?.outlet?.name || data.value?.site?.name || ''
  if (!name) return ''
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
})

// Build visual timeline steps from order + backend timeline
const timelineSteps = computed(() => {
  const order = data.value?.order || {}
  const ks = order.kitchen_status || 'pending'

  // Precedence order — what step the order is currently at
  const order_progress = ['pending', 'preparing', 'ready', 'served']
  const currentIdx = order_progress.indexOf(ks)

  // Pick timestamp: prefer top-level order field, fallback to backend timeline payload
  const tl = data.value?.timeline || []
  const tlMap = {}
  tl.forEach(s => { if (s.time) tlMap[s.key] = s.time })

  const steps = [
    {
      key: 'ordered',
      label: 'Pesanan Diterima',
      time: order.created_at || tlMap.ordered,
      reachedIdx: 0,
    },
    {
      key: 'preparing',
      label: 'Diproses Dapur',
      time: order.preparing_at || order.confirmed_at || tlMap.preparing,
      reachedIdx: 1,
    },
    {
      key: 'ready',
      label: 'Siap Disajikan',
      time: order.ready_at || tlMap.ready,
      reachedIdx: 2,
    },
    {
      key: 'served',
      label: 'Tersaji / Selesai',
      time: order.served_at || order.completed_at || tlMap.served,
      reachedIdx: 3,
    },
  ]

  // Cancelled — mark all remaining as pending, no active spinner
  if (ks === 'cancelled') {
    return steps.map((s, i) => ({
      ...s,
      state: s.time ? 'completed' : 'pending',
    }))
  }

  return steps.map((s, i) => {
    let state
    if (s.time || i < currentIdx) {
      state = 'completed'
    } else if (i === currentIdx) {
      state = 'active'
    } else {
      state = 'pending'
    }
    // If step.time exists but it's the current step, still mark active so spinner shows
    if (i === currentIdx && currentIdx !== order_progress.length - 1) {
      state = 'active'
    }
    return { ...s, state }
  })
})

const formatTime = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('id-ID', {
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta'
  }) + ' WIB'
}

// ── Fetch ───────────────────────────────────────────────────
const fetchOrder = async (silent = false) => {
  if (!silent) loading.value = true
  else refreshing.value = true

  try {
    const base = import.meta.env.VITE_API_URL || '/api'
    const res  = await axios.get(`${base}/track/${numericOutletId}/${orderCode}`)
    data.value  = res.data
    error.value = null
    logoFailed.value = false
    lastUpdated.value = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta' })
  } catch (e) {
    if (!silent) {
      error.value = e.response?.data?.message || 'Gagal memuat pesanan'
    }
  } finally {
    loading.value   = false
    refreshing.value = false
  }
}

onMounted(() => {
  fetchOrder()
  timer = setInterval(() => fetchOrder(true), 30000)
})
onUnmounted(() => clearInterval(timer))

// ── Formatters ──────────────────────────────────────────────
const formatDate = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta'
  })
}
const formatCurrency = (n) => {
  const v = Number(n || 0)
  return 'Rp ' + v.toLocaleString('id-ID', { maximumFractionDigits: 0 })
}
const formatOrderType = (t) => ({
  dine_in: 'Makan di Tempat', takeaway: 'Bawa Pulang', delivery: 'Delivery'
}[t] || t)

// ── Status helpers ───────────────────────────────────────────
const statusLabel = (s) => ({
  pending:    'Menunggu',
  processing: 'Sedang Diproses',
  preparing:  'Sedang Diproses',
  ready:      'Siap Diambil',
  served:     'Selesai',
  completed:  'Selesai',
  paid:       'Selesai',
  cancelled:  'Dibatalkan',
}[s] || s || 'Menunggu')

const statusIcon = (s) => ({
  pending:    'pi pi-clock',
  processing: 'pi pi-spin pi-cog',
  preparing:  'pi pi-spin pi-cog',
  ready:      'pi pi-bell',
  served:     'pi pi-check-circle',
  completed:  'pi pi-check-circle',
  paid:       'pi pi-check-circle',
  cancelled:  'pi pi-times-circle',
}[s] || 'pi pi-info-circle')

const statusClass = (s) => ({
  pending:    'status-pending',
  processing: 'status-preparing',
  preparing:  'status-preparing',
  ready:      'status-ready',
  served:     'status-served',
  completed:  'status-served',
  paid:       'status-served',
  cancelled:  'status-cancelled',
}[s] || 'status-pending')

const itemStatusLabel = (s) => ({
  pending:   'Antrian',
  preparing: 'Dimasak',
  ready:     'Siap',
  served:    'Disajikan',
}[s] || s || 'Antrian')

const itemStatusClass = (s) => ({
  pending:   'dot-pending',
  preparing: 'dot-preparing',
  ready:     'dot-ready',
  served:    'dot-served',
}[s] || 'dot-pending')
</script>

<style scoped>
/* ── Page ───────────────────────────────────────────────── */
.track-page {
  min-height: 100vh;
  background: var(--track-page-bg, #f0f0f0);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2rem 1rem 4rem;
  font-family: 'Courier New', 'Courier', monospace;
}

.receipt-wrap {
  width: 100%;
  max-width: 420px;
}

.receipt {
  background: var(--track-receipt-bg, #fff);
  color: var(--track-text, #111);
  border-radius: 6px 6px 0 0;
  box-shadow: 0 4px 24px rgba(0,0,0,0.13);
  position: relative;
  overflow: hidden;
}

/* ── Section wrappers ───────────────────────────────────── */
.receipt-section {
  padding: 0.9rem 1.4rem;
}
.receipt-section.center { text-align: center; }

/* ── Outlet header ──────────────────────────────────────── */
.outlet-logo {
  width: 70px;
  height: 70px;
  object-fit: contain;
  border-radius: 8px;
  margin: 0 auto 0.6rem;
  display: block;
}
.brand-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--track-icon-bg, #f4f4f4);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.75rem;
  font-size: 1.6rem;
  color: var(--track-text, #333);
}

.outlet-name {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin: 0 0 0.3rem;
  color: var(--track-text, #111);
}
.outlet-meta {
  font-size: 0.78rem;
  color: var(--track-muted, #666);
  margin: 0.1rem 0;
}
.custom-text p {
  font-size: 0.82rem;
  color: var(--track-text, #222);
  margin: 0.1rem 0;
}
.outlet-initials {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--track-text, #333);
  letter-spacing: 0.05em;
}
.phone-link {
  color: inherit;
  text-decoration: none;
  border-bottom: 1px dotted currentColor;
}
.outlet-tagline {
  font-size: 0.78rem;
  font-style: italic;
  color: var(--track-muted, #666);
  margin: 0.4rem 0 0;
}

/* ── Order timeline ─────────────────────────────────────── */
.order-timeline {
  padding: 0.5rem 0 0.25rem;
}
.timeline-step {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}
.step-icon {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
  border: 2px solid transparent;
}
.timeline-step.completed .step-icon {
  background: #22c55e; color: white;
}
.timeline-step.active .step-icon {
  background: #3b82f6; color: white;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.18);
}
.timeline-step.pending .step-icon {
  background: var(--track-icon-bg, #e5e7eb);
  color: var(--track-muted, #9ca3af);
  border-color: var(--track-divider-soft, #d1d5db);
}
.step-body {
  flex: 1;
  padding: 0.35rem 0;
}
.step-label {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--track-text, #111);
}
.timeline-step.pending .step-label {
  color: var(--track-muted, #888);
  font-weight: 500;
}
.step-time {
  font-size: 0.75rem;
  color: var(--track-muted, #888);
  margin-top: 0.1rem;
}
.timeline-connector {
  width: 2px;
  height: 18px;
  margin-left: 15px;
}
.timeline-connector.completed {
  background: #22c55e;
}
.timeline-connector.pending {
  background: transparent;
  border-left: 2px dashed var(--track-divider-soft, #d1d5db);
  width: 0;
}

/* ── Dividers ───────────────────────────────────────────── */
.receipt-divider {
  height: 1px;
  background: var(--track-divider, #333);
  margin: 0;
}
.receipt-divider.dashed {
  background: none;
  border-top: 1.5px dashed var(--track-divider-soft, #ccc);
}

/* ── Section title ──────────────────────────────────────── */
.section-title {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: var(--track-muted, #888);
  margin: 0 0 0.5rem;
}
.center { text-align: center; }
.muted { color: var(--track-muted, #888); }
.muted.small { font-size: 0.72rem; }

/* ── Status badge ───────────────────────────────────────── */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 1.3rem;
  border-radius: 999px;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.status-pending   { background: #fff7e6; color: #b45309; border: 1.5px solid #fcd34d; }
.status-preparing { background: #eff6ff; color: #1d4ed8; border: 1.5px solid #93c5fd; }
.status-ready     { background: #ecfdf5; color: #065f46; border: 1.5px solid #6ee7b7; }
.status-served    { background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; }
.status-cancelled { background: #fef2f2; color: #991b1b; border: 1.5px solid #fca5a5; }

/* ── Info rows ──────────────────────────────────────────── */
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 0.25rem 0;
  font-size: 0.85rem;
}
.info-row.inline {
  justify-content: center;
  gap: 0.5rem;
}
.info-label {
  color: var(--track-muted, #666);
  flex-shrink: 0;
  margin-right: 1rem;
}
.info-value {
  color: var(--track-text, #111);
  font-weight: 600;
  text-align: right;
}
.note-text {
  font-style: italic;
  font-weight: 400;
  color: var(--track-muted, #555);
}
.total-row {
  font-size: 1rem;
  font-weight: 800;
}
.total-row .info-label,
.total-row .info-value {
  color: var(--track-text, #111);
  font-weight: 800;
  letter-spacing: 0.05em;
}

/* ── Items ──────────────────────────────────────────────── */
.item-row {
  padding: 0.5rem 0;
  border-bottom: 1px dashed var(--track-divider-soft, #eee);
}
.item-row:last-child { border-bottom: none; }
.item-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.88rem;
}
.item-name {
  flex: 1;
  color: var(--track-text, #111);
  font-weight: 600;
}
.item-qty {
  font-weight: 700;
  color: var(--track-muted, #555);
  min-width: 28px;
  text-align: right;
}
.item-price {
  font-weight: 700;
  color: var(--track-text, #111);
  min-width: 70px;
  text-align: right;
}
.item-notes {
  font-size: 0.75rem;
  color: var(--track-muted, #888);
  margin-top: 0.15rem;
  font-style: italic;
}
.item-status-line {
  margin-top: 0.3rem;
}
.item-status-dot {
  display: inline-block;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.dot-pending   { background: #fef9c3; color: #713f12; }
.dot-preparing { background: #dbeafe; color: #1e40af; }
.dot-ready     { background: #dcfce7; color: #166534; }
.dot-served    { background: #f0fdf4; color: #15803d; }

/* ── QR ─────────────────────────────────────────────────── */
.qr-img {
  width: 120px;
  height: 120px;
  margin: 0.4rem auto;
  display: block;
  background: #fff;
  padding: 4px;
  border-radius: 4px;
}
.qr-img.small {
  width: 100px;
  height: 100px;
}

/* ── Footer ─────────────────────────────────────────────── */
.thanks-text {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--track-text, #333);
  margin: 0 0 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.refresh-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: var(--track-muted, #aaa);
  margin-bottom: 0.3rem;
}
.pi-refresh.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Thermal cut ────────────────────────────────────────── */
.receipt-cut {
  margin-top: 0.5rem;
  position: relative;
  height: 24px;
}
.cut-line {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
  border-top: 2px dashed var(--track-divider-soft, #ccc);
}
.cut-circles {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
}
.cut-circle {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--track-page-bg, #f0f0f0);
  flex-shrink: 0;
  margin: 0 -7px;
}

/* ── Skeleton ───────────────────────────────────────────── */
.skeleton-line {
  height: 12px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 6px;
}
.skeleton-item { padding: 0.5rem 1.5rem; }
.w-40 { width: 10rem; }
.w-28 { width: 7rem; }
.w-32 { width: 8rem; }
.w-20 { width: 5rem; }
.mx-auto { margin-left: auto; margin-right: auto; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-1 { margin-bottom: 0.25rem; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
