import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  const loading = ref(false)
  const error = ref(null)
  const menus = ref([])
  const permissions = ref([])

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

  const hasPermission = (permission) => {
    if (isSuperAdmin.value) return true
    return permissions.value.includes(permission)
  }

  const hasAnyPermission = (perms) => {
    if (isSuperAdmin.value) return true
    return perms.some(perm => permissions.value.includes(perm))
  }

  const setAuth = (userData, authToken) => {
    user.value = userData
    token.value = authToken
    localStorage.setItem('auth_token', authToken)
    localStorage.setItem('user', JSON.stringify(userData))
    
    // Extract permissions from roles
    if (userData.roles) {
      const allPermissions = userData.roles.flatMap(role => 
        role.permissions?.map(p => p.name) || []
      )
      permissions.value = [...new Set(allPermissions)]
      localStorage.setItem('permissions', JSON.stringify(permissions.value))
    }
  }

  const clearAuth = () => {
    user.value = null
    token.value = null
    menus.value = []
    permissions.value = []
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
    localStorage.removeItem('menus')
    localStorage.removeItem('permissions')
  }

  const register = async (credentials) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/register', credentials)
      setAuth(response.data.user, response.data.token)
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
      setAuth(response.data.user, response.data.token)
      await fetchMenus()
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
    
    if (storedUser && token.value) {
      try {
        user.value = JSON.parse(storedUser)
        if (storedMenus) {
          menus.value = JSON.parse(storedMenus)
        }
        if (storedPermissions) {
          permissions.value = JSON.parse(storedPermissions)
        }
        // Refresh user data and menus
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
    isAuthenticated,
    userRoles,
    isSuperAdmin,
    isAdmin,
    hasRole,
    hasAnyRole,
    hasPermission,
    hasAnyPermission,
    register,
    login,
    logout,
    fetchUser,
    fetchMenus,
    initAuth
  }
})
