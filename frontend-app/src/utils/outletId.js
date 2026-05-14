/**
 * Outlet ID encoding helpers.
 *
 * Encodes numeric outlet IDs as Base64URL strings so URLs don't expose
 * raw database IDs. The backend always receives the real numeric ID.
 *
 * encode(1)  → "MQ"
 * decode("MQ") → 1
 */

export function encodeOutletId(id) {
  if (id === null || id === undefined) return ''
  return btoa(String(id))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '')
}

export function decodeOutletId(hash) {
  if (!hash) return null
  try {
    // Re-pad base64 if needed
    const padded = hash.replace(/-/g, '+').replace(/_/g, '/')
    const pad = padded.length % 4
    const b64 = pad ? padded + '='.repeat(4 - pad) : padded
    const decoded = atob(b64)
    const num = parseInt(decoded, 10)
    return isNaN(num) ? null : num
  } catch {
    // If decoding fails, maybe it's already a raw number (fallback)
    const num = parseInt(hash, 10)
    return isNaN(num) ? null : num
  }
}
