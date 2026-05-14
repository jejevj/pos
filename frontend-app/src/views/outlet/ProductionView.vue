<template>
  <div class="prod-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('production.title') }}</h2>
        <p class="text-muted">{{ $t('production.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('common.back')" icon="pi pi-arrow-left" text
                @click="router.push(`/outlets/${outletId}/dashboard`)" />
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
      <button class="tab-btn" :class="{ active: tab === 'units' }" @click="tab = 'units'; fetchUnits()">
        <i class="pi pi-cog"></i> {{ $t('production.units') }}
      </button>
      <button class="tab-btn" :class="{ active: tab === 'orders' }" @click="tab = 'orders'; fetchOrders()">
        <i class="pi pi-list"></i> {{ $t('production.orders') }}
      </button>
      <button class="tab-btn" :class="{ active: tab === 'history' }" @click="tab = 'history'; fetchStockHistory()">
        <i class="pi pi-history"></i> {{ $t('production.stockHistory') }}
      </button>
    </div>

    <!-- ── UNITS TAB ── -->
    <div v-if="tab === 'units'">
      <div class="filter-bar">
        <div class="filter-fields">
          <div class="filter-group">
            <label class="filter-label">&nbsp;</label>
            <Button v-if="canManage" :label="$t('production.addUnit')" icon="pi pi-plus" @click="openUnitDialog()" />
          </div>
        </div>
      </div>

      <DataTable :value="units" :loading="loadingUnits" stripedRows class="mt-2">
        <Column field="nama" :header="$t('production.unitName')" />
        <Column field="deskripsi" :header="$t('production.description')" />
        <Column field="is_active" :header="$t('common.status')" style="width:120px">
          <template #body="{ data }">
            <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')"
                 :severity="data.is_active ? 'success' : 'secondary'" />
          </template>
        </Column>
        <Column :header="$t('common.actions')" style="width:120px">
          <template #body="{ data }">
            <Button v-if="canManage" icon="pi pi-pencil" text rounded size="small" @click="openUnitDialog(data)" />
            <Button v-if="canManage" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDeleteUnit(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ── ORDERS TAB ── -->
    <div v-if="tab === 'orders'">
      <div class="filter-bar">
        <div class="filter-fields">
          <div class="filter-group">
            <label class="filter-label">{{ $t('common.status') }}</label>
            <Select v-model="orderFilter.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    :placeholder="$t('common.all')" showClear style="width:180px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">{{ $t('production.unit') }}</label>
            <Select v-model="orderFilter.unit_id" :options="units" optionLabel="nama" optionValue="id"
                    :placeholder="$t('common.all')" showClear style="width:200px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">&nbsp;</label>
            <Button :label="$t('common.filter')" icon="pi pi-filter" @click="fetchOrders" />
          </div>
          <div class="filter-group">
            <label class="filter-label">&nbsp;</label>
            <Button v-if="canManage" :label="$t('production.createOrder')" icon="pi pi-plus" @click="openOrderDialog()" />
          </div>
        </div>
      </div>

      <DataTable :value="orders" :loading="loadingOrders" stripedRows paginator :rows="20" class="mt-2"
                 v-model:expandedRows="expandedRows" dataKey="id">
        <Column expander style="width:42px" />
        <Column field="id" header="#" style="width:80px" />
        <Column field="unit_nama" :header="$t('production.unit')" />
        <Column field="status" :header="$t('common.status')" style="width:140px">
          <template #body="{ data }">
            <Tag :value="$t('production.status.' + data.status)" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column field="created_at" :header="$t('production.createdAt')" style="width:160px">
          <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
        </Column>
        <Column field="completed_at" :header="$t('production.completedAt')" style="width:160px">
          <template #body="{ data }">{{ data.completed_at ? formatDate(data.completed_at) : '—' }}</template>
        </Column>
        <Column :header="$t('common.actions')" style="width:220px">
          <template #body="{ data }">
            <Button v-if="canManage && data.status === 'draft'" icon="pi pi-play" text rounded size="small"
                    severity="warn" v-tooltip.top="$t('production.startOrder')"
                    @click="changeStatus(data, 'in_progress')" />
            <Button v-if="canManage && (data.status === 'draft' || data.status === 'in_progress')"
                    icon="pi pi-check" text rounded size="small" severity="success"
                    v-tooltip.top="$t('production.completeOrder')"
                    @click="openCompleteDialog(data)" />
            <Button v-if="canManage && (data.status === 'draft' || data.status === 'in_progress')"
                    icon="pi pi-times" text rounded size="small" severity="danger"
                    v-tooltip.top="$t('production.cancelOrder')"
                    @click="changeStatus(data, 'cancelled')" />
          </template>
        </Column>
        <template #expansion="{ data }">
          <div class="order-items-wrap">
            <DataTable :value="data.items" stripedRows>
              <Column field="bahan_baku_nama" :header="$t('production.material')" />
              <Column field="quantity_planned" :header="$t('production.qtyPlanned')">
                <template #body="{ data: row }">{{ formatQty(row.quantity_planned) }} {{ row.satuan_nama || '' }}</template>
              </Column>
              <Column field="quantity_actual" :header="$t('production.qtyActual')">
                <template #body="{ data: row }">{{ row.quantity_actual != null ? formatQty(row.quantity_actual) + ' ' + (row.satuan_nama || '') : '—' }}</template>
              </Column>
              <Column field="location_nama" :header="$t('production.location')">
                <template #body="{ data: row }">{{ row.location_nama || '—' }}</template>
              </Column>
              <Column field="notes" :header="$t('common.notes')" />
            </DataTable>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- ── STOCK HISTORY TAB ── -->
    <div v-if="tab === 'history'">
      <div class="filter-bar">
        <div class="filter-fields">
          <div class="filter-group">
            <label class="filter-label">{{ $t('production.tipe.label') }}</label>
            <Select v-model="historyFilter.tipe" :options="tipeOptions" optionLabel="label" optionValue="value"
                    :placeholder="$t('common.all')" showClear style="width:160px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">{{ $t('production.material') }}</label>
            <Select v-model="historyFilter.bahan_baku_id" :options="bahanBakuList" optionLabel="nama" optionValue="id"
                    :placeholder="$t('common.all')" showClear filter style="width:240px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">{{ $t('common.from') }}</label>
            <DatePicker v-model="historyFilter.date_from" dateFormat="yy-mm-dd" showIcon style="width:160px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">{{ $t('common.to') }}</label>
            <DatePicker v-model="historyFilter.date_to" dateFormat="yy-mm-dd" showIcon style="width:160px" />
          </div>
          <div class="filter-group">
            <label class="filter-label">&nbsp;</label>
            <Button :label="$t('common.filter')" icon="pi pi-filter" @click="fetchStockHistory" />
          </div>
        </div>
      </div>

      <DataTable :value="stockHistory" :loading="loadingHistory" stripedRows paginator :rows="30" class="mt-2">
        <Column field="created_at" :header="$t('common.date')" style="width:160px">
          <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
        </Column>
        <Column field="bahan_baku_nama" :header="$t('production.material')" />
        <Column field="tipe" :header="$t('production.tipe.label')" style="width:120px">
          <template #body="{ data }">
            <Tag :value="$t('production.tipe.' + data.tipe) || data.tipe" :severity="tipeSeverity(data.tipe)" />
          </template>
        </Column>
        <Column field="quantity" :header="$t('production.qty')">
          <template #body="{ data }">{{ formatQty(data.quantity) }}</template>
        </Column>
        <Column field="stock_before" :header="$t('production.stockBefore')">
          <template #body="{ data }">{{ formatQty(data.stock_before) }}</template>
        </Column>
        <Column field="stock_after" :header="$t('production.stockAfter')">
          <template #body="{ data }">{{ formatQty(data.stock_after) }}</template>
        </Column>
        <Column field="reference_type" :header="$t('production.reference')">
          <template #body="{ data }">
            <span v-if="data.reference_type">{{ data.reference_type }} #{{ data.reference_id }}</span>
            <span v-else>—</span>
          </template>
        </Column>
        <Column field="notes" :header="$t('common.notes')" />
      </DataTable>
    </div>

    <!-- ── Unit Dialog ── -->
    <Dialog v-model:visible="unitDialogVisible"
            :header="editUnit ? $t('production.editUnit') : $t('production.addUnit')"
            modal style="width:460px">
      <div class="form-fields">
        <div class="form-field">
          <label>{{ $t('production.unitName') }} *</label>
          <InputText v-model="unitForm.nama" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('production.description') }}</label>
          <Textarea v-model="unitForm.deskripsi" fluid rows="2" />
        </div>
        <div class="form-field-row">
          <label>{{ $t('common.active') }}</label>
          <ToggleSwitch v-model="unitForm.is_active" />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="unitDialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-check" @click="saveUnit" :loading="savingUnit" />
      </template>
    </Dialog>

    <!-- ── Order Dialog ── -->
    <Dialog v-model:visible="orderDialogVisible" :header="$t('production.createOrder')"
            modal style="width:900px" :breakpoints="{ '960px': '90vw' }">
      <div class="form-fields">
        <div class="form-field">
          <label>{{ $t('production.unit') }} *</label>
          <Select v-model="orderForm.unit_id" :options="units" optionLabel="nama" optionValue="id"
                  :placeholder="$t('production.selectUnit')" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="orderForm.notes" fluid rows="2" />
        </div>
        <div class="form-field">
          <label>{{ $t('production.items') }}</label>
          <DataTable :value="orderForm.items" stripedRows>
            <Column :header="$t('production.material')">
              <template #body="{ index }">
                <Select v-model="orderForm.items[index].bahan_baku_id" :options="bahanBakuList"
                        optionLabel="nama" optionValue="id" filter
                        :placeholder="$t('production.selectMaterial')" style="width:100%"
                        @change="onItemBahanChange(index)" />
              </template>
            </Column>
            <Column :header="$t('production.qtyPlanned')" style="width:140px">
              <template #body="{ index }">
                <InputNumber v-model="orderForm.items[index].quantity_planned"
                             :step="0.001" :minFractionDigits="0" :maxFractionDigits="3" fluid />
              </template>
            </Column>
            <Column :header="$t('production.satuan')" style="width:140px">
              <template #body="{ index }">
                <Select v-model="orderForm.items[index].satuan_id" :options="satuanList"
                        optionLabel="nama" optionValue="id" :placeholder="'-'" showClear style="width:100%" />
              </template>
            </Column>
            <Column :header="$t('production.location')" style="width:160px">
              <template #body="{ index }">
                <Select v-model="orderForm.items[index].location_id" :options="locations"
                        optionLabel="name" optionValue="id" :placeholder="'-'" showClear style="width:100%" />
              </template>
            </Column>
            <Column :header="$t('common.notes')">
              <template #body="{ index }">
                <InputText v-model="orderForm.items[index].notes" style="width:100%" />
              </template>
            </Column>
            <Column style="width:60px">
              <template #body="{ index }">
                <Button icon="pi pi-trash" text rounded size="small" severity="danger"
                        @click="removeOrderItem(index)" />
              </template>
            </Column>
          </DataTable>
          <div class="mt-2">
            <Button :label="$t('production.addItem')" icon="pi pi-plus" outlined size="small" @click="addOrderItem" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="orderDialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-check" @click="saveOrder" :loading="savingOrder" />
      </template>
    </Dialog>

    <!-- ── Complete Order Dialog ── -->
    <Dialog v-model:visible="completeDialogVisible" :header="$t('production.completeOrder')"
            modal style="width:800px" :breakpoints="{ '960px': '90vw' }">
      <div class="form-fields" v-if="completingOrder">
        <p class="text-muted">{{ $t('production.unit') }}: <strong>{{ completingOrder.unit_nama }}</strong></p>
        <DataTable :value="completeItems" stripedRows>
          <Column field="bahan_baku_nama" :header="$t('production.material')" />
          <Column :header="$t('production.qtyPlanned')" style="width:140px">
            <template #body="{ data }">{{ formatQty(data.quantity_planned) }} {{ data.satuan_nama || '' }}</template>
          </Column>
          <Column :header="$t('production.qtyActual')" style="width:160px">
            <template #body="{ index }">
              <InputNumber v-model="completeItems[index].quantity_actual"
                           :step="0.001" :minFractionDigits="0" :maxFractionDigits="3" fluid />
            </template>
          </Column>
          <Column :header="$t('production.location')" style="width:200px">
            <template #body="{ index }">
              <Select v-model="completeItems[index].location_id" :options="locations"
                      optionLabel="name" optionValue="id"
                      :placeholder="$t('production.selectLocation')" style="width:100%" />
            </template>
          </Column>
        </DataTable>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="completeDialogVisible = false" />
        <Button :label="$t('production.confirmComplete')" icon="pi pi-check" severity="success"
                @click="confirmComplete" :loading="completingSaving" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import DatePicker from 'primevue/datepicker'
import ConfirmDialog from 'primevue/confirmdialog'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
const authStore = useAuthStore()

const outletId = route.params.outletId

const canView = computed(() => authStore.hasOutletPermission(outletId, 'view_production'))
const canManage = computed(() => authStore.hasOutletPermission(outletId, 'manage_production'))

const tab = ref('units')

// Units
const units = ref([])
const loadingUnits = ref(false)
const unitDialogVisible = ref(false)
const editUnit = ref(null)
const savingUnit = ref(false)
const unitForm = ref({ nama: '', deskripsi: '', is_active: true })

// Orders
const orders = ref([])
const loadingOrders = ref(false)
const expandedRows = ref({})
const orderFilter = ref({ status: null, unit_id: null })
const orderDialogVisible = ref(false)
const savingOrder = ref(false)
const orderForm = ref({ unit_id: null, notes: '', items: [] })

// Complete
const completeDialogVisible = ref(false)
const completingOrder = ref(null)
const completeItems = ref([])
const completingSaving = ref(false)

// Stock history
const stockHistory = ref([])
const loadingHistory = ref(false)
const historyFilter = ref({ tipe: null, bahan_baku_id: null, date_from: null, date_to: null })

// Reference lists
const bahanBakuList = ref([])
const satuanList = ref([])
const locations = ref([])

const statusOptions = computed(() => [
  { value: 'draft',       label: t('production.status.draft') },
  { value: 'in_progress', label: t('production.status.in_progress') },
  { value: 'completed',   label: t('production.status.completed') },
  { value: 'cancelled',   label: t('production.status.cancelled') },
])

const tipeOptions = computed(() => [
  { value: 'production', label: t('production.tipe.production') },
  { value: 'in',         label: t('production.tipe.in') },
  { value: 'out',        label: t('production.tipe.out') },
  { value: 'transfer',   label: t('production.tipe.transfer') },
])

const statusSeverity = (s) => ({
  draft: 'secondary',
  in_progress: 'warn',
  completed: 'success',
  cancelled: 'danger',
}[s] || 'secondary')

const tipeSeverity = (t) => ({
  production: 'success',
  in: 'info',
  out: 'danger',
  transfer: 'warn',
}[t] || 'secondary')

const formatQty = (v) => {
  if (v == null) return '—'
  const n = Number(v)
  return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/\.?0+$/, '')
}
const formatDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleString()
}

// ── Units ─────────────────────────────────────────────────────────────────
const fetchUnits = async () => {
  loadingUnits.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/production/units`)
    units.value = res.data.data || res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    loadingUnits.value = false
  }
}

const openUnitDialog = (u = null) => {
  editUnit.value = u
  unitForm.value = u ? { ...u } : { nama: '', deskripsi: '', is_active: true }
  unitDialogVisible.value = true
}

const saveUnit = async () => {
  if (!unitForm.value.nama) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('production.nameRequired'), life: 3000 })
    return
  }
  savingUnit.value = true
  try {
    if (editUnit.value) {
      await api.put(`/outlets/${outletId}/production/units/${editUnit.value.id}`, unitForm.value)
    } else {
      await api.post(`/outlets/${outletId}/production/units`, unitForm.value)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.savedSuccessfully'), life: 2500 })
    unitDialogVisible.value = false
    fetchUnits()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    savingUnit.value = false
  }
}

const confirmDeleteUnit = (u) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: u.nama }),
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: t('common.delete'),
    rejectLabel: t('common.cancel'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/production/units/${u.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 2500 })
        fetchUnits()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
      }
    }
  })
}

// ── Orders ────────────────────────────────────────────────────────────────
const fetchOrders = async () => {
  loadingOrders.value = true
  try {
    const params = {}
    if (orderFilter.value.status) params.status = orderFilter.value.status
    if (orderFilter.value.unit_id) params.unit_id = orderFilter.value.unit_id
    const res = await api.get(`/outlets/${outletId}/production/orders`, { params })
    orders.value = res.data.data || res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    loadingOrders.value = false
  }
}

const openOrderDialog = async () => {
  if (!units.value.length) await fetchUnits()
  await ensureRefData()
  orderForm.value = { unit_id: null, notes: '', items: [makeEmptyItem()] }
  orderDialogVisible.value = true
}

const makeEmptyItem = () => ({
  bahan_baku_id: null,
  quantity_planned: null,
  satuan_id: null,
  location_id: null,
  notes: '',
})

const addOrderItem = () => orderForm.value.items.push(makeEmptyItem())
const removeOrderItem = (i) => orderForm.value.items.splice(i, 1)

const onItemBahanChange = (idx) => {
  const item = orderForm.value.items[idx]
  const bb = bahanBakuList.value.find(b => b.id === item.bahan_baku_id)
  if (bb && !item.satuan_id) {
    item.satuan_id = bb.satuan_id || null
  }
}

const saveOrder = async () => {
  if (!orderForm.value.unit_id) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('production.unitRequired'), life: 3000 })
    return
  }
  const validItems = orderForm.value.items.filter(i => i.bahan_baku_id && i.quantity_planned > 0)
  if (!validItems.length) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('production.itemsRequired'), life: 3000 })
    return
  }
  savingOrder.value = true
  try {
    await api.post(`/outlets/${outletId}/production/orders`, {
      unit_id: orderForm.value.unit_id,
      notes: orderForm.value.notes,
      items: validItems,
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.savedSuccessfully'), life: 2500 })
    orderDialogVisible.value = false
    fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    savingOrder.value = false
  }
}

const changeStatus = (order, status) => {
  confirm.require({
    message: t('production.confirmStatus', { status: t('production.status.' + status) }),
    header: t('common.confirm'),
    icon: 'pi pi-question-circle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.put(`/outlets/${outletId}/production/orders/${order.id}/status`, { status })
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.savedSuccessfully'), life: 2500 })
        fetchOrders()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
      }
    }
  })
}

const openCompleteDialog = async (order) => {
  await ensureRefData()
  completingOrder.value = order
  completeItems.value = (order.items || []).map(i => ({
    id: i.id,
    bahan_baku_nama: i.bahan_baku_nama,
    quantity_planned: i.quantity_planned,
    satuan_nama: i.satuan_nama,
    quantity_actual: i.quantity_actual ?? i.quantity_planned,
    location_id: i.location_id || null,
  }))
  completeDialogVisible.value = true
}

const confirmComplete = async () => {
  for (const it of completeItems.value) {
    if (it.quantity_actual == null || it.quantity_actual < 0) {
      toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('production.qtyActualRequired'), life: 3000 })
      return
    }
    if (!it.location_id) {
      toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('production.locationRequired'), life: 3000 })
      return
    }
  }
  completingSaving.value = true
  try {
    await api.post(`/outlets/${outletId}/production/orders/${completingOrder.value.id}/complete`, {
      items: completeItems.value.map(i => ({
        id: i.id,
        quantity_actual: i.quantity_actual,
        location_id: i.location_id,
      })),
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('production.completed'), life: 2500 })
    completeDialogVisible.value = false
    fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    completingSaving.value = false
  }
}

// ── Stock history ─────────────────────────────────────────────────────────
const fetchStockHistory = async () => {
  loadingHistory.value = true
  try {
    const params = {}
    if (historyFilter.value.tipe) params.tipe = historyFilter.value.tipe
    if (historyFilter.value.bahan_baku_id) params.bahan_baku_id = historyFilter.value.bahan_baku_id
    if (historyFilter.value.date_from) params.date_from = toDateStr(historyFilter.value.date_from)
    if (historyFilter.value.date_to) params.date_to = toDateStr(historyFilter.value.date_to)
    const res = await api.get(`/outlets/${outletId}/production/stock-history`, { params })
    stockHistory.value = res.data.data || res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || e.message, life: 3000 })
  } finally {
    loadingHistory.value = false
  }
}

const toDateStr = (d) => {
  if (!d) return null
  if (typeof d === 'string') return d
  const dt = new Date(d)
  const yyyy = dt.getFullYear()
  const mm = String(dt.getMonth() + 1).padStart(2, '0')
  const dd = String(dt.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

// ── Reference data ────────────────────────────────────────────────────────
const refDataLoaded = ref(false)
const ensureRefData = async () => {
  if (refDataLoaded.value) return
  try {
    const [bbRes, satRes, locRes] = await Promise.all([
      api.get(`/outlets/${outletId}/bahan-baku`),
      api.get(`/outlets/${outletId}/satuan`),
      api.get(`/outlets/${outletId}/locations`),
    ])
    bahanBakuList.value = bbRes.data?.data || bbRes.data || []
    satuanList.value = satRes.data?.data || satRes.data || []
    locations.value = locRes.data?.data || locRes.data || []
    refDataLoaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data referensi', life: 3000 })
  }
}

onMounted(async () => {
  await ensureRefData()
  await fetchUnits()
})
</script>

<style scoped>
.prod-view { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }

.tabs-bar { display: flex; gap: 0.25rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem; }
.tab-btn {
  background: none; border: none; padding: 0.65rem 1rem; cursor: pointer;
  font-weight: 500; color: #6b7280; border-bottom: 2px solid transparent;
}
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.tab-btn i { margin-right: 0.4rem; }

.filter-bar { padding: 0.75rem 0; }
.filter-fields { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
.filter-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; }

.form-fields { display: flex; flex-direction: column; gap: 1rem; }
.form-field { display: flex; flex-direction: column; gap: 0.4rem; }
.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field-row { display: flex; align-items: center; justify-content: space-between; }

.order-items-wrap { padding: 0.5rem 1rem; background: #f9fafb; }
.mt-2 { margin-top: 0.5rem; }
</style>
