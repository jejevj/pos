<template>
  <VueroLayout
    :links="navLinks"
    :page-title="pageTitle"
    :theme="vueroTheme"
    :is-dark="isDark"
    :show-sidebar="showSidebar"
    :open-on-mounted="showSidebar"
  >
    <template #logo>
      <i class="pi pi-box logo-icon"></i>
      <span class="brand-name">SaaS App</span>
    </template>

    <template #toolbar>
      <button class="toolbar-btn" @click="toggleDark" :title="isDark ? 'Light mode' : 'Dark mode'">
        <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'"></i>
      </button>
      <button class="toolbar-btn" @click="toggleLanguageMenu" :title="currentLanguage.flag">
        {{ currentLanguage.flag }}
      </button>
      <Menu ref="languageMenu" :model="languageMenuItems" popup />
      <button class="toolbar-btn user-btn" @click="toggleUserMenu">
        <i class="pi pi-user"></i>
        <span>{{ authStore.user?.name }}</span>
      </button>
      <Menu ref="userMenu" :model="userMenuItems" popup />
    </template>

    <template #links-bottom>
      <button class="toolbar-btn" @click="toggleDark">
        <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'"></i>
      </button>
    </template>

    <!-- Main content -->
    <router-view />
  </VueroLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLanguage } from '@/composables/useLanguage'
import { useWahaSocket } from '@/composables/useWahaSocket'
import { clearWahaUnread } from '@/composables/useWahaSocket'
import { useI18n } from 'vue-i18n'
import Menu from 'primevue/menu'
import VueroLayout from './layouts/VueroLayout.vue'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()
const { t }     = useI18n()
const { locale, availableLocales, changeLocale } = useLanguage()

const isDark      = ref(false)
const vueroTheme  = ref('default') // default | color | curved | color-curved
const userMenu    = ref()
const languageMenu = ref()

const { unreadCount } = useWahaSocket()
const outletId = computed(() => route.params.outletId)

const pageTitle = computed(() => route.meta.title || t('common.dashboard'))

// Sidebar hanya untuk superadmin — outlet user tidak boleh ada sidebar
const showSidebar = computed(() => authStore.isSuperAdmin)

const currentLanguage = computed(() =>
  availableLocales.find(l => l.code === locale.value) || availableLocales[0]
)

// Build nav links hanya untuk superadmin
const navLinks = computed(() => {
  if (!authStore.isSuperAdmin) return []
  return (authStore.menus || []).map(menu => {
    if (menu.children?.length) {
      return {
        id:       menu.id,
        type:     'collapse',
        label:    menu.title,
        icon:     menu.icon,
        children: menu.children.map(c => ({ label: c.title, to: c.url, icon: c.icon })),
      }
    }
    return {
      id:    menu.id,
      type:  'link',
      label: menu.title,
      icon:  menu.icon,
      to:    menu.url,
    }
  })
})

const toggleDark = () => { isDark.value = !isDark.value }

const languageMenuItems = computed(() =>
  availableLocales.map(lang => ({
    label:   lang.name,
    icon:    lang.code === locale.value ? 'pi pi-check' : '',
    command: () => changeLocale(lang.code),
  }))
)

const userMenuItems = computed(() => [
  { label: t('common.profile'), icon: 'pi pi-user' },
  { separator: true },
  {
    label:   t('common.logout'),
    icon:    'pi pi-sign-out',
    command: async () => { await authStore.logout(); router.push({ name: 'login' }) },
  },
])

const toggleUserMenu     = (e) => userMenu.value.toggle(e)
const toggleLanguageMenu = (e) => languageMenu.value.toggle(e)

// WA floating button
const goToWhatsApp = () => {
  clearWahaUnread()
  router.push({ name: 'outlet-whatsapp', params: { outletId: outletId.value } })
}

onMounted(async () => {
  // Hanya fetch menus untuk superadmin
  if (authStore.isSuperAdmin && authStore.menus.length === 0) {
    await authStore.fetchMenus()
  }
})
</script>

<style scoped>
.logo-icon { font-size: 1.5rem; color: var(--primary, #3b82f6); margin-right: 0.5rem; }
.brand-name { font-weight: 700; font-size: 1.1rem; }

.toolbar-btn {
  background: none; border: none; cursor: pointer;
  height: 36px; padding: 0 0.5rem; border-radius: 8px;
  display: flex; align-items: center; gap: 0.4rem;
  color: var(--light-text, #6b7280);
  font-size: 0.9rem;
  transition: background 0.2s;
}
.toolbar-btn:hover { background: var(--widget-grey, #f3f4f6); color: var(--dark-text, #1f2937); }
.user-btn { font-weight: 600; }
</style>
