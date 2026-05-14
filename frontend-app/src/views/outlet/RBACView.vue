<template>
  <div class="rbac-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('rbac.title') }}</h2>
        <p class="text-muted">{{ $t('rbac.subtitle') }}</p>
      </div>
      <Button :label="$t('rbac.createRole')" icon="pi pi-plus" @click="openCreateRoleDialog" />
    </div>

    <!-- Roles List -->
    <Card>
      <template #title>{{ $t('rbac.rolesAndPermissions') }}</template>
      <template #content>
        <DataTable 
          :value="roles" 
          :loading="loading"
          stripedRows
          showGridlines
          responsiveLayout="scroll"
        >
          <Column field="display_name" :header="$t('rbac.roleName')" style="min-width: 200px">
            <template #body="slotProps">
              <div class="role-name">
                <Tag :value="slotProps.data.display_name" :severity="getRoleSeverity(slotProps.data.name)" />
                <span v-if="isSystemRole(slotProps.data.name)" class="system-badge">
                  <i class="pi pi-lock"></i> {{ $t('rbac.systemRole') }}
                </span>
              </div>
            </template>
          </Column>
          
          <Column field="description" :header="$t('rbac.description')" style="min-width: 250px" />
          
          <Column field="level" :header="$t('rbac.level')" style="width: 100px">
            <template #body="slotProps">
              <Tag :value="slotProps.data.level" severity="info" />
            </template>
          </Column>
          
          <Column field="users_count" :header="$t('rbac.usersCount')" style="width: 120px">
            <template #body="slotProps">
              <Chip :label="String(slotProps.data.users_count)" icon="pi pi-users" />
            </template>
          </Column>
          
          <Column field="permissions" :header="$t('rbac.permissions')" style="min-width: 200px">
            <template #body="slotProps">
              <div class="permissions-summary">
                <Chip :label="`${slotProps.data.permissions?.length || 0} ${$t('rbac.permissions')}`" icon="pi pi-shield" />
                <Button 
                  icon="pi pi-eye" 
                  text 
                  rounded 
                  size="small"
                  @click="viewPermissions(slotProps.data)"
                  v-tooltip.top="$t('rbac.viewPermissions')"
                />
              </div>
            </template>
          </Column>
          
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="slotProps">
              <div class="action-buttons">
                <Button 
                  icon="pi pi-pencil" 
                  text 
                  rounded 
                  severity="info"
                  @click="editRole(slotProps.data)"
                  :disabled="isSystemRole(slotProps.data.name)"
                  v-tooltip.top="$t('common.edit')"
                />
                <Button 
                  icon="pi pi-trash" 
                  text 
                  rounded 
                  severity="danger"
                  @click="confirmDeleteRole(slotProps.data)"
                  :disabled="isSystemRole(slotProps.data.name) || slotProps.data.users_count > 0"
                  v-tooltip.top="$t('common.delete')"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Role Dialog -->
    <Dialog 
      v-model:visible="roleDialog" 
      :header="editingRole ? $t('rbac.editRole') : $t('rbac.createRole')"
      :modal="true"
      :style="{ width: '800px' }"
      :closable="true"
    >
      <div class="role-form">
        <div class="form-grid">
          <div class="form-field">
            <label>{{ $t('rbac.roleName') }} *</label>
            <InputText v-model="roleForm.display_name" :placeholder="$t('rbac.roleNamePlaceholder')" />
          </div>
          
          <div class="form-field">
            <label>{{ $t('rbac.roleIdentifier') }} *</label>
            <InputText v-model="roleForm.name" :placeholder="$t('rbac.roleIdentifierPlaceholder')" />
            <small>{{ $t('rbac.roleIdentifierHint') }}</small>
          </div>
          
          <div class="form-field">
            <label>{{ $t('rbac.level') }} *</label>
            <InputNumber v-model="roleForm.level" :min="1" :max="99" showButtons />
            <small>{{ $t('rbac.levelHint') }}</small>
          </div>
          
          <div class="form-field full-width">
            <label>{{ $t('rbac.description') }}</label>
            <Textarea v-model="roleForm.description" rows="3" :placeholder="$t('rbac.descriptionPlaceholder')" />
          </div>
        </div>

        <Divider />

        <div class="permissions-section">
          <h4>{{ $t('rbac.assignPermissions') }}</h4>
          <p class="text-muted">{{ $t('rbac.selectPermissions') }}</p>
          
          <Accordion :multiple="true" :activeIndex="[0]">
            <AccordionPanel v-for="(perms, group) in groupedPermissions" :key="group" :value="group">
              <AccordionHeader>
                <div class="permission-group-header">
                  <span>{{ group }}</span>
                  <Chip :label="`${getSelectedCount(perms)}/${perms.length}`" size="small" />
                </div>
              </AccordionHeader>
              <AccordionContent>
                <div class="permissions-grid">
                  <div v-for="permission in perms" :key="permission.id" class="permission-item">
                    <Checkbox 
                      v-model="roleForm.permissions" 
                      :inputId="`perm-${permission.id}`"
                      :value="permission.id"
                    />
                    <label :for="`perm-${permission.id}`" class="permission-label">
                      {{ permission.display_name }}
                    </label>
                  </div>
                </div>
              </AccordionContent>
            </AccordionPanel>
          </Accordion>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="roleDialog = false" />
        <Button :label="$t('common.save')" icon="pi pi-check" @click="saveRole" :loading="saving" />
      </template>
    </Dialog>

    <!-- View Permissions Dialog -->
    <Dialog 
      v-model:visible="permissionsDialog" 
      :header="$t('rbac.rolePermissions')"
      :modal="true"
      :style="{ width: '600px' }"
    >
      <div v-if="selectedRole" class="permissions-view">
        <div class="role-info">
          <Tag :value="selectedRole.display_name" :severity="getRoleSeverity(selectedRole.name)" size="large" />
          <p>{{ selectedRole.description }}</p>
        </div>

        <Divider />

        <div v-for="(perms, group) in groupPermissionsByGroup(selectedRole.permissions)" :key="group" class="permission-group">
          <h4>{{ group }}</h4>
          <div class="permissions-list">
            <Chip 
              v-for="perm in perms" 
              :key="perm.id" 
              :label="perm.display_name" 
              icon="pi pi-check"
              class="permission-chip"
            />
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Chip from 'primevue/chip'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Divider from 'primevue/divider'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import ConfirmDialog from 'primevue/confirmdialog'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const loading = ref(false)
const saving = ref(false)
const roles = ref([])
const permissions = ref({})
const roleDialog = ref(false)
const permissionsDialog = ref(false)
const editingRole = ref(null)
const selectedRole = ref(null)

const roleForm = ref({
  name: '',
  display_name: '',
  description: '',
  level: 50,
  permissions: []
})

const systemRoles = ['owner', 'admin', 'staff']

const isSystemRole = (roleName) => {
  return systemRoles.includes(roleName)
}

const getRoleSeverity = (roleName) => {
  const severityMap = {
    'owner': 'danger',
    'admin': 'warn',
    'manager': 'info',
    'cashier': 'success',
    'kitchen_staff': 'secondary',
    'staff': 'secondary'
  }
  return severityMap[roleName] || 'secondary'
}

const groupedPermissions = computed(() => {
  return permissions.value
})

const getSelectedCount = (perms) => {
  return perms.filter(p => roleForm.value.permissions.includes(p.id)).length
}

const groupPermissionsByGroup = (perms) => {
  if (!perms) return {}
  return perms.reduce((acc, perm) => {
    if (!acc[perm.group_name]) {
      acc[perm.group_name] = []
    }
    acc[perm.group_name].push(perm)
    return acc
  }, {})
}

const loadRoles = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/roles`)
    roles.value = response.data
  } catch (error) {
    console.error('Failed to load roles:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: t('rbac.failedToLoadRoles'),
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const loadPermissions = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/permissions`)
    permissions.value = response.data
  } catch (error) {
    console.error('Failed to load permissions:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: t('rbac.failedToLoadPermissions'),
      life: 3000
    })
  }
}

const openCreateRoleDialog = () => {
  editingRole.value = null
  roleForm.value = {
    name: '',
    display_name: '',
    description: '',
    level: 50,
    permissions: []
  }
  roleDialog.value = true
}

const editRole = (role) => {
  editingRole.value = role
  roleForm.value = {
    name: role.name,
    display_name: role.display_name,
    description: role.description || '',
    level: role.level,
    permissions: role.permissions?.map(p => p.id) || []
  }
  roleDialog.value = true
}

const saveRole = async () => {
  // Validation
  if (!roleForm.value.name || !roleForm.value.display_name) {
    toast.add({
      severity: 'warn',
      summary: t('messages.warning'),
      detail: t('rbac.fillRequiredFields'),
      life: 3000
    })
    return
  }

  saving.value = true
  try {
    if (editingRole.value) {
      // Update
      await api.put(`/outlets/${outletId}/roles/${editingRole.value.id}`, roleForm.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('rbac.roleUpdated'),
        life: 3000
      })
    } else {
      // Create
      await api.post(`/outlets/${outletId}/roles`, roleForm.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('rbac.roleCreated'),
        life: 3000
      })
    }
    
    roleDialog.value = false
    await loadRoles()
  } catch (error) {
    console.error('Failed to save role:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: error.response?.data?.message || t('rbac.failedToSaveRole'),
      life: 3000
    })
  } finally {
    saving.value = false
  }
}

const confirmDeleteRole = (role) => {
  confirm.require({
    message: t('rbac.deleteRoleConfirm', { name: role.display_name }),
    header: t('rbac.deleteRole'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteRole(role)
  })
}

const deleteRole = async (role) => {
  try {
    await api.delete(`/outlets/${outletId}/roles/${role.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('rbac.roleDeleted'),
      life: 3000
    })
    await loadRoles()
  } catch (error) {
    console.error('Failed to delete role:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: error.response?.data?.message || t('rbac.failedToDeleteRole'),
      life: 3000
    })
  }
}

const viewPermissions = (role) => {
  selectedRole.value = role
  permissionsDialog.value = true
}

onMounted(() => {
  loadRoles()
  loadPermissions()
})
</script>

<style scoped>
.rbac-view {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 1rem;
}

.page-header h2 {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

.role-name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.system-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #6b7280;
  padding: 0.25rem 0.5rem;
  background: #f3f4f6;
  border-radius: 4px;
}

.permissions-summary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.role-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field.full-width {
  grid-column: 1 / -1;
}

.form-field label {
  font-weight: 600;
  font-size: 0.875rem;
  color: #374151;
}

.form-field small {
  font-size: 0.75rem;
  color: #6b7280;
}

.permissions-section h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.permission-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding-right: 1rem;
}

.permissions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 0.75rem;
  padding: 1rem 0;
}

.permission-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0;
}

.permission-label {
  font-size: 0.875rem;
  cursor: pointer;
  user-select: none;
  white-space: normal;
  word-break: break-word;
}

.permissions-view {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.role-info {
  text-align: center;
}

.role-info p {
  margin: 0.5rem 0 0 0;
  color: #6b7280;
}

.permission-group {
  margin-bottom: 1.5rem;
}

.permission-group h4 {
  margin: 0 0 0.75rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
}

.permissions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.permission-chip {
  background: #e0f2fe;
  color: #0369a1;
  margin: 0.25rem;
  white-space: normal;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .permissions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
