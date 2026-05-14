import { ref } from 'vue'
import api from '@/services/api'

export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  const data = ref(null)

  const execute = async (apiCall) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiCall()
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'An error occurred'
      throw err
    } finally {
      loading.value = false
    }
  }

  const get = (url, config = {}) => execute(() => api.get(url, config))
  const post = (url, payload, config = {}) => execute(() => api.post(url, payload, config))
  const put = (url, payload, config = {}) => execute(() => api.put(url, payload, config))
  const del = (url, config = {}) => execute(() => api.delete(url, config))

  return {
    loading,
    error,
    data,
    execute,
    get,
    post,
    put,
    delete: del
  }
}
