<template>
  <div class="member-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('member.title') }}</h2>
        <p class="text-muted">{{ $t('member.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('member.settings')" icon="pi pi-cog" outlined @click="showSettingsDialog = true" />
        <Button :label="$t('member.addMember')" icon="pi pi-plus" @click="openCreateDialog" />
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff;">
          <i class="pi pi-users" style="color: #3b82f6;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ members.length }}</div>
          <div class="stat-label">{{ $t('member.totalMembers') }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7;">
          <i class="pi pi-star" style="color: #f59e0b;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ members.filter(m => m.tier === 'Gold').length }}</div>
          <div class="stat-label">Gold</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #e0f2fe;">
          <i class="pi pi-star-fill" style="color: #0ea5e9;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ members.filter(m => m.tier === 'Platinum').length }}</div>
          <div class="stat-label">Platinum</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f0fdf4;">
          <i class="pi pi-bolt" style="color: #22c55e;"></i>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ totalPoints.toLocaleString('id-ID') }}</div>
          <div class="stat-label">{{ $t('member.totalPoints') }}</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <IconField>
        <InputIcon><i class="pi pi-search" /></InputIcon>
        <InputText v-model="searchQuery" :placeholder="$t('member.searchMember')" />
      </IconField>
      <Select v-model="filterTier" :options="tierOptions" optionLabel="label" optionValue="value" 
              :placeholder="$t('member.allTiers')" showClear />
      <Select v-model="filterActive" :options="activeOptions" optionLabel="label" optionValue="value" 
              :placeholder="$t('common.allStatuses')" showClear />
    </div>

    <!-- Table -->
    <DataTable :value="filteredMembers" :loading="loading" paginator :rows="15" 
               :rowsPerPageOptions="[10, 15, 25]" stripedRows>
      <Column field="card_number" :header="$t('member.cardNumber')" sortable />
      <Column field="nama" :header="$t('member.name')" sortable />
      <Column field="phone" :header="$t('member.phone')" />
      <Column field="email" :header="$t('member.email')" />
      <Column field="tier" :header="$t('member.tier')" sortable>
        <template #body="{ data }">
          <Tag :value="data.tier" :severity="getTierSeverity(data.tier)" />
        </template>
      </Column>
      <Column field="points" :header="$t('member.points')" sortable>
        <template #body="{ data }">
          <span class="points-badge">{{ data.points.toLocaleString('id-ID') }}</span>
        </template>
      </Column>
      <Column field="joined_at" :header="$t('member.joinedAt')" sortable>
        <template #body="{ data }">
          {{ formatDate(data.joined_at) }}
        </template>
      </Column>
      <Column field="is_active" :header="$t('common.status')">
        <template #body="{ data }">
          <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')" 
               :severity="data.is_active ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column :header="$t('common.actions')" style="width: 160px">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-bolt" text rounded size="small" severity="warn" 
                    @click="openPointsDialog(data)" v-tooltip.top="$t('member.adjustPoints')" />
            <Button icon="pi pi-history" text rounded size="small" severity="info" 
                    @click="openTransactionsDialog(data)" v-tooltip.top="$t('member.pointHistory')" />
            <Button icon="pi pi-pencil" text rounded size="small" 
                    @click="openEditDialog(data)" v-tooltip.top="$t('common.edit')" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" 
                    @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="formDialogVisible" :header="editingMember ? $t('member.editMember') : $t('member.addMember')" 
            modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('member.name') }} *</label>
          <InputText v-model="form.nama" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('member.phone') }}</label>
          <InputText v-model="form.phone" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('member.email') }}</label>
          <InputText v-model="form.email" type="email" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('member.birthDate') }}</label>
          <DatePicker v-model="form.tanggal_lahir" dateFormat="yy-mm-dd" fluid showIcon />
        </div>
        <div class="form-field">
          <label>{{ $t('member.gender') }}</label>
          <Select v-model="form.jenis_kelamin" :options="genderOptions" optionLabel="label" optionValue="value" 
                  :placeholder="$t('member.selectGender')" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('member.address') }}</label>
          <Textarea v-model="form.alamat" rows="2" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('member.password') }} {{ !editingMember ? '*' : '' }}</label>
          <Password v-model="form.password" :placeholder="editingMember ? $t('member.leaveBlankToKeep') : ''" 
                    toggleMask fluid :feedback="false" />
        </div>
        <div class="form-field">
          <label>{{ $t('member.confirmPassword') }}</label>
          <Password v-model="form.password_confirmation" :placeholder="editingMember ? $t('member.leaveBlankToKeep') : ''" 
                    toggleMask fluid :feedback="false" />
        </div>
        <div v-if="editingMember" class="form-field full-width">
          <label>{{ $t('common.status') }}</label>
          <div class="toggle-row">
            <ToggleSwitch v-model="form.is_active" />
            <span>{{ form.is_active ? $t('common.active') : $t('common.inactive') }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="formDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveMember" :loading="saving" />
      </template>
    </Dialog>

    <!-- Adjust Points Dialog -->
    <Dialog v-model:visible="pointsDialogVisible" :header="$t('member.adjustPoints')" modal :style="{ width: '400px' }">
      <div v-if="selectedMember" class="points-form">
        <div class="member-summary">
          <div class="member-summary-name">{{ selectedMember.nama }}</div>
          <div class="member-summary-points">
            <i class="pi pi-bolt" style="color: #f59e0b;"></i>
            <span>{{ selectedMember.points.toLocaleString('id-ID') }} {{ $t('member.points') }}</span>
          </div>
        </div>
        <div class="form-field">
          <label>{{ $t('member.adjustType') }}</label>
          <SelectButton v-model="pointsForm.type" :options="pointsTypeOptions" optionLabel="label" optionValue="value" />
        </div>
        <div class="form-field">
          <label>{{ $t('member.pointAmount') }}</label>
          <InputNumber v-model="pointsForm.amount" :min="1" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('member.description') }} *</label>
          <InputText v-model="pointsForm.description" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="pointsDialogVisible = false" />
        <Button :label="$t('common.save')" @click="adjustPoints" :loading="saving" />
      </template>
    </Dialog>

    <!-- Point Transactions Dialog -->
    <Dialog v-model:visible="transactionsDialogVisible" :header="$t('member.pointHistory')" modal :style="{ width: '600px' }">
      <div v-if="selectedMember" class="transactions-header">
        <span>{{ selectedMember.nama }}</span>
        <Tag :value="`${selectedMember.points.toLocaleString('id-ID')} pts`" severity="warn" />
      </div>
      <DataTable :value="pointTransactions" :loading="loadingTransactions" :rows="10" paginator>
        <Column field="type" :header="$t('member.type')">
          <template #body="{ data }">
            <Tag :value="data.type === 'earn' ? $t('member.earn') : $t('member.redeem')" 
                 :severity="data.type === 'earn' ? 'success' : 'danger'" size="small" />
          </template>
        </Column>
        <Column field="amount" :header="$t('member.points')">
          <template #body="{ data }">
            <span :class="data.type === 'earn' ? 'earn-amount' : 'redeem-amount'">
              {{ data.type === 'earn' ? '+' : '-' }}{{ data.amount.toLocaleString('id-ID') }}
            </span>
          </template>
        </Column>
        <Column field="balance_after" :header="$t('member.balance')">
          <template #body="{ data }">
            {{ data.balance_after.toLocaleString('id-ID') }}
          </template>
        </Column>
        <Column field="description" :header="$t('member.description')" />
        <Column field="created_at" :header="$t('common.date')">
          <template #body="{ data }">
            {{ formatDate(data.created_at) }}
          </template>
        </Column>
      </DataTable>
      <template #footer>
        <Button :label="$t('common.close')" text @click="transactionsDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Membership Settings Dialog -->
    <Dialog v-model:visible="showSettingsDialog" :header="$t('member.membershipSettings')" modal :style="{ width: '600px' }">
      <div v-if="settings" class="settings-form">
        <div class="settings-section">
          <h4>{{ $t('member.pointSettings') }}</h4>
          <div class="form-grid">
            <div class="form-field">
              <label>{{ $t('member.pointConversionRate') }}</label>
              <div class="conversion-hint">Rp <InputNumber v-model="settings.point_conversion_rate" :min="1" /> = 1 poin</div>
            </div>
            <div class="form-field">
              <label>{{ $t('member.minTransactionForPoints') }}</label>
              <InputNumber v-model="settings.min_transaction_for_points" :min="0" prefix="Rp " fluid />
            </div>
            <div class="form-field">
              <label>{{ $t('member.pointExpiryDays') }}</label>
              <InputNumber v-model="settings.point_expiry_days" :min="1" fluid />
              <small class="field-hint">{{ $t('member.noExpiry') }}</small>
            </div>
          </div>
        </div>

        <div class="settings-section">
          <div class="section-header">
            <h4>{{ $t('member.tiers') }}</h4>
            <Button :label="$t('member.addTier')" icon="pi pi-plus" size="small" text @click="addTier" />
          </div>
          <div v-for="(tier, index) in settings.tiers" :key="index" class="tier-row">
            <InputText v-model="tier.name" :placeholder="$t('member.tierName')" style="width: 120px" />
            <div class="tier-field">
              <label>Min Poin</label>
              <InputNumber v-model="tier.min_points" :min="0" style="width: 120px" />
            </div>
            <div class="tier-field">
              <label>Diskon %</label>
              <InputNumber v-model="tier.discount_percentage" :min="0" :max="100" suffix="%" style="width: 100px" />
            </div>
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" 
                    @click="removeTier(index)" :disabled="settings.tiers.length <= 1" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="showSettingsDialog = false" />
        <Button :label="$t('common.save')" @click="saveSettings" :loading="savingSettings" />
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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const members = ref([])
const loading = ref(false)
const saving = ref(false)
const savingSettings = ref(false)
const loadingTransactions = ref(false)
const searchQuery = ref('')
const filterTier = ref(null)
const filterActive = ref(null)
const formDialogVisible = ref(false)
const pointsDialogVisible = ref(false)
const transactionsDialogVisible = ref(false)
const showSettingsDialog = ref(false)
const editingMember = ref(null)
const selectedMember = ref(null)
const pointTransactions = ref([])
const settings = ref(null)

const form = ref({
  nama: '', phone: '', email: '', password: '', password_confirmation: '',
  tanggal_lahir: null, jenis_kelamin: null, alamat: '', is_active: true
})

const pointsForm = ref({ type: 'add', amount: null, description: '' })

const tierOptions = [
  { label: 'Silver', value: 'Silver' },
  { label: 'Gold', value: 'Gold' },
  { label: 'Platinum', value: 'Platinum' },
]

const activeOptions = computed(() => [
  { label: t('common.active'), value: true },
  { label: t('common.inactive'), value: false },
])

const genderOptions = [
  { label: 'Laki-laki', value: 'Laki-laki' },
  { label: 'Perempuan', value: 'Perempuan' },
]

const pointsTypeOptions = computed(() => [
  { label: t('member.add'), value: 'add' },
  { label: t('member.subtract'), value: 'subtract' },
])

const totalPoints = computed(() => members.value.reduce((sum, m) => sum + (m.points || 0), 0))

const filteredMembers = computed(() => {
  let result = members.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(m =>
      m.nama?.toLowerCase().includes(q) ||
      m.phone?.includes(q) ||
      m.card_number?.toLowerCase().includes(q) ||
      m.email?.toLowerCase().includes(q)
    )
  }
  if (filterTier.value) result = result.filter(m => m.tier === filterTier.value)
  if (filterActive.value !== null) result = result.filter(m => m.is_active === filterActive.value)
  return result
})

const fetchMembers = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/members`)
    members.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/membership-settings`)
    settings.value = response.data
  } catch (error) {
    console.error('Failed to fetch settings:', error)
  }
}

const openCreateDialog = () => {
  editingMember.value = null
  form.value = { 
    nama: '', phone: '', email: '', password: '', password_confirmation: '',
    tanggal_lahir: null, jenis_kelamin: null, alamat: '', is_active: true 
  }
  formDialogVisible.value = true
}

const openEditDialog = (member) => {
  editingMember.value = member
  form.value = { 
    ...member, 
    password: '', 
    password_confirmation: '',
    tanggal_lahir: member.tanggal_lahir ? new Date(member.tanggal_lahir) : null 
  }
  formDialogVisible.value = true
}

const saveMember = async () => {
  if (!form.value.nama) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('member.nameRequired'), life: 3000 })
    return
  }
  
  // Validate password for new members
  if (!editingMember.value && !form.value.password) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('member.passwordRequired'), life: 3000 })
    return
  }
  
  // Validate password confirmation
  if (form.value.password && form.value.password !== form.value.password_confirmation) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('member.passwordMismatch'), life: 3000 })
    return
  }
  
  // Validate password length
  if (form.value.password && form.value.password.length < 6) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('member.passwordMinLength'), life: 3000 })
    return
  }
  
  saving.value = true
  try {
    const payload = {
      ...form.value,
      tanggal_lahir: form.value.tanggal_lahir ? new Date(form.value.tanggal_lahir).toISOString().split('T')[0] : null
    }
    
    // Remove password fields if empty (for edit)
    if (editingMember.value && !form.value.password) {
      delete payload.password
      delete payload.password_confirmation
    }
    
    if (editingMember.value) {
      await api.put(`/outlets/${outletId}/members/${editingMember.value.id}`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('member.memberUpdated'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/members`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('member.memberCreated'), life: 3000 })
    }
    formDialogVisible.value = false
    fetchMembers()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (member) => {
  confirm.require({
    message: `${t('member.deleteConfirm')} ${member.nama}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/members/${member.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('member.memberDeleted'), life: 3000 })
        fetchMembers()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
      }
    }
  })
}

const openPointsDialog = (member) => {
  selectedMember.value = member
  pointsForm.value = { type: 'add', amount: null, description: '' }
  pointsDialogVisible.value = true
}

const adjustPoints = async () => {
  if (!pointsForm.value.amount || !pointsForm.value.description) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('member.fillAllFields'), life: 3000 })
    return
  }
  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/members/${selectedMember.value.id}/adjust-points`, pointsForm.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('member.pointsAdjusted'), life: 3000 })
    pointsDialogVisible.value = false
    fetchMembers()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const openTransactionsDialog = async (member) => {
  selectedMember.value = member
  transactionsDialogVisible.value = true
  loadingTransactions.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/members/${member.id}/transactions`)
    pointTransactions.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loadingTransactions.value = false
  }
}

const saveSettings = async () => {
  savingSettings.value = true
  try {
    await api.put(`/outlets/${outletId}/membership-settings`, settings.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('member.settingsSaved'), life: 3000 })
    showSettingsDialog.value = false
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    savingSettings.value = false
  }
}

const addTier = () => {
  settings.value.tiers.push({ name: '', min_points: 0, discount_percentage: 0 })
}

const removeTier = (index) => {
  settings.value.tiers.splice(index, 1)
}

const getTierSeverity = (tier) => {
  const map = { Silver: 'secondary', Gold: 'warn', Platinum: 'info' }
  return map[tier] || 'secondary'
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(() => {
  fetchMembers()
  fetchSettings()
})
</script>

<style scoped>
.member-view {
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

.filter-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.action-buttons { display: flex; gap: 0.25rem; }

.points-badge {
  font-weight: 700;
  color: #f59e0b;
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

.toggle-row { display: flex; align-items: center; gap: 0.75rem; }

.member-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background: #fffbeb;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.member-summary-name { font-weight: 700; font-size: 1.1rem; }
.member-summary-points { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; }

.transactions-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  font-weight: 600;
}

.earn-amount { color: #22c55e; font-weight: 700; }
.redeem-amount { color: #ef4444; font-weight: 700; }

.settings-form { display: flex; flex-direction: column; gap: 1.5rem; }
.settings-section { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; }
.settings-section h4 { margin: 0 0 1rem 0; }

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-header h4 { margin: 0; }

.tier-row {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f3f4f6;
}

.tier-row:last-child { border-bottom: none; margin-bottom: 0; }

.tier-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #6b7280;
}

.conversion-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.points-form { display: flex; flex-direction: column; gap: 1rem; }
.points-redeem-input { display: flex; align-items: center; gap: 0.75rem; }

.field-hint {
  color: #9ca3af;
  font-size: 0.75rem;
}
</style>
