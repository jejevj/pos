<template>
  <div class="sidebar-layout" :class="{ 'is-dark': isDark }">
    <!-- Mobile navbar -->
    <div class="mobile-navbar navbar-faded">
      <div class="navbar-brand">
        <button v-if="showSidebar" class="menu-toggle" @click="isMobileOpen = !isMobileOpen">
          <i class="pi pi-bars"></i>
        </button>
        <slot name="logo">
          <span class="brand-name">App</span>
        </slot>
        <div class="brand-end">
          <slot name="toolbar-mobile" />
        </div>
      </div>
    </div>

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div v-if="isMobileOpen" class="mobile-overlay" @click="isMobileOpen = false" />
    </Transition>

    <!-- Sideblock (desktop + mobile) — hanya tampil jika showSidebar true -->
    <Transition name="slide-x">
      <div
        v-if="showSidebar && (isMobileOpen || isDesktopOpen)"
        class="sidebar-block"
        :class="[themeClass, isMobileOpen ? 'is-mobile' : '']"
      >
        <div class="sidebar-block-header">
          <slot name="logo">
            <span class="brand-name">App</span>
          </slot>
          <button class="sidebar-close" @click="isDesktopOpen = false">
            <i class="pi pi-times"></i>
          </button>
        </div>

        <div class="sidebar-block-inner">
          <ul>
            <slot name="links">
              <VueroNavItem
                v-for="item in links"
                :key="item.id || item.label"
                :item="item"
              />
            </slot>
          </ul>
        </div>

        <div class="sidebar-block-footer">
          <slot name="links-bottom" />
        </div>
      </div>
    </Transition>

    <!-- Main content -->
    <div
      class="view-wrapper view-wrapper-full"
      :class="{ 'is-pushed-block': showSidebar && isDesktopOpen }"
    >
      <!-- Page heading -->
      <div class="page-heading">
        <button v-if="showSidebar" class="sidebar-toggle" @click="isDesktopOpen = !isDesktopOpen">
          <i class="pi pi-bars"></i>
        </button>
        <h1 class="page-title">{{ pageTitle }}</h1>
        <div class="toolbar">
          <slot name="toolbar" />
        </div>
      </div>

      <!-- Page content -->
      <div class="page-content-wrapper">
        <div class="page-content is-relative">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, provide } from 'vue'
import { useRoute } from 'vue-router'
import VueroNavItem from './VueroNavItem.vue'

const props = defineProps({
  links:         { type: Array,   default: () => [] },
  theme:         { type: String,  default: 'default' }, // default | color | curved | color-curved
  openOnMounted: { type: Boolean, default: true },
  closeOnChange: { type: Boolean, default: false },
  pageTitle:     { type: String,  default: '' },
  isDark:        { type: Boolean, default: false },
  showSidebar:   { type: Boolean, default: true },  // false = sembunyikan sidebar sepenuhnya
})

const route = useRoute()

const isMobileOpen  = ref(false)
const isDesktopOpen = ref(props.showSidebar && props.openOnMounted)

const themeClass = computed(() => {
  const map = {
    color:        'is-colored',
    curved:       'is-curved',
    'color-curved': 'is-colored is-curved',
  }
  return map[props.theme] || ''
})

watch(() => route.fullPath, () => {
  isMobileOpen.value = false
  if (props.closeOnChange) isDesktopOpen.value = false
})

// Ketika showSidebar berubah (misalnya auth state baru resolve), tutup sidebar
watch(() => props.showSidebar, (val) => {
  if (!val) {
    isMobileOpen.value = false
    isDesktopOpen.value = false
  }
})

provide('vuero-layout', { isDesktopOpen, isMobileOpen, links: computed(() => props.links) })

defineExpose({ isDesktopOpen, isMobileOpen })
</script>

<style scoped>
/* Mobile navbar */
.mobile-navbar {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--white, #fff);
  border-bottom: 1px solid var(--border, #e5e7eb);
  z-index: 40;
  padding: 0 1rem;
}
.navbar-brand {
  display: flex;
  align-items: center;
  height: 100%;
  gap: 0.75rem;
}
.brand-end { margin-left: auto; display: flex; align-items: center; gap: 0.5rem; }
.brand-name { font-weight: 700; font-size: 1.1rem; }
.menu-toggle, .sidebar-close, .sidebar-toggle {
  background: none; border: none; cursor: pointer;
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: var(--light-text, #6b7280);
  transition: background 0.2s;
}
.menu-toggle:hover, .sidebar-close:hover, .sidebar-toggle:hover {
  background: var(--widget-grey, #f3f4f6);
}

/* Mobile overlay */
.mobile-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4);
  z-index: 34;
}

/* Sidebar block */
.sidebar-block {
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  width: 280px;
  background: var(--white, #fff);
  z-index: 35;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border, #e5e7eb);
  transition: transform 0.3s;
}
.sidebar-block.is-curved {
  border-top-right-radius: 2rem;
  border-bottom-right-radius: 2rem;
}
.sidebar-block-header {
  display: flex;
  align-items: center;
  height: 60px;
  padding: 0 1.5rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
  flex-shrink: 0;
}
.sidebar-close { margin-left: auto; }
.sidebar-block-inner {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0.5rem 0;
}
.sidebar-block-inner::-webkit-scrollbar { width: 3px; }
.sidebar-block-inner::-webkit-scrollbar-thumb { border-radius: 5px; background: rgba(0,0,0,0.15); }
.sidebar-block-inner ul { list-style: none; padding: 0; margin: 0; }
.sidebar-block-footer {
  height: 60px;
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  border-top: 1px solid var(--border, #e5e7eb);
  flex-shrink: 0;
}

/* View wrapper */
.view-wrapper-full {
  width: 100%;
  min-height: 100vh;
  background: var(--background-grey, #f5f5f5);
  transition: margin-left 0.3s, width 0.3s;
}
.view-wrapper-full.is-pushed-block {
  margin-left: 280px;
  width: calc(100% - 280px);
}

/* Page heading */
.page-heading {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0 1.5rem;
  height: 60px;
  background: var(--white, #fff);
  border-bottom: 1px solid var(--border, #e5e7eb);
  position: sticky;
  top: 0;
  z-index: 20;
}
.page-title { margin: 0; font-size: 1.25rem; font-weight: 700; flex: 1; }
.toolbar { display: flex; align-items: center; gap: 0.5rem; }

/* Page content */
.page-content-wrapper { padding: 1.5rem; }
.page-content { background: transparent; }

/* Colored theme */
.sidebar-block.is-colored {
  background: #1a1a2e;
  border-color: #1a1a2e;
}

/* Transitions */
.slide-x-enter-active, .slide-x-leave-active { transition: transform 0.3s ease; }
.slide-x-enter-from, .slide-x-leave-to { transform: translateX(-100%); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Responsive */
@media (max-width: 768px) {
  .mobile-navbar { display: flex; }
  .sidebar-toggle { display: none; }
  .view-wrapper-full { margin-top: 60px; }
  .view-wrapper-full.is-pushed-block { margin-left: 0; width: 100%; }
  .sidebar-block:not(.is-mobile) { display: none; }
  .sidebar-block.is-mobile { z-index: 36; }
}
@media (min-width: 769px) {
  .mobile-navbar { display: none; }
}
</style>
