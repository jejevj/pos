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
          :class="{ active: activeTab === 'transaction' }"
          @click="activeTab = 'transaction'"
        >
          <i class="pi pi-receipt"></i>
          Transaksi &amp; Struk
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

    <!-- Transaction & Receipt Tab -->
    <div v-show="activeTab === 'transaction'" class="tab-content settings-view">
      <Card>
        <template #header>
          <div class="card-header">
            <h3>Pengaturan Transaksi &amp; Struk</h3>
          </div>
        </template>
        <template #content>
          <div v-if="txLoading" class="flex justify-center py-8">
            <ProgressSpinner style="width:40px;height:40px" strokeWidth="4" />
          </div>

          <template v-else>
            <!-- PPN Section -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-percentage"></i>
                Pajak Pertambahan Nilai (PPN)
              </div>

              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Aktifkan PPN</label>
                    <p class="setting-description">PPN akan otomatis dihitung dan ditambahkan ke setiap transaksi.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.tax_enabled" />
                </div>

                <template v-if="txSettings.tax_enabled">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Persentase PPN (%)</label>
                      <p class="setting-description">Tarif PPN standar Indonesia adalah 11%.</p>
                    </div>
                    <InputNumber
                      v-model="txSettings.tax_percentage"
                      :min="0" :max="100"
                      :minFractionDigits="0" :maxFractionDigits="2"
                      suffix=" %"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Label PPN</label>
                      <p class="setting-description">Nama yang tampil di struk (contoh: PPN, Pajak, Tax).</p>
                    </div>
                    <InputText v-model="txSettings.tax_label" class="setting-control" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">PPN Inklusif (sudah termasuk dalam harga)</label>
                      <p class="setting-description">Jika aktif, harga menu dianggap sudah termasuk PPN. Jika nonaktif, PPN ditambahkan di atas harga.</p>
                    </div>
                    <ToggleSwitch v-model="txSettings.tax_inclusive" />
                  </div>
                </template>
              </div>
            </div>

            <!-- Service Charge Section -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-server"></i>
                Service Charge
              </div>

              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Aktifkan Service Charge</label>
                    <p class="setting-description">Biaya pelayanan yang ditambahkan ke total transaksi.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.service_charge_enabled" />
                </div>

                <template v-if="txSettings.service_charge_enabled">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Persentase Service Charge (%)</label>
                      <p class="setting-description">Umumnya berkisar antara 5% - 10%.</p>
                    </div>
                    <InputNumber
                      v-model="txSettings.service_charge_percentage"
                      :min="0" :max="100"
                      :minFractionDigits="0" :maxFractionDigits="2"
                      suffix=" %"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Label Service Charge</label>
                      <p class="setting-description">Nama yang tampil di struk.</p>
                    </div>
                    <InputText v-model="txSettings.service_charge_label" class="setting-control" />
                  </div>
                </template>
              </div>
            </div>

            <!-- Aturan Lain -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-sliders-h"></i>
                Aturan Lainnya
              </div>

              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Minimum Nilai Order (Rp)</label>
                    <p class="setting-description">Transaksi tidak bisa diproses jika di bawah nilai ini. Set 0 untuk menonaktifkan.</p>
                  </div>
                  <InputNumber
                    v-model="txSettings.min_order_amount"
                    :min="0"
                    :minFractionDigits="0" :maxFractionDigits="0"
                    prefix="Rp "
                    :useGrouping="true"
                    class="setting-control"
                  />
                </div>
              </div>
            </div>

            <!-- Kustomisasi Struk -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-file-edit"></i>
                Kustomisasi Struk
              </div>

              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Tampilkan Logo</label>
                    <p class="setting-description">Logo outlet ditampilkan di bagian atas struk.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_logo_enabled" />
                </div>

                <div v-if="txSettings.receipt_logo_enabled" class="setting-item" style="align-items: flex-start;">
                  <div class="setting-info">
                    <label class="setting-label">Logo Kustom Struk</label>
                    <p class="setting-description">Opsional. Kosongkan untuk pakai logo outlet. Upload gambar (JPG/PNG/WEBP, maks 2MB).</p>
                  </div>
                  <div class="setting-control logo-upload-section">
                    <div v-if="txSettings.receipt_custom_logo_url" class="logo-preview-row">
                      <img :src="txSettings.receipt_custom_logo_url" alt="Logo Preview" class="logo-preview-img" />
                      <Button icon="pi pi-times" severity="danger" text size="small" @click="txSettings.receipt_custom_logo_url = ''" />
                    </div>
                    <div v-else class="logo-placeholder-row">
                      <i class="pi pi-image"></i>
                      <span>Belum ada logo</span>
                    </div>

                    <div class="logo-upload-btn">
                      <label class="upload-label">
                        <input type="file" accept="image/*" @change="handleLogoUpload" class="hidden-input" :disabled="uploadingLogo" />
                        <Button as="span" icon="pi pi-upload" :label="uploadingLogo ? 'Mengupload...' : 'Upload Logo'" outlined size="small" :loading="uploadingLogo" />
                      </label>
                    </div>
                  </div>
                </div>

                <div class="setting-item" style="align-items: flex-start;">
                  <div class="setting-info">
                    <label class="setting-label">Teks Header Struk</label>
                    <p class="setting-description">Teks tambahan setelah nama &amp; alamat outlet (mis: slogan, nomor izin usaha). Enter untuk baris baru.</p>
                  </div>
                  <Textarea
                    v-model="txSettings.receipt_header"
                    rows="2"
                    class="setting-control"
                    placeholder="Selamat datang di toko kami!"
                  />
                </div>

                <div class="setting-item" style="align-items: flex-start;">
                  <div class="setting-info">
                    <label class="setting-label">Teks Footer Struk</label>
                    <p class="setting-description">Ucapan terima kasih, promosi, atau informasi lain di bagian bawah struk.</p>
                  </div>
                  <Textarea
                    v-model="txSettings.receipt_footer"
                    rows="3"
                    class="setting-control"
                    placeholder="Terima kasih! Kunjungi kami lagi"
                  />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Tampilkan Nama Kasir</label>
                    <p class="setting-description">Nama kasir yang memproses transaksi.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_show_cashier" />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Tampilkan Nomor Meja</label>
                    <p class="setting-description">Nomor meja untuk transaksi dine-in.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_show_table" />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Tampilkan Info Member</label>
                    <p class="setting-description">Nama member dan saldo poin di struk.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_show_member" />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Tampilkan QR Code Tracking</label>
                    <p class="setting-description">QR Code di struk untuk pelanggan cek status pesanan secara online.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_show_qr" />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Cantumkan Info WiFi</label>
                    <p class="setting-description">SSID dan password WiFi gratis ditampilkan di bawah struk, lengkap dengan QR Code untuk connect langsung.</p>
                  </div>
                  <ToggleSwitch v-model="txSettings.receipt_wifi_enabled" />
                </div>

                <template v-if="txSettings.receipt_wifi_enabled">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Nama WiFi (SSID)</label>
                      <p class="setting-description">Nama jaringan WiFi yang ingin ditampilkan.</p>
                    </div>
                    <InputText v-model="txSettings.receipt_wifi_ssid" class="setting-control" placeholder="NamaWiFiSaya" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">Password WiFi</label>
                      <p class="setting-description">Kosongkan jika WiFi tidak menggunakan password.</p>
                    </div>
                    <InputText v-model="txSettings.receipt_wifi_password" class="setting-control" placeholder="password123" />
                  </div>

                  <div v-if="txSettings.receipt_wifi_ssid" class="wifi-preview">
                    <div class="wifi-preview-label">Preview QR WiFi:</div>
                    <img
                      :src="`https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent('WIFI:T:WPA;S:' + txSettings.receipt_wifi_ssid + ';P:' + txSettings.receipt_wifi_password + ';H:false;;')}`"
                      alt="WiFi QR"
                      class="wifi-preview-qr"
                    />
                    <p class="wifi-preview-hint">Scan dengan kamera HP untuk connect langsung.</p>
                  </div>
                </template>
              </div>
            </div>

            <!-- Preview kalkulasi -->
            <div v-if="txSettings.tax_enabled || txSettings.service_charge_enabled" class="tx-preview">
              <div class="tx-preview-title">Preview Kalkulasi (contoh: Rp 100.000)</div>
              <div class="tx-preview-row">
                <span>Subtotal</span>
                <span>Rp 100.000</span>
              </div>
              <div v-if="txSettings.tax_enabled" class="tx-preview-row tax">
                <span>{{ txSettings.tax_label || 'PPN' }} ({{ txSettings.tax_percentage }}%)
                  <span v-if="txSettings.tax_inclusive" class="inclusive-badge">inklusif</span>
                </span>
                <span v-if="txSettings.tax_inclusive">sudah termasuk</span>
                <span v-else>+ Rp {{ Math.round(100000 * txSettings.tax_percentage / 100).toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="txSettings.service_charge_enabled" class="tx-preview-row sc">
                <span>{{ txSettings.service_charge_label || 'Service Charge' }} ({{ txSettings.service_charge_percentage }}%)</span>
                <span>+ Rp {{ Math.round(100000 * txSettings.service_charge_percentage / 100).toLocaleString('id-ID') }}</span>
              </div>
              <div class="tx-preview-row total">
                <span>Total</span>
                <span>Rp {{ calcPreviewTotal().toLocaleString('id-ID') }}</span>
              </div>
            </div>

            <div class="form-actions">
              <Button label="Simpan Pengaturan Transaksi" icon="pi pi-check" :loading="txSaving" @click="saveTxSettings" />
              <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined @click="fetchTxSettings" />
            </div>
          </template>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { decodeOutletId } from '@/utils/outletId'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import ProgressSpinner from 'primevue/progressspinner'
import ToggleSwitch from 'primevue/toggleswitch'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()

// route.params.outletId is the encoded hash (e.g. "504f5301").
// The api interceptor auto-decodes it, but we also resolve the numeric id
// explicitly here so tx-settings calls work even if the URL pattern changes.
const rawOutletParam = route.params.outletId
const numericOutletId = decodeOutletId(rawOutletParam) || rawOutletParam
const outletId = rawOutletParam
const activeTab = ref('identity')
const savingIdentity = ref(false)
const uploadingLogo = ref(false)

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

// ── Transaction Settings (PPN, Service Charge, Receipt customization) ──
const txLoading = ref(false)
const txSaving  = ref(false)
const txSettings = ref({
  tax_enabled:                true,
  tax_percentage:             11,
  tax_label:                  'PPN',
  tax_inclusive:              false,
  service_charge_enabled:     false,
  service_charge_percentage:  0,
  service_charge_label:       'Service Charge',
  receipt_footer:             '',
  min_order_amount:           0,
  receipt_logo_enabled:       true,
  receipt_custom_logo_url:    '',
  receipt_header:             '',
  receipt_show_qr:            true,
  receipt_wifi_enabled:       false,
  receipt_wifi_ssid:          '',
  receipt_wifi_password:      '',
  receipt_show_cashier:       true,
  receipt_show_table:         true,
  receipt_show_member:        true,
})

const fetchTxSettings = async () => {
  if (!numericOutletId) return
  txLoading.value = true
  try {
    const res = await api.get(`/outlets/${numericOutletId}/transaction-settings`)
    const d   = res.data
    txSettings.value = {
      tax_enabled:               Boolean(d.tax_enabled),
      tax_percentage:            parseFloat(d.tax_percentage)  || 11,
      tax_label:                 d.tax_label                   || 'PPN',
      tax_inclusive:             Boolean(d.tax_inclusive),
      service_charge_enabled:    Boolean(d.service_charge_enabled),
      service_charge_percentage: parseFloat(d.service_charge_percentage) || 0,
      service_charge_label:      d.service_charge_label        || 'Service Charge',
      receipt_footer:            d.receipt_footer              || '',
      min_order_amount:          parseFloat(d.min_order_amount) || 0,
      receipt_logo_enabled:      Boolean(d.receipt_logo_enabled ?? true),
      receipt_custom_logo_url:   d.receipt_custom_logo_url     || '',
      receipt_header:            d.receipt_header              || '',
      receipt_show_qr:           Boolean(d.receipt_show_qr ?? true),
      receipt_wifi_enabled:      Boolean(d.receipt_wifi_enabled),
      receipt_wifi_ssid:         d.receipt_wifi_ssid           || '',
      receipt_wifi_password:     d.receipt_wifi_password       || '',
      receipt_show_cashier:      Boolean(d.receipt_show_cashier ?? true),
      receipt_show_table:        Boolean(d.receipt_show_table ?? true),
      receipt_show_member:       Boolean(d.receipt_show_member ?? true),
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat pengaturan transaksi', life: 3000 })
  } finally {
    txLoading.value = false
  }
}

const saveTxSettings = async () => {
  if (!numericOutletId) {
    toast.add({ severity: 'warn', summary: 'Perhatian', detail: 'Buka halaman ini dari menu outlet.', life: 4000 })
    return
  }
  txSaving.value = true
  try {
    await api.put(`/outlets/${numericOutletId}/transaction-settings`, txSettings.value)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pengaturan transaksi disimpan. Berlaku untuk transaksi baru.', life: 3000 })
  } catch (e) {
    const msg = e.response?.data?.message || 'Gagal menyimpan pengaturan transaksi'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
  } finally {
    txSaving.value = false
  }
}

const calcPreviewTotal = () => {
  const base = 100000
  const tax = txSettings.value.tax_enabled && !txSettings.value.tax_inclusive
    ? Math.round(base * txSettings.value.tax_percentage / 100)
    : 0
  const sc  = txSettings.value.service_charge_enabled
    ? Math.round(base * txSettings.value.service_charge_percentage / 100)
    : 0
  return base + tax + sc
}

async function handleLogoUpload(event) {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 2_000_000) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'File terlalu besar. Maksimal 2MB', life: 3000 })
    event.target.value = ''
    return
  }

  uploadingLogo.value = true
  try {
    const formData = new FormData()
    formData.append('image', file)

    const response = await api.post(
      `/outlets/${numericOutletId}/upload/image`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    txSettings.value.receipt_custom_logo_url = response.data.url
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Logo berhasil diupload. Jangan lupa Simpan pengaturan.', life: 3000 })
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal mengupload logo'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
    console.error('Upload failed:', err)
  } finally {
    uploadingLogo.value = false
    event.target.value = ''
  }
}

onMounted(() => {
  loadOutletData()
  fetchTxSettings()
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

.identity-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.identity-card {
  background: var(--p-surface-card, white);
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

/* Receipt logo upload */
.logo-upload-section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.logo-preview-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.logo-preview-img {
  max-width: 120px;
  max-height: 80px;
  object-fit: contain;
  border: 1px solid var(--p-surface-border, #e5e7eb);
  border-radius: 6px;
  padding: 4px;
  background: #fff;
}
.logo-placeholder-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: 2px dashed var(--p-surface-300, #d1d5db);
  border-radius: 8px;
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.9rem;
}
.logo-placeholder-row i {
  font-size: 1.25rem;
}
.upload-label {
  display: inline-block;
  cursor: pointer;
}
.hidden-input {
  display: none;
}

/* ── Transaction Settings section ── */
.tx-section {
  margin-bottom: 2rem;
}
.tx-section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--p-text-color, #1f2937);
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid var(--p-primary-color, #5d87ff);
}

.settings-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.setting-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.setting-info {
  flex: 1;
  margin-right: 2rem;
}

.setting-label {
  display: block;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.setting-description {
  margin: 0;
  font-size: 0.875rem;
  color: #6b7280;
}

.setting-control {
  min-width: 250px;
}

.tx-preview {
  margin: 1.5rem 0;
  padding: 1.25rem 1.5rem;
  background: var(--p-surface-50, #f9fafb);
  border: 1px solid var(--p-surface-200, #e5e7eb);
  border-radius: 10px;
  max-width: 400px;
}
.tx-preview-title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color, #6b7280);
  margin-bottom: 0.75rem;
}
.tx-preview-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
  padding: 0.3rem 0;
  color: var(--p-text-color, #374151);
  border-bottom: 1px dashed var(--p-surface-200, #e5e7eb);
}
.tx-preview-row:last-child { border-bottom: none; }
.tx-preview-row.tax  { color: #f59e0b; }
.tx-preview-row.sc   { color: #6366f1; }
.tx-preview-row.total {
  font-weight: 700;
  font-size: 1rem;
  color: var(--p-primary-color, #5d87ff);
  border-top: 2px solid var(--p-surface-200, #e5e7eb);
  margin-top: 0.25rem;
  padding-top: 0.5rem;
}
.inclusive-badge {
  display: inline-block;
  font-size: 0.65rem;
  background: rgba(245,158,11,0.15);
  color: #b45309;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  margin-left: 0.4rem;
  vertical-align: middle;
}

.flex { display: flex; }
.justify-center { justify-content: center; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }

.wifi-preview {
  padding: 1rem;
  background: var(--p-surface-50, #f9fafb);
  border: 1px dashed var(--p-surface-300, #d1d5db);
  border-radius: 8px;
  text-align: center;
}
.wifi-preview-label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color, #6b7280);
  margin-bottom: 0.5rem;
}
.wifi-preview-qr {
  width: 100px;
  height: 100px;
  border-radius: 4px;
}
.wifi-preview-hint {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #9ca3af);
  margin-top: 0.4rem;
}

/* dark mode overrides for tx panel inside utilities */
html.is-dark .tx-section-title { color: #e4e4ef; }
html.is-dark .tx-preview {
  background: #1a1a24 !important;
  border-color: #2a2a38 !important;
}
html.is-dark .tx-preview-row { color: #d0d0e8; border-color: #2a2a38; }
html.is-dark .tx-preview-row.total { color: #8ab4ff; border-color: #2a2a38; }
html.is-dark .setting-item {
  background: #1a1a24;
  border-color: #2a2a38;
}
html.is-dark .setting-label { color: #e4e4ef; }
html.is-dark .setting-description { color: #9ca0bc; }

@media (max-width: 768px) {
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
