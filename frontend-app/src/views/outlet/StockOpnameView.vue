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

    <!-- List View -->
    <Card v-if="currentView === 'list'">
      <template #title>
        <div class="card-header">
          <div>
            <h3 class="m-0">{{ $t('stockOpname.list') }}</h3>
            <p class="text-muted m-0 mt-1">{{ $t('stockOpname.description') }}</p>
          </div>
          <Button :label="$t('stockOpname.createSchedule')" icon="pi pi-plus" @click="showCreateDialog" />
        </div>
      </template>
      <template #content>
        <DataTable :value="stockOpnames" :loading="loading" stripedRows showGridlines>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-calendar-times" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('stockOpname.noStockOpname') }}</p>
              <p class="text-muted">{{ $t('stockOpname.createFirst') }}</p>
            </div>
          </template>
          <Column field="kode" :header="$t('stockOpname.code')" style="width: 150px" />
          <Column field="tanggal_mulai" :header="$t('stockOpname.startDate')" style="width: 120px">
            <template #body="{ data }">
              {{ formatDate(data.tanggal_mulai) }}
            </template>
          </Column>
          <Column field="tanggal_selesai" :header="$t('stockOpname.endDate')" style="width: 120px">
            <template #body="{ data }">
              {{ formatDate(data.tanggal_selesai) }}
            </template>
          </Column>
          <Column field="pic_name" :header="$t('stockOpname.picName')" />
          <Column field="total_items" :header="$t('stockOpname.totalItems')" style="width: 100px" />
          <Column field="status" :header="$t('stockOpname.status')" style="width: 150px">
            <template #body="{ data }">
              <Tag :value="$t(getStatusTranslationKey(data.status))" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 200px">
            <template #body="{ data }">
              <Button icon="pi pi-eye" text rounded @click="viewDetail(data.id)" v-tooltip.top="$t('stockOpname.viewDetail')" />
              <Button v-if="data.status === 'approved'" icon="pi pi-chart-bar" text rounded severity="success" 
                      @click="viewReport(data.id)" v-tooltip.top="$t('stockOpname.viewReport')" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Detail/Edit View -->
    <Card v-if="currentView === 'detail' && selectedOpname">
      <template #title>
        <div class="card-header">
          <div>
            <h3 class="m-0">{{ selectedOpname.kode }}</h3>
            <Tag :value="$t(getStatusTranslationKey(selectedOpname.status))" 
                 :severity="getStatusSeverity(selectedOpname.status)" class="mt-2" />
          </div>
          <div style="display: flex; gap: 0.5rem;">
            <Button :label="$t('stockOpname.backToList')" icon="pi pi-arrow-left" text @click="backToList" />
            <Button v-if="selectedOpname.is_editable" :label="$t('stockOpname.saveProgress')" 
                    icon="pi pi-save" @click="saveProgress" :loading="saving" />
            <Button v-if="selectedOpname.can_submit" :label="$t('stockOpname.submitForReview')" 
                    icon="pi pi-send" @click="confirmSubmitOpname" severity="success" />
            <Button v-if="selectedOpname.can_approve" :label="$t('stockOpname.approve')" 
                    icon="pi pi-check" @click="confirmApproveOpname" severity="success" />
            <Button v-if="selectedOpname.can_approve" :label="$t('stockOpname.reject')" 
                    icon="pi pi-times" @click="confirmRejectOpname" severity="danger" />
          </div>
        </div>
      </template>
      <template #content>
        <!-- Schedule Info -->
        <div class="info-grid mb-4">
          <div class="info-item">
            <label>{{ $t('stockOpname.startDate') }}</label>
            <div>{{ formatDate(selectedOpname.tanggal_mulai) }}</div>
          </div>
          <div class="info-item">
            <label>{{ $t('stockOpname.endDate') }}</label>
            <div>{{ formatDate(selectedOpname.tanggal_selesai) }}</div>
          </div>
          <div class="info-item">
            <label>{{ $t('stockOpname.picName') }}</label>
            <div>{{ selectedOpname.pic_name }}</div>
          </div>
          <div class="info-item">
            <label>{{ $t('stockOpname.totalItems') }}</label>
            <div>{{ selectedOpname.total_items }}</div>
          </div>
        </div>

        <!-- Approval Section (for submitted status) -->
        <div v-if="selectedOpname.status === 'submitted'" class="approval-section mb-4">
          <h4>{{ $t('stockOpname.reviewApproval') }}</h4>
          <p class="text-muted">{{ $t('stockOpname.reviewDesc') }}</p>
          <Textarea v-model="approvalNotes" :placeholder="$t('stockOpname.approvalNotesPlaceholder')" 
                    rows="3" style="width: 100%" />
        </div>

        <!-- Details Table -->
        <DataTable :value="selectedOpname.details" :loading="loadingDetail" stripedRows showGridlines>
          <Column field="bahan_baku.nama" :header="$t('stockOpname.materialName')" />
          <Column :header="$t('stockOpname.systemStock')" style="width: 150px">
            <template #body="{ data }">
              {{ formatNumber(data.system_stock) }} {{ data.bahan_baku?.satuan?.singkatan }}
            </template>
          </Column>
          <Column :header="$t('stockOpname.physicalStock')" style="width: 180px">
            <template #body="{ data }">
              <InputNumber v-if="selectedOpname.is_editable" v-model="data.physical_stock" 
                           :minFractionDigits="0" :maxFractionDigits="2" style="width: 100%" 
                           @input="calculateDifference(data)" />
              <span v-else>{{ formatNumber(data.physical_stock) }} {{ data.bahan_baku?.satuan?.singkatan }}</span>
            </template>
          </Column>
          <Column :header="$t('stockOpname.difference')" style="width: 150px">
            <template #body="{ data }">
              <Tag v-if="data.difference !== null && data.difference !== undefined && !isNaN(data.difference)" 
                   :value="formatDifference(data.difference, data.bahan_baku?.satuan?.singkatan)" 
                   :severity="getDifferenceSeverity(data.difference)" />
              <span v-else class="text-muted">-</span>
            </template>
          </Column>
          <Column :header="$t('stockOpname.differenceValue')" style="width: 150px">
            <template #body="{ data }">
              <span v-if="data.difference_value !== null && data.difference_value !== undefined && !isNaN(data.difference_value)" 
                    :class="Number(data.difference_value) >= 0 ? 'text-success' : 'text-danger'">
                {{ formatCurrency(data.difference_value) }}
              </span>
              <span v-else class="text-muted">-</span>
            </template>
          </Column>
          <Column :header="$t('stockOpname.itemNotes')" style="width: 200px">
            <template #body="{ data }">
              <InputText v-if="selectedOpname.is_editable" v-model="data.notes" 
                         :placeholder="$t('stockOpname.notesPlaceholder')" style="width: 100%" />
              <span v-else>{{ data.notes || '-' }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Report View -->
    <Card v-if="currentView === 'report' && reportData">
      <template #title>
        <div class="card-header">
          <div>
            <h3 class="m-0">{{ $t('stockOpname.report') }}</h3>
            <p class="text-muted m-0 mt-1">{{ reportData.stock_opname.kode }}</p>
          </div>
          <Button :label="$t('stockOpname.backToList')" icon="pi pi-arrow-left" text @click="backToList" />
        </div>
      </template>
      <template #content>
        <!-- Summary Cards -->
        <div class="summary-grid mb-4">
          <div class="summary-card profit">
            <i class="pi pi-arrow-up"></i>
            <div>
              <label>{{ $t('stockOpname.totalProfit') }}</label>
              <div class="value">{{ formatCurrency(reportData.summary.total_profit) }}</div>
              <small>{{ reportData.summary.profit_items_count }} {{ $t('stockOpname.profitItems') }}</small>
            </div>
          </div>
          <div class="summary-card loss">
            <i class="pi pi-arrow-down"></i>
            <div>
              <label>{{ $t('stockOpname.totalLoss') }}</label>
              <div class="value">{{ formatCurrency(reportData.summary.total_loss) }}</div>
              <small>{{ reportData.summary.loss_items_count }} {{ $t('stockOpname.lossItems') }}</small>
            </div>
          </div>
          <div class="summary-card net" :class="reportData.summary.net_difference >= 0 ? 'profit' : 'loss'">
            <i :class="reportData.summary.net_difference >= 0 ? 'pi pi-check-circle' : 'pi pi-exclamation-circle'"></i>
            <div>
              <label>{{ $t('stockOpname.netDifference') }}</label>
              <div class="value">{{ formatCurrency(reportData.summary.net_difference) }}</div>
            </div>
          </div>
        </div>

        <!-- Profit Items -->
        <div v-if="reportData.profit_items.length > 0" class="mb-4">
          <h4>{{ $t('stockOpname.profitItemsDetail') }}</h4>
          <DataTable :value="reportData.profit_items" stripedRows>
            <Column field="bahan_baku.nama" :header="$t('stockOpname.materialName')" />
            <Column :header="$t('stockOpname.difference')">
              <template #body="{ data }">
                +{{ formatNumber(data.difference) }} {{ data.bahan_baku?.satuan?.singkatan }}
              </template>
            </Column>
            <Column :header="$t('stockOpname.differenceValue')">
              <template #body="{ data }">
                <span class="text-success">{{ formatCurrency(data.difference_value) }}</span>
              </template>
            </Column>
            <Column field="notes" :header="$t('stockOpname.itemNotes')" />
          </DataTable>
        </div>

        <!-- Loss Items -->
        <div v-if="reportData.loss_items.length > 0">
          <h4>{{ $t('stockOpname.lossItemsDetail') }}</h4>
          <DataTable :value="reportData.loss_items" stripedRows>
            <Column field="bahan_baku.nama" :header="$t('stockOpname.materialName')" />
            <Column :header="$t('stockOpname.difference')">
              <template #body="{ data }">
                {{ formatNumber(data.difference) }} {{ data.bahan_baku?.satuan?.singkatan }}
              </template>
            </Column>
            <Column :header="$t('stockOpname.differenceValue')">
              <template #body="{ data }">
                <span class="text-danger">{{ formatCurrency(data.difference_value) }}</span>
              </template>
            </Column>
            <Column field="notes" :header="$t('stockOpname.itemNotes')" />
          </DataTable>
        </div>
      </template>
    </Card>

    <!-- Create Dialog -->
    <Dialog v-model:visible="createDialogVisible" :header="$t('stockOpname.scheduleForm')" modal :style="{ width: '600px' }">
      <p class="text-muted mb-4">{{ $t('stockOpname.scheduleFormDesc') }}</p>
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('stockOpname.startDate') }} *</label>
          <DatePicker v-model="formData.tanggal_mulai" dateFormat="yy-mm-dd" showIcon fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('stockOpname.endDate') }} *</label>
          <DatePicker v-model="formData.tanggal_selesai" dateFormat="yy-mm-dd" showIcon fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('stockOpname.picName') }} *</label>
          <Select
            v-model="formData.pic_user_id"
            :options="picOptions"
            optionLabel="name"
            optionValue="id"
            :placeholder="$t('stockOpname.picNameHelp')"
            :loading="loadingPicOptions"
            :emptyMessage="$t('stockOpname.noPicAvailable')"
            filter
            fluid
          />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="formData.notes" rows="3" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="createDialogVisible = false" />
        <Button :label="$t('common.create')" @click="createSchedule" :loading="creating" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('stockOpname.title') }
])

const outlet = ref(null)
const stockOpnames = ref([])
const selectedOpname = ref(null)
const reportData = ref(null)
const loading = ref(false)
const loadingDetail = ref(false)
const saving = ref(false)
const creating = ref(false)
const currentView = ref('list')
const createDialogVisible = ref(false)
const approvalNotes = ref('')

const formData = ref({
  tanggal_mulai: null,
  tanggal_selesai: null,
  pic_user_id: null,
  notes: ''
})

const picOptions = ref([])
const loadingPicOptions = ref(false)

const fetchPicOptions = async () => {
  loadingPicOptions.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/stock-opname/pic-options`)
    picOptions.value = Array.isArray(response.data) ? response.data : []
  } catch (error) {
    picOptions.value = []
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: error.response?.data?.message || 'Failed to fetch PIC options',
      life: 3000
    })
  } finally {
    loadingPicOptions.value = false
  }
}

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const fetchStockOpnames = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/stock-opname`)
    stockOpnames.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to fetch stock opnames', life: 3000 })
  } finally {
    loading.value = false
  }
}

const showCreateDialog = () => {
  formData.value = {
    tanggal_mulai: null,
    tanggal_selesai: null,
    pic_user_id: null,
    notes: ''
  }
  createDialogVisible.value = true
  if (picOptions.value.length === 0) {
    fetchPicOptions()
  }
}

const createSchedule = async () => {
  if (!formData.value.pic_user_id) {
    toast.add({
      severity: 'warn',
      summary: t('messages.warning'),
      detail: t('stockOpname.picRequired'),
      life: 3000
    })
    return
  }
  const selectedPic = picOptions.value.find(u => u.id === formData.value.pic_user_id)
  if (!selectedPic) {
    toast.add({
      severity: 'warn',
      summary: t('messages.warning'),
      detail: t('stockOpname.picRequired'),
      life: 3000
    })
    return
  }
  creating.value = true
  try {
    const payload = {
      tanggal_mulai: formatDateForAPI(formData.value.tanggal_mulai),
      tanggal_selesai: formatDateForAPI(formData.value.tanggal_selesai),
      pic_user_id: selectedPic.id,
      pic_name: selectedPic.name,
      notes: formData.value.notes
    }
    await api.post(`/outlets/${outletId}/stock-opname`, payload)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockOpname.createdSuccessfully'), life: 3000 })
    createDialogVisible.value = false
    await fetchStockOpnames()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to create schedule', life: 3000 })
  } finally {
    creating.value = false
  }
}

const viewDetail = async (id) => {
  loadingDetail.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/stock-opname/${id}`)
    selectedOpname.value = response.data
    currentView.value = 'detail'
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to fetch detail', life: 3000 })
  } finally {
    loadingDetail.value = false
  }
}

const viewReport = async (id) => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/stock-opname/${id}/report`)
    reportData.value = response.data
    currentView.value = 'report'
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to fetch report', life: 3000 })
  } finally {
    loading.value = false
  }
}

const backToList = () => {
  currentView.value = 'list'
  selectedOpname.value = null
  reportData.value = null
  approvalNotes.value = ''
  fetchStockOpnames()
}

const calculateDifference = (detail) => {
  // Only calculate if physical_stock is a valid number
  if (detail.physical_stock !== null && detail.physical_stock !== undefined && detail.physical_stock !== '') {
    const physical = Number(detail.physical_stock)
    const system = Number(detail.system_stock) || 0
    
    // Check if conversion resulted in valid number
    if (!isNaN(physical)) {
      detail.difference = physical - system
      
      // Calculate difference value if we have price info
      if (detail.bahan_baku?.harga_per_satuan_dasar || detail.bahan_baku?.harga_beli) {
        const pricePerUnit = detail.bahan_baku.harga_per_satuan_dasar || detail.bahan_baku.harga_beli
        detail.difference_value = detail.difference * pricePerUnit
      }
    } else {
      detail.difference = null
      detail.difference_value = null
    }
  } else {
    detail.difference = null
    detail.difference_value = null
  }
}

const saveProgress = async () => {
  // Validate that at least one item has physical stock filled
  const hasData = selectedOpname.value.details.some(d => 
    d.physical_stock !== null && d.physical_stock !== undefined && d.physical_stock !== ''
  )
  
  if (!hasData) {
    toast.add({ 
      severity: 'warn', 
      summary: t('messages.warning'), 
      detail: 'Harap isi minimal satu stok fisik', 
      life: 3000 
    })
    return
  }
  
  saving.value = true
  try {
    const payload = {
      details: selectedOpname.value.details.map(d => ({
        id: d.id,
        physical_stock: d.physical_stock !== null && d.physical_stock !== undefined && d.physical_stock !== '' 
          ? Number(d.physical_stock) 
          : null,
        notes: d.notes || null
      }))
    }
    
    const response = await api.put(`/outlets/${outletId}/stock-opname/${selectedOpname.value.id}`, payload)
    selectedOpname.value = response.data.data
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockOpname.updatedSuccessfully'), life: 3000 })
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to save'
    toast.add({ severity: 'error', summary: t('messages.error'), detail: errorMessage, life: 5000 })
    console.error('Save error:', error.response?.data)
  } finally {
    saving.value = false
  }
}

const confirmSubmitOpname = () => {
  confirm.require({
    message: t('stockOpname.confirmSubmit'),
    header: t('stockOpname.submitForReview'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: submitOpname
  })
}

const submitOpname = async () => {
  try {
    await api.post(`/outlets/${outletId}/stock-opname/${selectedOpname.value.id}/submit`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockOpname.submittedSuccessfully'), life: 3000 })
    await viewDetail(selectedOpname.value.id)
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to submit', life: 3000 })
  }
}

const confirmApproveOpname = () => {
  confirm.require({
    message: t('stockOpname.confirmApprove'),
    header: t('stockOpname.approve'),
    icon: 'pi pi-check-circle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: approveOpname
  })
}

const approveOpname = async () => {
  try {
    await api.post(`/outlets/${outletId}/stock-opname/${selectedOpname.value.id}/approve`, {
      approval_notes: approvalNotes.value
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockOpname.approvedSuccessfully'), life: 3000 })
    backToList()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to approve', life: 3000 })
  }
}

const confirmRejectOpname = () => {
  if (!approvalNotes.value) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('stockOpname.approvalNotesRequired'), life: 3000 })
    return
  }
  confirm.require({
    message: t('stockOpname.confirmReject'),
    header: t('stockOpname.reject'),
    icon: 'pi pi-times-circle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: rejectOpname
  })
}

const rejectOpname = async () => {
  try {
    await api.post(`/outlets/${outletId}/stock-opname/${selectedOpname.value.id}/reject`, {
      approval_notes: approvalNotes.value
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockOpname.rejectedSuccessfully'), life: 3000 })
    backToList()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to reject', life: 3000 })
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  if (isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatDateForAPI = (date) => {
  if (!date) return null
  const d = new Date(date)
  if (isNaN(d.getTime())) return null
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatNumber = (num) => {
  if (num === null || num === undefined) return '-'
  return Number(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

const formatCurrency = (num) => {
  if (num === null || num === undefined) return '-'
  return 'Rp ' + Number(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const formatDifference = (diff, unit) => {
  if (diff === null || diff === undefined || isNaN(diff)) return '-'
  const formatted = formatNumber(Math.abs(diff))
  const sign = diff > 0 ? '+' : (diff < 0 ? '-' : '')
  return `${sign}${formatted} ${unit}`
}

const getDifferenceSeverity = (diff) => {
  if (diff === null || diff === undefined || isNaN(diff)) return 'secondary'
  const numDiff = Number(diff)
  if (numDiff === 0) return 'secondary'
  return numDiff > 0 ? 'success' : 'danger'
}

const getStatusSeverity = (status) => {
  const severities = {
    draft: 'secondary',
    in_progress: 'info',
    submitted: 'warn',
    approved: 'success',
    rejected: 'danger'
  }
  return severities[status] || 'secondary'
}

const getStatusTranslationKey = (status) => {
  const keys = {
    draft: 'Draft',
    in_progress: 'InProgress',
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Rejected'
  }
  return `stockOpname.status${keys[status] || 'Draft'}`
}

onMounted(() => {
  fetchOutlet()
  fetchStockOpnames()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.info-item label {
  display: block;
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.info-item div {
  font-weight: 600;
  color: #111827;
}

.approval-section {
  padding: 1rem;
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  border-radius: 4px;
}

.approval-section h4 {
  margin: 0 0 0.5rem 0;
  color: #92400e;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 8px;
  background: white;
  border: 2px solid #e5e7eb;
}

.summary-card i {
  font-size: 2.5rem;
}

.summary-card.profit {
  border-color: #10b981;
  background: #f0fdf4;
}

.summary-card.profit i {
  color: #10b981;
}

.summary-card.loss {
  border-color: #ef4444;
  background: #fef2f2;
}

.summary-card.loss i {
  color: #ef4444;
}

.summary-card label {
  display: block;
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.summary-card .value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.summary-card small {
  font-size: 0.75rem;
  color: #9ca3af;
}

.text-success {
  color: #10b981;
  font-weight: 600;
}

.text-danger {
  color: #ef4444;
  font-weight: 600;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field.full-width {
  grid-column: 1 / -1;
}

.form-field label {
  font-weight: 600;
  color: #374151;
}
</style>
