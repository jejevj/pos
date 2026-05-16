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
        <button
          class="tab"
          :class="{ active: activeTab === 'membership' }"
          @click="activeTab = 'membership'"
        >
          <i class="pi pi-id-card"></i>
          Membership
        </button>
        <button
          class="tab"
          :class="{ active: activeTab === 'whatsapp' }"
          @click="activeTab = 'whatsapp'"
        >
          <i class="pi pi-whatsapp" style="color:#25d366"></i>
          WhatsApp
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

    <!-- Membership Tab -->
    <div v-show="activeTab === 'membership'" class="tab-content settings-view">
      <Card>
        <template #header>
          <div class="card-header">
            <h3>Pengaturan Halaman Pendaftaran Member</h3>
          </div>
        </template>
        <template #content>
          <div v-if="memberLoading" class="flex justify-center py-8">
            <ProgressSpinner style="width:40px;height:40px" strokeWidth="4" />
          </div>

          <template v-else>
            <!-- Toggle buka/tutup -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-power-off"></i>
                Status Pendaftaran
              </div>
              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Buka Pendaftaran Member</label>
                    <p class="setting-description">Izinkan pelanggan mendaftar via halaman publik.</p>
                  </div>
                  <ToggleSwitch v-model="memberSettings.registration_open" />
                </div>
              </div>
            </div>

            <!-- URL Publik dengan QR -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-link"></i>
                URL Pendaftaran &amp; QR Code
              </div>
              <div class="settings-section">
                <div class="setting-item member-url-row">
                  <div class="setting-info" style="flex: 1; min-width: 0;">
                    <label class="setting-label">URL Halaman Pendaftaran</label>
                    <p class="setting-description">Bagikan link ini ke pelanggan untuk mendaftar member.</p>
                    <div class="url-display">
                      <code>{{ memberPageUrl || '—' }}</code>
                      <Button icon="pi pi-copy" text size="small" :disabled="!memberPageUrl" @click="copyMemberUrl" aria-label="Copy URL" />
                      <Button icon="pi pi-external-link" text size="small" :disabled="!memberPageUrl" @click="openMemberUrl" aria-label="Open URL" />
                    </div>
                  </div>
                </div>
                <div v-if="memberPageUrl" class="qr-preview">
                  <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(memberPageUrl)}`" alt="QR" />
                  <div class="qr-hint">Scan untuk membuka halaman pendaftaran</div>
                </div>
              </div>
            </div>

            <!-- Kustomisasi halaman -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-palette"></i>
                Kustomisasi Tampilan
              </div>
              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Judul Halaman</label>
                    <p class="setting-description">Teks besar yang muncul di hero halaman pendaftaran.</p>
                  </div>
                  <InputText v-model="memberSettings.page_title" class="setting-control" />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Deskripsi Halaman</label>
                    <p class="setting-description">Penjelasan singkat program member.</p>
                  </div>
                  <Textarea v-model="memberSettings.page_description" rows="3" autoResize class="setting-control full" />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesan Sambutan</label>
                    <p class="setting-description">Pesan setelah pelanggan berhasil mendaftar.</p>
                  </div>
                  <Textarea v-model="memberSettings.welcome_message" rows="3" autoResize class="setting-control full" />
                </div>

                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Warna Tema</label>
                    <p class="setting-description">Kosongkan untuk pakai warna default. Contoh: #667eea</p>
                  </div>
                  <div class="color-input-wrap">
                    <input
                      type="color"
                      :value="memberSettings.custom_primary_color || '#667eea'"
                      @input="memberSettings.custom_primary_color = $event.target.value"
                      class="color-swatch"
                    />
                    <InputText v-model="memberSettings.custom_primary_color" placeholder="#667eea" class="color-text" />
                    <Button v-if="memberSettings.custom_primary_color" icon="pi pi-times" text size="small" @click="memberSettings.custom_primary_color = ''" aria-label="Reset color" />
                  </div>
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Logo Custom</label>
                    <p class="setting-description">Opsional. Kosongkan untuk pakai logo outlet. Upload gambar (JPG/PNG/WEBP, maks 2MB).</p>
                  </div>
                  <div class="logo-upload-row">
                    <img v-if="memberSettings.custom_logo_url" :src="memberSettings.custom_logo_url" class="logo-preview-sm" alt="Logo" />
                    <input type="file" accept="image/*" @change="handleMemberLogoUpload" :disabled="memberLogoUploading" />
                    <ProgressSpinner v-if="memberLogoUploading" style="width:20px;height:20px" strokeWidth="4" />
                    <Button v-if="memberSettings.custom_logo_url" icon="pi pi-times" text size="small" @click="memberSettings.custom_logo_url = ''" aria-label="Remove logo" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Benefits editor -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-star"></i>
                Benefit Member
              </div>
              <div class="settings-section">
                <p class="setting-description" style="margin: 0 0 0.75rem;">Daftar keuntungan yang ditampilkan di halaman pendaftaran.</p>
                <div class="benefits-editor">
                  <div v-for="(_, i) in memberSettings.benefits" :key="i" class="benefit-row">
                    <InputText
                      v-model="memberSettings.benefits[i]"
                      :placeholder="'Contoh: Diskon 10% setiap pembelian'"
                      class="setting-control full"
                    />
                    <Button icon="pi pi-trash" severity="danger" text size="small" @click="removeBenefit(i)" aria-label="Hapus benefit" />
                  </div>
                  <Button icon="pi pi-plus" label="Tambah Benefit" text @click="addBenefit" />
                </div>
              </div>
            </div>

            <!-- Requirements -->
            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-shield"></i>
                Persyaratan &amp; Persetujuan
              </div>
              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Wajib Isi Nomor Telepon</label>
                    <p class="setting-description">Calon member harus mengisi no. telepon.</p>
                  </div>
                  <ToggleSwitch v-model="memberSettings.require_phone" />
                </div>
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Wajib Isi Alamat</label>
                    <p class="setting-description">Calon member harus mengisi alamat lengkap.</p>
                  </div>
                  <ToggleSwitch v-model="memberSettings.require_address" />
                </div>
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Setujui Otomatis</label>
                    <p class="setting-description">Member langsung aktif tanpa persetujuan manual.</p>
                  </div>
                  <ToggleSwitch v-model="memberSettings.auto_approve" />
                </div>
              </div>
            </div>

            <div class="form-actions">
              <Button label="Simpan Pengaturan Membership" icon="pi pi-check" :loading="memberSaving" @click="saveMemberSettings" />
              <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined @click="fetchMemberSettings" />
            </div>
          </template>
        </template>
      </Card>
    </div>

    <!-- WhatsApp Tab -->
    <div v-show="activeTab === 'whatsapp'" class="tab-content settings-view">
      <Card>
        <template #header>
          <div class="card-header">
            <h3>Template Pesan WhatsApp Pesanan</h3>
          </div>
        </template>
        <template #content>
          <div v-if="waLoading" class="flex justify-center py-8">
            <ProgressSpinner style="width:40px;height:40px" strokeWidth="4" />
          </div>

          <template v-else>
            <div class="wa-placeholder-hint">
              <i class="pi pi-info-circle"></i>
              <div>
                <strong>Placeholder yang tersedia.</strong>
                Tarik chip ke dalam kotak template, atau klik untuk menyisipkan di posisi kursor pada template yang sedang difokus.
                Kosongkan template untuk pakai pesan default.
              </div>
            </div>

            <div class="wa-placeholder-bar" role="toolbar" aria-label="Placeholder template WhatsApp">
              <span
                v-for="ph in waPlaceholders"
                :key="ph.token"
                class="wa-chip"
                draggable="true"
                :title="ph.hint"
                @dragstart="onChipDragStart($event, ph.token)"
                @click="insertPlaceholder(ph.token)"
              >
                <i class="pi pi-bars wa-chip-grip" aria-hidden="true"></i>
                <span class="wa-chip-label">{{ ph.label }}</span>
                <code class="wa-chip-token">{{ ph.token }}</code>
              </span>
            </div>

            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-bell"></i>
                Notifikasi Aktif
              </div>
              <div class="settings-section">
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Kirim saat pesanan mulai diproses</label>
                    <p class="setting-description">Notifikasi dikirim sekali ketika bar atau kitchen pertama kali mulai memproses pesanan.</p>
                  </div>
                  <ToggleSwitch v-model="waSettings.notify_processing" />
                </div>
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Kirim saat pesanan siap</label>
                    <p class="setting-description">Notifikasi dikirim ketika semua item pesanan sudah selesai disiapkan.</p>
                  </div>
                  <ToggleSwitch v-model="waSettings.notify_ready" />
                </div>
                <div class="setting-item">
                  <div class="setting-info">
                    <label class="setting-label">Kirim saat pesanan selesai (sudah diantar/diambil)</label>
                    <p class="setting-description">Notifikasi dikirim ketika seluruh item dalam satu pesanan telah ditandai <em>served</em> oleh staff (selesai dilayani).</p>
                  </div>
                  <ToggleSwitch v-model="waSettings.notify_completed" />
                </div>
              </div>
            </div>

            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-cog"></i>
                Template Persetujuan Kasir
              </div>
              <div class="settings-section">
                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Disetujui</label>
                    <p class="setting-description">Dikirim saat kasir menyetujui pesanan publik (table/takeaway).</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_approved"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_approved', el)"
                    @focus="waActiveField = 'tpl_approved'"
                    @drop="onTemplateDrop($event, 'tpl_approved')"
                  />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Ditolak</label>
                    <p class="setting-description">Dikirim saat kasir menolak pesanan. Placeholder <code>{alasan}</code> berisi alasan penolakan.</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_rejected"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_rejected', el)"
                    @focus="waActiveField = 'tpl_rejected'"
                    @drop="onTemplateDrop($event, 'tpl_rejected')"
                  />
                </div>
              </div>
            </div>

            <div class="tx-section">
              <div class="tx-section-title">
                <i class="pi pi-clock"></i>
                Template Progres Pesanan
              </div>
              <div class="settings-section">
                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Mulai Diproses</label>
                    <p class="setting-description">Dikirim sekali ketika bar atau kitchen mulai memproses pesanan (mana yang lebih dulu).</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_processing"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_processing', el)"
                    @focus="waActiveField = 'tpl_processing'"
                    @drop="onTemplateDrop($event, 'tpl_processing')"
                  />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Siap (Dine-in / Delivery)</label>
                    <p class="setting-description">Dikirim saat pesanan siap diantar ke meja. Cocok dipakai untuk dine-in dan delivery.</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_ready_dinein"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_ready_dinein', el)"
                    @focus="waActiveField = 'tpl_ready_dinein'"
                    @drop="onTemplateDrop($event, 'tpl_ready_dinein')"
                  />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Siap (Takeaway / Pickup)</label>
                    <p class="setting-description">Dikirim saat pesanan takeaway siap diambil di kasir.</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_ready_takeaway"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_ready_takeaway', el)"
                    @focus="waActiveField = 'tpl_ready_takeaway'"
                    @drop="onTemplateDrop($event, 'tpl_ready_takeaway')"
                  />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Selesai (Dine-in / Delivery)</label>
                    <p class="setting-description">Dikirim sekali ketika seluruh item pesanan dine-in/delivery sudah ditandai <em>served</em> (telah diantar ke pelanggan).</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_completed_dinein"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_completed_dinein', el)"
                    @focus="waActiveField = 'tpl_completed_dinein'"
                    @drop="onTemplateDrop($event, 'tpl_completed_dinein')"
                  />
                </div>

                <div class="setting-item setting-item-block">
                  <div class="setting-info">
                    <label class="setting-label">Pesanan Selesai (Takeaway / Pickup)</label>
                    <p class="setting-description">Dikirim sekali ketika seluruh item pesanan takeaway sudah ditandai <em>served</em> (diserahkan ke pelanggan).</p>
                  </div>
                  <Textarea
                    v-model="waSettings.tpl_completed_takeaway"
                    rows="4"
                    autoResize
                    class="setting-control full"
                    placeholder="Kosongkan untuk pakai pesan default."
                    :ref="el => registerWaField('tpl_completed_takeaway', el)"
                    @focus="waActiveField = 'tpl_completed_takeaway'"
                    @drop="onTemplateDrop($event, 'tpl_completed_takeaway')"
                  />
                </div>
              </div>
            </div>

            <div class="form-actions">
              <Button label="Simpan Template WhatsApp" icon="pi pi-check" :loading="waSaving" @click="saveWaSettings" />
              <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined @click="fetchWaSettings" />
            </div>
          </template>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
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
const outletSlug = ref('')

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
    
    outletSlug.value = outlet.slug || ''
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

// ──────────────── Membership Settings ────────────────
const memberLoading = ref(false)
const memberSaving = ref(false)
const memberLogoUploading = ref(false)
const memberSettings = ref({
  registration_open: false,
  page_title: 'Daftar Member',
  page_description: '',
  benefits: [],
  welcome_message: 'Selamat datang di program member kami!',
  require_phone: true,
  require_address: false,
  auto_approve: true,
  custom_primary_color: '',
  custom_logo_url: '',
})

const memberPageUrl = computed(() => {
  if (!outletSlug.value) return ''
  return `${window.location.origin}/m/${outletSlug.value}`
})

const fetchMemberSettings = async () => {
  if (!numericOutletId) return
  memberLoading.value = true
  try {
    const res = await api.get(`/outlets/${numericOutletId}/membership-settings`)
    const d = res.data || {}
    let benefits = d.benefits
    if (typeof benefits === 'string') {
      try { benefits = JSON.parse(benefits || '[]') } catch { benefits = [] }
    }
    if (!Array.isArray(benefits)) benefits = []
    memberSettings.value = {
      registration_open:    Boolean(d.registration_open),
      page_title:           d.page_title           || 'Daftar Member',
      page_description:     d.page_description     || '',
      benefits,
      welcome_message:      d.welcome_message      || 'Selamat datang di program member kami!',
      require_phone:        Boolean(d.require_phone ?? true),
      require_address:      Boolean(d.require_address ?? false),
      auto_approve:         Boolean(d.auto_approve ?? true),
      custom_primary_color: d.custom_primary_color || '',
      custom_logo_url:      d.custom_logo_url      || '',
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat pengaturan membership', life: 3000 })
  } finally {
    memberLoading.value = false
  }
}

const saveMemberSettings = async () => {
  if (!numericOutletId) {
    toast.add({ severity: 'warn', summary: 'Perhatian', detail: 'Buka halaman ini dari menu outlet.', life: 4000 })
    return
  }
  memberSaving.value = true
  try {
    const payload = {
      ...memberSettings.value,
      benefits: (memberSettings.value.benefits || []).filter(b => String(b || '').trim() !== ''),
    }
    await api.put(`/outlets/${numericOutletId}/membership-settings`, payload)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Pengaturan membership disimpan.', life: 3000 })
  } catch (e) {
    const msg = e.response?.data?.message || 'Gagal menyimpan pengaturan membership'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
  } finally {
    memberSaving.value = false
  }
}

const addBenefit = () => {
  memberSettings.value.benefits.push('')
}
const removeBenefit = (i) => {
  memberSettings.value.benefits.splice(i, 1)
}

const copyMemberUrl = async () => {
  if (!memberPageUrl.value) return
  try {
    await navigator.clipboard.writeText(memberPageUrl.value)
    toast.add({ severity: 'success', summary: 'Disalin', detail: 'URL pendaftaran disalin ke clipboard.', life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Tidak bisa menyalin URL.', life: 3000 })
  }
}

const openMemberUrl = () => {
  if (memberPageUrl.value) window.open(memberPageUrl.value, '_blank', 'noopener')
}

async function handleMemberLogoUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  if (file.size > 2_000_000) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'File terlalu besar. Maksimal 2MB', life: 3000 })
    event.target.value = ''
    return
  }
  memberLogoUploading.value = true
  try {
    const formData = new FormData()
    formData.append('image', file)
    const response = await api.post(
      `/outlets/${numericOutletId}/upload/image`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    memberSettings.value.custom_logo_url = response.data.url
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Logo diupload. Jangan lupa Simpan.', life: 3000 })
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal mengupload logo'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
  } finally {
    memberLogoUploading.value = false
    event.target.value = ''
  }
}

// ── WhatsApp Templates ────────────────────────────────────────────────
const waLoading = ref(false)
const waSaving = ref(false)
const waSettings = ref({
  notify_processing:      true,
  notify_ready:           true,
  notify_completed:       true,
  tpl_approved:           '',
  tpl_rejected:           '',
  tpl_processing:         '',
  tpl_ready_dinein:       '',
  tpl_ready_takeaway:     '',
  tpl_completed_dinein:   '',
  tpl_completed_takeaway: '',
})

// Placeholder chips: dragged/clicked to insert tokens into the active
// template textarea. Tokens must match OrderMessageTemplate::vars() keys.
const waPlaceholders = [
  { token: '{nama_pelanggan}', label: 'Nama Pelanggan', hint: 'Nama pelanggan dari pesanan' },
  { token: '{link_tracking}',  label: 'Link Tracking',  hint: 'URL publik untuk pantau status pesanan' },
  { token: '{kode_pesanan}',   label: 'Kode Pesanan',   hint: 'Kode unik pesanan' },
  { token: '{nama_outlet}',    label: 'Nama Outlet',    hint: 'Nama outlet pengirim pesan' },
  { token: '{tipe_pesanan}',   label: 'Tipe Pesanan',   hint: 'Dine-in / Takeaway / Delivery' },
  { token: '{nomor_meja}',     label: 'Nomor Meja',     hint: 'Nomor meja untuk dine-in' },
  { token: '{total}',          label: 'Total',          hint: 'Total tagihan pesanan' },
  { token: '{status}',         label: 'Status',         hint: 'Status pesanan saat ini' },
  { token: '{alasan}',         label: 'Alasan',         hint: 'Alasan penolakan (jika ada)' },
]

const waActiveField = ref('tpl_processing')
const waFieldRefs = {}

const registerWaField = (key, comp) => {
  // PrimeVue Textarea wraps a native <textarea>; expose either the
  // component instance or the raw DOM element so we can read selection.
  waFieldRefs[key] = comp || null
}

const resolveTextarea = (key) => {
  const ref = waFieldRefs[key]
  if (!ref) return null
  // PrimeVue exposes the underlying <textarea> as `.$el` (component) or
  // the element itself if a plain element was passed.
  const el = ref.$el || ref
  if (!el) return null
  if (el.tagName === 'TEXTAREA') return el
  return el.querySelector ? el.querySelector('textarea') : null
}

const insertPlaceholder = (token, explicitKey = null) => {
  const key = explicitKey || waActiveField.value
  if (!key || !(key in waSettings.value)) return
  const ta = resolveTextarea(key)
  const current = waSettings.value[key] || ''
  if (ta && typeof ta.selectionStart === 'number') {
    const start = ta.selectionStart
    const end = ta.selectionEnd
    const next = current.slice(0, start) + token + current.slice(end)
    waSettings.value[key] = next
    // Restore caret position right after the inserted token
    nextTick(() => {
      ta.focus()
      const caret = start + token.length
      try { ta.setSelectionRange(caret, caret) } catch (_) { /* ignore */ }
    })
  } else {
    waSettings.value[key] = current + token
  }
  waActiveField.value = key
}

const onChipDragStart = (event, token) => {
  // Native textareas accept dropped text/plain payloads, which inserts
  // them at the drop position automatically — so we just hand off the
  // token and let the browser handle the insertion.
  if (event.dataTransfer) {
    event.dataTransfer.setData('text/plain', token)
    event.dataTransfer.effectAllowed = 'copy'
  }
}

const onTemplateDrop = (event, key) => {
  // The browser will already drop the text/plain payload at the cursor.
  // We just keep `waActiveField` in sync so the next chip-click targets
  // this textarea, and read back the value after the native drop.
  waActiveField.value = key
  nextTick(() => {
    const ta = resolveTextarea(key)
    if (ta && typeof ta.value === 'string' && ta.value !== waSettings.value[key]) {
      waSettings.value[key] = ta.value
    }
  })
}

const fetchWaSettings = async () => {
  if (!numericOutletId) return
  waLoading.value = true
  try {
    const res = await api.get(`/outlets/${numericOutletId}/whatsapp/settings`)
    const d = res.data || {}
    waSettings.value = {
      notify_processing:      Boolean(d.notify_processing ?? true),
      notify_ready:           Boolean(d.notify_ready ?? true),
      notify_completed:       Boolean(d.notify_completed ?? true),
      tpl_approved:           d.tpl_approved             || '',
      tpl_rejected:           d.tpl_rejected             || '',
      tpl_processing:         d.tpl_processing           || '',
      tpl_ready_dinein:       d.tpl_ready_dinein         || '',
      tpl_ready_takeaway:     d.tpl_ready_takeaway       || '',
      tpl_completed_dinein:   d.tpl_completed_dinein     || '',
      tpl_completed_takeaway: d.tpl_completed_takeaway   || '',
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat template WhatsApp', life: 3000 })
  } finally {
    waLoading.value = false
  }
}

const saveWaSettings = async () => {
  if (!numericOutletId) {
    toast.add({ severity: 'warn', summary: 'Perhatian', detail: 'Buka halaman ini dari menu outlet.', life: 4000 })
    return
  }
  waSaving.value = true
  try {
    await api.put(`/outlets/${numericOutletId}/whatsapp/settings`, waSettings.value)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Template WhatsApp disimpan.', life: 3000 })
  } catch (e) {
    const msg = e.response?.data?.message || 'Gagal menyimpan template WhatsApp'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
  } finally {
    waSaving.value = false
  }
}

onMounted(() => {
  loadOutletData()
  fetchTxSettings()
  fetchMemberSettings()
  fetchWaSettings()
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

/* ─── Membership tab specifics ─── */
.url-display {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.4rem;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0.4rem 0.6rem;
}
.url-display code {
  flex: 1;
  font-size: 0.85rem;
  word-break: break-all;
  color: #2563eb;
}
html.is-dark .url-display {
  background: #1f2937;
  border-color: #374151;
}
html.is-dark .url-display code { color: #60a5fa; }

.qr-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.75rem;
}
.qr-preview img {
  width: 140px;
  height: 140px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  padding: 4px;
}
.qr-hint {
  font-size: 0.75rem;
  color: #6b7280;
}

.benefits-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.benefit-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.benefit-row :deep(.p-inputtext) { flex: 1; }

.color-input-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.color-swatch {
  width: 36px;
  height: 36px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  background: none;
  padding: 2px;
}
.color-text {
  width: 140px;
}

.logo-upload-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.logo-preview-sm {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
}

.setting-item-block {
  flex-direction: column;
  align-items: stretch;
  gap: 0.5rem;
}
.setting-control.full { width: 100%; }
.member-url-row { align-items: flex-start; }

.wa-placeholder-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  margin-bottom: 1rem;
  border: 1px solid #e0f2fe;
  background: #f0f9ff;
  color: #0c4a6e;
  border-radius: 6px;
  font-size: 0.875rem;
}
.wa-placeholder-hint i { font-size: 1.25rem; line-height: 1.4; color: #0284c7; }
.wa-placeholder-hint code {
  padding: 0 0.25rem;
  background: rgba(2, 132, 199, 0.1);
  border-radius: 3px;
  font-size: 0.85em;
  margin: 0 0.1rem;
}

.wa-placeholder-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  padding: 0.75rem;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  background: #f8fafc;
}
.wa-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  font-size: 0.8125rem;
  color: #0f172a;
  cursor: grab;
  user-select: none;
  transition: background 0.12s ease, border-color 0.12s ease, transform 0.05s ease;
}
.wa-chip:hover { background: #e0f2fe; border-color: #0284c7; }
.wa-chip:active { cursor: grabbing; transform: translateY(1px); }
.wa-chip-grip { font-size: 0.7rem; color: #94a3b8; }
.wa-chip-label { font-weight: 600; }
.wa-chip-token {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
  font-size: 0.75rem;
  color: #475569;
  padding: 0 0.3rem;
  background: #f1f5f9;
  border-radius: 3px;
}
</style>
