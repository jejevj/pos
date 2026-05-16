<template>
  <div class="pto-page">
    <!-- Loading -->
    <div v-if="loading" class="state">
      <div class="spinner"></div>
      <p>{{ t('publicOrder.loading') }}</p>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="state error">
      <i class="pi pi-exclamation-triangle"></i>
      <h2>{{ t('publicOrder.notFound') }}</h2>
      <p>{{ loadError }}</p>
    </div>

    <!-- Unavailable: table exists but cannot accept orders right now -->
    <div v-else-if="!isOrderable" class="state unavailable">
      <i class="pi pi-info-circle"></i>
      <h2>{{ t('publicOrder.unavailableTitle') }}</h2>
      <p>{{ unavailableMessage }}</p>
      <div class="unavailable-meta" v-if="outlet.name">
        <strong>{{ outlet.name }}</strong>
        <span v-if="table.table_number"> · {{ t('publicOrder.table') }} {{ table.table_number }}</span>
      </div>
    </div>

    <template v-else>
      <!-- Header -->
      <header class="pto-header">
        <div class="brand">
          <img v-if="outlet.logo" :src="outlet.logo" alt="logo" class="logo" />
          <div v-else class="logo-fallback"><i class="pi pi-shop"></i></div>
          <div>
            <div class="outlet-name">{{ outlet.name }}</div>
            <div class="table-label">
              <i class="pi pi-th-large"></i> {{ t('publicOrder.table') }} {{ table.table_number }}
            </div>
          </div>
        </div>
        <button class="cart-btn" @click="cartOpen = true">
          <i class="pi pi-shopping-cart"></i>
          <span v-if="cartCount" class="badge">{{ cartCount }}</span>
        </button>
      </header>

      <!-- Search + Category Tabs -->
      <div class="pto-controls">
        <div class="search">
          <i class="pi pi-search"></i>
          <input
            v-model="search"
            :placeholder="t('publicOrder.searchMenu')"
            type="text"
          />
        </div>
        <div class="tabs">
          <button
            class="tab"
            :class="{ active: activeCategory === null }"
            @click="activeCategory = null"
          >
            {{ t('publicOrder.all') }}
          </button>
          <button
            v-for="c in categories"
            :key="c.id"
            class="tab"
            :class="{ active: activeCategory === c.id }"
            @click="activeCategory = c.id"
          >
            {{ c.nama }}
          </button>
        </div>
      </div>

      <!-- Menu Grid -->
      <main class="pto-menu">
        <div
          v-for="m in filteredMenu"
          :key="m.id"
          class="menu-card"
        >
          <div class="menu-img">
            <img v-if="m.gambar_url" :src="m.gambar_url" :alt="m.nama" />
            <div v-else class="img-fallback">{{ initials(m.nama) }}</div>
          </div>
          <div class="menu-body">
            <div class="menu-name">{{ m.nama }}</div>
            <div v-if="m.deskripsi" class="menu-desc">{{ m.deskripsi }}</div>
            <div class="menu-row">
              <div class="menu-price">{{ formatIdr(m.harga_jual) }}</div>
              <div class="qty-control">
                <button
                  class="qty-btn"
                  :disabled="!getQty(m.id)"
                  @click="decQty(m)"
                  :aria-label="'minus'"
                >
                  <i class="pi pi-minus"></i>
                </button>
                <span class="qty">{{ getQty(m.id) }}</span>
                <button class="qty-btn add" @click="incQty(m)" :aria-label="'plus'">
                  <i class="pi pi-plus"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!filteredMenu.length" class="empty">
          <i class="pi pi-inbox"></i>
          <p>{{ t('publicOrder.noMenu') }}</p>
        </div>
      </main>

      <!-- Cart bar -->
      <footer v-if="cartCount" class="pto-footer">
        <div class="totals">
          <span>{{ cartCount }} {{ t('publicOrder.item') }}</span>
          <strong>{{ formatIdr(cartSubtotal) }}</strong>
        </div>
        <button class="primary" @click="cartOpen = true">
          {{ t('publicOrder.viewCart') }}
        </button>
      </footer>

      <!-- Cart sheet -->
      <div v-if="cartOpen" class="sheet-backdrop" @click.self="cartOpen = false">
        <div class="sheet">
          <div class="sheet-header">
            <h3>{{ t('publicOrder.yourOrder') }}</h3>
            <button class="icon-btn" @click="cartOpen = false">
              <i class="pi pi-times"></i>
            </button>
          </div>

          <div v-if="!cart.length" class="empty">
            <i class="pi pi-shopping-cart"></i>
            <p>{{ t('publicOrder.cartEmpty') }}</p>
          </div>

          <ul v-else class="cart-list">
            <li v-for="c in cart" :key="c.menu_id" class="cart-item">
              <div class="ci-info">
                <div class="ci-name">{{ c.menu_name }}</div>
                <div class="ci-price">{{ formatIdr(c.menu_price) }} × {{ c.quantity }}</div>
                <input
                  v-model="c.notes"
                  type="text"
                  class="ci-notes"
                  :placeholder="t('publicOrder.noteOptional')"
                />
              </div>
              <div class="qty-control">
                <button class="qty-btn" @click="changeCartQty(c, -1)">
                  <i class="pi pi-minus"></i>
                </button>
                <span class="qty">{{ c.quantity }}</span>
                <button class="qty-btn add" @click="changeCartQty(c, 1)">
                  <i class="pi pi-plus"></i>
                </button>
              </div>
            </li>
          </ul>

          <!-- Customer form -->
          <form v-if="cart.length" class="form" @submit.prevent="submitOrder">
            <h4>{{ t('publicOrder.contact') }}</h4>
            <div class="field">
              <label>{{ t('publicOrder.name') }}</label>
              <input v-model="form.customer_name" type="text" :placeholder="t('publicOrder.namePh')" />
            </div>
            <div class="field">
              <label>{{ t('publicOrder.phone') }} <span class="req">*</span></label>
              <input
                v-model="form.customer_phone"
                type="tel"
                inputmode="tel"
                :placeholder="t('publicOrder.phonePh')"
                required
              />
            </div>
            <div class="field">
              <label>{{ t('publicOrder.email') }} <span class="req">*</span></label>
              <input
                v-model="form.customer_email"
                type="email"
                :placeholder="t('publicOrder.emailPh')"
                required
              />
            </div>
            <div class="field">
              <label>{{ t('publicOrder.memberCardOptional') }}</label>
              <input
                v-model="form.member_card"
                type="text"
                :placeholder="t('publicOrder.memberCardPh')"
              />
              <small class="hint">{{ t('publicOrder.memberHint') }}</small>
              <a
                v-if="settings.membership_open"
                class="link"
                :href="`/m/${outletSlug}`"
                target="_blank"
                >{{ t('publicOrder.registerMember') }}</a
              >
            </div>
            <div class="field">
              <label>{{ t('publicOrder.notes') }}</label>
              <textarea
                v-model="form.notes"
                rows="2"
                :placeholder="t('publicOrder.notesPh')"
              ></textarea>
            </div>

            <h4>{{ t('publicOrder.payment') }}</h4>
            <div v-if="!paymentMethods.length" class="warn-box">
              <i class="pi pi-exclamation-triangle"></i>
              {{ t('publicOrder.noPaymentMethod') }}
            </div>
            <div v-else class="payment-methods">
              <label
                v-for="pm in paymentMethods"
                :key="pm.id"
                class="payment-method-card"
                :class="{ active: form.payment_method_id === pm.id }"
              >
                <input
                  type="radio"
                  name="payment_method"
                  :value="pm.id"
                  v-model="form.payment_method_id"
                />
                <i class="pi" :class="pm.icon || 'pi-credit-card'"></i>
                <span>{{ pm.name }}</span>
                <i v-if="pm.qr_image_url" class="pi pi-qrcode pm-qr-badge" :title="t('publicOrder.qrAvailable')"></i>
              </label>
            </div>

            <div v-if="selectedPaymentMethod" class="qr-block">
              <div v-if="selectedPaymentMethod.qr_image_url" class="qr-info">
                <p class="qr-info-text">
                  <i class="pi pi-info-circle"></i>
                  {{ t('publicOrder.qrInstructionPay') }}
                </p>
                <div class="qr-action-row">
                  <button type="button" class="qr-btn" @click="openQrModal">
                    <i class="pi pi-eye"></i>
                    {{ t('publicOrder.viewQr') }}
                  </button>
                  <button type="button" class="qr-btn outlined" @click="downloadQr">
                    <i class="pi pi-download"></i>
                    {{ t('publicOrder.downloadQr') }}
                  </button>
                </div>
              </div>
              <div v-else-if="selectedPaymentMethod.code === 'qris'" class="qr-info missing">
                <i class="pi pi-exclamation-triangle"></i>
                {{ t('publicOrder.qrNotConfigured') }}
              </div>
            </div>

            <div class="field">
              <label>{{ t('publicOrder.paymentProof') }} <span class="req">*</span></label>
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                @change="onProofPicked"
              />
              <small class="hint">{{ t('publicOrder.paymentProofHint') }}</small>
              <div v-if="proofPreview" class="proof-preview">
                <img v-if="proofPreviewType === 'image'" :src="proofPreview" alt="proof" />
                <span v-else><i class="pi pi-file-pdf"></i> {{ proofFileName }}</span>
              </div>
            </div>

            <div class="totals-detail">
              <div class="row">
                <span>{{ t('publicOrder.subtotal') }}</span>
                <span>{{ formatIdr(cartSubtotal) }}</span>
              </div>
              <div v-if="settings.tax_enabled" class="row">
                <span>{{ t('publicOrder.tax') }} ({{ settings.tax_percentage }}%)</span>
                <span>{{ formatIdr(taxAmount) }}</span>
              </div>
              <div v-if="settings.service_charge_enabled" class="row">
                <span>{{ t('publicOrder.service') }} ({{ settings.service_charge_percentage }}%)</span>
                <span>{{ formatIdr(serviceAmount) }}</span>
              </div>
              <div class="row total">
                <span>{{ t('publicOrder.total') }}</span>
                <strong>{{ formatIdr(grandTotal) }}</strong>
              </div>
            </div>

            <p v-if="submitError" class="error-msg">{{ submitError }}</p>

            <button class="primary big" type="submit" :disabled="submitting">
              <i class="pi" :class="submitting ? 'pi-spin pi-spinner' : 'pi-send'"></i>
              {{ submitting ? t('publicOrder.sending') : t('publicOrder.placeOrder') }}
            </button>
            <p class="note">{{ t('publicOrder.approvalNote') }}</p>
          </form>
        </div>
      </div>
    </template>

    <!-- QR Image Modal -->
    <div v-if="qrModalOpen" class="qr-modal-backdrop" @click.self="qrModalOpen = false">
      <div class="qr-modal">
        <div class="qr-modal-header">
          <strong>{{ selectedPaymentMethod ? selectedPaymentMethod.name : '' }} QR</strong>
          <button class="icon-btn" @click="qrModalOpen = false" :aria-label="t('common.close')">
            <i class="pi pi-times"></i>
          </button>
        </div>
        <div class="qr-modal-body">
          <img
            v-if="selectedPaymentMethod && selectedPaymentMethod.qr_image_url"
            :src="selectedPaymentMethod.qr_image_url"
            :alt="selectedPaymentMethod.name + ' QR'"
          />
        </div>
        <p class="qr-modal-hint">{{ t('publicOrder.qrInstructionPay') }}</p>
        <button class="primary big" type="button" @click="downloadQr">
          <i class="pi pi-download"></i>
          {{ t('publicOrder.downloadQr') }}
        </button>
      </div>
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
const tableToken = route.params.tableToken

const loading = ref(true)
const loadError = ref('')
const isOrderable = ref(true)
const unavailableReason = ref('')
const outlet = ref({})
const table = ref({})
const categories = ref([])
const menu = ref([])
const settings = ref({
  tax_enabled: false,
  tax_percentage: 0,
  service_charge_enabled: false,
  service_charge_percentage: 0,
  membership_open: false,
})

const search = ref('')
const activeCategory = ref(null)
const cart = ref([])
const cartOpen = ref(false)
const submitting = ref(false)
const submitError = ref('')

const paymentMethods = ref([])
const proofFile = ref(null)
const proofPreview = ref('')
const proofPreviewType = ref('image')
const proofFileName = ref('')
const qrModalOpen = ref(false)

const selectedPaymentMethod = computed(() =>
  paymentMethods.value.find((p) => p.id === form.value.payment_method_id) || null
)

function openQrModal() {
  if (selectedPaymentMethod.value && selectedPaymentMethod.value.qr_image_url) {
    qrModalOpen.value = true
  }
}

async function downloadQr() {
  const pm = selectedPaymentMethod.value
  if (!pm || !pm.qr_image_url) return
  try {
    const resp = await fetch(pm.qr_image_url, { mode: 'cors' })
    const blob = await resp.blob()
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    const ext  = (pm.qr_image_url.split('.').pop() || 'png').split('?')[0].slice(0, 5)
    a.href     = url
    a.download = `qr-${(pm.code || 'payment')}.${ext}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 500)
  } catch (err) {
    // Fallback: open in new tab — browser will allow user to save
    window.open(pm.qr_image_url, '_blank')
  }
}

const form = ref({
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  member_card: '',
  notes: '',
  payment_method_id: null,
})

function onProofPicked (e) {
  const f = e.target.files && e.target.files[0]
  if (!f) {
    proofFile.value = null
    proofPreview.value = ''
    return
  }
  // 5 MB cap matches backend validator
  if (f.size > 5 * 1024 * 1024) {
    submitError.value = t('publicOrder.proofTooLarge')
    e.target.value = ''
    return
  }
  proofFile.value = f
  proofFileName.value = f.name
  if (f.type.startsWith('image/')) {
    proofPreviewType.value = 'image'
    const reader = new FileReader()
    reader.onload = (ev) => { proofPreview.value = ev.target.result }
    reader.readAsDataURL(f)
  } else {
    proofPreviewType.value = 'file'
    proofPreview.value = f.name
  }
}

onMounted(async () => {
  try {
    const res = await api.get(`/public/outlet/${outletSlug}/table/${tableToken}`)
    outlet.value = res.data.outlet || {}
    table.value = res.data.table || {}
    categories.value = res.data.categories || []
    menu.value = res.data.menu || []
    paymentMethods.value = res.data.payment_methods || []
    if (paymentMethods.value.length === 1) {
      form.value.payment_method_id = paymentMethods.value[0].id
    }
    settings.value = { ...settings.value, ...(res.data.settings || {}) }
    isOrderable.value = res.data.is_orderable !== false
    unavailableReason.value = res.data.unavailable_reason || ''
    if (outlet.value.name) {
      document.title = `${outlet.value.name} • Meja ${table.value.table_number || ''}`
    }
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Halaman tidak ditemukan'
  } finally {
    loading.value = false
  }
})

const unavailableMessage = computed(() => {
  const reason = unavailableReason.value
  if (reason === 'occupied') return t('publicOrder.unavailableOccupied')
  if (reason === 'reserved') return t('publicOrder.unavailableReserved')
  if (reason === 'inactive') return t('publicOrder.unavailableInactive')
  return t('publicOrder.unavailableGeneric')
})

const filteredMenu = computed(() => {
  let list = menu.value
  if (activeCategory.value !== null) {
    list = list.filter((m) => m.kategori_id === activeCategory.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter((m) =>
      (m.nama || '').toLowerCase().includes(q) ||
      (m.kode || '').toLowerCase().includes(q)
    )
  }
  return list
})

function getQty(menuId) {
  const found = cart.value.find((c) => c.menu_id === menuId)
  return found ? found.quantity : 0
}

function incQty(m) {
  const found = cart.value.find((c) => c.menu_id === m.id)
  if (found) {
    found.quantity++
  } else {
    cart.value.push({
      menu_id: m.id,
      menu_name: m.nama,
      menu_price: Number(m.harga_jual),
      quantity: 1,
      notes: '',
    })
  }
}

function decQty(m) {
  const idx = cart.value.findIndex((c) => c.menu_id === m.id)
  if (idx === -1) return
  cart.value[idx].quantity--
  if (cart.value[idx].quantity <= 0) cart.value.splice(idx, 1)
}

function changeCartQty(c, delta) {
  c.quantity += delta
  if (c.quantity <= 0) {
    cart.value = cart.value.filter((x) => x.menu_id !== c.menu_id)
  }
}

const cartCount = computed(() =>
  cart.value.reduce((s, c) => s + c.quantity, 0)
)
const cartSubtotal = computed(() =>
  cart.value.reduce((s, c) => s + c.menu_price * c.quantity, 0)
)
const taxAmount = computed(() =>
  settings.value.tax_enabled
    ? Math.round((cartSubtotal.value * Number(settings.value.tax_percentage)) / 100)
    : 0
)
const serviceAmount = computed(() =>
  settings.value.service_charge_enabled
    ? Math.round(
        (cartSubtotal.value * Number(settings.value.service_charge_percentage)) / 100
      )
    : 0
)
const grandTotal = computed(
  () => cartSubtotal.value + taxAmount.value + serviceAmount.value
)

function formatIdr(v) {
  const n = Number(v) || 0
  return 'Rp ' + n.toLocaleString('id-ID')
}

function initials(str) {
  return (str || '?')
    .split(' ')
    .map((s) => s.charAt(0))
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

async function submitOrder() {
  submitError.value = ''
  if (!form.value.customer_phone || !form.value.customer_email) {
    submitError.value = t('publicOrder.errPhoneEmail')
    return
  }
  if (!form.value.payment_method_id) {
    submitError.value = t('publicOrder.errPaymentMethod')
    return
  }
  if (!proofFile.value) {
    submitError.value = t('publicOrder.errProof')
    return
  }
  if (!cart.value.length) return

  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('customer_name', form.value.customer_name || '')
    fd.append('customer_phone', form.value.customer_phone)
    fd.append('customer_email', form.value.customer_email)
    if (form.value.member_card) fd.append('member_card', form.value.member_card)
    if (form.value.notes) fd.append('notes', form.value.notes)
    fd.append('payment_method_id', String(form.value.payment_method_id))
    fd.append('payment_proof', proofFile.value)
    fd.append('items', JSON.stringify(cart.value.map((c) => ({
      menu_id: c.menu_id,
      quantity: c.quantity,
      notes: c.notes || null,
    }))))
    const res = await api.post(
      `/public/outlet/${outletSlug}/table/${tableToken}/order`,
      fd,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    const kode = res.data?.data?.order?.kode
    if (kode) {
      router.push({
        name: 'public-order-status',
        params: { outletSlug, orderCode: kode },
      })
    }
  } catch (e) {
    submitError.value =
      e.response?.data?.message || t('publicOrder.errCreate')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.pto-page {
  min-height: 100vh;
  background: #f6f7fb;
  color: #1a1a1a;
  font-family: 'Inter', -apple-system, sans-serif;
  padding-bottom: 80px;
}
.state {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 24px;
}
.state.error i {
  font-size: 48px;
  color: #f87171;
}
.state.unavailable i {
  font-size: 48px;
  color: #f59e0b;
}
.state.unavailable h2 {
  margin: 12px 0 6px;
}
.unavailable-meta {
  margin-top: 12px;
  color: #6b7280;
  font-size: 14px;
}
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.pto-header {
  position: sticky;
  top: 0;
  z-index: 10;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
}
.brand { display: flex; align-items: center; gap: 10px; }
.brand .logo { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
.brand .logo-fallback {
  width: 40px; height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg,#6366f1,#8b5cf6);
  color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px;
}
.outlet-name { font-weight: 700; font-size: 15px; }
.table-label { font-size: 12px; color:#666; display:flex; align-items:center; gap:4px; }
.cart-btn {
  position:relative; background:#fff; border:1px solid #e5e7eb; width:42px; height:42px; border-radius:50%; cursor:pointer; font-size:18px;
}
.cart-btn .badge {
  position:absolute; top:-4px; right:-4px;
  background:#ef4444; color:#fff; border-radius:999px;
  font-size:10px; min-width:18px; height:18px; padding:0 4px;
  display:flex; align-items:center; justify-content:center; font-weight:700;
}

.pto-controls { padding: 12px 16px; background:#fff; border-bottom:1px solid #e5e7eb; }
.search { position:relative; }
.search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; }
.search input {
  width:100%; padding:10px 12px 10px 36px; font-size:14px;
  border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb;
}
.tabs { display:flex; gap:8px; overflow-x:auto; margin-top:10px; padding-bottom:2px; }
.tab {
  flex-shrink:0; background:#f3f4f6; border:1px solid transparent;
  padding:6px 14px; border-radius:999px; font-size:13px; cursor:pointer;
}
.tab.active { background:#6366f1; color:#fff; }

.pto-menu {
  padding: 12px; display: grid; grid-template-columns: 1fr; gap: 10px;
}
@media (min-width: 640px) {
  .pto-menu { grid-template-columns: 1fr 1fr; }
}
.menu-card {
  background:#fff; border-radius:12px; overflow:hidden;
  box-shadow:0 1px 3px rgba(0,0,0,.05); display:flex;
}
.menu-img {
  width:100px; height:100px; flex-shrink:0;
  background:#f3f4f6;
}
.menu-img img { width:100%; height:100%; object-fit:cover; }
.img-fallback {
  width:100%; height:100%; display:flex; align-items:center; justify-content:center;
  font-size:22px; color:#9ca3af; font-weight:700;
}
.menu-body { padding:10px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
.menu-name { font-weight:600; font-size:14px; }
.menu-desc {
  font-size:12px; color:#6b7280;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.menu-row { margin-top:auto; display:flex; align-items:center; justify-content:space-between; }
.menu-price { font-weight:700; color:#6366f1; font-size:14px; }

.qty-control { display:flex; align-items:center; gap:6px; }
.qty-btn {
  width:28px; height:28px; border-radius:8px; background:#f3f4f6; border:none;
  cursor:pointer; display:flex; align-items:center; justify-content:center; color:#374151;
}
.qty-btn:disabled { opacity:0.4; cursor:not-allowed; }
.qty-btn.add { background:#6366f1; color:#fff; }
.qty { min-width:18px; text-align:center; font-weight:600; font-size:14px; }

.pto-footer {
  position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #e5e7eb;
  display:flex; align-items:center; justify-content:space-between; padding:10px 16px; z-index:11;
}
.pto-footer .totals { display:flex; flex-direction:column; line-height:1.2; font-size:13px; }
.pto-footer .totals strong { font-size:15px; color:#1a1a1a; }
.primary {
  background:#6366f1; color:#fff; border:none; padding:10px 18px; border-radius:10px;
  font-weight:600; cursor:pointer; font-size:14px;
}
.primary:disabled { opacity:0.6; cursor:not-allowed; }
.primary.big { width:100%; padding:14px; font-size:15px; margin-top:8px; }

.sheet-backdrop {
  position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:20;
  display:flex; align-items:flex-end; justify-content:center;
}
.sheet {
  width:100%; max-width:480px; max-height:90vh; overflow-y:auto;
  background:#fff; border-radius:18px 18px 0 0;
  padding: 16px 16px 24px;
}
.sheet-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.sheet-header h3 { margin:0; font-size:16px; }
.icon-btn { background:transparent; border:none; cursor:pointer; font-size:18px; color:#6b7280; }
.empty { padding:30px; text-align:center; color:#9ca3af; }
.empty i { font-size:36px; display:block; margin-bottom:6px; }

.cart-list { list-style:none; padding:0; margin:0 0 12px; }
.cart-item {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding:8px 0; border-bottom:1px solid #f3f4f6; gap:10px;
}
.ci-info { flex:1; }
.ci-name { font-weight:600; font-size:14px; }
.ci-price { font-size:12px; color:#6b7280; }
.ci-notes {
  width:100%; margin-top:6px; padding:6px 8px; font-size:12px;
  border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb;
}

.form { margin-top:10px; }
.form h4 { font-size:14px; margin: 12px 0 8px; }
.field { margin-bottom:10px; }
.field label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
.field input, .field textarea {
  width:100%; padding:10px 12px; font-size:14px;
  border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;
}
.field .req { color:#ef4444; }
.field .hint { display:block; font-size:11px; color:#9ca3af; margin-top:4px; }
.field .link { display:inline-block; margin-top:4px; font-size:12px; color:#6366f1; }

.totals-detail { margin:14px 0; padding:10px; background:#f9fafb; border-radius:10px; font-size:13px; }
.totals-detail .row { display:flex; justify-content:space-between; padding:3px 0; }
.totals-detail .total { border-top:1px dashed #e5e7eb; padding-top:8px; margin-top:6px; font-size:14px; }
.totals-detail .total strong { color:#6366f1; }
.error-msg { color:#ef4444; font-size:13px; margin: 8px 0 0; }
.note { font-size:12px; color:#9ca3af; text-align:center; margin-top:8px; }

.payment-methods {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;
}
.payment-method-card {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb; border-radius: 10px;
  background:#f9fafb; cursor: pointer; font-size: 13px;
  transition: all 0.15s;
}
.payment-method-card input { display: none; }
.payment-method-card i { font-size: 16px; color: #6366f1; }
.payment-method-card.active {
  border-color: #6366f1; background: #eef2ff;
}
.warn-box {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; background: #fff7ed; border: 1px solid #fdba74;
  color: #c2410c; border-radius: 8px; font-size: 13px;
  margin-bottom: 12px;
}
.warn-box i { font-size: 16px; }
.proof-preview {
  margin-top: 8px; padding: 8px; background: #f9fafb;
  border: 1px dashed #d1d5db; border-radius: 8px; text-align: center;
  font-size: 12px; color: #4b5563;
}
.proof-preview img { max-width: 100%; max-height: 200px; border-radius: 6px; }
.proof-preview i { font-size: 18px; color: #ef4444; margin-right: 4px; }

.pm-qr-badge { margin-left: auto; color: #16a34a; font-size: 14px; }

.qr-block { margin: 4px 0 12px; }
.qr-info {
  background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3;
  border-radius: 10px; padding: 10px 12px; font-size: 13px;
  display: flex; flex-direction: column; gap: 8px;
}
.qr-info.missing {
  background: #fff7ed; border-color: #fdba74; color: #c2410c;
  flex-direction: row; align-items: center; gap: 8px;
}
.qr-info-text { margin: 0; display: flex; align-items: flex-start; gap: 6px; line-height: 1.4; }
.qr-info-text i { color: #6366f1; }
.qr-action-row { display: flex; gap: 8px; flex-wrap: wrap; }
.qr-btn {
  flex: 1; min-width: 120px;
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px 12px; font-size: 13px; font-weight: 600;
  background: #6366f1; color: #fff; border: 1px solid #6366f1;
  border-radius: 8px; cursor: pointer;
}
.qr-btn.outlined { background: #fff; color: #6366f1; }
.qr-btn:hover { opacity: 0.92; }

.qr-modal-backdrop {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6);
  display: flex; align-items: center; justify-content: center;
  z-index: 30; padding: 16px;
}
.qr-modal {
  background: #fff; border-radius: 14px;
  width: 100%; max-width: 380px;
  padding: 14px;
  display: flex; flex-direction: column; gap: 10px;
}
.qr-modal-header { display: flex; align-items: center; justify-content: space-between; }
.qr-modal-body {
  display: flex; justify-content: center; align-items: center;
  background: #f9fafb; border: 1px dashed #e5e7eb; border-radius: 10px;
  padding: 12px; min-height: 220px;
}
.qr-modal-body img { max-width: 100%; max-height: 360px; border-radius: 6px; }
.qr-modal-hint { font-size: 12px; color: #6b7280; margin: 0; text-align: center; }
</style>
