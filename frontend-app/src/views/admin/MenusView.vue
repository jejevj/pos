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
          <span>{{ $t('menu.menuManagement') }}</span>
          <Button 
            v-if="can('menus.create')"
            :label="$t('menuMgmt.addMenu')" 
            icon="pi pi-plus" 
            @click="openCreateDialog"
            :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
          />
        </div>
      </template>
      <template #content>
        <div class="menu-table-wrapper">
          <div class="menu-list">
            <draggable 
              v-model="menus" 
              @end="onDragEnd"
              item-key="id"
              handle=".drag-handle"
              :animation="200"
              ghost-class="ghost"
            >
              <template #item="{ element: menu }">
                <div class="menu-item-wrapper">
                  <div class="menu-row" :class="{ 'menu-inactive': !menu.is_active }">
                    <div class="menu-content">
                      <div class="drag-handle">
                        <i class="pi pi-bars"></i>
                      </div>
                      <div class="menu-info">
                        <div class="menu-main">
                          <i :class="menu.icon || 'pi pi-circle'" class="menu-icon"></i>
                          <span class="menu-title">{{ menu.title }}</span>
                          <code class="menu-name">{{ menu.name }}</code>
                          <Tag v-if="menu.children && menu.children.length > 0" :value="`${menu.children.length} ${$t('menuMgmt.children')}`" severity="info" class="ml-2" />
                        </div>
                        <div class="menu-meta">
                          <span class="menu-route">{{ menu.route || menu.url || '-' }}</span>
                          <Tag :value="`Order: ${menu.order}`" severity="secondary" />
                          <Tag :value="`${menu.permissions?.length || 0} ${$t('menuMgmt.perms')}`" severity="info" />
                          <Tag :value="menu.is_active ? 'Active' : 'Inactive'" :severity="menu.is_active ? 'success' : 'danger'" />
                        </div>
                      </div>
                    </div>
                    <div class="menu-actions">
                      <Button 
                        icon="pi pi-key" 
                        text 
                        rounded
                        severity="success"
                        @click="openPermissionsDialog(menu)"
                        v-tooltip.top="'Manage Permissions'"
                      />
                      <Button 
                        icon="pi pi-pencil" 
                        text 
                        rounded
                        severity="info"
                        @click="openEditDialog(menu)"
                        v-tooltip.top="'Edit'"
                      />
                      <Button 
                        icon="pi pi-trash" 
                        text 
                        rounded
                        severity="danger"
                        @click="confirmDelete(menu)"
                        v-tooltip.top="'Delete'"
                      />
                    </div>
                  </div>

                  <!-- Child Menus -->
                  <div v-if="menu.children && menu.children.length > 0" class="child-menu-container">
                    <draggable 
                      v-model="menu.children" 
                      @end="onChildDragEnd(menu)"
                      item-key="id"
                      handle=".drag-handle"
                      :animation="200"
                      ghost-class="ghost"
                      class="child-menu-list"
                    >
                      <template #item="{ element: child }">
                        <div class="menu-row menu-row-child" :class="{ 'menu-inactive': !child.is_active }">
                          <div class="menu-content">
                            <div class="child-indicator"></div>
                            <div class="drag-handle">
                              <i class="pi pi-bars"></i>
                            </div>
                            <div class="menu-info">
                              <div class="menu-main">
                                <i :class="child.icon || 'pi pi-circle'" class="menu-icon"></i>
                                <span class="menu-title">{{ child.title }}</span>
                                <code class="menu-name">{{ child.name }}</code>
                              </div>
                              <div class="menu-meta">
                                <span class="menu-route">{{ child.route || child.url || '-' }}</span>
                                <Tag :value="`Order: ${child.order}`" severity="secondary" />
                                <Tag :value="`${child.permissions?.length || 0} ${$t('menuMgmt.perms')}`" severity="info" />
                                <Tag :value="child.is_active ? 'Active' : 'Inactive'" :severity="child.is_active ? 'success' : 'danger'" />
                              </div>
                            </div>
                          </div>
                          <div class="menu-actions">
                            <Button 
                              icon="pi pi-key" 
                              text 
                              rounded
                              severity="success"
                              @click="openPermissionsDialog(child)"
                              v-tooltip.top="'Manage Permissions'"
                            />
                            <Button 
                              icon="pi pi-pencil" 
                              text 
                              rounded
                              severity="info"
                              @click="openEditDialog(child)"
                              v-tooltip.top="'Edit'"
                            />
                            <Button 
                              icon="pi pi-trash" 
                              text 
                              rounded
                              severity="danger"
                              @click="confirmDelete(child)"
                              v-tooltip.top="'Delete'"
                            />
                          </div>
                        </div>
                      </template>
                    </draggable>
                  </div>
                </div>
              </template>
            </draggable>

            <div v-if="menus.length === 0 && !loading" class="empty-state">
              <i class="pi pi-bars" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog 
      v-model:visible="dialogVisible" 
      :header="dialogMode === 'create' ? $t('menuMgmt.addMenu') : $t('menuMgmt.editMenu')"
      :modal="true"
      :style="{ width: '700px' }"
    >
      <div class="dialog-content">
        <div class="form-row">
          <div class="field">
            <label for="title">{{ $t('menuMgmt.menuTitle') }} *</label>
            <InputText 
              id="title"
              v-model="formData.title" 
              :class="{ 'p-invalid': errors.title }"
              placeholder="e.g., Dashboard"
            />
            <small v-if="errors.title" class="p-error">{{ errors.title }}</small>
          </div>

          <div class="field">
            <label for="name">{{ $t('menuMgmt.menuName') }} *</label>
            <InputText 
              id="name"
              v-model="formData.name" 
              :class="{ 'p-invalid': errors.name }"
              placeholder="e.g., dashboard"
            />
            <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="icon">{{ $t('menuMgmt.menuIcon') }}</label>
            <IconPicker v-model="formData.icon" />
            <small class="field-hint">{{ $t('menuMgmt.selectIcon') }}</small>
          </div>

          <div class="field">
            <label for="parent_id">{{ $t('menuMgmt.menuParent') }}</label>
            <Dropdown 
              id="parent_id"
              v-model="formData.parent_id" 
              :options="parentMenuOptions"
              optionLabel="title"
              optionValue="id"
              :placeholder="$t('menuMgmt.noParent')"
              showClear
            />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="route">{{ $t('menuMgmt.menuRoute') }}</label>
            <InputText 
              id="route"
              v-model="formData.route" 
              placeholder="e.g., /dashboard"
            />
          </div>

          <div class="field">
            <label for="url">{{ $t('menuMgmt.menuUrl') }}</label>
            <InputText 
              id="url"
              v-model="formData.url" 
              placeholder="e.g., /dashboard"
            />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="order">{{ $t('menuMgmt.menuOrder') }}</label>
            <InputNumber 
              id="order"
              v-model="formData.order" 
              :min="0"
              showButtons
            />
            <small class="field-hint">Lower numbers appear first</small>
          </div>

          <div class="field">
            <div class="checkbox-field">
              <Checkbox 
                v-model="formData.is_active" 
                inputId="is_active" 
                :binary="true" 
              />
              <label for="is_active" class="checkbox-label">{{ $t('menuMgmt.menuActive') }}</label>
            </div>
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
          @click="saveMenu"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>

    <!-- Permissions Dialog -->
    <Dialog 
      v-model:visible="permissionsDialogVisible" 
      :header="$t('role.managePermissions')"
      :modal="true"
      :style="{ width: '600px', maxHeight: '80vh' }"
    >
      <div class="permissions-content">
        <div class="permissions-header">
          <h3>{{ selectedMenu?.title }}</h3>
          <p class="text-muted">Select permissions required to access this menu</p>
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
      :header="$t('menuMgmt.deleteMenu')"
      :modal="true"
      :style="{ width: '450px' }"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
        <p>{{ $t('messages.confirmDelete', { item: menuToDelete?.title }) }}</p>
        <p v-if="menuToDelete?.children && menuToDelete.children.length > 0" class="warning-text">
          This menu has <strong>{{ menuToDelete.children.length }} child menu(s)</strong> that will also be deleted.
        </p>
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
          @click="deleteMenu"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { usePermission } from '@/composables/usePermission'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import draggable from 'vuedraggable'
import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import ProgressSpinner from 'primevue/progressspinner'
import IconPicker from '@/components/IconPicker.vue'

const toast = useToast()
const { can } = usePermission()
const authStore = useAuthStore()
const { t } = useI18n()

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.menuManagement') }
])

// Data
const menus = ref([])
const permissions = ref([])
const loading = ref(false)
const saving = ref(false)
const savingPermissions = ref(false)
const deleting = ref(false)
const processingOrder = ref(false)
const dialogVisible = ref(false)
const permissionsDialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const dialogMode = ref('create')
const menuToDelete = ref(null)
const selectedMenu = ref(null)
const selectedPermissions = ref([])
const permissionSearch = ref('')

// Computed
const isProcessing = computed(() => {
  return processingOrder.value || saving.value || savingPermissions.value || deleting.value
})

const loadingMessage = computed(() => {
  return t('common.loading')
})

const formData = ref({
  id: null,
  name: '',
  title: '',
  icon: '',
  route: '',
  url: '',
  parent_id: null,
  order: 0,
  is_active: true
})

const errors = ref({})

// Computed
const parentMenuOptions = computed(() => {
  // Only show root menus as parent options
  return menus.value.filter(m => !m.parent_id && m.id !== formData.value.id)
})

const groupedPermissions = computed(() => {
  if (!Array.isArray(permissions.value)) {
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
const fetchMenus = async () => {
  loading.value = true
  try {
    const response = await api.get('/admin/menus')
    menus.value = response.data
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to fetch menus',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const response = await api.get('/admin/permissions')
    permissions.value = response.data.permissions || []
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
  }
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  formData.value = {
    id: null,
    name: '',
    title: '',
    icon: '',
    route: '',
    url: '',
    parent_id: null,
    order: 0,
    is_active: true
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (menu) => {
  dialogMode.value = 'edit'
  formData.value = {
    id: menu.id,
    name: menu.name,
    title: menu.title,
    icon: menu.icon || '',
    route: menu.route || '',
    url: menu.url || '',
    parent_id: menu.parent_id,
    order: menu.order,
    is_active: menu.is_active
  }
  errors.value = {}
  dialogVisible.value = true
}

const openPermissionsDialog = (menu) => {
  selectedMenu.value = menu
  selectedPermissions.value = menu.permissions?.map(p => p.id) || []
  permissionSearch.value = ''
  permissionsDialogVisible.value = true
}

const validateForm = () => {
  errors.value = {}
  
  if (!formData.value.title) {
    errors.value.title = 'Title is required'
  }
  
  if (!formData.value.name) {
    errors.value.name = 'Name is required'
  }
  
  return Object.keys(errors.value).length === 0
}

const saveMenu = async () => {
  if (!validateForm()) return
  
  saving.value = true
  try {
    if (dialogMode.value === 'create') {
      await api.post('/admin/menus', formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.createdSuccessfully'),
        life: 3000
      })
    } else {
      await api.put(`/admin/menus/${formData.value.id}`, formData.value)
      toast.add({
        severity: 'success',
        summary: t('messages.success'),
        detail: t('messages.updatedSuccessfully'),
        life: 3000
      })
    }
    
    dialogVisible.value = false
    await fetchMenus()
    // Refresh sidebar menus
    await authStore.fetchMenus()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to save menu'
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
    await api.put(`/admin/menus/${selectedMenu.value.id}`, {
      name: selectedMenu.value.name,
      title: selectedMenu.value.title,
      icon: selectedMenu.value.icon,
      route: selectedMenu.value.route,
      url: selectedMenu.value.url,
      parent_id: selectedMenu.value.parent_id,
      order: selectedMenu.value.order,
      is_active: selectedMenu.value.is_active,
      permissions: selectedPermissions.value
    })
    
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.updatedSuccessfully'),
      life: 3000
    })
    
    permissionsDialogVisible.value = false
    await fetchMenus()
    // Refresh sidebar menus
    await authStore.fetchMenus()
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

const confirmDelete = (menu) => {
  menuToDelete.value = menu
  deleteDialogVisible.value = true
}

const deleteMenu = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/menus/${menuToDelete.value.id}`)
    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: t('messages.deletedSuccessfully'),
      life: 3000
    })
    
    deleteDialogVisible.value = false
    await fetchMenus()
    // Refresh sidebar menus
    await authStore.fetchMenus()
  } catch (error) {
    const message = error.response?.data?.message || 'Failed to delete menu'
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

const onDragEnd = async () => {
  // Update order for all root menus
  const updates = menus.value.map((menu, index) => ({
    id: menu.id,
    order: index
  }))

  processingOrder.value = true
  try {
    // Update each menu's order in background
    for (const update of updates) {
      const menu = menus.value.find(m => m.id === update.id)
      await api.put(`/admin/menus/${update.id}`, {
        name: menu.name,
        title: menu.title,
        icon: menu.icon,
        route: menu.route,
        url: menu.url,
        parent_id: menu.parent_id,
        order: update.order,
        is_active: menu.is_active
      })
    }

    // Refresh sidebar menus
    await authStore.fetchMenus()

    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: 'Menu order updated',
      life: 2000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to update menu order',
      life: 3000
    })
    // Refresh on error to restore correct order
    await fetchMenus()
  } finally {
    processingOrder.value = false
  }
}

const onChildDragEnd = async (parentMenu) => {
  // Update order for child menus
  const updates = parentMenu.children.map((child, index) => ({
    id: child.id,
    order: index
  }))

  processingOrder.value = true
  try {
    for (const update of updates) {
      const child = parentMenu.children.find(m => m.id === update.id)
      await api.put(`/admin/menus/${update.id}`, {
        name: child.name,
        title: child.title,
        icon: child.icon,
        route: child.route,
        url: child.url,
        parent_id: child.parent_id,
        order: update.order,
        is_active: child.is_active
      })
    }

    // Refresh sidebar menus
    await authStore.fetchMenus()

    toast.add({
      severity: 'success',
      summary: t('messages.success'),
      detail: 'Submenu order updated',
      life: 2000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: 'Failed to update submenu order',
      life: 3000
    })
    // Refresh on error to restore correct order
    await fetchMenus()
  } finally {
    processingOrder.value = false
  }
}

// Lifecycle
onMounted(() => {
  // Reset all loading states
  saving.value = false
  savingPermissions.value = false
  deleting.value = false
  processingOrder.value = false
  
  fetchMenus()
  fetchPermissions()
})
</script>

<style scoped>
.mb-4 {
  margin-bottom: 1rem;
}

.view-container {
  max-width: 1400px;
  margin: 0 auto;
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

.menu-table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.menu-list {
  background: #ffffff;
}

.menu-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  transition: all 0.2s;
  background: #ffffff;
}

.menu-row:last-child {
  border-bottom: none;
}

.menu-row:hover {
  background: #f9fafb;
}

.menu-row-child {
  background: #f9fafb;
  border-left: 3px solid var(--sage-primary);
}

.menu-row-child:hover {
  background: #f3f4f6;
}

.menu-inactive {
  opacity: 0.6;
}

.menu-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.drag-handle {
  cursor: move;
  color: #9ca3af;
  padding: 0.5rem;
  transition: color 0.2s;
}

.drag-handle:hover {
  color: var(--sage-primary);
}

.drag-handle i {
  font-size: 1.25rem;
}

.child-indicator {
  width: 2rem;
  height: 2px;
  background: var(--sage-primary);
  margin-left: 1rem;
}

.menu-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.menu-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.menu-icon {
  color: var(--sage-primary);
  font-size: 1.25rem;
  width: 1.5rem;
  text-align: center;
}

.menu-title {
  font-weight: 600;
  color: #1f2937;
  font-size: 1rem;
}

.menu-name {
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  color: #6b7280;
}

.menu-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.menu-route {
  color: #6b7280;
  font-size: 0.875rem;
}

.menu-actions {
  display: flex;
  gap: 0.25rem;
}

.child-menu-container {
  background: #f9fafb;
}

.child-menu-list {
  background: #f9fafb;
}

.ghost {
  opacity: 0.5;
  background: var(--sage-bg);
}

.ml-2 {
  margin-left: 0.5rem;
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

.icon-input-wrapper {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.icon-input-wrapper input {
  flex: 1;
}

.icon-preview {
  font-size: 1.5rem;
  color: var(--sage-primary);
  width: 2rem;
  text-align: center;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-top: 2rem;
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
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
