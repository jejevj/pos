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
      <Column :header="$t('common.actions')" style="width: 200px">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" 
                  v-tooltip.top="$t('common.delete')" />
          <Button v-if="data.status === 'occupied'" icon="pi pi-broom" text rounded severity="warning" 
                  @click="confirmCleanup(data)" v-tooltip.top="$t('table.cleanup')" />
        </template>
      </Column>
    </DataTable>

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
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
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

onMounted(() => {
  fetchTables()
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
