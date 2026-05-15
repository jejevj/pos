<template>
  <div class="pm-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('paymentMethod.title') }}</h2>
        <p class="text-muted">{{ $t('paymentMethod.subtitle') }}</p>
      </div>
      <Button :label="$t('paymentMethod.add')" icon="pi pi-plus" @click="openDialog()" />
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
      <button class="tab-btn" :class="{ active: tab === 'methods' }" @click="tab = 'methods'">
        <i class="pi pi-credit-card"></i> {{ $t('paymentMethod.methods') }}
      </button>
      <button class="tab-btn" :class="{ active: tab === 'bon' }" @click="tab = 'bon'; fetchBon()">
        <i class="pi pi-file-edit"></i> {{ $t('paymentMethod.bonList') }}
        <span v-if="bonCount > 0" class="tab-badge">{{ bonCount }}</span>
      </button>
    </div>

    <!-- Methods Tab -->
    <div v-if="tab === 'methods'">
      <DataTable :value="methods" :loading="loading" stripedRows>
        <Column field="display_order" header="#" style="width:60px" />
        <Column field="name" :header="$t('common.name')" />
        <Column field="code" :header="$t('paymentMethod.code')" />
        <Column field="defers_stock" :header="$t('paymentMethod.defersStock')">
          <template #body="{ data }">
            <Tag
              v-if="data.defers_stock"
              :value="$t('paymentMethod.bon')"
              severity="warn"
            />
            <Tag v-else :value="$t('paymentMethod.immediate')" severity="success" />
          </template>
        </Column>
        <Column field="is_active" :header="$t('common.status')">
          <template #body="{ data }">
            <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')"
                 :severity="data.is_active ? 'success' : 'secondary'" />
          </template>
        </Column>
        <Column :header="$t('common.actions')" style="width:120px">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded size="small" @click="openDialog(data)" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger"
                    @click="confirmDelete(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Bon Tab -->
    <div v-if="tab === 'bon'">
      <div class="filter-bar">
        <DatePicker v-model="bonDate" dateFormat="yy-mm-dd" showIcon :placeholder="$t('common.selectDate')" />
        <Button :label="$t('common.filter')" icon="pi pi-filter" @click="fetchBon" />
        <Button :label="$t('common.reset')" icon="pi pi-times" outlined @click="bonDate = null; fetchBon()" />
      </div>

      <DataTable :value="bonList" :loading="loadingBon" stripedRows>
        <Column field="kode" :header="$t('transaction.orderCode')" />
        <Column field="customer_name" :header="$t('pos.customerName')">
          <template #body="{ data }">{{ data.customer_name || '-' }}</template>
        </Column>
        <Column field="total_amount" :header="$t('transaction.total')">
          <template #body="{ data }">Rp {{ formatNumber(data.total_amount) }}</template>
        </Column>
        <Column field="payment_method_name" :header="$t('pos.paymentMethod')" />
        <Column field="cashier_name" :header="$t('paymentMethod.cashier')" />
        <Column field="created_at" :header="$t('common.date')">
          <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
        </Column>
        <Column :header="$t('common.actions')" style="width:120px">
          <template #body="{ data }">
            <Button
              :label="$t('paymentMethod.settle')"
              icon="pi pi-check"
              size="small"
              severity="success"
              @click="confirmSettle(data)"
            />
          </template>
        </Column>
      </DataTable>

      <div class="bon-summary" v-if="bonList.length > 0">
        <strong>{{ $t('paymentMethod.totalBon') }}:</strong>
        Rp {{ formatNumber(bonList.reduce((s, b) => s + parseFloat(b.total_amount || 0), 0)) }}
      </div>
    </div>

    <!-- Add/Edit Dialog -->
    <Dialog v-model:visible="dialogVisible" :header="editItem ? $t('paymentMethod.edit') : $t('paymentMethod.add')" modal style="width:480px">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('common.name') }} *</label>
          <InputText v-model="form.name" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('paymentMethod.code') }} *</label>
          <InputText v-model="form.code" fluid :disabled="!!editItem" />
        </div>
        <div class="form-field">
          <label>{{ $t('paymentMethod.icon') }}</label>
          <InputText v-model="form.icon" placeholder="pi pi-credit-card" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('common.order') }}</label>
          <InputNumber v-model="form.display_order" :min="0" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('paymentMethod.defersStock') }}</label>
          <div class="toggle-row">
            <ToggleSwitch v-model="form.defers_stock" />
            <span>{{ $t('paymentMethod.defersStockHint') }}</span>
          </div>
        </div>
        <div class="form-field full-width">
          <label>{{ $t('common.status') }}</label>
          <div class="toggle-row">
            <ToggleSwitch v-model="form.is_active" />
            <span>{{ form.is_active ? $t('common.active') : $t('common.inactive') }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-save" @click="save" :loading="saving" />
      </template>
    </Dialog>

    <ConfirmDialog />
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
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import DatePicker from 'primevue/datepicker'
import ConfirmDialog from 'primevue/confirmdialog'

const route   = useRoute()
const toast   = useToast()
const confirm = useConfirm()
const { t }   = useI18n()
const outletId = route.params.outletId

const tab        = ref('methods')
const loading    = ref(false)
const loadingBon = ref(false)
const saving     = ref(false)
const methods    = ref([])
const bonList    = ref([])
const bonDate    = ref(null)
const bonCount   = computed(() => bonList.value.length)

const dialogVisible = ref(false)
const editItem      = ref(null)
const form = ref({
  name: '', code: '', icon: '', display_order: 99,
  defers_stock: false, is_active: true
})

const fetchMethods = async () => {
  loading.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/payment-methods`)
    methods.value = res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchBon = async () => {
  loadingBon.value = true
  try {
    const params = {}
    if (bonDate.value) {
      const d = new Date(bonDate.value)
      params.date = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
    }
    const res = await api.get(`/outlets/${outletId}/bon`, { params })
    bonList.value = res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    loadingBon.value = false
  }
}

const openDialog = (item = null) => {
  editItem.value = item
  form.value = item
    ? { name: item.name, code: item.code, icon: item.icon || '', display_order: item.display_order, defers_stock: !!item.defers_stock, is_active: !!item.is_active }
    : { name: '', code: '', icon: '', display_order: 99, defers_stock: false, is_active: true }
  dialogVisible.value = true
}

const save = async () => {
  if (!form.value.name || !form.value.code) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('paymentMethod.fillRequired'), life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/outlets/${outletId}/payment-methods/${editItem.value.id}`, form.value)
    } else {
      await api.post(`/outlets/${outletId}/payment-methods`, form.value)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('paymentMethod.saved'), life: 3000 })
    dialogVisible.value = false
    fetchMethods()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (item) => {
  confirm.require({
    message: `${t('paymentMethod.deleteConfirm')} "${item.name}"?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteMethod(item)
  })
}

const deleteMethod = async (item) => {
  try {
    await api.delete(`/outlets/${outletId}/payment-methods/${item.id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('paymentMethod.deleted'), life: 3000 })
    fetchMethods()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  }
}

const confirmSettle = (bon) => {
  confirm.require({
    message: `${t('paymentMethod.settleConfirm')} ${bon.kode} (Rp ${formatNumber(bon.total_amount)})?`,
    header: t('paymentMethod.settle'),
    icon: 'pi pi-check-circle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => settleBon(bon)
  })
}

const settleBon = async (bon) => {
  try {
    await api.post(`/outlets/${outletId}/orders/${bon.id}/settle-bon`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('paymentMethod.settled'), life: 3000 })
    fetchBon()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  }
}

const formatNumber = (n) => Number(n || 0).toLocaleString('id-ID')
const formatDate   = (d) => d ? new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', timeZone: 'Asia/Jakarta' }) : '-'

onMounted(() => {
  fetchMethods()
  fetchBon()
})
</script>

<style scoped>
.pm-view { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.tabs-bar { display: flex; gap: 0.25rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 1.5rem; }
.tab-btn {
  padding: 0.6rem 1.25rem; background: none; border: none;
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  color: #6b7280; font-weight: 500; cursor: pointer;
  display: flex; align-items: center; gap: 0.4rem; transition: all 0.15s;
}
.tab-btn:hover { color: #3b82f6; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.tab-badge {
  background: #ef4444; color: white; border-radius: 10px;
  padding: 0 6px; font-size: 0.7rem; font-weight: 700;
}

.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-field { display: flex; flex-direction: column; gap: 0.4rem; }
.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field.full-width { grid-column: 1 / -1; }
.toggle-row { display: flex; align-items: center; gap: 0.75rem; }

.bon-summary {
  margin-top: 1rem; padding: 0.75rem 1rem;
  background: #fef3c7; border-radius: 8px;
  border: 1px solid #f59e0b; font-size: 0.95rem;
}
</style>
