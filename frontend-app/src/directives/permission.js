import { usePermissions } from '@/composables/usePermissions'

export const vPermission = {
  mounted(el, binding) {
    const { hasPermission, hasAnyPermission, hasRole, hasAnyRole } = usePermissions()
    
    const { value, modifiers } = binding
    
    let hasAccess = true

    if (typeof value === 'string') {
      // Single permission or role
      if (modifiers.role) {
        hasAccess = hasRole(value)
      } else {
        hasAccess = hasPermission(value)
      }
    } else if (Array.isArray(value)) {
      // Multiple permissions or roles
      if (modifiers.role) {
        hasAccess = hasAnyRole(value)
      } else {
        hasAccess = hasAnyPermission(value)
      }
    }

    if (!hasAccess) {
      // Remove element if no permission
      el.style.display = 'none'
      // Or completely remove from DOM
      // el.parentNode?.removeChild(el)
    }
  },
  
  updated(el, binding) {
    // Re-check permission on update
    const { hasPermission, hasAnyPermission, hasRole, hasAnyRole } = usePermissions()
    
    const { value, modifiers } = binding
    
    let hasAccess = true

    if (typeof value === 'string') {
      if (modifiers.role) {
        hasAccess = hasRole(value)
      } else {
        hasAccess = hasPermission(value)
      }
    } else if (Array.isArray(value)) {
      if (modifiers.role) {
        hasAccess = hasAnyRole(value)
      } else {
        hasAccess = hasAnyPermission(value)
      }
    }

    el.style.display = hasAccess ? '' : 'none'
  }
}
