<template>
  <div class="dashboard">
    <Card class="welcome-card">
      <template #content>
        <div class="welcome-content">
          <div>
            <h2>{{ $t('dashboard.welcomeBack', { name: authStore.user?.name }) }}</h2>
            <p>{{ $t('dashboard.loggedInAs') }}: <Tag :value="userRole" severity="secondary" /></p>
          </div>
          <i class="pi pi-home welcome-icon"></i>
        </div>
      </template>
    </Card>

    <div class="stats-grid">
      <Card class="stat-card">
        <template #content>
          <div class="stat-content">
            <div class="stat-icon users">
              <i class="pi pi-users"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.users }}</div>
              <div class="stat-label">{{ $t('dashboard.totalUsers') }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="stat-content">
            <div class="stat-icon roles">
              <i class="pi pi-shield"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.roles }}</div>
              <div class="stat-label">{{ $t('role.roles') }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="stat-content">
            <div class="stat-icon permissions">
              <i class="pi pi-lock"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.permissions }}</div>
              <div class="stat-label">{{ $t('permission.permissions') }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="stat-card">
        <template #content>
          <div class="stat-content">
            <div class="stat-icon menus">
              <i class="pi pi-bars"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.menus }}</div>
              <div class="stat-label">{{ $t('menuMgmt.menus') }}</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <div class="info-section">
      <Card>
        <template #title>{{ $t('dashboard.yourRoles') }}</template>
        <template #content>
          <div class="role-badges">
            <Tag 
              v-for="role in authStore.user?.roles" 
              :key="role.id"
              :value="role.display_name"
              class="role-tag"
            />
          </div>
        </template>
      </Card>

      <Card v-if="!authStore.isSuperAdmin">
        <template #title>{{ $t('dashboard.yourPermissions') }}</template>
        <template #content>
          <div class="permissions-list">
            <Chip 
              v-for="permission in authStore.permissions" 
              :key="permission"
              :label="permission"
              class="permission-chip"
            />
          </div>
        </template>
      </Card>

      <Card v-else>
        <template #title>{{ $t('dashboard.superadminAccess') }}</template>
        <template #content>
          <Message severity="success" :closable="false">
            {{ $t('dashboard.fullAccess') }}
          </Message>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Chip from 'primevue/chip'
import Message from 'primevue/message'

const authStore = useAuthStore()

const stats = ref({
  users: 0,
  roles: 0,
  permissions: 0,
  menus: 0
})

const userRole = computed(() => {
  const roles = authStore.user?.roles || []
  return roles.map(r => r.display_name).join(', ') || 'User'
})

const fetchStats = async () => {
  try {
    if (authStore.isSuperAdmin || authStore.hasPermission('users.view')) {
      const usersResponse = await api.get('/admin/users?per_page=1')
      stats.value.users = usersResponse.data.total
    }
    
    if (authStore.isSuperAdmin || authStore.hasPermission('roles.view')) {
      const rolesResponse = await api.get('/admin/roles')
      stats.value.roles = rolesResponse.data.length
    }
    
    if (authStore.isSuperAdmin || authStore.hasPermission('permissions.view')) {
      const permsResponse = await api.get('/admin/permissions')
      stats.value.permissions = permsResponse.data.permissions.length
    }
    
    stats.value.menus = authStore.menus.length
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

onMounted(() => {
  fetchStats()
})
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
  margin: 0 auto;
}

.welcome-card {
  margin-bottom: 2rem;
  background: linear-gradient(135deg, var(--sage-primary) 0%, var(--sage-hover) 100%);
  border: none;
}

.welcome-card :deep(.p-card-content) {
  padding: 2rem;
}

.welcome-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.welcome-content h2 {
  margin: 0 0 0.75rem 0;
  color: white;
  font-size: 1.75rem;
  font-weight: 600;
}

.welcome-content p {
  margin: 0;
  color: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
}

.welcome-icon {
  font-size: 4rem;
  color: rgba(255, 255, 255, 0.2);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  transition: all 0.3s ease;
  border: 1px solid #e5e7eb;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
  border-color: var(--sage-primary);
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.stat-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  color: white;
  flex-shrink: 0;
}

.stat-icon.users {
  background-color: var(--sage-primary);
}

.stat-icon.roles {
  background-color: var(--sage-light);
}

.stat-icon.permissions {
  background-color: var(--sage-hover);
}

.stat-icon.menus {
  background-color: #a8d5a8;
}

.stat-info {
  flex: 1;
  min-width: 0;
}

.stat-value {
  font-size: 2.25rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.stat-label {
  color: #6b7280;
  font-size: 0.95rem;
  font-weight: 500;
}

.info-section {
  display: grid;
  gap: 1.5rem;
}

.info-section :deep(.p-card-title) {
  color: #1f2937;
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.role-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.role-tag {
  background-color: var(--sage-primary) !important;
  color: white !important;
  font-weight: 500;
}

.permissions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  max-height: 400px;
  overflow-y: auto;
  padding: 0.5rem 0;
}

.permission-chip {
  background-color: #f3f4f6 !important;
  color: var(--sage-primary) !important;
  border: 1px solid #e5e7eb;
  font-weight: 500;
}

.permission-chip:hover {
  background: var(--sage-bg) !important;
  border-color: var(--sage-primary);
}

/* Scrollbar styling */
.permissions-list::-webkit-scrollbar {
  width: 6px;
}

.permissions-list::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 3px;
}

.permissions-list::-webkit-scrollbar-thumb {
  background: var(--sage-primary);
  border-radius: 3px;
}

.permissions-list::-webkit-scrollbar-thumb:hover {
  background: var(--sage-hover);
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-value {
    font-size: 1.75rem;
  }
  
  .stat-icon {
    width: 56px;
    height: 56px;
    font-size: 1.5rem;
  }
  
  .welcome-content {
    flex-direction: column;
    text-align: center;
  }
  
  .welcome-icon {
    font-size: 3rem;
  }
}

@media (max-width: 480px) {
  .welcome-card :deep(.p-card-content) {
    padding: 1.5rem;
  }
  
  .welcome-content h2 {
    font-size: 1.5rem;
  }
  
  .stat-content {
    gap: 1rem;
  }
}
</style>
