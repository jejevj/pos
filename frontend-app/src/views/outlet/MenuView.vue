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

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('menu.menus') }}</span>
          <Button :label="$t('menu.addMenu')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="menus" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>
          <template #header>
            <div class="table-header">
              <div class="flex gap-2">
                <IconField>
                  <InputIcon><i class="pi pi-search" /></InputIcon>
                  <InputText v-model="filters.search" :placeholder="$t('common.search')" @input="fetchMenus" />
                </IconField>
                <Select v-model="filters.kategori_id" :options="categories" optionLabel="nama" optionValue="id"
                        :placeholder="$t('menu.filterByCategory')" style="width: 180px" @change="fetchMenus" showClear />
              </div>
            </div>
          </template>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-book" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="kode" :header="$t('menu.menuCode')" sortable style="width: 140px" />
          <Column :header="$t('menu.image')" style="width: 80px">
            <template #body="{ data }">
              <div class="menu-image-cell">
                <img v-if="data.image_url" :src="data.image_url" :alt="getImageAlt(data.nama)" class="menu-image" />
                <div v-else class="menu-initial">{{ getInitials(data.nama) }}</div>
              </div>
            </template>
          </Column>
          <Column field="nama" :header="$t('menu.menuName')" sortable />
          <Column field="kategori.nama" :header="$t('menu.category')" sortable />
          <Column field="harga_jual" :header="$t('menu.sellingPrice')" sortable style="width: 150px">
            <template #body="{ data }">
              Rp {{ Number(data.harga_jual).toLocaleString('id-ID') }}
            </template>
          </Column>
          <Column field="harga_modal" :header="$t('menu.costPrice')" sortable style="width: 150px">
            <template #body="{ data }">
              Rp {{ Number(data.harga_modal).toLocaleString('id-ID') }}
            </template>
          </Column>
          <Column :header="$t('menu.availableQty')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag v-if="data.available_quantity > 0" 
                   :value="`${data.available_quantity} porsi`" 
                   severity="success" />
              <Tag v-else 
                   :value="$t('menu.outOfStock')" 
                   severity="danger" />
            </template>
          </Column>
          <Column :header="$t('menu.profit')" style="width: 120px">
            <template #body="{ data }">
              <span class="text-green-600 font-semibold">
                {{ ((data.harga_jual - data.harga_modal) / data.harga_jual * 100).toFixed(1) }}%
              </span>
            </template>
          </Column>
          <Column field="is_available" :header="$t('common.status')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.is_available ? $t('menu.available') : $t('menu.unavailable')"
                   :severity="data.is_available ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 120px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-eye" text rounded @click="viewDetail(data)" v-tooltip.top="'Detail'" />
                <Button icon="pi pi-pencil" text rounded severity="info" @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('menu.editMenu') : $t('menu.addMenu')"
            :modal="true" :style="{ width: '700px' }">
      <div class="dialog-content">
        <div class="field">
          <label>{{ $t('menu.menuName') }} *</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('menu.category') }} *</label>
          <Select v-model="form.kategori_id" :options="categories" optionLabel="nama" optionValue="id" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('station.station') }}</label>
          <Select v-model="form.station_id" :options="stations" optionLabel="nama" optionValue="id"
                  :placeholder="$t('station.noStation')" showClear style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('menu.imageUrl') }}</label>
          <div class="image-upload-container">
            <div v-if="form.gambar_url" class="image-preview">
              <img :src="form.gambar_url" :alt="form.nama" />
              <Button icon="pi pi-times" rounded severity="danger" size="small" @click="removeImage" class="remove-image-btn" />
            </div>
            <div v-else class="upload-placeholder">
              <i class="pi pi-image" style="font-size: 2rem; color: #9ca3af;"></i>
              <p class="text-muted">{{ $t('menu.noImage') }}</p>
            </div>
            <div class="upload-actions">
              <FileUpload mode="basic" name="image" accept="image/*" :maxFileSize="2000000" 
                          :chooseLabel="$t('menu.uploadImage')" 
                          @select="onImageSelect" 
                          :auto="false"
                          class="upload-btn" />
              <small class="text-muted">{{ $t('menu.imageUploadHint') }}</small>
            </div>
          </div>
        </div>
        <div class="field">
          <label>{{ $t('menu.description') }}</label>
          <Textarea v-model="form.deskripsi" rows="2" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('menu.sellingPrice') }} *</label>
          <InputNumber v-model="form.harga_jual" style="width: 100%" locale="id-ID" :minFractionDigits="0" :maxFractionDigits="0" />
          <small v-if="recommendedPrice > 0" class="text-success">
            {{ $t('menu.recommendedPrice') }}: Rp {{ Number(recommendedPrice).toLocaleString('id-ID') }}
          </small>
        </div>

        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.apply_fixed_cost" :binary="true" inputId="apply_fixed_cost" @change="calculateRecommendedPrice" />
            <label for="apply_fixed_cost" class="checkbox-label">{{ $t('menu.applyFixedCost') }}</label>
          </div>
          <small class="text-muted ml-6">{{ $t('menu.applyFixedCostHint') }}</small>
        </div>

        <div class="field">
          <label class="font-semibold">{{ $t('menu.ingredients') }}</label>
          <div v-for="(item, index) in form.bahan_baku" :key="index" class="ingredient-row">
            <Select v-model="item.bahan_baku_id" :options="materials" optionLabel="nama" optionValue="id"
                    :placeholder="$t('menu.selectIngredient')" class="ingredient-select" @change="onIngredientChange(item)" />
            <InputNumber v-model="item.jumlah" :placeholder="$t('menu.quantity')" class="ingredient-quantity" :minFractionDigits="0" :maxFractionDigits="4" @input="calculateRecommendedPrice" />
            <Select v-model="item.satuan_id" :options="getFilteredUnits(item.bahan_baku_id)" optionLabel="singkatan" optionValue="id"
                    :placeholder="'-'" class="ingredient-unit-select" @change="calculateRecommendedPrice" :disabled="!item.bahan_baku_id" />
            <div class="ingredient-cost">
              <span v-if="item.bahan_baku_id && item.jumlah">
                Rp {{ calculateIngredientCost(item).toLocaleString('id-ID') }}
              </span>
              <span v-else class="text-muted">-</span>
            </div>
            <Button icon="pi pi-trash" text rounded severity="danger" @click="removeIngredient(index)" class="delete-btn" />
          </div>
          <Button :label="$t('menu.addIngredient')" icon="pi pi-plus" text @click="addIngredient" class="mt-2" />
          
          <!-- Cost Summary -->
          <div v-if="form.bahan_baku.length > 0" class="cost-summary">
            <div class="cost-row">
              <span class="cost-label">{{ $t('menu.materialCost') }}:</span>
              <span class="cost-value">Rp {{ calculateMaterialCost().toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="form.apply_fixed_cost && outlet" class="cost-row">
              <span class="cost-label">
                {{ $t('menu.fixedCost') }}
                <span v-if="outlet.fixed_cost_type === 'percentage'" class="text-muted">
                  ({{ outlet.fixed_cost_percentage }}%)
                </span>
                <span v-else class="text-muted">
                  ({{ $t('outlet.nominal') }})
                </span>:
              </span>
              <span class="cost-value">Rp {{ calculateFixedCost().toLocaleString('id-ID') }}</span>
            </div>
            <div class="cost-row total-row">
              <span class="cost-label">{{ $t('menu.totalCost') }} (HPP):</span>
              <span class="cost-value">Rp {{ calculateTotalCost().toLocaleString('id-ID') }}</span>
            </div>
          </div>
        </div>

        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_available" :binary="true" inputId="is_available" />
            <label for="is_available">{{ $t('menu.available') }}</label>
          </div>
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('common.active') }}</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveMenu" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
      </template>
    </Dialog>

    <Dialog v-model:visible="detailDialogVisible"
            :header="selectedMenu?.nama"
            :modal="true" :style="{ width: '700px' }">
      <div v-if="selectedMenu" class="dialog-content">
        <div class="menu-detail-header">
          <div class="menu-image-large">
            <img v-if="selectedMenu.image_url" :src="selectedMenu.image_url" :alt="getImageAlt(selectedMenu.nama)" />
            <div v-else class="menu-initial-large">{{ getInitials(selectedMenu.nama) }}</div>
          </div>
          <div class="menu-info">
            <h3>{{ selectedMenu.nama }}</h3>
            <p class="text-muted">{{ selectedMenu.deskripsi || '-' }}</p>
          </div>
        </div>
        
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.menuCode') }}</span>
            <span class="detail-value">{{ selectedMenu.kode }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.category') }}</span>
            <span class="detail-value">{{ selectedMenu.kategori?.nama }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.costPrice') }} (HPP)</span>
            <span class="detail-value">Rp {{ Number(selectedMenu.harga_modal).toLocaleString('id-ID') }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.sellingPrice') }}</span>
            <span class="detail-value">Rp {{ Number(selectedMenu.harga_jual).toLocaleString('id-ID') }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.profit') }}</span>
            <span class="detail-value text-green-600">
              Rp {{ Number(selectedMenu.harga_jual - selectedMenu.harga_modal).toLocaleString('id-ID') }}
              ({{ ((selectedMenu.harga_jual - selectedMenu.harga_modal) / selectedMenu.harga_jual * 100).toFixed(1) }}%)
            </span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.fixedCostApplied') }}</span>
            <span class="detail-value">{{ selectedMenu.apply_fixed_cost ? $t('common.yes') : $t('common.no') }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('menu.availableQty') }}</span>
            <span class="detail-value" :class="selectedMenu.available_quantity > 0 ? 'text-green-600' : 'text-red-600'">
              {{ selectedMenu.available_quantity }} {{ $t('menu.portions') }}
            </span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('common.status') }}</span>
            <Tag :value="selectedMenu.can_be_made ? $t('menu.canBeMade') : $t('menu.cannotBeMade')" 
                 :severity="selectedMenu.can_be_made ? 'success' : 'danger'" />
          </div>
        </div>

        <div class="field">
          <p class="font-semibold mb-2">{{ $t('menu.ingredients') }}</p>
          <DataTable :value="selectedMenu.bahan_baku" stripedRows size="small">
            <Column field="bahan_baku.nama" :header="$t('menu.ingredientName')" />
            <Column field="jumlah" :header="$t('menu.requiredPerPortion')">
              <template #body="{ data }">{{ Number(data.jumlah).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }} {{ data.satuan?.singkatan }}</template>
            </Column>
            <Column :header="$t('menu.availableStock')">
              <template #body="{ data }">
                <span :class="data.bahan_baku.current_stock >= data.jumlah ? 'text-green-600' : 'text-red-600'">
                  {{ Number(data.bahan_baku.current_stock).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }} {{ data.satuan?.singkatan }}
                </span>
              </template>
            </Column>
            <Column :header="$t('menu.canMake')">
              <template #body="{ data }">
                <span class="font-semibold text-blue-600">
                  {{ Math.floor(data.bahan_baku.current_stock / data.jumlah) }} {{ $t('menu.portions') }}
                </span>
              </template>
            </Column>
            <Column :header="$t('menu.cost')">
              <template #body="{ data }">
                Rp {{ Number(data.bahan_baku.harga_beli * data.jumlah).toLocaleString('id-ID') }}
              </template>
            </Column>
            <Column field="keterangan" :header="$t('menu.notes')" />
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
import { compressForUpload } from '@/utils/imageCompression'
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
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import FileUpload from 'primevue/fileupload'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('menu.menus') }
])

const outlet = ref(null)
const menus = ref([])
const categories = ref([])
const stations = ref([])
const materials = ref([])
const units = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const detailDialogVisible = ref(false)
const saving = ref(false)
const isEdit = ref(false)
const selectedMenu = ref(null)
const recommendedPrice = ref(0)
const uploadingImage = ref(false)
const imagePath = ref(null) // Store path for deletion

const filters = ref({ search: '', kategori_id: null })

const form = ref({
  nama: '', kategori_id: null, station_id: null, deskripsi: '', gambar_url: '', harga_jual: 0,
  apply_fixed_cost: true, is_available: true, is_active: true, bahan_baku: []
})

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const fetchMenus = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.kategori_id) params.kategori_id = filters.value.kategori_id
    const response = await api.get(`/outlets/${outletId}/menu`, { params })
    menus.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch menus', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/kategori-menu`)
    categories.value = response.data
  } catch (error) { console.error(error) }
}

const fetchStations = async () => {
  try {
    const res = await api.get(`/outlets/${outletId}/stations`)
    stations.value = res.data
  } catch (e) { console.error(e) }
}

const fetchMaterials = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/bahan-baku`)
    materials.value = response.data
  } catch (error) { console.error(error) }
}

const fetchUnits = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/satuan`)
    units.value = response.data
  } catch (error) { console.error(error) }
}

const openDialog = (menu = null) => {
  isEdit.value = !!menu
  if (menu) {
    form.value = {
      ...menu,
      apply_fixed_cost: menu.apply_fixed_cost ?? true,
      bahan_baku: menu.bahan_baku?.map(bb => ({
        bahan_baku_id: bb.bahan_baku_id,
        satuan_id: bb.satuan_id,
        jumlah: bb.jumlah,
        keterangan: bb.keterangan
      })) || []
    }
  } else {
    form.value = {
      nama: '', kategori_id: null, station_id: null, deskripsi: '', gambar_url: '', harga_jual: 0,
      apply_fixed_cost: true, is_available: true, is_active: true, bahan_baku: []
    }
  }
  calculateRecommendedPrice()
  dialogVisible.value = true
}

const calculateIngredientCost = (item) => {
  if (!item.bahan_baku_id || !item.jumlah) return 0
  const material = materials.value.find(m => m.id === item.bahan_baku_id)
  if (!material) return 0
  // Use harga_per_satuan_dasar if available (price per base unit), otherwise use harga_beli
  const pricePerBaseUnit = material.harga_per_satuan_dasar || material.harga_beli
  // Get conversion factor for the selected satuan
  const satuan = units.value.find(u => u.id === item.satuan_id)
  const convFactor = satuan
    ? (satuan.is_base_unit ? 1 : Number(satuan.conversion_to_base))
    : 1
  return pricePerBaseUnit * (item.jumlah * convFactor)
}

const getMaterialUnit = (materialId) => {
  if (!materialId) return ''
  const material = materials.value.find(m => m.id === materialId)
  return material?.satuan?.singkatan || ''
}

// Return units with the same tipe as the selected bahan baku's satuan
const getFilteredUnits = (materialId) => {
  if (!materialId) return units.value
  const material = materials.value.find(m => m.id === materialId)
  if (!material?.satuan?.tipe) return units.value
  return units.value.filter(u => u.tipe === material.satuan.tipe)
}

const getInitials = (name) => {
  if (!name) return '?'
  const words = name.trim().split(' ')
  if (words.length === 1) {
    return words[0].substring(0, 2).toUpperCase()
  }
  return words.slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

const onImageSelect = async (event) => {
  const raw = event.files[0]
  if (!raw) return

  uploadingImage.value = true

  try {
    const { file } = await compressForUpload(raw, { maxSizeMB: 0.6, maxWidthOrHeight: 1600 })
    const formData = new FormData()
    formData.append('image', file)

    const response = await api.post(`/outlets/${outletId}/menu/upload-image`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    form.value.gambar_url = response.data.url
    imagePath.value = response.data.path
    
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('menu.imageUploaded'), 
      life: 3000 
    })
  } catch (error) {
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: error.response?.data?.message || 'Failed to upload image', 
      life: 3000 
    })
  } finally {
    uploadingImage.value = false
  }
}

const removeImage = async () => {
  if (imagePath.value) {
    try {
      await api.post(`/outlets/${outletId}/menu/delete-image`, {
        path: imagePath.value
      })
    } catch (error) {
      console.error('Failed to delete image:', error)
    }
  }
  
  form.value.gambar_url = ''
  imagePath.value = null
}

const getImageAlt = (menuName) => {
  return `${menuName} di ${outlet.value?.name || 'Outlet'}`
}

const calculateRecommendedPrice = () => {
  const total = calculateTotalCost()
  
  // Recommended price with 30% markup (divide by 0.7 to get price where profit is 30%)
  // Formula: selling_price = cost / (1 - markup_percentage)
  // For 30% profit margin: selling_price = cost / 0.7
  recommendedPrice.value = total > 0 ? Math.ceil(total / 0.7) : 0
}

const calculateMaterialCost = () => {
  let total = 0
  form.value.bahan_baku.forEach(item => {
    total += calculateIngredientCost(item)
  })
  return total
}

const calculateFixedCost = () => {
  if (!form.value.apply_fixed_cost || !outlet.value) return 0
  
  const materialCost = calculateMaterialCost()
  
  if (outlet.value.fixed_cost_type === 'percentage' && outlet.value.fixed_cost_percentage > 0) {
    return materialCost * (outlet.value.fixed_cost_percentage / 100)
  } else if (outlet.value.fixed_cost_type === 'nominal' && outlet.value.fixed_cost_nominal > 0) {
    return parseFloat(outlet.value.fixed_cost_nominal)
  }
  
  return 0
}

const calculateTotalCost = () => {
  return calculateMaterialCost() + calculateFixedCost()
}

const addIngredient = () => {
  form.value.bahan_baku.push({ bahan_baku_id: null, satuan_id: null, jumlah: 0, keterangan: '' })
}

const onIngredientChange = (item) => {
  // Auto-set satuan_id based on selected bahan baku
  if (item.bahan_baku_id) {
    const material = materials.value.find(m => m.id === item.bahan_baku_id)
    if (material) {
      // Default to the bahan baku's own satuan (base unit of that material)
      item.satuan_id = material.satuan_id
    }
  } else {
    item.satuan_id = null
  }
  calculateRecommendedPrice()
}

const removeIngredient = (index) => {
  form.value.bahan_baku.splice(index, 1)
  calculateRecommendedPrice()
}

const viewDetail = (menu) => {
  selectedMenu.value = menu
  detailDialogVisible.value = true
}

const saveMenu = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/menu/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/menu`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchMenus()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to save', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (menu) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: menu.nama }),
    header: t('menu.deleteMenu'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteMenu(menu.id)
  })
}

const deleteMenu = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/menu/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchMenus()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  }
}

onMounted(() => {
  fetchOutlet()
  fetchMenus()
  fetchCategories()
  fetchStations()
  fetchMaterials()
  fetchUnits()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }
.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.table-header { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
.action-buttons { display: flex; gap: 0.25rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 3rem; color: #6b7280; }
.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }
.field { display: flex; flex-direction: column; gap: 0.5rem; }
.field label { font-weight: 600; color: #374151; }

.checkbox-label {
  font-weight: 500 !important;
  cursor: pointer;
  user-select: none;
}

.ml-6 {
  margin-left: 1.5rem;
}

.ingredient-row { 
  display: grid;
  grid-template-columns: 2fr 100px 110px 110px 40px;
  gap: 0.5rem; 
  align-items: center; 
  margin-bottom: 0.5rem; 
}

.ingredient-select {
  min-width: 0;
}

.ingredient-quantity {
  width: 100%;
}

.ingredient-unit-select {
  width: 100%;
}

.ingredient-cost {
  text-align: right;
  font-weight: 600;
  color: #059669;
  font-size: 0.875rem;
  white-space: nowrap;
}

.delete-btn {
  padding: 0.5rem;
}

.detail-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 8px; }
.detail-item { display: flex; flex-direction: column; gap: 0.25rem; }
.detail-label { font-size: 0.875rem; color: #6b7280; }
.detail-value { font-weight: 600; color: #1f2937; }

.text-success {
  color: #22c55e;
  font-size: 0.875rem;
  display: block;
  margin-top: 0.25rem;
  font-weight: 600;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  display: block;
  margin-top: 0.25rem;
}

.menu-image-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-image {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  object-fit: cover;
  border: 2px solid #e5e7eb;
}

.menu-initial {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  border: 2px solid #e5e7eb;
}

.menu-detail-header {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.menu-image-large {
  flex-shrink: 0;
}

.menu-image-large img {
  width: 120px;
  height: 120px;
  border-radius: 12px;
  object-fit: cover;
  border: 3px solid #e5e7eb;
}

.menu-initial-large {
  width: 120px;
  height: 120px;
  border-radius: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 2.5rem;
  border: 3px solid #e5e7eb;
}

.menu-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.menu-info h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
  color: #1f2937;
}

.menu-info .text-muted {
  margin: 0;
  font-size: 0.875rem;
  color: #6b7280;
}

.image-upload-container {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.image-preview {
  position: relative;
  width: 150px;
  height: 150px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e5e7eb;
}

.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-image-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 32px;
  height: 32px;
}

.upload-placeholder {
  width: 150px;
  height: 150px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
}

.upload-placeholder p {
  margin: 0.5rem 0 0 0;
  font-size: 0.875rem;
}

.upload-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}

.upload-btn {
  width: 100%;
}

.cost-summary {
  margin-top: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.cost-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  font-size: 0.875rem;
}

.cost-row:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.cost-label {
  color: #6b7280;
  font-weight: 500;
}

.cost-value {
  color: #1f2937;
  font-weight: 600;
}

.total-row {
  margin-top: 0.5rem;
  padding-top: 0.75rem;
  border-top: 2px solid #d1d5db !important;
}

.total-row .cost-label {
  color: #1f2937;
  font-weight: 700;
  font-size: 1rem;
}

.total-row .cost-value {
  color: #059669;
  font-weight: 700;
  font-size: 1.125rem;
}
</style>
