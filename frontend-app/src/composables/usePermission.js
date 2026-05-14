import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermission() {
  const authStore = useAuthStore()

  const can = (permission) => {
    return authStore.hasPermission(permission)
  }

  const canAny = (permissions) => {
    return authStore.hasAnyPermission(permissions)
  }

  const hasRole = (role) => {
    return authStore.hasRole(role)
  }

  const hasAnyRole = (roles) => {
    return authStore.hasAnyRole(roles)
  }

  const isSuperAdmin = computed(() => authStore.isSuperAdmin)
  const isAdmin = computed(() => authStore.isAdmin)

  return {
    can,
    canAny,
    hasRole,
    hasAnyRole,
    isSuperAdmin,
    isAdmin
  }
}
