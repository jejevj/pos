import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { encodeOutletId, decodeOutletId } from '@/utils/outletId'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  const loading = ref(false)
  const error = ref(null)
  const menus = ref([])
  const permissions = ref([])

  /**
   * outletMemberships: array dari outlet-outlet di mana user ini terdaftar
   * Format: [{ outlet_id, outlet_name, schema, roles: [{name, display_name}], permissions: ['view_pos', ...] }]
   */
  const outletMemberships = ref(
    JSON.parse(localStorage.getItem('outlet_memberships') || '[]')
  )

  const isAuthenticated = computed(() => !!token.value)

  const userRoles = computed(() => {
    return user.value?.roles?.map(role => role.name) || []
  })

  const hasRole = (role) => {
    return userRoles.value.includes(role)
  }

  const hasAnyRole = (roles) => {
    return roles.some(role => userRoles.value.includes(role))
  }

  const isSuperAdmin = computed(() => {
    return hasRole('superadmin')
  })

  const isAdmin = computed(() => {
    return hasAnyRole(['admin', 'superadmin'])
  })

  /**
   * True jika user ini adalah outlet user (terdaftar di setidaknya 1 outlet)
   * dan BUKAN superadmin platform.
   */
  const isOutletUser = computed(() => {
    return !isSuperAdmin.value && outletMemberships.value.length > 0
  })

  /**
   * Kembalikan membership entry untuk outlet tertentu, atau null jika tidak terdaftar.
   */
  const getOutletMembership = (outletId) => {
    // Accept both encoded hash and raw numeric ID
    const id = typeof outletId === 'string' && isNaN(parseInt(outletId))
      ? decodeOutletId(outletId)
      : parseInt(outletId)
    return outletMemberships.value.find(m => m.outlet_id === id) || null
  }

  /**
   * Cek apakah user memiliki permission tertentu di outlet tertentu.
   * Superadmin selalu true.
   * Owner outlet (roles.includes('owner')) selalu true.
   */
  const hasOutletPermission = (outletId, permission) => {
    if (isSuperAdmin.value) return true
    const membership = getOutletMembership(outletId)
    if (!membership) return false
    // Owner punya akses penuh
    if (membership.roles?.some(r => (r.name || r) === 'owner')) return true
    return membership.permissions?.includes(permission) || false
  }

  /**
   * Cek apakah user memiliki salah satu dari beberapa permissions di outlet.
   */
  const hasAnyOutletPermission = (outletId, perms) => {
    if (isSuperAdmin.value) return true
    return perms.some(p => hasOutletPermission(outletId, p))
  }

  const hasPermission = (permission) => {
    if (isSuperAdmin.value) return true
    return permissions.value.includes(permission)
  }

  const hasAnyPermission = (perms) => {
    if (isSuperAdmin.value) return true
    return perms.some(perm => permissions.value.includes(perm))
  }

  const setAuth = (userData, authToken, memberships = []) => {
    user.value = userData
    token.value = authToken
    localStorage.setItem('auth_token', authToken)
    localStorage.setItem('user', JSON.stringify(userData))
    
    // Extract permissions from global roles
    if (userData.roles) {
      const allPermissions = userData.roles.flatMap(role => 
        role.permissions?.map(p => p.name) || []
      )
      permissions.value = [...new Set(allPermissions)]
      localStorage.setItem('permissions', JSON.stringify(permissions.value))
    }

    // Store outlet memberships
    outletMemberships.value = memberships
    localStorage.setItem('outlet_memberships', JSON.stringify(memberships))
  }

  const clearAuth = () => {
    user.value = null
    token.value = null
    menus.value = []
    permissions.value = []
    outletMemberships.value = []
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
    localStorage.removeItem('menus')
    localStorage.removeItem('permissions')
    localStorage.removeItem('outlet_memberships')
  }

  const register = async (credentials) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/register', credentials)
      setAuth(response.data.user, response.data.token, response.data.outlet_memberships || [])
      await fetchMenus()
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Registration failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  const login = async (credentials) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/login', credentials)
      setAuth(response.data.user, response.data.token, response.data.outlet_memberships || [])
      await fetchMenus()
      // Attach encoded_outlet_id to each membership for convenience
      outletMemberships.value = outletMemberships.value.map(m => ({
        ...m,
        encoded_outlet_id: encodeOutletId(m.outlet_id)
      }))
      localStorage.setItem('outlet_memberships', JSON.stringify(outletMemberships.value))
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    loading.value = true
    try {
      await api.post('/auth/logout')
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      clearAuth()
      loading.value = false
    }
  }

  const fetchUser = async () => {
    if (!token.value) return
    
    loading.value = true
    try {
      const response = await api.get('/auth/user')
      user.value = response.data.user
      localStorage.setItem('user', JSON.stringify(response.data.user))
      
      // Extract permissions
      if (response.data.user.roles) {
        const allPermissions = response.data.user.roles.flatMap(role => 
          role.permissions?.map(p => p.name) || []
        )
        permissions.value = [...new Set(allPermissions)]
        localStorage.setItem('permissions', JSON.stringify(permissions.value))
      }

      // Refresh outlet memberships
      if (response.data.outlet_memberships) {
        outletMemberships.value = response.data.outlet_memberships
        localStorage.setItem('outlet_memberships', JSON.stringify(response.data.outlet_memberships))
      }
    } catch (err) {
      clearAuth()
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchMenus = async () => {
    if (!token.value) return
    
    try {
      // Superadmin: gunakan endpoint menu global (sidebar)
      // Outlet user: tidak perlu fetch global menus (sidebar tidak ditampilkan)
      if (!isSuperAdmin.value && outletMemberships.value.length > 0) {
        menus.value = []
        return
      }

      const response = await api.get('/menus/user')
      menus.value = response.data
      localStorage.setItem('menus', JSON.stringify(response.data))
    } catch (err) {
      console.error('Failed to fetch menus:', err)
      menus.value = []
    }
  }

  // Initialize user from localStorage
  const initAuth = () => {
    const storedUser = localStorage.getItem('user')
    const storedMenus = localStorage.getItem('menus')
    const storedPermissions = localStorage.getItem('permissions')
    const storedMemberships = localStorage.getItem('outlet_memberships')
    
    if (storedUser && token.value) {
      try {
        user.value = JSON.parse(storedUser)
        if (storedMenus) {
          menus.value = JSON.parse(storedMenus)
        }
        if (storedPermissions) {
          permissions.value = JSON.parse(storedPermissions)
        }
        if (storedMemberships) {
          outletMemberships.value = JSON.parse(storedMemberships)
        }
        // Refresh user data and menus from server
        fetchUser()
        fetchMenus()
      } catch (err) {
        clearAuth()
      }
    }
  }

  return {
    user,
    token,
    loading,
    error,
    menus,
    permissions,
    outletMemberships,
    isAuthenticated,
    userRoles,
    isSuperAdmin,
    isAdmin,
    isOutletUser,
    hasRole,
    hasAnyRole,
    hasPermission,
    hasAnyPermission,
    hasOutletPermission,
    hasAnyOutletPermission,
    getOutletMembership,
    register,
    login,
    logout,
    fetchUser,
    fetchMenus,
    initAuth
  }
})
