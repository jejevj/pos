import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export function useLanguage() {
  const { locale, t } = useI18n()

  const currentLocale = computed({
    get: () => locale.value,
    set: (newLocale) => {
      locale.value = newLocale
      localStorage.setItem('locale', newLocale)
    }
  })

  const availableLocales = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'id', name: 'Indonesia', flag: '🇮🇩' }
  ]

  const changeLocale = (newLocale) => {
    currentLocale.value = newLocale
  }

  return {
    locale: currentLocale,
    availableLocales,
    changeLocale,
    t
  }
}
