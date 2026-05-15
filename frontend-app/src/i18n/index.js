import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import id from './locales/id.json'

// Default ke 'id' (Indonesia). Jika user pernah pilih bahasa lain, pakai itu.
const savedLocale = localStorage.getItem('locale') || 'id'

const i18n = createI18n({
  legacy: false, // Composition API mode
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: { en, id },
  globalInjection: true // aktifkan $t global di semua komponen
})

export default i18n
