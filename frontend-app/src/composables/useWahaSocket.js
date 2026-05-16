import { ref, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'

const WAHA_WS_URL = import.meta.env.VITE_WAHA_URL?.replace(/^http/, 'ws') || 'ws://localhost:3000'
const WAHA_KEY    = import.meta.env.VITE_WAHA_API_KEY || ''

// WAHA is opt-in. The frontend must NOT auto-connect with a placeholder/default
// API key, otherwise every browser session opens a failing WS to /waha/ws.
const RAW_ENABLED = String(import.meta.env.VITE_WAHA_ENABLED ?? 'false').toLowerCase()
const PLACEHOLDER_KEYS = new Set(['', 'change-me', 'your-waha-api-key'])
export const wahaEnabled =
  (RAW_ENABLED === 'true' || RAW_ENABLED === '1') && !PLACEHOLDER_KEYS.has(WAHA_KEY)

// Singleton WS connection + shared state
let socket = null
let reconnectTimer = null
let consumerCount = 0

// Shared reactive state
const connected   = ref(false)
const unreadCount = ref(0)

// Extra callbacks for components that want raw message events
const messageCallbacks = new Set()

// ── Dedupe ──────────────────────────────────────────────────────────────────
// WAHA can emit the same incoming message multiple times (e.g. broadcast per
// active session when subscribing with session=*, or duplicated payloads on
// reconnect). Without dedupe each repeat would increment unreadCount and fire
// another toast, so a single incoming chat shows up as 3× toasts and 3× badge.
//
// We remember recently-seen message keys in a bounded ring buffer and skip
// anything we've already processed.
const seenIds = new Set()
const seenOrder = []
const SEEN_MAX = 500

function messageKey(payload) {
  if (!payload) return null
  if (payload.id) return String(payload.id)
  // Fallback: chat + timestamp (sec) + short body hash. Good enough to
  // collapse near-simultaneous duplicates without colliding across users.
  const from = payload.from || ''
  const ts   = payload.timestamp || 0
  const body = (payload.body || '').slice(0, 64)
  return `${from}|${ts}|${body}`
}

function markSeen(key) {
  if (!key) return false
  if (seenIds.has(key)) return false
  seenIds.add(key)
  seenOrder.push(key)
  if (seenOrder.length > SEEN_MAX) {
    const drop = seenOrder.shift()
    seenIds.delete(drop)
  }
  return true
}

function buildWsUrl() {
  const params = new URLSearchParams({ 'x-api-key': WAHA_KEY, session: '*' })
  ;['message', 'session.status'].forEach(e => params.append('events', e))
  return `${WAHA_WS_URL}/ws?${params.toString()}`
}

function connect() {
  if (!wahaEnabled) return
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
        dispatchEvent(data)
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

// Centralised event dispatch — runs ONCE per WS frame, no matter how many
// components have called useWahaSocket(). Components subscribe to derived
// state (unreadCount) or raw payloads via onWahaMessage(), but counting,
// dedupe and toasting all happen here exactly once.
let centralToast = null
function dispatchEvent(data) {
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

  // Dedupe — same logical message must only count once across all surfaces
  const key = messageKey(payload)
  if (!markSeen(key)) return

  unreadCount.value++

  if (centralToast) {
    const sender = payload.pushName || payload.from?.replace(/@.*$/, '') || 'Unknown'
    const body   = payload.body || (payload.hasMedia ? '📎 Media' : '')
    centralToast.add({
      severity: 'info',
      summary: `💬 ${sender}`,
      detail: body?.substring(0, 100) || '',
      life: 6000,
      group: 'wa',
    })
  }

  // Notify any component-level subscribers (chat view, etc.) — these may run
  // many times, but receive the same already-deduped payload.
  messageCallbacks.forEach(cb => {
    try { cb(payload) } catch (e) { console.error('[WAHA] subscriber error', e) }
  })
}

export function useWahaSocket() {
  // First mounted consumer "owns" the toast service — subsequent mounts
  // reuse it. This guarantees one toast per incoming message regardless of
  // how many components call useWahaSocket().
  if (!centralToast) {
    try { centralToast = useToast() } catch { /* outside provider */ }
  }

  if (wahaEnabled) {
    consumerCount++
    connect()
  }

  onUnmounted(() => {
    if (!wahaEnabled) return
    consumerCount = Math.max(0, consumerCount - 1)
    if (consumerCount === 0) {
      disconnect()
      centralToast = null
    }
  })

  return { connected, unreadCount, wahaEnabled }
}

/**
 * Subscribe to raw incoming message payloads (already deduped).
 * Returns an unsubscribe function — call it in onUnmounted.
 */
export function onWahaMessage(callback) {
  if (!wahaEnabled) return () => {}
  messageCallbacks.add(callback)
  return () => messageCallbacks.delete(callback)
}

export function clearWahaUnread() {
  unreadCount.value = 0
}
