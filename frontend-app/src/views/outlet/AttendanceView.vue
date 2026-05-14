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

          <!-- Location Info -->
          <div class="location-section">
            <div class="location-header">
              <h4>{{ $t('attendance.location') }}</h4>
              <div class="location-actions">
                <Button 
                  :label="$t('attendance.pickFromMap')" 
                  icon="pi pi-map" 
                  size="small"
                  severity="info"
                  outlined
                  @click="openMapLocationDialog"
                />
                <Button 
                  :label="$t('attendance.enterManually')" 
                  icon="pi pi-pencil" 
                  size="small"
                  severity="secondary"
                  outlined
                  @click="openManualLocationDialog"
                />
              </div>
            </div>
            <div class="location-info">
              <i class="pi pi-map-marker"></i>
              <div>
                <div class="location-label">{{ $t('attendance.location') }}</div>
                <div v-if="locationLoading" class="location-value">
                  <ProgressSpinner style="width: 20px; height: 20px" strokeWidth="4" />
                  {{ $t('attendance.detectingLocation') }}
                </div>
                <div v-else-if="locationError" class="location-value error">
                  {{ locationError }}
                </div>
                <div v-else-if="location" class="location-value">
                  <div>{{ $t('attendance.latitude') }}: {{ location.latitude.toFixed(6) }}</div>
                  <div>{{ $t('attendance.longitude') }}: {{ location.longitude.toFixed(6) }}</div>
                  <div class="accuracy">{{ $t('attendance.accuracy') }}: {{ location.accuracy.toFixed(0) }}m</div>
                  <div v-if="!isWithinRadius" class="distance-warning">
                    <i class="pi pi-exclamation-triangle"></i>
                    {{ $t('attendance.outsideRadius') }} ({{ distanceFromOffice.toFixed(0) }}m)
                  </div>
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

    <!-- Attendance History -->
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

    <!-- Map Location Picker Dialog -->
    <Dialog v-model:visible="mapLocationDialogVisible" :header="$t('attendance.pickLocationFromMap')" modal :style="{ width: '800px' }" @hide="closeMapDialog">
      <div class="map-picker-content">
        <Message severity="info" :closable="false">
          {{ $t('attendance.mapPickerDesc') }}
        </Message>
        
        <div class="map-picker-container">
          <div id="mapPickerElement" class="map-picker"></div>
        </div>
        
        <Message v-if="attendanceSettings?.attendance_location_lat" severity="success" :closable="false">
          <i class="pi pi-building"></i> {{ $t('attendance.officeLocationShown') }}
        </Message>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="closeMapDialog" />
        <Button :label="$t('attendance.useThisLocation')" @click="applyMapLocation" />
      </template>
    </Dialog>

    <!-- Manual Location Dialog -->
    <Dialog v-model:visible="manualLocationDialogVisible" :header="$t('attendance.manualLocation')" modal :style="{ width: '500px' }">
      <div class="manual-location-content">
        <Message severity="info" :closable="false">
          {{ $t('attendance.manualLocationDesc') }}
        </Message>
        
        <div class="form-field">
          <label>{{ $t('attendance.latitude') }} *</label>
          <InputNumber v-model="manualLat" :minFractionDigits="6" :maxFractionDigits="6" fluid />
        </div>
        
        <div class="form-field">
          <label>{{ $t('attendance.longitude') }} *</label>
          <InputNumber v-model="manualLng" :minFractionDigits="6" :maxFractionDigits="6" fluid />
        </div>
        
        <Message severity="warn" :closable="false">
          {{ $t('attendance.manualLocationWarning') }}
        </Message>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="manualLocationDialogVisible = false" />
        <Button :label="$t('attendance.useThisLocation')" @click="applyManualLocation" />
      </template>
    </Dialog>

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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
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
import InputNumber from 'primevue/inputnumber'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId
const userId = 1 // TODO: Get from auth

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
const manualLocationDialogVisible = ref(false)
const manualLat = ref(null)
const manualLng = ref(null)
const mapLocationDialogVisible = ref(false)
let mediaStream = null
let locationWatchId = null
let mapPickerMap = null
let mapPickerMarker = null

const distanceFromOffice = computed(() => {
  if (!location.value || !attendanceSettings.value) return 0
  
  const settings = attendanceSettings.value
  if (!settings.attendance_location_lat || !settings.attendance_location_lng) return 0
  
  return calculateDistance(
    location.value.latitude,
    location.value.longitude,
    settings.attendance_location_lat,
    settings.attendance_location_lng
  )
})

const isWithinRadius = computed(() => {
  if (!location.value || !attendanceSettings.value) return true
  
  const settings = attendanceSettings.value
  if (!settings.attendance_location_lat || !settings.attendance_location_lng) return true
  
  return distanceFromOffice.value <= (settings.attendance_radius || 100)
})

const canSubmit = computed(() => {
  return capturedImage.value && location.value && !locationError.value && isWithinRadius.value
})

const initCamera = async () => {
  try {
    const constraints = {
      video: {
        facingMode: 'user',
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    }
    
    mediaStream = await navigator.mediaDevices.getUserMedia(constraints)
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

const initLocation = () => {
  if (!navigator.geolocation) {
    locationError.value = t('attendance.geolocationNotSupported')
    return
  }

  locationLoading.value = true

  const options = {
    enableHighAccuracy: true,  // Force GPS usage on mobile
    timeout: 30000,            // Increased timeout for better GPS lock
    maximumAge: 0              // Don't use cached location
  }

  locationWatchId = navigator.geolocation.watchPosition(
    (position) => {
      locationLoading.value = false
      
      // Accept any location for testing purposes
      // Note: In production, you may want to enable accuracy validation
      
      location.value = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: position.timestamp
      }
      locationError.value = null
      
      console.log('Location updated:', {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracy: position.coords.accuracy,
        source: position.coords.accuracy < 100 ? 'GPS' : 'WiFi/IP'
      })
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

const calculateDistance = (lat1, lon1, lat2, lon2) => {
  const R = 6371e3 // Earth radius in meters
  const φ1 = lat1 * Math.PI / 180
  const φ2 = lat2 * Math.PI / 180
  const Δφ = (lat2 - lat1) * Math.PI / 180
  const Δλ = (lon2 - lon1) * Math.PI / 180

  const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
            Math.cos(φ1) * Math.cos(φ2) *
            Math.sin(Δλ / 2) * Math.sin(Δλ / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))

  return R * c // Distance in meters
}

const fetchAttendanceSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/payroll-settings`)
    attendanceSettings.value = response.data
  } catch (error) {
    console.error('Failed to fetch attendance settings:', error)
  }
}

const capturePhoto = () => {
  if (!videoElement.value || !canvasElement.value) return

  const video = videoElement.value
  const canvas = canvasElement.value
  
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  
  const context = canvas.getContext('2d')
  context.drawImage(video, 0, 0, canvas.width, canvas.height)
  
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.8)
}

const retakePhoto = () => {
  capturedImage.value = null
}

const handleClockIn = async () => {
  if (!canSubmit.value) return

  submitting.value = true
  try {
    await api.post(`/outlets/${outletId}/attendances/clock-in`, {
      user_id: userId,
      photo: capturedImage.value,
      latitude: location.value.latitude,
      longitude: location.value.longitude,
      accuracy: location.value.accuracy
    })
    
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('attendance.clockInSuccess'), 
      life: 3000 
    })
    
    capturedImage.value = null
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: error.response?.data?.message || t('messages.error'), 
      life: 3000 
    })
  } finally {
    submitting.value = false
  }
}

const handleClockOut = async () => {
  if (!canSubmit.value) return

  submitting.value = true
  try {
    await api.post(`/outlets/${outletId}/attendances/clock-out`, {
      user_id: userId,
      photo: capturedImage.value,
      latitude: location.value.latitude,
      longitude: location.value.longitude,
      accuracy: location.value.accuracy
    })
    
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('attendance.clockOutSuccess'), 
      life: 3000 
    })
    
    capturedImage.value = null
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: error.response?.data?.message || t('messages.error'), 
      life: 3000 
    })
  } finally {
    submitting.value = false
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

const fetchAttendances = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/attendances`, { params: { user_id: userId } })
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

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const getStatusSeverity = (status) => {
  const map = { present: 'success', late: 'warn', absent: 'danger', leave: 'info', half_day: 'secondary' }
  return map[status] || 'secondary'
}

const openManualLocationDialog = () => {
  manualLat.value = location.value?.latitude || null
  manualLng.value = location.value?.longitude || null
  manualLocationDialogVisible.value = true
}

const applyManualLocation = () => {
  if (!manualLat.value || !manualLng.value) {
    toast.add({ 
      severity: 'warn', 
      summary: t('messages.warning'), 
      detail: t('attendance.enterBothCoordinates'), 
      life: 3000 
    })
    return
  }
  
  location.value = {
    latitude: manualLat.value,
    longitude: manualLng.value,
    accuracy: 10, // Set high accuracy for manual location
    timestamp: Date.now()
  }
  locationError.value = null
  locationLoading.value = false
  
  manualLocationDialogVisible.value = false
  
  toast.add({ 
    severity: 'success', 
    summary: t('messages.success'), 
    detail: t('attendance.manualLocationSet'), 
    life: 3000 
  })
  
  console.log('Manual location set:', {
    lat: manualLat.value,
    lng: manualLng.value,
    source: 'MANUAL'
  })
}

const openMapLocationDialog = async () => {
  mapLocationDialogVisible.value = true
  
  // Wait for dialog to render
  await nextTick()
  
  // Initialize map
  const mapElement = document.getElementById('mapPickerElement')
  if (!mapElement || mapPickerMap) return
  
  // Use current location or office location as default
  const defaultLat = location.value?.latitude || attendanceSettings.value?.attendance_location_lat || -6.2088
  const defaultLng = location.value?.longitude || attendanceSettings.value?.attendance_location_lng || 106.8456
  
  mapPickerMap = L.map(mapElement).setView([defaultLat, defaultLng], 15)
  
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(mapPickerMap)
  
  // Add draggable marker
  mapPickerMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(mapPickerMap)
  
  // Update coordinates when marker is dragged
  mapPickerMarker.on('dragend', (e) => {
    const position = e.target.getLatLng()
    console.log('Marker dragged to:', position.lat, position.lng)
  })
  
  // Click on map to move marker
  mapPickerMap.on('click', (e) => {
    mapPickerMarker.setLatLng(e.latlng)
    console.log('Map clicked at:', e.latlng.lat, e.latlng.lng)
  })
  
  // Add office location marker if available
  if (attendanceSettings.value?.attendance_location_lat && attendanceSettings.value?.attendance_location_lng) {
    const officeIcon = L.divIcon({
      className: 'office-marker',
      html: '<div style="background: #ef4444; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="pi pi-building" style="color: white; font-size: 14px;"></i></div>',
      iconSize: [30, 30],
      iconAnchor: [15, 15]
    })
    
    L.marker([
      attendanceSettings.value.attendance_location_lat,
      attendanceSettings.value.attendance_location_lng
    ], { icon: officeIcon }).addTo(mapPickerMap)
      .bindPopup('Office Location')
    
    // Add radius circle
    L.circle([
      attendanceSettings.value.attendance_location_lat,
      attendanceSettings.value.attendance_location_lng
    ], {
      color: '#3b82f6',
      fillColor: '#3b82f6',
      fillOpacity: 0.1,
      radius: attendanceSettings.value.attendance_radius || 100
    }).addTo(mapPickerMap)
  }
}

const applyMapLocation = () => {
  if (!mapPickerMarker) return
  
  const position = mapPickerMarker.getLatLng()
  
  location.value = {
    latitude: position.lat,
    longitude: position.lng,
    accuracy: 10,
    timestamp: Date.now()
  }
  locationError.value = null
  locationLoading.value = false
  
  // Clean up map
  if (mapPickerMap) {
    mapPickerMap.remove()
    mapPickerMap = null
    mapPickerMarker = null
  }
  
  mapLocationDialogVisible.value = false
  
  toast.add({ 
    severity: 'success', 
    summary: t('messages.success'), 
    detail: t('attendance.locationSetFromMap'), 
    life: 3000 
  })
  
  console.log('Location set from map:', {
    lat: position.lat,
    lng: position.lng,
    source: 'MAP'
  })
}

const closeMapDialog = () => {
  if (mapPickerMap) {
    mapPickerMap.remove()
    mapPickerMap = null
    mapPickerMarker = null
  }
  mapLocationDialogVisible.value = false
}

onMounted(() => {
  fetchAttendanceSettings()
  fetchTodayStatus()
  fetchAttendances()
  
  // Show permission dialog
  permissionDialogVisible.value = true
})

onUnmounted(() => {
  if (mediaStream) {
    mediaStream.getTracks().forEach(track => track.stop())
  }
  if (locationWatchId) {
    navigator.geolocation.clearWatch(locationWatchId)
  }
})
</script>

<style scoped>
.attendance-view {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h2 {
  margin: 0;
  font-size: 1.75rem;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

.status-card {
  margin-bottom: 1.5rem;
}

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
  color: #6b7280;
}

.status-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.action-card {
  margin-bottom: 1.5rem;
}

.action-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

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
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: #000;
}

.captured-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.permission-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
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

.location-section {
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.location-info {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.location-info i {
  font-size: 1.5rem;
  color: #3b82f6;
  margin-top: 0.25rem;
}

.location-label {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.location-value {
  font-size: 0.875rem;
  color: #1f2937;
}

.location-value.error {
  color: #dc2626;
}

.accuracy {
  margin-top: 0.25rem;
  color: #6b7280;
  font-size: 0.75rem;
}

.distance-warning {
  margin-top: 0.5rem;
  padding: 0.5rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 4px;
  color: #dc2626;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.action-buttons {
  display: flex;
  justify-content: center;
}

.action-buttons button {
  min-width: 200px;
}

.history-card {
  margin-bottom: 1.5rem;
}

.card-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.card-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

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
  color: #1f2937;
}

.permission-item p {
  margin: 0;
  font-size: 0.875rem;
  color: #6b7280;
  line-height: 1.5;
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
  color: #1f2937;
}

.location-actions {
  display: flex;
  gap: 0.5rem;
}

.manual-location-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.map-picker-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.map-picker-container {
  width: 100%;
  height: 500px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.map-picker {
  width: 100%;
  height: 100%;
}

.office-marker {
  background: transparent;
  border: none;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label {
  font-weight: 600;
  font-size: 0.875rem;
  color: #1f2937;
}

</style>
