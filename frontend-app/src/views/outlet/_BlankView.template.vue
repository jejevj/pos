<template>
  <div class="view-container">

    <!-- ─── Breadcrumb ─────────────────────────────────────── -->
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

    <!-- ─── Loading Overlay (save/delete) ─────────────────── -->
    <div v-if="isProcessing" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <!-- ─── Main Card ──────────────────────────────────────── -->
    <Card>
      <template #title>
        <div class="card-header">
          <span>Judul Halaman</span>
          <Button label="Tambah" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>

      <template #content>
        <DataTable :value="filteredItems" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>

          <!-- Filter Bar -->
          <template #header>
            <div class="filter-bar">
              <div class="filter-fields">

                <!-- Search -->
                <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
                  <InputText v-model="searchQuery" :placeholder="$t('common.search') + '...'" class="filter-input" />
                </div>

                <!-- Contoh filter tambahan (Select) -->
                <!-- <div class="filter-group">
                  <label class="filter-label"><i class="pi pi-tag" /> Kategori</label>
                  <Select v-model="filterKategori" :options="categories" optionLabel="nama" optionValue="id"
                          placeholder="Semua" showClear class="filter-select" @change="fetchItems" />
                </div> -->

              </div>

              <!-- Tombol Reset (muncul jika ada filter aktif) -->
              <Button v-if="searchQuery" label="Reset" icon="pi pi-filter-slash"
                      severity="secondary" outlined size="small" class="filter-reset-btn"
                      @click="resetFilter" />
            </div>
          </template>

          <!-- Empty State -->
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-inbox" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>

          <!-- Kolom data — sesuaikan dengan entitas kamu -->
          <Column field="nama" header="Nama" sortable />
          <Column field="deskripsi" header="Deskripsi" />
          <Column field="is_active" header="Status" sortable style="width: 100px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.yes') : $t('common.no')"
                   :severity="data.is_active ? 'success' : 'danger'" />
            </template>
          </Column>

          <!-- Kolom Aksi -->
          <Column :header="$t('common.actions')" style="width: 110px">
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

    <!-- ─── Form Dialog (Tambah / Edit) ───────────────────── -->
    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? 'Edit Item' : 'Tambah Item'"
            :modal="true" :style="{ width: '480px' }">
      <div class="dialog-content">

        <div class="field">
          <label>Nama *</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>

        <div class="field">
          <label>Deskripsi</label>
          <Textarea v-model="form.deskripsi" rows="2" style="width: 100%" />
        </div>

        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">Aktif</label>
          </div>
        </div>

      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveItem" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
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

// PrimeVue components
import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'
// import Select from 'primevue/select'         // uncomment jika butuh dropdown filter
// import InputNumber from 'primevue/inputnumber' // uncomment jika butuh input angka

const route   = useRoute()
const toast   = useToast()
const confirm = useConfirm()
const { t }   = useI18n()

const outletId = route.params.outletId
const outlet   = ref(null)

// ─── State ────────────────────────────────────────────────
const items    = ref([])
const loading  = ref(false)
const saving   = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit   = ref(false)
const searchQuery = ref('')

const isProcessing = computed(() => saving.value || deleting.value)

// Form default — sesuaikan field dengan entitas kamu
const defaultForm = () => ({ nama: '', deskripsi: '', is_active: true })
const form = ref(defaultForm())

// ─── Breadcrumb ───────────────────────────────────────────
const breadcrumbHome  = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: 'Nama Halaman' } // ganti sesuai halaman
])

// ─── Filter ───────────────────────────────────────────────
const filteredItems = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return items.value
  return items.value.filter(i =>
    i.nama?.toLowerCase().includes(q) ||
    i.deskripsi?.toLowerCase().includes(q)
  )
})

const resetFilter = () => { searchQuery.value = '' }

// ─── API Calls ────────────────────────────────────────────
const fetchOutlet = async () => {
  try { outlet.value = (await api.get(`/outlets/${outletId}`)).data }
  catch (e) { console.error(e) }
}

const fetchItems = async () => {
  loading.value = true
  try {
    // Ganti endpoint sesuai resource
    items.value = (await api.get(`/outlets/${outletId}/ENDPOINT`)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Gagal memuat data', life: 3000 })
  } finally {
    loading.value = false
  }
}

// ─── Dialog ───────────────────────────────────────────────
const openDialog = (item = null) => {
  isEdit.value = !!item
  form.value = item ? { ...item } : defaultForm()
  dialogVisible.value = true
}

// ─── Save ─────────────────────────────────────────────────
const saveItem = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/ENDPOINT/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/ENDPOINT`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    saving.value = false
  }
}

// ─── Delete ───────────────────────────────────────────────
const confirmDelete = (item) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: item.nama }),
    header: 'Hapus Item',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteItem(item.id)
  })
}

const deleteItem = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/ENDPOINT/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    deleting.value = false
  }
}

// ─── Init ─────────────────────────────────────────────────
onMounted(() => { fetchOutlet(); fetchItems() })
</script>

<style scoped>
/* ─── Wajib ada di setiap view — jangan diubah ─────────── */
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

/* ─── Filter bar ────────────────────────────────────────── */
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
.filter-select { width: 200px; }
.filter-reset-btn { white-space: nowrap; align-self: flex-end; }

/* ─── Table ─────────────────────────────────────────────── */
.action-buttons { display: flex; gap: 0.25rem; }
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 3rem; color: #6b7280;
}

/* ─── Dialog ────────────────────────────────────────────── */
.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }
.field          { display: flex; flex-direction: column; gap: 0.5rem; }
.field label    { font-weight: 600; color: #374151; }

/* ─── Tambah style spesifik view di bawah ini ───────────── */
</style>
