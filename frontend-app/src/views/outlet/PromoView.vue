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
          <div>
            <h3 class="m-0">{{ $t('promo.title') }}</h3>
            <p class="text-muted m-0 mt-1">{{ $t('promo.description') }}</p>
          </div>
          <Button :label="$t('promo.create')" icon="pi pi-plus" @click="showCreateDialog" />
        </div>
      </template>
      <template #content>
        <DataTable :value="promos" :loading="loading" stripedRows showGridlines>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-tag" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('promo.noPromo') }}</p>
            </div>
          </template>
          <Column field="kode" :header="$t('promo.code')" style="width: 150px" />
          <Column field="nama" :header="$t('promo.name')" />
          <Column :header="$t('promo.discount')" style="width: 150px">
            <template #body="{ data }">
              <Tag v-if="data.tipe === 'percentage'" :value="`${data.nilai}%`" severity="info" />
              <Tag v-else :value="`Rp ${formatNumber(data.nilai)}`" severity="success" />
            </template>
          </Column>
          <Column :header="$t('promo.period')" style="width: 200px">
            <template #body="{ data }">
              {{ formatDate(data.tanggal_mulai) }} - {{ formatDate(data.tanggal_selesai) }}
            </template>
          </Column>
          <Column :header="$t('promo.activeTime')" style="width: 150px">
            <template #body="{ data }">
              <span v-if="data.jam_mulai && data.jam_selesai">
                {{ data.jam_mulai.substring(0, 5) }} - {{ data.jam_selesai.substring(0, 5) }}
              </span>
              <span v-else class="text-muted">{{ $t('promo.allDay') }}</span>
            </template>
          </Column>
          <Column :header="$t('promo.quota')" style="width: 120px">
            <template #body="{ data }">
              <span v-if="data.kuota_penggunaan">
                {{ data.jumlah_terpakai }} / {{ data.kuota_penggunaan }}
              </span>
              <span v-else class="text-muted">{{ $t('promo.unlimited') }}</span>
            </template>
          </Column>
          <Column field="is_active" :header="$t('common.status')" style="width: 100px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')" 
                   :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column :header="$t('promo.options')" style="width: 150px">
            <template #body="{ data }">
              <div class="promo-badges">
                <Tag v-if="data.is_stackable" value="Stackable" severity="warn" size="small" />
                <Tag v-if="data.is_member_only" value="Member" severity="info" size="small" />
              </div>
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded @click="editPromo(data)" v-tooltip.top="$t('common.edit')" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="dialogVisible" :header="editingPromo ? $t('promo.edit') : $t('promo.create')" 
            modal :style="{ width: '800px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('promo.code') }} *</label>
          <InputText v-model="formData.kode" :placeholder="$t('promo.codeHelp')" fluid 
                     :disabled="editingPromo !== null" />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.name') }} *</label>
          <InputText v-model="formData.nama" :placeholder="$t('promo.nameHelp')" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('promo.description') }}</label>
          <Textarea v-model="formData.deskripsi" rows="2" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.discountType') }} *</label>
          <Select v-model="formData.tipe" :options="discountTypes" optionLabel="label" 
                  optionValue="value" :placeholder="$t('promo.selectType')" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.discountValue') }} *</label>
          <InputNumber v-model="formData.nilai" :minFractionDigits="0" :maxFractionDigits="2" 
                       :prefix="formData.tipe === 'nominal' ? 'Rp ' : ''" 
                       :suffix="formData.tipe === 'percentage' ? '%' : ''" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.minPurchase') }}</label>
          <InputNumber v-model="formData.minimum_pembelian" :minFractionDigits="0" 
                       :maxFractionDigits="0" prefix="Rp " fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.maxDiscount') }}</label>
          <InputNumber v-model="formData.maksimum_diskon" :minFractionDigits="0" 
                       :maxFractionDigits="0" prefix="Rp " fluid 
                       :disabled="formData.tipe !== 'percentage'" />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.startDate') }} *</label>
          <DatePicker v-model="formData.tanggal_mulai" dateFormat="yy-mm-dd" showIcon fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.endDate') }} *</label>
          <DatePicker v-model="formData.tanggal_selesai" dateFormat="yy-mm-dd" showIcon fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.startTime') }}</label>
          <InputMask v-model="formData.jam_mulai" mask="99:99" :placeholder="$t('promo.timeFormat')" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.endTime') }}</label>
          <InputMask v-model="formData.jam_selesai" mask="99:99" :placeholder="$t('promo.timeFormat')" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('promo.activeDays') }}</label>
          <MultiSelect v-model="formData.hari_aktif" :options="daysOfWeek" optionLabel="label" 
                       optionValue="value" :placeholder="$t('promo.selectDays')" fluid display="chip" />
        </div>
        <div class="form-field">
          <label>{{ $t('promo.usageQuota') }}</label>
          <InputNumber v-model="formData.kuota_penggunaan" :min="1" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('common.status') }}</label>
          <div class="flex align-items-center">
            <ToggleSwitch v-model="formData.is_active" />
            <span class="ml-2">{{ formData.is_active ? $t('common.active') : $t('common.inactive') }}</span>
          </div>
        </div>
        <div class="form-field full-width">
          <div class="promo-options">
            <div class="promo-option-item">
              <Checkbox v-model="formData.is_stackable" :binary="true" inputId="stackable" />
              <label for="stackable" class="ml-2">
                <strong>{{ $t('promo.stackable') }}</strong>
                <p class="text-muted-sm">{{ $t('promo.stackableHelp') }}</p>
              </label>
            </div>
            <div class="promo-option-item">
              <Checkbox v-model="formData.is_member_only" :binary="true" inputId="memberOnly" />
              <label for="memberOnly" class="ml-2">
                <strong>{{ $t('promo.memberOnly') }}</strong>
                <p class="text-muted-sm">{{ $t('promo.memberOnlyHelp') }}</p>
              </label>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="savePromo" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
import InputMask from 'primevue/inputmask'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('promo.title') }
])

const outlet = ref(null)
const promos = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editingPromo = ref(null)
const saving = ref(false)

const formData = ref({
  kode: '',
  nama: '',
  deskripsi: '',
  tipe: 'percentage',
  nilai: 0,
  minimum_pembelian: 0,
  maksimum_diskon: null,
  tanggal_mulai: null,
  tanggal_selesai: null,
  jam_mulai: '',
  jam_selesai: '',
  hari_aktif: [],
  kuota_penggunaan: null,
  is_active: true,
  is_stackable: false,
  is_member_only: false
})

const discountTypes = [
  { label: 'Percentage (%)', value: 'percentage' },
  { label: 'Nominal (Rp)', value: 'nominal' }
]

const daysOfWeek = [
  { label: 'Senin', value: 'senin' },
  { label: 'Selasa', value: 'selasa' },
  { label: 'Rabu', value: 'rabu' },
  { label: 'Kamis', value: 'kamis' },
  { label: 'Jumat', value: 'jumat' },
  { label: 'Sabtu', value: 'sabtu' },
  { label: 'Minggu', value: 'minggu' }
]

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error(error)
  }
}

const fetchPromos = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/promos`)
    promos.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch promos', life: 3000 })
  } finally {
    loading.value = false
  }
}

const showCreateDialog = () => {
  editingPromo.value = null
  formData.value = {
    kode: '',
    nama: '',
    deskripsi: '',
    tipe: 'percentage',
    nilai: 0,
    minimum_pembelian: 0,
    maksimum_diskon: null,
    tanggal_mulai: null,
    tanggal_selesai: null,
    jam_mulai: '',
    jam_selesai: '',
    hari_aktif: [],
    kuota_penggunaan: null,
    is_active: true,
    is_stackable: false,
    is_member_only: false
  }
  dialogVisible.value = true
}

const editPromo = (promo) => {
  editingPromo.value = promo
  formData.value = {
    kode: promo.kode,
    nama: promo.nama,
    deskripsi: promo.deskripsi,
    tipe: promo.tipe,
    nilai: promo.nilai,
    minimum_pembelian: promo.minimum_pembelian,
    maksimum_diskon: promo.maksimum_diskon,
    tanggal_mulai: new Date(promo.tanggal_mulai),
    tanggal_selesai: new Date(promo.tanggal_selesai),
    jam_mulai: promo.jam_mulai ? promo.jam_mulai.substring(0, 5) : '',
    jam_selesai: promo.jam_selesai ? promo.jam_selesai.substring(0, 5) : '',
    hari_aktif: promo.hari_aktif ? promo.hari_aktif.split(',') : [],
    kuota_penggunaan: promo.kuota_penggunaan,
    is_active: promo.is_active,
    is_stackable: promo.is_stackable || false,
    is_member_only: promo.is_member_only || false
  }
  dialogVisible.value = true
}

const savePromo = async () => {
  saving.value = true
  try {
    const payload = {
      ...formData.value,
      kode: formData.value.kode.toUpperCase(),
      tanggal_mulai: formatDateForAPI(formData.value.tanggal_mulai),
      tanggal_selesai: formatDateForAPI(formData.value.tanggal_selesai),
      jam_mulai: formData.value.jam_mulai || null,
      jam_selesai: formData.value.jam_selesai || null,
      hari_aktif: formData.value.hari_aktif.length > 0 ? formData.value.hari_aktif.join(',') : null
    }

    if (editingPromo.value) {
      await api.put(`/outlets/${outletId}/promos/${editingPromo.value.id}`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('promo.updated'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/promos`, payload)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('promo.created'), life: 3000 })
    }

    dialogVisible.value = false
    fetchPromos()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to save', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (promo) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: promo.nama }),
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deletePromo(promo.id)
  })
}

const deletePromo = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/promos/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('promo.deleted'), life: 3000 })
    fetchPromos()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to delete', life: 3000 })
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatDateForAPI = (date) => {
  if (!date) return null
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

onMounted(() => {
  fetchOutlet()
  fetchPromos()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field.full-width {
  grid-column: 1 / -1;
}

.form-field label {
  font-weight: 600;
  color: #374151;
}

.promo-options {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.promo-option-item {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.promo-option-item label {
  cursor: pointer;
  font-weight: normal;
}

.promo-option-item strong {
  display: block;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.25rem;
}

.text-muted-sm {
  color: #6b7280;
  font-size: 0.8rem;
  margin: 0;
}

.promo-badges {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}
</style>
