<template>
  <div class="view-container settings-view">
    <!-- Breadcrumb -->
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
          <span>{{ $t('common.settings') }}</span>
        </div>
      </template>
      <template #content>
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="general">
              <i class="pi pi-cog mr-2"></i>
              {{ $t('settings.general') }}
            </Tab>
            <Tab value="profile">
              <i class="pi pi-user mr-2"></i>
              {{ $t('settings.profile') }}
            </Tab>
            <Tab value="security">
              <i class="pi pi-shield mr-2"></i>
              {{ $t('settings.security') }}
            </Tab>
            <Tab value="notifications">
              <i class="pi pi-bell mr-2"></i>
              {{ $t('settings.notifications') }}
            </Tab>
            <Tab value="transaction">
              <i class="pi pi-receipt mr-2"></i>
              {{ $t('settings.transaction') || 'Transaksi' }}
            </Tab>
          </TabList>
          
          <TabPanels>
            <!-- General Tab -->
            <TabPanel value="general">
              <div class="tab-content">
                <h3>{{ $t('settings.general') }} {{ $t('common.settings') }}</h3>
                <p class="text-muted mb-4">Manage your application preferences</p>

                <div class="settings-section">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.language') }}</label>
                      <p class="setting-description">Choose your preferred language</p>
                    </div>
                    <Dropdown 
                      v-model="settings.language" 
                      :options="languages" 
                      optionLabel="name" 
                      optionValue="code"
                      :placeholder="$t('settings.language')"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.timezone') }}</label>
                      <p class="setting-description">Set your local timezone</p>
                    </div>
                    <Dropdown 
                      v-model="settings.timezone" 
                      :options="timezones" 
                      optionLabel="name" 
                      optionValue="value"
                      :placeholder="$t('settings.timezone')"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.dateFormat') }}</label>
                      <p class="setting-description">Choose how dates are displayed</p>
                    </div>
                    <Dropdown 
                      v-model="settings.dateFormat" 
                      :options="dateFormats" 
                      optionLabel="label" 
                      optionValue="value"
                      :placeholder="$t('settings.dateFormat')"
                      class="setting-control"
                    />
                  </div>
                </div>

                <div class="settings-actions">
                  <Button 
                    :label="$t('common.save') + ' Changes'" 
                    icon="pi pi-check"
                    :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
                  />
                </div>
              </div>
            </TabPanel>

            <!-- Profile Tab -->
            <TabPanel value="profile">
              <div class="tab-content">
                <h3>{{ $t('settings.profile') }} Information</h3>
                <p class="text-muted mb-4">Update your personal information</p>

                <div class="settings-section">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.fullName') }}</label>
                      <p class="setting-description">Your display name</p>
                    </div>
                    <InputText 
                      v-model="profile.name" 
                      :placeholder="$t('settings.fullName')"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('auth.email') }}</label>
                      <p class="setting-description">Your email for notifications</p>
                    </div>
                    <InputText 
                      v-model="profile.email" 
                      type="email"
                      :placeholder="$t('auth.email')"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.phoneNumber') }}</label>
                      <p class="setting-description">Your contact number</p>
                    </div>
                    <InputText 
                      v-model="profile.phone" 
                      :placeholder="$t('settings.phoneNumber')"
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.bio') }}</label>
                      <p class="setting-description">Tell us about yourself</p>
                    </div>
                    <Textarea 
                      v-model="profile.bio" 
                      rows="4"
                      :placeholder="$t('settings.bio')"
                      class="setting-control"
                    />
                  </div>
                </div>

                <div class="settings-actions">
                  <Button 
                    :label="$t('common.update') + ' ' + $t('settings.profile')" 
                    icon="pi pi-check"
                    :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
                  />
                </div>
              </div>
            </TabPanel>

            <!-- Security Tab -->
            <TabPanel value="security">
              <div class="tab-content">
                <h3>{{ $t('settings.security') }} {{ $t('common.settings') }}</h3>
                <p class="text-muted mb-4">Manage your account security</p>

                <div class="settings-section">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.currentPassword') }}</label>
                      <p class="setting-description">Enter your current password</p>
                    </div>
                    <Password 
                      v-model="security.currentPassword" 
                      :placeholder="$t('settings.currentPassword')"
                      :feedback="false"
                      toggleMask
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.newPassword') }}</label>
                      <p class="setting-description">Choose a strong password</p>
                    </div>
                    <Password 
                      v-model="security.newPassword" 
                      :placeholder="$t('settings.newPassword')"
                      toggleMask
                      class="setting-control"
                    />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('auth.confirmPassword') }}</label>
                      <p class="setting-description">Re-enter your new password</p>
                    </div>
                    <Password 
                      v-model="security.confirmPassword" 
                      :placeholder="$t('auth.confirmPassword')"
                      :feedback="false"
                      toggleMask
                      class="setting-control"
                    />
                  </div>

                  <Divider />

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.twoFactor') }}</label>
                      <p class="setting-description">Add an extra layer of security</p>
                    </div>
                    <ToggleSwitch v-model="security.twoFactorEnabled" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.sessionTimeout') }}</label>
                      <p class="setting-description">Auto logout after inactivity</p>
                    </div>
                    <Dropdown 
                      v-model="security.sessionTimeout" 
                      :options="sessionTimeouts" 
                      optionLabel="label" 
                      optionValue="value"
                      :placeholder="$t('settings.sessionTimeout')"
                      class="setting-control"
                    />
                  </div>
                </div>

                <div class="settings-actions">
                  <Button 
                    :label="$t('common.update') + ' ' + $t('settings.security')" 
                    icon="pi pi-shield"
                    :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
                  />
                </div>
              </div>
            </TabPanel>

            <!-- Notifications Tab -->
            <TabPanel value="notifications">
              <div class="tab-content">
                <h3>{{ $t('settings.notifications') }} Preferences</h3>
                <p class="text-muted mb-4">Choose what notifications you receive</p>

                <div class="settings-section">
                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.emailNotifications') }}</label>
                      <p class="setting-description">Receive updates via email</p>
                    </div>
                    <ToggleSwitch v-model="notifications.email" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.pushNotifications') }}</label>
                      <p class="setting-description">Browser push notifications</p>
                    </div>
                    <ToggleSwitch v-model="notifications.push" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.smsNotifications') }}</label>
                      <p class="setting-description">Receive SMS alerts</p>
                    </div>
                    <ToggleSwitch v-model="notifications.sms" />
                  </div>

                  <Divider />

                  <h4 class="notification-category">{{ $t('settings.notifications') }} Types</h4>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.systemUpdates') }}</label>
                      <p class="setting-description">Important system announcements</p>
                    </div>
                    <Checkbox v-model="notifications.types" value="system" :binary="false" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.securityAlerts') }}</label>
                      <p class="setting-description">Account security notifications</p>
                    </div>
                    <Checkbox v-model="notifications.types" value="security" :binary="false" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.activityUpdates') }}</label>
                      <p class="setting-description">User activity notifications</p>
                    </div>
                    <Checkbox v-model="notifications.types" value="activity" :binary="false" />
                  </div>

                  <div class="setting-item">
                    <div class="setting-info">
                      <label class="setting-label">{{ $t('settings.marketing') }}</label>
                      <p class="setting-description">Promotional emails and offers</p>
                    </div>
                    <Checkbox v-model="notifications.types" value="marketing" :binary="false" />
                  </div>
                </div>

                <div class="settings-actions">
                  <Button 
                    :label="$t('common.save') + ' Preferences'" 
                    icon="pi pi-check"
                    :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
                  />
                </div>
              </div>
            </TabPanel>

            <!-- Transaction Tab -->
            <TabPanel value="transaction">
              <div class="tab-content">
                <h3>Pengaturan Transaksi</h3>
                <p class="text-muted mb-4">Konfigurasi PPN, service charge, dan aturan transaksi outlet ini.</p>

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

                      <div class="setting-item" style="align-items: flex-start;">
                        <div class="setting-info">
                          <label class="setting-label">Catatan Bawah Struk</label>
                          <p class="setting-description">Teks yang tampil di bagian bawah struk (ucapan terima kasih, promosi, dll).</p>
                        </div>
                        <Textarea
                          v-model="txSettings.receipt_footer"
                          rows="3"
                          class="setting-control"
                          placeholder="Terima kasih telah berbelanja!"
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
                      <!-- Logo -->
                      <div class="setting-item">
                        <div class="setting-info">
                          <label class="setting-label">Tampilkan Logo</label>
                          <p class="setting-description">Logo outlet ditampilkan di bagian atas struk.</p>
                        </div>
                        <ToggleSwitch v-model="txSettings.receipt_logo_enabled" />
                      </div>

                      <div v-if="txSettings.receipt_logo_enabled" class="setting-item" style="align-items: flex-start;">
                        <div class="setting-info">
                          <label class="setting-label">URL Logo Kustom</label>
                          <p class="setting-description">Opsional. Kosongkan untuk pakai logo outlet. Gunakan URL publik gambar (JPG/PNG).</p>
                        </div>
                        <InputText v-model="txSettings.receipt_custom_logo_url" class="setting-control" placeholder="https://..." />
                      </div>

                      <!-- Header kustom -->
                      <div class="setting-item" style="align-items: flex-start;">
                        <div class="setting-info">
                          <label class="setting-label">Teks Header Struk</label>
                          <p class="setting-description">Teks tambahan setelah nama & alamat outlet (mis: slogan, nomor izin usaha). Enter untuk baris baru.</p>
                        </div>
                        <Textarea
                          v-model="txSettings.receipt_header"
                          rows="2"
                          class="setting-control"
                          placeholder="Selamat datang di toko kami!"
                        />
                      </div>

                      <!-- Footer kustom -->
                      <div class="setting-item" style="align-items: flex-start;">
                        <div class="setting-info">
                          <label class="setting-label">Teks Footer Struk</label>
                          <p class="setting-description">Ucapan terima kasih, promosi, atau informasi lain di bagian bawah struk.</p>
                        </div>
                        <Textarea
                          v-model="txSettings.receipt_footer"
                          rows="3"
                          class="setting-control"
                          placeholder="Terima kasih! Kunjungi kami lagi 😊"
                        />
                      </div>

                      <!-- Tampilkan/sembunyikan kolom info -->
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

                      <!-- QR Code -->
                      <div class="setting-item">
                        <div class="setting-info">
                          <label class="setting-label">Tampilkan QR Code Tracking</label>
                          <p class="setting-description">QR Code di struk untuk pelanggan cek status pesanan secara online.</p>
                        </div>
                        <ToggleSwitch v-model="txSettings.receipt_show_qr" />
                      </div>

                      <!-- WiFi -->
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

                        <!-- Preview WiFi QR -->
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

                  <div class="settings-actions">
                    <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined @click="fetchTxSettings" />
                    <Button
                      label="Simpan Pengaturan Transaksi"
                      icon="pi pi-check"
                      :loading="txSaving"
                      @click="saveTxSettings"
                    />
                  </div>
                </template>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import api from '@/services/api'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Breadcrumb from 'primevue/breadcrumb'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Password from 'primevue/password'
import Dropdown from 'primevue/dropdown'
import ToggleSwitch from 'primevue/toggleswitch'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import InputNumber from 'primevue/inputnumber'
import ProgressSpinner from 'primevue/progressspinner'

const router = useRouter()
const route  = useRoute()
const toast  = useToast()
const { t }  = useI18n()

// outletId dari route params (halaman ini diakses via /outlets/:outletId/settings)
const outletId = computed(() => route.params.outletId)

// Breadcrumb
const breadcrumbHome = computed(() => ({
  icon: 'pi pi-home',
  to: outletId.value ? `/outlets/${outletId.value}/dashboard` : '/'
}))
const breadcrumbItems = computed(() => [
  { label: t('common.settings') }
])

// Active Tab
const activeTab = ref('general')

// Settings Data
const settings = ref({
  language: 'en',
  timezone: 'UTC',
  dateFormat: 'DD/MM/YYYY'
})

const profile = ref({
  name: '',
  email: '',
  phone: '',
  bio: ''
})

const security = ref({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
  twoFactorEnabled: false,
  sessionTimeout: 30
})

const notifications = ref({
  email: true,
  push: true,
  sms: false,
  types: ['system', 'security']
})

// Options
const languages = ref([
  { name: 'English', code: 'en' },
  { name: 'Indonesia', code: 'id' },
  { name: 'Spanish', code: 'es' },
  { name: 'French', code: 'fr' }
])

const timezones = ref([
  { name: 'UTC', value: 'UTC' },
  { name: 'Asia/Jakarta (WIB)', value: 'Asia/Jakarta' },
  { name: 'America/New_York (EST)', value: 'America/New_York' },
  { name: 'Europe/London (GMT)', value: 'Europe/London' }
])

const dateFormats = ref([
  { label: 'DD/MM/YYYY', value: 'DD/MM/YYYY' },
  { label: 'MM/DD/YYYY', value: 'MM/DD/YYYY' },
  { label: 'YYYY-MM-DD', value: 'YYYY-MM-DD' }
])

const sessionTimeouts = ref([
  { label: '15 minutes', value: 15 },
  { label: '30 minutes', value: 30 },
  { label: '1 hour', value: 60 },
  { label: '2 hours', value: 120 },
  { label: 'Never', value: 0 }
])

// ── Transaction Settings (PPN & Service Charge) ───────────────────────────────
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
  if (!outletId.value) return
  txLoading.value = true
  try {
    const res = await api.get(`/outlets/${outletId.value}/transaction-settings`)
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
    }
    txSettings.value.receipt_logo_enabled = Boolean(d.receipt_logo_enabled ?? true)
    txSettings.value.receipt_custom_logo_url = d.receipt_custom_logo_url || ''
    txSettings.value.receipt_header = d.receipt_header || ''
    txSettings.value.receipt_show_qr = Boolean(d.receipt_show_qr ?? true)
    txSettings.value.receipt_wifi_enabled = Boolean(d.receipt_wifi_enabled)
    txSettings.value.receipt_wifi_ssid = d.receipt_wifi_ssid || ''
    txSettings.value.receipt_wifi_password = d.receipt_wifi_password || ''
    txSettings.value.receipt_show_cashier = Boolean(d.receipt_show_cashier ?? true)
    txSettings.value.receipt_show_table = Boolean(d.receipt_show_table ?? true)
    txSettings.value.receipt_show_member = Boolean(d.receipt_show_member ?? true)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat pengaturan transaksi', life: 3000 })
  } finally {
    txLoading.value = false
  }
}

const saveTxSettings = async () => {
  if (!outletId.value) {
    toast.add({ severity: 'warn', summary: 'Perhatian', detail: 'Buka halaman ini dari menu outlet, bukan langsung.', life: 4000 })
    return
  }
  txSaving.value = true
  try {
    await api.put(`/outlets/${outletId.value}/transaction-settings`, txSettings.value)
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
  let tax = txSettings.value.tax_enabled && !txSettings.value.tax_inclusive
    ? Math.round(base * txSettings.value.tax_percentage / 100)
    : 0
  let sc = txSettings.value.service_charge_enabled
    ? Math.round(base * txSettings.value.service_charge_percentage / 100)
    : 0
  return base + tax + sc
}

onMounted(() => {
  fetchTxSettings()
})
</script>

<style scoped>
.view-container {
  max-width: 1200px;
  margin: 0 auto;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.tab-content {
  padding: 1.5rem 0;
}

.tab-content h3 {
  margin: 0 0 0.5rem 0;
  color: #1f2937;
  font-size: 1.5rem;
}

.text-muted {
  color: #6b7280;
  font-size: 0.95rem;
}

.settings-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  margin-bottom: 2rem;
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

.notification-category {
  margin: 1rem 0 0.5rem 0;
  color: #1f2937;
  font-size: 1.1rem;
  font-weight: 600;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.mr-2 {
  margin-right: 0.5rem;
}

.mb-4 {
  margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
  .setting-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .setting-info {
    margin-right: 0;
    margin-bottom: 1rem;
  }

  .setting-control {
    width: 100%;
    min-width: auto;
  }
}

/* ── Transaction Settings ── */
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

/* dark mode overrides untuk tx panel */
html.is-dark .tx-section-title { color: #e4e4ef; }
html.is-dark .tx-preview {
  background: #1a1a24 !important;
  border-color: #2a2a38 !important;
}
html.is-dark .tx-preview-row { color: #d0d0e8; border-color: #2a2a38; }
html.is-dark .tx-preview-row.total { color: #8ab4ff; border-color: #2a2a38; }

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
</style>
