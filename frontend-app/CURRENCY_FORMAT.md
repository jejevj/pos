# Currency Formatting Guide

## Overview

Sistem ini menggunakan format currency Indonesia (IDR) yang **menghilangkan desimal ,00** untuk tampilan yang lebih bersih.

## Format Rules

- **Integer**: `Rp 1.000` (bukan `Rp 1.000,00`)
- **Decimal**: `Rp 1.000,50` (tetap tampilkan jika ada desimal)
- **Thousand separator**: `.` (titik)
- **Decimal separator**: `,` (koma)

## Usage in Vue Components

### 1. Using Global Filter (Recommended)

```vue
<template>
  <!-- Display currency -->
  <div>{{ $formatCurrency(price) }}</div>
  <!-- Output: Rp 50.000 -->

  <!-- Display without Rp symbol -->
  <div>{{ $formatCurrency(price, false) }}</div>
  <!-- Output: 50.000 -->

  <!-- Display plain number -->
  <div>{{ $formatNumber(quantity) }}</div>
  <!-- Output: 1.000 -->

  <!-- Display with decimals -->
  <div>{{ $formatNumber(weight, 2) }}</div>
  <!-- Output: 1.500,50 -->
</template>

<script setup>
const price = 50000
const quantity = 1000
const weight = 1500.5
</script>
```

### 2. Using Composable

```vue
<script setup>
import { useCurrency } from '@/composables/useCurrency'

const { formatCurrency, formatNumber, parseCurrency } = useCurrency()

const price = 50000
const formattedPrice = formatCurrency(price) // "Rp 50.000"
const plainPrice = formatCurrency(price, false) // "50.000"
const numericValue = parseCurrency("Rp 50.000") // 50000
</script>
```

### 3. Direct Import

```vue
<script setup>
import { formatCurrency, formatNumber } from '@/utils/currency'

const displayPrice = formatCurrency(50000) // "Rp 50.000"
</script>
```

## Examples by Use Case

### DataTable Column

```vue
<Column field="price" header="Harga">
  <template #body="{ data }">
    {{ $formatCurrency(data.price) }}
  </template>
</Column>
```

### Input Field (Display Only)

```vue
<InputText 
  :value="$formatCurrency(form.total, false)" 
  readonly 
/>
```

### Computed Property

```vue
<script setup>
import { computed } from 'vue'
import { formatCurrency } from '@/utils/currency'

const order = ref({ subtotal: 100000, tax: 10000 })

const totalFormatted = computed(() => {
  return formatCurrency(order.value.subtotal + order.value.tax)
})
</script>

<template>
  <div>Total: {{ totalFormatted }}</div>
</template>
```

### Form Summary

```vue
<template>
  <div class="summary">
    <div class="row">
      <span>Subtotal:</span>
      <span>{{ $formatCurrency(subtotal) }}</span>
    </div>
    <div class="row">
      <span>Tax (10%):</span>
      <span>{{ $formatCurrency(tax) }}</span>
    </div>
    <div class="row total">
      <span>Total:</span>
      <span>{{ $formatCurrency(total) }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const subtotal = ref(100000)
const tax = computed(() => subtotal.value * 0.1)
const total = computed(() => subtotal.value + tax.value)
</script>
```

## API Functions

### `formatCurrency(value, showSymbol = true)`

Format number as Indonesian Rupiah currency.

**Parameters:**
- `value` (number|string): The value to format
- `showSymbol` (boolean): Whether to show "Rp" symbol (default: true)

**Returns:** (string) Formatted currency

**Examples:**
```javascript
formatCurrency(1000)        // "Rp 1.000"
formatCurrency(1000.50)     // "Rp 1.000,50"
formatCurrency(1000, false) // "1.000"
formatCurrency(0)           // "Rp 0"
```

### `formatNumber(value, decimals = 0)`

Format number with thousand separators (no currency symbol).

**Parameters:**
- `value` (number|string): The value to format
- `decimals` (number): Number of decimal places (default: 0)

**Returns:** (string) Formatted number

**Examples:**
```javascript
formatNumber(1000)       // "1.000"
formatNumber(1000.5, 2)  // "1.000,50"
formatNumber(1000.5, 0)  // "1.001" (rounded)
```

### `parseCurrency(value)`

Parse formatted currency string back to number.

**Parameters:**
- `value` (string): The formatted currency string

**Returns:** (number) Parsed number

**Examples:**
```javascript
parseCurrency("Rp 1.000")     // 1000
parseCurrency("1.000,50")     // 1000.5
parseCurrency("Rp 1.500.000") // 1500000
```

## Migration Guide

### Before (with ,00)

```vue
<template>
  <!-- Old way - shows Rp 50.000,00 -->
  <div>Rp {{ price.toLocaleString('id-ID') }}</div>
</template>
```

### After (without ,00)

```vue
<template>
  <!-- New way - shows Rp 50.000 -->
  <div>{{ $formatCurrency(price) }}</div>
</template>
```

## Common Patterns

### 1. Price Display in Card

```vue
<Card>
  <template #title>{{ product.name }}</template>
  <template #content>
    <div class="price">{{ $formatCurrency(product.price) }}</div>
  </template>
</Card>
```

### 2. Order Summary

```vue
<div class="order-summary">
  <div class="line-item" v-for="item in items" :key="item.id">
    <span>{{ item.name }} x {{ item.qty }}</span>
    <span>{{ $formatCurrency(item.price * item.qty) }}</span>
  </div>
  <div class="total">
    <strong>Total:</strong>
    <strong>{{ $formatCurrency(orderTotal) }}</strong>
  </div>
</div>
```

### 3. DataTable with Currency

```vue
<DataTable :value="products">
  <Column field="name" header="Produk" />
  <Column field="price" header="Harga">
    <template #body="{ data }">
      {{ $formatCurrency(data.price) }}
    </template>
  </Column>
  <Column field="stock" header="Stok">
    <template #body="{ data }">
      {{ $formatNumber(data.stock) }}
    </template>
  </Column>
</DataTable>
```

### 4. Input with Currency Display

```vue
<template>
  <div class="form-field">
    <label>Harga</label>
    <InputNumber 
      v-model="form.price" 
      mode="currency" 
      currency="IDR" 
      locale="id-ID"
      :minFractionDigits="0"
      :maxFractionDigits="0"
    />
    <small>Preview: {{ $formatCurrency(form.price) }}</small>
  </div>
</template>
```

## Best Practices

1. **Always use formatCurrency for money values**
   ```vue
   <!-- Good -->
   {{ $formatCurrency(price) }}
   
   <!-- Bad -->
   Rp {{ price.toLocaleString() }}
   ```

2. **Use formatNumber for quantities**
   ```vue
   <!-- Good -->
   {{ $formatNumber(stock) }} unit
   
   <!-- Bad -->
   {{ stock }} unit
   ```

3. **Parse before sending to API**
   ```javascript
   // Good
   const data = {
     price: parseCurrency(formattedPrice)
   }
   
   // Bad - sending formatted string
   const data = {
     price: "Rp 50.000"
   }
   ```

4. **Use computed for complex calculations**
   ```vue
   <script setup>
   const total = computed(() => {
     return items.value.reduce((sum, item) => 
       sum + (item.price * item.qty), 0
     )
   })
   
   const totalFormatted = computed(() => formatCurrency(total.value))
   </script>
   ```

## Notes

- Format mengikuti standar Indonesia (id-ID)
- Desimal hanya ditampilkan jika ada nilai desimal (bukan 0)
- Thousand separator menggunakan titik (.)
- Decimal separator menggunakan koma (,)
- Semua fungsi handle null/undefined dengan aman (return "Rp 0" atau "0")
