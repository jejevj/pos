import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useSiteSettings = defineStore('siteSettings', () => {
  // Raw flat settings object: { site_name: '...', site_logo: '...', ... }
  const settings = ref(JSON.parse(localStorage.getItem('site_settings') || 'null'))
  const loading  = ref(false)
  const loaded   = ref(!!settings.value)

  // ─── Computed getters ──────────────────────────────────────────
  const siteName    = computed(() => settings.value?.site_name    || 'SaaS App')
  const siteTagline = computed(() => settings.value?.site_tagline || 'Your Admin Dashboard')
  const siteLogo    = computed(() => settings.value?.site_logo    || '')
  const siteLogoDark= computed(() => settings.value?.site_logo_dark || '')
  const favicon     = computed(() => settings.value?.site_favicon  || '')
  const primaryColor= computed(() => settings.value?.primary_color || '#06b6d4')

  // Gunakan logo jika ada, fallback ke icon default
  const hasLogo = computed(() => !!siteLogo.value)

  // ─── Actions ──────────────────────────────────────────────────
  async function fetch() {
    if (loading.value) return
    loading.value = true
    try {
      // /api/site-settings adalah public endpoint, tidak butuh token
      const res = await api.get('/site-settings')
      settings.value = res.data.settings   // flat object { key: value }
      localStorage.setItem('site_settings', JSON.stringify(settings.value))
      loaded.value = true

      // Update document title & favicon dinamis
      if (settings.value.site_name) {
        document.title = settings.value.site_name
      }
      if (settings.value.site_favicon) {
        let link = document.querySelector("link[rel~='icon']")
        if (!link) {
          link = document.createElement('link')
          link.rel = 'icon'
          document.head.appendChild(link)
        }
        link.href = settings.value.site_favicon
      }
    } catch (err) {
      console.error('Failed to fetch site settings:', err)
    } finally {
      loading.value = false
    }
  }

  return {
    settings,
    loading,
    loaded,
    siteName,
    siteTagline,
    siteLogo,
    siteLogoDark,
    favicon,
    primaryColor,
    hasLogo,
    fetch,
  }
})
