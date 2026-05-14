<template>
  <div class="user-management-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('users.title') }}</h2>
        <p class="text-muted">{{ $t('users.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('users.viewRoles')" icon="pi pi-shield" outlined @click="activeTab = 'roles'" />
        <Button :label="$t('users.addUser')" icon="pi pi-plus" @click="openCreateDialog" />
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button 
          class="tab" 
          :class="{ active: activeTab === 'users' }"
          @click="activeTab = 'users'"
        >
          <i class="pi pi-users"></i>
          {{ $t('users.users') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'roles' }"
          @click="activeTab = 'roles'"
        >
          <i class="pi pi-shield"></i>
          {{ $t('users.roles') }}
        </button>
      </div>
    </div>

    <!-- Users Tab -->
    <div v-show="activeTab === 'users'">
      <!-- Stats -->
      <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff;">
          <i class="pi pi-users" style="color: #3b82f6;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ users.length }}</div>
          <div class="stat-label">{{ $t('users.totalUsers') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f0fdf4;">
          <i class="pi pi-check-circle" style="color: #22c55e;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ users.filter(u => u.is_active).length }}</div>
          <div class="stat-label">{{ $t('common.active') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7;">
          <i class="pi pi-shield" style="color: #f59e0b;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ roles.length }}</div>
          <div class="stat-label">{{ $t('users.roles') }}</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="filter-group">
        <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
        <InputText v-model="searchQuery" :placeholder="$t('users.searchUser')" class="filter-input" />
      </div>
      <div class="filter-group">
        <label class="filter-label"><i class="pi pi-shield" /> {{ $t('users.role') }}</label>
        <Select v-model="filterRole" :options="roleOptions" optionLabel="label" optionValue="value" 
                :placeholder="$t('users.allRoles')" showClear style="width:180px" />
      </div>
      <div class="filter-group">
        <label class="filter-label"><i class="pi pi-circle" /> {{ $t('common.status') }}</label>
        <Select v-model="filterActive" :options="activeOptions" optionLabel="label" optionValue="value" 
                :placeholder="$t('common.allStatuses')" showClear style="width:160px" />
      </div>
    </div>

    <!-- Table -->
    <DataTable :value="filteredUsers" :loading="loading" paginator :rows="15" 
               :rowsPerPageOptions="[10, 15, 25]" stripedRows>
      <Column field="name" :header="$t('users.name')" sortable />
      <Column field="email" :header="$t('users.email')" sortable />
      <Column field="phone" :header="$t('users.phone')" />
      <Column field="role" :header="$t('users.role')" sortable>
        <template #body="{ data }">
          <Tag v-if="data.role_name" :value="getRoleDisplayName(data.role_name)" 
               :severity="getRoleSeverity(data.role_name)" />
          <span v-else class="text-muted">-</span>
        </template>
      </Column>
      <Column field="is_active" :header="$t('common.status')">
        <template #body="{ data }">
          <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')" 
               :severity="data.is_active ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column field="created_at" :header="$t('users.joinedAt')" sortable>
        <template #body="{ data }">
          {{ formatDate(data.created_at) }}
        </template>
      </Column>
      <Column :header="$t('common.actions')" style="width: 120px">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-pencil" text rounded size="small" 
                    @click="openEditDialog(data)" v-tooltip.top="$t('common.edit')" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" 
                    @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
          </div>
        </template>
      </Column>
    </DataTable>
    </div>

    <!-- Roles Tab -->
    <div v-show="activeTab === 'roles'">
      <div class="roles-section">
        <div class="roles-header">
          <div>
            <h3>{{ $t('users.roleManagement') }}</h3>
            <p class="text-muted">{{ $t('users.roleManagementDesc') }}</p>
          </div>
          <Button :label="$t('users.addRole')" icon="pi pi-plus" @click="openCreateRoleDialog" />
        </div>

        <div class="roles-grid">
          <Card v-for="role in sortedRoles" :key="role.id" class="role-card">
            <template #header>
              <div class="role-card-header">
                <div class="role-badge" :style="{ backgroundColor: getRoleColor(role.name) }">
                  <i class="pi pi-shield"></i>
                </div>
                <Tag :value="`Level ${role.level}`" severity="info" />
              </div>
            </template>
            <template #title>
              {{ role.display_name }}
            </template>
            <template #subtitle>
              {{ role.description }}
            </template>
            <template #content>
              <div class="role-stats">
                <div class="role-stat">
                  <i class="pi pi-shield"></i>
                  <span>{{ role.permissions_count || 0 }} {{ $t('users.permissions') }}</span>
                </div>
                <div class="role-stat">
                  <i class="pi pi-users"></i>
                  <span>{{ role.users_count || 0 }} {{ $t('users.users') }}</span>
                </div>
              </div>
              <div class="role-actions">
                <Button 
                  :label="$t('users.viewPermissions')" 
                  text 
                  size="small"
                  @click="viewRolePermissions(role)" 
                />
                <Button 
                  icon="pi pi-pencil" 
                  text 
                  rounded
                  size="small"
                  @click="openEditRoleDialog(role)" 
                  v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  icon="pi pi-trash" 
                  text 
                  rounded
                  size="small"
                  severity="danger"
                  @click="confirmDeleteRole(role)" 
                  v-tooltip.top="$t('common.delete')"
                />
              </div>
            </template>
          </Card>
        </div>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="formDialogVisible" :header="editingUser ? $t('users.editUser') : $t('users.addUser')" 
            modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field full-width">
          <label>{{ $t('users.name') }} *</label>
          <InputText v-model="form.name" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('users.email') }} *</label>
          <InputText v-model="form.email" type="email" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('users.phone') }}</label>
          <InputText v-model="form.phone" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('users.role') }} *</label>
          <Select v-model="form.role_id" :options="roles" optionLabel="display_name" optionValue="id" 
                  :placeholder="$t('users.selectRole')" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('users.password') }} {{ !editingUser ? '*' : '' }}</label>
          <Password v-model="form.password" :placeholder="editingUser ? $t('users.leaveBlankToKeep') : ''" 
                    toggleMask fluid :feedback="false" />
        </div>
        <div v-if="editingUser" class="form-field full-width">
          <label>{{ $t('common.status') }}</label>
          <div class="toggle-row">
            <ToggleSwitch v-model="form.is_active" />
            <span>{{ form.is_active ? $t('common.active') : $t('common.inactive') }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="formDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveUser" :loading="saving" />
      </template>
    </Dialog>

    <!-- Role Permissions Dialog -->
    <Dialog v-model:visible="permissionsDialogVisible" :header="selectedRole?.display_name" 
            modal :style="{ width: '800px' }">
      <div v-if="selectedRole" class="permissions-content">
        <div class="role-info">
          <Tag :value="`Level ${selectedRole.level}`" severity="info" />
          <p class="role-description">{{ selectedRole.description }}</p>
        </div>

        <div class="permissions-grid">
          <div v-for="(perms, group) in groupedPermissions" :key="group" class="permission-group">
            <h4 class="group-title">{{ formatGroupName(group) }}</h4>
            <div class="permission-list">
              <div v-for="perm in perms" :key="perm.id" class="permission-item">
                <i class="pi pi-check-circle" style="color: #22c55e;"></i>
                <span>{{ perm.display_name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="permissionsDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Create/Edit Role Dialog -->
    <Dialog v-model:visible="roleFormDialogVisible" 
            :header="editingRole ? $t('users.editRole') : $t('users.addRole')" 
            modal :style="{ width: '900px', maxHeight: '90vh' }" class="role-form-dialog">
      <div class="role-form-content">
        <!-- Basic Info -->
        <div class="form-section">
          <h4>{{ $t('users.basicInfo') }}</h4>
          <div class="form-grid">
            <div class="form-field" v-if="!editingRole">
              <label>{{ $t('users.roleName') }} * <small>({{ $t('users.roleNameHint') }})</small></label>
              <InputText v-model="roleForm.name" fluid placeholder="e.g. custom_manager" />
            </div>
            <div class="form-field" :class="{ 'full-width': editingRole }">
              <label>{{ $t('users.roleDisplayName') }} *</label>
              <InputText v-model="roleForm.display_name" fluid placeholder="e.g. Custom Manager" />
            </div>
            <div class="form-field">
              <label>{{ $t('users.roleLevel') }} *</label>
              <InputNumber v-model="roleForm.level" :min="1" :max="100" fluid />
              <small class="field-hint">{{ $t('users.roleLevelHint') }}</small>
            </div>
            <div class="form-field full-width">
              <label>{{ $t('users.roleDescription') }}</label>
              <Textarea v-model="roleForm.description" rows="2" fluid />
            </div>
          </div>
        </div>

        <!-- Permissions -->
        <div class="form-section">
          <h4>{{ $t('users.selectPermissions') }}</h4>
          <div class="permissions-selection">
            <div v-for="(perms, group) in allPermissions" :key="group" class="permission-group-select">
              <div class="group-header">
                <Checkbox 
                  :modelValue="isGroupSelected(perms)" 
                  @update:modelValue="toggleGroupPermissions(perms)"
                  :binary="true"
                />
                <h5>{{ formatGroupName(group) }}</h5>
                <span class="group-count">({{ perms.length }})</span>
              </div>
              <div class="permission-checkboxes">
                <div v-for="perm in perms" :key="perm.id" class="permission-checkbox">
                  <Checkbox 
                    :modelValue="selectedPermissions.includes(perm.id)" 
                    @update:modelValue="togglePermission(perm.id)"
                    :binary="true"
                    :inputId="`perm-${perm.id}`"
                  />
                  <label :for="`perm-${perm.id}`">{{ perm.display_name }}</label>
                </div>
              </div>
            </div>
          </div>
          <div class="selected-count">
            <Tag :value="`${selectedPermissions.length} ${$t('users.permissionsSelected')}`" severity="info" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="roleFormDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveRole" :loading="saving" />
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
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Password from 'primevue/password'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import ToggleSwitch from 'primevue/toggleswitch'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Textarea from 'primevue/textarea'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const users = ref([])
const roles = ref([])
const loading = ref(false)
const saving = ref(false)
const searchQuery = ref('')
const filterRole = ref(null)
const filterActive = ref(null)
const formDialogVisible = ref(false)
const permissionsDialogVisible = ref(false)
const roleFormDialogVisible = ref(false)
const editingUser = ref(null)
const editingRole = ref(null)
const activeTab = ref('users')
const selectedRole = ref(null)
const rolePermissions = ref([])
const allPermissions = ref([])
const selectedPermissions = ref([])

const form = ref({
  name: '', email: '', phone: '', password: '', role_id: null, is_active: true
})

const roleForm = ref({
  name: '', display_name: '', description: '', level: 50, is_active: true
})

const roleOptions = computed(() => {
  return roles.value.map(r => ({ label: r.display_name, value: r.id }))
})

const activeOptions = computed(() => [
  { label: t('common.active'), value: true },
  { label: t('common.inactive'), value: false },
])

const sortedRoles = computed(() => {
  return [...roles.value].sort((a, b) => b.level - a.level)
})

const groupedPermissions = computed(() => {
  return rolePermissions.value.reduce((acc, perm) => {
    if (!acc[perm.group_name]) {
      acc[perm.group_name] = []
    }
    acc[perm.group_name].push(perm)
    return acc
  }, {})
})

const filteredUsers = computed(() => {
  let result = users.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(u =>
      u.name?.toLowerCase().includes(q) ||
      u.email?.toLowerCase().includes(q) ||
      u.phone?.includes(q)
    )
  }
  if (filterRole.value) result = result.filter(u => u.role_id === filterRole.value)
  if (filterActive.value !== null) result = result.filter(u => u.is_active === filterActive.value)
  return result
})

const fetchUsers = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/users`)
    users.value = response.data.users || []
    
    // Get role info for each user
    users.value.forEach(user => {
      const role = roles.value.find(r => r.id === user.role_id)
      if (role) {
        user.role_name = role.name
        user.role_display = role.display_name
      }
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/roles`)
    roles.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch roles:', error)
  }
}

const fetchAllPermissions = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/permissions`)
    allPermissions.value = response.data || {}
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
  }
}

const openCreateDialog = () => {
  editingUser.value = null
  form.value = { name: '', email: '', phone: '', password: '', role_id: null, is_active: true }
  formDialogVisible.value = true
}

const openEditDialog = (user) => {
  editingUser.value = user
  form.value = { ...user, password: '' }
  formDialogVisible.value = true
}

const saveUser = async () => {
  if (!form.value.name || !form.value.email) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  if (!editingUser.value && !form.value.password) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.passwordRequired'), life: 3000 })
    return
  }

  if (!form.value.role_id) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.roleRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const payload = { ...form.value }
    if (editingUser.value && !payload.password) {
      delete payload.password
    }

    if (editingUser.value) {
      await api.put(`/outlets/${outletId}/users/${editingUser.value.id}`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.userUpdated'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/users`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.userCreated'), life: 3000 })
    }
    formDialogVisible.value = false
    fetchUsers()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (user) => {
  confirm.require({
    message: `${t('users.deleteConfirm')} ${user.name}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/users/${user.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.userDeleted'), life: 3000 })
        fetchUsers()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
      }
    }
  })
}

const getRoleDisplayName = (roleName) => {
  const role = roles.value.find(r => r.name === roleName)
  return role?.display_name || roleName
}

const getRoleSeverity = (roleName) => {
  const map = {
    owner: 'danger',
    spv: 'warn',
    manager: 'info',
    cashier: 'success',
    barista: 'secondary',
    waitress: 'secondary',
    kitchen_staff: 'secondary'
  }
  return map[roleName] || 'secondary'
}

const getRoleColor = (roleName) => {
  const map = {
    owner: '#ef4444',
    spv: '#f59e0b',
    manager: '#3b82f6',
    cashier: '#22c55e',
    barista: '#8b5cf6',
    waitress: '#ec4899',
    kitchen_staff: '#6b7280'
  }
  return map[roleName] || '#6b7280'
}

const formatGroupName = (group) => {
  const map = {
    dashboard: 'Dashboard & Reports',
    users: 'User Management',
    menu: 'Menu Management',
    inventory: 'Inventory Management',
    pos: 'POS & Transactions',
    kds: 'Kitchen Display',
    tables: 'Table Management',
    membership: 'Membership',
    promo: 'Promo Management',
    settings: 'Settings'
  }
  return map[group] || group
}

const viewRolePermissions = async (role) => {
  selectedRole.value = role
  try {
    const response = await api.get(`/outlets/${outletId}/roles/${role.id}/permissions`)
    rolePermissions.value = response.data.permissions || []
    permissionsDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const openCreateRoleDialog = () => {
  editingRole.value = null
  roleForm.value = { name: '', display_name: '', description: '', level: 50, is_active: true }
  selectedPermissions.value = []
  roleFormDialogVisible.value = true
}

const openEditRoleDialog = async (role) => {
  editingRole.value = role
  roleForm.value = { ...role }
  
  // Fetch role permissions
  try {
    const response = await api.get(`/outlets/${outletId}/roles/${role.id}/permissions`)
    const permissions = response.data.permissions || []
    selectedPermissions.value = permissions.map(p => p.id)
  } catch (error) {
    console.error('Failed to fetch role permissions:', error)
    selectedPermissions.value = []
  }
  
  roleFormDialogVisible.value = true
}

const saveRole = async () => {
  if (!roleForm.value.display_name || !roleForm.value.level) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  if (!editingRole.value && !roleForm.value.name) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.roleNameRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const payload = { ...roleForm.value, permissions: selectedPermissions.value }

    if (editingRole.value) {
      await api.put(`/outlets/${outletId}/roles/${editingRole.value.id}`, payload)
      
      // Update permissions separately
      await api.put(`/outlets/${outletId}/roles/${editingRole.value.id}/permissions`, {
        permissions: selectedPermissions.value
      })
      
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.roleUpdated'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/roles`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.roleCreated'), life: 3000 })
    }
    
    roleFormDialogVisible.value = false
    fetchRoles()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDeleteRole = (role) => {
  confirm.require({
    message: `${t('users.deleteRoleConfirm')} ${role.display_name}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/roles/${role.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('users.roleDeleted'), life: 3000 })
        fetchRoles()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
      }
    }
  })
}

const togglePermission = (permissionId) => {
  const index = selectedPermissions.value.indexOf(permissionId)
  if (index > -1) {
    selectedPermissions.value.splice(index, 1)
  } else {
    selectedPermissions.value.push(permissionId)
  }
}

const toggleGroupPermissions = (groupPerms) => {
  const groupIds = groupPerms.map(p => p.id)
  const allSelected = groupIds.every(id => selectedPermissions.value.includes(id))
  
  if (allSelected) {
    // Deselect all
    selectedPermissions.value = selectedPermissions.value.filter(id => !groupIds.includes(id))
  } else {
    // Select all
    groupIds.forEach(id => {
      if (!selectedPermissions.value.includes(id)) {
        selectedPermissions.value.push(id)
      }
    })
  }
}

const isGroupSelected = (groupPerms) => {
  const groupIds = groupPerms.map(p => p.id)
  return groupIds.every(id => selectedPermissions.value.includes(id))
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(() => {
  fetchRoles().then(() => {
    fetchUsers()
    fetchAllPermissions()
  })
})
</script>

<style scoped>
.user-management-view {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.tabs-container {
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.tabs {
  display: flex;
  gap: 0.5rem;
}

.tab {
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6b7280;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: -2px;
}

.tab:hover {
  color: #3b82f6;
}

.tab.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid #e5e7eb;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.stat-value { font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.75rem; color: #6b7280; }

.filter-bar { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 0.3rem; }
.filter-input { width: 220px; }

.action-buttons { display: flex; gap: 0.25rem; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field.full-width { grid-column: 1 / -1; }

.toggle-row { display: flex; align-items: center; gap: 0.75rem; }

.roles-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.role-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.role-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.role-header h4 { margin: 0; font-size: 1rem; }
.role-description { margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #6b7280; }

.role-stats {
  display: flex;
  gap: 1.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.role-stats span {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.roles-section {
  padding: 1rem 0;
}

.roles-header {
  margin-bottom: 1.5rem;
}

.roles-header h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.role-card {
  border: 1px solid #e5e7eb;
  transition: all 0.2s;
}

.role-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.role-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f9fafb;
}

.role-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.role-stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.permissions-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.role-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.role-description {
  margin: 0;
  color: #6b7280;
  font-size: 0.875rem;
}

.permissions-grid {
  display: grid;
  gap: 1.5rem;
}

.permission-group {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.group-title {
  margin: 0 0 0.75rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: #3b82f6;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.permission-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}

.permission-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
  color: #374151;
  padding: 0.5rem;
  line-height: 1.5;
}

.roles-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.role-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.role-form-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  max-height: 70vh;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.form-section {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.form-section h4 {
  margin: 0 0 1rem 0;
  font-size: 1rem;
  color: #1f2937;
}

.field-hint {
  color: #9ca3af;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.permissions-selection {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 400px;
  overflow-y: auto;
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}

.permission-group-select {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.75rem;
  background: #f9fafb;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.group-header h5 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: #3b82f6;
  flex: 1;
}

.group-count {
  font-size: 0.75rem;
  color: #6b7280;
}

.permission-checkboxes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
  padding: 0.5rem 0;
}

.permission-checkbox {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  min-height: 32px;
}

.permission-checkbox label {
  font-size: 0.875rem;
  color: #374151;
  cursor: pointer;
  line-height: 1.5;
  word-break: break-word;
  flex: 1;
}

.selected-count {
  margin-top: 1rem;
  text-align: right;
}

.role-form-dialog :deep(.p-dialog-content) {
  padding: 1.5rem;
}
</style>
