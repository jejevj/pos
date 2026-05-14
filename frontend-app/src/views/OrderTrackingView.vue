<template>
  <div class="tracking-page">
    <!-- Loading -->
    <div v-if="loading" class="receipt-wrap">
      <div class="receipt">
        <div class="receipt-header">
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
        <div class="receipt-header">
          <div class="brand-icon">
            <i class="pi pi-times-circle" style="font-size:2rem;color:#FA896B"></i>
          </div>
          <h2 class="outlet-name">Pesanan Tidak Ditemukan</h2>
          <p class="order-code">{{ error }}</p>
        </div>
        <div class="receipt-divider"></div>
        <p class="text-center text-muted" style="padding:1rem 0">
          Pastikan kode pesanan Anda benar, atau hubungi kasir.
        </p>
        <div class="receipt-bottom-cut"></div>
      </div>
    </div>

    <!-- Data -->
    <div v-else-if="data" class="receipt-wrap">
      <div class="receipt">

        <!-- Header -->
        <div class="receipt-header">
          <div class="brand-icon">
            <i class="pi pi-shop"></i>
          </div>
          <h1 class="outlet-name">{{ data.outlet.name }}</h1>
          <p class="order-code"># {{ data.order.kode }}</p>
          <p class="order-date">{{ formatDate(data.order.created_at) }}</p>
        </div>

        <!-- Customer & table info -->
        <div class="receipt-divider dashed"></div>
        <div class="info-row" v-if="data.order.customer_name">
          <span class="info-label">Pelanggan</span>
          <span class="info-value">{{ data.order.customer_name }}</span>
        </div>
        <div class="info-row" v-if="data.order.table_number">
          <span class="info-label">Meja</span>
          <span class="info-value">{{ data.order.table_number }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Tipe</span>
          <span class="info-value">{{ formatOrderType(data.order.order_type) }}</span>
        </div>
        <div class="info-row" v-if="data.order.notes">
          <span class="info-label">Catatan</span>
          <span class="info-value note-text">{{ data.order.notes }}</span>
        </div>

        <!-- Status badge -->
        <div class="receipt-divider dashed"></div>
        <div class="status-section">
          <div class="status-badge" :class="statusClass(data.order.kitchen_status)">
            <i :class="statusIcon(data.order.kitchen_status)"></i>
            {{ statusLabel(data.order.kitchen_status) }}
          </div>
        </div>

        <!-- Timeline -->
        <div class="receipt-divider dashed"></div>
        <div class="timeline">
          <div
            v-for="step in data.timeline"
            :key="step.key"
            class="timeline-step"
            :class="{ done: step.done }"
          >
            <div class="timeline-dot" :style="step.done ? `background:${step.color}` : ''">
              <i :class="step.icon"></i>
            </div>
            <div class="timeline-body">
              <span class="timeline-label">{{ step.label }}</span>
              <span class="timeline-time" v-if="step.time">{{ formatTime(step.time) }}</span>
            </div>
          </div>
        </div>

        <!-- Items -->
        <div class="receipt-divider"></div>
        <p class="section-title">DAFTAR PESANAN</p>

        <div
          v-for="item in allItems"
          :key="item.id"
          class="item-row"
        >
          <div class="item-main">
            <span class="item-qty">{{ item.quantity }}x</span>
            <span class="item-name">{{ item.menu_name }}</span>
            <span class="item-status-dot" :class="itemStatusClass(item.status)">
              {{ itemStatusLabel(item.status) }}
            </span>
          </div>
          <div v-if="item.notes" class="item-notes">
            <i class="pi pi-comment"></i> {{ item.notes }}
          </div>
        </div>

        <!-- Footer -->
        <div class="receipt-divider dashed"></div>
        <div class="receipt-footer">
          <p class="footer-text">Terima kasih atas pesanan Anda!</p>
          <p class="footer-subtext">Halaman ini otomatis diperbarui setiap 15 detik</p>
          <div class="refresh-indicator">
            <i class="pi pi-refresh" :class="{ spinning: refreshing }"></i>
            <span>{{ lastUpdated }}</span>
          </div>
        </div>

        <!-- Thermal cut effect -->
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

const route   = useRoute()
const loading = ref(true)
const error   = ref(null)
const data    = ref(null)
const refreshing = ref(false)
const lastUpdated = ref('')
let timer = null

const outletId  = route.params.outletId
const orderCode = route.params.orderCode

// ── Computed ────────────────────────────────────────────────
const allItems = computed(() => {
  if (!data.value) return []
  return data.value.stations.flatMap(s => s.items)
})

// ── Fetch ───────────────────────────────────────────────────
const fetchOrder = async (silent = false) => {
  if (!silent) loading.value = true
  else refreshing.value = true

  try {
    const base = import.meta.env.VITE_API_URL || '/api'
    const res  = await axios.get(`${base}/track/${outletId}/${orderCode}`)
    data.value  = res.data
    error.value = null
    lastUpdated.value = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID')
  } catch (e) {
    if (!silent) {
      error.value = e.response?.data?.message || 'Gagal memuat pesanan'
    }
  } finally {
    loading.value   = false
    refreshing.value = false
  }
}

// ── Auto refresh ────────────────────────────────────────────
onMounted(() => {
  fetchOrder()
  timer = setInterval(() => fetchOrder(true), 15000)
})
onUnmounted(() => clearInterval(timer))

// ── Formatters ──────────────────────────────────────────────
const formatDate = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}
const formatTime = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
const formatOrderType = (t) => ({
  dine_in: 'Makan di Tempat', takeaway: 'Bawa Pulang', delivery: 'Delivery'
}[t] || t)

// ── Status helpers ───────────────────────────────────────────
const statusLabel = (s) => ({
  pending:   'Menunggu Diproses',
  preparing: 'Sedang Dimasak',
  ready:     'Siap Disajikan',
  served:    'Sudah Disajikan',
  cancelled: 'Dibatalkan',
}[s] || s)

const statusIcon = (s) => ({
  pending:   'pi pi-clock',
  preparing: 'pi pi-spin pi-cog',
  ready:     'pi pi-bell',
  served:    'pi pi-check-circle',
  cancelled: 'pi pi-times-circle',
}[s] || 'pi pi-info-circle')

const statusClass = (s) => ({
  pending:   'status-pending',
  preparing: 'status-preparing',
  ready:     'status-ready',
  served:    'status-served',
  cancelled: 'status-cancelled',
}[s] || '')

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
.tracking-page {
  min-height: 100vh;
  background: #f0f0f0;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2rem 1rem 4rem;
  font-family: 'Courier New', 'Courier', monospace;
}

/* ── Receipt wrapper ────────────────────────────────────── */
.receipt-wrap {
  width: 100%;
  max-width: 380px;
}

.receipt {
  background: #fff;
  border-radius: 4px 4px 0 0;
  box-shadow: 0 4px 24px rgba(0,0,0,0.13);
  position: relative;
  overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────── */
.receipt-header {
  text-align: center;
  padding: 2rem 1.5rem 1.2rem;
}

.brand-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #f4f4f4;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.75rem;
  font-size: 1.6rem;
  color: #333;
}

.outlet-name {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin: 0 0 0.3rem;
  color: #111;
}

.order-code {
  font-size: 0.95rem;
  font-weight: 700;
  color: #333;
  letter-spacing: 0.05em;
  margin: 0 0 0.15rem;
}

.order-date {
  font-size: 0.78rem;
  color: #888;
  margin: 0;
}

/* ── Dividers ───────────────────────────────────────────── */
.receipt-divider {
  height: 1px;
  background: #333;
  margin: 0;
}
.receipt-divider.dashed {
  background: none;
  border-top: 1.5px dashed #ccc;
}

/* ── Info rows ──────────────────────────────────────────── */
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 0.3rem 1.5rem;
  font-size: 0.82rem;
}
.info-label {
  color: #888;
  flex-shrink: 0;
  margin-right: 1rem;
}
.info-value {
  color: #222;
  font-weight: 600;
  text-align: right;
}
.note-text {
  font-style: italic;
  font-weight: 400;
  color: #555;
}

/* ── Status badge ───────────────────────────────────────── */
.status-section {
  padding: 0.75rem 1.5rem;
  text-align: center;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.2rem;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.status-pending   { background: #fff7e6; color: #b45309; border: 1.5px solid #fcd34d; }
.status-preparing { background: #eff6ff; color: #1d4ed8; border: 1.5px solid #93c5fd; }
.status-ready     { background: #ecfdf5; color: #065f46; border: 1.5px solid #6ee7b7; }
.status-served    { background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; }
.status-cancelled { background: #fef2f2; color: #991b1b; border: 1.5px solid #fca5a5; }

/* ── Timeline ───────────────────────────────────────────── */
.timeline {
  padding: 0.75rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.timeline-step {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.4rem 0;
  opacity: 0.35;
  transition: opacity 0.3s;
}
.timeline-step.done { opacity: 1; }

.timeline-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  flex-shrink: 0;
  color: #fff;
  transition: background 0.3s;
}
.timeline-step:not(.done) .timeline-dot { color: #aaa; }

.timeline-body {
  display: flex;
  flex-direction: column;
}
.timeline-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #222;
}
.timeline-time {
  font-size: 0.72rem;
  color: #888;
}

/* ── Section title ──────────────────────────────────────── */
.section-title {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: #888;
  text-align: center;
  margin: 0.6rem 0 0.2rem;
}

/* ── Items ──────────────────────────────────────────────── */
.item-row {
  padding: 0.45rem 1.5rem;
  border-bottom: 1px dashed #eee;
}
.item-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.item-qty {
  font-weight: 700;
  font-size: 0.88rem;
  color: #333;
  min-width: 28px;
}
.item-name {
  flex: 1;
  font-size: 0.88rem;
  color: #111;
  font-weight: 600;
}
.item-notes {
  font-size: 0.75rem;
  color: #888;
  padding-left: 36px;
  margin-top: 0.15rem;
  font-style: italic;
}

/* Item status dots */
.item-status-dot {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  flex-shrink: 0;
}
.dot-pending   { background: #fef9c3; color: #713f12; }
.dot-preparing { background: #dbeafe; color: #1e40af; }
.dot-ready     { background: #dcfce7; color: #166534; }
.dot-served    { background: #f0fdf4; color: #15803d; }

/* ── Footer ─────────────────────────────────────────────── */
.receipt-footer {
  text-align: center;
  padding: 1rem 1.5rem 0.5rem;
}
.footer-text {
  font-size: 0.85rem;
  font-weight: 700;
  color: #333;
  margin: 0 0 0.2rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.footer-subtext {
  font-size: 0.72rem;
  color: #aaa;
  margin: 0 0 0.5rem;
}
.refresh-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: #bbb;
}
.pi-refresh.spinning {
  animation: spin 1s linear infinite;
}
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
  border-top: 2px dashed #ccc;
}
.cut-circles {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  padding: 0 0;
}
.cut-circle {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #f0f0f0;
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
.text-center { text-align: center; }
.text-muted { color: #aaa; font-size: 0.85rem; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
