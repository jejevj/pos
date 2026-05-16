/**
 * Client-side image compression helper backed by `browser-image-compression`.
 *
 * Why client-side: the backend has been kept dumb (`/upload/image` accepts
 * whatever we send). Compressing in the browser saves bandwidth on slow
 * outlet links and avoids round-tripping a 5MB selfie to PHP just so it can
 * be resized server-side.
 *
 * The helper is tolerant: if the input is not a File/Blob, is not an image,
 * or compression throws, the original file is returned so the caller's
 * upload still succeeds.
 */
import imageCompression from 'browser-image-compression'

const DEFAULT_OPTIONS = {
  maxSizeMB: 0.6,
  maxWidthOrHeight: 1600,
  initialQuality: 0.82,
  useWebWorker: true,
  fileType: undefined,
  alwaysKeepResolution: false,
}

const SKIPPED_MIME_PREFIXES = ['image/svg', 'image/gif']

function shouldSkip(file) {
  if (!file || typeof file !== 'object') return true
  if (typeof File !== 'undefined' && !(file instanceof File) && !(file instanceof Blob)) return true
  const type = (file.type || '').toLowerCase()
  if (!type.startsWith('image/')) return true
  return SKIPPED_MIME_PREFIXES.some((p) => type.startsWith(p))
}

/**
 * Compress a single image file. Returns the original file unchanged if
 * compression cannot help (already small, unsupported format, or fails).
 *
 * @param {File|Blob} file
 * @param {object} [options]            See browser-image-compression docs.
 * @param {number} [options.maxSizeMB]
 * @param {number} [options.maxWidthOrHeight]
 * @param {number} [options.initialQuality]
 * @param {string} [options.fileType]   e.g. 'image/webp' to force re-encode.
 * @param {(p:number)=>void} [options.onProgress]  0..100
 * @returns {Promise<File>}
 */
export async function compressImage(file, options = {}) {
  if (shouldSkip(file)) return file

  const { onProgress, ...rest } = options
  const opts = { ...DEFAULT_OPTIONS, ...rest }
  if (typeof onProgress === 'function') opts.onProgress = onProgress

  // Already smaller than the target? Skip the worker round-trip.
  const targetBytes = (opts.maxSizeMB ?? DEFAULT_OPTIONS.maxSizeMB) * 1024 * 1024
  if (typeof file.size === 'number' && file.size <= targetBytes * 0.95) {
    return file
  }

  try {
    const out = await imageCompression(file, opts)
    if (!out) return file
    // Library may return a Blob in some browsers — wrap so callers get a File
    // with the original filename (server-side validators often key on extension).
    if (typeof File !== 'undefined' && !(out instanceof File)) {
      const name = file.name || 'image'
      const type = out.type || file.type || 'image/jpeg'
      return new File([out], name, { type, lastModified: Date.now() })
    }
    // Some bundles strip the original filename — restore it if needed.
    if (out instanceof File && (!out.name || out.name === 'image.png') && file.name) {
      return new File([out], file.name, { type: out.type || file.type, lastModified: Date.now() })
    }
    return out
  } catch (e) {
    // Don't block the upload on a compression failure — log and return original.
    if (typeof console !== 'undefined') {
      console.warn('[imageCompression] falling back to original:', e?.message || e)
    }
    return file
  }
}

/**
 * Convenience wrapper for `<FileUpload @select>` (PrimeVue) or any handler
 * that hands you a File. Returns { file, ratio } where ratio = compressed/original.
 */
export async function compressForUpload(file, options = {}) {
  const original = file
  const compressed = await compressImage(file, options)
  const originalSize = original?.size || 0
  const compressedSize = compressed?.size || originalSize
  const ratio = originalSize > 0 ? compressedSize / originalSize : 1
  return { file: compressed, originalSize, compressedSize, ratio }
}

export default compressImage
