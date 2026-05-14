<template>
  <div class="view-container">
    <!-- Breadcrumb -->
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

    <!-- Loading Overlay -->
    <div v-if="isProcessing" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner 
          style="width: 60px; height: 60px" 
          strokeWidth="4"
          animationDuration="1s"
        />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('menu.outletManagement') }}</span>
          <Button 
            v-if="can('outlets.create')"
            :label="$t('outlet.addOutlet')" 
            icon="pi pi-plus" 
            @click="openCreateDialog"
            :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          />
        </div>
      </template>
      <template #content>
        <DataTable 
          :value="outlets" 
          :loading="loading"
          paginator 
          :rows="10"
          :rowsPerPageOptions="[10, 25, 50]"
          stripedRows
          showGridlines
        >
          <template #header>
            <div class="table-header">
              <IconField>
                <InputIcon>
                  <i class="pi pi-search" />
                </InputIcon>
                <InputText 
                  v-model="searchQuery" 
                  :placeholder="$t('common.search')" 
                />
              </IconField>
            </div>
          </template>
          
          <Column field="id" header="ID" sortable style="width: 80px"></Column>
          <Column field="name" :header="$t('outlet.outletName')" sortable></Column>
          <Column field="slug" :header="$t('outlet.outletSlug')" sortable>
            <template #body="{ data }">
              <code class="code-text">{{ data.slug }}</code>
            </template>
          </Column>
          <Column field="address" :header="$t('outlet.outletAddress')"></Column>
          <Column field="phone" :header="$t('outlet.outletPhone')"></Column>
          <Column field="owner.name" :header="$t('outlet.outletOwner')" sortable></Column>
          <Column field="is_active" :header="$t('outlet.outletActive')" style="width: 120px">
            <template #body="{ data }">
              <Tag 
                :value="data.is_active ? 'Active' : 'Inactive'" 
                :severity="data.is_active ? 'success' : 'danger'" 
              />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 250px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button 
                  icon="pi pi-box" 
                  text 
                  rounded
                  severity="secondary"
                  @click="router.push({ name: 'outlet-dashboard', params: { outletId: data.id } })"
                  v-tooltip.top="$t('bahanBaku.title')"
                />
                <Button 
                  icon="pi pi-users" 
                  text 
                  rounded
                  severity="success"
                  @click="openUsersDialog(data)"
                  v-tooltip.top="$t('outlet.manageUsers')"
                />
                <Button 
                  v-if="can('outlets.edit')"
                  icon="pi pi-pencil" 
                  text 
                  rounded
                  severity="info"
                  @click="openEditDialog(data)"
                  v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  v-if="can('outlets.delete')"
                  icon="pi pi-trash" 
                  text 
                  rounded
                  severity="danger"
                  @click="confirmDelete(data)"
                  v-tooltip.top="$t('common.delete')"
                />
              </div>
            </template>
          </Column>
          
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-building" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('outlet.addOutlet') : $t('outlet.editOutlet')"
      :modal="true"
      :style="{ width: '700px' }"
    >
      <div class="dialog-content">
        <div class="form-row">
          <div class="field">
            <label for="name">{{ $t('outlet.outletName') }} *</label>
            <InputText 
              id="name"
              v-model="formData.name" 
              :class="{ 'p-invalid': errors.name }"
              :placeholder="$t('outlet.outletName')"
            />
            <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          </div>

          <div class="field">
            <label for="slug">{{ $t('outlet.outletSlug') }}</label>
            <InputText 
              id="slug"
              v-model="formData.slug" 
              :class="{ 'p-invalid': errors.slug }"
              :placeholder="$t('outlet.outletSlug')"
            />
            <small v-if="errors.slug" class="p-error">{{ errors.slug }}</small>
            <small v-else class="field-hint">Leave empty to auto-generate from name</small>
          </div>
        </div>

        <div class="field">
          <label for="description">{{ $t('outlet.outletDescription') }}</label>
          <Textarea 
            id="description"
            v-model="formData.description" 
            rows="3"
            :placeholder="$t('outlet.outletDescription')"
          />
        </div>

        <div class="field">
          <label for="address">{{ $t('outlet.outletAddress') }}</label>
          <Textarea 
            id="address"
            v-model="formData.address" 
            rows="2"
            :placeholder="$t('outlet.outletAddress')"
          />
        </div>

        <div class="form-row">
          <div class="field">
            <label for="phone">{{ $t('outlet.outletPhone') }}</label>
            <InputText 
              id="phone"
              v-model="formData.phone" 
              :placeholder="$t('outlet.outletPhone')"
            />
          </div>

          <div class="field">
            <label for="email">{{ $t('outlet.outletEmail') }}</label>
            <InputText 
              id="email"
              v-model="formData.email" 
              type="email"
              :placeholder="$t('outlet.outletEmail')"
            />
          </div>
        </div>

        <div class="field">
          <label for="fixed_cost">{{ $t('outlet.fixedCost') }}</label>
          <div class="fixed-cost-group">
            <Select 
              v-model="formData.fixed_cost_type" 
              :options="fixedCostTypes" 
              optionLabel="label" 
              optionValue="value"
              style="width: 150px"
            />
            <InputNumber 
              v-if="formData.fixed_cost_type === 'percentage'"
              id="fixed_cost_percentage"
              v-model="formData.fixed_cost_percentage" 
              :min="0"
              :max="100"
              suffix="%"
              :minFractionDigits="0"
              :maxFractionDigits="2"
              :placeholder="$t('outlet.fixedCostPercentage')"
              style="flex: 1"
            />
            <InputNumber 
              v-else
              id="fixed_cost_nominal"
              v-model="formData.fixed_cost_nominal" 
              :min="0"
              mode="currency"
              currency="IDR"
              locale="id-ID"
              :placeholder="$t('outlet.fixedCostNominal')"
              style="flex: 1"
            />
          </div>
          <small class="text-muted">{{ $t('outlet.fixedCostHint') }}</small>
        </div>

        <div class="field">
          <div class="checkbox-field">
            <Checkbox 
              v-model="formData.is_active" 
              inputId="is_active" 
              :binary="true" 
            />
            <label for="is_active" class="checkbox-label">{{ $t('outlet.outletActive') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          :label="$t('common.cancel')" 
          text 
          @click="dialogVisible = false"
        />
        <Button 
          :label="dialogMode === 'create' ? $t('common.create') : $t('common.update')" 
          :loading="saving"
          @click="saveOutlet"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialogVisible" 
      :header="$t('outlet.deleteOutlet')"
      :modal="true"
      :style="{ width: '450px' }"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
        <p>{{ $t('messages.confirmDelete', { item: outletToDelete?.name }) }}</p>
        <p class="warning-text">{{ $t('messages.cannotUndo') }}</p>
      </div>

      <template #footer>
        <Button 
          :label="$t('common.cancel')" 
          text 
          @click="deleteDialogVisible = false"
        />
        <Button 
          :label="$t('common.delete')" 
          severity="danger"
          :loading="deleting"
          @click="deleteOutlet"
        />
      </template>
    </Dialog>

    <!-- Outlet Users Dialog -->
    <Dialog 
      v-model:visible="usersDialogVisible" 
      :header="$t('outlet.outletUsers') + ' - ' + selectedOutlet?.name"
      :modal="true"
      :style="{ width: '900px', maxHeight: '80vh' }"
    >
      <OutletUsersManager 
        v-if="selectedOutlet"
        :outlet="selectedOutlet"
        @close="usersDialogVisible = false"
      />
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { usePermission } from '@/composables/usePermission'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import ProgressSpinner from 'primevue/progressspinner'
import OutletUsersManager from '@/components/OutletUsersManager.vue'

const router = useRouter()
const toast = useToast()
const { can } = usePermission()
const { t } = useI18n()

// Fixed cost types
const fixedCostTypes = ref([
  { label: t('outlet.percentage'), value: 'percentage' },
  { label: t('outlet.nominal'), value: 'nominal' }
])

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement') }
])

// Data
const outlets = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const usersDialogVisible = ref(false)
const dialogMode = ref('create')
const outletToDelete = ref(null)
const selectedOutlet = ref(null)
const searchQuery = ref('')

const isProcessing = computed(() => saving.value || deleting.value)

const formData = ref({
  id: null,
  name: '',
  slug: '',
  description: '',
  address: '',
  phone: '',
  email: '',
  fixed_cost_type: 'percentage',
  fixed_cost_percentage: 0,
  fixed_cost_nominal: 0,
  is_active: true
})

const errors = ref({})

// Methods
const fetchOutlets = async () => {
  loading.value = true
  try {
    const response = await api.get('/outlets')
    outlets.value = response.data
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch outlets',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  formData.value = {
    id: null,
    name: '',
    slug: '',
    description: '',
    address: '',
    phone: '',
    email: '',
    fixed_cost_type: 'percentage',
    fixed_cost_percentage: 0,
    fixed_cost_nominal: 0,
    is_active: true
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (outlet) => {
  dialogMode.value = 'edit'
  formData.value = {
    id: outlet.id,
    name: outlet.name,
    slug: outlet.slug,
    description: outlet.description || '',
    address: outlet.address || '',
    phone: outlet.phone || '',
    email: outlet.email || '',
    fixed_cost_type: outlet.fixed_cost_type || 'percentage',
    fixed_cost_percentage: outlet.fixed_cost_percentage || 0,
    fixed_cost_nominal: outlet.fixed_cost_nominal || 0,
    is_active: outlet.is_active
  }
  errors.value = {}
  dialogVisible.value = true
}

const openUsersDialog = (outlet) => {
  selectedOutlet.value = outlet
  usersDialogVisible.value = true
}

const validateForm = () => {
  errors.value = {}
  
  if (!formData.value.name) {
    errors.value.name = 'Name is required'
  }
  
  return Object.keys(errors.value).length === 0
}

const saveOutlet = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.post('/outlets', formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/outlets/${formData.value.id}`, formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.updatedSuccessfully'),
        life: 3000
      })
    }
    
    dialogVisible.value = false
    await fetchOutlets()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to save outlet'
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: message,
      life: 3000
    })
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (outlet) => {
  outletToDelete.value = outlet
  deleteDialogVisible.value = true
}

const deleteOutlet = async () => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletToDelete.value.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.deletedSuccessfully'),
      life: 3000
    })
    
    deleteDialogVisible.value = false
    await fetchOutlets()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to delete outlet'
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: message,
      life: 3000
    })
  } finally {
    deleting.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchOutlets()
})
</script>

<style scoped>
.view-container {
  max-width: 1400px;
  margin: 0 auto;
}

.mb-4 {
  margin-bottom: 1rem;
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

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.table-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

.code-text {
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  color: #1f2937;
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

.field label {
  font-weight: 600;
  color: #374151;
}

.field-hint {
  color: #6b7280;
  font-size: 0.875rem;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox-label {
  cursor: pointer;
  font-weight: 600;
  color: #374151;
}

.confirmation-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  text-align: center;
}

.warning-text {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  display: block;
  margin-top: 0.25rem;
}

.fixed-cost-group {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
</style>
