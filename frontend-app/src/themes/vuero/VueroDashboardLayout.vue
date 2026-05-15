<template>
  <VueroLayout
    :links="navLinks"
    :page-title="pageTitle"
    :theme="vueroTheme"
    :show-sidebar="showSidebar"
    :open-on-mounted="showSidebar"
    :is-dark="themeStore.isDark"
  >
    <!-- Logo slot -->
    <template #logo>
      <img v-if="site.hasLogo" :src="site.siteLogo" :alt="site.siteName" class="brand-logo-img" />
      <template v-else>
        <i class="pi pi-box logo-icon" />
        <span class="brand-name">{{ site.siteName }}</span>
      </template>
    </template>

    <!-- Toolbar slot (top-right header) -->
    <template #toolbar>
      <!-- Language switcher -->
      <button class="toolbar-btn" @click="toggleLanguageMenu" :title="currentLanguage.flag">
        {{ currentLanguage.flag }}
      </button>
      <Menu ref="languageMenu" :model="languageMenuItems" popup />

      <!-- Dark mode toggle -->
      <button class="toolbar-btn" @click="themeStore.toggleDark" :title="themeStore.isDark ? 'Light Mode' : 'Dark Mode'">
        <i :class="themeStore.isDark ? 'pi pi-sun' : 'pi pi-moon'" />
      </button>

      <!-- WA badge (outlet only) -->
      <button
        v-if="outletId && unreadCount > 0"
        class="toolbar-btn wa-btn"
        @click="goToWhatsApp"
        title="WhatsApp"
      >
        <i class="pi pi-whatsapp" />
        <span class="wa-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
      </button>

      <!-- User menu -->
      <button class="toolbar-btn user-btn" @click="toggleUserMenu">
        <span class="user-avatar">{{ userInitial }}</span>
        <span class="user-name">{{ authStore.user?.name }}</span>
        <i class="pi pi-angle-down" style="font-size:0.75rem;color:var(--light-text)" />
      </button>
      <Menu ref="userMenu" :model="userMenuItems" popup />
    </template>

    <!-- Sidebar nav links (superadmin only) -->
    <template #links>
      <VueroNavItem v-for="item in navLinks" :key="item.id ?? item.to" :item="item" />
    </template>

    <!-- Sidebar bottom slot -->
    <template #links-bottom>
      <!-- User profile pill in sidebar footer -->
      <div class="sidebar-user">
        <div class="user-avatar-sm">{{ userInitial }}</div>
        <div class="user-meta">
          <span class="user-meta-name">{{ authStore.user?.name }}</span>
          <span class="user-meta-role">{{ userRole }}</span>
        </div>
      </div>
    </template>

    <!-- Page content -->
    <router-view />
  </VueroLayout>

  <!-- WhatsApp FAB (outlet context) -->
  <div v-if="outletId" class="wa-fab-wrap">
    <button class="wa-fab" @click="goToWhatsApp" title="WhatsApp">
      <i class="pi pi-whatsapp" />
      <span v-if="unreadCount > 0" class="wa-fab-badge">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLanguage } from '@/composables/useLanguage'
import { useWahaSocket, clearWahaUnread } from '@/composables/useWahaSocket'
import { useI18n } from 'vue-i18n'
import Menu from 'primevue/menu'
import VueroLayout from './layouts/VueroLayout.vue'
import { useThemeStore } from '@/stores/theme'
import { useSiteSettings } from '@/stores/siteSettings'
import VueroNavItem from './layouts/VueroNavItem.vue'

const router    = useRouter()
const themeStore = useThemeStore()
const site      = useSiteSettings()
const route     = useRoute()
const authStore = useAuthStore()
const { t }     = useI18n()
const { locale, availableLocales, changeLocale } = useLanguage()

// ─── State ──────────────────────────────────────────────────────
const vueroTheme   = ref('default') // default | color | curved | color-curved
const userMenu     = ref()
const languageMenu = ref()

const { unreadCount } = useWahaSocket()
const outletId = computed(() => route.params.outletId)

// ─── Computed ────────────────────────────────────────────────────
const pageTitle = computed(() => route.meta.title || t('common.dashboard'))

/** Superadmin tetap tampilkan sidebar saat masuk halaman outlet */
const showSidebar = computed(() => authStore.isSuperAdmin)

const userInitial = computed(() => {
  const name = authStore.user?.name || ''
  return name.charAt(0).toUpperCase()
})

const userRole = computed(() => {
  if (authStore.isSuperAdmin) return 'Super Admin'
  const membership = authStore.outletMemberships?.[0]
  return membership?.roles?.[0]?.display_name || 'Staff'
})

const currentLanguage = computed(() =>
  availableLocales.find(l => l.code === locale.value) || availableLocales[0]
)

/** Superadmin tetap dapat nav links global saat di halaman outlet */
const navLinks = computed(() => {
  if (authStore.isSuperAdmin) {
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
      return { id: menu.id, type: 'link', label: menu.title, icon: menu.icon, to: menu.url }
    })
  }
  return []
})

const languageMenuItems = computed(() =>
  availableLocales.map(lang => ({
    label:   lang.name,
    icon:    lang.code === locale.value ? 'pi pi-check' : '',
    command: () => changeLocale(lang.code),
  }))
)

const userMenuItems = computed(() => [
  {
    label: t('common.profile'),
    icon:  'pi pi-user',
    command: () => {},
  },
  { separator: true },
  {
    label:   t('common.logout'),
    icon:    'pi pi-sign-out',
    command: async () => {
      await authStore.logout()
      router.push({ name: 'login' })
    },
  },
])

// ─── Lifecycle ───────────────────────────────────────────────────
onMounted(async () => {
  // Jika superadmin belum punya menus (misal langsung buka URL outlet), fetch sekarang
  if (authStore.isSuperAdmin && authStore.menus.length === 0) {
    await authStore.fetchMenus()
  }
})

// ─── Methods ─────────────────────────────────────────────────────
const toggleUserMenu     = (e) => userMenu.value.toggle(e)
const toggleLanguageMenu = (e) => languageMenu.value.toggle(e)

const goToWhatsApp = () => {
  clearWahaUnread()
  router.push({ name: 'outlet-whatsapp', params: { outletId: outletId.value } })
}

</script>

<style scoped>
/* Logo */
.logo-icon {
  font-size: 1.4rem;
  color: var(--primary, #41b3a3);
}
.brand-name {
  font-family: var(--font-alt, 'Montserrat Variable', sans-serif);
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--dark-text, #283252);
}

/* Toolbar buttons */
.toolbar-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  height: 36px;
  padding: 0 0.6rem;
  border: none;
  border-radius: var(--radius-rounded, 9999px);
  background: none;
  cursor: pointer;
  color: var(--light-text, #a2a5b9);
  font-size: 0.875rem;
  transition: background 0.2s, color 0.2s;
}
.toolbar-btn:hover {
  background: var(--widget-grey, #f5f6fa);
  color: var(--dark-text, #283252);
}

/* User button */
.user-btn {
  padding: 0 0.75rem;
  gap: 0.5rem;
}
.user-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary, #41b3a3);
  color: #fff;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}
.user-name {
  font-weight: 600;
  color: var(--dark-text, #283252);
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* WA button */
.wa-btn {
  position: relative;
  color: #25d366;
}
.wa-badge {
  position: absolute;
  top: 0; right: 0;
  min-width: 16px; height: 16px;
  padding: 0 3px;
  border-radius: 9999px;
  background: #ef4444;
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid #fff;
}

/* Sidebar footer user pill */
.sidebar-user {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  overflow: hidden;
}
.user-avatar-sm {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 50%;
  background: var(--primary, #41b3a3);
  color: #fff;
  font-weight: 700;
  font-size: 0.8rem;
  flex-shrink: 0;
}
.user-meta {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.user-meta-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--dark-text, #283252);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-meta-role {
  font-size: 0.7rem;
  color: var(--light-text, #a2a5b9);
}

/* WhatsApp FAB */
.wa-fab-wrap {
  position: fixed;
  bottom: 1.5rem;
  left: 1.5rem;
  z-index: 200;
}
.wa-fab {
  position: relative;
  width: 50px; height: 50px;
  border-radius: 50%;
  background: #25d366;
  border: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(37,211,102,.4);
  transition: transform 0.15s, box-shadow 0.15s;
}
.wa-fab:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 18px rgba(37,211,102,.55);
}
.wa-fab-badge {
  position: absolute;
  top: -3px; right: -3px;
  min-width: 18px; height: 18px;
  padding: 0 4px;
  border-radius: 9999px;
  background: #ef4444;
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid #fff;
}

@media (max-width: 768px) {
  .user-name { display: none; }
}
</style>
