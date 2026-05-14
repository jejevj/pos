<template>
  <div class="view-container">
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
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
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

const router = useRouter()
const { t } = useI18n()

// Breadcrumb
const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
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
</style>
