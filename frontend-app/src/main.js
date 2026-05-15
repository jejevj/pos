import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'

// Extend Aura dengan surface palette khusus dark mode
const AppTheme = definePreset(Aura, {
  semantic: {
    colorScheme: {
      dark: {
        surface: {
          0:   '#ffffff',
          50:  '#1a1a24',
          100: '#1e1e28',
          200: '#232330',
          300: '#2a2a38',
          400: '#32324a',
          500: '#3e3e58',
          600: '#5a5a7a',
          700: '#7a7a9a',
          800: '#9f9fbb',
          900: '#c8c8e0',
          950: '#e4e4f4',
        },
        primary: {
          color:         '{primary.400}',
          contrastColor: '{surface.0}',
          hoverColor:    '{primary.300}',
          activeColor:   '{primary.200}',
        },
        highlight: {
          background:      'color-mix(in srgb, {primary.400}, transparent 84%)',
          focusBackground: 'color-mix(in srgb, {primary.400}, transparent 76%)',
          color:           'rgba(255,255,255,0.87)',
          focusColor:      'rgba(255,255,255,0.87)',
        },
        content: {
          background:       '{surface.100}',
          hoverBackground:  '{surface.200}',
          borderColor:      '{surface.300}',
          color:            '{surface.950}',
          hoverColor:       '{surface.950}',
        },
        overlay: {
          select: {
            background:  '{surface.100}',
            borderColor: '{surface.300}',
            color:       '{surface.950}',
          },
          popover: {
            background:  '{surface.100}',
            borderColor: '{surface.300}',
            color:       '{surface.950}',
          },
          modal: {
            background:  '{surface.100}',
            borderColor: '{surface.300}',
            color:       '{surface.950}',
          },
        },
        text: {
          color:          '{surface.950}',
          hoverColor:     '{surface.950}',
          mutedColor:     '{surface.700}',
          hoverMutedColor:'{surface.800}',
        },
        list: {
          option: {
            focusBackground:         '{surface.200}',
            selectedBackground:      '{highlight.background}',
            selectedFocusBackground: '{highlight.focusBackground}',
            color:                   '{text.color}',
            focusColor:              '{text.hover.color}',
            selectedColor:           '{highlight.color}',
            selectedFocusColor:      '{highlight.focus.color}',
          },
        },
        navigation: {
          item: {
            focusBackground:    '{surface.200}',
            activeBackground:   '{surface.200}',
            color:              '{text.color}',
            focusColor:         '{text.hover.color}',
            activeColor:        '{text.hover.color}',
          },
          submenuLabel: {
            background: 'transparent',
            color:      '{text.muted.color}',
          },
        },
        formField: {
          background:           '{surface.100}',
          disabledBackground:   '{surface.200}',
          filledBackground:     '{surface.200}',
          filledFocusBackground:'{surface.200}',
          borderColor:          '{surface.300}',
          hoverBorderColor:     '{surface.400}',
          focusBorderColor:     '{primary.color}',
          invalidBorderColor:   '{red.400}',
          color:                '{surface.950}',
          disabledColor:        '{surface.600}',
          placeholderColor:     '{surface.600}',
          invalidPlaceholderColor:'{red.400}',
          floatLabelColor:       '{surface.600}',
          floatLabelFocusColor:  '{primary.color}',
          floatLabelInvalidColor:'{red.400}',
          iconColor:             '{surface.700}',
          shadow:                '0 0 #0000, 0 0 #0000, 0 1px 2px 0 rgba(18,18,23,0.05)',
        },
      },
    },
  },
})
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import { vPermission } from './directives/permission'
import i18n from './i18n'
import { formatCurrency, formatNumber } from './utils/currency'
import 'primeicons/primeicons.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)

// Initialize auth store
const authStore = useAuthStore()
authStore.initAuth()

app.use(router)
app.use(i18n)
app.use(PrimeVue, {
  theme: {
    preset: AppTheme,
    options: {
      darkModeSelector: 'html.is-dark',
      cssLayer: {
        name: 'primevue',
        order: 'tailwind-base, primevue, tailwind-utilities'
      }
    }
  }
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)
app.directive('permission', vPermission)

// Global currency filters
app.config.globalProperties.$formatCurrency = formatCurrency
app.config.globalProperties.$formatNumber = formatNumber

app.mount('#app')
