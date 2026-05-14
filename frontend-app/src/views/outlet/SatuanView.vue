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
        <div class="flex justify-between items-center w-full">
          <span>{{ $t('bahanBaku.units') }}</span>
          <Button :label="$t('bahanBaku.addUnit')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <!-- Filter Bar -->
        <div class="filter-bar mb-4">
          <div class="filter-group">
            <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
            <InputText v-model="searchQuery" :placeholder="$t('common.search') + '...'" style="width:220px" />
          </div>
          <div class="filter-group">
            <label class="filter-label"><i class="pi pi-tag" /> Tipe</label>
            <Select v-model="filterTipe" :options="tipeOptions" optionLabel="label" optionValue="value"
                    placeholder="Semua Tipe" showClear style="width:160px" />
          </div>
          <Button v-if="searchQuery || filterTipe" label="Reset" icon="pi pi-filter-slash"
                  severity="secondary" outlined size="small" class="align-self-end" @click="resetFilter" />
        </div>

        <!-- Grouped by type -->
        <div v-if="loading" class="flex justify-center p-6">
          <ProgressSpinner style="width:40px;height:40px" />
        </div>

        <div v-else-if="groupedUnits.length === 0" class="empty-state">
          <i class="pi pi-calculator" style="font-size: 3rem; color: #9ca3af;"></i>
          <p>{{ $t('common.noData') }}</p>
        </div>

        <div v-else class="units-groups">
          <div v-for="group in groupedUnits" :key="group.tipe" class="unit-group mb-4">
            <!-- Group Header -->
            <div class="group-header">
              <Tag :value="group.label" :severity="getTypeSeverity(group.tipe)" />
              <span class="group-count">{{ group.baseUnit ? group.baseUnit.singkatan : '' }} · {{ group.units.length }} satuan</span>
            </div>

            <!-- Units Table per group -->
            <DataTable :value="group.units" stripedRows size="small" class="group-table">
              <template #empty>
                <div class="text-center py-4 text-gray-400">Tidak ada satuan</div>
              </template>

              <Column style="width:32px">
                <template #body="{ data }">
                  <span v-if="data.is_base_unit" v-tooltip.top="'Satuan Dasar'" class="base-dot base-dot--active" />
                  <span v-else class="base-dot base-dot--child" />
                </template>
              </Column>

              <Column field="nama" header="Nama Satuan" sortable>
                <template #body="{ data }">
                  <div class="unit-name-cell">
                    <span v-if="!data.is_base_unit" class="child-indent">↳</span>
                    <span :class="data.is_base_unit ? 'font-semibold' : ''">{{ data.nama }}</span>
                    <span class="unit-symbol">{{ data.singkatan }}</span>
                    <Tag v-if="data.is_base_unit" value="Dasar" severity="success" style="font-size:0.7rem;padding:2px 6px" />
                  </div>
                </template>
              </Column>

              <Column header="Konversi" style="width:260px">
                <template #body="{ data }">
                  <div v-if="data.is_base_unit" class="conversion-display conversion-display--base">
                    <i class="pi pi-star-fill" style="color:#f59e0b;font-size:0.75rem" />
                    <span>Satuan dasar untuk {{ group.label }}</span>
                  </div>
                  <div v-else class="conversion-display">
                    <span class="conv-num">1</span>
                    <span class="conv-unit">{{ data.singkatan }}</span>
                    <span class="conv-eq">=</span>
                    <span class="conv-num">{{ formatConversion(data.conversion_to_base) }}</span>
                    <span class="conv-unit">{{ group.baseUnit?.singkatan || '?' }}</span>
                  </div>
                </template>
              </Column>

              <Column field="is_active" header="Status" style="width:90px" sortable>
                <template #body="{ data }">
                  <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                       :severity="data.is_active ? 'success' : 'danger'" />
                </template>
              </Column>

              <Column header="Aksi" style="width:90px">
                <template #body="{ data }">
                  <div class="flex gap-1">
                    <Button icon="pi pi-pencil" text rounded severity="info" size="small"
                            @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small"
                            @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </div>
      </template>
    </Card>

    <!-- Dialog Tambah/Edit -->
    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('bahanBaku.editUnit') : $t('bahanBaku.addUnit')"
            :modal="true" :style="{ width: '480px' }" :closable="!saving">
      <div class="dialog-form">

        <!-- Nama & Simbol -->
        <div class="form-row-2">
          <div class="field">
            <label class="field-label">Nama Satuan <span class="req">*</span></label>
            <InputText v-model="form.nama" placeholder="contoh: Kilogram" style="width:100%" />
          </div>
          <div class="field">
            <label class="field-label">Simbol <span class="req">*</span></label>
            <InputText v-model="form.singkatan" placeholder="contoh: kg" style="width:100%" />
          </div>
        </div>

        <!-- Tipe -->
        <div class="field">
          <label class="field-label">Tipe <span class="req">*</span></label>
          <SelectButton v-model="form.tipe" :options="tipeButtonOptions" optionLabel="label" optionValue="value"
                        @change="onTipeChange" style="width:100%" />
        </div>

        <!-- Jenis: Dasar atau Turunan -->
        <div class="field">
          <label class="field-label">Jenis Satuan <span class="req">*</span></label>
          <div class="jenis-options">
            <div class="jenis-option" :class="{ active: form.is_base_unit }" @click="form.is_base_unit = true; form.conversion_to_base = null">
              <i class="pi pi-star" />
              <div>
                <div class="jenis-title">Satuan Dasar</div>
                <div class="jenis-desc">Acuan utama untuk tipe ini (misal: ml untuk Volume)</div>
              </div>
            </div>
            <div class="jenis-option" :class="{ active: !form.is_base_unit }" @click="form.is_base_unit = false">
              <i class="pi pi-sitemap" />
              <div>
                <div class="jenis-title">Satuan Turunan</div>
                <div class="jenis-desc">Punya konversi ke satuan dasar (misal: 1 L = 1000 ml)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Konversi (hanya jika turunan) -->
        <div v-if="!form.is_base_unit" class="field">
          <label class="field-label">
            Konversi ke Satuan Dasar
            <span class="req">*</span>
          </label>

          <!-- Pilih satuan dasar tipe ini -->
          <div v-if="baseUnitsOfType.length === 0" class="p-message p-message-warn p-3 border-round mb-2">
            <i class="pi pi-exclamation-triangle mr-2" />
            Belum ada satuan dasar untuk tipe <strong>{{ getTipeLabel(form.tipe) }}</strong>. Buat satuan dasar terlebih dahulu.
          </div>

          <div v-else class="conversion-input-group">
            <div class="conv-label-row">
              <span class="conv-badge">1</span>
              <InputText v-model="form.singkatan" placeholder="simbol" style="width:80px" />
              <span class="conv-eq-text">=</span>
              <InputNumber v-model="form.conversion_to_base" :minFractionDigits="0" :maxFractionDigits="6"
                           :min="0.000001" placeholder="jumlah" style="width:130px" />
              <Select v-model="selectedBaseUnitId" :options="baseUnitsOfType"
                      optionLabel="label" optionValue="value" style="width:120px"
                      placeholder="satuan dasar" />
            </div>

            <!-- Preview real-time -->
            <div v-if="form.conversion_to_base && form.singkatan && selectedBaseSymbol" class="conversion-preview">
              <i class="pi pi-info-circle" />
              <span>
                <strong>1 {{ form.singkatan }}</strong>
                = <strong>{{ formatConversion(form.conversion_to_base) }} {{ selectedBaseSymbol }}</strong>
              </span>
              <span class="preview-reverse">
                · 1 {{ selectedBaseSymbol }} = {{ formatConversion(1 / form.conversion_to_base) }} {{ form.singkatan }}
              </span>
            </div>
          </div>
        </div>

        <!-- Info satuan dasar -->
        <div v-if="form.is_base_unit" class="info-box">
          <i class="pi pi-info-circle" />
          <span>Satuan dasar akan menjadi acuan konversi untuk semua satuan turunan bertipe <strong>{{ getTipeLabel(form.tipe) }}</strong>. Pastikan hanya ada <strong>satu</strong> satuan dasar per tipe.</span>
        </div>

        <!-- Deskripsi & Aktif -->
        <div class="field">
          <label class="field-label">Deskripsi</label>
          <Textarea v-model="form.deskripsi" rows="2" style="width:100%" placeholder="Opsional" />
        </div>

        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active" style="cursor:pointer">{{ $t('bahanBaku.unitActive') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" :disabled="saving" />
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
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const outlet = ref(null)
const units = ref([])
const searchQuery = ref('')
const filterTipe = ref(null)
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const selectedBaseUnitId = ref(null)

const isProcessing = computed(() => saving.value || deleting.value)

const tipeButtonOptions = [
  { label: '⚖ Berat', value: 'weight' },
  { label: '💧 Volume', value: 'volume' },
  { label: '📦 Jumlah', value: 'count' },
]

const tipeOptions = [
  { label: '⚖ Berat', value: 'weight' },
  { label: '💧 Volume', value: 'volume' },
  { label: '📦 Jumlah', value: 'count' },
]

const form = ref({
  nama: '', singkatan: '', tipe: 'weight',
  is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true
})

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.units') }
])

// Satuan dasar dari tipe yang dipilih (untuk dropdown konversi)
const baseUnitsOfType = computed(() => {
  return units.value
    .filter(u => u.tipe === form.value.tipe && u.is_base_unit && (!isEdit.value || u.id !== form.value.id))
    .map(u => ({ label: `${u.nama} (${u.singkatan})`, value: u.id, singkatan: u.singkatan }))
})

// Simbol satuan dasar yang dipilih
const selectedBaseSymbol = computed(() => {
  if (!selectedBaseUnitId.value) return ''
  const found = baseUnitsOfType.value.find(u => u.value === selectedBaseUnitId.value)
  return found?.singkatan || ''
})

// Grouped units per tipe
const TIPE_ORDER = ['weight', 'volume', 'count']
const TIPE_LABELS = { weight: 'Berat', volume: 'Volume', count: 'Jumlah' }

const groupedUnits = computed(() => {
  const q = searchQuery.value.toLowerCase()
  const filtered = units.value.filter(u => {
    const matchSearch = !q || u.nama?.toLowerCase().includes(q) || u.singkatan?.toLowerCase().includes(q)
    const matchTipe = !filterTipe.value || u.tipe === filterTipe.value
    return matchSearch && matchTipe
  })

  return TIPE_ORDER
    .map(tipe => {
      const group = filtered.filter(u => u.tipe === tipe)
      if (group.length === 0) return null
      // Sort: base first, then alphabetical
      const sorted = [...group].sort((a, b) => {
        if (a.is_base_unit && !b.is_base_unit) return -1
        if (!a.is_base_unit && b.is_base_unit) return 1
        return a.nama.localeCompare(b.nama)
      })
      const baseUnit = sorted.find(u => u.is_base_unit) || null
      return { tipe, label: TIPE_LABELS[tipe], units: sorted, baseUnit }
    })
    .filter(Boolean)
})

const getTypeSeverity = (type) => ({ weight: 'info', volume: 'success', count: 'warn' }[type] || 'secondary')
const getTipeLabel = (tipe) => TIPE_LABELS[tipe] || tipe

const formatConversion = (val) => {
  if (!val) return '—'
  const n = Number(val)
  if (n >= 1000) return n.toLocaleString('id-ID')
  if (Number.isInteger(n)) return n.toString()
  return n.toLocaleString('id-ID', { maximumFractionDigits: 4 })
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
  // Auto-select base unit if only one exists
  if (!form.value.is_base_unit && baseUnitsOfType.value.length === 1) {
    selectedBaseUnitId.value = baseUnitsOfType.value[0].value
  }
}

// Auto-select base unit when tipe changes
watch(() => form.value.tipe, () => {
  if (!form.value.is_base_unit && baseUnitsOfType.value.length === 1) {
    selectedBaseUnitId.value = baseUnitsOfType.value[0].value
  }
})

const resetFilter = () => {
  searchQuery.value = ''
  filterTipe.value = null
}

const fetchOutlet = async () => {
  try {
    const res = await api.get(`/outlets/${outletId}`)
    outlet.value = res.data
  } catch (e) { console.error(e) }
}

const fetchUnits = async () => {
  loading.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/satuan`)
    units.value = res.data
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Gagal memuat satuan', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (unit = null) => {
  isEdit.value = !!unit
  selectedBaseUnitId.value = null
  if (unit) {
    form.value = { ...unit }
    // Try to detect which base unit this belongs to
    if (!unit.is_base_unit) {
      const base = units.value.find(u => u.tipe === unit.tipe && u.is_base_unit)
      if (base) selectedBaseUnitId.value = base.id
    }
  } else {
    form.value = { nama: '', singkatan: '', tipe: 'weight', is_base_unit: false, conversion_to_base: null, deskripsi: '', is_active: true }
    // Auto-select if only one base unit exists for default tipe
    const bases = units.value.filter(u => u.tipe === 'weight' && u.is_base_unit)
    if (bases.length === 1) selectedBaseUnitId.value = bases[0].id
  }
  dialogVisible.value = true
}

const saveUnit = async () => {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (payload.is_base_unit) {
      payload.conversion_to_base = null
    }
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
  } finally {
    saving.value = false
  }
}

const confirmDelete = (unit) => {
  confirm.require({
    message: `Hapus satuan "${unit.nama}"? Bahan baku yang menggunakan satuan ini mungkin terpengaruh.`,
    header: t('bahanBaku.deleteUnit'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    acceptSeverity: 'danger',
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
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchOutlet()
  fetchUnits()
})
</script>

<style scoped>
.view-container { max-width: 1200px; margin: 0 auto; }

/* Loading overlay */
.loading-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.45); display: flex; align-items: center;
  justify-content: center; z-index: 9999; backdrop-filter: blur(4px);
}
.loading-content {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 2rem; background: white; border-radius: 12px;
}
.loading-text { color: #1f2937; font-size: 1rem; font-weight: 600; margin: 0; }

/* Filter bar */
.filter-bar { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label {
  font-size: 0.72rem; font-weight: 600; color: #6b7280;
  text-transform: uppercase; letter-spacing: 0.05em;
  display: flex; align-items: center; gap: 0.3rem;
}

/* Empty state */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 3rem; color: #6b7280; text-align: center;
}

/* Group layout */
.unit-group {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 1rem;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.group-count {
  font-size: 0.8rem;
  color: #9ca3af;
}

.group-table {
  border: none !important;
}

/* Base dot indicator */
.base-dot {
  display: inline-block;
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.base-dot--active { background: #22c55e; }
.base-dot--child  { background: #d1d5db; margin-left: 2px; width: 6px; height: 6px; }

/* Unit name cell */
.unit-name-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.child-indent { color: #9ca3af; font-size: 0.9rem; }
.unit-symbol {
  font-size: 0.75rem; color: #6b7280;
  background: #f3f4f6; padding: 1px 6px;
  border-radius: 4px; font-family: monospace;
}

/* Conversion display in table */
.conversion-display {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.875rem;
}
.conversion-display--base {
  color: #9ca3af;
  font-size: 0.8rem;
  font-style: italic;
  gap: 0.4rem;
}
.conv-num { font-weight: 600; color: #1f2937; }
.conv-unit { color: #6b7280; font-size: 0.8rem; }
.conv-eq { color: #9ca3af; }

/* Dialog form */
.dialog-form { display: flex; flex-direction: column; gap: 1.25rem; padding: 0.5rem 0; }

.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field-label { font-size: 0.875rem; font-weight: 600; color: #374151; }
.req { color: #ef4444; }

/* Jenis options (Dasar / Turunan) */
.jenis-options { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.jenis-option {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 0.875rem; border: 2px solid #e5e7eb;
  border-radius: 10px; cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.jenis-option:hover { border-color: #93c5fd; background: #f0f9ff; }
.jenis-option.active { border-color: var(--sage-primary, #5D87FF); background: #eff6ff; }
.jenis-option i { font-size: 1.25rem; color: #6b7280; margin-top: 2px; flex-shrink: 0; }
.jenis-option.active i { color: var(--sage-primary, #5D87FF); }
.jenis-title { font-size: 0.875rem; font-weight: 600; color: #1f2937; }
.jenis-desc { font-size: 0.75rem; color: #6b7280; margin-top: 2px; line-height: 1.4; }

/* Conversion input group */
.conversion-input-group { display: flex; flex-direction: column; gap: 0.75rem; }

.conv-label-row {
  display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}
.conv-badge {
  background: #e5e7eb; color: #374151;
  font-weight: 700; font-size: 1rem;
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px; flex-shrink: 0;
}
.conv-eq-text { font-size: 1.25rem; color: #6b7280; font-weight: 300; }

/* Preview box */
.conversion-preview {
  display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
  padding: 0.625rem 0.875rem;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: 8px; font-size: 0.875rem; color: #166534;
}
.conversion-preview i { color: #22c55e; flex-shrink: 0; }
.preview-reverse { color: #6b7280; font-size: 0.8rem; }

/* Info box */
.info-box {
  display: flex; align-items: flex-start; gap: 0.625rem;
  padding: 0.75rem 1rem;
  background: #eff6ff; border: 1px solid #bfdbfe;
  border-radius: 8px; font-size: 0.8rem; color: #1e40af;
  line-height: 1.5;
}
.info-box i { color: #3b82f6; flex-shrink: 0; margin-top: 2px; }
</style>
