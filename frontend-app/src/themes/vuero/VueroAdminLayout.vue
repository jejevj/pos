<template>
  <VueroLayout
    :links="navLinks"
    :page-title="pageTitle"
    :theme="vueroTheme"
    :show-sidebar="true"
    :open-on-mounted="true"
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

      <!-- User menu -->
      <button class="toolbar-btn user-btn" @click="toggleUserMenu">
        <span class="user-avatar">{{ userInitial }}</span>
        <span class="user-name">{{ authStore.user?.name }}</span>
        <i class="pi pi-angle-down" style="font-size:0.75rem;color:var(--light-text)" />
      </button>
      <Menu ref="userMenu" :model="userMenuItems" popup />
    </template>

    <!-- Sidebar nav links -->
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
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLanguage } from '@/composables/useLanguage'
import { useI18n } from 'vue-i18n'
import Menu from 'primevue/menu'
import VueroLayout from './layouts/VueroLayout.vue'
import { useSiteSettings } from '@/stores/siteSettings'
import VueroNavItem from './layouts/VueroNavItem.vue'

const router    = useRouter()
const site      = useSiteSettings()
const route     = useRoute()
const authStore = useAuthStore()
const { t }     = useI18n()
const { locale, availableLocales, changeLocale } = useLanguage()

// ─── State ──────────────────────────────────────────────────────
const vueroTheme   = ref('default') // default | color | curved | color-curved
const userMenu     = ref()
const languageMenu = ref()

// ─── Computed ────────────────────────────────────────────────────
const pageTitle = computed(() => route.meta.title || t('common.dashboard'))

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

const navLinks = computed(() => {
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

// ─── Methods ─────────────────────────────────────────────────────
const toggleUserMenu     = (e) => userMenu.value.toggle(e)
const toggleLanguageMenu = (e) => languageMenu.value.toggle(e)

// ─── Lifecycle ───────────────────────────────────────────────────
onMounted(async () => {
  if (authStore.menus.length === 0) {
    await authStore.fetchMenus()
  }
})
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

@media (max-width: 768px) {
  .user-name { display: none; }
}
</style>
