<template>
  <div class="pos-status-page">
    <div v-if="loading" class="state">
      <div class="spinner"></div>
      <p>{{ t('publicOrder.loading') }}</p>
    </div>

    <div v-else-if="loadError" class="state error">
      <i class="pi pi-exclamation-triangle"></i>
      <h2>{{ t('publicOrder.notFound') }}</h2>
      <p>{{ loadError }}</p>
    </div>

    <template v-else>
      <header class="hdr">
        <h1>{{ t('publicOrder.orderTitle') }}</h1>
        <div class="kode">{{ order.kode }}</div>
      </header>

      <!-- Status visualization -->
      <div class="status-card" :class="statusClass">
        <div class="icon">
          <i :class="statusIcon"></i>
        </div>
        <h2>{{ statusLabel }}</h2>
        <p v-if="isPending" class="sub">
          {{ t('publicOrder.waitMessage') }}
        </p>
        <p v-else-if="isApproved" class="sub">
          {{ t('publicOrder.approvedMessage') }}
        </p>
        <p v-else-if="isRejected" class="sub">
          {{ t('publicOrder.rejectedMessage') }}
          <span v-if="order.rejection_reason"><br />“{{ order.rejection_reason }}”</span>
        </p>

        <!-- Countdown -->
        <div v-if="isPending" class="timer">
          <i class="pi pi-clock"></i> {{ formatCountdown(elapsed) }}
          <span class="of">/ ~60{{ t('publicOrder.seconds') }}</span>
        </div>
      </div>

      <!-- Items list -->
      <section class="card">
        <div class="card-head">
          <h3>{{ t('publicOrder.items') }}</h3>
          <span class="badge">{{ items.length }}</span>
        </div>
        <ul class="items">
          <li v-for="it in items" :key="it.id">
            <div class="i-name">
              <strong>{{ it.quantity }}×</strong> {{ it.menu_name }}
              <div v-if="it.notes" class="i-notes">“{{ it.notes }}”</div>
            </div>
            <div class="i-price">{{ formatIdr(it.subtotal) }}</div>
          </li>
        </ul>
      </section>

      <!-- Totals -->
      <section class="card">
        <div class="row"><span>{{ t('publicOrder.subtotal') }}</span><span>{{ formatIdr(order.subtotal) }}</span></div>
        <div v-if="Number(order.tax_amount) > 0" class="row">
          <span>{{ t('publicOrder.tax') }}</span>
          <span>{{ formatIdr(order.tax_amount) }}</span>
        </div>
        <div v-if="Number(order.service_charge_amount) > 0" class="row">
          <span>{{ t('publicOrder.service') }}</span>
          <span>{{ formatIdr(order.service_charge_amount) }}</span>
        </div>
        <div class="row total">
          <span>{{ t('publicOrder.total') }}</span>
          <strong>{{ formatIdr(order.total_amount) }}</strong>
        </div>
      </section>

      <section class="card">
        <div v-if="order.order_type === 'takeaway'" class="row">
          <span>{{ t('publicOrder.takeaway') }}</span><span>—</span>
        </div>
        <div v-else class="row">
          <span>{{ t('publicOrder.table') }}</span><span>{{ order.table_number }}</span>
        </div>
        <div class="row"><span>{{ t('publicOrder.phone') }}</span><span>{{ order.customer_phone }}</span></div>
        <div class="row"><span>{{ t('publicOrder.email') }}</span><span>{{ order.customer_email }}</span></div>
        <div v-if="paymentProofUrl" class="row">
          <span>{{ t('publicOrder.paymentProof') }}</span>
          <a :href="paymentProofUrl" target="_blank" rel="noopener" style="color:#6366f1; font-weight:600;">
            <i class="pi pi-image"></i>
          </a>
        </div>
      </section>

      <p class="hint">
        <i class="pi pi-info-circle"></i> {{ t('publicOrder.statusHint') }}
      </p>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const route = useRoute()
const { t } = useI18n()

const outletSlug = route.params.outletSlug
const orderCode = route.params.orderCode

const loading = ref(true)
const loadError = ref('')
const order = ref({})
const items = ref([])
const paymentProofUrl = ref('')
const elapsed = ref(0)

let pollTimer = null
let tickTimer = null

async function load() {
  try {
    const res = await api.get(`/public/outlet/${outletSlug}/order/${orderCode}`)
    order.value = res.data.order || {}
    items.value = res.data.items || []
    paymentProofUrl.value = res.data.payment_proof_url || ''
    if (order.value.created_at) {
      const created = new Date(order.value.created_at).getTime()
      elapsed.value = Math.floor((Date.now() - created) / 1000)
    }
    loading.value = false
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Pesanan tidak ditemukan'
    loading.value = false
  }
}

const isPending = computed(() => order.value.approval_status === 'pending')
const isApproved = computed(() => order.value.approval_status === 'approved')
const isRejected = computed(() => order.value.approval_status === 'rejected')

const statusLabel = computed(() => {
  if (isPending.value) return t('publicOrder.statusPending')
  if (isApproved.value) return t('publicOrder.statusApproved')
  if (isRejected.value) return t('publicOrder.statusRejected')
  return order.value.status || ''
})
const statusClass = computed(() => {
  if (isPending.value) return 'pending'
  if (isApproved.value) return 'approved'
  if (isRejected.value) return 'rejected'
  return ''
})
const statusIcon = computed(() => {
  if (isPending.value) return 'pi pi-clock'
  if (isApproved.value) return 'pi pi-check-circle'
  if (isRejected.value) return 'pi pi-times-circle'
  return 'pi pi-info-circle'
})

function formatIdr(v) {
  return 'Rp ' + (Number(v) || 0).toLocaleString('id-ID')
}

function formatCountdown(sec) {
  const s = Math.max(0, sec)
  const m = Math.floor(s / 60)
  const r = s % 60
  return `${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`
}

onMounted(async () => {
  await load()
  pollTimer = setInterval(async () => {
    if (!isPending.value) return
    await load()
  }, 5000)
  tickTimer = setInterval(() => {
    if (isPending.value) elapsed.value++
  }, 1000)
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (tickTimer) clearInterval(tickTimer)
})
</script>

<style scoped>
.pos-status-page {
  min-height: 100vh;
  background: #f6f7fb;
  padding: 20px 16px 40px;
  max-width: 480px;
  margin: 0 auto;
  font-family: 'Inter', -apple-system, sans-serif;
  color: #1a1a1a;
}
.state {
  min-height: 50vh; display:flex; flex-direction:column;
  align-items:center; justify-content:center; text-align:center;
}
.state.error i { font-size:48px; color:#f87171; margin-bottom:8px; }
.spinner {
  width:36px; height:36px; border:3px solid #e5e7eb;
  border-top-color:#6366f1; border-radius:50%;
  animation:spin .8s linear infinite; margin-bottom:12px;
}
@keyframes spin { to { transform:rotate(360deg); } }

.hdr { text-align:center; margin-bottom: 16px; }
.hdr h1 { margin:0; font-size:18px; }
.hdr .kode { font-size:13px; color:#6b7280; margin-top:4px; }

.status-card {
  background:#fff; border-radius:14px; padding:20px;
  text-align:center; margin-bottom: 14px;
  box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.status-card .icon { font-size:48px; margin-bottom:6px; }
.status-card.pending .icon { color:#f59e0b; animation: pulse 1.6s infinite; }
.status-card.approved .icon { color:#10b981; }
.status-card.rejected .icon { color:#ef4444; }
@keyframes pulse {
  0%,100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}
.status-card h2 { margin: 4px 0 6px; font-size:18px; }
.status-card .sub { font-size:13px; color:#6b7280; margin: 6px 0 0; }
.timer {
  margin-top:14px; display:inline-flex; align-items:center; gap:6px;
  background:#fef3c7; color:#92400e; padding:6px 12px; border-radius:999px;
  font-weight:600; font-size:13px;
}
.timer .of { font-weight:400; opacity:.7; margin-left:2px; }

.card {
  background:#fff; border-radius:14px; padding:14px;
  margin-bottom:10px; box-shadow:0 1px 3px rgba(0,0,0,.04);
}
.card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.card h3 { margin:0; font-size:14px; }
.badge {
  background:#eef2ff; color:#4f46e5; font-size:12px; padding:2px 8px;
  border-radius:999px; font-weight:600;
}
.items { list-style:none; padding:0; margin:0; }
.items li { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6; font-size:13px; }
.items li:last-child { border-bottom:none; }
.i-notes { font-size:11px; color:#9ca3af; margin-top:2px; }
.row { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
.row.total { border-top:1px dashed #e5e7eb; padding-top:8px; margin-top:6px; font-size:14px; }
.row.total strong { color:#6366f1; }
.hint { font-size:12px; color:#9ca3af; text-align:center; margin-top:16px; }
.hint i { margin-right:4px; }
</style>
