/**
 * useThermalPrinter
 *
 * Supports two connection modes stored in localStorage per outlet:
 *   - bluetooth : Web Bluetooth API (Chrome/Edge Android & Desktop)
 *   - network   : HTTP POST to a local print-bridge server (any browser)
 *
 * ESC/POS commands are built here from the `lines` array returned by the backend.
 * Paper width assumed: 58 mm → 32 chars per line
 *                      80 mm → 48 chars per line
 */

import { ref } from 'vue'

const PAPER_COLS = { 58: 32, 80: 48 }

// Bluetooth service/characteristic UUIDs for most generic ESC/POS printers
const BT_SERVICE   = '000018f0-0000-1000-8000-00805f9b34fb'
const BT_CHAR_WRITE = '00002af1-0000-1000-8000-00805f9b34fb'

// ESC/POS byte helpers
const ESC = 0x1b
const GS  = 0x1d

function cmd(...bytes) { return new Uint8Array(bytes) }

const ESC_INIT       = cmd(ESC, 0x40)
const ESC_BOLD_ON    = cmd(ESC, 0x45, 0x01)
const ESC_BOLD_OFF   = cmd(ESC, 0x45, 0x00)
const ESC_ALIGN_LEFT   = cmd(ESC, 0x61, 0x00)
const ESC_ALIGN_CENTER = cmd(ESC, 0x61, 0x01)
const ESC_ALIGN_RIGHT  = cmd(ESC, 0x61, 0x02)
const GS_CUT         = cmd(GS,  0x56, 0x42, 0x00)  // partial cut

function lineFeed(n = 1) { return new Uint8Array(n).fill(0x0a) }

function encodeText(text) {
  // Simple ASCII + Latin-1 encoding
  const bytes = []
  for (let i = 0; i < text.length; i++) {
    const code = text.charCodeAt(i)
    bytes.push(code < 256 ? code : 0x3f) // '?' for unsupported chars
  }
  return new Uint8Array(bytes)
}

function buildRow(left, right, cols) {
  const maxLeft = cols - right.length - 1
  const paddedLeft = left.length > maxLeft ? left.substring(0, maxLeft) : left.padEnd(maxLeft)
  return paddedLeft + ' ' + right
}

function buildDivider(cols) {
  return '-'.repeat(cols)
}

/**
 * Convert lines[] from backend into a single Uint8Array of ESC/POS bytes
 */
function buildEscPos(lines, cols = 32) {
  const parts = [ESC_INIT]

  for (const line of lines) {
    switch (line.type) {
      case 'align':
        if (line.value === 'center') parts.push(ESC_ALIGN_CENTER)
        else if (line.value === 'right') parts.push(ESC_ALIGN_RIGHT)
        else parts.push(ESC_ALIGN_LEFT)
        break

      case 'bold':
        parts.push(line.value ? ESC_BOLD_ON : ESC_BOLD_OFF)
        break

      case 'text':
        parts.push(encodeText(line.value))
        parts.push(lineFeed())
        break

      case 'row':
        parts.push(ESC_ALIGN_LEFT)
        parts.push(encodeText(buildRow(line.left, line.right, cols)))
        parts.push(lineFeed())
        break

      case 'divider':
        parts.push(ESC_ALIGN_LEFT)
        parts.push(encodeText(buildDivider(cols)))
        parts.push(lineFeed())
        break

      case 'feed':
        parts.push(lineFeed(line.lines || 3))
        break

      case 'cut':
        parts.push(GS_CUT)
        break

      case 'qr':
        // ESC/POS QR code command (GS ( k)
        if (line.value) {
          const qrData = new TextEncoder().encode(line.value)
          const len = qrData.length + 3
          const lenL = len & 0xff
          const lenH = (len >> 8) & 0xff
          // Model: GS ( k pL pH cn fn n  (model 2)
          parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 4, 0, 49, 65, 50, 0]))
          // Size: GS ( k pL pH cn fn n
          parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 3, 0, 49, 67, 6]))
          // Error correction: GS ( k pL pH cn fn n
          parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 3, 0, 49, 69, 48]))
          // Store data: GS ( k pL pH cn fn m d1...dk
          parts.push(new Uint8Array([0x1d, 0x28, 0x6b, lenL, lenH, 49, 80, 48]))
          parts.push(qrData)
          // Print: GS ( k pL pH cn fn m
          parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 3, 0, 49, 81, 48]))
          parts.push(lineFeed(1))
        }
        break
    }
  }

  // Merge all Uint8Arrays into one
  const total = parts.reduce((n, p) => n + p.length, 0)
  const result = new Uint8Array(total)
  let offset = 0
  for (const p of parts) {
    result.set(p, offset)
    offset += p.length
  }
  return result
}

// ─── Composable ──────────────────────────────────────────────────────────────

export function useThermalPrinter(outletId) {
  const settingsKey = `thermal_printer_${outletId}`

  const settings = ref(loadSettings())
  const connecting = ref(false)
  const printing = ref(false)
  const error = ref(null)

  // Bluetooth device handle (persisted in memory for session)
  let btDevice = null
  let btChar   = null

  function loadSettings() {
    try {
      return JSON.parse(localStorage.getItem(settingsKey)) || defaultSettings()
    } catch {
      return defaultSettings()
    }
  }

  function defaultSettings() {
    return {
      mode: null,          // null | 'bluetooth' | 'network'
      paperWidth: 58,      // 58 | 80
      networkUrl: 'http://localhost:9100', // print-bridge URL
      configured: false,
    }
  }

  function saveSettings(newSettings) {
    settings.value = { ...settings.value, ...newSettings, configured: true }
    localStorage.setItem(settingsKey, JSON.stringify(settings.value))
  }

  function clearSettings() {
    settings.value = defaultSettings()
    localStorage.removeItem(settingsKey)
    btDevice = null
    btChar   = null
  }

  // ── Bluetooth ──────────────────────────────────────────────────────────────

  async function connectBluetooth() {
    if (!navigator.bluetooth) {
      throw new Error('Web Bluetooth tidak didukung di browser ini. Gunakan Chrome/Edge.')
    }
    connecting.value = true
    error.value = null
    try {
      btDevice = await navigator.bluetooth.requestDevice({
        filters: [{ services: [BT_SERVICE] }],
        optionalServices: [BT_SERVICE],
      })
      const server  = await btDevice.gatt.connect()
      const service = await server.getPrimaryService(BT_SERVICE)
      btChar        = await service.getCharacteristic(BT_CHAR_WRITE)
      saveSettings({ mode: 'bluetooth' })
      return true
    } finally {
      connecting.value = false
    }
  }

  async function printViaBluetooth(escPosBytes) {
    if (!btChar) {
      // Try to reconnect if device is remembered
      if (btDevice) {
        const server  = await btDevice.gatt.connect()
        const service = await server.getPrimaryService(BT_SERVICE)
        btChar        = await service.getCharacteristic(BT_CHAR_WRITE)
      } else {
        throw new Error('Printer Bluetooth belum terhubung. Silakan hubungkan printer terlebih dahulu.')
      }
    }

    // Write in 512-byte chunks (BLE MTU limit)
    const CHUNK = 512
    for (let i = 0; i < escPosBytes.length; i += CHUNK) {
      await btChar.writeValueWithoutResponse(escPosBytes.slice(i, i + CHUNK))
    }
  }

  // ── Network (print-bridge) ─────────────────────────────────────────────────
  // Expects a small HTTP server running locally that accepts:
  //   POST /print  body: { data: base64EncodedEscPos }

  async function printViaNetwork(escPosBytes) {
    const url = (settings.value.networkUrl || 'http://localhost:9100').replace(/\/$/, '') + '/print'
    const base64 = btoa(String.fromCharCode(...escPosBytes))

    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ data: base64 }),
    })

    if (!response.ok) {
      const text = await response.text()
      throw new Error(`Print server error: ${text}`)
    }
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Main print function. Pass the `lines` array from the backend thermal-receipt endpoint.
   */
  async function print(lines) {
    if (!settings.value.configured || !settings.value.mode) {
      throw new Error('PRINTER_NOT_CONFIGURED')
    }

    printing.value = true
    error.value = null

    try {
      const cols = PAPER_COLS[settings.value.paperWidth] || 32
      const escPosBytes = buildEscPos(lines, cols)

      if (settings.value.mode === 'bluetooth') {
        await printViaBluetooth(escPosBytes)
      } else if (settings.value.mode === 'network') {
        await printViaNetwork(escPosBytes)
      }
    } finally {
      printing.value = false
    }
  }

  const isBluetoothSupported = typeof navigator !== 'undefined' && !!navigator.bluetooth

  return {
    settings,
    connecting,
    printing,
    error,
    isBluetoothSupported,
    connectBluetooth,
    saveSettings,
    clearSettings,
    print,
  }
}
