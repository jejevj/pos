/**
 * Format number as Indonesian Rupiah currency
 * Removes unnecessary decimal places (.00)
 * 
 * @param {number|string} value - The value to format
 * @param {boolean} showSymbol - Whether to show Rp symbol (default: true)
 * @returns {string} Formatted currency string
 * 
 * Examples:
 * formatCurrency(1000) => "Rp 1.000"
 * formatCurrency(1000.50) => "Rp 1.000,50"
 * formatCurrency(1000, false) => "1.000"
 */
export function formatCurrency(value, showSymbol = true) {
  if (value === null || value === undefined || value === '') {
    return showSymbol ? 'Rp 0' : '0'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return showSymbol ? 'Rp 0' : '0'
  }

  // Check if number has decimal places
  const hasDecimals = numValue % 1 !== 0

  const formatted = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: hasDecimals ? 2 : 0,
    maximumFractionDigits: hasDecimals ? 2 : 0,
  }).format(numValue)

  if (!showSymbol) {
    // Remove "Rp" and trim
    return formatted.replace('Rp', '').trim()
  }

  return formatted
}

/**
 * Format number as plain number with thousand separators
 * No currency symbol, just formatted number
 * 
 * @param {number|string} value - The value to format
 * @param {number} decimals - Number of decimal places (default: 0)
 * @returns {string} Formatted number string
 * 
 * Examples:
 * formatNumber(1000) => "1.000"
 * formatNumber(1000.5, 2) => "1.000,50"
 */
export function formatNumber(value, decimals = 0) {
  if (value === null || value === undefined || value === '') {
    return '0'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return '0'
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(numValue)
}

/**
 * Parse formatted currency string back to number
 * Handles both "Rp 1.000" and "1.000" formats
 * 
 * @param {string} value - The formatted currency string
 * @returns {number} Parsed number
 * 
 * Examples:
 * parseCurrency("Rp 1.000") => 1000
 * parseCurrency("1.000,50") => 1000.5
 */
export function parseCurrency(value) {
  if (!value) return 0
  
  // Remove Rp, spaces, and dots (thousand separator)
  // Replace comma with dot (decimal separator)
  const cleaned = value
    .toString()
    .replace(/Rp/g, '')
    .replace(/\s/g, '')
    .replace(/\./g, '')
    .replace(/,/g, '.')
  
  return parseFloat(cleaned) || 0
}

/**
 * Format currency for input fields
 * Automatically formats as user types
 * 
 * @param {Event} event - Input event
 * @returns {number} Numeric value
 */
export function formatCurrencyInput(event) {
  const input = event.target
  const value = parseCurrency(input.value)
  input.value = formatCurrency(value, false)
  return value
}
