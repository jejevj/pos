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
          <span>{{ $t('menu.userManagement') }}</span>
          <Button 
            v-if="can('users.create')"
            :label="$t('user.addUser')" 
            icon="pi pi-plus" 
            @click="openCreateDialog"
            :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          />
        </div>
      </template>
      <template #content>
        <DataTable 
          :value="filteredUsers" 
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
                  @input="filterUsers"
                />
              </IconField>
            </div>
          </template>
          
          <Column field="id" header="ID" sortable style="width: 80px"></Column>
          <Column field="name" :header="$t('user.userName')" sortable></Column>
          <Column field="email" :header="$t('user.userEmail')" sortable></Column>
          <Column :header="$t('user.userRole')" style="width: 250px">
            <template #body="{ data }">
              <div class="roles-container">
                <Tag 
                  v-for="role in data.roles" 
                  :key="role.id"
                  :value="role.display_name"
                  :severity="getRoleSeverity(role.name)"
                  class="role-tag"
                />
              </div>
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button 
                  v-if="can('users.edit')"
                  icon="pi pi-pencil" 
                  text 
                  rounded
                  severity="info"
                  @click="openEditDialog(data)"
                  :v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  v-if="can('users.delete')"
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
              <i class="pi pi-users" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('user.addUser') : $t('user.editUser')"
      :modal="true"
      :style="{ width: '500px' }"
    >
      <div class="dialog-content">
        <div class="field">
          <label for="name">{{ $t('user.userName') }}</label>
          <InputText 
            id="name"
            v-model="formData.name" 
            :class="{ 'p-invalid': errors.name }"
            style="width: 100%"
          />
          <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
        </div>

        <div class="field">
          <label for="email">{{ $t('user.userEmail') }}</label>
          <InputText 
            id="email"
            v-model="formData.email" 
            type="email"
            :class="{ 'p-invalid': errors.email }"
            style="width: 100%"
          />
          <small v-if="errors.email" class="p-error">{{ errors.email }}</small>
        </div>

        <div v-if="dialogMode === 'create'" class="field">
          <label for="password">{{ $t('auth.password') }}</label>
          <Password 
            id="password"
            v-model="formData.password" 
            :class="{ 'p-invalid': errors.password }"
            toggleMask
            :feedback="false"
            style="width: 100%"
          />
          <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
        </div>

        <div class="field">
          <label for="role">{{ $t('user.userRole') }}</label>
          <Dropdown 
            id="role"
            v-model="formData.role" 
            :options="availableRoles"
            optionLabel="display_name"
            optionValue="id"
            :placeholder="$t('user.selectRole')"
            :class="{ 'p-invalid': errors.role }"
            style="width: 100%"
          />
          <small v-if="errors.role" class="p-error">{{ errors.role }}</small>
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
          @click="saveUser"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialogVisible" 
      :header="$t('user.deleteUser')"
      :modal="true"
      :style="{ width: '400px' }"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
        <p>{{ $t('messages.confirmDelete', { item: userToDelete?.name }) }}</p>
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
          @click="deleteUser"
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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Password from 'primevue/password'
import Dropdown from 'primevue/dropdown'
import ProgressSpinner from 'primevue/progressspinner'

const toast = useToast()
const { can } = usePermission()
const { t } = useI18n()

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.userManagement') }
])

// Data
const users = ref([])
const availableRoles = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const dialogMode = ref('create') // 'create' or 'edit'
const userToDelete = ref(null)
const searchQuery = ref('')

// Computed
const isProcessing = computed(() => {
  return saving.value || deleting.value
})

const loadingMessage = computed(() => {
  if (saving.value) return t('common.loading')
  if (deleting.value) return t('common.loading')
  return t('common.loading')
})

const formData = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  role: null
})

const errors = ref({})

// Computed
const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value
  
  const query = searchQuery.value.toLowerCase()
  return users.value.filter(user => 
    user.name.toLowerCase().includes(query) ||
    user.email.toLowerCase().includes(query)
  )
})

// Methods
const filterUsers = () => {
  // Trigger reactivity
}

const fetchUsers = async () => {
  loading.value = true
  try {
    const response = await api.get('/admin/users')
    users.value = response.data.data
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch users',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const response = await api.get('/admin/roles')
    availableRoles.value = response.data
    console.log('Available roles:', availableRoles.value)
  } catch (error) {
    console.error('Failed to fetch roles:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch roles',
      life: 3000
    })
  }
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  formData.value = {
    id: null,
    name: '',
    email: '',
    password: '',
    role: null
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (user) => {
  dialogMode.value = 'edit'
  formData.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    role: user.roles && user.roles.length > 0 ? user.roles[0].id : null
  }
  errors.value = {}
  dialogVisible.value = true
}

const validateForm = () => {
  errors.value = {}
  
  if (!formData.value.name) {
    errors.value.name = 'Name is required'
  }
  
  if (!formData.value.email) {
    errors.value.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.value.email)) {
    errors.value.email = 'Invalid email format'
  }
  
  if (dialogMode.value === 'create' && !formData.value.password) {
    errors.value.password = 'Password is required'
  }
  
  if (dialogMode.value === 'create' && formData.value.password && formData.value.password.length < 8) {
    errors.value.password = 'Password must be at least 8 characters'
  }
  
  if (!formData.value.role) {
    errors.value.role = 'Role is required'
  }
  
  return Object.keys(errors.value).length === 0
}

const saveUser = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    const payload = {
      name: formData.value.name,
      email: formData.value.email,
      roles: [formData.value.role] // Convert single role to array for backend
    }
    
    if (dialogMode.value === 'create') {
      payload.password = formData.value.password
      await api.post('/admin/users', payload)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/admin/users/${formData.value.id}`, payload)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.updatedSuccessfully'),
        life: 3000
      })
    }
    
    dialogVisible.value = false
    await fetchUsers()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to save user'
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: message,
      life: 3000
    })
    
    // Handle validation errors
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (user) => {
  userToDelete.value = user
  deleteDialogVisible.value = true
}

const deleteUser = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/users/${userToDelete.value.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.deletedSuccessfully'),
      life: 3000
    })
    
    deleteDialogVisible.value = false
    await fetchUsers()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to delete user'
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

const getRoleSeverity = (roleName) => {
  const severityMap = {
    'superadmin': 'danger',
    'admin': 'warning',
    'manager': 'info',
    'user': 'success'
  }
  return severityMap[roleName] || 'secondary'
}

// Lifecycle
onMounted(() => {
  fetchUsers()
  fetchRoles()
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

.roles-container {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.role-tag {
  font-size: 0.875rem;
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
</style>
