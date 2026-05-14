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
          <span>{{ $t('bahanBaku.suppliers') }}</span>
          <Button :label="$t('bahanBaku.addSupplier')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredSuppliers" :loading="loading" paginator :rows="10"
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
              <i class="pi pi-building" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="kode" :header="$t('bahanBaku.supplierCode')" sortable style="width: 120px" />
          <Column field="nama" :header="$t('bahanBaku.supplierName')" sortable />
          <Column field="contact_person" :header="$t('bahanBaku.contactPerson')" />
          <Column field="phone" :header="$t('bahanBaku.phone')" style="width: 150px" />
          <Column field="email" :header="$t('bahanBaku.email')" />
          <Column field="is_active" :header="$t('bahanBaku.supplierActive')" sortable style="width: 100px">
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
            :header="isEdit ? $t('bahanBaku.editSupplier') : $t('bahanBaku.addSupplier')"
            :modal="true" :style="{ width: '550px' }">
      <div class="dialog-content">
        <div class="field">
          <label>{{ $t('bahanBaku.supplierName') }}</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.contactPerson') }}</label>
          <InputText v-model="form.contact_person" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.phone') }}</label>
          <InputText v-model="form.phone" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.email') }}</label>
          <InputText v-model="form.email" type="email" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.address') }}</label>
          <Textarea v-model="form.alamat" rows="2" style="width: 100%" />
        </div>
        <div class="field">
          <label>Kota</label>
          <InputText v-model="form.kota" style="width: 100%" />
        </div>
        <div class="field">
          <label>Provinsi</label>
          <InputText v-model="form.provinsi" style="width: 100%" />
        </div>
        <div class="field">
          <label>Kode Pos</label>
          <InputText v-model="form.kode_pos" style="width: 100%" />
        </div>
        <div class="field">
          <label>Payment Terms</label>
          <InputText v-model="form.payment_terms" placeholder="e.g., Net 30" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('bahanBaku.notes') }}</label>
          <Textarea v-model="form.notes" rows="2" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('bahanBaku.supplierActive') }}</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveSupplier" :loading="saving"
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
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const outlet = ref(null)
const suppliers = ref([])
const searchQuery = ref('')
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)

const isProcessing = computed(() => saving.value || deleting.value)

const form = ref({
  nama: '', contact_person: '', phone: '', email: '',
  alamat: '', kota: '', provinsi: '', kode_pos: '',
  payment_terms: '', notes: '', is_active: true
})

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.suppliers') }
])

const filteredSuppliers = computed(() => {
  if (!searchQuery.value) return suppliers.value
  const q = searchQuery.value.toLowerCase()
  return suppliers.value.filter(s =>
    s.nama?.toLowerCase().includes(q) ||
    s.contact_person?.toLowerCase().includes(q) ||
    s.phone?.toLowerCase().includes(q) ||
    s.email?.toLowerCase().includes(q)
  )
})

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const fetchSuppliers = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/supplier`)
    suppliers.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: t('messages.error'), life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (supplier = null) => {
  isEdit.value = !!supplier
  form.value = supplier ? { ...supplier } : { nama: '', contact_person: '', phone: '', email: '', alamat: '', kota: '', provinsi: '', kode_pos: '', payment_terms: '', notes: '', is_active: true }
  dialogVisible.value = true
}

const saveSupplier = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/supplier/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/supplier`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchSuppliers()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (supplier) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: supplier.nama }),
    header: t('bahanBaku.deleteSupplier'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteSupplier(supplier.id)
  })
}

const deleteSupplier = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/supplier/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchSuppliers()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchOutlet()
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
