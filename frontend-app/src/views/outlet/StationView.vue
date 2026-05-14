<template>
  <div class="station-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('station.title') }}</h2>
        <p class="text-muted">{{ $t('station.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('station.addStation')" icon="pi pi-plus" @click="openCreate" />
        <Button :label="$t('common.back')" icon="pi pi-arrow-left" text
                @click="router.push(`/outlets/${outletId}/dashboard`)" />
      </div>
    </div>

    <div class="stations-grid">
      <div v-for="station in stations" :key="station.id" class="station-card">
        <div class="station-color-bar" :style="{ background: station.warna }"></div>
        <div class="station-body">
          <div class="station-icon-wrap" :style="{ background: station.warna + '22' }">
            <i :class="station.icon" :style="{ color: station.warna }"></i>
          </div>
          <div class="station-info">
            <div class="station-name">{{ station.nama }}</div>
            <div class="station-desc">{{ station.deskripsi || '-' }}</div>
            <Tag :value="station.is_active ? $t('common.active') : $t('common.inactive')"
                 :severity="station.is_active ? 'success' : 'secondary'" size="small" />
          </div>
          <div class="station-actions">
            <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(station)" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger"
                    @click="confirmDelete(station)" />
          </div>
        </div>
      </div>

      <div v-if="stations.length === 0 && !loading" class="empty-state">
        <i class="pi pi-box"></i>
        <p>{{ $t('station.noStations') }}</p>
        <Button :label="$t('station.addStation')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="dialogVisible" :header="editingStation ? $t('station.editStation') : $t('station.addStation')"
            modal :style="{ width: '420px' }">
      <div class="form-fields">
        <div class="form-field">
          <label>{{ $t('station.name') }} *</label>
          <InputText v-model="form.nama" fluid :placeholder="$t('station.namePlaceholder')" />
        </div>
        <div class="form-field">
          <label>{{ $t('station.description') }}</label>
          <Textarea v-model="form.deskripsi" fluid rows="2" />
        </div>
        <div class="form-field">
          <label>{{ $t('station.color') }}</label>
          <div class="color-row">
            <input type="color" v-model="form.warna" class="color-picker" />
            <span class="color-value">{{ form.warna }}</span>
            <div class="color-presets">
              <button v-for="c in colorPresets" :key="c"
                      class="color-preset" :style="{ background: c }"
                      @click="form.warna = c" />
            </div>
          </div>
        </div>
        <div class="form-field">
          <label>{{ $t('station.icon') }}</label>
          <div class="icon-grid">
            <button v-for="ic in iconOptions" :key="ic.value"
                    class="icon-btn" :class="{ active: form.icon === ic.value }"
                    @click="form.icon = ic.value" :title="ic.label">
              <i :class="ic.value"></i>
            </button>
          </div>
        </div>
        <div class="form-field">
          <label>{{ $t('station.order') }}</label>
          <InputNumber v-model="form.urutan" :min="0" fluid />
        </div>
        <div class="form-field-row">
          <label>{{ $t('common.active') }}</label>
          <ToggleSwitch v-model="form.is_active" />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-check" @click="saveStation" :loading="saving" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import ToggleSwitch from 'primevue/toggleswitch'
import ConfirmDialog from 'primevue/confirmdialog'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const stations = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const editingStation = ref(null)

const colorPresets = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280']

const iconOptions = [
  { value: 'pi pi-fire', label: 'Fire' },
  { value: 'pi pi-box', label: 'Box' },
  { value: 'pi pi-shopping-bag', label: 'Bag' },
  { value: 'pi pi-star', label: 'Star' },
  { value: 'pi pi-bolt', label: 'Bolt' },
  { value: 'pi pi-home', label: 'Home' },
  { value: 'pi pi-coffee', label: 'Coffee' },
  { value: 'pi pi-heart', label: 'Heart' },
  { value: 'pi pi-tag', label: 'Tag' },
  { value: 'pi pi-wrench', label: 'Wrench' },
  { value: 'pi pi-truck', label: 'Truck' },
  { value: 'pi pi-users', label: 'Users' },
]

const defaultForm = () => ({
  nama: '', deskripsi: '', warna: '#3b82f6', icon: 'pi pi-box', urutan: 0, is_active: true
})

const form = ref(defaultForm())

const fetchStations = async () => {
  loading.value = true
  try {
    const res = await api.get(`/outlets/${outletId}/stations`)
    stations.value = res.data
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  editingStation.value = null
  form.value = defaultForm()
  dialogVisible.value = true
}

const openEdit = (station) => {
  editingStation.value = station
  form.value = { ...station }
  dialogVisible.value = true
}

const saveStation = async () => {
  if (!form.value.nama) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('station.nameRequired'), life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editingStation.value) {
      await api.put(`/outlets/${outletId}/stations/${editingStation.value.id}`, form.value)
    } else {
      await api.post(`/outlets/${outletId}/stations`, form.value)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.savedSuccessfully'), life: 3000 })
    dialogVisible.value = false
    fetchStations()
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (station) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: station.nama }),
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: t('common.delete'),
    rejectLabel: t('common.cancel'),
    accept: async () => {
      try {
        await api.delete(`/outlets/${outletId}/stations/${station.id}`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
        fetchStations()
      } catch (e) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: e.response?.data?.message, life: 3000 })
      }
    }
  })
}

onMounted(fetchStations)
</script>

<style scoped>
.station-view { padding: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }

.stations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}

.station-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.station-color-bar { height: 4px; }
.station-body { display: flex; align-items: center; gap: 1rem; padding: 1rem; }
.station-icon-wrap {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem; flex-shrink: 0;
}
.station-info { flex: 1; min-width: 0; }
.station-name { font-weight: 700; font-size: 1rem; }
.station-desc { font-size: 0.8rem; color: #6b7280; margin: 0.2rem 0 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.station-actions { display: flex; gap: 0.25rem; }

.empty-state { grid-column: 1/-1; text-align: center; padding: 3rem; color: #9ca3af; }
.empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; }

.form-fields { display: flex; flex-direction: column; gap: 1rem; }
.form-field { display: flex; flex-direction: column; gap: 0.4rem; }
.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field-row { display: flex; align-items: center; justify-content: space-between; }

.color-row { display: flex; align-items: center; gap: 0.75rem; }
.color-picker { width: 40px; height: 36px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; padding: 2px; }
.color-value { font-family: monospace; font-size: 0.875rem; color: #6b7280; }
.color-presets { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.color-preset { width: 22px; height: 22px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; transition: transform 0.1s; }
.color-preset:hover { transform: scale(1.2); border-color: #374151; }

.icon-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.icon-btn {
  width: 36px; height: 36px; border-radius: 6px; border: 1px solid #e5e7eb;
  background: white; cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 1rem; color: #374151; transition: all 0.15s;
}
.icon-btn:hover { border-color: #3b82f6; color: #3b82f6; }
.icon-btn.active { border-color: #3b82f6; background: #eff6ff; color: #3b82f6; }
</style>
