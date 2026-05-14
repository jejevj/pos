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

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('kategoriMenu.menuCategories') }}</span>
          <Button :label="$t('kategoriMenu.addCategory')" icon="pi pi-plus" @click="openDialog()" />
        </div>
      </template>
      <template #content>
        <DataTable :value="categories" :loading="loading" stripedRows showGridlines>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-list" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="nama" :header="$t('kategoriMenu.categoryName')" sortable />
          <Column field="deskripsi" :header="$t('kategoriMenu.description')" />
          <Column field="urutan" :header="$t('kategoriMenu.order')" sortable style="width: 100px" />
          <Column header="Station Dapur" style="width: 160px">
            <template #body="{ data }">
              <div v-if="data.station" class="station-badge" :style="{ background: data.station.warna + '22', color: data.station.warna, borderColor: data.station.warna }">
                <i :class="data.station.icon" style="font-size:0.8rem"></i>
                {{ data.station.nama }}
              </div>
              <span v-else class="text-muted">—</span>
            </template>
          </Column>
          <Column field="menu_count" :header="$t('kategoriMenu.menuCount')" sortable style="width: 120px" />
          <Column field="is_active" :header="$t('common.status')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')"
                   :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 120px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-pencil" text rounded severity="info" @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('kategoriMenu.editCategory') : $t('kategoriMenu.addCategory')"
            :modal="true" :style="{ width: '500px' }">
      <div class="dialog-content">
        <div class="field">
          <label>{{ $t('kategoriMenu.categoryName') }} *</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('kategoriMenu.description') }}</label>
          <Textarea v-model="form.deskripsi" rows="3" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('kategoriMenu.order') }}</label>
          <InputNumber v-model="form.urutan" style="width: 100%" />
        </div>

        <!-- Station dapur -->
        <div class="field">
          <label>Station Dapur</label>
          <small class="text-muted">Menu dalam kategori ini otomatis masuk ke station yang dipilih</small>
          <Select
            v-model="form.station_id"
            :options="stations"
            optionLabel="nama"
            optionValue="id"
            placeholder="Pilih station (opsional)"
            showClear
            style="width: 100%"
          >
            <template #option="{ option }">
              <div class="station-option">
                <span class="station-dot" :style="{ background: option.warna }"></span>
                <i :class="option.icon" style="font-size:0.9rem"></i>
                {{ option.nama }}
              </div>
            </template>
            <template #value="{ value }">
              <div v-if="value" class="station-option">
                <span class="station-dot" :style="{ background: getStation(value)?.warna }"></span>
                {{ getStation(value)?.nama }}
              </div>
              <span v-else class="text-muted">Pilih station (opsional)</span>
            </template>
          </Select>
        </div>

        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('common.active') }}</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveCategory" :loading="saving" />
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
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('kategoriMenu.menuCategories') }
])

const outlet     = ref(null)
const categories = ref([])
const stations   = ref([])
const loading    = ref(false)
const dialogVisible = ref(false)
const saving     = ref(false)
const isEdit     = ref(false)

const form = ref({ nama: '', deskripsi: '', urutan: 0, station_id: null, is_active: true })

const getStation = (id) => stations.value.find(s => s.id === id)

const fetchOutlet = async () => {
  try {
    outlet.value = (await api.get(`/outlets/${outletId}`)).data
  } catch {}
}

const fetchCategories = async () => {
  loading.value = true
  try {
    categories.value = (await api.get(`/outlets/${outletId}/kategori-menu`)).data
  } catch {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Gagal memuat kategori', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchStations = async () => {
  try {
    stations.value = (await api.get(`/outlets/${outletId}/stations`)).data.filter(s => s.is_active)
  } catch {}
}

const openDialog = (category = null) => {
  isEdit.value = !!category
  form.value = category
    ? { nama: category.nama, deskripsi: category.deskripsi, urutan: category.urutan, station_id: category.station_id, is_active: category.is_active, id: category.id }
    : { nama: '', deskripsi: '', urutan: 0, station_id: null, is_active: true }
  dialogVisible.value = true
}

const saveCategory = async () => {
  if (!form.value.nama?.trim()) {
    toast.add({ severity: 'warn', summary: 'Validasi', detail: 'Nama kategori wajib diisi', life: 3000 })
    return
  }
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/kategori-menu/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/kategori-menu`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Gagal menyimpan', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (category) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: category.nama }),
    header: t('kategoriMenu.deleteCategory'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteCategory(category.id)
  })
}

const deleteCategory = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/kategori-menu/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  }
}

onMounted(() => {
  fetchOutlet()
  fetchCategories()
  fetchStations()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }
.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.action-buttons { display: flex; gap: 0.25rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 3rem; color: #6b7280; }
.dialog-content { display: flex; flex-direction: column; gap: 1.25rem; padding: 1rem 0; }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field label { font-weight: 600; color: #374151; }
.text-muted { color: #9ca3af; font-size: 0.8rem; }

.station-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  border: 1px solid;
  font-size: 0.8rem;
  font-weight: 600;
}
.station-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.station-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
</style>
