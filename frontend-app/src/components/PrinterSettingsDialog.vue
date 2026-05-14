<template>
  <Dialog v-model:visible="visible" :header="$t('printer.settings')" modal :style="{ width: '420px' }">
    <div class="printer-settings">

      <!-- Current status -->
      <div class="printer-status" :class="statusClass">
        <i :class="statusIcon"></i>
        <span>{{ statusText }}</span>
      </div>

      <!-- Mode selection -->
      <div class="form-field">
        <label>{{ $t('printer.connectionMode') }}</label>
        <div class="mode-buttons">
          <Button :label="$t('printer.bluetooth')" icon="pi pi-bluetooth"
                  :outlined="form.mode !== 'bluetooth'"
                  :disabled="!isBluetoothSupported"
                  @click="form.mode = 'bluetooth'" />
          <Button :label="$t('printer.network')" icon="pi pi-wifi"
                  :outlined="form.mode !== 'network'"
                  @click="form.mode = 'network'" />
        </div>
        <small v-if="!isBluetoothSupported" class="hint-warn">
          {{ $t('printer.bluetoothNotSupported') }}
        </small>
      </div>

      <!-- Network URL (only for network mode) -->
      <div v-if="form.mode === 'network'" class="form-field">
        <label>{{ $t('printer.bridgeUrl') }}</label>
        <InputText v-model="form.networkUrl" placeholder="http://localhost:9100" fluid />
        <small class="hint">{{ $t('printer.bridgeHint') }}</small>
      </div>

      <!-- Paper width -->
      <div class="form-field">
        <label>{{ $t('printer.paperWidth') }}</label>
        <div class="mode-buttons">
          <Button label="58 mm" :outlined="form.paperWidth !== 58" @click="form.paperWidth = 58" />
          <Button label="80 mm" :outlined="form.paperWidth !== 80" @click="form.paperWidth = 80" />
        </div>
      </div>

      <!-- Bluetooth connect button -->
      <div v-if="form.mode === 'bluetooth'" class="form-field">
        <Button :label="settings.configured && settings.mode === 'bluetooth'
                          ? $t('printer.reconnect')
                          : $t('printer.connectBluetooth')"
                icon="pi pi-bluetooth" :loading="connecting"
                @click="handleBluetoothConnect" fluid />
      </div>

    </div>

    <template #footer>
      <Button :label="$t('common.cancel')" text @click="visible = false" />
      <Button v-if="settings.configured" :label="$t('printer.clearSettings')"
              icon="pi pi-trash" severity="danger" outlined @click="handleClear" />
      <Button v-if="form.mode === 'network'" :label="$t('common.save')"
              icon="pi pi-check" @click="handleSaveNetwork" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const props = defineProps({
  modelValue: Boolean,
  printer: { type: Object, required: true }, // useThermalPrinter instance
})
const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const toast = useToast()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const { settings, connecting, isBluetoothSupported, connectBluetooth, saveSettings, clearSettings } = props.printer

const form = ref({
  mode: settings.value.mode || null,
  paperWidth: settings.value.paperWidth || 58,
  networkUrl: settings.value.networkUrl || 'http://localhost:9100',
})

watch(() => props.modelValue, (v) => {
  if (v) {
    form.value = {
      mode: settings.value.mode || null,
      paperWidth: settings.value.paperWidth || 58,
      networkUrl: settings.value.networkUrl || 'http://localhost:9100',
    }
  }
})

const statusClass = computed(() => {
  if (!settings.value.configured) return 'status-none'
  return settings.value.mode === 'bluetooth' ? 'status-bt' : 'status-net'
})

const statusIcon = computed(() => {
  if (!settings.value.configured) return 'pi pi-times-circle'
  return settings.value.mode === 'bluetooth' ? 'pi pi-bluetooth' : 'pi pi-wifi'
})

const statusText = computed(() => {
  if (!settings.value.configured) return t('printer.notConfigured')
  if (settings.value.mode === 'bluetooth') return t('printer.connectedBluetooth')
  return t('printer.connectedNetwork', { url: settings.value.networkUrl })
})

async function handleBluetoothConnect() {
  try {
    saveSettings({ paperWidth: form.value.paperWidth })
    await connectBluetooth()
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('printer.bluetoothConnected'), life: 3000 })
    visible.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: e.message, life: 5000 })
  }
}

function handleSaveNetwork() {
  saveSettings({ mode: 'network', paperWidth: form.value.paperWidth, networkUrl: form.value.networkUrl })
  toast.add({ severity: 'success', summary: t('messages.success'), detail: t('printer.settingsSaved'), life: 3000 })
  visible.value = false
}

function handleClear() {
  clearSettings()
  toast.add({ severity: 'info', summary: t('messages.info'), detail: t('printer.settingsCleared'), life: 3000 })
  visible.value = false
}
</script>

<style scoped>
.printer-settings { display: flex; flex-direction: column; gap: 1.25rem; }

.printer-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
}
.status-none { background: #fef2f2; color: #dc2626; }
.status-bt   { background: #eff6ff; color: #2563eb; }
.status-net  { background: #f0fdf4; color: #16a34a; }

.form-field { display: flex; flex-direction: column; gap: 0.5rem; }
.form-field label { font-weight: 600; font-size: 0.875rem; }

.mode-buttons { display: flex; gap: 0.5rem; }
.mode-buttons button { flex: 1; }

.hint { color: #6b7280; font-size: 0.75rem; }
.hint-warn { color: #d97706; font-size: 0.75rem; }
</style>
