import { ref, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'

const WAHA_WS_URL = import.meta.env.VITE_WAHA_URL?.replace(/^http/, 'ws') || 'ws://localhost:3000'
const WAHA_KEY    = import.meta.env.VITE_WAHA_API_KEY || 'dbe9f1b6a3a54ef99d6f019fc5f3ef67'

// Singleton
let socket = null
let reconnectTimer = null
const listeners = new Set()

// Shared reactive state
const connected   = ref(false)
const unreadCount = ref(0)

// Extra callbacks for components that want raw message events
const messageCallbacks = new Set()

function buildWsUrl() {
  const params = new URLSearchParams({ 'x-api-key': WAHA_KEY, session: '*' })
  ;['message', 'session.status'].forEach(e => params.append('events', e))
  return `${WAHA_WS_URL}/ws?${params.toString()}`
}

function connect() {
  if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) return
  try {
    socket = new WebSocket(buildWsUrl())
    socket.onopen  = () => {
      console.log('[WAHA WS] Connected')
      if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null }
    }
    socket.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        listeners.forEach(fn => fn(data))
      } catch { /* ignore */ }
    }
    socket.onclose = () => {
      console.log('[WAHA WS] Disconnected — reconnecting in 5s')
      socket = null
      reconnectTimer = setTimeout(connect, 5000)
    }
    socket.onerror = () => { socket?.close() }
  } catch {
    reconnectTimer = setTimeout(connect, 5000)
  }
}

function disconnect() {
  if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null }
  if (socket) { socket.onclose = null; socket.close(); socket = null }
}

export function useWahaSocket() {
  const toast = useToast()

  function handleMessage(data) {
    if (data.event === 'session.status') {
      connected.value = data.payload?.status === 'WORKING'
      return
    }

    if (data.event !== 'message') return
    const payload = data.payload
    if (!payload || payload.fromMe) return

    // Ignore status/story broadcasts
    const from = payload.from || ''
    if (from.includes('@broadcast') || from.includes('status@') || from.includes('@newsletter')) return

    unreadCount.value++

    const sender = payload.pushName || payload.from?.replace(/@.*$/, '') || 'Unknown'
    const body   = payload.body || (payload.hasMedia ? '📎 Media' : '')

    toast.add({
      severity: 'info',
      summary: `💬 ${sender}`,
      detail: body?.substring(0, 100) || '',
      life: 6000,
      group: 'wa',
    })

    // Notify any component-level subscribers
    messageCallbacks.forEach(cb => cb(payload))
  }

  listeners.add(handleMessage)
  connect()

  onUnmounted(() => {
    listeners.delete(handleMessage)
    if (listeners.size === 0) disconnect()
  })

  return { connected, unreadCount }
}

/**
 * Subscribe to raw incoming message payloads.
 * Returns an unsubscribe function — call it in onUnmounted.
 */
export function onWahaMessage(callback) {
  messageCallbacks.add(callback)
  return () => messageCallbacks.delete(callback)
}

export function clearWahaUnread() {
  unreadCount.value = 0
}
