<template>
  <div class="outlet-users-manager">
    <div class="manager-header">
      <Button 
        :label="$t('outlet.addOutletUser')" 
        icon="pi pi-plus" 
        @click="openCreateDialog"
        :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
      />
    </div>

    <DataTable 
      :value="users" 
      :loading="loading"
      stripedRows
      showGridlines
    >
      <Column field="id" header="ID" sortable style="width: 80px"></Column>
      <Column field="name" :header="$t('outlet.outletUserName')" sortable></Column>
      <Column field="email" :header="$t('outlet.outletUserEmail')" sortable></Column>
      <Column field="phone" :header="$t('outlet.outletUserPhone')"></Column>
      <Column field="role" :header="$t('outlet.outletUserRole')" sortable>
        <template #body="{ data }">
          <Tag 
            :value="data.role" 
            :severity="getRoleSeverity(data.role)"
            style="text-transform: capitalize;"
          />
        </template>
      </Column>
      <Column field="is_active" :header="$t('outlet.outletUserActive')" style="width: 120px">
        <template #body="{ data }">
          <Tag 
            :value="data.is_active ? 'Active' : 'Inactive'" 
            :severity="data.is_active ? 'success' : 'danger'" 
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
              v-tooltip.top="$t('common.edit')"
            />
            <Button 
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
          <i class="pi pi-users" style="font-size: 3rem; color: #9ca3af;"></i>
          <p>{{ $t('common.noData') }}</p>
        </div>
      </template>
    </DataTable>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('outlet.addOutletUser') : $t('outlet.editOutletUser')"
      :modal="true"
      :style="{ width: '600px' }"
    >
      <div class="dialog-content">
        <div class="field">
          <label for="name">{{ $t('outlet.outletUserName') }} *</label>
          <InputText 
            id="name"
            v-model="formData.name" 
            :class="{ 'p-invalid': errors.name }"
            :placeholder="$t('outlet.outletUserName')"
          />
          <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
        </div>

        <div class="field">
          <label for="email">{{ $t('outlet.outletUserEmail') }} *</label>
          <InputText 
            id="email"
            v-model="formData.email" 
            type="email"
            :class="{ 'p-invalid': errors.email }"
            :placeholder="$t('outlet.outletUserEmail')"
          />
          <small v-if="errors.email" class="p-error">{{ errors.email }}</small>
        </div>

        <div class="field">
          <label for="password">{{ $t('auth.password') }} {{ dialogMode === 'edit' ? '(leave empty to keep current)' : '*' }}</label>
          <Password 
            id="password"
            v-model="formData.password" 
            :class="{ 'p-invalid': errors.password }"
            :placeholder="$t('auth.password')"
            toggleMask
            :feedback="false"
          />
          <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
        </div>

        <div class="field">
          <label for="phone">{{ $t('outlet.outletUserPhone') }}</label>
          <InputText 
            id="phone"
            v-model="formData.phone" 
            :placeholder="$t('outlet.outletUserPhone')"
          />
        </div>

        <div class="field">
          <label for="role">{{ $t('outlet.outletUserRole') }}</label>
          <Dropdown 
            id="role"
            v-model="formData.role" 
            :options="roleOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('outlet.selectOutletRole')"
          />
        </div>

        <div class="field">
          <div class="checkbox-field">
            <Checkbox 
              v-model="formData.is_active" 
              inputId="is_active" 
              :binary="true" 
            />
            <label for="is_active" class="checkbox-label">{{ $t('outlet.outletUserActive') }}</label>
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
          @click="saveUser"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialogVisible" 
      :header="$t('outlet.deleteOutletUser')"
      :modal="true"
      :style="{ width: '450px' }"
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
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  outlet: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])

const toast = useToast()
const { t } = useI18n()

// Data
const users = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const dialogMode = ref('create')
const userToDelete = ref(null)

const formData = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  phone: '',
  role: 'staff',
  is_active: true
})

const errors = ref({})

const roleOptions = ref([
  { label: t('outlet.staff'), value: 'staff' },
  { label: t('outlet.manager'), value: 'manager' },
  { label: t('outlet.admin'), value: 'admin' }
])

// Methods
const fetchUsers = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${props.outlet.id}/users`)
    users.value = response.data.users
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

const openCreateDialog = () => {
  dialogMode.value = 'create'
  formData.value = {
    id: null,
    name: '',
    email: '',
    password: '',
    phone: '',
    role: 'staff',
    is_active: true
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
    phone: user.phone || '',
    role: user.role,
    is_active: user.is_active
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
  }
  
  if (dialogMode.value === 'create' && !formData.value.password) {
    errors.value.password = 'Password is required'
  }
  
  return Object.keys(errors.value).length === 0
}

const saveUser = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.post(`/outlets/${props.outlet.id}/users`, formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/outlets/${props.outlet.id}/users/${formData.value.id}`, formData.value)
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
    await api.delete(`/outlets/${props.outlet.id}/users/${userToDelete.value.id}`)
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

const getRoleSeverity = (role) => {
  const severityMap = {
    'admin': 'danger',
    'manager': 'warning',
    'staff': 'info'
  }
  return severityMap[role] || 'secondary'
}

// Lifecycle
onMounted(() => {
  fetchUsers()
})
</script>

<style scoped>
.outlet-users-manager {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.manager-header {
  display: flex;
  justify-content: flex-end;
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
</style>
