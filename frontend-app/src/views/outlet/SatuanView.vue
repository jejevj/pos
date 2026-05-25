<template>
  <div class="view-container">
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

    <div v-if="isProcessing" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('bahanBaku.units') }}</span>
          <Button :label="$t('bahanBaku.addUnit')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="flatUnits" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>
          <template #header>
            <div class="filter-bar">
              <div class="filter-fields">
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
                  <InputText v-model="searchQuery" :placeholder="$t('common.search') + '...'" class="filter-input" />
                </div>
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-tag" /> Tipe</label>
                  <Select v-model="filterTipe" :options="tipeOptions" optionLabel="label" optionValue="value"
                          placeholder="Semua Tipe" showClear class="filter-select" />
                </div>
              </div>
              <Button v-if="searchQuery || filterTipe" label="Reset" icon="pi pi-filter-slash"
                      severity="secondary" outlined size="small" class="filter-reset-btn"
                      @click="resetFilter" />
            </div>
          </template>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-calculator" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>

          <Column field="tipe" header="Tipe" sortable style="width: 110px">
            <template #body="{ data }">
              <Tag :value="getTipeLabel(data.tipe)" :severity="getTypeSeverity(data.tipe)" />
            </template>
          </Column>

          <Column field="nama" header="Nama Satuan" sortable>
            <template #body="{ data }">
              <div class="unit-name-cell">
                <span v-if="!data.is_base_unit" class="child-arrow">↳</span>
                <span :class="{ 'font-semibold': data.is_base_unit }">{{ data.nama }}</span>
                <Tag v-if="data.is_base_unit" value="Dasar" severity="success"
                     style="font-size:0.7rem; padding: 1px 6px;" />
              </div>
            </template>
          </Column>

          <Column field="singkatan" header="Simbol" sortable style="width: 90px">
            <template #body="{ data }">
              <code class="unit-symbol-badge">{{ data.singkatan }}</code>
            </template>
          </Column>

          <Column header="Konversi" style="width: 240px">
            <template #body="{ data }">
              <div v-if="data.is_base_unit" class="conv-base-label">
                <i class="pi pi-star-fill" style="color: #f59e0b; font-size: 0.75rem;" />
                <span>Satuan dasar</span>
              </div>
              <div v-else class="conv-formula">
                <span class="conv-n">1</span>
                <span class="conv-u">{{ data.singkatan }}</span>
                <span class="conv-eq">=</span>
                <span class="conv-n">{{ formatConv(data.conversion_to_base) }}</span>
                <span class="conv-u">{{ getBaseSymbol(data.tipe) }}</span>
              </div>
            </template>
          </Column>

          <Column field="is_active" header="Status" sortable style="width: 100px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.yes') : $t('common.no')"
                   :severity="data.is_active ? 'success' : 'danger'" />
            </template>
          </Column>

          <Column :header="$t('common.actions')" style="width: 100px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-pencil" text rounded severity="info"
                        @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger"
                        @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Form Dialog -->
    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('bahanBaku.editUnit') : $t('bahanBaku.addUnit')"
            :modal="true" :style="{ width: '480px' }">
      <div class="dialog-content">

        <!-- Nama & Simbol -->
        <div class="field-row-2">
          <div class="field">
            <label>Nama Satuan *</label>
            <InputText v-model="form.nama" placeholder="contoh: Kilogram" style="width: 100%" />
          </div>
          <div class="field">
            <label>Simbol *</label>
            <InputText v-model="form.singkatan" placeholder="contoh: kg" style="width: 100%" />
          </div>
        </div>

        <!-- Tipe -->
        <div class="field">
          <label>Tipe *</label>
          <SelectButton v-model="form.tipe" :options="tipeButtonOptions"
                        optionLabel="label" optionValue="value"
                        @change="onTipeChange" style="width: 100%" />
        </div>

        <!-- Jenis: Dasar / Turunan -->
        <div class="field">
          <label>Jenis Satuan *</label>
          <div class="jenis-cards">
            <div class="jenis-card" :class="{ 'jenis-card--active': form.is_base_unit }"
                 @click="form.is_base_unit = true; form.conversion_to_base = null">
              <i class="pi pi-star" />
              <div>
                <div class="jenis-card-title">Satuan Dasar</div>
                <div class="jenis-card-desc">Acuan utama tipe ini (misal: ml untuk Volume)</div>
              </div>
            </div>
            <div class="jenis-card" :class="{ 'jenis-card--active': !form.is_base_unit }"
                 @click="form.is_base_unit = false">
              <i class="pi pi-sitemap" />
              <div>
                <div class="jenis-card-title">Satuan Turunan</div>
                <div class="jenis-card-desc">Punya konversi ke satuan dasar (misal: 1 L = 1000 ml)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Konversi (hanya jika turunan) -->
        <div v-if="!form.is_base_unit" class="field">
          <label>Konversi ke Satuan Dasar *</label>

          <Message v-if="baseUnitsOfType.length === 0" severity="warn" :closable="false">
            Belum ada satuan dasar untuk tipe <strong>{{ getTipeLabel(form.tipe) }}</strong>.
            Buat satuan dasar terlebih dahulu.
          </Message>

          <div v-else class="conv-input-row">
            <span class="conv-badge">1</span>
            <InputText v-model="form.singkatan" placeholder="simbol" style="width: 72px" />
            <span class="conv-eq-text">=</span>
            <InputNumber v-model="form.conversion_to_base" :maxFractionDigits="6"
                         :min="0.000001" placeholder="jumlah" style="width: 120px" />
            <Select v-model="selectedBaseUnitId" :options="baseUnitsOfType"
                    optionLabel="label" optionValue="value" style="width: 130px"
                    placeholder="satuan dasar" />
          </div>

          <!-- Preview real-time -->
          <div v-if="form.conversion_to_base && form.singkatan && selectedBaseSymbol"
               class="conv-preview">
            <i class="pi pi-check-circle" />
            <span>
              <strong>1 {{ form.singkatan }}</strong> = <strong>{{ formatConv(form.conversion_to_base) }} {{ selectedBaseSymbol }}</strong>
            </span>
            <span class="conv-preview-rev">
              &nbsp;· 1 {{ selectedBaseSymbol }} = {{ formatConv(1 / form.conversion_to_base) }} {{ form.singkatan }}
            </span>
          </div>
        </div>

        <!-- Info satuan dasar -->
        <Message v-if="form.is_base_unit" severity="info" :closable="false">
          Satuan dasar menjadi acuan untuk semua turunan bertipe
          <strong>{{ getTipeLabel(form.tipe) }}</strong>. Pastikan hanya ada satu per tipe.
        </Message>

        <!-- Deskripsi -->
        <div class="field">
          <label>{{ $t('bahanBaku.description') }}</label>
          <Textarea v-model="form.deskripsi" rows="2" style="width: 100%" />
        </div>

        <!-- Aktif -->
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('bahanBaku.unitActive') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveUnit" :loading="saving"
                :disabled="!canSave"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Checkbox from 'primevue/checkbox'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'

const route  = useRoute()
const toast  = useToast()
const confirm = useConfirm()
const { t }  = useI18n()

const outletId = route.params.outletId
const outlet   = ref(null)
const units    = ref([])

const searchQuery = ref('')
const filterTipe  = ref(null)
const loading  = ref(false)
const saving   = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit   = ref(false)
const selectedBaseUnitId = ref(null)

const isProcessing = computed(() => saving.value || deleting.value)

const tipeButtonOptions = [
  { label: '⚖ Berat',   value: 'weight' },
  { label: '💧 Volume',  value: 'volume' },
  { label: '📦 Jumlah', value: 'count'  },
]
const tipeOptions = [...tipeButtonOptions]

const TIPE_LABELS = { weight: 'Berat', volume: 'Volume', count: 'Jumlah' }
const getTipeLabel    = (t) => TIPE_LABELS[t] || t
const getTypeSeverity = (t) => ({ weight: 'info', volume: 'success', count: 'warn' }[t] || 'secondary')

const form = ref({
  nama: '', singkatan: '', tipe: 'weight',
  is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true
})

const breadcrumbHome  = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.units') }
])

// Daftar satuan dasar sesuai tipe yang sedang dipilih di form
const baseUnitsOfType = computed(() =>
  units.value
    .filter(u => u.tipe === form.value.tipe && u.is_base_unit && (!isEdit.value || u.id !== form.value.id))
    .map(u => ({ label: `${u.nama} (${u.singkatan})`, value: u.id, singkatan: u.singkatan }))
)

const selectedBaseSymbol = computed(() => {
  const found = baseUnitsOfType.value.find(u => u.value === selectedBaseUnitId.value)
  return found?.singkatan || ''
})

// Tampilkan simbol satuan dasar per tipe (untuk kolom tabel)
const getBaseSymbol = (tipe) => {
  const base = units.value.find(u => u.tipe === tipe && u.is_base_unit)
  return base?.singkatan || '?'
}

// Daftar flat untuk DataTable, urut: dasar dulu lalu turunan per tipe
const TIPE_ORDER = ['weight', 'volume', 'count']
const flatUnits = computed(() => {
  const q = searchQuery.value.toLowerCase()
  const filtered = units.value.filter(u => {
    const matchSearch = !q || u.nama?.toLowerCase().includes(q) || u.singkatan?.toLowerCase().includes(q)
    const matchTipe   = !filterTipe.value || u.tipe === filterTipe.value
    return matchSearch && matchTipe
  })

  return TIPE_ORDER.flatMap(tipe => {
    const group = filtered.filter(u => u.tipe === tipe)
    return [...group].sort((a, b) => {
      if (a.is_base_unit && !b.is_base_unit) return -1
      if (!a.is_base_unit && b.is_base_unit) return 1
      return a.nama.localeCompare(b.nama)
    })
  })
})

const formatConv = (val) => {
  if (!val) return '—'
  const n = Number(val)
  if (n >= 1000) return n.toLocaleString('id-ID')
  return Number.isInteger(n) ? n.toString() : n.toLocaleString('id-ID', { maximumFractionDigits: 4 })
}

const canSave = computed(() => {
  if (!form.value.nama || !form.value.singkatan || !form.value.tipe) return false
  if (!form.value.is_base_unit) {
    if (!form.value.conversion_to_base || form.value.conversion_to_base <= 0) return false
    if (baseUnitsOfType.value.length === 0) return false
  }
  return true
})

const onTipeChange = () => {
  selectedBaseUnitId.value = null
  form.value.conversion_to_base = null
  if (!form.value.is_base_unit && baseUnitsOfType.value.length === 1)
    selectedBaseUnitId.value = baseUnitsOfType.value[0].value
}

watch(() => form.value.tipe, () => {
  if (!form.value.is_base_unit && baseUnitsOfType.value.length === 1)
    selectedBaseUnitId.value = baseUnitsOfType.value[0].value
})

const resetFilter = () => { searchQuery.value = ''; filterTipe.value = null }

const fetchOutlet = async () => {
  try { outlet.value = (await api.get(`/outlets/${outletId}`)).data }
  catch (e) { console.error(e) }
}

const fetchUnits = async () => {
  loading.value = true
  try { units.value = (await api.get(`/outlets/${outletId}/satuan`)).data }
  catch (e) { toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Gagal memuat satuan', life: 3000 }) }
  finally { loading.value = false }
}

const openDialog = (unit = null) => {
  isEdit.value = !!unit
  selectedBaseUnitId.value = null
  if (unit) {
    form.value = { ...unit }
    if (!unit.is_base_unit) {
      const base = units.value.find(u => u.tipe === unit.tipe && u.is_base_unit)
      if (base) selectedBaseUnitId.value = base.id
    }
  } else {
    form.value = { nama: '', singkatan: '', tipe: 'weight', is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true }
    const bases = units.value.filter(u => u.tipe === 'weight' && u.is_base_unit)
    if (bases.length === 1) selectedBaseUnitId.value = bases[0].id
  }
  dialogVisible.value = true
}

const saveUnit = async () => {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (payload.is_base_unit) payload.conversion_to_base = null
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/satuan/${form.value.id}`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/satuan`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchUnits()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message || t('messages.error'), life: 3000 })
  } finally { saving.value = false }
}

const confirmDelete = (unit) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: unit.nama }),
    header: t('bahanBaku.deleteUnit'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteUnit(unit.id)
  })
}

const deleteUnit = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/satuan/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchUnits()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message || t('messages.error'), life: 3000 })
  } finally { deleting.value = false }
}

onMounted(() => { fetchOutlet(); fetchUnits() })
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }

.loading-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; backdrop-filter: blur(4px);
}
.loading-content {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 2rem; background: white;
  border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.loading-text { color: #1f2937; font-size: 1rem; font-weight: 600; margin: 0; }

.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }

.filter-bar {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;
}
.filter-fields { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; flex: 1; }
.filter-group  { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label  {
  font-size: 0.75rem; font-weight: 600; color: #6b7280;
  text-transform: uppercase; letter-spacing: 0.04em;
  display: flex; align-items: center; gap: 0.3rem;
}
.filter-input  { width: 220px; }
.filter-select { width: 180px; }
.filter-reset-btn { white-space: nowrap; align-self: flex-end; }

.action-buttons { display: flex; gap: 0.25rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 3rem; color: #6b7280;
}

/* Table cells */
.unit-name-cell { display: flex; align-items: center; gap: 0.5rem; }
.child-arrow    { color: #9ca3af; }
.unit-symbol-badge {
  background: #f3f4f6; color: #374151;
  padding: 2px 8px; border-radius: 4px;
  font-size: 0.8rem; font-family: monospace;
}
.conv-base-label { display: flex; align-items: center; gap: 0.4rem; color: #9ca3af; font-size: 0.8rem; font-style: italic; }
.conv-formula    { display: flex; align-items: center; gap: 0.3rem; font-size: 0.875rem; }
.conv-n  { font-weight: 600; color: #1f2937; }
.conv-u  { color: #6b7280; font-size: 0.8rem; }
.conv-eq { color: #9ca3af; }

/* Dialog */
.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }

.field { display: flex; flex-direction: column; gap: 0.5rem; }
.field label { font-weight: 600; color: #374151; }

.field-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* Jenis cards */
.jenis-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.jenis-card {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 0.875rem; border: 2px solid #e5e7eb;
  border-radius: 8px; cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.jenis-card:hover { border-color: #93c5fd; background: #f0f9ff; }
.jenis-card--active { border-color: var(--sage-primary, #5D87FF); background: #eff6ff; }
.jenis-card i { font-size: 1.2rem; color: #9ca3af; margin-top: 2px; flex-shrink: 0; }
.jenis-card--active i { color: var(--sage-primary, #5D87FF); }
.jenis-card-title { font-size: 0.875rem; font-weight: 600; color: #1f2937; }
.jenis-card-desc  { font-size: 0.75rem; color: #6b7280; margin-top: 2px; line-height: 1.4; }

/* Conversion input row */
.conv-input-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.conv-badge {
  background: #e5e7eb; color: #374151; font-weight: 700;
  width: 28px; height: 28px; display: flex; align-items: center;
  justify-content: center; border-radius: 6px; flex-shrink: 0; font-size: 0.9rem;
}
.conv-eq-text { font-size: 1.25rem; color: #9ca3af; }

/* Preview */
.conv-preview {
  display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
  padding: 0.625rem 0.875rem;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: 8px; font-size: 0.875rem; color: #166534;
}
.conv-preview i { color: #22c55e; flex-shrink: 0; }
.conv-preview-rev { color: #6b7280; font-size: 0.8rem; }
</style>
