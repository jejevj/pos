<template>
  <div class="purchase-view">
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <div class="page-header">
      <div>
        <h2>{{ $t('purchase.title') }}</h2>
        <p class="text-muted">{{ $t('purchase.subtitle') }}</p>
      </div>
      <Button :label="$t('purchase.addPurchase')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <Card>
      <template #content>
        <DataTable :value="purchases" stripedRows showGridlines paginator :rows="10">
          <Column field="purchase_code" :header="$t('purchase.purchaseCode')" sortable></Column>
          <Column field="purchase_date" :header="$t('purchase.purchaseDate')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.purchase_date) }}
            </template>
          </Column>
          <Column field="supplier_name" :header="$t('purchase.supplier')"></Column>
          <Column field="total_amount" :header="$t('purchase.totalAmount')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.total_amount) }}
            </template>
          </Column>
          <Column field="payment_method" :header="$t('purchase.paymentMethod')"></Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-eye" text rounded severity="info" @click="viewDetails(data)" v-tooltip.top="$t('common.view')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-shopping-cart" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create Purchase Dialog -->
    <Dialog v-model:visible="createDialogVisible" :header="$t('purchase.addPurchase')" modal :style="{ width: '900px' }">
      <div class="form-content">
        <div class="form-row">
          <div class="field">
            <label>{{ $t('purchase.purchaseDate') }} *</label>
            <DatePicker v-model="form.purchase_date" dateFormat="yy-mm-dd" showIcon fluid />
          </div>
          <div class="field">
            <label>{{ $t('purchase.supplier') }}</label>
            <InputText v-model="form.supplier_name" :placeholder="$t('purchase.supplier')" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>{{ $t('purchase.paymentMethod') }}</label>
            <InputText v-model="form.payment_method" :placeholder="$t('purchase.paymentMethod')" fluid />
          </div>
          <div class="field">
            <label>{{ $t('purchase.paymentProof') }}</label>
            <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000" @select="onFileSelect" :auto="true" :chooseLabel="$t('purchase.uploadProof')" />
          </div>
        </div>

        <div class="field">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="form.notes" rows="2" fluid />
        </div>

        <Divider />

        <div class="items-section">
          <div class="section-header">
            <h3>{{ $t('purchase.items') }}</h3>
            <Button :label="$t('purchase.addItem')" icon="pi pi-plus" size="small" @click="addItem" />
          </div>

          <div v-for="(item, index) in form.items" :key="index" class="item-row">
            <div class="item-select-wrapper">
              <Select 
                v-model="item.bahan_baku_id" 
                :options="bahanBaku" 
                optionLabel="nama" 
                optionValue="id" 
                :placeholder="$t('purchase.selectItem')" 
                fluid 
                class="item-select"
                filter
              />
              <Button 
                icon="pi pi-plus" 
                text 
                rounded 
                size="small" 
                severity="success" 
                @click="openAddBahanBakuDialog(index)" 
                v-tooltip.top="$t('bahanBaku.addBahanBaku')"
              />
            </div>
            <InputNumber v-model="item.quantity" :placeholder="$t('purchase.quantity')" :minFractionDigits="2" :maxFractionDigits="2" fluid class="item-quantity" />
            <InputNumber v-model="item.unit_price" :placeholder="$t('purchase.unitPrice')" mode="currency" currency="IDR" locale="id-ID" fluid class="item-price" />
            <div class="item-subtotal">{{ formatCurrency(item.quantity * item.unit_price) }}</div>
            <Button icon="pi pi-trash" text rounded severity="danger" @click="removeItem(index)" />
          </div>

          <div v-if="form.items.length === 0" class="empty-items">
            {{ $t('purchase.noItems') }}
          </div>

          <div class="total-row">
            <strong>{{ $t('purchase.totalAmount') }}:</strong>
            <strong>{{ formatCurrency(totalAmount) }}</strong>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="createDialogVisible = false" />
        <Button :label="$t('common.create')" @click="createPurchase" :loading="saving" />
      </template>
    </Dialog>

    <!-- Details Dialog -->
    <Dialog v-model:visible="detailsDialogVisible" :header="$t('purchase.purchaseDetails')" modal :style="{ width: '800px' }">
      <div v-if="selectedPurchase" class="details-content">
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ $t('purchase.purchaseCode') }}</span>
            <span class="detail-value">{{ selectedPurchase.purchase_code }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('purchase.purchaseDate') }}</span>
            <span class="detail-value">{{ formatDate(selectedPurchase.purchase_date) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('purchase.supplier') }}</span>
            <span class="detail-value">{{ selectedPurchase.supplier_name || '-' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('purchase.paymentMethod') }}</span>
            <span class="detail-value">{{ selectedPurchase.payment_method || '-' }}</span>
          </div>
        </div>

        <div v-if="selectedPurchase.payment_proof_url" class="proof-section">
          <label>{{ $t('purchase.paymentProof') }}</label>
          <Image :src="selectedPurchase.payment_proof_url" alt="Payment Proof" width="300" preview />
        </div>

        <Divider />

        <h4>{{ $t('purchase.items') }}</h4>
        <DataTable :value="selectedPurchase.items" stripedRows>
          <Column field="bahan_baku_name" :header="$t('bahanBaku.bahanBaku')"></Column>
          <Column field="quantity" :header="$t('purchase.quantity')">
            <template #body="{ data }">
              {{ data.quantity }} {{ data.satuan_name }}
            </template>
          </Column>
          <Column field="unit_price" :header="$t('purchase.unitPrice')">
            <template #body="{ data }">
              {{ formatCurrency(data.unit_price) }}
            </template>
          </Column>
          <Column field="subtotal" :header="$t('purchase.subtotal')">
            <template #body="{ data }">
              {{ formatCurrency(data.subtotal) }}
            </template>
          </Column>
        </DataTable>

        <div class="total-section">
          <strong>{{ $t('purchase.totalAmount') }}: {{ formatCurrency(selectedPurchase.total_amount) }}</strong>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.close')" text @click="detailsDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Add Bahan Baku Dialog -->
    <Dialog v-model:visible="addBahanBakuDialogVisible" :header="$t('bahanBaku.addBahanBaku')" modal :style="{ width: '550px' }">
      <div class="form-content">
        <div class="field">
          <label>{{ $t('bahanBaku.materialName') }} *</label>
          <InputText v-model="newBahanBaku.nama" :placeholder="$t('bahanBaku.materialName')" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.category') }} *</label>
          <Select v-model="newBahanBaku.kategori_id" :options="categories" optionLabel="nama" optionValue="id" :placeholder="$t('bahanBaku.selectCategory')" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.unit') }} *</label>
          <Select v-model="newBahanBaku.satuan_id" :options="units" optionLabel="nama" optionValue="id" :placeholder="$t('bahanBaku.selectUnit')" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.purchaseUnit') }}</label>
          <Select v-model="newBahanBaku.satuan_pembelian_id" :options="units" optionLabel="nama" optionValue="id" :placeholder="$t('bahanBaku.selectUnit')" fluid showClear />
          <small class="text-muted">{{ $t('bahanBaku.purchaseUnitHelp') }}</small>
        </div>

        <div v-if="newBahanBaku.satuan_pembelian_id" class="field">
          <label>{{ $t('bahanBaku.quantityPerPurchaseUnit') }} *</label>
          <InputNumber v-model="newBahanBaku.jumlah_per_unit_pembelian" :minFractionDigits="2" :maxFractionDigits="4" fluid />
          <small class="text-muted">
            {{ $t('bahanBaku.quantityPerPurchaseUnitHelp') }}
            <span v-if="newBahanBaku.satuan_id" class="font-semibold">
              (1 {{ getPurchaseUnitName() }} = ? {{ getBaseUnitName() }})
            </span>
          </small>
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.purchasePrice') }} *</label>
          <InputNumber v-model="newBahanBaku.harga_beli" mode="currency" currency="IDR" locale="id-ID" fluid />
          <small v-if="newBahanBaku.satuan_pembelian_id && newBahanBaku.jumlah_per_unit_pembelian && newBahanBaku.harga_beli" class="price-hint">
            💡 {{ $t('bahanBaku.pricePerBaseUnit') }}: 
            Rp {{ newBahanBaku.harga_beli.toLocaleString('id-ID') }} ÷ {{ newBahanBaku.jumlah_per_unit_pembelian.toLocaleString('id-ID') }} {{ getBaseUnitName() }} 
            = Rp {{ calculatePricePerBaseUnit().toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) }}/{{ getBaseUnitName() }}
          </small>
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.supplier') }}</label>
          <InputText v-model="newBahanBaku.supplier_name" :placeholder="$t('bahanBaku.supplier')" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.minimumStock') }} *</label>
          <InputNumber v-model="newBahanBaku.minimum_stock" :minFractionDigits="2" :maxFractionDigits="2" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.currentStock') }}</label>
          <InputNumber v-model="newBahanBaku.current_stock" :minFractionDigits="2" :maxFractionDigits="2" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.storageLocation') }}</label>
          <InputText v-model="newBahanBaku.lokasi_penyimpanan" :placeholder="$t('bahanBaku.storageLocation')" fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.expiryDate') }}</label>
          <DatePicker v-model="newBahanBaku.expired_date" dateFormat="yy-mm-dd" showIcon fluid />
        </div>

        <div class="field">
          <label>{{ $t('bahanBaku.description') }}</label>
          <Textarea v-model="newBahanBaku.deskripsi" rows="2" fluid />
        </div>

        <div class="field">
          <div class="checkbox-field">
            <Checkbox v-model="newBahanBaku.is_active" :binary="true" inputId="is_active_new" />
            <label for="is_active_new">{{ $t('bahanBaku.materialActive') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="addBahanBakuDialogVisible = false" />
        <Button :label="$t('common.create')" @click="createBahanBaku" :loading="savingBahanBaku" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Divider from 'primevue/divider'
import Image from 'primevue/image'
import Checkbox from 'primevue/checkbox'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const loading = ref(false)
const saving = ref(false)
const savingBahanBaku = ref(false)
const purchases = ref([])
const bahanBaku = ref([])
const categories = ref([])
const units = ref([])
const createDialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const addBahanBakuDialogVisible = ref(false)
const selectedPurchase = ref(null)
const currentItemIndex = ref(null)

const form = ref({
  purchase_date: new Date(),
  supplier_name: '',
  payment_method: '',
  payment_proof: null,
  notes: '',
  items: []
})

const newBahanBaku = ref({
  nama: '',
  kategori_id: null,
  satuan_id: null,
  satuan_pembelian_id: null,
  jumlah_per_unit_pembelian: null,
  harga_beli: 0,
  supplier_name: '',
  minimum_stock: 0,
  current_stock: 0,
  lokasi_penyimpanan: '',
  expired_date: null,
  deskripsi: '',
  is_active: true
})

const totalAmount = computed(() => {
  return form.value.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0)
})

const fetchPurchases = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/purchases`)
    purchases.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchBahanBaku = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/bahan-baku`)
    bahanBaku.value = response.data
  } catch (error) {
    console.error('Failed to fetch bahan baku:', error)
  }
}

const fetchCategories = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/kategori-bahan-baku`)
    categories.value = response.data
  } catch (error) {
    console.error('Failed to fetch categories:', error)
  }
}

const fetchUnits = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/satuan`)
    units.value = response.data
  } catch (error) {
    console.error('Failed to fetch units:', error)
  }
}

const openCreateDialog = () => {
  form.value = {
    purchase_date: new Date(),
    supplier_name: '',
    payment_method: '',
    payment_proof: null,
    notes: '',
    items: []
  }
  createDialogVisible.value = true
}

const addItem = () => {
  form.value.items.push({
    bahan_baku_id: null,
    quantity: 0,
    unit_price: 0
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const onFileSelect = (event) => {
  form.value.payment_proof = event.files[0]
}

const createPurchase = async () => {
  if (form.value.items.length === 0) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('purchase.noItems'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const formData = new FormData()
    formData.append('purchase_date', form.value.purchase_date.toISOString().split('T')[0])
    if (form.value.supplier_name) formData.append('supplier_name', form.value.supplier_name)
    if (form.value.payment_method) formData.append('payment_method', form.value.payment_method)
    if (form.value.payment_proof) formData.append('payment_proof', form.value.payment_proof)
    if (form.value.notes) formData.append('notes', form.value.notes)
    formData.append('items', JSON.stringify(form.value.items))

    await api.post(`/outlets/${outletId}/purchases`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('purchase.purchaseCreated'), life: 3000 })
    createDialogVisible.value = false
    fetchPurchases()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const viewDetails = async (purchase) => {
  try {
    const response = await api.get(`/outlets/${outletId}/purchases/${purchase.id}`)
    selectedPurchase.value = response.data
    detailsDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const confirmDelete = (purchase) => {
  confirm.require({
    message: t('purchase.confirmDeletePurchase'),
    header: t('purchase.deletePurchase'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deletePurchase(purchase.id)
  })
}

const deletePurchase = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/purchases/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('purchase.purchaseDeleted'), life: 3000 })
    fetchPurchases()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatCurrency = (value) => {
  if (!value) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}

const openAddBahanBakuDialog = (itemIndex) => {
  currentItemIndex.value = itemIndex
  newBahanBaku.value = {
    nama: '',
    kategori_id: null,
    satuan_id: null,
    satuan_pembelian_id: null,
    jumlah_per_unit_pembelian: null,
    harga_beli: 0,
    supplier_name: '',
    minimum_stock: 0,
    current_stock: 0,
    lokasi_penyimpanan: '',
    expired_date: null,
    deskripsi: '',
    is_active: true
  }
  addBahanBakuDialogVisible.value = true
}

const getPurchaseUnitName = () => {
  const unit = units.value.find(u => u.id === newBahanBaku.value.satuan_pembelian_id)
  return unit?.nama || ''
}

const getBaseUnitName = () => {
  const unit = units.value.find(u => u.id === newBahanBaku.value.satuan_id)
  return unit?.nama || ''
}

const calculatePricePerBaseUnit = () => {
  if (!newBahanBaku.value.harga_beli || !newBahanBaku.value.jumlah_per_unit_pembelian) return 0
  return newBahanBaku.value.harga_beli / newBahanBaku.value.jumlah_per_unit_pembelian
}

const createBahanBaku = async () => {
  if (!newBahanBaku.value.nama || !newBahanBaku.value.kategori_id || !newBahanBaku.value.satuan_id) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  savingBahanBaku.value = true
  try {
    const payload = {
      nama: newBahanBaku.value.nama,
      kategori_id: newBahanBaku.value.kategori_id,
      satuan_id: newBahanBaku.value.satuan_id,
      harga_beli: newBahanBaku.value.harga_beli,
      minimum_stock: newBahanBaku.value.minimum_stock,
      current_stock: newBahanBaku.value.current_stock || 0,
      is_active: newBahanBaku.value.is_active
    }

    // Optional fields
    if (newBahanBaku.value.satuan_pembelian_id) {
      payload.satuan_pembelian_id = newBahanBaku.value.satuan_pembelian_id
    }
    if (newBahanBaku.value.jumlah_per_unit_pembelian) {
      payload.jumlah_per_unit_pembelian = newBahanBaku.value.jumlah_per_unit_pembelian
    }
    if (newBahanBaku.value.lokasi_penyimpanan) {
      payload.lokasi_penyimpanan = newBahanBaku.value.lokasi_penyimpanan
    }
    if (newBahanBaku.value.expired_date) {
      payload.expired_date = newBahanBaku.value.expired_date.toISOString().split('T')[0]
    }
    if (newBahanBaku.value.deskripsi) {
      payload.deskripsi = newBahanBaku.value.deskripsi
    }

    const response = await api.post(`/outlets/${outletId}/bahan-baku`, payload)

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('bahanBaku.bahanBakuCreated'), life: 3000 })
    
    // Refresh bahan baku list
    await fetchBahanBaku()
    
    // Auto-select the newly created bahan baku
    if (currentItemIndex.value !== null && response.data.data?.id) {
      form.value.items[currentItemIndex.value].bahan_baku_id = response.data.data.id
    }
    
    addBahanBakuDialogVisible.value = false
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    savingBahanBaku.value = false
  }
}

onMounted(() => {
  fetchPurchases()
  fetchBahanBaku()
  fetchCategories()
  fetchUnits()
})
</script>

<style scoped>
.purchase-view {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

.form-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field label { font-weight: 600; font-size: 0.875rem; }

.items-section {
  margin-top: 1rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-header h3 { margin: 0; }

.item-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1.5fr 1fr auto;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}

.item-select-wrapper {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.item-select {
  flex: 1;
}

.item-subtotal {
  font-weight: 600;
  text-align: right;
}

.empty-items {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
  font-style: italic;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  margin-top: 1rem;
  font-size: 1.1rem;
}

.details-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.detail-value {
  font-weight: 600;
}

.proof-section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.total-section {
  text-align: right;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  font-size: 1.1rem;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(4px);
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.loading-text {
  color: #1f2937;
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  display: block;
  margin-top: 0.25rem;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox-field label {
  cursor: pointer;
  font-weight: 400;
}

.price-hint {
  color: #10b981;
  font-weight: 600;
  display: block;
  margin-top: 0.5rem;
}

.font-semibold {
  font-weight: 600;
}
</style>
