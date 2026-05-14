<template>
  <div class="kds-view" :style="{ '--station-color': currentStation?.warna || '#3b82f6' }">

    <!-- Header -->
    <div class="kds-header">
      <div class="kds-header-left">
        <div class="station-badge" v-if="currentStation">
          <i :class="currentStation.icon"></i>
          <span>{{ currentStation.nama }}</span>
        </div>
        <div class="kds-clock">{{ currentTime }}</div>
      </div>
      <div class="kds-header-right">
        <!-- Station switcher -->
        <div class="station-switcher">
          <Button v-for="s in stations" :key="s.id"
                  :label="s.nama" :icon="s.icon"
                  :outlined="currentStation?.id !== s.id"
                  size="small"
                  :style="currentStation?.id === s.id ? { background: s.warna, borderColor: s.warna } : { borderColor: s.warna, color: s.warna }"
                  @click="switchStation(s)" />
        </div>
        <Button :icon="isStandalone ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
                :label="isStandalone ? $t('kds.exitFullscreen') : $t('kds.fullscreen')"
                outlined size="small"
                @click="toggleFullscreen"
                v-tooltip.bottom="(isStandalone ? $t('kds.exitFullscreen') : $t('kds.fullscreen')) + ' (F)'" />
        <Button v-if="!isStandalone" :label="$t('common.back')" icon="pi pi-arrow-left" text
                @click="router.push(`/outlets/${outletId}/dashboard`)" />
      </div>
    </div>

    <!-- Stats bar -->
    <div class="kds-stats">
      <div class="stat-item">
        <span class="stat-num">{{ pendingOrders.length }}</span>
        <span class="stat-label">{{ $t('kds.pending') }}</span>
      </div>
      <div class="stat-item preparing">
        <span class="stat-num">{{ preparingOrders.length }}</span>
        <span class="stat-label">{{ $t('kds.preparing') }}</span>
      </div>
      <div class="stat-item ready">
        <span class="stat-num">{{ readyOrders.length }}</span>
        <span class="stat-label">{{ $t('kds.ready') }}</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="kds-loading">
      <i class="pi pi-spin pi-spinner"></i>
      <span>{{ $t('common.loading') }}</span>
    </div>

    <!-- No station selected -->
    <div v-else-if="!currentStation" class="kds-empty">
      <i class="pi pi-box"></i>
      <p>{{ $t('kds.selectStation') }}</p>
    </div>

    <!-- No orders -->
    <div v-else-if="orders.length === 0" class="kds-empty">
      <i class="pi pi-check-circle" style="color: #22c55e;"></i>
      <p>{{ $t('kds.noOrders') }}</p>
      <small>{{ $t('kds.noOrdersHint') }}</small>
    </div>

    <!-- Orders grid -->
    <div v-else class="kds-grid">
      <div v-for="order in orders" :key="order.id"
           class="order-card" :class="getOrderClass(order)">

        <!-- Card header -->
        <div class="order-card-header">
          <div class="order-card-left">
            <span class="order-kode">{{ order.kode }}</span>
            <Tag v-if="order.order_type === 'dine_in'" :value="$t('pos.dineIn')" severity="info" size="small" />
            <Tag v-else :value="$t('pos.takeaway')" severity="secondary" size="small" />
          </div>
          <div class="order-card-right">
            <span v-if="order.table_number" class="table-badge">
              <i class="pi pi-home"></i> {{ order.table_number }}
            </span>
            <span class="order-time">{{ formatTime(order.created_at) }}</span>
            <span class="order-elapsed" :class="getElapsedClass(order.created_at)">
              {{ getElapsed(order.created_at) }}
            </span>
          </div>
        </div>

        <!-- Customer note -->
        <div v-if="order.notes" class="order-notes">
          <i class="pi pi-comment"></i> {{ order.notes }}
        </div>

        <!-- Items -->
        <div class="order-items">
          <div v-for="item in order.items" :key="item.id"
               class="order-item" :class="'item-' + item.status">
            <div class="item-left">
              <span class="item-qty">{{ item.quantity }}×</span>
              <span class="item-name">{{ item.menu_name }}</span>
              <span v-if="item.notes" class="item-note">— {{ item.notes }}</span>
            </div>
            <div class="item-status-badge">
              <Tag v-if="item.status === 'ready'" :value="$t('kds.ready')" severity="success" size="small" />
              <Tag v-else-if="item.status === 'preparing'" :value="$t('kds.preparing')" severity="warn" size="small" />
              <Tag v-else :value="$t('kds.pending')" severity="secondary" size="small" />
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="order-card-footer">
          <!-- Start all pending items -->
          <Button v-if="hasPendingItems(order)"
                  :label="$t('kds.startAll')" icon="pi pi-play"
                  size="small" outlined
                  :style="{ borderColor: currentStation?.warna, color: currentStation?.warna }"
                  @click="startAllItems(order)" :loading="actionLoading === order.id + '_start'" />

          <!-- Confirm all items ready -->
          <Button v-if="hasPreparingItems(order)"
                  :label="$t('kds.confirmAll')" icon="pi pi-check"
                  size="small"
                  :style="{ background: currentStation?.warna, borderColor: currentStation?.warna }"
                  @click="confirmAllItems(order)" :loading="actionLoading === order.id + '_confirm'" />

          <!-- All ready badge + Serve button -->
          <template v-if="allItemsReady(order)">
            <div class="all-ready-badge">
              <i class="pi pi-check-circle"></i>
              {{ $t('kds.allReady') }}
            </div>
            <Button :label="$t('kds.served')" icon="pi pi-send"
                    size="small" severity="success"
                    @click="serveOrder(order)" :loading="actionLoading === order.id + '_serve'" />
          </template>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId

const stations = ref([])
const currentStation = ref(null)
const orders = ref([])
const loading = ref(false)
const actionLoading = ref(null)
const currentTime = ref('')
const previousOrderCount = ref(0)
const isFullscreen = ref(false)
const isStandalone = computed(() => route.meta.standalone === true)
let refreshInterval = null
let clockInterval = null
let audioContext = null

// ── Computed ──────────────────────────────────────────────────────────────────

const pendingOrders = computed(() =>
  orders.value.filter(o => o.items.every(i => i.status === 'pending'))
)
const preparingOrders = computed(() =>
  orders.value.filter(o => o.items.some(i => i.status === 'preparing') && !o.items.every(i => i.status === 'ready'))
)
const readyOrders = computed(() =>
  orders.value.filter(o => o.items.every(i => i.status === 'ready'))
)

// ── Helpers ───────────────────────────────────────────────────────────────────

// Play notification sound
const playNotificationSound = () => {
  try {
    // Initialize AudioContext if not exists
    if (!audioContext) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
    }
    
    // Create a simple notification sound (3 beeps)
    const beep = (frequency, duration, delay) => {
      setTimeout(() => {
        const oscillator = audioContext.createOscillator()
        const gainNode = audioContext.createGain()
        
        oscillator.connect(gainNode)
        gainNode.connect(audioContext.destination)
        
        oscillator.frequency.value = frequency
        oscillator.type = 'sine'
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration)
        
        oscillator.start(audioContext.currentTime)
        oscillator.stop(audioContext.currentTime + duration)
      }, delay)
    }
    
    // Play 3 beeps: high-mid-high pattern
    beep(800, 0.15, 0)      // First beep
    beep(600, 0.15, 200)    // Second beep (lower)
    beep(800, 0.2, 400)     // Third beep (higher, longer)
    
  } catch (error) {
    console.error('Failed to play notification sound:', error)
  }
}

const hasPendingItems  = (o) => o.items.some(i => i.status === 'pending')
const hasPreparingItems = (o) => o.items.some(i => i.status === 'preparing')
const allItemsReady    = (o) => o.items.length > 0 && o.items.every(i => i.status === 'ready')

const getOrderClass = (order) => {
  if (allItemsReady(order)) return 'order-ready'
  if (order.items.some(i => i.status === 'preparing')) return 'order-preparing'
  return 'order-pending'
}

const formatTime = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const getElapsed = (dt) => {
  if (!dt) return ''
  const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000)
  if (diff < 60) return `${diff}d`
  const m = Math.floor(diff / 60)
  if (m < 60) return `${m}m`
  return `${Math.floor(m / 60)}j ${m % 60}m`
}

const getElapsedClass = (dt) => {
  if (!dt) return ''
  const mins = Math.floor((Date.now() - new Date(dt).getTime()) / 60000)
  if (mins >= 15) return 'elapsed-danger'
  if (mins >= 8) return 'elapsed-warn'
  return 'elapsed-ok'
}

// ── Data fetching ─────────────────────────────────────────────────────────────

const fetchStations = async () => {
  try {
    const res = await api.get(`/outlets/${outletId}/stations`)
    stations.value = res.data.filter(s => s.is_active)
    // Auto-select first station or the one from route query
    const stationId = route.query.station ? parseInt(route.query.station) : null
    if (stationId) {
      currentStation.value = stations.value.find(s => s.id === stationId) || stations.value[0] || null
    } else {
      currentStation.value = stations.value[0] || null
    }
    if (currentStation.value) fetchOrders()
  } catch (e) {
    console.error(e)
  }
}

const fetchOrders = async () => {
  if (!currentStation.value) return
  loading.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/stations/${currentStation.value.id}/orders`)
    const newOrders = res.data.orders
    
    // Check if there are new orders (order count increased)
    if (previousOrderCount.value > 0 && newOrders.length > previousOrderCount.value) {
      playNotificationSound()
      
      // Show toast notification
      const newOrdersCount = newOrders.length - previousOrderCount.value
      toast.add({
        severity: 'info',
        summary: t('kds.newOrder'),
        detail: t('kds.newOrderMessage', { count: newOrdersCount }),
        life: 5000
      })
    }
    
    previousOrderCount.value = newOrders.length
    orders.value = newOrders
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const switchStation = (station) => {
  currentStation.value = station
  orders.value = []
  previousOrderCount.value = 0 // Reset count when switching station
  fetchOrders()
}

// ── Actions ───────────────────────────────────────────────────────────────────

// Toggle fullscreen (standalone mode)
const toggleFullscreen = () => {
  if (isStandalone.value) {
    // Exit standalone mode - go back to normal kitchen view
    router.push(`/outlets/${outletId}/kitchen`)
  } else {
    // Enter standalone mode - redirect to fullscreen route
    router.push(`/outlets/${outletId}/kitchen/fullscreen`)
  }
}

// Keyboard shortcut for fullscreen (F key)
const handleKeyPress = (event) => {
  if (event.key === 'f' || event.key === 'F') {
    // Only trigger if not typing in an input
    if (!['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
      event.preventDefault()
      toggleFullscreen()
    }
  }
}

const startAllItems = async (order) => {
  actionLoading.value = order.id + '_start'
  try {
    const pendingItems = order.items.filter(i => i.status === 'pending')
    await Promise.all(pendingItems.map(item =>
      api.post(`/outlets/${outletId}/stations/${currentStation.value.id}/items/${item.id}/start`)
    ))
    await fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    actionLoading.value = null
  }
}

const confirmAllItems = async (order) => {
  actionLoading.value = order.id + '_confirm'
  try {
    const preparingItems = order.items.filter(i => i.status === 'preparing')
    await Promise.all(preparingItems.map(item =>
      api.post(`/outlets/${outletId}/stations/${currentStation.value.id}/items/${item.id}/confirm`)
    ))
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kds.orderReady'), life: 3000 })
    await fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    actionLoading.value = null
  }
}

const serveOrder = async (order) => {
  actionLoading.value = order.id + '_serve'
  try {
    await api.post(`/outlets/${outletId}/stations/${currentStation.value.id}/orders/${order.id}/serve`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kds.orderServed'), life: 3000 })
    await fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    actionLoading.value = null
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
  fetchStations()

  // Auto-refresh every 15 seconds
  refreshInterval = setInterval(fetchOrders, 15000)

  // Clock
  const updateClock = () => {
    currentTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  }
  updateClock()
  clockInterval = setInterval(updateClock, 1000)
  
  // Listen to keyboard shortcuts
  document.addEventListener('keydown', handleKeyPress)
})

onBeforeUnmount(() => {
  clearInterval(refreshInterval)
  clearInterval(clockInterval)
  document.removeEventListener('keydown', handleKeyPress)
})
</script>

<style scoped>
.kds-view {
  min-height: 100vh;
  background: #0f172a;
  color: white;
  display: flex;
  flex-direction: column;
  padding: 0;
}

/* Header */
.kds-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1.25rem;
  background: #1e293b;
  border-bottom: 2px solid var(--station-color);
  gap: 1rem;
  flex-wrap: wrap;
}
.kds-header-left { display: flex; align-items: center; gap: 1rem; }
.kds-header-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

.station-badge {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1.25rem; font-weight: 700;
  color: var(--station-color);
}
.station-badge i { font-size: 1.5rem; }

.kds-clock { font-size: 1.1rem; font-family: monospace; color: #94a3b8; }

.station-switcher { display: flex; gap: 0.4rem; flex-wrap: wrap; }

/* Stats */
.kds-stats {
  display: flex;
  gap: 0;
  background: #1e293b;
  border-bottom: 1px solid #334155;
}
.stat-item {
  flex: 1; text-align: center; padding: 0.6rem 1rem;
  border-right: 1px solid #334155;
}
.stat-item:last-child { border-right: none; }
.stat-item.preparing { background: rgba(234, 179, 8, 0.1); }
.stat-item.ready { background: rgba(34, 197, 94, 0.1); }
.stat-num { display: block; font-size: 1.75rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }

/* Loading / Empty */
.kds-loading, .kds-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0.75rem; color: #64748b; padding: 3rem;
}
.kds-loading i, .kds-empty i { font-size: 3rem; }
.kds-empty p { font-size: 1.1rem; margin: 0; }
.kds-empty small { color: #475569; }

/* Orders grid */
.kds-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
  padding: 1rem;
  align-content: start;
}

/* Order card */
.order-card {
  background: #1e293b;
  border-radius: 10px;
  border: 2px solid #334155;
  overflow: hidden;
  transition: border-color 0.2s;
}
.order-pending { border-color: #475569; }
.order-preparing { border-color: #eab308; }
.order-ready { border-color: #22c55e; }

.order-card-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 0.75rem 1rem 0.5rem;
  background: #0f172a;
  gap: 0.5rem;
}
.order-card-left { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.order-card-right { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }

.order-kode { font-family: monospace; font-weight: 700; font-size: 1rem; color: var(--station-color); }
.table-badge { font-size: 0.75rem; color: #94a3b8; display: flex; align-items: center; gap: 0.25rem; }
.order-time { font-size: 0.8rem; color: #64748b; }
.order-elapsed { font-size: 0.75rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; }
.elapsed-ok { background: #166534; color: #86efac; }
.elapsed-warn { background: #854d0e; color: #fde68a; }
.elapsed-danger { background: #7f1d1d; color: #fca5a5; }

.order-notes {
  padding: 0.4rem 1rem;
  font-size: 0.8rem; color: #fbbf24;
  background: rgba(251, 191, 36, 0.08);
  display: flex; align-items: center; gap: 0.4rem;
}

/* Items */
.order-items { padding: 0.5rem 1rem; display: flex; flex-direction: column; gap: 0.4rem; }
.order-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 0.5rem 0.75rem; border-radius: 6px;
  background: #0f172a;
  border-left: 3px solid #334155;
}
.item-pending { border-left-color: #475569; }
.item-preparing { border-left-color: #eab308; background: rgba(234, 179, 8, 0.05); }
.item-ready { border-left-color: #22c55e; background: rgba(34, 197, 94, 0.05); opacity: 0.7; }

.item-left { display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0; }
.item-qty { font-weight: 800; font-size: 1.1rem; color: var(--station-color); min-width: 28px; }
.item-name { font-weight: 600; font-size: 0.9rem; }
.item-note { font-size: 0.75rem; color: #94a3b8; font-style: italic; }
.item-status-badge { flex-shrink: 0; }

/* Footer */
.order-card-footer {
  padding: 0.75rem 1rem;
  display: flex; gap: 0.5rem; align-items: center;
  border-top: 1px solid #334155;
  background: #0f172a;
}
.all-ready-badge {
  display: flex; align-items: center; gap: 0.4rem;
  color: #22c55e; font-weight: 700; font-size: 0.875rem;
}
</style>
