import { formatCurrency, formatNumber, parseCurrency, formatCurrencyInput } from '@/utils/currency'

/**
 * Composable for currency formatting
 * Use this in Vue components for reactive currency formatting
 */
export function useCurrency() {
  return {
    formatCurrency,
    formatNumber,
    parseCurrency,
    formatCurrencyInput
  }
}
