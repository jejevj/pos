import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // 'default' = current PrimeVue layout, 'vuero' = Vuero-style layout
  const activeTheme = ref(localStorage.getItem('app-theme') || 'default')

  function setTheme(theme) {
    activeTheme.value = theme
    localStorage.setItem('app-theme', theme)
  }

  return { activeTheme, setTheme }
})
