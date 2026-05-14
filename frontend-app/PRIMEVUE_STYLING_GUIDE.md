# PrimeVue Styling Guide for Outlet Views

## Standard Layout Pattern

### Main Container
```vue
<template>
  <div class="card">
    <!-- Content here -->
  </div>
</template>
```

### Header Section
```vue
<div class="flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="m-0">{{ title }}</h2>
    <p class="text-color-secondary mt-2">{{ subtitle }}</p>
  </div>
  <Button label="Add" icon="pi pi-plus" @click="openDialog()" />
</div>
```

### Form Fields in Dialog
```vue
<div class="flex flex-column gap-3 mt-3">
  <div class="field">
    <label for="fieldName" class="font-semibold">Label</label>
    <InputText id="fieldName" v-model="form.field" class="w-full" />
  </div>
</div>
```

### Grid Layout
```vue
<!-- 2 columns -->
<div class="grid">
  <div class="col-12 md:col-6">
    <div class="field">
      <label>Field 1</label>
      <InputText class="w-full" />
    </div>
  </div>
  <div class="col-12 md:col-6">
    <div class="field">
      <label>Field 2</label>
      <InputText class="w-full" />
    </div>
  </div>
</div>

<!-- 3 columns -->
<div class="grid">
  <div class="col-12 md:col-4">...</div>
  <div class="col-12 md:col-4">...</div>
  <div class="col-12 md:col-4">...</div>
</div>
```

### DataTable
```vue
<DataTable :value="items" :loading="loading" stripedRows paginator :rows="10">
  <Column field="name" header="Name" sortable />
  <Column header="Actions" style="width: 10rem">
    <template #body="{ data }">
      <Button icon="pi pi-pencil" text rounded severity="info" @click="edit(data)" class="mr-2" />
      <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" />
    </template>
  </Column>
</DataTable>
```

## Color Classes

### Text Colors
- Primary text: (default)
- Secondary text: `text-color-secondary`
- Success: `text-green-500`
- Warning: `text-orange-500`
- Danger: `text-red-500`
- Info: `text-blue-500`

### Background Colors
- Primary: `var(--primary-100)` for light, `var(--primary-500)` for normal
- Success: `var(--green-100)` / `var(--green-500)`
- Warning: `var(--orange-100)` / `var(--orange-500)`
- Danger: `var(--red-100)` / `var(--red-500)`
- Info: `var(--blue-100)` / `var(--blue-500)`

## Spacing

### Margins
- Small: `mb-2`, `mt-2`, `ml-2`, `mr-2`
- Medium: `mb-3`, `mt-3`, `ml-3`, `mr-3`
- Large: `mb-4`, `mt-4`, `ml-4`, `mr-4`

### Padding
Use PrimeVue's built-in padding from `.card` class

### Gaps
- Small: `gap-2`
- Medium: `gap-3`
- Large: `gap-4`

## Flex Utilities

### Flexbox
- `flex` - Display flex
- `flex-column` - Flex direction column
- `flex-row` - Flex direction row
- `justify-content-between` - Space between
- `justify-content-center` - Center
- `align-items-center` - Align center
- `align-items-start` - Align start

### Width
- `w-full` - 100% width
- `w-6` - 50% width (6/12)
- `w-4` - 33% width (4/12)
- `w-3` - 25% width (3/12)

## Typography

### Headings
- `<h2 class="m-0">` - Main heading (no margin)
- `<h3 class="m-0">` - Sub heading

### Text Sizes
- `text-sm` - Small
- (default) - Normal
- `text-lg` - Large
- `text-xl` - Extra large
- `text-2xl` - 2x large
- `text-3xl` - 3x large

### Font Weight
- `font-semibold` - Semi bold
- `font-bold` - Bold

## Common Patterns

### Checkbox with Label
```vue
<div class="field flex align-items-center gap-2">
  <Checkbox v-model="form.active" :binary="true" inputId="active" />
  <label for="active">Active</label>
</div>
```

### Action Buttons
```vue
<Button icon="pi pi-pencil" text rounded severity="info" @click="edit()" class="mr-2" />
<Button icon="pi pi-trash" text rounded severity="danger" @click="delete()" />
```

### Tags
```vue
<Tag :value="count" severity="info" />
<Tag :value="status" :severity="active ? 'success' : 'danger'" />
```

## DO NOT USE

❌ Tailwind classes like:
- `p-6`, `px-4`, `py-2` (use PrimeVue spacing)
- `text-gray-800`, `text-gray-600` (use `text-color-secondary`)
- `justify-between`, `items-center` (use `justify-content-between`, `align-items-center`)
- `flex-col` (use `flex-column`)
- Custom colors like `#8fbc8f` (use PrimeVue color variables)

✅ Use PrimeVue Flex CSS utilities instead
