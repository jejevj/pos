<template>
  <div class="expense-view">
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <div class="page-header">
      <div>
        <h2>{{ $t('expense.title') }}</h2>
        <p class="text-muted">{{ $t('expense.subtitle') }}</p>
      </div>
      <Button :label="$t('expense.addExpense')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <Card>
      <template #content>
        <DataTable :value="expenses" stripedRows showGridlines paginator :rows="10">
          <Column field="expense_code" :header="$t('expense.expenseCode')" sortable></Column>
          <Column field="expense_date" :header="$t('expense.expenseDate')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.expense_date) }}
            </template>
          </Column>
          <Column field="category" :header="$t('expense.category')"></Column>
          <Column field="description" :header="$t('expense.description')"></Column>
          <Column field="amount" :header="$t('expense.amount')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.amount) }}
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-eye" text rounded severity="info" @click="viewDetails(data)" v-tooltip.top="$t('common.view')" />
                <Button icon="pi pi-pencil" text rounded severity="warning" @click="openEditDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-wallet" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="dialogVisible" :header="dialogMode === 'create' ? $t('expense.addExpense') : $t('expense.editExpense')" modal :style="{ width: '600px' }">
      <div class="form-content">
        <div class="form-row">
          <div class="field">
            <label>{{ $t('expense.expenseDate') }} *</label>
            <DatePicker v-model="form.expense_date" dateFormat="yy-mm-dd" showIcon fluid />
          </div>
          <div class="field">
            <label>{{ $t('expense.category') }} *</label>
            <Select v-model="form.category" :options="categoryOptions" optionLabel="label" optionValue="value" :placeholder="$t('expense.selectCategory')" fluid editable />
          </div>
        </div>

        <div class="field">
          <label>{{ $t('expense.description') }} *</label>
          <Textarea v-model="form.description" rows="3" fluid />
        </div>

        <div class="form-row">
          <div class="field">
            <label>{{ $t('expense.amount') }} *</label>
            <InputNumber v-model="form.amount" mode="currency" currency="IDR" locale="id-ID" fluid />
          </div>
          <div class="field">
            <label>{{ $t('expense.paymentMethod') }}</label>
            <InputText v-model="form.payment_method" :placeholder="$t('expense.paymentMethod')" fluid />
          </div>
        </div>

        <div class="field">
          <label>{{ $t('expense.paymentProof') }}</label>
          <FileUpload mode="basic" accept="image/*" :maxFileSize="5000000" @select="onFileSelect" :auto="true" :chooseLabel="$t('expense.uploadProof')" />
        </div>

        <div class="field">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="form.notes" rows="2" fluid />
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="dialogMode === 'create' ? $t('common.create') : $t('common.update')" @click="saveExpense" :loading="saving" />
      </template>
    </Dialog>

    <!-- Details Dialog -->
    <Dialog v-model:visible="detailsDialogVisible" :header="$t('expense.expenseDetails')" modal :style="{ width: '600px' }">
      <div v-if="selectedExpense" class="details-content">
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ $t('expense.expenseCode') }}</span>
            <span class="detail-value">{{ selectedExpense.expense_code }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('expense.expenseDate') }}</span>
            <span class="detail-value">{{ formatDate(selectedExpense.expense_date) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('expense.category') }}</span>
            <span class="detail-value">{{ selectedExpense.category }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('expense.amount') }}</span>
            <span class="detail-value">{{ formatCurrency(selectedExpense.amount) }}</span>
          </div>
          <div class="detail-item full-width">
            <span class="detail-label">{{ $t('expense.description') }}</span>
            <span class="detail-value">{{ selectedExpense.description }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ $t('expense.paymentMethod') }}</span>
            <span class="detail-value">{{ selectedExpense.payment_method || '-' }}</span>
          </div>
          <div v-if="selectedExpense.notes" class="detail-item full-width">
            <span class="detail-label">{{ $t('common.notes') }}</span>
            <span class="detail-value">{{ selectedExpense.notes }}</span>
          </div>
        </div>

        <div v-if="selectedExpense.payment_proof_url" class="proof-section">
          <label>{{ $t('expense.paymentProof') }}</label>
          <Image :src="selectedExpense.payment_proof_url" alt="Payment Proof" width="300" preview />
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.close')" text @click="detailsDialogVisible = false" />
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
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Image from 'primevue/image'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const loading = ref(false)
const saving = ref(false)
const expenses = ref([])
const dialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const dialogMode = ref('create')
const selectedExpense = ref(null)

const form = ref({
  id: null,
  expense_date: new Date(),
  category: '',
  description: '',
  amount: 0,
  payment_method: '',
  payment_proof: null,
  notes: ''
})

const categoryOptions = computed(() => [
  { label: t('expense.categories.utilities'), value: 'utilities' },
  { label: t('expense.categories.rent'), value: 'rent' },
  { label: t('expense.categories.maintenance'), value: 'maintenance' },
  { label: t('expense.categories.marketing'), value: 'marketing' },
  { label: t('expense.categories.transportation'), value: 'transportation' },
  { label: t('expense.categories.office_supplies'), value: 'office_supplies' },
  { label: t('expense.categories.other'), value: 'other' }
])

const fetchExpenses = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/expenses`)
    expenses.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  form.value = {
    id: null,
    expense_date: new Date(),
    category: '',
    description: '',
    amount: 0,
    payment_method: '',
    payment_proof: null,
    notes: ''
  }
  dialogVisible.value = true
}

const openEditDialog = (expense) => {
  dialogMode.value = 'edit'
  form.value = {
    id: expense.id,
    expense_date: new Date(expense.expense_date),
    category: expense.category,
    description: expense.description,
    amount: expense.amount,
    payment_method: expense.payment_method || '',
    payment_proof: null,
    notes: expense.notes || ''
  }
  dialogVisible.value = true
}

const onFileSelect = (event) => {
  form.value.payment_proof = event.files[0]
}

const saveExpense = async () => {
  if (!form.value.category || !form.value.description || !form.value.amount) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const formData = new FormData()
    formData.append('expense_date', form.value.expense_date.toISOString().split('T')[0])
    formData.append('category', form.value.category)
    formData.append('description', form.value.description)
    formData.append('amount', form.value.amount)
    if (form.value.payment_method) formData.append('payment_method', form.value.payment_method)
    if (form.value.payment_proof) formData.append('payment_proof', form.value.payment_proof)
    if (form.value.notes) formData.append('notes', form.value.notes)

    if (dialogMode.value === 'create') {
      await api.post(`/outlets/${outletId}/expenses`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('expense.expenseCreated'), life: 3000 })
    } else {
      formData.append('_method', 'PUT')
      await api.post(`/outlets/${outletId}/expenses/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('expense.expenseUpdated'), life: 3000 })
    }

    dialogVisible.value = false
    fetchExpenses()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const viewDetails = async (expense) => {
  try {
    const response = await api.get(`/outlets/${outletId}/expenses/${expense.id}`)
    selectedExpense.value = response.data
    detailsDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const confirmDelete = (expense) => {
  confirm.require({
    message: t('expense.confirmDeleteExpense'),
    header: t('expense.deleteExpense'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteExpense(expense.id)
  })
}

const deleteExpense = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/expenses/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('expense.expenseDeleted'), life: 3000 })
    fetchExpenses()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatCurrency = (value) => {
  if (!value) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}

onMounted(() => {
  fetchExpenses()
})
</script>

<style scoped>
.expense-view {
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

.form-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
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

.field label { font-weight: 600; font-size: 0.875rem; }

.details-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.detail-value {
  font-weight: 600;
}

.proof-section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
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
</style>
