import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

const userPermissions = ref([])
const userRoles = ref([])
const currentOutletUser = ref(null)
const loading = ref(false)

export function usePermissions() {
  const route = useRoute()

  /**
   * Fetch user permissions for current outlet
   */
  const fetchUserPermissions = async (outletId, userId) => {
    if (!outletId || !userId) return

    loading.value = true
    try {
      const response = await api.get(`/outlets/${outletId}/users/${userId}/permissions`)
      userPermissions.value = response.data.permissions || []
      userRoles.value = response.data.roles || []
      currentOutletUser.value = response.data.user
    } catch (error) {
      console.error('Failed to fetch permissions:', error)
      userPermissions.value = []
      userRoles.value = []
    } finally {
      loading.value = false
    }
  }

  /**
   * Check if user has specific permission
   */
  const hasPermission = (permission) => {
    if (!permission) return true
    return userPermissions.value.some(p => p.name === permission)
  }

  /**
   * Check if user has any of the permissions (OR logic)
   */
  const hasAnyPermission = (permissions) => {
    if (!permissions || permissions.length === 0) return true
    return permissions.some(permission => hasPermission(permission))
  }

  /**
   * Check if user has all permissions (AND logic)
   */
  const hasAllPermissions = (permissions) => {
    if (!permissions || permissions.length === 0) return true
    return permissions.every(permission => hasPermission(permission))
  }

  /**
   * Check if user has specific role
   */
  const hasRole = (roleName) => {
    if (!roleName) return true
    return userRoles.value.some(r => r.name === roleName)
  }

  /**
   * Check if user has any of the roles (OR logic)
   */
  const hasAnyRole = (roles) => {
    if (!roles || roles.length === 0) return true
    return roles.some(role => hasRole(role))
  }

  /**
   * Get user's highest role level
   */
  const getUserLevel = computed(() => {
    if (userRoles.value.length === 0) return 0
    return Math.max(...userRoles.value.map(r => r.level || 0))
  })

  /**
   * Check if user is owner
   */
  const isOwner = computed(() => hasRole('owner'))

  /**
   * Check if user is supervisor or higher
   */
  const isSupervisor = computed(() => hasAnyRole(['owner', 'spv']))

  /**
   * Check if user is manager or higher
   */
  const isManager = computed(() => hasAnyRole(['owner', 'spv', 'manager']))

  /**
   * Get permission display name
   */
  const getPermissionName = (permission) => {
    const perm = userPermissions.value.find(p => p.name === permission)
    return perm?.display_name || permission
  }

  /**
   * Get role display name
   */
  const getRoleName = (roleName) => {
    const role = userRoles.value.find(r => r.name === roleName)
    return role?.display_name || roleName
  }

  /**
   * Clear permissions (on logout or outlet change)
   */
  const clearPermissions = () => {
    userPermissions.value = []
    userRoles.value = []
    currentOutletUser.value = null
  }

  return {
    // State
    userPermissions,
    userRoles,
    currentOutletUser,
    loading,

    // Methods
    fetchUserPermissions,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasRole,
    hasAnyRole,
    getPermissionName,
    getRoleName,
    clearPermissions,

    // Computed
    getUserLevel,
    isOwner,
    isSupervisor,
    isManager,
  }
}
