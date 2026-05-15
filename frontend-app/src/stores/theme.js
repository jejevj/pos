import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // Layout theme: 'vuero' adalah satu-satunya tema aktif saat ini
  const activeTheme = ref(localStorage.getItem('app-theme') || 'vuero')

  // Dark mode
  const isDark = ref(localStorage.getItem('app-dark') === 'true')

  function setTheme(theme) {
    activeTheme.value = theme
    localStorage.setItem('app-theme', theme)
  }

  function toggleDark() {
    isDark.value = !isDark.value
    localStorage.setItem('app-dark', String(isDark.value))
    applyDark(isDark.value)
  }

  function setDark(val) {
    isDark.value = val
    localStorage.setItem('app-dark', String(val))
    applyDark(val)
  }

  // Terapkan class ke <html> agar CSS global dark-mode bisa bekerja
  function applyDark(val) {
    if (val) {
      document.documentElement.classList.add('is-dark')    // VueroLayout & custom CSS
      document.body.classList.add('dark-mode')             // legacy fallback
    } else {
      document.documentElement.classList.remove('is-dark')
      document.body.classList.remove('dark-mode')
    }
  }

  // Apply on store init
  applyDark(isDark.value)

  return { activeTheme, isDark, setTheme, toggleDark, setDark }
})
