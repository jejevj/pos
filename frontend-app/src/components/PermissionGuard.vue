<template>
  <slot v-if="hasAccess" />
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps({
  permission: {
    type: String,
    default: null
  },
  permissions: {
    type: Array,
    default: () => []
  },
  role: {
    type: String,
    default: null
  },
  roles: {
    type: Array,
    default: () => []
  },
  requireAll: {
    type: Boolean,
    default: false
  }
})

const { hasPermission, hasAnyPermission, hasAllPermissions, hasRole, hasAnyRole } = usePermissions()

const hasAccess = computed(() => {
  // Check single permission
  if (props.permission) {
    return hasPermission(props.permission)
  }

  // Check multiple permissions
  if (props.permissions.length > 0) {
    return props.requireAll 
      ? hasAllPermissions(props.permissions)
      : hasAnyPermission(props.permissions)
  }

  // Check single role
  if (props.role) {
    return hasRole(props.role)
  }

  // Check multiple roles
  if (props.roles.length > 0) {
    return hasAnyRole(props.roles)
  }

  // No restrictions, allow access
  return true
})
</script>
