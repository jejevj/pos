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
          <span>{{ $t('menu.roleManagement') }}</span>
          <Button 
            v-if="can('roles.create')"
            :label="$t('role.addRole')" 
            icon="pi pi-plus" 
            @click="openCreateDialog"
            :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          />
        </div>
      </template>
      <template #content>
        <DataTable 
          :value="filteredRoles" 
          :loading="loading"
          paginator 
          :rows="10"
          :rowsPerPageOptions="[5, 10, 20, 50]"
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
                  @input="filterRoles"
                />
              </IconField>
            </div>
          </template>
          
          <Column field="id" header="ID" sortable style="width: 80px"></Column>
          <Column field="display_name" :header="$t('role.roleName')" sortable></Column>
          <Column field="name" :header="$t('role.roleSlug')" sortable></Column>
          <Column field="description" :header="$t('role.roleDescription')"></Column>
          <Column :header="$t('role.permissions')" style="width: 150px">
            <template #body="{ data }">
              <Tag 
                :value="`${data.permissions?.length || 0} ${$t('role.permissions').toLowerCase()}`"
                severity="info"
              />
            </template>
          </Column>
          <Column header="Status" style="width: 100px">
            <template #body="{ data }">
              <Tag 
                :value="data.is_active ? 'Active' : 'Inactive'"
                :severity="data.is_active ? 'success' : 'danger'"
              />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 200px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button 
                  icon="pi pi-key" 
                  text 
                  rounded
                  severity="success"
                  @click="openPermissionsDialog(data)"
                  :v-tooltip.top="$t('role.managePermissions')"
                />
                <Button 
                  icon="pi pi-pencil" 
                  text 
                  rounded
                  severity="info"
                  @click="openEditDialog(data)"
                  :v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  v-if="data.name !== 'superadmin'"
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
              <i class="pi pi-shield" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('role.addRole') : $t('role.editRole')"
      :modal="true"
      :style="{ width: '600px' }"
    >
      <div class="dialog-content">
        <div class="field">
          <label for="display_name">{{ $t('role.roleName') }}</label>
          <InputText 
            id="display_name"
            v-model="formData.display_name" 
            :class="{ 'p-invalid': errors.display_name }"
            style="width: 100%"
            placeholder="e.g., Administrator"
          />
          <small v-if="errors.display_name" class="p-error">{{ errors.display_name }}</small>
        </div>

        <div class="field">
          <label for="name">{{ $t('role.roleSlug') }}</label>
          <InputText 
            id="name"
            v-model="formData.name" 
            :class="{ 'p-invalid': errors.name }"
            style="width: 100%"
            placeholder="e.g., admin"
            :disabled="dialogMode === 'edit'"
          />
          <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          <small v-else class="field-hint">Lowercase, no spaces. Cannot be changed after creation.</small>
        </div>

        <div class="field">
          <label for="description">{{ $t('role.roleDescription') }}</label>
          <Textarea 
            id="description"
            v-model="formData.description" 
            rows="3"
            style="width: 100%"
            placeholder="Describe the role's purpose..."
          />
        </div>

        <div class="field">
          <div class="flex items-center gap-2">
            <Checkbox 
              v-model="formData.is_active" 
              inputId="is_active" 
              :binary="true" 
            />
            <label for="is_active" class="checkbox-label">Active</label>
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
          @click="saveRole"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Permissions Dialog -->
    <Dialog 
      v-model:visible="permissionsDialogVisible" 
      :header="$t('role.managePermissions')"
      :modal="true"
      :style="{ width: '700px', maxHeight: '80vh' }"
    >
      <div class="permissions-content">
        <div class="permissions-header">
          <h3>{{ selectedRole?.display_name }}</h3>
          <p class="text-muted">Select permissions for this role</p>
        </div>

        <div class="permissions-search">
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText 
              v-model="permissionSearch" 
              :placeholder="$t('common.search')" 
              style="width: 100%"
            />
          </IconField>
        </div>

        <div class="permissions-list">
          <div v-for="group in groupedPermissions" :key="group.name" class="permission-group">
            <div class="group-header">
              <Checkbox 
                :modelValue="isGroupSelected(group.permissions)"
                @update:modelValue="toggleGroup(group.permissions, $event)"
                :binary="true"
              />
              <span class="group-name">{{ group.name }}</span>
              <span class="group-count">({{ group.permissions.length }})</span>
            </div>
            <div class="group-permissions">
              <div 
                v-for="permission in group.permissions" 
                :key="permission.id"
                class="permission-item"
              >
                <Checkbox 
                  v-model="selectedPermissions" 
                  :inputId="`perm-${permission.id}`"
                  :value="permission.id"
                />
                <label :for="`perm-${permission.id}`" class="permission-label">
                  {{ permission.display_name }}
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          :label="$t('common.cancel')" 
          text 
          @click="permissionsDialogVisible = false"
        />
        <Button 
          :label="$t('common.save')" 
          :loading="savingPermissions"
          @click="savePermissions"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialogVisible" 
      :header="$t('role.deleteRole')"
      :modal="true"
      :style="{ width: '400px' }"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
        <p>{{ $t('messages.confirmDelete', { item: roleToDelete?.display_name }) }}</p>
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
          @click="deleteRole"
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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import ProgressSpinner from 'primevue/progressspinner'

const toast = useToast()
const { can } = usePermission()
const { t } = useI18n()

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.roleManagement') }
])

// Data
const roles = ref([])
const permissions = ref([])
const loading = ref(false)
const saving = ref(false)
const savingPermissions = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const permissionsDialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const dialogMode = ref('create')
const roleToDelete = ref(null)
const selectedRole = ref(null)
const selectedPermissions = ref([])
const searchQuery = ref('')
const permissionSearch = ref('')

// Computed
const isProcessing = computed(() => {
  return saving.value || deleting.value || savingPermissions.value
})

const loadingMessage = computed(() => {
  return t('common.loading')
})

const formData = ref({
  id: null,
  name: '',
  display_name: '',
  description: '',
  is_active: true
})

const errors = ref({})

// Computed
const filteredRoles = computed(() => {
  if (!searchQuery.value) return roles.value
  
  const query = searchQuery.value.toLowerCase()
  return roles.value.filter(role => 
    role.name.toLowerCase().includes(query) ||
    role.display_name.toLowerCase().includes(query) ||
    (role.description && role.description.toLowerCase().includes(query))
  )
})

const groupedPermissions = computed(() => {
  // Ensure permissions is an array
  if (!Array.isArray(permissions.value)) {
    console.warn('Permissions is not an array:', permissions.value)
    return []
  }

  const filtered = permissionSearch.value
    ? permissions.value.filter(p => 
        p.name.toLowerCase().includes(permissionSearch.value.toLowerCase()) ||
        p.display_name.toLowerCase().includes(permissionSearch.value.toLowerCase())
      )
    : permissions.value

  const groups = {}
  filtered.forEach(permission => {
    const group = permission.group || 'other'
    if (!groups[group]) {
      groups[group] = []
    }
    groups[group].push(permission)
  })

  return Object.keys(groups).map(key => ({
    name: key.charAt(0).toUpperCase() + key.slice(1),
    permissions: groups[key]
  }))
})

// Methods
const filterRoles = () => {
  // Trigger reactivity
}

const fetchRoles = async () => {
  loading.value = true
  try {
    const response = await api.get('/admin/roles')
    roles.value = response.data
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch roles',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const response = await api.get('/admin/permissions')
    console.log('Permissions response:', response.data)
    // Backend returns { permissions: [...], grouped: {...} }
    permissions.value = response.data.permissions || []
    console.log('Permissions loaded:', permissions.value)
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch permissions',
      life: 3000
    })
  }
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  formData.value = {
    id: null,
    name: '',
    display_name: '',
    description: '',
    is_active: true
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (role) => {
  dialogMode.value = 'edit'
  formData.value = {
    id: role.id,
    name: role.name,
    display_name: role.display_name,
    description: role.description || '',
    is_active: role.is_active
  }
  errors.value = {}
  dialogVisible.value = true
}

const openPermissionsDialog = (role) => {
  console.log('Opening permissions dialog for role:', role)
  console.log('Available permissions:', permissions.value)
  selectedRole.value = role
  selectedPermissions.value = role.permissions?.map(p => p.id) || []
  console.log('Selected permissions:', selectedPermissions.value)
  permissionSearch.value = ''
  permissionsDialogVisible.value = true
}

const validateForm = () => {
  errors.value = {}
  
  if (!formData.value.display_name) {
    errors.value.display_name = 'Display name is required'
  }
  
  if (!formData.value.name) {
    errors.value.name = 'Slug is required'
  } else if (!/^[a-z0-9-_]+$/.test(formData.value.name)) {
    errors.value.name = 'Slug must be lowercase letters, numbers, hyphens, or underscores only'
  }
  
  return Object.keys(errors.value).length === 0
}

const saveRole = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.post('/admin/roles', formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/admin/roles/${formData.value.id}`, formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.updatedSuccessfully'),
        life: 3000
      })
    }
    
    dialogVisible.value = false
    await fetchRoles()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to save role'
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

const isGroupSelected = (groupPermissions) => {
  return groupPermissions.every(p => selectedPermissions.value.includes(p.id))
}

const toggleGroup = (groupPermissions, checked) => {
  if (checked) {
    groupPermissions.forEach(p => {
      if (!selectedPermissions.value.includes(p.id)) {
        selectedPermissions.value.push(p.id)
      }
    })
  } else {
    groupPermissions.forEach(p => {
      const index = selectedPermissions.value.indexOf(p.id)
      if (index > -1) {
        selectedPermissions.value.splice(index, 1)
      }
    })
  }
}

const savePermissions = async () => {
  savingPermissions.value = true
  try {
    await api.post(`/admin/roles/${selectedRole.value.id}/permissions`, {
      permissions: selectedPermissions.value
    })
    
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.updatedSuccessfully'),
      life: 3000
    })
    
    permissionsDialogVisible.value = false
    await fetchRoles()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to update permissions'
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: message,
      life: 3000
    })
  } finally {
    savingPermissions.value = false
  }
}

const confirmDelete = (role) => {
  roleToDelete.value = role
  deleteDialogVisible.value = true
}

const deleteRole = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/roles/${roleToDelete.value.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.deletedSuccessfully'),
      life: 3000
    })
    
    deleteDialogVisible.value = false
    await fetchRoles()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to delete role'
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
  console.log('RolesView mounted')
  console.log('User permissions:', can('roles.view'), can('roles.create'), can('roles.edit'), can('roles.delete'))
  fetchRoles()
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

.table-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
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

/* Permissions Dialog */
.permissions-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.permissions-header h3 {
  margin: 0 0 0.5rem 0;
  color: #1f2937;
}

.text-muted {
  color: #6b7280;
  margin: 0;
  font-size: 0.875rem;
}

.permissions-search {
  width: 100%;
}

.permissions-list {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.permission-group {
  margin-bottom: 1.5rem;
}

.permission-group:last-child {
  margin-bottom: 0;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 6px;
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #1f2937;
}

.group-name {
  flex: 1;
  text-transform: capitalize;
}

.group-count {
  color: #6b7280;
  font-size: 0.875rem;
  font-weight: 400;
}

.group-permissions {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 0.75rem;
  padding-left: 2rem;
}

.permission-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.permission-label {
  cursor: pointer;
  font-size: 0.9rem;
  color: #4b5563;
  user-select: none;
}

/* Scrollbar */
.permissions-list::-webkit-scrollbar {
  width: 8px;
}

.permissions-list::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 4px;
}

.permissions-list::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.permissions-list::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>
