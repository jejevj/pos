<template>
  <div class="mod-dashboard">

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-left">
        <h4 class="page-title">{{ outlet?.name || 'Dashboard' }}</h4>
        <p class="page-subtitle">{{ currentDate }}</p>
      </div>
      <div class="header-actions">
        <span class="clock-badge"><i class="pi pi-clock"></i> {{ currentTime }}</span>
        <Button icon="pi pi-pencil" label="Edit Outlet" outlined size="small" @click="navigateTo('outlet-utilities')" />
      </div>
    </div>

    <!-- KPI Row -->
    <div class="kpi-row">
      <!-- Transaksi Hari Ini -->
      <div class="kpi-card" @click="navigateTo('outlet-transactions')">
        <div class="kpi-icon" style="background:#ECF2FF">
          <i class="pi pi-receipt" style="color:#5D87FF"></i>
        </div>
        <div class="kpi-info">
          <div class="kpi-value">{{ stats.transactions }}</div>
          <div class="kpi-label">Transaksi Hari Ini</div>
        </div>
        <div class="kpi-trend up"><i class="pi pi-arrow-up"></i></div>
      </div>

      <!-- Total Menu -->
      <div class="kpi-card" @click="navigateTo('outlet-menu')">
        <div class="kpi-icon" style="background:#E6FFFA">
          <i class="pi pi-book" style="color:#13DEB9"></i>
        </div>
        <div class="kpi-info">
          <div class="kpi-value">{{ stats.menus }}</div>
          <div class="kpi-label">Total Menu</div>
        </div>
        <div class="kpi-trend up"><i class="pi pi-arrow-up"></i></div>
      </div>

      <!-- Total Member -->
      <div class="kpi-card" @click="navigateTo('outlet-members')">
        <div class="kpi-icon" style="background:#FEF5E5">
          <i class="pi pi-id-card" style="color:#FFAE1F"></i>
        </div>
        <div class="kpi-info">
          <div class="kpi-value">{{ stats.members }}</div>
          <div class="kpi-label">Total Member</div>
        </div>
        <div class="kpi-trend up"><i class="pi pi-arrow-up"></i></div>
      </div>

      <!-- Stok Menipis -->
      <div class="kpi-card" @click="navigateTo('outlet-bahan-baku')">
        <div class="kpi-icon" style="background:#FDEDE8">
          <i class="pi pi-exclamation-triangle" style="color:#FA896B"></i>
        </div>
        <div class="kpi-info">
          <div class="kpi-value">{{ stats.lowStock }}</div>
          <div class="kpi-label">Stok Menipis</div>
        </div>
        <div class="kpi-trend down"><i class="pi pi-arrow-down"></i></div>
      </div>
    </div>

    <!-- Middle Row -->
    <div class="middle-row">

      <!-- Left: Quick Access Grid -->
      <div class="quick-access-panel">
        <div class="panel-header">
          <span class="panel-title">Akses Cepat</span>
        </div>
        <div class="menu-grid">

          <div class="menu-card" @click="navigateTo('outlet-pos')">
            <div class="menu-icon" style="background:#5D87FF">
              <i class="pi pi-shopping-cart" style="color:white"></i>
            </div>
            <span class="menu-label">POS / Kasir</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-transactions')">
            <div class="menu-icon" style="background:#49BEFF">
              <i class="pi pi-receipt" style="color:white"></i>
            </div>
            <span class="menu-label">Transaksi</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-bahan-baku')">
            <div class="menu-icon" style="background:#13DEB9">
              <i class="pi pi-box" style="color:white"></i>
            </div>
            <span class="menu-label">Bahan Baku</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-stock-opname')">
            <div class="menu-icon" style="background:#0A7EA4">
              <i class="pi pi-clipboard" style="color:white"></i>
            </div>
            <span class="menu-label">Stock Opname</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-purchases')">
            <div class="menu-icon" style="background:#FFAE1F">
              <i class="pi pi-truck" style="color:white"></i>
            </div>
            <span class="menu-label">Barang Masuk</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-menu')">
            <div class="menu-icon" style="background:#FA896B">
              <i class="pi pi-book" style="color:white"></i>
            </div>
            <span class="menu-label">Menu</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-promos')">
            <div class="menu-icon" style="background:#763EBD">
              <i class="pi pi-tag" style="color:white"></i>
            </div>
            <span class="menu-label">Promo</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-members')">
            <div class="menu-icon" style="background:#01C0C8">
              <i class="pi pi-id-card" style="color:white"></i>
            </div>
            <span class="menu-label">Member</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-hr')">
            <div class="menu-icon" style="background:#5D87FF">
              <i class="pi pi-users" style="color:white"></i>
            </div>
            <span class="menu-label">HR / Karyawan</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-shifts')">
            <div class="menu-icon" style="background:#49BEFF">
              <i class="pi pi-calendar" style="color:white"></i>
            </div>
            <span class="menu-label">Shift</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-attendance')">
            <div class="menu-icon" style="background:#13DEB9">
              <i class="pi pi-clock" style="color:white"></i>
            </div>
            <span class="menu-label">Absensi</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-expenses')">
            <div class="menu-icon" style="background:#FA896B">
              <i class="pi pi-wallet" style="color:white"></i>
            </div>
            <span class="menu-label">Pengeluaran</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-reports')">
            <div class="menu-icon" style="background:#FFAE1F">
              <i class="pi pi-chart-bar" style="color:white"></i>
            </div>
            <span class="menu-label">Laporan</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-payment-methods')">
            <div class="menu-icon" style="background:#6366f1">
              <i class="pi pi-credit-card" style="color:white"></i>
            </div>
            <span class="menu-label">Metode Pembayaran</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-stock-locations')">
            <div class="menu-icon" style="background:#0891b2">
              <i class="pi pi-building" style="color:white"></i>
            </div>
            <span class="menu-label">Lokasi Stok</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-whatsapp')">
            <div class="menu-icon" style="background:#25d366">
              <i class="pi pi-whatsapp" style="color:white"></i>
            </div>
            <span class="menu-label">WhatsApp</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-utilities')">
            <div class="menu-icon" style="background:#7C8FAC">
              <i class="pi pi-wrench" style="color:white"></i>
            </div>
            <span class="menu-label">Utilitas</span>
          </div>

          <div class="menu-card" @click="navigateTo('outlet-rbac')">
            <div class="menu-icon" style="background:#763EBD">
              <i class="pi pi-shield" style="color:white"></i>
            </div>
            <span class="menu-label">Pengaturan RBAC</span>
          </div>

        </div>
      </div>

      <!-- Right: Info Panel -->
      <div class="info-panel">

        <!-- Weather Widget -->
        <div class="info-card weather-card" @click="navigateTo('outlet-utilities')">
          <div class="info-card-header">
            <span class="info-card-title"><i class="pi pi-cloud"></i> Cuaca</span>
          </div>
          <div v-if="weather" class="weather-content">
            <div class="weather-main">
              <img
                :src="`https://openweathermap.org/img/wn/${weather.weather[0].icon}@2x.png`"
                class="weather-icon-img"
                alt="weather"
              />
              <div class="weather-temp">{{ Math.round(weather.main.temp) }}°C</div>
            </div>
            <div class="weather-desc">{{ weather.weather[0].description }}</div>
            <div class="weather-city"><i class="pi pi-map-marker"></i> {{ weather.name }}</div>
          </div>
          <div v-else class="weather-content weather-loading">
            <i class="pi pi-spin pi-spinner"></i>
            <span>Memuat cuaca...</span>
          </div>
        </div>

        <!-- Outlet Info -->
        <div class="info-card outlet-info-card">
          <div class="info-card-header">
            <span class="info-card-title"><i class="pi pi-building"></i> Info Outlet</span>
          </div>
          <ul class="outlet-info-list">
            <li v-if="outlet?.address">
              <i class="pi pi-map-marker"></i>
              <span>{{ outlet.address }}</span>
            </li>
            <li v-if="outlet?.phone">
              <i class="pi pi-phone"></i>
              <span>{{ outlet.phone }}</span>
            </li>
            <li v-if="outlet?.email">
              <i class="pi pi-envelope"></i>
              <span>{{ outlet.email }}</span>
            </li>
            <li v-if="outlet?.business_hours">
              <i class="pi pi-clock"></i>
              <span>{{ outlet.business_hours }}</span>
            </li>
            <li v-if="!outlet?.address && !outlet?.phone && !outlet?.email" class="info-empty">
              <i class="pi pi-info-circle"></i>
              <span>Belum ada info outlet</span>
            </li>
          </ul>
        </div>

        <!-- Stock Status Mini Chart -->
        <div class="info-card stock-mini-card">
          <div class="info-card-header">
            <span class="info-card-title"><i class="pi pi-chart-bar"></i> Status Stok</span>
          </div>
          <div class="stock-bars">
            <div class="stock-bar-item">
              <div class="stock-bar-label">
                <span>Tersedia</span>
                <span class="stock-bar-count">{{ stats.inStock }}</span>
              </div>
              <div class="stock-bar-track">
                <div
                  class="stock-bar-fill"
                  style="background:#13DEB9"
                  :style="{ width: stats.materials > 0 ? (stats.inStock / stats.materials * 100) + '%' : '0%' }"
                ></div>
              </div>
            </div>
            <div class="stock-bar-item">
              <div class="stock-bar-label">
                <span>Menipis</span>
                <span class="stock-bar-count">{{ stats.lowStock }}</span>
              </div>
              <div class="stock-bar-track">
                <div
                  class="stock-bar-fill"
                  style="background:#FFAE1F"
                  :style="{ width: stats.materials > 0 ? (stats.lowStock / stats.materials * 100) + '%' : '0%' }"
                ></div>
              </div>
            </div>
            <div class="stock-bar-item">
              <div class="stock-bar-label">
                <span>Habis</span>
                <span class="stock-bar-count">{{ stats.outOfStock }}</span>
              </div>
              <div class="stock-bar-track">
                <div
                  class="stock-bar-fill"
                  style="background:#FA896B"
                  :style="{ width: stats.materials > 0 ? (stats.outOfStock / stats.materials * 100) + '%' : '0%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Bottom Summary Row -->
    <div class="summary-row">

      <!-- Inventori -->
      <div class="summary-card">
        <div class="summary-card-header">
          <div class="summary-icon" style="background:#E6FFFA">
            <i class="pi pi-box" style="color:#13DEB9"></i>
          </div>
          <div>
            <div class="summary-title">Inventori</div>
            <div class="summary-subtitle">Bahan Baku</div>
          </div>
        </div>
        <div class="summary-stats">
          <div class="summary-stat">
            <span class="summary-stat-value">{{ stats.materials }}</span>
            <span class="summary-stat-label">Total Bahan</span>
          </div>
          <div class="summary-stat">
            <span class="summary-stat-value" style="color:#13DEB9">{{ stats.inStock }}</span>
            <span class="summary-stat-label">Tersedia</span>
          </div>
          <div class="summary-stat">
            <span class="summary-stat-value" style="color:#FFAE1F">{{ stats.lowStock }}</span>
            <span class="summary-stat-label">Menipis</span>
          </div>
          <div class="summary-stat">
            <span class="summary-stat-value" style="color:#FA896B">{{ stats.outOfStock }}</span>
            <span class="summary-stat-label">Habis</span>
          </div>
        </div>
      </div>

      <!-- SDM -->
      <div class="summary-card">
        <div class="summary-card-header">
          <div class="summary-icon" style="background:#ECF2FF">
            <i class="pi pi-users" style="color:#5D87FF"></i>
          </div>
          <div>
            <div class="summary-title">SDM</div>
            <div class="summary-subtitle">Sumber Daya Manusia</div>
          </div>
        </div>
        <div class="summary-stats">
          <div class="summary-stat">
            <span class="summary-stat-value">{{ stats.users }}</span>
            <span class="summary-stat-label">Karyawan</span>
          </div>
          <div class="summary-stat">
            <span class="summary-stat-value">{{ stats.shifts }}</span>
            <span class="summary-stat-label">Shift</span>
          </div>
        </div>
      </div>

      <!-- Keuangan -->
      <div class="summary-card">
        <div class="summary-card-header">
          <div class="summary-icon" style="background:#FEF5E5">
            <i class="pi pi-wallet" style="color:#FFAE1F"></i>
          </div>
          <div>
            <div class="summary-title">Keuangan</div>
            <div class="summary-subtitle">Pembelian & Pengeluaran</div>
          </div>
        </div>
        <div class="summary-stats">
          <div class="summary-stat">
            <span class="summary-stat-value">{{ stats.purchases }}</span>
            <span class="summary-stat-label">Pembelian</span>
          </div>
          <div class="summary-stat">
            <span class="summary-stat-value">{{ stats.expenses }}</span>
            <span class="summary-stat-label">Pengeluaran</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Back Button -->
    <div class="back-row">
      <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" text @click="router.push({ name: 'outlets' })" />
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Chip from 'primevue/chip'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId || route.meta.defaultOutletId || 1
const outlet = ref(null)
const currentTime = ref('')
const currentDate = ref('')
const weather = ref(null)
const stats = ref({
  categories: 0,
  units: 0,
  suppliers: 0,
  materials: 0,
  menuCategories: 0,
  menus: 0,
  promos: 0,
  tables: 0,
  members: 0,
  users: 0,
  stations: 0,
  shifts: 0,
  purchases: 0,
  expenses: 0,
  transactions: 0,
  inStock: 0,
  lowStock: 0,
  outOfStock: 0
})

const navigateTo = (routeName) => {
  const id = route.params.outletId || route.meta.defaultOutletId || 1
  router.push({ name: routeName, params: { outletId: id } })
}

const openLink = (url) => {
  window.open(url, '_blank')
}

const hasSocialMedia = computed(() => {
  if (!outlet.value?.social_media) return false
  const sm = outlet.value.social_media
  return sm.facebook || sm.instagram || sm.twitter || sm.whatsapp
})

const updateClock = () => {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('id-ID', { 
    hour: '2-digit', 
    minute: '2-digit',
    second: '2-digit'
  })
  currentDate.value = now.toLocaleDateString('id-ID', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
}

const fetchWeather = async () => {
  // Try to get user's current location first
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const lat = position.coords.latitude
        const lon = position.coords.longitude
        
        try {
          // Fetch weather for current location
          const weatherResponse = await fetch(
            `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,cloud_cover,pressure_msl,wind_speed_10m&daily=sunrise,sunset&timezone=auto`
          )
          
          if (!weatherResponse.ok) {
            throw new Error('Failed to fetch weather')
          }
          
          const weatherData = await weatherResponse.json()
          
          // Get city name from coordinates using Nominatim (OpenStreetMap)
          let cityName = 'Lokasi Anda'
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
              // Try to get city name from various address components
              cityName = geoData.address?.city || 
                        geoData.address?.town || 
                        geoData.address?.village || 
                        geoData.address?.county || 
                        geoData.address?.state || 
                        'Lokasi Anda'
            }
          } catch (geoError) {
            console.warn('Failed to get city name:', geoError)
          }
          
          const getWeatherInfo = (code) => {
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
              80: { desc: 'Hujan Ringan', icon: '09d' },
              81: { desc: 'Hujan Sedang', icon: '09d' },
              82: { desc: 'Hujan Deras', icon: '09d' },
              95: { desc: 'Badai Petir', icon: '11d' }
            }
            return weatherCodes[code] || { desc: 'Cerah', icon: '01d' }
          }
          
          const weatherInfo = getWeatherInfo(weatherData.current.weather_code)
          
          weather.value = {
            name: cityName,
            main: {
              temp: weatherData.current.temperature_2m,
              feels_like: weatherData.current.apparent_temperature,
              humidity: weatherData.current.relative_humidity_2m,
              pressure: weatherData.current.pressure_msl
            },
            weather: [{
              main: weatherInfo.desc,
              description: weatherInfo.desc.toLowerCase(),
              icon: weatherInfo.icon
            }],
            wind: {
              speed: weatherData.current.wind_speed_10m
            },
            clouds: {
              all: weatherData.current.cloud_cover
            }
          }
        } catch (error) {
          console.error('Failed to fetch weather:', error)
          // Fallback to Jakarta if location-based weather fails
          fetchWeatherForJakarta()
        }
      },
      (error) => {
        console.warn('Geolocation error:', error)
        // Fallback to Jakarta if geolocation fails
        fetchWeatherForJakarta()
      },
      {
        enableHighAccuracy: false,
        timeout: 10000,
        maximumAge: 300000 // Cache for 5 minutes
      }
    )
  } else {
    // Fallback to Jakarta if geolocation not supported
    fetchWeatherForJakarta()
  }
}

const fetchWeatherForJakarta = async () => {
  try {
    // Jakarta coordinates
    const lat = -6.2088
    const lon = 106.8456
    
    const response = await fetch(
      `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,cloud_cover,pressure_msl,wind_speed_10m&daily=sunrise,sunset&timezone=Asia/Jakarta`
    )
    
    if (response.ok) {
      const data = await response.json()
      
      const getWeatherInfo = (code) => {
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
          80: { desc: 'Hujan Ringan', icon: '09d' },
          81: { desc: 'Hujan Sedang', icon: '09d' },
          82: { desc: 'Hujan Deras', icon: '09d' },
          95: { desc: 'Badai Petir', icon: '11d' }
        }
        return weatherCodes[code] || { desc: 'Cerah', icon: '01d' }
      }
      
      const weatherInfo = getWeatherInfo(data.current.weather_code)
      
      weather.value = {
        name: 'Jakarta',
        main: {
          temp: data.current.temperature_2m,
          feels_like: data.current.apparent_temperature,
          humidity: data.current.relative_humidity_2m,
          pressure: data.current.pressure_msl
        },
        weather: [{
          main: weatherInfo.desc,
          description: weatherInfo.desc.toLowerCase(),
          icon: weatherInfo.icon
        }],
        wind: {
          speed: data.current.wind_speed_10m
        },
        clouds: {
          all: data.current.cloud_cover
        }
      }
    }
  } catch (error) {
    console.error('Failed to fetch weather for Jakarta:', error)
  }
}

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch outlet', life: 3000 })
  }
}

const fetchStats = async () => {
  try {
    // Fetch categories count
    const categoriesRes = await api.get(`/outlets/${outletId}/kategori-bahan-baku`)
    stats.value.categories = categoriesRes.data.length

    // Fetch units count
    const unitsRes = await api.get(`/outlets/${outletId}/satuan`)
    stats.value.units = unitsRes.data.length

    // Fetch suppliers count
    const suppliersRes = await api.get(`/outlets/${outletId}/supplier`)
    stats.value.suppliers = suppliersRes.data.length

    // Fetch materials and calculate stock stats
    const materialsRes = await api.get(`/outlets/${outletId}/bahan-baku`)
    const materials = materialsRes.data
    stats.value.materials = materials.length
    stats.value.inStock = materials.filter(m => m.stock_status === 'in_stock').length
    stats.value.lowStock = materials.filter(m => m.stock_status === 'low_stock').length
    stats.value.outOfStock = materials.filter(m => m.stock_status === 'out_of_stock').length

    // Fetch menu categories count
    const menuCategoriesRes = await api.get(`/outlets/${outletId}/kategori-menu`)
    stats.value.menuCategories = menuCategoriesRes.data.length

    // Fetch menus count
    const menusRes = await api.get(`/outlets/${outletId}/menu`)
    stats.value.menus = menusRes.data.length

    // Fetch promos count
    const promosRes = await api.get(`/outlets/${outletId}/promos`)
    stats.value.promos = promosRes.data.length

    // Fetch tables count
    const tablesRes = await api.get(`/outlets/${outletId}/tables`)
    stats.value.tables = tablesRes.data.length

    // Fetch members count
    const membersRes = await api.get(`/outlets/${outletId}/members`)
    stats.value.members = membersRes.data.length

    // Fetch users count
    const usersRes = await api.get(`/outlets/${outletId}/users`)
    stats.value.users = usersRes.data.users?.length || 0

    // Fetch stations count
    try {
      const stationsRes = await api.get(`/outlets/${outletId}/stations`)
      stats.value.stations = stationsRes.data.length
    } catch (error) {
      stats.value.stations = 0
    }

    // Fetch shifts count
    try {
      const shiftsRes = await api.get(`/outlets/${outletId}/shifts`)
      stats.value.shifts = shiftsRes.data.length
    } catch (error) {
      stats.value.shifts = 0
    }

    // Fetch purchases count
    try {
      const purchasesRes = await api.get(`/outlets/${outletId}/purchases`)
      stats.value.purchases = purchasesRes.data.length
    } catch (error) {
      stats.value.purchases = 0
    }

    // Fetch expenses count
    try {
      const expensesRes = await api.get(`/outlets/${outletId}/expenses`)
      stats.value.expenses = expensesRes.data.length
    } catch (error) {
      stats.value.expenses = 0
    }

    // Fetch transactions count (paid orders)
    const ordersRes = await api.get(`/outlets/${outletId}/orders`)
    stats.value.transactions = ordersRes.data.filter(o => o.status === 'paid').length
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

onMounted(() => {
  fetchOutlet()
  fetchStats()
  updateClock()
  fetchWeather()
  
  // Update clock every second
  setInterval(updateClock, 1000)
  
  // Update weather every 10 minutes
  setInterval(fetchWeather, 600000)
})
</script>

<style scoped>
/* ── CSS Variables ─────────────────────────────────────────── */
.mod-dashboard {
  --mod-primary:   #5D87FF;
  --mod-secondary: #49BEFF;
  --mod-success:   #13DEB9;
  --mod-warning:   #FFAE1F;
  --mod-error:     #FA896B;
  --mod-text:      #2A3547;
  --mod-text-light:#7C8FAC;
  --mod-border:    #e5eaef;
  --mod-bg:        #f6f9fc;

  max-width: 1400px;
  margin: 0 auto;
  padding: 0 0 2rem;
  background: var(--mod-bg);
  color: var(--mod-text);
  font-family: inherit;
}

/* ── Page Header ───────────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding: 1.25rem 1.5rem;
  background: white;
  border: 1px solid var(--mod-border);
  border-radius: 8px;
}

.page-title {
  margin: 0 0 0.25rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--mod-text);
}

.page-subtitle {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--mod-text-light);
  text-transform: capitalize;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.clock-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.875rem;
  background: #ECF2FF;
  color: var(--mod-primary);
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
}

/* ── KPI Row ───────────────────────────────────────────────── */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  background: white;
  border: 1px solid var(--mod-border);
  border-radius: 8px;
  cursor: pointer;
  transition: box-shadow 0.2s, border-color 0.2s;
  position: relative;
}

.kpi-card:hover {
  box-shadow: 0 4px 16px rgba(93, 135, 255, 0.12);
  border-color: var(--mod-primary);
}

.kpi-icon {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.375rem;
}

.kpi-info {
  flex: 1;
  min-width: 0;
}

.kpi-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--mod-text);
  line-height: 1;
  margin-bottom: 0.25rem;
}

.kpi-label {
  font-size: 0.8125rem;
  color: var(--mod-text-light);
  font-weight: 500;
}

.kpi-trend {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.kpi-trend.up {
  background: #E6FFFA;
  color: #13DEB9;
}

.kpi-trend.down {
  background: #FDEDE8;
  color: #FA896B;
}

/* ── Middle Row ────────────────────────────────────────────── */
.middle-row {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.25rem;
  margin-bottom: 1.25rem;
  align-items: start;
}

/* ── Quick Access Panel ────────────────────────────────────── */
.quick-access-panel {
  background: white;
  border: 1px solid var(--mod-border);
  border-radius: 8px;
  overflow: hidden;
}

.panel-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--mod-border);
}

.panel-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--mod-text);
}

.menu-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
  padding: 1.25rem;
}

.menu-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 0.5rem;
  border-radius: 8px;
  border: 1px solid var(--mod-border);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  text-align: center;
}

.menu-card:hover {
  border-color: var(--mod-primary);
  background: #f8faff;
  box-shadow: 0 2px 8px rgba(93, 135, 255, 0.1);
}

.menu-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.menu-label {
  font-size: 0.75rem;
  color: var(--mod-text);
  font-weight: 500;
  line-height: 1.3;
  word-break: break-word;
}

/* ── Info Panel ────────────────────────────────────────────── */
.info-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-card {
  background: white;
  border: 1px solid var(--mod-border);
  border-radius: 8px;
  overflow: hidden;
}

.info-card-header {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--mod-border);
}

.info-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--mod-text);
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.info-card-title i {
  color: var(--mod-primary);
  font-size: 0.875rem;
}

/* Weather */
.weather-card {
  cursor: pointer;
  transition: box-shadow 0.2s;
}

.weather-card:hover {
  box-shadow: 0 4px 16px rgba(93, 135, 255, 0.1);
}

.weather-content {
  padding: 1rem;
}

.weather-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.weather-icon-img {
  width: 52px;
  height: 52px;
  object-fit: contain;
}

.weather-temp {
  font-size: 2rem;
  font-weight: 700;
  color: var(--mod-text);
  line-height: 1;
}

.weather-desc {
  font-size: 0.8125rem;
  color: var(--mod-text-light);
  text-transform: capitalize;
  margin-bottom: 0.375rem;
}

.weather-city {
  font-size: 0.8125rem;
  color: var(--mod-text-light);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.weather-city i {
  color: var(--mod-error);
  font-size: 0.75rem;
}

.weather-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--mod-text-light);
  font-size: 0.875rem;
}

/* Outlet Info List */
.outlet-info-list {
  list-style: none;
  margin: 0;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.outlet-info-list li {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: var(--mod-text);
  line-height: 1.4;
}

.outlet-info-list li i {
  color: var(--mod-primary);
  font-size: 0.8125rem;
  margin-top: 0.1rem;
  flex-shrink: 0;
}

.outlet-info-list li.info-empty {
  color: var(--mod-text-light);
}

.outlet-info-list li.info-empty i {
  color: var(--mod-text-light);
}

/* Stock Bars */
.stock-bars {
  padding: 0.875rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.stock-bar-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stock-bar-label {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: var(--mod-text-light);
}

.stock-bar-count {
  font-weight: 600;
  color: var(--mod-text);
}

.stock-bar-track {
  height: 6px;
  background: var(--mod-bg);
  border-radius: 3px;
  overflow: hidden;
}

.stock-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
  min-width: 4px;
}

/* ── Summary Row ───────────────────────────────────────────── */
.summary-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.summary-card {
  background: white;
  border: 1px solid var(--mod-border);
  border-radius: 8px;
  padding: 1.25rem;
}

.summary-card-header {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  margin-bottom: 1rem;
  padding-bottom: 0.875rem;
  border-bottom: 1px solid var(--mod-border);
}

.summary-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.summary-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--mod-text);
  line-height: 1.2;
}

.summary-subtitle {
  font-size: 0.75rem;
  color: var(--mod-text-light);
  margin-top: 0.125rem;
}

.summary-stats {
  display: flex;
  gap: 1.25rem;
  flex-wrap: wrap;
}

.summary-stat {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.summary-stat-value {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--mod-text);
  line-height: 1;
}

.summary-stat-label {
  font-size: 0.75rem;
  color: var(--mod-text-light);
}

/* ── Back Row ──────────────────────────────────────────────── */
.back-row {
  margin-top: 0.5rem;
}

/* ── Responsive ────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .middle-row {
    grid-template-columns: 1fr;
  }

  .info-panel {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 900px) {
  .kpi-row {
    grid-template-columns: repeat(2, 1fr);
  }

  .summary-row {
    grid-template-columns: 1fr;
  }

  .menu-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .kpi-row {
    grid-template-columns: 1fr 1fr;
  }

  .menu-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .info-panel {
    grid-template-columns: 1fr;
  }

  .kpi-value {
    font-size: 1.375rem;
  }

  .summary-stats {
    gap: 1rem;
  }
}

@media (max-width: 400px) {
  .kpi-row {
    grid-template-columns: 1fr;
  }
}
</style>
