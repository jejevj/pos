<template>
  <div class="table-management">
    <div class="page-header">
      <div>
        <h2>{{ $t('table.title') }}</h2>
        <p class="text-muted">{{ $t('table.subtitle') }}</p>
      </div>
      <Button :label="$t('table.addTable')" icon="pi pi-plus" @click="openDialog()" />
    </div>

    <DataTable :value="tables" :loading="loading" stripedRows>
      <Column field="table_number" :header="$t('table.tableNumber')" sortable />
      <Column field="capacity" :header="$t('table.capacity')" sortable>
        <template #body="{ data }">
          {{ data.capacity }} {{ $t('common.seats') }}
        </template>
      </Column>
      <Column field="area" :header="$t('table.area')" sortable>
        <template #body="{ data }">
          <Tag :value="getAreaLabel(data.area)" :severity="getAreaSeverity(data.area)" />
        </template>
      </Column>
      <Column field="status" :header="$t('table.status')" sortable>
        <template #body="{ data }">
          <Tag :value="getStatusLabel(data.status)" :severity="getStatusSeverity(data.status)" />
        </template>
      </Column>
      <Column field="is_active" :header="$t('common.status')" sortable>
        <template #body="{ data }">
          <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')" 
               :severity="data.is_active ? 'success' : 'danger'" />
        </template>
      </Column>
      <Column :header="$t('common.actions')" style="width: 260px">
        <template #body="{ data }">
          <Button icon="pi pi-qrcode" text rounded severity="info" @click="openQrDialog(data)"
                  v-tooltip.top="$t('tableQr.showQr')" />
          <Button icon="pi pi-pencil" text rounded @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)"
                  v-tooltip.top="$t('common.delete')" />
          <Button v-if="data.status === 'occupied'" icon="pi pi-broom" text rounded severity="warning"
                  @click="confirmCleanup(data)" v-tooltip.top="$t('table.cleanup')" />
        </template>
      </Column>
    </DataTable>

    <!-- QR Code Dialog -->
    <Dialog v-model:visible="qrDialogVisible" :header="$t('tableQr.qrCode')" modal :style="{ width: '420px' }">
      <div v-if="qrTable" style="text-align: center; padding: 10px;">
        <p style="margin-bottom: 6px;">
          <strong>{{ $t('table.tableNumber') }}:</strong> {{ qrTable.table_number }}
        </p>
        <p style="font-size: 12px; color: #666; word-break: break-all; margin: 0 0 12px;">
          {{ qrPublicUrl }}
        </p>
        <div style="background: #fff; padding: 16px; display: inline-block; border-radius: 12px; border: 1px solid #eee;">
          <img v-if="qrImageData" :src="qrImageData" alt="QR" style="width: 240px; height: 240px;" />
          <div v-else style="width: 240px; height: 240px; display: flex; align-items: center; justify-content: center; color: #999;">
            ...
          </div>
        </div>
        <p style="margin-top: 12px; font-size: 13px; color: #555;">
          <i class="pi pi-info-circle"></i> {{ $t('tableQr.scanToOrder') }}
        </p>
        <div style="display: flex; gap: 8px; margin-top: 14px; justify-content: center; flex-wrap: wrap;">
          <Button :label="$t('tableQr.copyLink')" icon="pi pi-copy" outlined size="small" @click="copyQrLink" />
          <Button :label="$t('tableQr.downloadQr')" icon="pi pi-download" outlined size="small" @click="downloadQr" />
          <Button :label="$t('tableQr.regenerate')" icon="pi pi-refresh" outlined severity="warning" size="small"
                  @click="regenerateToken(qrTable)" :loading="regenerating" />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="qrDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="dialogVisible" :header="isEdit ? $t('table.editTable') : $t('table.addTable')" 
            modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('table.tableNumber') }} *</label>
          <InputText v-model="form.table_number" :placeholder="$t('table.tableNumber')" fluid />
        </div>

        <div class="form-field">
          <label>{{ $t('table.capacity') }} *</label>
          <InputNumber v-model="form.capacity" :min="1" :max="20" showButtons fluid />
        </div>

        <div class="form-field">
          <label>{{ $t('table.area') }} *</label>
          <Select v-model="form.area" :options="areaOptions" optionLabel="label" optionValue="value" 
                  :placeholder="$t('table.selectArea')" fluid />
        </div>

        <div v-if="isEdit" class="form-field">
          <label>{{ $t('table.status') }}</label>
          <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" 
                  :placeholder="$t('table.selectStatus')" fluid />
        </div>

        <div class="form-field">
          <label class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" />
            {{ $t('common.active') }}
          </label>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveTable" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const tables = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const isEdit = ref(false)

const form = ref({
  table_number: '',
  capacity: 4,
  area: 'indoor',
  status: 'available',
  is_active: true
})

const areaOptions = [
  { label: t('table.indoor'), value: 'indoor' },
  { label: t('table.outdoor'), value: 'outdoor' },
  { label: t('table.vip'), value: 'vip' }
]

const statusOptions = [
  { label: t('table.available'), value: 'available' },
  { label: t('table.occupied'), value: 'occupied' },
  { label: t('table.reserved'), value: 'reserved' }
]

const fetchTables = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/tables`)
    tables.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch tables', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (table = null) => {
  if (table) {
    isEdit.value = true
    form.value = { ...table }
  } else {
    isEdit.value = false
    form.value = {
      table_number: '',
      capacity: 4,
      area: 'indoor',
      status: 'available',
      is_active: true
    }
  }
  dialogVisible.value = true
}

const saveTable = async () => {
  if (!form.value.table_number || !form.value.capacity || !form.value.area) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('messages.fillRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/tables/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('table.updateSuccess'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/tables`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('table.createSuccess'), life: 3000 })
    }
    dialogVisible.value = false
    fetchTables()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to save table', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (table) => {
  confirm.require({
    message: `${t('table.deleteConfirm')} ${table.table_number}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/tables/${table.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('table.deleteSuccess'), life: 3000 })
        fetchTables()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to delete table', life: 3000 })
      }
    }
  })
}

const confirmCleanup = (table) => {
  confirm.require({
    message: `${t('table.cleanupConfirm')} ${table.table_number}?`,
    header: t('table.cleanup'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.post(`/outlets/${outletId}/tables/${table.id}/cleanup`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('table.cleanupSuccess'), life: 3000 })
        fetchTables()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to cleanup table', life: 3000 })
      }
    }
  })
}

const getAreaLabel = (area) => {
  const labels = { indoor: t('table.indoor'), outdoor: t('table.outdoor'), vip: t('table.vip') }
  return labels[area] || area
}

const getAreaSeverity = (area) => {
  const severities = { indoor: 'info', outdoor: 'success', vip: 'warn' }
  return severities[area] || 'info'
}

const getStatusLabel = (status) => {
  const labels = { available: t('table.available'), occupied: t('table.occupied'), reserved: t('table.reserved') }
  return labels[status] || status
}

const getStatusSeverity = (status) => {
  const severities = { available: 'success', occupied: 'danger', reserved: 'warn' }
  return severities[status] || 'info'
}

// QR code state
const qrDialogVisible = ref(false)
const qrTable = ref(null)
const qrImageData = ref('')
const regenerating = ref(false)
const outletSlug = ref('')

const qrPublicUrl = computed(() => {
  if (!qrTable.value || !outletSlug.value) return ''
  const origin = window.location.origin
  return `${origin}/o/${outletSlug.value}/t/${qrTable.value.qr_token}`
})

async function fetchOutletSlug () {
  try {
    const res = await api.get(`/outlets/${outletId}`)
    outletSlug.value = res.data?.slug || res.data?.data?.slug || ''
  } catch (e) {
    // ignore
  }
}

async function generateQrImage () {
  qrImageData.value = ''
  if (!qrPublicUrl.value) return
  try {
    qrImageData.value = await QRCode.toDataURL(qrPublicUrl.value, {
      errorCorrectionLevel: 'M',
      margin: 2,
      width: 480,
    })
  } catch (e) {
    qrImageData.value = ''
  }
}

watch(qrPublicUrl, () => { generateQrImage() })

function openQrDialog (table) {
  qrTable.value = table
  qrDialogVisible.value = true
  generateQrImage()
}

async function copyQrLink () {
  if (!qrPublicUrl.value) return
  try {
    await navigator.clipboard.writeText(qrPublicUrl.value)
    toast.add({ severity: 'success', summary: '', detail: t('tableQr.linkCopied'), life: 2500 })
  } catch (e) {
    // fallback
    const ta = document.createElement('textarea')
    ta.value = qrPublicUrl.value
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    toast.add({ severity: 'success', summary: '', detail: t('tableQr.linkCopied'), life: 2500 })
  }
}

function downloadQr () {
  if (!qrImageData.value || !qrTable.value) return
  const a = document.createElement('a')
  a.href = qrImageData.value
  a.download = `table-${qrTable.value.table_number || 'qr'}.png`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

async function regenerateToken (table) {
  if (!table) return
  if (!window.confirm(t('tableQr.regenerateConfirm'))) return
  regenerating.value = true
  try {
    const res = await api.post(`/outlets/${outletId}/tables/${table.id}/regenerate-token`)
    const fresh = res.data?.data || res.data
    if (fresh) {
      qrTable.value = fresh
      // replace in list
      const idx = tables.value.findIndex((x) => x.id === fresh.id)
      if (idx !== -1) tables.value[idx] = fresh
    }
    await generateQrImage()
    toast.add({ severity: 'success', summary: t('messages.success'), detail: 'OK', life: 2500 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: e.response?.data?.message || 'Failed',
      life: 3000,
    })
  } finally {
    regenerating.value = false
  }
}

onMounted(() => {
  fetchTables()
  fetchOutletSlug()
})
</script>

<style scoped>
.table-management {
  padding: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.page-header h2 {
  margin: 0;
  font-size: 1.5rem;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

.form-grid {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label {
  font-weight: 600;
  color: #374151;
}
</style>
