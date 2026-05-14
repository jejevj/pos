<template>
  <div class="icon-picker">
    <div class="icon-input-wrapper" @click="toggleDialog">
      <InputText 
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        placeholder="Select an icon..."
        readonly
        class="icon-input"
      />
      <div class="icon-preview-box">
        <i :class="modelValue || 'pi pi-search'" class="icon-preview"></i>
      </div>
    </div>

    <Dialog 
      v-model:visible="dialogVisible" 
      header="Select Icon"
      :modal="true"
      :style="{ width: '700px', maxHeight: '80vh' }"
    >
      <div class="icon-picker-content">
        <div class="search-section">
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText 
              v-model="searchQuery" 
              placeholder="Search icons..." 
              style="width: 100%"
            />
          </IconField>
        </div>

        <div class="icons-grid">
          <div 
            v-for="icon in filteredIcons" 
            :key="icon"
            class="icon-item"
            :class="{ 'icon-item-selected': modelValue === icon }"
            @click="selectIcon(icon)"
            v-tooltip.top="icon"
          >
            <i :class="icon"></i>
          </div>
        </div>

        <div v-if="filteredIcons.length === 0" class="no-results">
          <i class="pi pi-search" style="font-size: 2rem; color: #9ca3af;"></i>
          <p>No icons found</p>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Clear" 
          text 
          severity="secondary"
          @click="clearIcon"
        />
        <Button 
          label="Close" 
          @click="dialogVisible = false"
          :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const dialogVisible = ref(false)
const searchQuery = ref('')

// PrimeIcons list
const primeIcons = [
  'pi pi-home',
  'pi pi-users',
  'pi pi-user',
  'pi pi-shield',
  'pi pi-lock',
  'pi pi-unlock',
  'pi pi-key',
  'pi pi-bars',
  'pi pi-th-large',
  'pi pi-list',
  'pi pi-chart-bar',
  'pi pi-chart-line',
  'pi pi-chart-pie',
  'pi pi-cog',
  'pi pi-wrench',
  'pi pi-sliders-h',
  'pi pi-sliders-v',
  'pi pi-bell',
  'pi pi-envelope',
  'pi pi-inbox',
  'pi pi-send',
  'pi pi-calendar',
  'pi pi-clock',
  'pi pi-stopwatch',
  'pi pi-folder',
  'pi pi-folder-open',
  'pi pi-file',
  'pi pi-file-edit',
  'pi pi-file-pdf',
  'pi pi-file-excel',
  'pi pi-image',
  'pi pi-images',
  'pi pi-video',
  'pi pi-camera',
  'pi pi-map',
  'pi pi-map-marker',
  'pi pi-compass',
  'pi pi-globe',
  'pi pi-bookmark',
  'pi pi-star',
  'pi pi-star-fill',
  'pi pi-heart',
  'pi pi-heart-fill',
  'pi pi-flag',
  'pi pi-tag',
  'pi pi-tags',
  'pi pi-shopping-cart',
  'pi pi-shopping-bag',
  'pi pi-credit-card',
  'pi pi-money-bill',
  'pi pi-wallet',
  'pi pi-dollar',
  'pi pi-percentage',
  'pi pi-box',
  'pi pi-briefcase',
  'pi pi-building',
  'pi pi-server',
  'pi pi-database',
  'pi pi-cloud',
  'pi pi-cloud-upload',
  'pi pi-cloud-download',
  'pi pi-download',
  'pi pi-upload',
  'pi pi-external-link',
  'pi pi-link',
  'pi pi-paperclip',
  'pi pi-bookmark',
  'pi pi-book',
  'pi pi-pencil',
  'pi pi-pen-to-square',
  'pi pi-trash',
  'pi pi-plus',
  'pi pi-minus',
  'pi pi-times',
  'pi pi-check',
  'pi pi-check-circle',
  'pi pi-times-circle',
  'pi pi-exclamation-circle',
  'pi pi-exclamation-triangle',
  'pi pi-info-circle',
  'pi pi-question-circle',
  'pi pi-search',
  'pi pi-search-plus',
  'pi pi-search-minus',
  'pi pi-filter',
  'pi pi-sort',
  'pi pi-sort-up',
  'pi pi-sort-down',
  'pi pi-sort-alpha-down',
  'pi pi-sort-alpha-up',
  'pi pi-sort-amount-down',
  'pi pi-sort-amount-up',
  'pi pi-sort-numeric-down',
  'pi pi-sort-numeric-up',
  'pi pi-arrow-up',
  'pi pi-arrow-down',
  'pi pi-arrow-left',
  'pi pi-arrow-right',
  'pi pi-arrow-up-left',
  'pi pi-arrow-up-right',
  'pi pi-arrow-down-left',
  'pi pi-arrow-down-right',
  'pi pi-chevron-up',
  'pi pi-chevron-down',
  'pi pi-chevron-left',
  'pi pi-chevron-right',
  'pi pi-angle-up',
  'pi pi-angle-down',
  'pi pi-angle-left',
  'pi pi-angle-right',
  'pi pi-angle-double-up',
  'pi pi-angle-double-down',
  'pi pi-angle-double-left',
  'pi pi-angle-double-right',
  'pi pi-caret-up',
  'pi pi-caret-down',
  'pi pi-caret-left',
  'pi pi-caret-right',
  'pi pi-refresh',
  'pi pi-replay',
  'pi pi-sync',
  'pi pi-spinner',
  'pi pi-circle',
  'pi pi-circle-fill',
  'pi pi-ellipsis-h',
  'pi pi-ellipsis-v',
  'pi pi-eye',
  'pi pi-eye-slash',
  'pi pi-power-off',
  'pi pi-sign-in',
  'pi pi-sign-out',
  'pi pi-play',
  'pi pi-pause',
  'pi pi-stop',
  'pi pi-forward',
  'pi pi-backward',
  'pi pi-step-forward',
  'pi pi-step-backward',
  'pi pi-fast-forward',
  'pi pi-fast-backward',
  'pi pi-volume-up',
  'pi pi-volume-down',
  'pi pi-volume-off',
  'pi pi-microphone',
  'pi pi-phone',
  'pi pi-mobile',
  'pi pi-tablet',
  'pi pi-desktop',
  'pi pi-wifi',
  'pi pi-bluetooth',
  'pi pi-print',
  'pi pi-save',
  'pi pi-copy',
  'pi pi-clone',
  'pi pi-share-alt',
  'pi pi-window-maximize',
  'pi pi-window-minimize',
  'pi pi-th-large',
  'pi pi-table',
  'pi pi-palette',
  'pi pi-code',
  'pi pi-sitemap',
  'pi pi-ticket',
  'pi pi-thumbs-up',
  'pi pi-thumbs-down',
  'pi pi-comment',
  'pi pi-comments',
  'pi pi-ban',
  'pi pi-discord',
  'pi pi-github',
  'pi pi-facebook',
  'pi pi-twitter',
  'pi pi-google',
  'pi pi-apple',
  'pi pi-microsoft',
  'pi pi-android',
  'pi pi-whatsapp',
  'pi pi-telegram',
  'pi pi-slack',
  'pi pi-youtube',
  'pi pi-vimeo',
  'pi pi-instagram',
  'pi pi-linkedin',
  'pi pi-reddit',
  'pi pi-paypal',
  'pi pi-amazon',
  'pi pi-prime'
]

const filteredIcons = computed(() => {
  if (!searchQuery.value) return primeIcons
  
  const query = searchQuery.value.toLowerCase()
  return primeIcons.filter(icon => icon.toLowerCase().includes(query))
})

const toggleDialog = () => {
  dialogVisible.value = true
}

const selectIcon = (icon) => {
  emit('update:modelValue', icon)
  dialogVisible.value = false
}

const clearIcon = () => {
  emit('update:modelValue', '')
  dialogVisible.value = false
}
</script>

<style scoped>
.icon-picker {
  width: 100%;
}

.icon-input-wrapper {
  display: flex;
  gap: 0.5rem;
  cursor: pointer;
}

.icon-input {
  flex: 1;
  cursor: pointer;
}

.icon-preview-box {
  width: 3rem;
  height: 3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #f9fafb;
  transition: all 0.2s;
}

.icon-preview-box:hover {
  background: #f3f4f6;
  border-color: var(--sage-primary);
}

.icon-preview {
  font-size: 1.5rem;
  color: var(--sage-primary);
}

.icon-picker-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.search-section {
  width: 100%;
}

.icons-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
  gap: 0.5rem;
  max-height: 400px;
  overflow-y: auto;
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.icon-item {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  background: #ffffff;
}

.icon-item:hover {
  border-color: var(--sage-primary);
  background: var(--sage-bg);
  transform: scale(1.05);
}

.icon-item i {
  font-size: 1.5rem;
  color: #4b5563;
}

.icon-item-selected {
  border-color: var(--sage-primary);
  background: var(--sage-bg);
}

.icon-item-selected i {
  color: var(--sage-primary);
}

.no-results {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

.no-results p {
  margin: 0;
}

/* Scrollbar */
.icons-grid::-webkit-scrollbar {
  width: 8px;
}

.icons-grid::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 4px;
}

.icons-grid::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.icons-grid::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>
