<template>
  <div class="attendance-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('attendance.title') }}</h2>
        <p class="text-muted">{{ $t('attendance.subtitle') }}</p>
      </div>
    </div>

    <!-- Today Status Card -->
    <Card v-if="todayStatus.attendance" class="status-card">
      <template #content>
        <div class="status-grid">
          <div class="status-item">
            <i class="pi pi-calendar"></i>
            <div>
              <div class="status-label">{{ $t('common.date') }}</div>
              <div class="status-value">{{ formatDate(todayStatus.attendance.date) }}</div>
            </div>
          </div>
          <div class="status-item">
            <i class="pi pi-sign-in"></i>
            <div>
              <div class="status-label">{{ $t('attendance.clockIn') }}</div>
              <div class="status-value">{{ formatTime(todayStatus.attendance.clock_in) }}</div>
            </div>
          </div>
          <div class="status-item" v-if="todayStatus.attendance.clock_out">
            <i class="pi pi-sign-out"></i>
            <div>
              <div class="status-label">{{ $t('attendance.clockOut') }}</div>
              <div class="status-value">{{ formatTime(todayStatus.attendance.clock_out) }}</div>
            </div>
          </div>
          <div class="status-item" v-if="todayStatus.attendance.work_hours">
            <i class="pi pi-clock"></i>
            <div>
              <div class="status-label">{{ $t('attendance.workHours') }}</div>
              <div class="status-value">{{ todayStatus.attendance.work_hours }}h</div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Attendance Action Card -->
    <Card class="action-card">
      <template #content>
        <div class="action-content">
          <!-- Camera Preview -->
          <div class="camera-section">
            <div class="camera-container">
              <video ref="videoElement" autoplay playsinline class="camera-preview"></video>
              <canvas ref="canvasElement" style="display: none;"></canvas>
              <div v-if="capturedImage" class="captured-preview">
                <img :src="capturedImage" alt="Captured" />
              </div>
              <div v-if="!cameraReady && !cameraError" class="permission-overlay">
                <i class="pi pi-camera" style="font-size: 3rem; color: #9ca3af;"></i>
                <p>{{ $t('attendance.waitingCamera') }}</p>
              </div>
              <div v-if="cameraError" class="permission-overlay error">
                <i class="pi pi-times-circle" style="font-size: 3rem; color: #ef4444;"></i>
                <p>{{ cameraError }}</p>
              </div>
            </div>
            <Button
              v-if="!capturedImage"
              :label="$t('attendance.takePhoto')"
              icon="pi pi-camera"
              @click="capturePhoto"
              :disabled="!cameraReady"
              class="capture-btn"
            />
            <Button
              v-else
              :label="$t('attendance.retakePhoto')"
              icon="pi pi-refresh"
              @click="retakePhoto"
              severity="secondary"
              class="capture-btn"
            />
          </div>

          <!-- Location Info — GPS otomatis, tidak ada pilihan manual/peta -->
          <div class="location-section">
            <div class="location-header">
              <h4>{{ $t('attendance.location') }}</h4>
              <!-- Indikator status GPS -->
              <span v-if="locationLoading" class="gps-badge detecting">
                <i class="pi pi-spin pi-spinner"></i>
                {{ $t('attendance.detectingLocation') }}
              </span>
              <span v-else-if="location && !locationError" class="gps-badge ready">
                <i class="pi pi-map-marker"></i>
                GPS Aktif
              </span>
              <span v-else-if="locationError" class="gps-badge error">
                <i class="pi pi-exclamation-circle"></i>
                GPS Gagal
              </span>
            </div>

            <div class="location-info">
              <i class="pi pi-map-marker"></i>
              <div class="location-details">
                <div class="location-label">{{ $t('attendance.location') }}</div>

                <div v-if="locationLoading" class="location-value detecting">
                  <ProgressSpinner style="width: 18px; height: 18px" strokeWidth="4" />
                  <span>{{ $t('attendance.detectingLocation') }}</span>
                </div>

                <div v-else-if="locationError" class="location-value error">
                  <i class="pi pi-exclamation-triangle"></i>
                  {{ locationError }}
                  <Button
                    :label="$t('attendance.retryLocation') || 'Coba Lagi'"
                    icon="pi pi-refresh"
                    size="small"
                    text
                    @click="retryLocation"
                    class="retry-btn"
                  />
                </div>

                <div v-else-if="location" class="location-value">
                  <div class="coord-row">
                    <span class="coord-label">{{ $t('attendance.latitude') }}</span>
                    <span class="coord-val">{{ location.latitude.toFixed(6) }}</span>
                  </div>
                  <div class="coord-row">
                    <span class="coord-label">{{ $t('attendance.longitude') }}</span>
                    <span class="coord-val">{{ location.longitude.toFixed(6) }}</span>
                  </div>
                  <div class="accuracy-row">
                    <i class="pi pi-crosshairs"></i>
                    {{ $t('attendance.accuracy') }}: {{ location.accuracy.toFixed(0) }}m
                  </div>
                  <div v-if="!isWithinRadius" class="distance-warning">
                    <i class="pi pi-exclamation-triangle"></i>
                    {{ $t('attendance.outsideRadius') }} ({{ distanceFromOffice.toFixed(0) }}m)
                  </div>
                  <div v-else-if="attendanceSettings?.attendance_location_lat" class="distance-ok">
                    <i class="pi pi-check-circle"></i>
                    {{ $t('attendance.withinRadius') || 'Dalam radius absensi' }}
                  </div>
                </div>

                <div v-else class="location-value muted">
                  <i class="pi pi-info-circle"></i>
                  Menunggu sinyal GPS...
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="action-buttons">
            <Button
              v-if="!todayStatus.has_clocked_in"
              :label="$t('attendance.clockIn')"
              icon="pi pi-sign-in"
              @click="handleClockIn"
              :disabled="!canSubmit"
              :loading="submitting"
              size="large"
            />
            <Button
              v-else-if="!todayStatus.has_clocked_out"
              :label="$t('attendance.clockOut')"
              icon="pi pi-sign-out"
              severity="secondary"
              @click="handleClockOut"
              :disabled="!canSubmit"
              :loading="submitting"
              size="large"
            />
            <Message v-else severity="success" :closable="false">
              {{ $t('attendance.completedToday') }}
            </Message>
          </div>
        </div>
      </template>
    </Card>

    <!-- Attendance History — data milik user yang login saja -->
    <Card class="history-card">
      <template #header>
        <div class="card-header">
          <h3>{{ $t('attendance.history') }}</h3>
        </div>
      </template>
      <template #content>
        <DataTable :value="attendances" :loading="loading" paginator :rows="10" stripedRows>
          <Column field="date" :header="$t('common.date')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.date) }}
            </template>
          </Column>
          <Column field="clock_in" :header="$t('attendance.clockIn')" sortable>
            <template #body="{ data }">
              {{ formatTime(data.clock_in) }}
            </template>
          </Column>
          <Column field="clock_out" :header="$t('attendance.clockOut')" sortable>
            <template #body="{ data }">
              {{ formatTime(data.clock_out) }}
            </template>
          </Column>
          <Column field="work_hours" :header="$t('attendance.workHours')" sortable>
            <template #body="{ data }">
              {{ data.work_hours }}h
            </template>
          </Column>
          <Column field="status" :header="$t('common.status')">
            <template #body="{ data }">
              <Tag :value="$t(`attendance.${data.status}`)" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Permission Request Dialog -->
    <Dialog v-model:visible="permissionDialogVisible" :header="$t('attendance.permissionRequired')" modal :closable="false" :style="{ width: '500px' }">
      <div class="permission-content">
        <div class="permission-item">
          <i class="pi pi-camera permission-icon"></i>
          <div>
            <h4>{{ $t('attendance.cameraPermission') }}</h4>
            <p>{{ $t('attendance.cameraPermissionDesc') }}</p>
          </div>
        </div>
        <div class="permission-item">
          <i class="pi pi-map-marker permission-icon"></i>
          <div>
            <h4>{{ $t('attendance.locationPermission') }}</h4>
            <p>{{ $t('attendance.locationPermissionDesc') }}</p>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('attendance.allowPermissions')" @click="requestPermissions" :loading="requestingPermissions" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Dialog from 'primevue/dialog'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId
// Absensi selalu untuk user yang sedang login.
// Backend menentukan outlet_user dari auth token — tidak perlu kirim user_id eksplisit.
const userId = 'me'

const videoElement = ref(null)
const canvasElement = ref(null)
const cameraReady = ref(false)
const cameraError = ref(null)
const capturedImage = ref(null)
const location = ref(null)
const locationLoading = ref(false)
const locationError = ref(null)
const todayStatus = ref({ has_clocked_in: false, has_clocked_out: false, attendance: null })
const attendances = ref([])
const loading = ref(false)
const submitting = ref(false)
const permissionDialogVisible = ref(false)
const requestingPermissions = ref(false)
const attendanceSettings = ref(null)
let mediaStream = null
let locationWatchId = null

// ── Computed ──────────────────────────────────────────────────────────────────

const distanceFromOffice = computed(() => {
  if (!location.value || !attendanceSettings.value) return 0
  const s = attendanceSettings.value
  if (!s.attendance_location_lat || !s.attendance_location_lng) return 0
  return calculateDistance(
    location.value.latitude,
    location.value.longitude,
    s.attendance_location_lat,
    s.attendance_location_lng
  )
})

const isWithinRadius = computed(() => {
  if (!location.value || !attendanceSettings.value) return true
  const s = attendanceSettings.value
  if (!s.attendance_location_lat || !s.attendance_location_lng) return true
  return distanceFromOffice.value <= (s.attendance_radius || 100)
})

const canSubmit = computed(() => {
  return capturedImage.value && location.value && !locationError.value && isWithinRadius.value
})

// ── Camera ────────────────────────────────────────────────────────────────────

const initCamera = async () => {
  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
    })
    if (videoElement.value) {
      videoElement.value.srcObject = mediaStream
      cameraReady.value = true
      cameraError.value = null
    }
  } catch (error) {
    console.error('Camera error:', error)
    cameraError.value = t('attendance.cameraError')
    cameraReady.value = false
  }
}

const capturePhoto = () => {
  if (!videoElement.value || !canvasElement.value) return
  const video = videoElement.value
  const canvas = canvasElement.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.8)
}

const retakePhoto = () => {
  capturedImage.value = null
}

// ── Location — GPS otomatis saja, tidak ada input manual/peta ────────────────

const initLocation = () => {
  if (!navigator.geolocation) {
    locationError.value = t('attendance.geolocationNotSupported')
    return
  }

  locationLoading.value = true
  locationError.value = null

  const options = {
    enableHighAccuracy: true,
    timeout: 30000,
    maximumAge: 0
  }

  locationWatchId = navigator.geolocation.watchPosition(
    (position) => {
      locationLoading.value = false
      location.value = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: position.timestamp
      }
      locationError.value = null
    },
    (error) => {
      locationLoading.value = false
      switch (error.code) {
        case error.PERMISSION_DENIED:
          locationError.value = t('attendance.locationDenied')
          break
        case error.POSITION_UNAVAILABLE:
          locationError.value = t('attendance.locationUnavailable')
          break
        case error.TIMEOUT:
          locationError.value = t('attendance.locationTimeout')
          break
        default:
          locationError.value = t('attendance.locationError')
      }
    },
    options
  )
}

const retryLocation = () => {
  if (locationWatchId !== null) {
    navigator.geolocation.clearWatch(locationWatchId)
    locationWatchId = null
  }
  location.value = null
  initLocation()
}

// ── Permissions ───────────────────────────────────────────────────────────────

const requestPermissions = async () => {
  requestingPermissions.value = true
  try {
    await initCamera()
    initLocation()
    permissionDialogVisible.value = false
  } catch (error) {
    console.error('Permission error:', error)
  } finally {
    requestingPermissions.value = false
  }
}

// ── API ───────────────────────────────────────────────────────────────────────

const calculateDistance = (lat1, lon1, lat2, lon2) => {
  const R = 6371e3
  const φ1 = lat1 * Math.PI / 180
  const φ2 = lat2 * Math.PI / 180
  const Δφ = (lat2 - lat1) * Math.PI / 180
  const Δλ = (lon2 - lon1) * Math.PI / 180
  const a = Math.sin(Δφ / 2) ** 2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

const fetchAttendanceSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/payroll-settings`)
    attendanceSettings.value = response.data
  } catch (error) {
    console.error('Failed to fetch attendance settings:', error)
  }
}

const fetchTodayStatus = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/attendances/today/${userId}`)
    todayStatus.value = response.data
  } catch (error) {
    console.error('Failed to fetch today status:', error)
  }
}

// Fetch absensi hanya milik user yang sedang login (user_id: 'me')
const fetchAttendances = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/attendances`, {
      params: { user_id: 'me' }
    })
    attendances.value = response.data || []
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: error.response?.data?.message,
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const handleClockIn = async () => {
  if (!canSubmit.value) return
  submitting.value = true
  try {
    await api.post(`/outlets/${outletId}/attendances/clock-in`, {
      photo: capturedImage.value,
      latitude: location.value.latitude,
      longitude: location.value.longitude,
      accuracy: location.value.accuracy
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('attendance.clockInSuccess'), life: 3000 })
    capturedImage.value = null
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    submitting.value = false
  }
}

const handleClockOut = async () => {
  if (!canSubmit.value) return
  submitting.value = true
  try {
    await api.post(`/outlets/${outletId}/attendances/clock-out`, {
      photo: capturedImage.value,
      latitude: location.value.latitude,
      longitude: location.value.longitude,
      accuracy: location.value.accuracy
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('attendance.clockOutSuccess'), life: 3000 })
    capturedImage.value = null
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    submitting.value = false
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
}

const getStatusSeverity = (status) => {
  const map = { present: 'success', late: 'warn', absent: 'danger', leave: 'info', half_day: 'secondary' }
  return map[status] || 'secondary'
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
  fetchAttendanceSettings()
  fetchTodayStatus()
  fetchAttendances()
  permissionDialogVisible.value = true
})

onUnmounted(() => {
  if (mediaStream) mediaStream.getTracks().forEach(track => track.stop())
  if (locationWatchId !== null) navigator.geolocation.clearWatch(locationWatchId)
})
</script>

<style scoped>
.attendance-view {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

/* ── Page header ── */
.page-header {
  margin-bottom: 1.5rem;
}
.page-header h2 {
  margin: 0;
  font-size: 1.75rem;
  color: var(--p-text-color, #1f2937);
}
.text-muted {
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

/* ── Status card ── */
.status-card { margin-bottom: 1.5rem; }
.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}
.status-item {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.status-item i {
  font-size: 2rem;
  color: #3b82f6;
}
.status-label {
  font-size: 0.875rem;
  color: var(--p-text-muted-color, #6b7280);
}
.status-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--p-text-color, #1f2937);
}

/* ── Action card ── */
.action-card { margin-bottom: 1.5rem; }
.action-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* ── Camera ── */
.camera-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.camera-container {
  position: relative;
  width: 100%;
  max-width: 640px;
  aspect-ratio: 4/3;
  background: #000;
  border-radius: 8px;
  overflow: hidden;
}
.camera-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.captured-preview {
  position: absolute;
  inset: 0;
  background: #000;
}
.captured-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.permission-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  background: rgba(0, 0, 0, 0.8);
  color: white;
}
.permission-overlay.error {
  background: rgba(239, 68, 68, 0.1);
}
.capture-btn {
  width: 100%;
  max-width: 300px;
}

/* ── Location section — GPS only, no manual/map buttons ── */
.location-section {
  padding: 1rem;
  background: var(--p-surface-50, #f9fafb);
  border: 1px solid var(--p-surface-200, #e5e7eb);
  border-radius: 8px;
}
.location-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.location-header h4 {
  margin: 0;
  font-size: 1rem;
  color: var(--p-text-color, #1f2937);
}

/* GPS status badge */
.gps-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
}
.gps-badge.detecting {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}
.gps-badge.ready {
  background: rgba(19, 222, 185, 0.12);
  color: #0ea88a;
}
.gps-badge.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

/* Location info row */
.location-info {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}
.location-info > i {
  font-size: 1.5rem;
  color: #3b82f6;
  margin-top: 0.125rem;
  flex-shrink: 0;
}
.location-details { flex: 1; }
.location-label {
  font-size: 0.875rem;
  color: var(--p-text-muted-color, #6b7280);
  margin-bottom: 0.5rem;
}
.location-value {
  font-size: 0.875rem;
  color: var(--p-text-color, #1f2937);
}
.location-value.detecting {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--p-text-muted-color, #6b7280);
}
.location-value.error {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  color: #dc2626;
}
.location-value.muted {
  color: var(--p-text-muted-color, #9ca3af);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.retry-btn { padding: 0 !important; }

/* Coordinate rows */
.coord-row {
  display: flex;
  gap: 0.5rem;
  align-items: baseline;
  margin-bottom: 0.2rem;
}
.coord-label {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #6b7280);
  min-width: 60px;
}
.coord-val {
  font-family: monospace;
  font-size: 0.875rem;
  color: var(--p-text-color, #1f2937);
}
.accuracy-row {
  margin-top: 0.4rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #6b7280);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.distance-warning {
  margin-top: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 6px;
  color: #dc2626;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}
.distance-ok {
  margin-top: 0.5rem;
  padding: 0.4rem 0.75rem;
  background: rgba(19, 222, 185, 0.08);
  border: 1px solid rgba(19, 222, 185, 0.25);
  border-radius: 6px;
  color: #0ea88a;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

/* ── Action buttons ── */
.action-buttons {
  display: flex;
  justify-content: center;
}
.action-buttons button {
  min-width: 200px;
}

/* ── History card ── */
.history-card { margin-bottom: 1.5rem; }
.card-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--p-surface-200, #e5e7eb);
}
.card-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--p-text-color, #1f2937);
}

/* ── Permission dialog ── */
.permission-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1rem 0;
}
.permission-item {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}
.permission-icon {
  font-size: 2rem;
  color: #3b82f6;
  flex-shrink: 0;
}
.permission-item h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  color: var(--p-text-color, #1f2937);
}
.permission-item p {
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color, #6b7280);
  line-height: 1.5;
}
</style>
