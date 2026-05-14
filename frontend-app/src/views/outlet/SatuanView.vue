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
          <span>{{ $t('bahanBaku.units') }}</span>
          <Button :label="$t('bahanBaku.addUnit')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredUnits" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>
          <template #header>
            <div class="table-header">
              <IconField>
                <InputIcon><i class="pi pi-search" /></InputIcon>
                <InputText v-model="searchQuery" :placeholder="$t('common.search')" />
              </IconField>
            </div>
          </template>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-calculator" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="nama" :header="$t('bahanBaku.unitName')" sortable />
          <Column field="singkatan" :header="$t('bahanBaku.unitSymbol')" sortable style="width: 100px" />
          <Column field="tipe" :header="$t('bahanBaku.type')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.tipe" :severity="getTypeSeverity(data.tipe)" />
            </template>
          </Column>
          <Column field="is_base_unit" :header="$t('bahanBaku.baseUnit')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.is_base_unit ? $t('common.yes') : $t('common.no')"
                   :severity="data.is_base_unit ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column field="conversion_to_base" :header="$t('bahanBaku.conversionFactor')" sortable style="width: 150px" />
          <Column field="is_active" :header="$t('bahanBaku.unitActive')" sortable style="width: 100px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.yes') : $t('common.no')"
                   :severity="data.is_active ? 'success' : 'danger'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 100px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-pencil" text rounded severity="info" @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('bahanBaku.editUnit') : $t('bahanBaku.addUnit')"
            :modal="true" :style="{ width: '450px' }">
      <div class="dialog-content">
        <div class="field">
          <label for="nama">{{ $t('bahanBaku.unitName') }}</label>
          <InputText id="nama" v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label for="singkatan">{{ $t('bahanBaku.unitSymbol') }}</label>
          <InputText id="singkatan" v-model="form.singkatan" style="width: 100%" />
        </div>
        <div class="field">
          <label for="tipe">{{ $t('bahanBaku.type') }}</label>
          <Select id="tipe" v-model="form.tipe" :options="types" optionLabel="label" optionValue="value" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_base_unit" :binary="true" inputId="is_base_unit" />
            <label for="is_base_unit">{{ $t('bahanBaku.baseUnit') }}</label>
          </div>
        </div>
        <div v-if="!form.is_base_unit" class="field">
          <label for="conversion">{{ $t('bahanBaku.conversionFactor') }}</label>
          <InputNumber id="conversion" v-model="form.conversion_to_base" style="width: 100%"
                       :minFractionDigits="2" :maxFractionDigits="4" />
        </div>
        <div class="field">
          <label for="deskripsi">{{ $t('bahanBaku.description') }}</label>
          <Textarea id="deskripsi" v-model="form.deskripsi" rows="2" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('bahanBaku.unitActive') }}</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveUnit" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
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
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const outlet = ref(null)
const units = ref([])
const searchQuery = ref('')
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)

const isProcessing = computed(() => saving.value || deleting.value)

const types = [
  { label: 'Weight', value: 'weight' },
  { label: 'Volume', value: 'volume' },
  { label: 'Count', value: 'count' }
]

const form = ref({
  nama: '', singkatan: '', tipe: 'weight',
  is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true
})

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.units') }
])

const filteredUnits = computed(() => {
  if (!searchQuery.value) return units.value
  const q = searchQuery.value.toLowerCase()
  return units.value.filter(u =>
    u.nama?.toLowerCase().includes(q) || u.singkatan?.toLowerCase().includes(q)
  )
})

const getTypeSeverity = (type) => ({ weight: 'info', volume: 'success', count: 'warning' }[type] || 'secondary')

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const fetchUnits = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/satuan`)
    units.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: t('messages.error'), life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (unit = null) => {
  isEdit.value = !!unit
  form.value = unit ? { ...unit } : { nama: '', singkatan: '', tipe: 'weight', is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true }
  dialogVisible.value = true
}

const saveUnit = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/satuan/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/satuan`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchUnits()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (unit) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: unit.nama }),
    header: t('bahanBaku.deleteUnit'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteUnit(unit.id)
  })
}

const deleteUnit = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/satuan/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchUnits()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchOutlet()
  fetchUnits()
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

.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }

.table-header { display: flex; justify-content: flex-end; margin-bottom: 1rem; }

.action-buttons { display: flex; gap: 0.25rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 3rem; color: #6b7280;
}

.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }

.field { display: flex; flex-direction: column; gap: 0.5rem; }

.field label { font-weight: 600; color: #374151; }
</style>
