<template>
  <div class="tracking-page">

    <!-- Header Bar -->
    <div class="tracking-header">
      <div class="header-brand">
        <i class="pi pi-box brand-icon"></i>
        <span class="brand-name">{{ data?.outlet?.name || 'Moira' }}</span>
      </div>
      <div class="header-badge">
        <i class="pi pi-map-marker"></i>
        Lacak Pesanan
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="state-center">
      <ProgressSpinner style="width:48px;height:48px" strokeWidth="4" fill="transparent" />
      <p>Memuat status pesanan...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="state-center">
      <div class="error-box">
        <i class="pi pi-exclamation-circle error-icon"></i>
        <h3>Pesanan Tidak Ditemukan</h3>
        <p>{{ error }}</p>
      </div>
    </div>

    <!-- Receipt -->
    <div v-else-if="data" class="receipt-wrapper">

      <!-- ═══ RECEIPT PAPER ═══ -->
      <div class="receipt-paper">

        <!-- Outlet Header -->
        <div class="receipt-top">
          <div class="receipt-outlet-name">{{ data.outlet?.name }}</div>
          <div v-if="data.outlet?.address" class="receipt-outlet-address">{{ data.outlet.address }}</div>
          <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>
        </div>

        <!-- Order Code + Status -->
        <div class="receipt-order-block">
          <div class="receipt-order-label">NO. PESANAN</div>
          <div class="receipt-order-code">{{ data.order.kode }}</div>
          <div class="receipt-status-pill" :class="`status-${data.order.kitchen_status}`">
            <i :class="kitchenIcon(data.order.kitchen_status)"></i>
            {{ kitchenLabel(data.order.kitchen_status) }}
          </div>
        </div>

        <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>

        <!-- Order Meta -->
        <div class="receipt-meta-block">
          <div class="receipt-meta-row">
            <span class="meta-key">Waktu</span>
            <span class="meta-val">{{ formatDateTime(data.order.created_at) }}</span>
          </div>
          <div class="receipt-meta-row">
            <span class="meta-key">Tipe</span>
            <span class="meta-val">{{ orderTypeLabel(data.order.order_type) }}</span>
          </div>
          <div v-if="data.order.table_number" class="receipt-meta-row">
            <span class="meta-key">Meja</span>
            <span class="meta-val">{{ data.order.table_number }}</span>
          </div>
          <div v-if="data.order.customer_name" class="receipt-meta-row">
            <span class="meta-key">Pelanggan</span>
            <span class="meta-val">{{ data.order.customer_name }}</span>
          </div>
        </div>

        <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>

        <!-- Items -->
        <div class="receipt-section-label">PESANAN ({{ data.order.items?.length || 0 }} ITEM)</div>
        <div class="receipt-items">
          <div v-for="item in data.order.items" :key="item.id" class="receipt-item-row">
            <div class="item-row-top">
              <span class="item-row-name">{{ item.menu_name }}</span>
              <span class="item-row-qty">×{{ item.quantity }}</span>
            </div>
            <div v-if="item.notes" class="item-row-notes">
              <i class="pi pi-comment"></i> {{ item.notes }}
            </div>
            <div v-if="item.station_name" class="item-row-station"
                 :style="{ color: item.station_color }">
              <i class="pi pi-cog"></i> {{ item.station_name }}
            </div>
          </div>
        </div>

        <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>

        <!-- Status Timeline -->
        <div class="receipt-section-label">STATUS PESANAN</div>
        <div class="receipt-timeline">
          <div v-for="(step, idx) in data.timeline" :key="step.key"
               class="timeline-row" :class="{ done: step.done, last: idx === data.timeline.length - 1 }">
            <!-- Connector line -->
            <div class="tl-line-wrap">
              <div class="tl-dot" :style="step.done ? { background: step.color, borderColor: step.color } : {}">
                <i :class="step.icon" :style="step.done ? { color: '#fff' } : {}"></i>
              </div>
              <div v-if="idx < data.timeline.length - 1" class="tl-connector"
                   :class="{ filled: step.done }"></div>
            </div>
            <!-- Content -->
            <div class="tl-content">
              <div class="tl-label" :class="{ active: step.done }">{{ step.label }}</div>
              <div v-if="step.done && step.time" class="tl-time">
                <i class="pi pi-clock"></i> {{ formatTime(step.time) }}
              </div>
              <div v-else-if="!step.done" class="tl-pending">
                <i class="pi pi-hourglass"></i> Menunggu...
              </div>
            </div>
          </div>
        </div>

        <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>

        <!-- Kitchen Stations -->
        <template v-for="station in data.stations" :key="station.station_id || 'no_station'">
          <div class="receipt-section-label" :style="{ color: station.station_color }">
            <span class="station-dot" :style="{ background: station.station_color }"></span>
            {{ station.station_name.toUpperCase() }}
          </div>
          <div class="receipt-station-items">
            <div v-for="item in station.items" :key="item.id" class="station-item-row">
              <div class="station-item-top">
                <span class="station-item-name">{{ item.menu_name }}</span>
                <span class="station-item-qty">×{{ item.quantity }}</span>
              </div>
              <div class="station-item-status"
                   :class="`item-status-${item.status}`">
                <i :class="itemIcon(item.status)"></i>
                {{ itemLabel(item.status) }}
              </div>
              <div v-if="item.notes" class="station-item-notes">
                <i class="pi pi-comment"></i> {{ item.notes }}
              </div>
            </div>
          </div>
          <div class="receipt-dashes">- - - - - - - - - - - - - - - - - - - - -</div>
        </template>

        <!-- Receipt Footer -->
        <div class="receipt-footer-text">
          <div>Terima kasih atas pesanan Anda</div>
          <div class="receipt-footer-sub">Scan QR untuk memantau status</div>
        </div>

        <!-- Perforated bottom edge -->
        <div class="receipt-tear"></div>

      </div>
      <!-- ═══ END RECEIPT ═══ -->

      <!-- Auto-refresh pill -->
      <div class="refresh-pill">
        <i class="pi pi-refresh spin-icon"></i>
        <span>Diperbarui otomatis dalam <strong>{{ countdown }}s</strong></span>
      </div>

    </div>

    <!-- Footer -->
    <div class="tracking-footer">
      Powered by Moira &bull; View Only
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

const loading   = ref(true)
const error     = ref(null)
const data      = ref(null)
const countdown = ref(10)
const REFRESH_INTERVAL = 10

let countdownTimer = null

const fetchTracking = async () => {
  try {
    const { outletId, orderCode } = route.params
    const res = await axios.get(`${API_URL}/track/${outletId}/${orderCode}`)
    data.value  = res.data
    error.value = null
  } catch (e) {
    error.value = e.response?.data?.message || 'Pesanan tidak ditemukan'
  } finally {
    loading.value = false
  }
}

const startAutoRefresh = () => {
  countdown.value = REFRESH_INTERVAL
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      countdown.value = REFRESH_INTERVAL
      fetchTracking()
    }
  }, 1000)
}

// ── Labels & helpers ──────────────────────────────────────────────────────────
const kitchenLabel = (s) => ({ pending: 'Menunggu', preparing: 'Diproses', ready: 'Siap', served: 'Disajikan' }[s] || 'Menunggu')
const kitchenIcon  = (s) => ({ pending: 'pi pi-clock', preparing: 'pi pi-spin pi-cog', ready: 'pi pi-bell', served: 'pi pi-check-circle' }[s] || 'pi pi-clock')
const itemLabel    = (s) => ({ pending: 'Antrian', preparing: 'Diproses', ready: 'Siap', served: 'Disajikan' }[s] || 'Antrian')
const itemIcon     = (s) => ({ pending: 'pi pi-circle', preparing: 'pi pi-spin pi-cog', ready: 'pi pi-check', served: 'pi pi-check-circle' }[s] || 'pi pi-circle')
const orderTypeLabel = (t) => ({ dine_in: 'Makan di Tempat', takeaway: 'Bungkus', delivery: 'Pengiriman' }[t] || t)

const formatTime = (ts) => {
  if (!ts) return ''
  return new Date(ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const formatDateTime = (ts) => {
  if (!ts) return ''
  return new Date(ts).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

onMounted(() => {
  fetchTracking()
  startAutoRefresh()
})

onUnmounted(() => clearInterval(countdownTimer))
</script>

<style scoped>
/* ── Page shell ──────────────────────────────────────────────────────────── */
.tracking-page {
  min-height: 100vh;
  background: #e8ecf0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #2A3547;
  display: flex;
  flex-direction: column;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.tracking-header {
  background: linear-gradient(135deg, #5D87FF 0%, #4A6FD6 100%);
  color: white;
  padding: 0.875rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(93,135,255,0.35);
  position: sticky;
  top: 0;
  z-index: 10;
}

.header-brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 700;
  font-size: 1.05rem;
}

.brand-icon { font-size: 1.3rem; }

.header-badge {
  background: rgba(255,255,255,0.22);
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.78rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  backdrop-filter: blur(8px);
  font-weight: 600;
}

/* ── States ──────────────────────────────────────────────────────────────── */
.state-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.25rem;
  padding: 3rem 1rem;
  color: #7C8FAC;
}

.state-center p { margin: 0; font-size: 0.9rem; }

.error-box {
  text-align: center;
  background: white;
  border-radius: 12px;
  padding: 2rem 1.5rem;
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  max-width: 320px;
}

.error-icon { font-size: 2.5rem; color: #FA896B; margin-bottom: 0.75rem; display: block; }
.error-box h3 { margin: 0 0 0.5rem; font-size: 1rem; color: #2A3547; }
.error-box p  { margin: 0; font-size: 0.85rem; color: #7C8FAC; }

/* ── Receipt wrapper ─────────────────────────────────────────────────────── */
.receipt-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1.5rem 1rem 1rem;
  gap: 1rem;
}

/* ── Receipt paper ───────────────────────────────────────────────────────── */
.receipt-paper {
  width: 100%;
  max-width: 380px;
  background: #fff;
  border-radius: 4px 4px 0 0;
  box-shadow:
    0 2px 4px rgba(0,0,0,0.06),
    0 8px 24px rgba(0,0,0,0.10),
    0 0 0 1px rgba(0,0,0,0.04);
  padding: 1.5rem 1.25rem 0;
  position: relative;
  /* Subtle paper texture */
  background-image: repeating-linear-gradient(
    0deg,
    transparent,
    transparent 27px,
    rgba(0,0,0,0.015) 27px,
    rgba(0,0,0,0.015) 28px
  );
}

/* ── Outlet header ───────────────────────────────────────────────────────── */
.receipt-top {
  text-align: center;
  margin-bottom: 1rem;
}

.receipt-outlet-name {
  font-size: 1.1rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #1a2332;
  margin-bottom: 0.3rem;
}

.receipt-outlet-address {
  font-size: 0.72rem;
  color: #7C8FAC;
  line-height: 1.5;
  margin-bottom: 0.5rem;
}

/* ── Dashed divider ──────────────────────────────────────────────────────── */
.receipt-dashes {
  text-align: center;
  font-size: 0.7rem;
  color: #c8d0da;
  letter-spacing: 0.05em;
  margin: 0.75rem 0;
  user-select: none;
  overflow: hidden;
}

/* ── Order code block ────────────────────────────────────────────────────── */
.receipt-order-block {
  text-align: center;
  margin: 0.5rem 0;
}

.receipt-order-label {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: #7C8FAC;
  margin-bottom: 0.35rem;
}

.receipt-order-code {
  font-size: 2rem;
  font-weight: 900;
  font-family: 'Courier New', 'Courier', monospace;
  letter-spacing: 0.12em;
  color: #1a2332;
  line-height: 1;
  margin-bottom: 0.75rem;
}

/* Status pill */
.receipt-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 1.1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.status-pending   { background: #FEF5E5; color: #FFAE1F; }
.status-preparing { background: #FEF5E5; color: #e08a00; }
.status-ready     { background: #ECF2FF; color: #5D87FF; }
.status-served    { background: #E6FFFA; color: #13DEB9; }

/* ── Meta block ──────────────────────────────────────────────────────────── */
.receipt-meta-block {
  margin: 0.25rem 0;
}

.receipt-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 0.8rem;
  padding: 0.3rem 0;
  border-bottom: 1px dotted #edf0f4;
}

.receipt-meta-row:last-child { border-bottom: none; }

.meta-key {
  color: #7C8FAC;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.meta-val {
  color: #2A3547;
  font-weight: 700;
  text-align: right;
  margin-left: 0.5rem;
}

/* ── Section label ───────────────────────────────────────────────────────── */
.receipt-section-label {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: #7C8FAC;
  text-transform: uppercase;
  margin-bottom: 0.6rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.station-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

/* ── Items ───────────────────────────────────────────────────────────────── */
.receipt-items {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.receipt-item-row {
  padding: 0.5rem 0.6rem;
  background: #f8fafc;
  border-radius: 4px;
  border-left: 3px solid #5D87FF;
}

.item-row-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.item-row-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1a2332;
  flex: 1;
}

.item-row-qty {
  font-size: 0.8rem;
  font-weight: 700;
  color: #5D87FF;
  background: #ECF2FF;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  flex-shrink: 0;
}

.item-row-notes {
  font-size: 0.72rem;
  color: #7C8FAC;
  font-style: italic;
  margin-top: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.item-row-notes i { font-size: 0.65rem; }

.item-row-station {
  font-size: 0.72rem;
  font-weight: 600;
  margin-top: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.item-row-station i { font-size: 0.65rem; }

/* ── Timeline ────────────────────────────────────────────────────────────── */
.receipt-timeline {
  display: flex;
  flex-direction: column;
  margin-bottom: 0.25rem;
}

.timeline-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.tl-line-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 28px;
}

.tl-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #dde3ea;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.3s;
  z-index: 1;
}

.tl-dot i {
  font-size: 0.75rem;
  color: #c0c9d4;
}

.timeline-row.done .tl-dot i { color: #fff; }

.tl-connector {
  width: 2px;
  flex: 1;
  min-height: 20px;
  background: #dde3ea;
  margin: 2px 0;
}

.tl-connector.filled { background: #5D87FF; }

.tl-content {
  padding: 0.3rem 0 0.75rem;
  flex: 1;
}

.tl-label {
  font-size: 0.82rem;
  font-weight: 500;
  color: #9ca3af;
}

.tl-label.active {
  color: #1a2332;
  font-weight: 700;
}

.tl-time {
  font-size: 0.7rem;
  color: #5D87FF;
  font-weight: 700;
  margin-top: 0.2rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.tl-time i { font-size: 0.65rem; }

.tl-pending {
  font-size: 0.7rem;
  color: #b0bac5;
  margin-top: 0.2rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  animation: pulse-text 2s ease-in-out infinite;
}

.tl-pending i { font-size: 0.65rem; }

@keyframes pulse-text {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* ── Station items ───────────────────────────────────────────────────────── */
.receipt-station-items {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin-bottom: 0.25rem;
}

.station-item-row {
  padding: 0.5rem 0.6rem;
  background: #f8fafc;
  border-radius: 4px;
  border-left: 3px solid #e5eaef;
}

.station-item-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}

.station-item-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: #1a2332;
  flex: 1;
}

.station-item-qty {
  font-size: 0.75rem;
  font-weight: 700;
  color: #7C8FAC;
  background: #f0f4f8;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  flex-shrink: 0;
}

.station-item-status {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 12px;
}

.item-status-pending   { background: #f3f4f6; color: #9ca3af; }
.item-status-preparing { background: #FEF5E5; color: #e08a00; }
.item-status-ready     { background: #ECF2FF; color: #5D87FF; }
.item-status-served    { background: #E6FFFA; color: #13DEB9; }

.station-item-notes {
  font-size: 0.7rem;
  color: #7C8FAC;
  font-style: italic;
  margin-top: 0.3rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.station-item-notes i { font-size: 0.65rem; }

/* ── Receipt footer text ─────────────────────────────────────────────────── */
.receipt-footer-text {
  text-align: center;
  padding: 1rem 0 1.25rem;
  font-size: 0.78rem;
  color: #7C8FAC;
  font-weight: 600;
  letter-spacing: 0.03em;
}

.receipt-footer-sub {
  font-size: 0.68rem;
  color: #b0bac5;
  margin-top: 0.25rem;
  font-weight: 400;
}

/* ── Perforated tear edge ────────────────────────────────────────────────── */
.receipt-tear {
  height: 16px;
  background:
    radial-gradient(circle at 0 50%, #e8ecf0 8px, transparent 8px),
    radial-gradient(circle at 100% 50%, #e8ecf0 8px, transparent 8px);
  background-size: 20px 16px;
  background-position: 0 0, 10px 0;
  background-repeat: repeat-x;
  margin: 0 -1.25rem;
  position: relative;
}

/* ── Refresh pill ────────────────────────────────────────────────────────── */
.refresh-pill {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  border: 1px solid #dde3ea;
  border-radius: 20px;
  padding: 0.5rem 1rem;
  font-size: 0.78rem;
  color: #7C8FAC;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.refresh-pill strong { color: #5D87FF; }

.spin-icon {
  font-size: 0.85rem;
  color: #5D87FF;
  animation: spin-anim 2s linear infinite;
}

@keyframes spin-anim {
  to { transform: rotate(360deg); }
}

/* ── Footer ──────────────────────────────────────────────────────────────── */
.tracking-footer {
  text-align: center;
  padding: 0.875rem;
  font-size: 0.72rem;
  color: #b0bac5;
  background: #e8ecf0;
}

/* ── Mobile tweaks ───────────────────────────────────────────────────────── */
@media (max-width: 480px) {
  .receipt-wrapper { padding: 1rem 0.5rem 0.75rem; }

  .receipt-paper {
    max-width: 100%;
    border-radius: 4px 4px 0 0;
    padding: 1.25rem 1rem 0;
  }

  .receipt-order-code { font-size: 1.75rem; }

  .receipt-tear { margin: 0 -1rem; }
}
</style>
