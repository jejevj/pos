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

const form = ref({
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  member_card: '',
  notes: '',
})

onMounted(async () => {
  try {
    const res = await api.get(`/public/outlet/${outletSlug}/table/${tableToken}`)
    outlet.value = res.data.outlet
    table.value = res.data.table
    categories.value = res.data.categories || []
    menu.value = res.data.menu || []
    settings.value = { ...settings.value, ...(res.data.settings || {}) }
    document.title = `${outlet.value.name} • Meja ${table.value.table_number}`
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Halaman tidak ditemukan'
  } finally {
    loading.value = false
  }
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
  if (!cart.value.length) return

  submitting.value = true
  try {
    const payload = {
      customer_name: form.value.customer_name || null,
      customer_phone: form.value.customer_phone,
      customer_email: form.value.customer_email,
      member_card: form.value.member_card || null,
      notes: form.value.notes || null,
      items: cart.value.map((c) => ({
        menu_id: c.menu_id,
        quantity: c.quantity,
        notes: c.notes || null,
      })),
    }
    const res = await api.post(
      `/public/outlet/${outletSlug}/table/${tableToken}/order`,
      payload
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
</style>
