<template>
  <div class="hr-view">
    <div class="page-header">
      <div>
        <h2>Approval Lembur</h2>
        <p class="text-muted">Kelola dan setujui pengajuan lembur karyawan</p>
      </div>
    </div>

    <!-- Filter Status -->
    <div class="section-header">
      <div class="filter-tabs">
        <button
          v-for="opt in statusOptions"
          :key="opt.value"
          class="tab"
          :class="{ active: filterStatus === opt.value }"
          @click="filterStatus = opt.value; loadData()"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- DataTable -->
    <DataTable
      :value="overtimeList"
      :loading="loading"
      paginator
      :rows="15"
      striped-rows
      class="mt-4"
    >
      <template #empty>
        <div class="text-center py-4 text-muted">Tidak ada data pengajuan lembur.</div>
      </template>

      <Column field="date" header="Tanggal" sortable>
        <template #body="{ data }">
          {{ formatDate(data.date) }}
        </template>
      </Column>

      <Column field="user_name" header="Karyawan" sortable />

      <Column field="work_hours" header="Jam Kerja" sortable>
        <template #body="{ data }">
          {{ data.work_hours ? Number(data.work_hours).toFixed(1) + 'h' : '-' }}
        </template>
      </Column>

      <Column field="overtime_hours" header="Lembur" sortable>
        <template #body="{ data }">
          <span class="font-semibold" style="color: #f59e0b">{{ data.overtime_hours }}h</span>
        </template>
      </Column>

      <Column field="overtime_reason" header="Alasan Lembur">
        <template #body="{ data }">
          {{ data.overtime_reason || '-' }}
        </template>
      </Column>

      <Column field="overtime_status" header="Status">
        <template #body="{ data }">
          <Tag
            :value="statusLabel(data.overtime_status)"
            :severity="statusSeverity(data.overtime_status)"
          />
        </template>
      </Column>

      <Column field="approver_name" header="Diproses Oleh">
        <template #body="{ data }">
          {{ data.approver_name || '-' }}
        </template>
      </Column>

      <Column field="overtime_notes" header="Catatan">
        <template #body="{ data }">
          {{ data.overtime_notes || '-' }}
        </template>
      </Column>

      <Column header="Aksi" style="width: 160px">
        <template #body="{ data }">
          <div v-if="data.overtime_status === 'pending_approval'" class="action-buttons">
            <Button
              icon="pi pi-check"
              size="small"
              severity="success"
              text
              rounded
              @click="confirmApprove(data)"
              v-tooltip.top="'Setujui'"
            />
            <Button
              icon="pi pi-times"
              size="small"
              severity="danger"
              text
              rounded
              @click="openRejectDialog(data)"
              v-tooltip.top="'Tolak'"
            />
          </div>
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
    </DataTable>

    <!-- Dialog Konfirmasi Approve -->
    <Dialog
      v-model:visible="approveDialog"
      header="Konfirmasi Persetujuan"
      :modal="true"
      :style="{ width: '400px' }"
    >
      <p>
        Setujui lembur <strong>{{ selectedRecord?.user_name }}</strong> sebesar
        <strong>{{ selectedRecord?.overtime_hours }} jam</strong> pada
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
      <div class="field">
        <p class="mb-3">
          Tolak lembur <strong>{{ selectedRecord?.user_name }}</strong>
          ({{ selectedRecord?.overtime_hours }} jam)?
        </p>
        <label class="font-medium">Alasan Penolakan <span class="p-error">*</span></label>
        <Textarea
          v-model="rejectionReason"
          rows="3"
          fluid
          placeholder="Masukkan alasan penolakan..."
          auto-resize
          class="mt-2"
        />
        <small v-if="rejectError" class="p-error">{{ rejectError }}</small>
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
import api from '@/services/api'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'

const route    = useRoute()
const toast    = useToast()
const outletId = route.params.outletId

// ── State ──────────────────────────────────────────────────────────────
const overtimeList    = ref([])
const loading         = ref(false)
const actionLoading   = ref(false)
const filterStatus    = ref('pending_approval')

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

// ── Helpers ────────────────────────────────────────────────────────────
const formatDate = (d) => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

const statusLabel = (s) => {
  const map = { pending_approval: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' }
  return map[s] || s
}

const statusSeverity = (s) => {
  const map = { pending_approval: 'warn', approved: 'success', rejected: 'danger' }
  return map[s] || 'secondary'
}

// ── Data ───────────────────────────────────────────────────────────────
const loadData = async () => {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get(`/outlets/${outletId}/attendances/overtime-requests`, { params })
    overtimeList.value = res.data || []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal memuat data', life: 3000 })
  } finally {
    loading.value = false
  }
}

// ── Approve ────────────────────────────────────────────────────────────
const confirmApprove = (record) => {
  selectedRecord.value = record
  approveDialog.value  = true
}

const doApprove = async () => {
  actionLoading.value = true
  try {
    await api.post(`/outlets/${outletId}/attendances/${selectedRecord.value.id}/approve-overtime`)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Lembur disetujui', life: 3000 })
    approveDialog.value = false
    loadData()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal menyetujui', life: 3000 })
  } finally {
    actionLoading.value = false
  }
}

// ── Reject ─────────────────────────────────────────────────────────────
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
    await api.post(`/outlets/${outletId}/attendances/${selectedRecord.value.id}/reject-overtime`, {
      rejection_reason: rejectionReason.value,
    })
    toast.add({ severity: 'info', summary: 'Ditolak', detail: 'Pengajuan lembur ditolak', life: 3000 })
    rejectDialog.value = false
    loadData()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || 'Gagal menolak', life: 3000 })
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => loadData())
</script>

<style scoped>
.hr-view {
  padding: 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.filter-tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid #e5e7eb;
  width: 100%;
}

.tab {
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6b7280;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: -2px;
}

.tab:hover { color: #3b82f6; }
.tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
