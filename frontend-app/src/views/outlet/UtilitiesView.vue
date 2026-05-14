<template>
  <div class="utilities-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('utilities.title') }}</h2>
        <p class="text-muted">{{ $t('utilities.subtitle') }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button 
          class="tab" 
          :class="{ active: activeTab === 'identity' }"
          @click="activeTab = 'identity'"
        >
          <i class="pi pi-building"></i>
          {{ $t('utilities.outletIdentity') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'weather' }"
          @click="activeTab = 'weather'"
        >
          <i class="pi pi-cloud"></i>
          {{ $t('utilities.weather') }}
        </button>
      </div>
    </div>

    <!-- Outlet Identity Tab -->
    <div v-show="activeTab === 'identity'" class="tab-content">
      <div class="identity-section">
        <Card class="identity-card">
          <template #header>
            <div class="card-header">
              <h3>{{ $t('utilities.outletInformation') }}</h3>
            </div>
          </template>
          <template #content>
            <div class="identity-form">
              <!-- Logo Upload -->
              <div class="form-section">
                <h4>{{ $t('utilities.logo') }}</h4>
                <div class="logo-upload">
                  <div class="logo-preview">
                    <img v-if="outletForm.logo" :src="outletForm.logo" alt="Logo" />
                    <div v-else class="logo-placeholder">
                      <i class="pi pi-image"></i>
                      <span>{{ $t('utilities.noLogo') }}</span>
                    </div>
                  </div>
                  <div class="logo-actions">
                    <FileUpload 
                      mode="basic" 
                      accept="image/*" 
                      :maxFileSize="2000000"
                      :chooseLabel="$t('utilities.uploadLogo')"
                      @select="onLogoSelect"
                      :auto="true"
                      customUpload
                    />
                    <Button 
                      v-if="outletForm.logo" 
                      :label="$t('common.remove')" 
                      icon="pi pi-times" 
                      severity="danger" 
                      outlined 
                      size="small"
                      @click="outletForm.logo = null"
                    />
                  </div>
                </div>
              </div>

              <!-- Basic Information -->
              <div class="form-section">
                <h4>{{ $t('utilities.basicInformation') }}</h4>
                <div class="form-grid">
                  <div class="form-field">
                    <label>{{ $t('utilities.outletName') }} *</label>
                    <InputText v-model="outletForm.name" />
                  </div>
                  <div class="form-field">
                    <label>{{ $t('utilities.phone') }}</label>
                    <InputText v-model="outletForm.phone" />
                  </div>
                  <div class="form-field">
                    <label>{{ $t('utilities.email') }}</label>
                    <InputText v-model="outletForm.email" type="email" />
                  </div>
                  <div class="form-field">
                    <label>{{ $t('utilities.website') }}</label>
                    <InputText v-model="outletForm.website" />
                  </div>
                  <div class="form-field full-width">
                    <label>{{ $t('utilities.address') }}</label>
                    <Textarea v-model="outletForm.address" rows="3" />
                  </div>
                  <div class="form-field full-width">
                    <label>{{ $t('utilities.description') }}</label>
                    <Textarea v-model="outletForm.description" rows="3" />
                  </div>
                  <div class="form-field full-width">
                    <label>{{ $t('utilities.businessHours') }}</label>
                    <Textarea v-model="outletForm.business_hours" rows="2" 
                              :placeholder="$t('utilities.businessHoursPlaceholder')" />
                  </div>
                </div>
              </div>

              <!-- Location -->
              <div class="form-section">
                <h4>{{ $t('utilities.location') }}</h4>
                <div class="form-grid">
                  <div class="form-field">
                    <label>{{ $t('utilities.latitude') }}</label>
                    <InputNumber v-model="outletForm.latitude" :minFractionDigits="6" :maxFractionDigits="8" />
                  </div>
                  <div class="form-field">
                    <label>{{ $t('utilities.longitude') }}</label>
                    <InputNumber v-model="outletForm.longitude" :minFractionDigits="6" :maxFractionDigits="8" />
                  </div>
                </div>
              </div>

              <!-- Social Media -->
              <div class="form-section">
                <h4>{{ $t('utilities.socialMedia') }}</h4>
                <div class="form-grid">
                  <div class="form-field">
                    <label><i class="pi pi-facebook"></i> Facebook</label>
                    <InputText v-model="outletForm.social_media.facebook" placeholder="@username" />
                  </div>
                  <div class="form-field">
                    <label><i class="pi pi-instagram"></i> Instagram</label>
                    <InputText v-model="outletForm.social_media.instagram" placeholder="@username" />
                  </div>
                  <div class="form-field">
                    <label><i class="pi pi-twitter"></i> Twitter</label>
                    <InputText v-model="outletForm.social_media.twitter" placeholder="@username" />
                  </div>
                  <div class="form-field">
                    <label><i class="pi pi-whatsapp"></i> WhatsApp</label>
                    <InputText v-model="outletForm.social_media.whatsapp" placeholder="+62..." />
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="form-actions">
                <Button :label="$t('common.save')" icon="pi pi-check" @click="saveOutletIdentity" :loading="savingIdentity" />
                <Button :label="$t('common.cancel')" icon="pi pi-times" severity="secondary" outlined @click="loadOutletData" />
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Weather Tab -->
    <div v-show="activeTab === 'weather'" class="tab-content">
      <div class="weather-section">
        <!-- Current Weather -->
        <Card v-if="currentWeather" class="weather-card">
          <template #header>
            <div class="card-header">
              <h3>{{ $t('utilities.currentWeather') }}</h3>
              <span class="update-time">{{ $t('utilities.lastUpdate') }}: {{ formatTime(currentWeather.dt) }}</span>
            </div>
          </template>
          <template #content>
            <div class="weather-content">
              <div class="weather-main">
                <img 
                  :src="`https://openweathermap.org/img/wn/${currentWeather.weather[0].icon}@4x.png`" 
                  :alt="currentWeather.weather[0].description"
                  class="weather-icon"
                />
                <div class="weather-temp">
                  <div class="temp-value">{{ Math.round(currentWeather.main.temp) }}°C</div>
                  <div class="temp-desc">{{ currentWeather.weather[0].description }}</div>
                </div>
              </div>
              
              <div class="weather-details">
                <div class="detail-item">
                  <i class="pi pi-compass"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.feelsLike') }}</div>
                    <div class="detail-value">{{ Math.round(currentWeather.main.feels_like) }}°C</div>
                  </div>
                </div>
                <div class="detail-item">
                  <i class="pi pi-chart-line"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.humidity') }}</div>
                    <div class="detail-value">{{ currentWeather.main.humidity }}%</div>
                  </div>
                </div>
                <div class="detail-item">
                  <i class="pi pi-send"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.windSpeed') }}</div>
                    <div class="detail-value">{{ currentWeather.wind.speed }} m/s</div>
                  </div>
                </div>
                <div class="detail-item">
                  <i class="pi pi-gauge"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.pressure') }}</div>
                    <div class="detail-value">{{ currentWeather.main.pressure }} hPa</div>
                  </div>
                </div>
                <div class="detail-item">
                  <i class="pi pi-eye"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.visibility') }}</div>
                    <div class="detail-value">{{ (currentWeather.visibility / 1000).toFixed(1) }} km</div>
                  </div>
                </div>
                <div class="detail-item">
                  <i class="pi pi-cloud"></i>
                  <div>
                    <div class="detail-label">{{ $t('utilities.cloudiness') }}</div>
                    <div class="detail-value">{{ currentWeather.clouds.all }}%</div>
                  </div>
                </div>
              </div>

              <div class="sun-times">
                <div class="sun-item">
                  <i class="pi pi-sun"></i>
                  <div>
                    <div class="sun-label">{{ $t('utilities.sunrise') }}</div>
                    <div class="sun-value">{{ formatTime(currentWeather.sys.sunrise) }}</div>
                  </div>
                </div>
                <div class="sun-item">
                  <i class="pi pi-moon"></i>
                  <div>
                    <div class="sun-label">{{ $t('utilities.sunset') }}</div>
                    <div class="sun-value">{{ formatTime(currentWeather.sys.sunset) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Loading State -->
        <Card v-else-if="loading" class="loading-card">
          <template #content>
            <div class="loading-state">
              <ProgressSpinner />
              <p>{{ $t('utilities.loadingWeather') }}</p>
            </div>
          </template>
        </Card>

        <!-- Error State -->
        <Message v-else-if="error" severity="error" :closable="false">
          {{ error }}
        </Message>

        <!-- Empty State -->
        <Card v-else class="empty-card">
          <template #content>
            <div class="empty-state">
              <i class="pi pi-cloud" style="font-size: 4rem; color: #9ca3af;"></i>
              <p>{{ $t('utilities.selectCityToView') }}</p>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId
const activeTab = ref('identity')
const currentWeather = ref(null)
const loading = ref(false)
const error = ref(null)
const savingIdentity = ref(false)

// Outlet Identity Form
const outletForm = ref({
  name: '',
  logo: null,
  address: '',
  phone: '',
  email: '',
  website: '',
  description: '',
  business_hours: '',
  latitude: null,
  longitude: null,
  social_media: {
    facebook: '',
    instagram: '',
    twitter: '',
    whatsapp: ''
  }
})

// Open-Meteo API - FREE, NO API KEY REQUIRED!
// Documentation: https://open-meteo.com/

// Weather code mapping for Open-Meteo
const getWeatherDescription = (code) => {
  const weatherCodes = {
    0: { desc: 'Cerah', icon: '01d' },
    1: { desc: 'Sebagian Cerah', icon: '02d' },
    2: { desc: 'Berawan Sebagian', icon: '03d' },
    3: { desc: 'Berawan', icon: '04d' },
    45: { desc: 'Berkabut', icon: '50d' },
    48: { desc: 'Kabut Tebal', icon: '50d' },
    51: { desc: 'Gerimis Ringan', icon: '09d' },
    53: { desc: 'Gerimis', icon: '09d' },
    55: { desc: 'Gerimis Lebat', icon: '09d' },
    61: { desc: 'Hujan Ringan', icon: '10d' },
    63: { desc: 'Hujan', icon: '10d' },
    65: { desc: 'Hujan Lebat', icon: '10d' },
    71: { desc: 'Salju Ringan', icon: '13d' },
    73: { desc: 'Salju', icon: '13d' },
    75: { desc: 'Salju Lebat', icon: '13d' },
    77: { desc: 'Butiran Salju', icon: '13d' },
    80: { desc: 'Hujan Ringan', icon: '09d' },
    81: { desc: 'Hujan Sedang', icon: '09d' },
    82: { desc: 'Hujan Deras', icon: '09d' },
    85: { desc: 'Hujan Salju Ringan', icon: '13d' },
    86: { desc: 'Hujan Salju Lebat', icon: '13d' },
    95: { desc: 'Badai Petir', icon: '11d' },
    96: { desc: 'Badai Petir dengan Hujan Es', icon: '11d' },
    99: { desc: 'Badai Petir dengan Hujan Es Lebat', icon: '11d' }
  }
  return weatherCodes[code] || { desc: 'Tidak Diketahui', icon: '01d' }
}

const fetchWeather = async () => {
  if (!navigator.geolocation) {
    error.value = t('utilities.geolocationNotSupported')
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: t('utilities.geolocationNotSupported'), 
      life: 3000 
    })
    return
  }
  
  loading.value = true
  error.value = null
  
  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const lat = position.coords.latitude
      const lon = position.coords.longitude
      
      try {
        // Fetch weather for current location
        const weatherResponse = await fetch(
          `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,cloud_cover,pressure_msl,surface_pressure,wind_speed_10m,wind_direction_10m&daily=sunrise,sunset&timezone=auto`
        )
        
        if (!weatherResponse.ok) {
          throw new Error('Failed to fetch weather data')
        }
        
        const data = await weatherResponse.json()
        
        // Get city name from coordinates using Nominatim
        let cityName = t('utilities.myLocation')
        try {
          const geoResponse = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1`,
            {
              headers: {
                'Accept-Language': 'id'
              }
            }
          )
          
          if (geoResponse.ok) {
            const geoData = await geoResponse.json()
            cityName = geoData.address?.city || 
                      geoData.address?.town || 
                      geoData.address?.village || 
                      geoData.address?.county || 
                      geoData.address?.state || 
                      t('utilities.myLocation')
          }
        } catch (geoError) {
          console.warn('Failed to get city name:', geoError)
        }
        
        const weather = getWeatherDescription(data.current.weather_code)
        
        // Transform Open-Meteo data to match our display format
        currentWeather.value = {
          name: cityName,
          dt: Math.floor(new Date(data.current.time).getTime() / 1000),
          main: {
            temp: data.current.temperature_2m,
            feels_like: data.current.apparent_temperature,
            humidity: data.current.relative_humidity_2m,
            pressure: data.current.pressure_msl,
            temp_min: data.current.temperature_2m - 2,
            temp_max: data.current.temperature_2m + 2
          },
          weather: [{
            id: data.current.weather_code,
            main: weather.desc,
            description: weather.desc.toLowerCase(),
            icon: weather.icon
          }],
          wind: { 
            speed: data.current.wind_speed_10m,
            deg: data.current.wind_direction_10m 
          },
          clouds: { all: data.current.cloud_cover },
          visibility: 10000,
          sys: {
            country: '',
            sunrise: Math.floor(new Date(data.daily.sunrise[0]).getTime() / 1000),
            sunset: Math.floor(new Date(data.daily.sunset[0]).getTime() / 1000)
          }
        }
        
        loading.value = false
      } catch (err) {
        error.value = t('utilities.weatherError')
        console.error('Weather fetch error:', err)
        toast.add({ 
          severity: 'error', 
          summary: t('messages.error'), 
          detail: t('utilities.weatherError'), 
          life: 3000 
        })
        loading.value = false
      }
    },
    (err) => {
      loading.value = false
      let errorMessage = t('utilities.locationError')
      
      switch(err.code) {
        case err.PERMISSION_DENIED:
          errorMessage = t('utilities.locationDenied')
          break
        case err.POSITION_UNAVAILABLE:
          errorMessage = t('utilities.locationUnavailable')
          break
        case err.TIMEOUT:
          errorMessage = t('utilities.locationTimeout')
          break
      }
      
      error.value = errorMessage
      toast.add({ 
        severity: 'error', 
        summary: t('messages.error'), 
        detail: errorMessage, 
        life: 5000 
      })
    },
    {
      enableHighAccuracy: true,
      timeout: 30000,
      maximumAge: 0
    }
  )
}

const formatTime = (timestamp) => {
  return new Date(timestamp * 1000).toLocaleTimeString('id-ID', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

// Outlet Identity Functions
const loadOutletData = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    const outlet = response.data
    
    outletForm.value = {
      name: outlet.name || '',
      logo: outlet.logo || null,
      address: outlet.address || '',
      phone: outlet.phone || '',
      email: outlet.email || '',
      website: outlet.website || '',
      description: outlet.description || '',
      business_hours: outlet.business_hours || '',
      latitude: outlet.latitude || null,
      longitude: outlet.longitude || null,
      social_media: outlet.social_media || {
        facebook: '',
        instagram: '',
        twitter: '',
        whatsapp: ''
      }
    }
  } catch (err) {
    console.error('Failed to load outlet data:', err)
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: t('utilities.failedToLoadOutlet'), 
      life: 3000 
    })
  }
}

const onLogoSelect = (event) => {
  const file = event.files[0]
  if (file) {
    // Check file size (max 2MB)
    if (file.size > 2000000) {
      toast.add({ 
        severity: 'error', 
        summary: t('messages.error'), 
        detail: 'File terlalu besar. Maksimal 2MB', 
        life: 3000 
      })
      return
    }
    
    const reader = new FileReader()
    reader.onload = (e) => {
      outletForm.value.logo = e.target.result
      console.log('Logo encoded, size:', e.target.result.length, 'bytes')
    }
    reader.onerror = (error) => {
      console.error('Error reading file:', error)
      toast.add({ 
        severity: 'error', 
        summary: t('messages.error'), 
        detail: 'Gagal membaca file', 
        life: 3000 
      })
    }
    reader.readAsDataURL(file)
  }
}

const saveOutletIdentity = async () => {
  savingIdentity.value = true
  
  try {
    console.log('Saving outlet identity...', {
      hasLogo: !!outletForm.value.logo,
      logoSize: outletForm.value.logo?.length || 0,
      data: {
        ...outletForm.value,
        logo: outletForm.value.logo ? `[base64 ${outletForm.value.logo.length} bytes]` : null
      }
    })
    
    const response = await api.put(`/outlets/${outletId}`, outletForm.value)
    
    console.log('Save successful:', response.data)
    
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('utilities.outletUpdated'), 
      life: 3000 
    })
    
    // Reload outlet data to reflect changes
    await loadOutletData()
  } catch (err) {
    console.error('Failed to save outlet:', err)
    console.error('Error response:', err.response?.data)
    
    // Show detailed error message
    let errorMessage = t('utilities.failedToSaveOutlet')
    
    if (err.response?.data?.errors) {
      // Validation errors
      const errors = err.response.data.errors
      const errorList = Object.values(errors).flat().join(', ')
      errorMessage = errorList
    } else if (err.response?.data?.message) {
      errorMessage = err.response.data.message
    } else if (err.message) {
      errorMessage = err.message
    }
    
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: errorMessage, 
      life: 5000 
    })
  } finally {
    savingIdentity.value = false
  }
}

onMounted(() => {
  loadOutletData()
  if (activeTab.value === 'weather') {
    fetchWeather()
  }
})
</script>

<style scoped>
.utilities-view {
  padding: 1.5rem;
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

.tabs-container {
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.tabs {
  display: flex;
  gap: 0.5rem;
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

.tab:hover {
  color: #3b82f6;
}

.tab.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.weather-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.identity-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.identity-card {
  background: white;
}

.identity-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-section h4 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.logo-upload {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

.logo-preview {
  width: 150px;
  height: 150px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: #f9fafb;
}

.logo-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  color: #9ca3af;
}

.logo-placeholder i {
  font-size: 2rem;
}

.logo-placeholder span {
  font-size: 0.875rem;
}

.logo-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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
  font-size: 0.875rem;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.form-field label i {
  font-size: 1rem;
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.weather-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.card-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.update-time {
  font-size: 0.875rem;
  color: #6b7280;
}

.weather-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.weather-main {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding: 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  color: white;
}

.weather-icon {
  width: 150px;
  height: 150px;
}

.weather-temp {
  flex: 1;
}

.temp-value {
  font-size: 4rem;
  font-weight: 700;
  line-height: 1;
}

.temp-desc {
  font-size: 1.5rem;
  text-transform: capitalize;
  margin-top: 0.5rem;
}

.weather-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.detail-item i {
  font-size: 1.5rem;
  color: #3b82f6;
}

.detail-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.detail-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.sun-times {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.sun-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #fef3c7;
  border-radius: 8px;
}

.sun-item i {
  font-size: 2rem;
  color: #f59e0b;
}

.sun-label {
  font-size: 0.875rem;
  color: #92400e;
}

.sun-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: #78350f;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

@media (max-width: 768px) {
  .location-controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .city-select-input {
    width: 100%;
  }
  
  .weather-main {
    flex-direction: column;
    text-align: center;
  }
  
  .weather-details {
    grid-template-columns: 1fr;
  }
  
  .sun-times {
    flex-direction: column;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .logo-upload {
    flex-direction: column;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .form-actions button {
    width: 100%;
  }
}
</style>
