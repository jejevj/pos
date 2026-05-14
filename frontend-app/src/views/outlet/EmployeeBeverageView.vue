<template>
  <div class="employee-beverage-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('employeeBeverage.title') }}</h2>
        <p class="text-muted">{{ $t('employeeBeverage.subtitle') }}</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff;">
          <i class="pi pi-calendar" style="color: #3b82f6;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ statistics.claims_today || 0 }}</div>
          <div class="stat-label">{{ $t('employeeBeverage.claimsToday') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f0fdf4;">
          <i class="pi pi-users" style="color: #22c55e;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ statistics.active_employees_today || 0 }}</div>
          <div class="stat-label">{{ $t('employeeBeverage.activeEmployees') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7;">
          <i class="pi pi-box" style="color: #f59e0b;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ settings.daily_quota || 0 }}</div>
          <div class="stat-label">{{ $t('employeeBeverage.dailyQuota') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fce7f3;">
          <i class="pi pi-list" style="color: #ec4899;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ allowedBeverages.length }}</div>
          <div class="stat-label">{{ $t('employeeBeverage.allowedBeverages') }}</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button class="tab" :class="{ active: activeTab === 'claim' }" @click="activeTab = 'claim'">
          <i class="pi pi-shopping-cart"></i>
          {{ $t('employeeBeverage.claimBeverage') }}
        </button>
        <button class="tab" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
          <i class="pi pi-history"></i>
          {{ $t('employeeBeverage.claimHistory') }}
        </button>
        <button class="tab" :class="{ active: activeTab === 'settings' }" @click="activeTab = 'settings'">
          <i class="pi pi-cog"></i>
          {{ $t('employeeBeverage.settings') }}
        </button>
      </div>
    </div>

    <!-- Claim Tab -->
    <div v-show="activeTab === 'claim'" class="tab-content">
      <div class="claim-section">
        <div class="employee-selector">
          <label>{{ $t('employeeBeverage.selectEmployee') }}</label>
          <Select 
            v-model="selectedEmployee" 
            :options="employees" 
            optionLabel="nama" 
            optionValue="id"
            :placeholder="$t('employeeBeverage.selectEmployee')"
            filter
            fluid
            @change="onEmployeeChange"
          />
        </div>

        <div v-if="selectedEmployee && quotaStatus" class="quota-status">
          <div class="quota-card" :class="{ 'quota-full': !quotaStatus.can_claim }">
            <div class="quota-info">
              <i class="pi pi-check-circle" v-if="quotaStatus.can_claim"></i>
              <i class="pi pi-times-circle" v-else></i>
              <div>
                <div class="quota-text">
                  {{ $t('employeeBeverage.quotaUsed') }}: {{ quotaStatus.claimed }} / {{ quotaStatus.daily_quota }}
                </div>
                <div class="quota-remaining">
                  {{ $t('employeeBeverage.remaining') }}: {{ quotaStatus.remaining }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedEmployee && quotaStatus?.can_claim" class="beverages-grid">
          <Card 
            v-for="beverage in allowedBeverages.filter(b => b.is_active)" 
            :key="beverage.id"
            class="beverage-card"
            @click="selectBeverage(beverage)"
          >
            <template #header>
              <div class="beverage-image">
                <img v-if="beverage.menu_image" :src="beverage.menu_image" :alt="beverage.menu_name" />
                <div v-else class="no-image">
                  <i class="pi pi-image"></i>
                </div>
              </div>
            </template>
            <template #title>
              {{ beverage.menu_name }}
            </template>
            <template #subtitle>
              <Tag :value="beverage.category_name" severity="info" />
            </template>
            <template #footer>
              <Button 
                :label="$t('employeeBeverage.claim')" 
                icon="pi pi-check" 
                fluid
                @click.stop="confirmClaim(beverage)"
              />
            </template>
          </Card>
        </div>

        <div v-else-if="selectedEmployee && !quotaStatus?.can_claim" class="empty-state">
          <i class="pi pi-ban"></i>
          <p>{{ $t('employeeBeverage.quotaReached') }}</p>
        </div>

        <div v-else class="empty-state">
          <i class="pi pi-user"></i>
          <p>{{ $t('employeeBeverage.selectEmployeeFirst') }}</p>
        </div>
      </div>
    </div>

    <!-- History Tab -->
    <div v-show="activeTab === 'history'" class="tab-content">
      <div class="filter-bar">
        <div class="filter-group">
          <label class="filter-label"><i class="pi pi-calendar" /> {{ $t('common.date') }}</label>
          <DatePicker v-model="filterDate" dateFormat="yy-mm-dd" :placeholder="$t('common.selectDate')" showIcon style="width:180px" />
        </div>
        <div class="filter-group">
          <label class="filter-label"><i class="pi pi-user" /> {{ $t('employeeBeverage.employee') }}</label>
          <Select 
            v-model="filterEmployeeId" 
            :options="employees" 
            optionLabel="nama" 
            optionValue="id"
            :placeholder="$t('employeeBeverage.allEmployees')"
            showClear
            filter
            style="width:200px"
          />
        </div>
        <div class="filter-group" style="justify-content:flex-end">
          <Button :label="$t('common.filter')" icon="pi pi-filter" @click="fetchClaims" />
        </div>
      </div>

      <DataTable :value="claims" :loading="loading" paginator :rows="15" stripedRows>
        <Column field="employee_name" :header="$t('employeeBeverage.employee')" sortable>
          <template #body="{ data }">
            <div class="employee-cell">
              <Avatar icon="pi pi-user" shape="circle" />
              <span>{{ data.employee_name }}</span>
            </div>
          </template>
        </Column>
        <Column field="menu_name" :header="$t('employeeBeverage.beverage')" sortable>
          <template #body="{ data }">
            <div class="menu-cell">
              <img v-if="data.menu_image" :src="data.menu_image" :alt="data.menu_name" class="menu-thumb" />
              <span>{{ data.menu_name }}</span>
            </div>
          </template>
        </Column>
        <Column field="claimed_at" :header="$t('employeeBeverage.claimedAt')" sortable>
          <template #body="{ data }">
            {{ formatDateTime(data.claimed_at) }}
          </template>
        </Column>
        <Column field="notes" :header="$t('common.notes')" />
      </DataTable>
    </div>

    <!-- Settings Tab -->
    <div v-show="activeTab === 'settings'" class="tab-content">
      <div class="settings-section">
        <!-- General Settings -->
        <Card>
          <template #title>{{ $t('employeeBeverage.generalSettings') }}</template>
          <template #content>
            <div class="form-grid">
              <div class="form-field">
                <label>{{ $t('employeeBeverage.dailyQuota') }}</label>
                <InputNumber v-model="settings.daily_quota" :min="0" :max="10" fluid />
                <small>{{ $t('employeeBeverage.dailyQuotaHint') }}</small>
              </div>
              <div class="form-field">
                <label>{{ $t('common.status') }}</label>
                <div class="toggle-row">
                  <ToggleSwitch v-model="settings.is_active" />
                  <span>{{ settings.is_active ? $t('common.active') : $t('common.inactive') }}</span>
                </div>
              </div>
              <div class="form-field full-width">
                <label>{{ $t('common.notes') }}</label>
                <Textarea v-model="settings.notes" rows="3" fluid />
              </div>
            </div>
            <div class="form-actions">
              <Button :label="$t('common.save')" icon="pi pi-save" @click="saveSettings" :loading="saving" />
            </div>
          </template>
        </Card>

        <!-- Allowed Beverages -->
        <Card class="mt-3">
          <template #title>
            <div class="card-title-row">
              <span>{{ $t('employeeBeverage.allowedBeveragesList') }}</span>
              <Button 
                :label="$t('employeeBeverage.addBeverage')" 
                icon="pi pi-plus" 
                size="small"
                @click="openAddBeverageDialog" 
              />
            </div>
          </template>
          <template #content>
            <DataTable :value="allowedBeverages" :loading="loading" stripedRows>
              <Column field="menu_name" :header="$t('employeeBeverage.beverage')">
                <template #body="{ data }">
                  <div class="menu-cell">
                    <img v-if="data.menu_image" :src="data.menu_image" :alt="data.menu_name" class="menu-thumb" />
                    <span>{{ data.menu_name }}</span>
                  </div>
                </template>
              </Column>
              <Column field="category_name" :header="$t('menu.category')">
                <template #body="{ data }">
                  <Tag :value="data.category_name" severity="info" />
                </template>
              </Column>
              <Column field="is_active" :header="$t('common.status')">
                <template #body="{ data }">
                  <Tag 
                    :value="data.is_active ? $t('common.active') : $t('common.inactive')" 
                    :severity="data.is_active ? 'success' : 'secondary'" 
                  />
                </template>
              </Column>
              <Column :header="$t('common.actions')" style="width: 100px">
                <template #body="{ data }">
                  <Button 
                    icon="pi pi-trash" 
                    text 
                    rounded 
                    size="small" 
                    severity="danger"
                    @click="confirmRemoveBeverage(data)" 
                    v-tooltip.top="$t('common.delete')"
                  />
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>
    </div>

    <!-- Add Beverage Dialog -->
    <Dialog v-model:visible="addBeverageDialogVisible" :header="$t('employeeBeverage.addBeverage')" modal :style="{ width: '600px' }">
      <div class="form-field">
        <label>{{ $t('employeeBeverage.selectBeverage') }}</label>
        <Select 
          v-model="selectedMenuId" 
          :options="availableMenus" 
          optionLabel="nama" 
          optionValue="id"
          :placeholder="$t('employeeBeverage.selectBeverage')"
          filter
          fluid
        >
          <template #option="{ option }">
            <div class="menu-option">
              <img v-if="option.foto" :src="option.foto" :alt="option.nama" class="menu-thumb-small" />
              <div>
                <div>{{ option.nama }}</div>
                <small>{{ option.kategori_nama }}</small>
              </div>
            </div>
          </template>
        </Select>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="addBeverageDialogVisible = false" />
        <Button :label="$t('common.add')" @click="addBeverage" :loading="saving" />
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
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Card from 'primevue/card'
import ToggleSwitch from 'primevue/toggleswitch'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Avatar from 'primevue/avatar'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const activeTab = ref('claim')
const loading = ref(false)
const saving = ref(false)

const settings = ref({
  daily_quota: 1,
  is_active: true,
  reset_time: '00:00:00',
  notes: ''
})

const employees = ref([])
const selectedEmployee = ref(null)
const quotaStatus = ref(null)
const allowedBeverages = ref([])
const claims = ref([])
const statistics = ref({})
const availableMenus = ref([])

const filterDate = ref(new Date())
const filterEmployeeId = ref(null)

const addBeverageDialogVisible = ref(false)
const selectedMenuId = ref(null)

const fetchSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/employee-beverages/settings`)
    settings.value = response.data
  } catch (error) {
    console.error('Failed to fetch settings:', error)
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    await api.put(`/outlets/${outletId}/employee-beverages/settings`, settings.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('employeeBeverage.settingsSaved'), life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const fetchEmployees = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/users`)
    // Map outlet_users to employee format
    const users = response.data.users || []
    employees.value = users.map(user => ({
      id: user.id,
      nama: user.name
    }))
  } catch (error) {
    console.error('Failed to fetch employees:', error)
  }
}

const fetchAllowedBeverages = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/employee-beverages/allowed`)
    allowedBeverages.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch allowed beverages:', error)
  } finally {
    loading.value = false
  }
}

const fetchAvailableMenus = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/menu`)
    // Response is direct array, not wrapped in 'menu' key
    const menus = Array.isArray(response.data) ? response.data : []
    availableMenus.value = menus.map(menu => ({
      id: menu.id,
      nama: menu.nama,
      foto: menu.gambar_url,
      kategori_nama: menu.kategori?.nama || 'Uncategorized'
    }))
  } catch (error) {
    console.error('Failed to fetch menus:', error)
  }
}

const fetchClaims = async () => {
  loading.value = true
  try {
    const params = {
      date: filterDate.value ? formatDate(filterDate.value) : undefined,
      user_id: filterEmployeeId.value || undefined
    }
    const response = await api.get(`/outlets/${outletId}/employee-beverages/claims`, { params })
    claims.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch claims:', error)
  } finally {
    loading.value = false
  }
}

const fetchStatistics = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/employee-beverages/statistics`)
    statistics.value = response.data || {}
  } catch (error) {
    console.error('Failed to fetch statistics:', error)
  }
}

const onEmployeeChange = async () => {
  if (!selectedEmployee.value) {
    quotaStatus.value = null
    return
  }
  
  try {
    const response = await api.get(`/outlets/${outletId}/employee-beverages/quota/${selectedEmployee.value}`)
    quotaStatus.value = response.data
  } catch (error) {
    console.error('Failed to fetch quota status:', error)
  }
}

const confirmClaim = (beverage) => {
  confirm.require({
    message: `${t('employeeBeverage.confirmClaim')} ${beverage.menu_name}?`,
    header: t('employeeBeverage.claimBeverage'),
    icon: 'pi pi-question-circle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => claimBeverage(beverage)
  })
}

const claimBeverage = async (beverage) => {
  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/employee-beverages/claim`, {
      user_id: selectedEmployee.value,
      menu_id: beverage.menu_id
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('employeeBeverage.claimSuccess'), life: 3000 })
    onEmployeeChange()
    fetchStatistics()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const openAddBeverageDialog = () => {
  selectedMenuId.value = null
  addBeverageDialogVisible.value = true
}

const addBeverage = async () => {
  if (!selectedMenuId.value) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('employeeBeverage.selectBeverageFirst'), life: 3000 })
    return
  }

  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/employee-beverages/allowed`, {
      menu_id: selectedMenuId.value
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('employeeBeverage.beverageAdded'), life: 3000 })
    addBeverageDialogVisible.value = false
    fetchAllowedBeverages()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmRemoveBeverage = (beverage) => {
  confirm.require({
    message: `${t('employeeBeverage.removeBeverageConfirm')} ${beverage.menu_name}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => removeBeverage(beverage)
  })
}

const removeBeverage = async (beverage) => {
  try {
    await api.delete(`/outlets/${outletId}/employee-beverages/allowed/${beverage.id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('employeeBeverage.beverageRemoved'), life: 3000 })
    fetchAllowedBeverages()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const formatDate = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('id-ID', { 
    day: '2-digit', 
    month: 'short', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  fetchSettings()
  fetchEmployees()
  fetchAllowedBeverages()
  fetchStatistics()
  fetchAvailableMenus()
  fetchClaims()
})
</script>

<style scoped>
.employee-beverage-view {
  padding: 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
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

.tab:hover { color: #3b82f6; }
.tab.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-content {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
}

.claim-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.employee-selector label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.quota-status {
  display: flex;
  justify-content: center;
}

.quota-card {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 2px solid #22c55e;
  border-radius: 12px;
  padding: 1.5rem;
  max-width: 400px;
  width: 100%;
}

.quota-card.quota-full {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border-color: #ef4444;
}

.quota-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.quota-info i {
  font-size: 2rem;
  color: #22c55e;
}

.quota-card.quota-full .quota-info i {
  color: #ef4444;
}

.quota-text {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
}

.quota-remaining {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.beverages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1.5rem;
}

.beverage-card {
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid transparent;
}

.beverage-card:hover {
  border-color: #3b82f6;
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.beverage-image {
  height: 200px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
}

.beverage-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-image {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  font-size: 3rem;
  color: #9ca3af;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: #9ca3af;
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.filter-bar { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 0.3rem; }

.employee-cell, .menu-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.menu-thumb, .menu-thumb-small {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  object-fit: cover;
}

.menu-thumb-small {
  width: 32px;
  height: 32px;
}

.settings-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

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
.form-field small { color: #6b7280; font-size: 0.75rem; }

.toggle-row { display: flex; align-items: center; gap: 0.75rem; }

.form-actions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
}

.card-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.mt-3 { margin-top: 1.5rem; }

.menu-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .beverages-grid {
    grid-template-columns: 1fr;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
