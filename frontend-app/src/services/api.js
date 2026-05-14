import axios from 'axios'
import { decodeOutletId } from '@/utils/outletId'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Decode encoded outlet IDs in request URLs before sending to backend.
// URLs like /outlets/MQ/... become /outlets/1/... transparently.
const OUTLET_RE = /\/outlets\/([^/]+)\//g
function decodeOutletUrl(url) {
  if (!url || !url.includes('/outlets/')) return url
  return url.replace(OUTLET_RE, (match, hash) => {
    const id = decodeOutletId(hash)
    return id ? `/outlets/${id}/` : match
  })
}

// Request interceptor — auth token + outlet ID decode
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    if (config.url) {
      config.url = decodeOutletUrl(config.url)
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor — handle global errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
