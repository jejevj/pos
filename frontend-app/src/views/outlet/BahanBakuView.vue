<template>
  <div class="view-container">
    <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="mb-4">
      <template #item="{ item, props }">
        <router-link v-if="item.to" v-slot="{ href, navigate }" :to="item.to" custom>
          <a :href="href" v-bind="props.action" @click="navigate">
            <span v-if="item.icon" :class="item.icon"></span>
            <span v-if="item.label">{{ item.label }}</span>
          </a>
        </router-link>
        <span v-else>{{ item.label }}</span>
      </template>
    </Breadcrumb>

    <div v-if="isProcessing" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('bahanBaku.materials') }}</span>
          <Button :label="$t('bahanBaku.addMaterial')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="materials" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>
          <template #header>
            <div class="filter-bar">
              <div class="filter-fields">
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
                  <InputText v-model="filters.search" :placeholder="$t('common.search') + '...'" @input="fetchMaterials" class="filter-input" />
                </div>
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-tag" /> {{ $t('bahanBaku.category') }}</label>
                  <Select v-model="filters.kategori_id" :options="categories" optionLabel="nama" optionValue="id"
                          :placeholder="$t('bahanBaku.allCategories')" class="filter-select" @change="fetchMaterials" showClear />
                </div>
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-chart-bar" /> {{ $t('bahanBaku.stockStatus') }}</label>
                  <Select v-model="filters.stock_status" :options="stockStatuses" optionLabel="label" optionValue="value"
                          :placeholder="$t('bahanBaku.allStatuses')" class="filter-select" @change="fetchMaterials" showClear />
                </div>
              </div>
              <Button
                v-if="filters.search || filters.kategori_id || filters.stock_status"
                :label="$t('common.reset')"
                icon="pi pi-filter-slash"
                severity="secondary"
                outlined
                size="small"
                class="filter-reset-btn"
                @click="resetFilters"
              />
            </div>
          </template>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-box" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="kode" :header="$t('bahanBaku.materialCode')" sortable style="width: 120px" />
          <Column field="nama" :header="$t('bahanBaku.materialName')" sortable />
          <Column field="kategori.nama" :header="$t('bahanBaku.category')" sortable />
          <Column field="current_stock" :header="$t('bahanBaku.currentStock')" sortable style="width: 150px">
            <template #body="{ data }">
              <span :class="getStockClass(data)">{{ data.current_stock }} {{ data.satuan?.singkatan }}</span>
            </template>
          </Column>          <Column field="stock_status" :header="$t('bahanBaku.stockStatus')" sortable style="width: 130px">
            <template #body="{ data }">
              <Tag :value="$t(`bahanBaku.${data.stock_status}`)" :severity="getStockSeverity(data.stock_status)" />
            </template>
          </Column>
          <Column field="defers_on_bon" :header="$t('bahanBaku.defersOnBon')" style="width: 120px">
            <template #body="{ data }">
              <Tag v-if="data.defers_on_bon" value="Bon" severity="warn" v-tooltip.top="$t('bahanBaku.defersOnBonHint')" />
              <span v-else class="text-muted" style="font-size:0.8rem">-</span>
            </template>
          </Column>
          <Column field="harga_beli" :header="$t('bahanBaku.purchasePrice')" sortable style="width: 150px">
            <template #body="{ data }">
              Rp {{ Number(data.harga_beli).toLocaleString('id-ID') }}
            </template>
          </Column>
          <Column field="supplier.nama" :header="$t('bahanBaku.supplier')" />
          <Column :header="$t('common.actions')" style="width: 160px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-eye" text rounded @click="viewDetail(data)" v-tooltip.top="'Detail'" />
                <Button icon="pi pi-pencil" text rounded severity="info" @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-plus-circle" text rounded severity="success" @click="openStockDialog(data, 'add')" v-tooltip.top="'Add Stock'" />
                <Button icon="pi pi-minus-circle" text rounded severity="warning" @click="openStockDialog(data, 'reduce')" v-tooltip.top="'Reduce Stock'" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Material Form Dialog -->
    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('bahanBaku.editMaterial') : $t('bahanBaku.addMaterial')"
            :modal="true" :style="{ width: '550px' }">
      <div class="dialog-content">
        <div class="field">
          <label>{{ $t('bahanBaku.materialName') }} *</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.category') }} *</label>
          <Select v-model="form.kategori_id" :options="categories" optionLabel="nama" optionValue="id" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.unit') }} *</label>
          <Select v-model="form.satuan_id" :options="units" optionLabel="nama" optionValue="id" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.purchaseUnit') }}</label>
          <Select v-model="form.satuan_pembelian_id" :options="units" optionLabel="nama" optionValue="id" style="width: 100%" showClear />
          <small class="text-gray-500">{{ $t('bahanBaku.purchaseUnitHelp') }}</small>
        </div>
        <div v-if="form.satuan_pembelian_id" class="field">
          <label>{{ $t('bahanBaku.quantityPerPurchaseUnit') }} *</label>
          <InputNumber v-model="form.jumlah_per_unit_pembelian" style="width: 100%" :minFractionDigits="2" :maxFractionDigits="4" />
          <small class="text-gray-500">
            {{ $t('bahanBaku.quantityPerPurchaseUnitHelp') }}
            <span v-if="form.satuan_id" class="font-semibold">
              (1 {{ getPurchaseUnitName() }} = ? {{ getBaseUnitName() }})
            </span>
          </small>
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.purchasePrice') }} *</label>
          <InputNumber v-model="form.harga_beli" style="width: 100%" mode="currency" currency="IDR" locale="id-ID" />
          <small v-if="form.satuan_pembelian_id && form.jumlah_per_unit_pembelian && form.harga_beli" class="text-green-600 font-semibold block mt-2">
            💡 {{ $t('bahanBaku.pricePerBaseUnit') }}: 
            Rp {{ form.harga_beli.toLocaleString('id-ID') }} ÷ {{ form.jumlah_per_unit_pembelian.toLocaleString('id-ID') }} {{ getBaseUnitName() }} 
            = Rp {{ calculatePricePerBaseUnit().toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) }}/{{ getBaseUnitName() }}
          </small>
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.supplier') }}</label>
          <Select v-model="form.supplier_id" :options="suppliers" optionLabel="nama" optionValue="id" style="width: 100%" showClear />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.minimumStock') }} *</label>
          <InputNumber v-model="form.minimum_stock" style="width: 100%" :minFractionDigits="2" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.currentStock') }}</label>
          <InputNumber v-model="form.current_stock" style="width: 100%" :minFractionDigits="2" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.storageLocation') }}</label>
          <InputText v-model="form.lokasi_penyimpanan" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.expiryDate') }}</label>
          <DatePicker v-model="form.expired_date" style="width: 100%" dateFormat="yy-mm-dd" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.description') }}</label>
          <Textarea v-model="form.deskripsi" rows="2" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('bahanBaku.materialActive') }}</label>
          </div>
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.defers_on_bon" :binary="true" inputId="defers_on_bon" />
            <label for="defers_on_bon">{{ $t('bahanBaku.defersOnBon') }}</label>
          </div>
          <small class="text-muted">{{ $t('bahanBaku.defersOnBonHint') }}</small>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveMaterial" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
      </template>
    </Dialog>

    <!-- Stock Operation Dialog -->
    <Dialog v-model:visible="stockDialogVisible"
            :header="stockOperation === 'add' ? $t('bahanBaku.addStock') : $t('bahanBaku.reduceStock')"
            :modal="true" :style="{ width: '450px' }">
      <div class="dialog-content">
        <div class="field">
          <p class="m-0"><strong>{{ $t('bahanBaku.materialName') }}:</strong> {{ selectedMaterial?.nama }}</p>
          <p class="m-0 mt-1"><strong>{{ $t('bahanBaku.currentStock') }}:</strong> {{ selectedMaterial?.current_stock }} {{ selectedMaterial?.satuan?.singkatan }}</p>
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.quantity') }} *</label>
          <InputNumber v-model="stockForm.quantity" style="width: 100%" :minFractionDigits="2" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.notes') }}</label>
          <Textarea v-model="stockForm.notes" rows="3" style="width: 100%" />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="stockDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveStockOperation" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
      </template>
    </Dialog>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="detailDialogVisible"
            :header="selectedMaterial?.nama"
            :modal="true" :style="{ width: '750px' }">
      <div v-if="selectedMaterial" class="dialog-content">
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.materialCode') }}</span>
            <span class="detail-value">{{ selectedMaterial.kode }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.category') }}</span>
            <span class="detail-value">{{ selectedMaterial.kategori?.nama }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.unit') }}</span>
            <span class="detail-value">{{ selectedMaterial.satuan?.nama }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.supplier') }}</span>
            <span class="detail-value">{{ selectedMaterial.supplier?.nama || '-' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.purchasePrice') }}</span>
            <span class="detail-value">Rp {{ Number(selectedMaterial.harga_beli).toLocaleString('id-ID') }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('bahanBaku.currentStock') }}</span>
            <span class="detail-value" :class="getStockClass(selectedMaterial)">
              {{ selectedMaterial.current_stock }} {{ selectedMaterial.satuan?.singkatan }}
            </span>
          </div>
        </div>

        <!-- Stock per location -->
        <div class="stock-locations-section">
          <p class="section-title">
            <i class="pi pi-building"></i>
            {{ $t('bahanBaku.stockPerLocation') }}
          </p>
          <div v-if="loadingLocationStocks" class="loading-center">
            <ProgressSpinner style="width:28px;height:28px" />
          </div>
          <div v-else-if="locationStocks.length" class="location-stock-grid">
            <div v-for="loc in locationStocks" :key="loc.location_id"
                 class="location-stock-card"
                 :class="'loc-type-' + loc.location_type">
              <div class="loc-icon">
                <i :class="locIcon(loc.location_type)"></i>
              </div>
              <div class="loc-info">
                <div class="loc-name">{{ loc.location_name }}</div>
                <div class="loc-type-label">{{ $t('stockLocation.' + loc.location_type) }}</div>
              </div>
              <div class="loc-stock">
                {{ formatQty(loc.current_stock) }}
                <span class="loc-unit">{{ selectedMaterial.satuan?.singkatan }}</span>
              </div>
            </div>
            <!-- Total row -->
            <div class="location-stock-card loc-total">
              <div class="loc-icon"><i class="pi pi-calculator"></i></div>
              <div class="loc-info">
                <div class="loc-name">{{ $t('stockLocation.totalStock') }}</div>
                <div class="loc-type-label">{{ $t('bahanBaku.allLocations') }}</div>
              </div>
              <div class="loc-stock">
                {{ formatQty(selectedMaterial.current_stock) }}
                <span class="loc-unit">{{ selectedMaterial.satuan?.singkatan }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-locations">
            <i class="pi pi-info-circle"></i>
            {{ $t('bahanBaku.noLocationStock') }}
          </div>
        </div>

        <div class="field">
          <p class="font-semibold mb-2">{{ $t('bahanBaku.stockHistory') }}</p>
          <DataTable :value="stockHistory" :loading="loadingHistory" stripedRows size="small">
            <Column field="created_at" :header="$t('bahanBaku.date')" sortable>
              <template #body="{ data }">{{ new Date(data.created_at).toLocaleString('id-ID') }}</template>
            </Column>
            <Column field="tipe" :header="$t('bahanBaku.type')" sortable>
              <template #body="{ data }">
                <Tag :value="$t(`bahanBaku.${data.tipe === 'in' ? 'stockIn' : data.tipe === 'out' ? 'stockOut' : 'adjustment'}`)"
                     :severity="data.tipe === 'in' ? 'success' : data.tipe === 'out' ? 'danger' : 'info'" />
              </template>
            </Column>
            <Column field="quantity" :header="$t('bahanBaku.quantity')" sortable />
            <Column field="stock_before" :header="$t('bahanBaku.stockBefore')" sortable />
            <Column field="stock_after" :header="$t('bahanBaku.stockAfter')" sortable />
            <Column field="notes" :header="$t('bahanBaku.notes')" />
          </DataTable>
        </div>
      </div>
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
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.materials') }
])

const outlet = ref(null)

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const materials = ref([])
const categories = ref([])
const units = ref([])
const suppliers = ref([])
const stockHistory = ref([])
const locationStocks = ref([])
const loadingLocationStocks = ref(false)
const loading = ref(false)
const loadingHistory = ref(false)
const dialogVisible = ref(false)
const stockDialogVisible = ref(false)
const detailDialogVisible = ref(false)
const saving = ref(false)
const deleting = ref(false)
const isEdit = ref(false)
const selectedMaterial = ref(null)
const stockOperation = ref('add')

const isProcessing = computed(() => saving.value || deleting.value)

const filters = ref({ search: '', kategori_id: null, stock_status: null })

const stockStatuses = ref([
  { label: t('bahanBaku.allStatuses'), value: null },
  { label: t('bahanBaku.inStock'), value: 'active' },
  { label: t('bahanBaku.lowStock'), value: 'low_stock' }
])

const form = ref({
  nama: '', kategori_id: null, satuan_id: null, satuan_pembelian_id: null, jumlah_per_unit_pembelian: null, supplier_id: null,
  harga_beli: 0, minimum_stock: 0, current_stock: 0,
  lokasi_penyimpanan: '', expired_date: null, deskripsi: '', is_active: true, defers_on_bon: false
})

const stockForm = ref({ quantity: 0, notes: '' })

const getStockClass = (data) => {
  if (data.stock_status === 'out_of_stock') return 'text-red-500 font-semibold'
  if (data.stock_status === 'low_stock') return 'text-orange-500 font-semibold'
  return 'text-green-500'
}

const formatQty = (n) => Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })

const locIcon = (type) => ({
  warehouse:  'pi pi-box',
  production: 'pi pi-cog',
  retail:     'pi pi-shopping-bag',
}[type] || 'pi pi-building')

const getStockSeverity = (status) => ({ in_stock: 'success', low_stock: 'warning', out_of_stock: 'danger' }[status] || 'secondary')

const fetchMaterials = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.kategori_id) params.kategori_id = filters.value.kategori_id
    if (filters.value.stock_status) params.stock_status = filters.value.stock_status
    const response = await api.get(`/outlets/${outletId}/bahan-baku`, { params })
    materials.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch materials', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/kategori-bahan-baku`)
    categories.value = response.data
  } catch (error) { console.error(error) }
}

const fetchUnits = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/satuan`)
    units.value = response.data
  } catch (error) { console.error(error) }
}

const fetchSuppliers = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/supplier`)
    suppliers.value = response.data
  } catch (error) { console.error(error) }
}

const fetchStockHistory = async (materialId) => {
  loadingHistory.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/bahan-baku/${materialId}/stock-history`)
    stockHistory.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch stock history', life: 3000 })
  } finally {
    loadingHistory.value = false
  }
}

const openDialog = (material = null) => {
  isEdit.value = !!material
  form.value = material
    ? { ...material, defers_on_bon: !!material.defers_on_bon }
    : { nama: '', kategori_id: null, satuan_id: null, satuan_pembelian_id: null, jumlah_per_unit_pembelian: null, supplier_id: null, harga_beli: 0, minimum_stock: 0, current_stock: 0, lokasi_penyimpanan: '', expired_date: null, deskripsi: '', is_active: true, defers_on_bon: false }
  dialogVisible.value = true
}

const calculatePricePerBaseUnit = () => {
  if (!form.value.satuan_pembelian_id || !form.value.jumlah_per_unit_pembelian || !form.value.harga_beli) {
    return 0
  }
  return form.value.harga_beli / form.value.jumlah_per_unit_pembelian
}

const getBaseUnitName = () => {
  const unit = units.value.find(u => u.id === form.value.satuan_id)
  return unit ? unit.singkatan : ''
}

const getPurchaseUnitName = () => {
  const unit = units.value.find(u => u.id === form.value.satuan_pembelian_id)
  return unit ? unit.singkatan : ''
}

const openStockDialog = (material, operation) => {
  selectedMaterial.value = material
  stockOperation.value = operation
  stockForm.value = { quantity: 0, notes: '' }
  stockDialogVisible.value = true
}

const viewDetail = async (material) => {
  selectedMaterial.value = material
  locationStocks.value = []
  detailDialogVisible.value = true
  await Promise.all([
    fetchStockHistory(material.id),
    fetchLocationStocks(material.id),
  ])
}

const fetchLocationStocks = async (materialId) => {
  loadingLocationStocks.value = true
  try {
    // Use summary endpoint filtered by this bahan baku
    const res = await api.get(`/outlets/${outletId}/stock-movements/summary`)
    const item = res.data.find(d => d.id === materialId)
    locationStocks.value = item?.locations || []
  } catch (e) {
    console.error(e)
  } finally {
    loadingLocationStocks.value = false
  }
}

const saveMaterial = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/bahan-baku/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/bahan-baku`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchMaterials()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to save', life: 3000 })
  } finally {
    saving.value = false
  }
}

const saveStockOperation = async () => {
  saving.value = true
  try {
    const endpoint = stockOperation.value === 'add' ? 'add-stock' : 'reduce-stock'
    await api.post(`/outlets/${outletId}/bahan-baku/${selectedMaterial.value.id}/${endpoint}`, stockForm.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.savedSuccessfully'), life: 3000 })
    stockDialogVisible.value = false
    fetchMaterials()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to update stock', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (material) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: material.nama }),
    header: t('bahanBaku.deleteMaterial'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteMaterial(material.id)
  })
}

const deleteMaterial = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/bahan-baku/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchMaterials()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    deleting.value = false
  }
}

const resetFilters = () => {
  filters.value = { search: '', kategori_id: null, stock_status: null }
  fetchMaterials()
}

onMounted(() => {
  fetchOutlet()
  fetchMaterials()
  fetchCategories()
  fetchUnits()
  fetchSuppliers()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }

.loading-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
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

.loading-text { color: #1f2937; font-size: 1rem; font-weight: 600; margin: 0; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.filter-bar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}

.filter-fields {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: flex-end;
  flex: 1;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.filter-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.filter-input {
  width: 220px;
}

.filter-select {
  width: 200px;
}

.filter-reset-btn {
  white-space: nowrap;
  align-self: flex-end;
}

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

.dialog-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1rem 0;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field label {
  font-weight: 600;
  color: #374151;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
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
  color: #1f2937;
}

/* Stock per location */
.stock-locations-section {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.section-title {
  font-weight: 600;
  font-size: 0.95rem;
  color: #374151;
  margin: 0 0 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.location-stock-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
}

.location-stock-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: white;
}

.loc-type-warehouse  { border-left: 4px solid #3b82f6; background: #eff6ff; }
.loc-type-production { border-left: 4px solid #f59e0b; background: #fffbeb; }
.loc-type-retail     { border-left: 4px solid #22c55e; background: #f0fdf4; }
.loc-total           { border-left: 4px solid #6366f1; background: #f5f3ff; }

.loc-icon { font-size: 1.25rem; flex-shrink: 0; }
.loc-type-warehouse  .loc-icon { color: #3b82f6; }
.loc-type-production .loc-icon { color: #f59e0b; }
.loc-type-retail     .loc-icon { color: #22c55e; }
.loc-total           .loc-icon { color: #6366f1; }

.loc-info { flex: 1; min-width: 0; }
.loc-name { font-weight: 600; font-size: 0.875rem; color: #1f2937; }
.loc-type-label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

.loc-stock { font-size: 1.1rem; font-weight: 700; color: #1f2937; text-align: right; white-space: nowrap; }
.loc-unit  { font-size: 0.75rem; font-weight: 400; color: #6b7280; margin-left: 0.2rem; }

.empty-locations {
  padding: 1rem;
  text-align: center;
  color: #9ca3af;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.loading-center { display: flex; justify-content: center; padding: 1rem; }
</style>
