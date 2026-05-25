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

/** Tampilkan sidebar untuk superadmin dan outlet user */
const showSidebar = computed(() => authStore.isSuperAdmin || authStore.isOutletUser)

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

/** Nav links: superadmin pakai menu dari DB, outlet user pakai menu outlet statis */
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

  // Outlet user — bangun sidebar dari daftar menu grid outlet
  if (!authStore.isOutletUser || !outletId.value) return []

  const id = outletId.value
  const can = (perm) => authStore.hasOutletPermission(id, perm)
  // isOutletAdmin: superadmin, owner, atau punya semua permission
  const isAdmin = authStore.isSuperAdmin ||
    authStore.outletMemberships?.some(m =>
      String(m.encoded_outlet_id) === String(id) ||
      String(m.outlet_id) === String(id)
        ? (m.roles || []).some(r => r.is_owner || r.name === 'admin')
        : false
    )

  const p = (label, to, icon) => ({ type: 'link', label, to, icon })
  const items = []

  // ── Transaksi ─────────────────────────────────────────────────
  if (can('access_pos'))
    items.push(p('POS / Kasir', `/outlets/${id}/pos`, 'pi pi-shopping-cart'))
  if (can('view_transactions'))
    items.push(p('Transaksi', `/outlets/${id}/transactions`, 'pi pi-receipt'))
  if (can('manage_tables'))
    items.push(p('Meja', `/outlets/${id}/tables`, 'pi pi-table'))
  if (can('access_kitchen_display'))
    items.push(p('Kitchen Display', `/outlets/${id}/kitchen`, 'pi pi-bolt'))
  if (isAdmin)
    items.push(p('Stasiun KDS', `/outlets/${id}/stations`, 'pi pi-desktop'))

  // ── Inventori ─────────────────────────────────────────────────
  if (can('view_inventory')) {
    items.push({ type: 'divider' })
    items.push({
      type: 'collapse',
      label: 'Bahan Baku',
      icon: 'pi pi-box',
      children: [
        { label: 'Daftar Bahan Baku', to: `/outlets/${id}/bahan-baku`, icon: 'pi pi-list' },
        { label: 'Kategori', to: `/outlets/${id}/kategori-bahan-baku`, icon: 'pi pi-tags' },
        { label: 'Satuan', to: `/outlets/${id}/satuan`, icon: 'pi pi-arrows-h' },
        { label: 'Supplier', to: `/outlets/${id}/supplier`, icon: 'pi pi-building' },
        { label: 'Lokasi Stok', to: `/outlets/${id}/stock-locations`, icon: 'pi pi-map-marker' },
      ],
    })
    if (can('view_stock_opname'))
      items.push(p('Stock Opname', `/outlets/${id}/stock-opname`, 'pi pi-clipboard'))
    if (can('view_purchases'))
      items.push(p('Barang Masuk', `/outlets/${id}/purchases`, 'pi pi-truck'))
  }

  // ── Menu & Penjualan ──────────────────────────────────────────
  if (can('view_menu')) {
    items.push({ type: 'divider' })
    items.push({
      type: 'collapse',
      label: 'Menu',
      icon: 'pi pi-book',
      children: [
        { label: 'Daftar Menu', to: `/outlets/${id}/menu`, icon: 'pi pi-list' },
        { label: 'Kategori Menu', to: `/outlets/${id}/kategori-menu`, icon: 'pi pi-th-large' },
      ],
    })
  }
  if (can('view_promos'))
    items.push(p('Promo', `/outlets/${id}/promos`, 'pi pi-tag'))
  if (can('view_members'))
    items.push(p('Member', `/outlets/${id}/members`, 'pi pi-id-card'))

  // ── HR ────────────────────────────────────────────────────────
  if (can('view_employees') || can('view_attendance')) {
    items.push({ type: 'divider' })
    const hrChildren = []
    if (can('view_employees')) {
      hrChildren.push({ label: 'Data Karyawan', to: `/outlets/${id}/hr`, icon: 'pi pi-users' })
      hrChildren.push({ label: 'Shift', to: `/outlets/${id}/shifts`, icon: 'pi pi-calendar' })
      hrChildren.push({ label: 'Jatah Minum', to: `/outlets/${id}/employee-beverage`, icon: 'pi pi-star' })
    }
    if (can('view_attendance'))
      hrChildren.push({ label: 'Absensi', to: `/outlets/${id}/attendance`, icon: 'pi pi-clock' })
    items.push({ type: 'collapse', label: 'HR / Karyawan', icon: 'pi pi-users', children: hrChildren })
  }

  // ── Keuangan ─────────────────────────────────────────────────
  if (can('view_expenses') || can('view_reports')) {
    items.push({ type: 'divider' })
    if (can('view_expenses'))
      items.push(p('Pengeluaran', `/outlets/${id}/expenses`, 'pi pi-wallet'))
    if (can('view_reports')) {
      items.push({
        type: 'collapse',
        label: 'Laporan',
        icon: 'pi pi-chart-bar',
        children: [
          { label: 'Laporan Penjualan', to: `/outlets/${id}/reports?tab=0`, icon: 'pi pi-chart-line' },
          { label: 'Laporan Menu', to: `/outlets/${id}/reports?tab=1`, icon: 'pi pi-book' },
          { label: 'Laporan Cuaca', to: `/outlets/${id}/reports?tab=2`, icon: 'pi pi-cloud' },
          { label: 'Laporan Inventori', to: `/outlets/${id}/reports?tab=3`, icon: 'pi pi-box' },
          { label: 'Laporan Pengeluaran', to: `/outlets/${id}/reports?tab=4`, icon: 'pi pi-wallet' },
        ],
      })
    }
  }

  // ── Pengaturan ────────────────────────────────────────────────
  items.push({ type: 'divider' })
  if (can('manage_users'))
    items.push(p('Manajemen User', `/outlets/${id}/users`, 'pi pi-user-edit'))
  if (isAdmin)
    items.push(p('Metode Pembayaran', `/outlets/${id}/payment-methods`, 'pi pi-credit-card'))
  if (isAdmin)
    items.push(p('WhatsApp', `/outlets/${id}/whatsapp`, 'pi pi-whatsapp'))
  if (can('edit_settings'))
    items.push(p('Utilitas', `/outlets/${id}/utilities`, 'pi pi-wrench'))
  if (can('manage_roles'))
    items.push(p('Pengaturan RBAC', `/outlets/${id}/rbac`, 'pi pi-shield'))

  return items
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
