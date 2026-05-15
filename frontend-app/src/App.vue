<template>
  <div id="app">
    <RouterView />
    <Toast />
    <!-- WhatsApp notifications: bottom-right, white background -->
    <Toast
      group="wa"
      position="bottom-right"
      :pt="{
        root: { style: 'bottom: 1.5rem; right: 1.5rem;' },
        message: { style: 'background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #25d366; box-shadow: 0 4px 16px rgba(0,0,0,0.12); border-radius: 8px;' },
        summary: { style: 'color: #111827; font-weight: 600;' },
        detail: { style: 'color: #374151;' },
        icon: { style: 'color: #25d366;' }
      }"
    />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettings } from '@/stores/siteSettings'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'

const authStore  = useAuthStore()
const siteSettings = useSiteSettings()

onMounted(() => {
  authStore.initAuth()
  siteSettings.fetch()   // load site identity (name, logo, favicon) saat app start
})
</script>

<style scoped>
#app {
  min-height: 100vh;
}
</style>
