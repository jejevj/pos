<template>
  <div class="theme-switcher" v-tooltip.bottom="'Switch Layout Theme'">
    <button
      class="theme-btn"
      :class="{ active: themeStore.activeTheme === 'default' }"
      @click="themeStore.setTheme('default')"
      title="Default Theme"
    >
      <i class="pi pi-th-large"></i>
    </button>
    <button
      class="theme-btn"
      :class="{ active: themeStore.activeTheme === 'vuero' }"
      @click="themeStore.setTheme('vuero')"
      title="Vuero Theme"
    >
      <i class="pi pi-palette"></i>
    </button>
  </div>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme'
const themeStore = useThemeStore()
</script>

<style scoped>
.theme-switcher {
  display: flex;
  align-items: center;
  background: var(--widget-grey, #f3f4f6);
  border-radius: 8px;
  padding: 2px;
  gap: 2px;
}
.theme-btn {
  background: none;
  border: none;
  cursor: pointer;
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  transition: all 0.15s;
  font-size: 0.85rem;
}
.theme-btn.active {
  background: white;
  color: #3b82f6;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.theme-btn:hover:not(.active) { color: #374151; }
</style>
