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

    <Message v-if="!authStore.isSuperAdmin" severity="error" :closable="false" class="mb-4">
      {{ $t('common.forbidden') || 'Access denied — superadmin only.' }}
    </Message>

    <Card v-else>
      <template #title>
        <div class="card-header">
          <span>
            <i class="pi pi-globe mr-2"></i>
            {{ $t('siteSettings.title') }}
          </span>
          <Button
            :label="$t('common.save') || 'Save'"
            icon="pi pi-save"
            :loading="saving"
            @click="saveAll"
          />
        </div>
      </template>
      <template #content>
        <div v-if="loading" class="loading-row">
          <ProgressSpinner style="width:48px;height:48px" strokeWidth="4" />
        </div>

        <Tabs v-else v-model:value="activeTab">
          <TabList>
            <Tab value="general">
              <i class="pi pi-cog mr-2"></i>
              {{ $t('siteSettings.general') }}
            </Tab>
            <Tab value="branding">
              <i class="pi pi-palette mr-2"></i>
              {{ $t('siteSettings.branding') }}
            </Tab>
            <Tab value="contact">
              <i class="pi pi-phone mr-2"></i>
              {{ $t('siteSettings.contact') }}
            </Tab>
            <Tab value="social">
              <i class="pi pi-share-alt mr-2"></i>
              {{ $t('siteSettings.social') }}
            </Tab>
          </TabList>

          <TabPanels>
            <!-- General -->
            <TabPanel value="general">
              <div class="tab-content">
                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.siteName') }}</label>
                  <InputText v-model="settings.site_name" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('site_name') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.tagline') }}</label>
                  <InputText v-model="settings.site_tagline" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('site_tagline') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.description') }}</label>
                  <Textarea v-model="settings.site_description" rows="3" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('site_description') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.footerText') }}</label>
                  <InputText v-model="settings.footer_text" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('footer_text') }}</small>
                </div>
              </div>
            </TabPanel>

            <!-- Branding -->
            <TabPanel value="branding">
              <div class="tab-content">
                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.logo') }}</label>
                  <div class="image-row">
                    <div class="image-preview">
                      <img v-if="settings.site_logo" :src="resolveImg(settings.site_logo)" alt="logo" />
                      <span v-else class="image-placeholder">
                        <i class="pi pi-image"></i>
                      </span>
                    </div>
                    <div class="image-actions">
                      <FileUpload
                        mode="basic"
                        :auto="true"
                        accept="image/*"
                        :maxFileSize="5242880"
                        :customUpload="true"
                        :chooseLabel="$t('siteSettings.uploadLogo')"
                        @uploader="(e) => uploadImage(e, 'site_logo')"
                      />
                    </div>
                  </div>
                  <small class="field-hint">{{ descriptionOf('site_logo') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.logoDark') }}</label>
                  <div class="image-row">
                    <div class="image-preview image-preview-dark">
                      <img v-if="settings.site_logo_dark" :src="resolveImg(settings.site_logo_dark)" alt="logo dark" />
                      <span v-else class="image-placeholder">
                        <i class="pi pi-image"></i>
                      </span>
                    </div>
                    <div class="image-actions">
                      <FileUpload
                        mode="basic"
                        :auto="true"
                        accept="image/*"
                        :maxFileSize="5242880"
                        :customUpload="true"
                        :chooseLabel="$t('siteSettings.uploadLogo')"
                        @uploader="(e) => uploadImage(e, 'site_logo_dark')"
                      />
                    </div>
                  </div>
                  <small class="field-hint">{{ descriptionOf('site_logo_dark') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.favicon') }}</label>
                  <div class="image-row">
                    <div class="image-preview image-preview-favicon">
                      <img v-if="settings.site_favicon" :src="resolveImg(settings.site_favicon)" alt="favicon" />
                      <span v-else class="image-placeholder">
                        <i class="pi pi-image"></i>
                      </span>
                    </div>
                    <div class="image-actions">
                      <FileUpload
                        mode="basic"
                        :auto="true"
                        accept="image/*"
                        :maxFileSize="5242880"
                        :customUpload="true"
                        :chooseLabel="$t('siteSettings.uploadFavicon')"
                        @uploader="(e) => uploadImage(e, 'site_favicon')"
                      />
                    </div>
                  </div>
                  <small class="field-hint">{{ descriptionOf('site_favicon') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">{{ $t('siteSettings.primaryColor') }}</label>
                  <div class="color-row">
                    <ColorPicker v-model="primaryColorHex" format="hex" />
                    <InputText v-model="settings.primary_color" class="color-input" />
                  </div>
                  <small class="field-hint">{{ descriptionOf('primary_color') }}</small>
                </div>
              </div>
            </TabPanel>

            <!-- Contact -->
            <TabPanel value="contact">
              <div class="tab-content">
                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-envelope mr-2"></i>
                    {{ labelOf('contact_email') }}
                  </label>
                  <InputText v-model="settings.contact_email" type="email" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('contact_email') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-phone mr-2"></i>
                    {{ labelOf('contact_phone') }}
                  </label>
                  <InputText v-model="settings.contact_phone" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('contact_phone') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-map-marker mr-2"></i>
                    {{ labelOf('contact_address') }}
                  </label>
                  <Textarea v-model="settings.contact_address" rows="3" class="w-full" />
                  <small class="field-hint">{{ descriptionOf('contact_address') }}</small>
                </div>
              </div>
            </TabPanel>

            <!-- Social -->
            <TabPanel value="social">
              <div class="tab-content">
                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-instagram mr-2"></i>
                    {{ labelOf('social_instagram') }}
                  </label>
                  <IconField>
                    <InputIcon><i class="pi pi-instagram" /></InputIcon>
                    <InputText v-model="settings.social_instagram" class="w-full" placeholder="https://instagram.com/..." />
                  </IconField>
                  <small class="field-hint">{{ descriptionOf('social_instagram') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-facebook mr-2"></i>
                    {{ labelOf('social_facebook') }}
                  </label>
                  <IconField>
                    <InputIcon><i class="pi pi-facebook" /></InputIcon>
                    <InputText v-model="settings.social_facebook" class="w-full" placeholder="https://facebook.com/..." />
                  </IconField>
                  <small class="field-hint">{{ descriptionOf('social_facebook') }}</small>
                </div>

                <div class="field-group">
                  <label class="field-label">
                    <i class="pi pi-twitter mr-2"></i>
                    {{ labelOf('social_twitter') }}
                  </label>
                  <IconField>
                    <InputIcon><i class="pi pi-twitter" /></InputIcon>
                    <InputText v-model="settings.social_twitter" class="w-full" placeholder="https://x.com/..." />
                  </IconField>
                  <small class="field-hint">{{ descriptionOf('social_twitter') }}</small>
                </div>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import FileUpload from 'primevue/fileupload'
import ColorPicker from 'primevue/colorpicker'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'

const toast = useToast()
const { t } = useI18n()
const authStore = useAuthStore()

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('common.admin') || 'Admin', to: '/dashboard' },
  { label: t('siteSettings.title') }
])

const activeTab = ref('general')
const loading = ref(false)
const saving = ref(false)

const settings = reactive({
  site_name: '',
  site_tagline: '',
  site_description: '',
  site_logo: '',
  site_logo_dark: '',
  site_favicon: '',
  primary_color: '#06b6d4',
  contact_email: '',
  contact_phone: '',
  contact_address: '',
  social_instagram: '',
  social_facebook: '',
  social_twitter: '',
  footer_text: '',
})

const meta = ref({})

const primaryColorHex = computed({
  get: () => (settings.primary_color || '#06b6d4').replace(/^#/, ''),
  set: (v) => {
    if (!v) return
    settings.primary_color = v.startsWith('#') ? v : '#' + v
  }
})

const descriptionOf = (key) => meta.value[key]?.description || ''
const labelOf = (key) => meta.value[key]?.label || key

const resolveImg = (path) => {
  if (!path) return ''
  if (/^https?:\/\//i.test(path)) return path
  const base = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return base + path
}

const fetchSettings = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/site-settings')
    const flat = data.settings || {}
    const all = data.all || []
    Object.keys(settings).forEach((k) => {
      if (flat[k] !== undefined && flat[k] !== null) settings[k] = flat[k]
    })
    const metaMap = {}
    all.forEach((row) => {
      metaMap[row.key] = { label: row.label, description: row.description, type: row.type, group: row.group }
    })
    meta.value = metaMap
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error') || 'Error', detail: 'Failed to load site settings', life: 3000 })
  } finally {
    loading.value = false
  }
}

const saveAll = async () => {
  saving.value = true
  try {
    await api.put('/site-settings', { settings: { ...settings } })
    toast.add({ severity: 'success', summary: t('messages.success') || 'Success', detail: t('siteSettings.saved'), life: 3000 })
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save site settings'
    toast.add({ severity: 'error', summary: t('messages.error') || 'Error', detail: msg, life: 3000 })
  } finally {
    saving.value = false
  }
}

const uploadImage = async (event, key) => {
  const file = event.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('image', file)
  fd.append('key', key)
  try {
    const { data } = await api.post('/site-settings/upload', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    if (data?.url) {
      settings[key] = data.url
      toast.add({ severity: 'success', summary: t('messages.success') || 'Success', detail: 'Image uploaded', life: 2500 })
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Upload failed'
    toast.add({ severity: 'error', summary: t('messages.error') || 'Error', detail: msg, life: 3000 })
  }
}

onMounted(fetchSettings)
</script>

<style scoped>
.view-container {
  max-width: 1100px;
  margin: 0 auto;
}

.mb-4 {
  margin-bottom: 1rem;
}

.mr-2 {
  margin-right: 0.5rem;
}

.w-full {
  width: 100%;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.loading-row {
  display: flex;
  justify-content: center;
  padding: 3rem;
}

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1rem 0;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field-label {
  font-weight: 600;
  color: #374151;
}

.field-hint {
  color: #6b7280;
  font-size: 0.8rem;
}

.image-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.image-preview {
  width: 160px;
  height: 80px;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
  overflow: hidden;
}

.image-preview img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.image-preview-dark {
  background: #1f2937;
}

.image-preview-favicon {
  width: 64px;
  height: 64px;
}

.image-placeholder {
  color: #9ca3af;
  font-size: 1.5rem;
}

.image-actions {
  display: flex;
  align-items: center;
}

.color-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.color-input {
  width: 140px;
}
</style>
