import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function useIdleTimeout(timeoutMinutes = 1) {
  const router = useRouter()
  const authStore = useAuthStore()
  
  let idleTimer = null
  const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']
  
  const resetTimer = () => {
    clearTimeout(idleTimer)
    
    // Set timeout in milliseconds
    idleTimer = setTimeout(() => {
      handleTimeout()
    }, timeoutMinutes * 60 * 1000)
  }
  
  const handleTimeout = async () => {
    // Logout user
    await authStore.logout()
    
    // Set flag for session expired
    localStorage.setItem('session_expired', 'true')
    
    // Redirect to login
    router.push({ name: 'login' })
  }
  
  const setupListeners = () => {
    events.forEach(event => {
      window.addEventListener(event, resetTimer)
    })
    
    // Start the timer
    resetTimer()
  }
  
  const cleanupListeners = () => {
    events.forEach(event => {
      window.removeEventListener(event, resetTimer)
    })
    
    clearTimeout(idleTimer)
  }
  
  onMounted(() => {
    if (authStore.isAuthenticated) {
      setupListeners()
    }
  })
  
  onUnmounted(() => {
    cleanupListeners()
  })
  
  return {
    resetTimer,
    setupListeners,
    cleanupListeners
  }
}
