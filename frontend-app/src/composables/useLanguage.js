import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import i18n from '@/i18n'

// useLanguage menggunakan instance i18n global agar perubahan locale
// langsung reaktif di SEMUA komponen, bukan hanya komponen yang memanggilnya.
export function useLanguage() {
  // useI18n() di sini untuk akses locale reaktif dalam template komponen ini
  const { locale } = useI18n()

  const availableLocales = [
    { code: 'id', name: 'Indonesia', flag: '🇮🇩' },
    { code: 'en', name: 'English',   flag: '🇬🇧' },
  ]

  /**
   * Ganti bahasa secara global — mengubah i18n.global.locale
   * sehingga semua komponen yang pakai $t atau useI18n() ikut berubah.
   */
  const changeLocale = (newLocale) => {
    i18n.global.locale.value = newLocale   // update global instance
    locale.value = newLocale               // update instance lokal (sinkron)
    localStorage.setItem('locale', newLocale)
  }

  return {
    locale: computed({
      get: () => i18n.global.locale.value,
      set: (v) => changeLocale(v),
    }),
    availableLocales,
    changeLocale,
  }
}
