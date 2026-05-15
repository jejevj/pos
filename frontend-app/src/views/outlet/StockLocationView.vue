<template>
  <div class="sl-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('stockLocation.title') }}</h2>
        <p class="text-muted">{{ $t('stockLocation.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('stockLocation.addLocation')" icon="pi pi-plus" outlined @click="openLocationDialog()" />
        <Button :label="$t('stockLocation.movement')" icon="pi pi-arrows-h" @click="openMovementDialog('transfer')" />
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
      <button class="tab-btn" :class="{ active: tab === 'summary' }" @click="tab = 'summary'; fetchSummary()">
        <i class="pi pi-chart-bar"></i> {{ $t('stockLocation.summary') }}
      </button>
      <button class="tab-btn" :class="{ active: tab === 'locations' }" @click="tab = 'locations'; fetchLocations()">
        <i class="pi pi-building"></i> {{ $t('stockLocation.locations') }}
      </button>
      <button class="tab-btn" :class="{ active: tab === 'movements' }" @click="tab = 'movements'; fetchMovements()">
        <i class="pi pi-list"></i> {{ $t('stockLocation.movements') }}
      </button>
    </div>

    <!-- ── SUMMARY TAB ── -->
    <div v-if="tab === 'summary'">
      <!-- Quick action buttons -->
      <div class="quick-actions">
        <Button :label="$t('stockLocation.stockIn')" icon="pi pi-arrow-down" severity="success" outlined @click="openMovementDialog('in')" />
        <Button :label="$t('stockLocation.stockOut')" icon="pi pi-arrow-up" severity="danger" outlined @click="openMovementDialog('out')" />
        <Button :label="$t('stockLocation.transfer')" icon="pi pi-arrows-h" severity="info" outlined @click="openMovementDialog('transfer')" />
      </div>

      <div class="filter-bar mt-2">
        <InputText v-model="summarySearch" :placeholder="$t('common.search')" style="width:250px" />
        <Select v-model="summaryLocation" :options="locations" optionLabel="name" optionValue="id"
                :placeholder="$t('stockLocation.allLocations')" showClear style="width:200px" />
      </div>

      <DataTable :value="filteredSummary" :loading="loadingSummary" stripedRows class="mt-2"
                 expandable-row-groups row-group-mode="subheader" group-rows-by="kategori_nama"
                 sort-field="kategori_nama" :sort-order="1">
        <template #groupheader="{ data }">
          <span class="group-header">{{ data.kategori_nama || 'Uncategorized' }}</span>
        </template>
        <Column field="kode" :header="$t('bahanBaku.code')" style="width:120px" />
        <Column field="nama" :header="$t('common.name')" />
        <Column field="current_stock" :header="$t('stockLocation.totalStock')">
          <template #body="{ data }">
            <span :class="{ 'text-red': data.current_stock <= data.minimum_stock }">
              {{ formatQty(data.current_stock) }} {{ data.satuan_nama }}
            </span>
          </template>
        </Column>
        <Column :header="$t('stockLocation.perLocation')">
          <template #body="{ data }">
            <div class="location-chips">
              <span v-for="loc in data.locations" :key="loc.location_id" class="loc-chip"
                    :class="'loc-' + loc.location_type">
                {{ loc.location_name }}: {{ formatQty(loc.current_stock) }}
              </span>
              <span v-if="!data.locations.length" class="text-muted">—</span>
            </div>
          </template>
        </Column>
        <Column :header="$t('common.actions')" style="width:120px">
          <template #body="{ data }">
            <Button icon="pi pi-arrow-down" text rounded size="small" severity="success"
                    v-tooltip.top="$t('stockLocation.stockIn')" @click="openMovementDialog('in', data)" />
            <Button icon="pi pi-arrow-up" text rounded size="small" severity="danger"
                    v-tooltip.top="$t('stockLocation.stockOut')" @click="openMovementDialog('out', data)" />
            <Button icon="pi pi-arrows-h" text rounded size="small" severity="info"
                    v-tooltip.top="$t('stockLocation.transfer')" @click="openMovementDialog('transfer', data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ── LOCATIONS TAB ── -->
    <div v-if="tab === 'locations'">
      <DataTable :value="locations" :loading="loadingLocations" stripedRows>
        <Column field="name" :header="$t('common.name')" />
        <Column field="type" :header="$t('stockLocation.type')">
          <template #body="{ data }">
            <Tag :value="$t('stockLocation.' + data.type)" :severity="typeColor(data.type)" />
          </template>
        </Column>
        <Column field="description" :header="$t('common.description')" />
        <Column field="is_active" :header="$t('common.status')">
          <template #body="{ data }">
            <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')"
                 :severity="data.is_active ? 'success' : 'secondary'" />
          </template>
        </Column>
        <Column :header="$t('common.actions')" style="width:100px">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded size="small" @click="openLocationDialog(data)" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDeleteLocation(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ── MOVEMENTS TAB ── -->
    <div v-if="tab === 'movements'">
      <div class="filter-bar">
        <Select v-model="movFilter.type" :options="movTypes" optionLabel="label" optionValue="value"
                :placeholder="$t('stockLocation.allTypes')" showClear style="width:160px" />
        <Select v-model="movFilter.location_id" :options="locations" optionLabel="name" optionValue="id"
                :placeholder="$t('stockLocation.allLocations')" showClear style="width:180px" />
        <DatePicker v-model="movFilter.date_from" dateFormat="yy-mm-dd" showIcon :placeholder="$t('common.from')" style="width:160px" />
        <DatePicker v-model="movFilter.date_to" dateFormat="yy-mm-dd" showIcon :placeholder="$t('common.to')" style="width:160px" />
        <Button :label="$t('common.filter')" icon="pi pi-filter" @click="fetchMovements" />
      </div>

      <DataTable :value="movements" :loading="loadingMovements" stripedRows paginator :rows="25" class="mt-2">
        <Column field="created_at" :header="$t('common.date')" style="width:150px">
          <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
        </Column>
        <Column field="type" :header="$t('stockLocation.type')" style="width:110px">
          <template #body="{ data }">
            <Tag :value="$t('stockLocation.' + data.type)" :severity="movColor(data.type)" />
          </template>
        </Column>
        <Column field="bahan_baku_nama" :header="$t('stockLocation.material')" />
        <Column :header="$t('stockLocation.from')">
          <template #body="{ data }">{{ data.from_location_name || '—' }}</template>
        </Column>
        <Column :header="$t('stockLocation.to')">
          <template #body="{ data }">{{ data.to_location_name || '—' }}</template>
        </Column>
        <Column field="quantity" :header="$t('stockLocation.qty')">
          <template #body="{ data }">{{ formatQty(data.quantity) }} {{ data.satuan_nama }}</template>
        </Column>
        <Column field="notes" :header="$t('common.notes')" />
        <Column field="created_by_name" :header="$t('common.createdBy')" />
      </DataTable>
    </div>

    <!-- ── Location Dialog ── -->
    <Dialog v-model:visible="locationDialogVisible"
            :header="editLocation ? $t('stockLocation.editLocation') : $t('stockLocation.addLocation')"
            modal style="width:460px">
      <div class="form-grid">
        <div class="form-field full-width">
          <label>{{ $t('common.name') }} *</label>
          <InputText v-model="locationForm.name" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('stockLocation.type') }} *</label>
          <Select v-model="locationForm.type" :options="locationTypes" optionLabel="label" optionValue="value" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('common.order') }}</label>
          <InputNumber v-model="locationForm.display_order" :min="0" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('common.description') }}</label>
          <Textarea v-model="locationForm.description" rows="2" fluid />
        </div>
        <div class="form-field full-width">
          <div class="toggle-row">
            <ToggleSwitch v-model="locationForm.is_active" />
            <span>{{ locationForm.is_active ? $t('common.active') : $t('common.inactive') }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="locationDialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-save" @click="saveLocation" :loading="saving" />
      </template>
    </Dialog>

    <!-- ── Movement Dialog ── -->
    <Dialog v-model:visible="movDialogVisible" :header="movDialogTitle" modal style="width:500px">
      <div class="form-grid">
        <div class="form-field full-width">
          <label>{{ $t('stockLocation.material') }} *</label>
          <Select v-model="movForm.bahan_baku_id" :options="bahanBakuList" optionLabel="nama" optionValue="id"
                  filter :placeholder="$t('stockLocation.selectMaterial')" fluid />
        </div>

        <div v-if="movForm.type !== 'in'" class="form-field" :class="movForm.type === 'transfer' ? '' : 'full-width'">
          <label>{{ $t('stockLocation.fromLocation') }} *</label>
          <Select v-model="movForm.from_location_id" :options="locations" optionLabel="name" optionValue="id"
                  :placeholder="$t('stockLocation.selectLocation')" fluid />
          <small v-if="movForm.from_location_id && movForm.bahan_baku_id" class="text-muted">
            {{ $t('stockLocation.available') }}: {{ formatQty(getLocStock(movForm.bahan_baku_id, movForm.from_location_id)) }}
          </small>
        </div>

        <div v-if="movForm.type !== 'out'" class="form-field" :class="movForm.type === 'transfer' ? '' : 'full-width'">
          <label>{{ $t('stockLocation.toLocation') }} *</label>
          <Select v-model="movForm.to_location_id" :options="locations" optionLabel="name" optionValue="id"
                  :placeholder="$t('stockLocation.selectLocation')" fluid />
        </div>

        <div class="form-field full-width">
          <label>{{ $t('stockLocation.qty') }} *</label>
          <InputNumber v-model="movForm.quantity" :min="0" :minFractionDigits="0" :maxFractionDigits="4" fluid />
        </div>

        <div class="form-field full-width">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="movForm.notes" rows="2" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="movDialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-save" @click="saveMovement" :loading="saving" />
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
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import DatePicker from 'primevue/datepicker'
import ConfirmDialog from 'primevue/confirmdialog'

const route    = useRoute()
const toast    = useToast()
const confirm  = useConfirm()
const { t }    = useI18n()
const outletId = route.params.outletId

// ── state ──────────────────────────────────────────────────────────────────
const tab             = ref('summary')
const loadingSummary  = ref(false)
const loadingLocations = ref(false)
const loadingMovements = ref(false)
const saving          = ref(false)

const summary    = ref([])
const locations  = ref([])
const movements  = ref([])
const bahanBakuList = ref([])

const summarySearch   = ref('')
const summaryLocation = ref(null)

const movFilter = ref({ type: null, location_id: null, date_from: null, date_to: null })

// ── location dialog ────────────────────────────────────────────────────────
const locationDialogVisible = ref(false)
const editLocation = ref(null)
const locationForm = ref({ name: '', type: 'warehouse', description: '', display_order: 99, is_active: true })

const locationTypes = [
  { label: t('stockLocation.warehouse'),  value: 'warehouse' },
  { label: t('stockLocation.production'), value: 'production' },
  { label: t('stockLocation.retail'),     value: 'retail' },
]

// ── movement dialog ────────────────────────────────────────────────────────
const movDialogVisible = ref(false)
const movForm = ref({ type: 'transfer', bahan_baku_id: null, from_location_id: null, to_location_id: null, quantity: null, notes: '' })

const movTypes = [
  { label: t('stockLocation.in'),       value: 'in' },
  { label: t('stockLocation.out'),      value: 'out' },
  { label: t('stockLocation.transfer'), value: 'transfer' },
]

const movDialogTitle = computed(() => {
  const map = { in: t('stockLocation.stockIn'), out: t('stockLocation.stockOut'), transfer: t('stockLocation.transfer') }
  return map[movForm.value.type] || t('stockLocation.movement')
})

// ── computed ───────────────────────────────────────────────────────────────
const filteredSummary = computed(() => {
  let data = summary.value
  if (summarySearch.value) {
    const q = summarySearch.value.toLowerCase()
    data = data.filter(d => d.nama?.toLowerCase().includes(q) || d.kode?.toLowerCase().includes(q))
  }
  if (summaryLocation.value) {
    data = data.filter(d => d.locations.some(l => l.location_id === summaryLocation.value))
  }
  return data
})

// ── fetch ──────────────────────────────────────────────────────────────────
const fetchSummary = async () => {
  loadingSummary.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/stock-movements/summary`)
    summary.value = res.data
  } catch (e) { err(e) }
  finally { loadingSummary.value = false }
}

const fetchLocations = async () => {
  loadingLocations.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/locations`)
    locations.value = res.data
  } catch (e) { err(e) }
  finally { loadingLocations.value = false }
}

const fetchMovements = async () => {
  loadingMovements.value = true
  try {
    const params = {}
    if (movFilter.value.type)        params.type        = movFilter.value.type
    if (movFilter.value.location_id) params.location_id = movFilter.value.location_id
    if (movFilter.value.date_from)   params.date_from   = fmtDate(movFilter.value.date_from)
    if (movFilter.value.date_to)     params.date_to     = fmtDate(movFilter.value.date_to)
    const res = await api.get(`/outlets/${outletId}/stock-movements`, { params })
    movements.value = res.data.data || res.data
  } catch (e) { err(e) }
  finally { loadingMovements.value = false }
}

const fetchBahanBaku = async () => {
  try {
    const res = await api.get(`/outlets/${outletId}/bahan-baku`)
    bahanBakuList.value = res.data
  } catch (e) { console.error(e) }
}

// ── location CRUD ──────────────────────────────────────────────────────────
const openLocationDialog = (loc = null) => {
  editLocation.value = loc
  locationForm.value = loc
    ? { name: loc.name, type: loc.type, description: loc.description || '', display_order: loc.display_order, is_active: !!loc.is_active }
    : { name: '', type: 'warehouse', description: '', display_order: 99, is_active: true }
  locationDialogVisible.value = true
}

const saveLocation = async () => {
  if (!locationForm.value.name) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('stockLocation.fillName'), life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editLocation.value) {
      await api.put(`/outlets/${outletId}/locations/${editLocation.value.id}`, locationForm.value)
    } else {
      await api.post(`/outlets/${outletId}/locations`, locationForm.value)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockLocation.locationSaved'), life: 3000 })
    locationDialogVisible.value = false
    fetchLocations()
  } catch (e) { err(e) }
  finally { saving.value = false }
}

const confirmDeleteLocation = (loc) => {
  confirm.require({
    message: `${t('stockLocation.deleteConfirm')} "${loc.name}"?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/locations/${loc.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockLocation.locationDeleted'), life: 3000 })
        fetchLocations()
      } catch (e) { err(e) }
    }
  })
}

// ── movement ───────────────────────────────────────────────────────────────
const openMovementDialog = (type, item = null) => {
  movForm.value = {
    type,
    bahan_baku_id:    item?.id || null,
    from_location_id: null,
    to_location_id:   null,
    quantity:         null,
    notes:            '',
  }
  movDialogVisible.value = true
}

const saveMovement = async () => {
  const f = movForm.value
  if (!f.bahan_baku_id || !f.quantity) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('stockLocation.fillRequired'), life: 3000 })
    return
  }
  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/stock-movements/${f.type}`, f)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('stockLocation.movementSaved'), life: 3000 })
    movDialogVisible.value = false
    fetchSummary()
    if (tab.value === 'movements') fetchMovements()
  } catch (e) { err(e) }
  finally { saving.value = false }
}

// ── helpers ────────────────────────────────────────────────────────────────
const err = (e) => toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message || e.message, life: 4000 })

const getLocStock = (bbId, locId) => {
  const item = summary.value.find(s => s.id === bbId)
  if (!item) return 0
  const loc = item.locations.find(l => l.location_id === locId)
  return loc?.current_stock || 0
}

const formatQty  = (n) => Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })
const formatDate = (d) => d ? new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', timeZone: 'Asia/Jakarta' }) : '-'
const fmtDate    = (d) => { if (!d) return null; const dt = new Date(d); return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}` }

const typeColor = (t) => ({ warehouse: 'info', production: 'warn', retail: 'success' }[t] || 'secondary')
const movColor  = (t) => ({ in: 'success', out: 'danger', transfer: 'info', order: 'warn', purchase: 'secondary' }[t] || 'secondary')

onMounted(() => {
  fetchLocations()
  fetchSummary()
  fetchBahanBaku()
})
</script>

<style scoped>
.sl-view { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }

.tabs-bar { display: flex; gap: 0.25rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 1.5rem; }
.tab-btn {
  padding: 0.6rem 1.25rem; background: none; border: none;
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  color: #6b7280; font-weight: 500; cursor: pointer;
  display: flex; align-items: center; gap: 0.4rem; transition: all 0.15s;
}
.tab-btn:hover { color: #3b82f6; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }

.quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.filter-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.mt-2 { margin-top: 0.75rem; }

.group-header { font-weight: 700; color: #374151; }

.location-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.loc-chip {
  padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;
}
.loc-warehouse  { background: #dbeafe; color: #1d4ed8; }
.loc-production { background: #fef3c7; color: #92400e; }
.loc-retail     { background: #dcfce7; color: #166534; }

.text-red { color: #ef4444; font-weight: 600; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-field { display: flex; flex-direction: column; gap: 0.4rem; }
.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field.full-width { grid-column: 1 / -1; }
.toggle-row { display: flex; align-items: center; gap: 0.75rem; }
</style>
