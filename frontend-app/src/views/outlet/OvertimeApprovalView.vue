<template>
  <div class="overtime-approval-page p-4">
    <div class="flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="text-2xl font-bold m-0">Approval Lembur</h2>
        <p class="text-color-secondary mt-1 mb-0">Kelola pengajuan lembur karyawan</p>
      </div>
    </div>

    <!-- Filter Tab Status -->
    <div class="mb-3">
      <SelectButton
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        @change="loadOvertimeRequests"
      />
    </div>

    <!-- Tabel -->
    <DataTable
      :value="overtimeList"
      :loading="loading"
      striped-rows
      responsive-layout="scroll"
      class="p-datatable-sm"
    >
      <template #empty>
        <div class="text-center py-4 text-color-secondary">
          Tidak ada data pengajuan lembur.
        </div>
      </template>

      <Column field="date" header="Tanggal" style="min-width:110px">
        <template #body="{ data }">
          {{ formatDate(data.date) }}
        </template>
      </Column>

      <Column field="user_name" header="Karyawan" style="min-width:140px" />

      <Column header="Jam Kerja" style="min-width:110px">
        <template #body="{ data }">
          {{ data.work_hours ? Number(data.work_hours).toFixed(1) + ' jam' : '-' }}
        </template>
      </Column>

      <Column field="overtime_hours" header="Lembur" style="min-width:90px">
        <template #body="{ data }">
          <span class="font-semibold text-orange-500">{{ data.overtime_hours }} jam</span>
        </template>
      </Column>

      <Column field="overtime_reason" header="Alasan Lembur" style="min-width:180px">
        <template #body="{ data }">
          {{ data.overtime_reason || '-' }}
        </template>
      </Column>

      <Column field="overtime_status" header="Status" style="min-width:130px">
        <template #body="{ data }">
          <Tag
            :value="statusLabel(data.overtime_status)"
            :severity="statusSeverity(data.overtime_status)"
          />
        </template>
      </Column>

      <Column field="approver_name" header="Diproses Oleh" style="min-width:130px">
        <template #body="{ data }">
          {{ data.approver_name || '-' }}
        </template>
      </Column>

      <Column field="overtime_notes" header="Catatan" style="min-width:150px">
        <template #body="{ data }">
          {{ data.overtime_notes || '-' }}
        </template>
      </Column>

      <Column header="Aksi" style="min-width:160px">
        <template #body="{ data }">
          <div v-if="data.overtime_status === 'pending_approval'" class="flex gap-2">
            <Button
              label="Setujui"
              icon="pi pi-check"
              size="small"
              severity="success"
              @click="confirmApprove(data)"
            />
            <Button
              label="Tolak"
              icon="pi pi-times"
              size="small"
              severity="danger"
              outlined
              @click="openRejectDialog(data)"
            />
          </div>
          <span v-else class="text-color-secondary text-sm">—</span>
        </template>
      </Column>
    </DataTable>

    <!-- Dialog Konfirmasi Approve -->
    <Dialog
      v-model:visible="approveDialog"
      header="Konfirmasi Persetujuan Lembur"
      :modal="true"
      :style="{ width: '400px' }"
    >
      <p>
        Setujui lembur <strong>{{ selectedRecord?.user_name }}</strong> sebesar
        <strong>{{ selectedRecord?.overtime_hours }} jam</strong> pada tanggal
        <strong>{{ formatDate(selectedRecord?.date) }}</strong>?
      </p>
      <template #footer>
        <Button label="Batal" text @click="approveDialog = false" />
        <Button label="Setujui" icon="pi pi-check" severity="success" :loading="actionLoading" @click="doApprove" />
      </template>
    </Dialog>

    <!-- Dialog Tolak -->
    <Dialog
      v-model:visible="rejectDialog"
      header="Tolak Pengajuan Lembur"
      :modal="true"
      :style="{ width: '450px' }"
    >
      <div class="mb-3">
        <p class="mb-2">
          Tolak lembur <strong>{{ selectedRecord?.user_name }}</strong> ({{ selectedRecord?.overtime_hours }} jam)?
        </p>
        <label class="block font-medium mb-1">Alasan Penolakan <span class="text-red-500">*</span></label>
        <Textarea
          v-model="rejectionReason"
          rows="3"
          class="w-full"
          placeholder="Masukkan alasan penolakan..."
          auto-resize
        />
        <small v-if="rejectError" class="text-red-500">{{ rejectError }}</small>
      </div>
      <template #footer>
        <Button label="Batal" text @click="rejectDialog = false" />
        <Button label="Tolak" icon="pi pi-times" severity="danger" :loading="actionLoading" @click="doReject" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import axios from '@/utils/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import SelectButton from 'primevue/selectbutton'

const route = useRoute()
const toast = useToast()
const outletId = route.params.outletId

// ── State ──────────────────────────────────────────────────────────────────
const overtimeList  = ref([])
const loading       = ref(false)
const actionLoading = ref(false)
const filterStatus  = ref('pending_approval')

const approveDialog   = ref(false)
const rejectDialog    = ref(false)
const selectedRecord  = ref(null)
const rejectionReason = ref('')
const rejectError     = ref('')

const statusOptions = [
  { label: 'Menunggu',  value: 'pending_approval' },
  { label: 'Disetujui', value: 'approved' },
  { label: 'Ditolak',   value: 'rejected' },
  { label: 'Semua',     value: '' },
]

// ── Helpers ────────────────────────────────────────────────────────────────
const formatDate = (d) => {
  if (!d) return '-'
  const date = new Date(d)
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

const statusLabel = (s) => {
  const map = {
    pending_approval: 'Menunggu',
    approved:         'Disetujui',
    rejected:         'Ditolak',
  }
  return map[s] || s
}

const statusSeverity = (s) => {
  const map = {
    pending_approval: 'warning',
    approved:         'success',
    rejected:         'danger',
  }
  return map[s] || 'info'
}

// ── Data ───────────────────────────────────────────────────────────────────
const loadOvertimeRequests = async () => {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value) params.status = filterStatus.value
    const res = await axios.get(`/outlets/${outletId}/overtime-requests`, { params })
    overtimeList.value = res.data || []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal memuat data lembur', life: 3000 })
  } finally {
    loading.value = false
  }
}

// ── Approve ────────────────────────────────────────────────────────────────
const confirmApprove = (record) => {
  selectedRecord.value = record
  approveDialog.value = true
}

const doApprove = async () => {
  actionLoading.value = true
  try {
    await axios.post(`/outlets/${outletId}/attendances/${selectedRecord.value.id}/approve-overtime`)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Lembur disetujui', life: 3000 })
    approveDialog.value = false
    loadOvertimeRequests()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal menyetujui lembur', life: 3000 })
  } finally {
    actionLoading.value = false
  }
}

// ── Reject ─────────────────────────────────────────────────────────────────
const openRejectDialog = (record) => {
  selectedRecord.value  = record
  rejectionReason.value = ''
  rejectError.value     = ''
  rejectDialog.value    = true
}

const doReject = async () => {
  if (!rejectionReason.value.trim()) {
    rejectError.value = 'Alasan penolakan wajib diisi'
    return
  }
  actionLoading.value = true
  try {
    await axios.post(`/outlets/${outletId}/attendances/${selectedRecord.value.id}/reject-overtime`, {
      rejection_reason: rejectionReason.value,
    })
    toast.add({ severity: 'info', summary: 'Ditolak', detail: 'Pengajuan lembur ditolak', life: 3000 })
    rejectDialog.value = false
    loadOvertimeRequests()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal menolak lembur', life: 3000 })
  } finally {
    actionLoading.value = false
  }
}

// ── Init ───────────────────────────────────────────────────────────────────
onMounted(() => loadOvertimeRequests())
</script>

<style scoped>
.overtime-approval-page {
  max-width: 1200px;
}
</style>
