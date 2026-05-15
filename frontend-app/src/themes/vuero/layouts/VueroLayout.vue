<template>
  <div class="vuero-app" :class="{ 'is-dark': isDark }">

    <!-- ─── Mobile navbar ─── -->
    <nav class="mobile-navbar">
      <div class="navbar-brand">
        <button
          v-if="showSidebar"
          class="vuero-hamburger"
          aria-label="Toggle menu"
          @click="isMobileOpen = !isMobileOpen"
        >
          <span class="menu-toggle" :class="{ active: isMobileOpen }">
            <span class="icon-box-toggle">
              <span class="rotate">
                <i class="icon-line-top" />
                <i class="icon-line-center" />
                <i class="icon-line-bottom" />
              </span>
            </span>
          </span>
        </button>
        <slot name="logo">
          <span class="brand-name">App</span>
        </slot>
        <div class="brand-end">
          <slot name="toolbar-mobile" />
        </div>
      </div>
    </nav>

    <!-- ─── Mobile overlay ─── -->
    <Transition name="fade">
      <div
        v-if="isMobileOpen"
        class="app-overlay is-active"
        @click="isMobileOpen = false"
      />
    </Transition>

    <!-- ─── Mobile sideblock ─── -->
    <Transition name="slide-x">
      <div
        v-if="showSidebar && isMobileOpen"
        class="sidebar-block is-mobile"
        :class="themeClass"
      >
        <div class="sidebar-block-header">
          <slot name="logo" />
          <button class="sidebar-block-close" @click="isMobileOpen = false">
            <i class="pi pi-times" />
          </button>
        </div>
        <div class="sidebar-block-inner">
          <ul>
            <slot name="links" />
          </ul>
        </div>
        <div class="sidebar-block-footer">
          <slot name="links-bottom" />
        </div>
      </div>
    </Transition>

    <!-- ─── Desktop sideblock ─── -->
    <Transition name="slide-x">
      <div
        v-if="showSidebar && isDesktopOpen"
        class="sidebar-block"
        :class="themeClass"
      >
        <div class="sidebar-block-header">
          <slot name="logo" />
        </div>
        <div class="sidebar-block-inner">
          <ul>
            <slot name="links" />
          </ul>
        </div>
        <div class="sidebar-block-footer">
          <slot name="links-bottom" />
        </div>
      </div>
    </Transition>

    <!-- ─── Main view wrapper ─── -->
    <div
      class="view-wrapper"
      :class="{ 'is-pushed-block': showSidebar && isDesktopOpen }"
    >
      <!-- Sticky page title / toolbar -->
      <div class="page-title">
        <div
          v-if="showSidebar"
          class="vuero-hamburger nav-trigger push-resize"
          role="button"
          tabindex="0"
          @click="isDesktopOpen = !isDesktopOpen"
          @keydown.enter.prevent="isDesktopOpen = !isDesktopOpen"
        >
          <span class="menu-toggle has-chevron">
            <span class="icon-box-toggle" :class="{ active: isDesktopOpen }">
              <span class="rotate">
                <i class="icon-line-top" />
                <i class="icon-line-center" />
                <i class="icon-line-bottom" />
              </span>
            </span>
          </span>
        </div>

        <div class="title-wrap">
          <h1 class="title is-5">{{ pageTitle }}</h1>
        </div>

        <div class="toolbar desktop-toolbar">
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

const props = defineProps({
  links:         { type: Array,   default: () => [] },
  theme:         { type: String,  default: 'default' },
  openOnMounted: { type: Boolean, default: true },
  closeOnChange: { type: Boolean, default: false },
  pageTitle:     { type: String,  default: '' },
  isDark:        { type: Boolean, default: false },
  showSidebar:   { type: Boolean, default: true },
})

const route = useRoute()

const isMobileOpen  = ref(false)
const isDesktopOpen = ref(props.showSidebar && props.openOnMounted)

const themeClass = computed(() => {
  const map = {
    color:          'is-colored',
    curved:         'is-curved',
    'color-curved': 'is-colored is-curved',
  }
  return map[props.theme] || ''
})

watch(() => route.fullPath, () => {
  isMobileOpen.value = false
  if (props.closeOnChange) isDesktopOpen.value = false
})

watch(() => props.showSidebar, (val) => {
  if (!val) {
    isMobileOpen.value  = false
    isDesktopOpen.value = false
  }
})

provide('vuero-layout', {
  isDesktopOpen,
  isMobileOpen,
  links: computed(() => props.links),
})

defineExpose({ isDesktopOpen, isMobileOpen })
</script>

<style scoped>
/* ─────────────────────────────────────────────────────────────────
   Vuero design tokens (local — mirrors CSS vars in main.css)
───────────────────────────────────────────────────────────────── */
.vuero-app {
  min-height: 100vh;
  background: var(--background-grey, #fafafa);
  font-family: var(--font, 'Roboto Flex Variable', sans-serif);
}

/* ─────────────────────────────────────────────────────────────────
   Mobile navbar
───────────────────────────────────────────────────────────────── */
.mobile-navbar {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0;
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
.brand-end {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ─────────────────────────────────────────────────────────────────
   Vuero hamburger (matches vuero-demo animation)
───────────────────────────────────────────────────────────────── */
.vuero-hamburger {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: var(--radius-rounded, 9999px);
  border: none;
  background: none;
  cursor: pointer;
  transition: background 0.2s;
  flex-shrink: 0;
}
.vuero-hamburger:hover {
  background: var(--widget-grey, #f5f6fa);
}
.vuero-hamburger:focus {
  outline: 2px solid var(--primary, #41b3a3);
  outline-offset: 2px;
}

.menu-toggle {
  position: relative;
  display: inline-block;
  width: 22px;
  height: 22px;
}
.icon-box-toggle {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  width: 22px;
  height: 22px;
  gap: 4px;
}
.icon-line-top,
.icon-line-center,
.icon-line-bottom {
  display: block;
  width: 20px;
  height: 2px;
  background: var(--light-text, #a2a5b9);
  border-radius: 2px;
  transition: transform 0.3s, opacity 0.3s, width 0.3s;
}
.icon-box-toggle.active .icon-line-top {
  transform: translateY(6px) rotate(45deg);
}
.icon-box-toggle.active .icon-line-center {
  opacity: 0;
  width: 0;
}
.icon-box-toggle.active .icon-line-bottom {
  transform: translateY(-6px) rotate(-45deg);
}

/* has-chevron variant: rotate arrow on open */
.has-chevron .icon-box-toggle.active {
  /* reuse same animation */
}

/* ─────────────────────────────────────────────────────────────────
   App overlay (mobile)
───────────────────────────────────────────────────────────────── */
.app-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 34;
}

/* ─────────────────────────────────────────────────────────────────
   Sidebar block (Vuero sideblock style)
───────────────────────────────────────────────────────────────── */
.sidebar-block {
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  width: 280px;
  background: var(--white, #fff);
  z-index: 35;
  display: flex;
  flex-direction: column;
  box-shadow: var(--light-box-shadow, -1px 3px 10px 0 rgba(0,0,0,.06));
  transition: transform 0.3s, border-radius 0.3s;
}

.sidebar-block.is-curved {
  border-start-end-radius: 2rem;
  border-end-end-radius:   2rem;
  border-right: 1px solid var(--border, #dbdbdb);
  box-shadow: none;
}

.sidebar-block.is-colored {
  background: color-mix(in oklab, #222225, black 12%);
}

.sidebar-block-header {
  display: flex;
  align-items: center;
  height: 60px;
  padding: 0 2rem;
  border-bottom: 1px solid var(--border, #dbdbdb);
  flex-shrink: 0;
  gap: 0.75rem;
}

.sidebar-block-close {
  margin-left: auto;
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border-radius: var(--radius-rounded, 9999px);
  color: var(--light-text, #a2a5b9);
  transition: background 0.2s;
}
.sidebar-block-close:hover {
  background: var(--widget-grey, #f5f6fa);
  color: var(--dark-text, #283252);
}

.sidebar-block-inner {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0.5rem 0;
}
.sidebar-block-inner::-webkit-scrollbar { width: 3px; }
.sidebar-block-inner::-webkit-scrollbar-thumb {
  border-radius: 5px;
  background: rgba(0,0,0,.15);
}

.sidebar-block-inner ul {
  list-style: none;
  padding: 10px 0;
  margin: 0;
}

.sidebar-block-footer {
  height: 60px;
  padding: 0 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--border, #dbdbdb);
  flex-shrink: 0;
}

/* ─────────────────────────────────────────────────────────────────
   View wrapper
───────────────────────────────────────────────────────────────── */
.view-wrapper {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  transition: margin-left 0.3s;
}
.view-wrapper.is-pushed-block {
  margin-left: 280px;
}

/* ─────────────────────────────────────────────────────────────────
   Page title / toolbar (Vuero style)
───────────────────────────────────────────────────────────────── */
.page-title {
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  height: 60px;
  padding: 0 1.5rem;
  background: var(--white, #fff);
  border-bottom: 1px solid var(--border, #dbdbdb);
  gap: 0.75rem;
  box-shadow: var(--light-box-shadow, -1px 3px 10px 0 rgba(0,0,0,.06));
}

.title-wrap {
  flex: 1;
  margin-left: 0.25rem;
}
.title-wrap .title {
  margin: 0;
  font-family: var(--font-alt, 'Montserrat Variable', sans-serif);
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--dark-text, #283252);
  line-height: 1;
}

.desktop-toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}

/* ─────────────────────────────────────────────────────────────────
   Page content
───────────────────────────────────────────────────────────────── */
.page-content-wrapper {
  flex: 1;
  padding: 1.5rem;
}
.page-content {
  background: transparent;
}

/* ─────────────────────────────────────────────────────────────────
   Transitions
───────────────────────────────────────────────────────────────── */
.slide-x-enter-active,
.slide-x-leave-active { transition: transform 0.3s ease; }
.slide-x-enter-from,
.slide-x-leave-to     { transform: translateX(-100%); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from,
.fade-leave-to     { opacity: 0; }

/* ─────────────────────────────────────────────────────────────────
   Responsive
───────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .mobile-navbar   { display: flex; }
  .view-wrapper    { margin-top: 60px; }
  .view-wrapper.is-pushed-block { margin-left: 0; }
  .sidebar-block:not(.is-mobile) { display: none; }
  .page-title .vuero-hamburger.nav-trigger { display: none; }
  .page-content-wrapper { padding: 1rem; }
}
@media (min-width: 769px) {
  .mobile-navbar { display: none; }
}

/* ─────────────────────────────────────────────────────────────────
   Dark mode stubs
───────────────────────────────────────────────────────────────── */
.is-dark .sidebar-block {
  background: #1e1e24;
  border-color: #2e2e36;
}
.is-dark .sidebar-block-header {
  border-color: #2e2e36;
}
.is-dark .sidebar-block-footer {
  border-color: #2e2e36;
}
.is-dark .page-title {
  background: #18181f;
  border-color: #2e2e36;
}
.is-dark .title-wrap .title {
  color: #e4e4ef;
}
.is-dark .view-wrapper {
  background: #13131a;
}
.is-dark .page-content {
  background: #13131a;
}
/* Single link text dalam dark mode */
.is-dark :deep(.single-link) {
  color: #9f9fbb;
}
.is-dark :deep(.single-link:hover),
.is-dark :deep(.single-link.is-active) {
  background: rgba(255,255,255,0.06);
  color: #fff;
}
.is-dark :deep(.single-link.is-active .icon i) {
  color: var(--primary, #41b3a3);
}
/* Toolbar dark */
.is-dark :deep(.toolbar-btn) {
  color: #9f9fbb;
}
.is-dark :deep(.toolbar-btn:hover) {
  background: rgba(255,255,255,0.08);
  color: #fff;
}
/* Submenu dark */
.is-dark :deep(.is-submenu) {
  color: #7a7a9a;
}
.is-dark :deep(.is-submenu:hover),
.is-dark :deep(.is-submenu.is-active) {
  background: rgba(255,255,255,0.06);
  color: #fff;
}
/* Mobile navbar dark */
.is-dark .mobile-navbar {
  background: #1e1e24;
  border-color: #2e2e36;
}
</style>
