/**
 * Outlet ID encoding helpers.
 *
 * Encodes numeric outlet IDs as fixed 8-character hex strings
 * so URLs don't expose raw database IDs and always look uniform.
 *
 * Strategy: XOR the ID with a secret seed, encode as 8-char hex.
 * Always exactly 8 characters regardless of ID size.
 *
 * encode(1)  → "504f5301"
 * encode(2)  → "504f5302"
 * decode("504f5301") → 1
 */

const SEED = 0x504F5300 // Secret XOR seed — change this to any 8-hex value

export function encodeOutletId(id) {
  if (id === null || id === undefined) return ''
  const n = (parseInt(id) ^ SEED) >>> 0  // XOR + force unsigned 32-bit
  return n.toString(16).padStart(8, '0')
}

export function decodeOutletId(hash) {
  if (!hash) return null
  try {
    if (/^[0-9a-f]{8}$/i.test(hash)) {
      const n = (parseInt(hash, 16) ^ SEED) >>> 0
      return n > 0 ? n : null
    }
    // Fallback: maybe it's a raw numeric ID (old link or direct access)
    const num = parseInt(hash, 10)
    return isNaN(num) ? null : num
  } catch {
    return null
  }
}
