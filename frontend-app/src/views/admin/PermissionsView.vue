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
        <p class="loading-text">{{ loadingMessage }}</p>
      </div>
    </div>

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('menu.permissionManagement') }}</span>
          <Button 
            v-if="can('permissions.create')"
            :label="$t('permission.addPermission')" 
            icon="pi pi-plus" 
            @click="openCreateDialog"
            :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          />
        </div>
      </template>
      <template #content>
        <!-- Group Filter -->
        <div class="filter-section">
          <div class="filter-group">
            <label>{{ $t('permission.filterByGroup') }}:</label>
            <div class="group-chips">
              <Chip 
                :label="$t('permission.all')" 
                :class="{ 'chip-active': selectedGroup === null }"
                @click="selectedGroup = null"
                class="group-chip"
              />
              <Chip 
                v-for="group in permissionGroups" 
                :key="group"
                :label="group"
                :class="{ 'chip-active': selectedGroup === group }"
                @click="selectedGroup = group"
                class="group-chip"
              />
            </div>
          </div>
        </div>

        <DataTable 
          :value="filteredPermissions" 
          :loading="loading"
          paginator 
          :rows="15"
          :rowsPerPageOptions="[10, 15, 25, 50]"
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
                  @input="filterPermissions"
                />
              </IconField>
            </div>
          </template>
          
          <Column field="id" header="ID" sortable style="width: 80px"></Column>
          <Column field="display_name" :header="$t('permission.permissionName')" sortable></Column>
          <Column field="name" :header="$t('permission.permissionKey')" sortable>
            <template #body="{ data }">
              <code class="permission-code">{{ data.name }}</code>
            </template>
          </Column>
          <Column field="group" :header="$t('permission.permissionGroup')" sortable style="width: 150px">
            <template #body="{ data }">
              <Tag 
                :value="data.group || 'other'"
                :severity="getGroupSeverity(data.group)"
                class="group-tag"
              />
            </template>
          </Column>
          <Column field="description" :header="$t('permission.permissionDescription')"></Column>
          <Column :header="$t('permission.usedBy')" style="width: 120px">
            <template #body="{ data }">
              <Tag 
                :value="`${data.roles?.length || 0} ${$t('role.roles').toLowerCase()}`"
                severity="info"
              />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button 
                  icon="pi pi-pencil" 
                  text 
                  rounded
                  severity="info"
                  @click="openEditDialog(data)"
                  :v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  icon="pi pi-trash" 
                  text 
                  rounded
                  severity="danger"
                  @click="confirmDelete(data)"
                  :v-tooltip.top="$t('common.delete')"
                />
              </div>
            </template>
          </Column>
          
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-lock" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('permission.addPermission') : $t('permission.editPermission')"
      :modal="true"
      :style="{ width: '600px' }"
    >
      <div class="dialog-content">
        <div class="field">
          <label for="display_name">{{ $t('permission.permissionName') }}</label>
          <InputText 
            id="display_name"
            v-model="formData.display_name" 
            :class="{ 'p-invalid': errors.display_name }"
            style="width: 100%"
            placeholder="e.g., Create Users"
          />
          <small v-if="errors.display_name" class="p-error">{{ errors.display_name }}</small>
        </div>

        <div class="field">
          <label for="name">{{ $t('permission.permissionKey') }}</label>
          <InputText 
            id="name"
            v-model="formData.name" 
            :class="{ 'p-invalid': errors.name }"
            style="width: 100%"
            placeholder="e.g., users.create"
            :disabled="dialogMode === 'edit'"
          />
          <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          <small v-else class="field-hint">Format: group.action (e.g., users.create, roles.edit). Cannot be changed after creation.</small>
        </div>

        <div class="field">
          <label for="group">{{ $t('permission.permissionGroup') }}</label>
          <Dropdown 
            id="group"
            v-model="formData.group" 
            :options="permissionGroups"
            placeholder="Select a group"
            :class="{ 'p-invalid': errors.group }"
            style="width: 100%"
            editable
          />
          <small v-if="errors.group" class="p-error">{{ errors.group }}</small>
          <small v-else class="field-hint">Group permissions by category (e.g., users, roles, dashboard)</small>
        </div>

        <div class="field">
          <label for="description">{{ $t('permission.permissionDescription') }}</label>
          <Textarea 
            id="description"
            v-model="formData.description" 
            rows="3"
            style="width: 100%"
            placeholder="Describe what this permission allows..."
          />
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
          @click="savePermission"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialogVisible" 
      :header="$t('permission.deletePermission')"
      :modal="true"
      :style="{ width: '450px' }"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
        <p>{{ $t('messages.confirmDelete', { item: permissionToDelete?.display_name }) }}</p>
        <p class="warning-text">This permission is used by <strong>{{ permissionToDelete?.roles?.length || 0 }} role(s)</strong>.</p>
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
          @click="deletePermission"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
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
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Chip from 'primevue/chip'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'

const toast = useToast()
const { can } = usePermission()
const { t } = useI18n()

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.permissionManagement') }
])

// Data
const permissions = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const dialogMode = ref('create')
const permissionToDelete = ref(null)
const searchQuery = ref('')
const selectedGroup = ref(null)

// Computed
const isProcessing = computed(() => {
  return saving.value || deleting.value
})

const loadingMessage = computed(() => {
  return t('common.loading')
})

const formData = ref({
  id: null,
  name: '',
  display_name: '',
  group: '',
  description: ''
})

const errors = ref({})

// Computed
const permissionGroups = computed(() => {
  const groups = [...new Set(permissions.value.map(p => p.group).filter(Boolean))]
  return groups.sort()
})

const filteredPermissions = computed(() => {
  let filtered = permissions.value

  // Filter by group
  if (selectedGroup.value) {
    filtered = filtered.filter(p => p.group === selectedGroup.value)
  }

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(query) ||
      p.display_name.toLowerCase().includes(query) ||
      (p.description && p.description.toLowerCase().includes(query)) ||
      (p.group && p.group.toLowerCase().includes(query))
    )
  }

  return filtered
})

// Methods
const filterPermissions = () => {
  // Trigger reactivity
}

const fetchPermissions = async () => {
  loading.value = true
  try {
    const response = await api.get('/admin/permissions')
    permissions.value = response.data.permissions || []
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch permissions',
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
    display_name: '',
    group: '',
    description: ''
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (permission) => {
  dialogMode.value = 'edit'
  formData.value = {
    id: permission.id,
    name: permission.name,
    display_name: permission.display_name,
    group: permission.group || '',
    description: permission.description || ''
  }
  errors.value = {}
  dialogVisible.value = true
}

const validateForm = () => {
  errors.value = {}
  
  if (!formData.value.display_name) {
    errors.value.display_name = 'Display name is required'
  }
  
  if (!formData.value.name) {
    errors.value.name = 'Permission key is required'
  } else if (!/^[a-z0-9._-]+$/.test(formData.value.name)) {
    errors.value.name = 'Permission key must be lowercase letters, numbers, dots, hyphens, or underscores only'
  }
  
  if (!formData.value.group) {
    errors.value.group = 'Group is required'
  }
  
  return Object.keys(errors.value).length === 0
}

const savePermission = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.post('/admin/permissions', formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/admin/permissions/${formData.value.id}`, formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.updatedSuccessfully'),
        life: 3000
      })
    }
    
    dialogVisible.value = false
    await fetchPermissions()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to save permission'
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

const confirmDelete = (permission) => {
  permissionToDelete.value = permission
  deleteDialogVisible.value = true
}

const deletePermission = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/permissions/${permissionToDelete.value.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.deletedSuccessfully'),
      life: 3000
    })
    
    deleteDialogVisible.value = false
    await fetchPermissions()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to delete permission'
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

const getGroupSeverity = (group) => {
  const severityMap = {
    'users': 'info',
    'roles': 'warning',
    'permissions': 'danger',
    'menus': 'success',
    'dashboard': 'secondary',
    'reports': 'info',
    'settings': 'warning'
  }
  return severityMap[group] || 'secondary'
}

// Lifecycle
onMounted(() => {
  fetchPermissions()
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

.filter-section {
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.filter-group label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.75rem;
}

.group-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.group-chip {
  cursor: pointer;
  transition: all 0.2s;
  text-transform: capitalize;
}

.group-chip:hover {
  background: var(--sage-bg);
}

.chip-active {
  background: var(--sage-primary) !important;
  color: white !important;
}

.table-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

.permission-code {
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  color: #1f2937;
}

.group-tag {
  text-transform: capitalize;
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

.field-hint {
  color: #6b7280;
  font-size: 0.875rem;
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

/* PrimeVue Chip override */
:deep(.p-chip) {
  background: #e5e7eb;
  color: #374151;
  padding: 0.5rem 1rem;
}

:deep(.p-chip:hover) {
  background: #d1d5db;
}
</style>
