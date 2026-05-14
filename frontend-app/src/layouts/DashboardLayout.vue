<template>
  <!-- Theme switcher: renders Vuero layout or default layout -->
  <VueroDashboardLayout v-if="themeStore.activeTheme === 'vuero'" />
  <div v-else class="dashboard-layout">
    <!-- Sidebar (Fixed) -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <i class="pi pi-box logo-icon"></i>
        <span class="logo-text">SaaS App</span>
      </div>

      <nav class="sidebar-nav">
        <Skeleton v-if="menusLoading" height="3rem" class="mb-2" />
        <Skeleton v-if="menusLoading" height="3rem" class="mb-2" />
        <Skeleton v-if="menusLoading" height="3rem" class="mb-2" />

        <template v-else>
          <template v-for="menu in menus" :key="menu.id">
            <!-- Menu without children -->
            <router-link
              v-if="!menu.children || menu.children.length === 0"
              :to="menu.url"
              class="menu-item"
              active-class="menu-item-active"
            >
              <i :class="menu.icon" class="menu-icon"></i>
              <span class="menu-label">{{ menu.title }}</span>
            </router-link>

            <!-- Menu with children -->
            <div v-else class="menu-group">
              <button
                @click="toggleSubmenu(menu.id)"
                class="menu-item menu-item-parent"
                :class="{ 'menu-item-expanded': expandedMenus.includes(menu.id) }"
              >
                <i :class="menu.icon" class="menu-icon"></i>
                <span class="menu-label">{{ menu.title }}</span>
                <i
                  class="pi pi-chevron-down submenu-arrow"
                  :class="{ 'submenu-arrow-expanded': expandedMenus.includes(menu.id) }"
                ></i>
              </button>

              <div
                v-show="expandedMenus.includes(menu.id)"
                class="submenu"
              >
                <router-link
                  v-for="child in menu.children"
                  :key="child.id"
                  :to="child.url"
                  class="submenu-item"
                  active-class="submenu-item-active"
                >
                  <i :class="child.icon" class="submenu-icon"></i>
                  <span class="submenu-label">{{ child.title }}</span>
                </router-link>
              </div>
            </div>
          </template>
        </template>
      </nav>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
      <!-- Header (Sticky) -->
      <header class="header">
        <div class="header-left">
          <h2 class="page-title">{{ pageTitle }}</h2>
        </div>
        <div class="header-right">
          <ThemeSwitcher />
          <Button
            icon="pi pi-bell"
            text
            rounded
            severity="secondary"
            class="header-button"
          />
          <Button
            :label="currentLanguage.flag"
            text
            rounded
            @click="toggleLanguageMenu"
            class="header-button"
            v-tooltip.bottom="'Change Language'"
          />
          <Menu ref="languageMenu" :model="languageMenuItems" popup />
          <Button
            :label="authStore.user?.name"
            icon="pi pi-user"
            text
            @click="toggleUserMenu"
            class="header-button user-button"
          />
          <Menu ref="userMenu" :model="userMenuItems" popup />
        </div>
      </header>

      <!-- Content Area -->
      <main class="content">
        <router-view />
      </main>
    </div>

    <!-- WhatsApp Floating Button -->
    <div v-if="outletId" class="wa-fab-wrap">
      <button class="wa-fab" @click="goToWhatsApp" :title="'WhatsApp'">
        <i class="pi pi-whatsapp"></i>
        <span v-if="unreadCount > 0" class="wa-fab-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
      </button>
    </div>
  </div>
  <!-- /v-else default layout -->
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useIdleTimeout } from '@/composables/useIdleTimeout'
import { useLanguage } from '@/composables/useLanguage'
import { useWahaSocket } from '@/composables/useWahaSocket'
import { clearWahaUnread } from '@/composables/useWahaSocket'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Skeleton from 'primevue/skeleton'
import { useThemeStore } from '@/stores/theme'
import VueroDashboardLayout from '@/themes/vuero/VueroDashboardLayout.vue'
import ThemeSwitcher from '@/themes/vuero/ThemeSwitcher.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { locale, availableLocales, changeLocale } = useLanguage()
const themeStore = useThemeStore()

// Setup idle timeout (1 minute)
useIdleTimeout(30)

// WAHA real-time incoming message notifications
const { unreadCount } = useWahaSocket()

const outletId = computed(() => route.params.outletId)

const goToWhatsApp = () => {
  clearWahaUnread()
  router.push({ name: 'outlet-whatsapp', params: { outletId: outletId.value } })
}

const userMenu = ref()
const languageMenu = ref()
const expandedMenus = ref([])
const menusLoading = ref(true)

const menus = computed(() => authStore.menus)

const pageTitle = computed(() => {
  return route.meta.title || t('common.dashboard')
})

const currentLanguage = computed(() => {
  return availableLocales.find(l => l.code === locale.value) || availableLocales[0]
})

const languageMenuItems = computed(() => {
  return availableLocales.map(lang => ({
    label: lang.name,
    icon: lang.code === locale.value ? 'pi pi-check' : '',
    command: () => {
      changeLocale(lang.code)
    }
  }))
})

const userMenuItems = computed(() => [
  {
    label: t('common.profile'),
    icon: 'pi pi-user',
    command: () => {
      console.log('Profile clicked')
    }
  },
  {
    label: t('common.settings'),
    icon: 'pi pi-cog',
    command: () => {
      router.push({ name: 'settings' })
    }
  },
  {
    separator: true
  },
  {
    label: t('common.logout'),
    icon: 'pi pi-sign-out',
    command: async () => {
      await authStore.logout()
      router.push({ name: 'login' })
    }
  }
])

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}

const toggleLanguageMenu = (event) => {
  languageMenu.value.toggle(event)
}

const toggleSubmenu = (menuId) => {
  const index = expandedMenus.value.indexOf(menuId)
  if (index > -1) {
    expandedMenus.value.splice(index, 1)
  } else {
    expandedMenus.value.push(menuId)
  }
}

onMounted(async () => {
  menusLoading.value = true
  if (authStore.menus.length === 0) {
    await authStore.fetchMenus()
  }
  menusLoading.value = false
})
</script>

<style scoped>
.dashboard-layout {
  display: flex;
  min-height: 100vh;
  background: #f8f9fa;
}

/* Sidebar (Fixed) */
.sidebar {
  width: 260px;
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 100;
}

.sidebar-header {
  padding: 1.5rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.logo-icon {
  font-size: 1.75rem;
  color: var(--sage-primary);
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0.75rem;
}

/* Menu Items */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.25rem;
  border-radius: 8px;
  color: #4b5563;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
  border: none;
  background: transparent;
  width: 100%;
  font-size: 0.95rem;
}

.menu-item:hover {
  background: var(--sage-bg);
  color: var(--sage-primary);
}

.menu-item-active {
  background: var(--sage-bg);
  color: var(--sage-primary);
  font-weight: 600;
}

.menu-icon {
  font-size: 1.1rem;
  width: 1.25rem;
  text-align: center;
}

.menu-label {
  flex: 1;
}

/* Menu with children */
.menu-group {
  margin-bottom: 0.25rem;
}

.menu-item-parent {
  position: relative;
}

.submenu-arrow {
  font-size: 0.75rem;
  transition: transform 0.2s;
}

.submenu-arrow-expanded {
  transform: rotate(180deg);
}

.submenu {
  padding-left: 1rem;
  margin-top: 0.25rem;
}

.submenu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 1rem;
  margin-bottom: 0.25rem;
  border-radius: 8px;
  color: #6b7280;
  text-decoration: none;
  transition: all 0.2s;
  font-size: 0.9rem;
}

.submenu-item:hover {
  background: var(--sage-bg);
  color: var(--sage-primary);
}

.submenu-item-active {
  background: var(--sage-bg);
  color: var(--sage-primary);
  font-weight: 600;
}

.submenu-icon {
  font-size: 0.95rem;
  width: 1.25rem;
  text-align: center;
}

/* Main Wrapper (Beside Sidebar) */
.main-wrapper {
  flex: 1;
  margin-left: 260px;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* Header (Sticky) */
.header {
  position: sticky;
  top: 0;
  z-index: 50;
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 70px;
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.header-button {
  color: #6b7280;
}

.header-button:hover {
  color: var(--sage-primary);
  background: var(--sage-bg);
}

.user-button {
  font-weight: 600;
}

/* Content Area (Scrollable) */
.content {
  flex: 1;
  padding: 2rem;
  overflow-y: auto;
}

/* Scrollbar styling */
.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

/* WhatsApp Floating Button */
.wa-fab-wrap {
  position: fixed;
  bottom: 1.5rem;
  left: 1.5rem;
  z-index: 100;
}

.wa-fab {
  position: relative;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #25d366;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(37, 211, 102, 0.45);
  transition: transform 0.15s, box-shadow 0.15s;
  color: white;
}

.wa-fab:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 18px rgba(37, 211, 102, 0.55);
}

.wa-fab i {
  font-size: 1.5rem;
}

.wa-fab-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  border-radius: 10px;
  background: #ef4444;
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  animation: badge-pop 0.2s ease;
}

@keyframes badge-pop {
  0%   { transform: scale(0); }
  70%  { transform: scale(1.2); }
  100% { transform: scale(1); }
}

/* Responsive */
@media (max-width: 768px) {
  .sidebar {
    width: 70px;
  }

  .main-wrapper {
    margin-left: 70px;
  }

  .logo-text,
  .menu-label,
  .submenu-label {
    display: none;
  }

  .sidebar-header {
    justify-content: center;
  }

  .menu-item,
  .submenu-item {
    justify-content: center;
    padding: 0.75rem;
  }

  .submenu-arrow {
    display: none;
  }

  .header {
    padding: 1rem;
  }

  .page-title {
    font-size: 1.25rem;
  }

  .content {
    padding: 1rem;
  }
}
</style>
