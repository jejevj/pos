import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { encodeOutletId } from '@/utils/outletId'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/themes/vuero/VueroLoginView.vue'),
      meta: { guest: true }
    },
    // Public order tracking — no auth, no layout
    {
      path: '/track/:outletId/:orderCode',
      name: 'order-tracking',
      component: () => import('@/views/OrderTrackingView.vue'),
      meta: { public: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true }
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true, superadminOnly: true },
      children: [
        { path: '', redirect: '/admin/dashboard' },
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Admin Dashboard',
            superadminOnly: true,
          }
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/admin/UsersView.vue'),
          meta: {
            requiresAuth: true,
            title: 'User Management',
            permission: 'users.view'
          }
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/admin/RolesView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Role Management',
            permission: 'roles.view'
          }
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('@/views/admin/PermissionsView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Permission Management',
            permission: 'permissions.view'
          }
        },
        {
          path: 'menus',
          name: 'menus',
          component: () => import('@/views/admin/MenusView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Menu Management',
            permission: 'menus.view'
          }
        },
        {
          path: 'site-settings',
          name: 'site-settings',
          component: () => import('@/views/admin/SiteSettingsView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Pengaturan Situs',
            superadminOnly: true,
          }
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/views/ReportsView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Reports',
            permission: 'reports.view'
          }
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/SettingsView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Settings',
            permission: 'settings.view'
          }
        },
      ]
    },
    {
      path: '/',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: () => {
            const authStore = useAuthStore()
            if (authStore.isSuperAdmin) return '/admin/dashboard'
            if (authStore.isOutletUser && authStore.outletMemberships?.length > 0) {
              const first = authStore.outletMemberships[0]
              const encoded = first.encoded_outlet_id || encodeOutletId(first.outlet_id)
              return `/outlets/${encoded}/dashboard`
            }
            return '/login'
          }
        },
        {
          // Main dashboard — shows outlet 1 dashboard directly (legacy entrypoint)
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/outlet/OutletDashboardView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Dashboard',
            defaultOutletId: 1,
            superadminOnly: true,
          }
        },
        {
          path: 'outlets',
          name: 'outlets',
          component: () => import('@/views/OutletsView.vue'),
          meta: {
            requiresAuth: true,
            title: 'Outlet Management',
            permission: 'outlets.view'
          }
        },
        {
          path: 'outlets/:outletId/dashboard',
          name: 'outlet-dashboard',
          component: () => import('@/views/outlet/OutletDashboardView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Outlet Dashboard'
          }
        },
        {
          path: 'outlets/:outletId/kategori-bahan-baku',
          name: 'outlet-kategori-bahan-baku',
          component: () => import('@/views/outlet/KategoriBahanBakuView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Kategori Bahan Baku'
          }
        },
        {
          path: 'outlets/:outletId/satuan',
          name: 'outlet-satuan',
          component: () => import('@/views/outlet/SatuanView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Satuan'
          }
        },
        {
          path: 'outlets/:outletId/supplier',
          name: 'outlet-supplier',
          component: () => import('@/views/outlet/SupplierView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Supplier'
          }
        },
        {
          path: 'outlets/:outletId/bahan-baku',
          name: 'outlet-bahan-baku',
          component: () => import('@/views/outlet/BahanBakuView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Bahan Baku'
          }
        },
        {
          path: 'outlets/:outletId/kategori-menu',
          name: 'outlet-kategori-menu',
          component: () => import('@/views/outlet/KategoriMenuView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Kategori Menu'
          }
        },
        {
          path: 'outlets/:outletId/menu',
          name: 'outlet-menu',
          component: () => import('@/views/outlet/MenuView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Menu'
          }
        },
        {
          path: 'outlets/:outletId/stock-opname',
          name: 'outlet-stock-opname',
          component: () => import('@/views/outlet/StockOpnameView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Stock Opname'
          }
        },
        {
          path: 'outlets/:outletId/promos',
          name: 'outlet-promos',
          component: () => import('@/views/outlet/PromoView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Promo Management'
          }
        },
        {
          path: 'outlets/:outletId/members',
          name: 'outlet-members',
          component: () => import('@/views/outlet/MemberView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Member Management'
          }
        },
        {
          path: 'outlets/:outletId/users',
          name: 'outlet-users',
          component: () => import('@/views/outlet/UserManagementView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'User Management',
            permission: 'view_users'
          }
        },
        {
          path: 'outlets/:outletId/hr',
          name: 'outlet-hr',
          component: () => import('@/views/outlet/HRView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'HR Management'
          }
        },
        {
          path: 'outlets/:outletId/attendance',
          name: 'outlet-attendance',
          component: () => import('@/views/outlet/AttendanceView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Attendance'
          }
        },
        {
          path: 'outlets/:outletId/utilities',
          name: 'outlet-utilities',
          component: () => import('@/views/outlet/UtilitiesView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Utilities'
          }
        },
        {
          path: 'outlets/:outletId/reports',
          name: 'outlet-reports',
          component: () => import('@/views/outlet/ReportView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Reports'
          }
        },
        {
          path: 'outlets/:outletId/rbac',
          name: 'outlet-rbac',
          component: () => import('@/views/outlet/RBACView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Roles & Permissions'
          }
        },
        {
          path: 'outlets/:outletId/employee-beverage',
          name: 'outlet-employee-beverage',
          component: () => import('@/views/outlet/EmployeeBeverageView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Employee Beverage'
          }
        },
        {
          path: 'outlets/:outletId/pos',
          name: 'outlet-pos',
          component: () => import('@/views/outlet/POSView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Point of Sale'
          }
        },
        {
          path: 'outlets/:outletId/tables',
          name: 'outlet-tables',
          component: () => import('@/views/outlet/TableView.vue'),
          meta: { 
            requiresAuth: true,
            title: 'Table Management'
          }
        },
        {
          path: 'outlets/:outletId/transactions',
          name: 'outlet-transactions',
          component: () => import('@/views/outlet/TransactionView.vue'),
          meta: { requiresAuth: true, title: 'Transaction History' }
        },
        {
          path: 'outlets/:outletId/stations',
          name: 'outlet-stations',
          component: () => import('@/views/outlet/StationView.vue'),
          meta: { requiresAuth: true, title: 'Station Management' }
        },
        {
          path: 'outlets/:outletId/kitchen',
          name: 'outlet-kitchen',
          component: () => import('@/views/outlet/KitchenOrderView.vue'),
          meta: { requiresAuth: true, title: 'Kitchen Display' }
        },
        {
          path: 'outlets/:outletId/shifts',
          name: 'outlet-shifts',
          component: () => import('@/views/outlet/ShiftManagementView.vue'),
          meta: { requiresAuth: true, title: 'Shift Management' }
        },
        {
          path: 'outlets/:outletId/purchases',
          name: 'outlet-purchases',
          component: () => import('@/views/outlet/PurchaseView.vue'),
          meta: { requiresAuth: true, title: 'Incoming Goods' }
        },
        {
          path: 'outlets/:outletId/expenses',
          name: 'outlet-expenses',
          component: () => import('@/views/outlet/ExpenseView.vue'),
          meta: { requiresAuth: true, title: 'Expenses' }
        },
        {
          path: 'outlets/:outletId/whatsapp',
          name: 'outlet-whatsapp',
          component: () => import('@/views/outlet/WhatsAppView.vue'),
          meta: { requiresAuth: true, title: 'WhatsApp Notifications' }
        },
        {
          path: 'outlets/:outletId/payment-methods',
          name: 'outlet-payment-methods',
          component: () => import('@/views/outlet/PaymentMethodView.vue'),
          meta: { requiresAuth: true, title: 'Payment Methods' }
        },
        {
          path: 'outlets/:outletId/stock-locations',
          name: 'outlet-stock-locations',
          component: () => import('@/views/outlet/StockLocationView.vue'),
          meta: { requiresAuth: true, title: 'Stock Locations' }
        },
        {
          path: 'outlets/:outletId/production',
          name: 'outlet-production',
          component: () => import('@/views/outlet/ProductionView.vue'),
          meta: { requiresAuth: true, title: 'Production' }
        }
      ]
    },
    // Standalone Kitchen Display (no layout)
    {
      path: '/outlets/:outletId/kitchen/fullscreen',
      name: 'outlet-kitchen-fullscreen',
      component: () => import('@/views/outlet/KitchenOrderView.vue'),
      meta: { requiresAuth: true, title: 'Kitchen Display', standalone: true }
    },
    // Legacy admin URL redirects (menu URLs di DB masih pakai path lama sebelum prefix /admin)
    { path: '/users',       redirect: '/admin/users' },
    { path: '/roles',       redirect: '/admin/roles' },
    { path: '/permissions', redirect: '/admin/permissions' },
    { path: '/menus',       redirect: '/admin/menus' },
    { path: '/reports',     redirect: '/admin/reports' },
    { path: '/settings',    redirect: '/admin/settings' },
    { path: '/dashboard',   redirect: '/admin/dashboard' },
    {
      path: '/unauthorized',
      name: 'unauthorized',
      component: () => import('@/views/errors/UnauthorizedView.vue')
    },
    {
      path: '/forbidden',
      name: 'forbidden',
      component: () => import('@/views/errors/ForbiddenView.vue')
    },
    {
      path: '/server-error',
      name: 'server-error',
      component: () => import('@/views/errors/ServerErrorView.vue')
    },
    {
      path: '/service-unavailable',
      name: 'service-unavailable',
      component: () => import('@/views/errors/ServiceUnavailableView.vue')
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/errors/NotFoundView.vue')
    }
  ]
})

router.beforeEach((to, _from) => {
  const authStore = useAuthStore()

  // Allow public routes without any auth check
  if (to.meta.public) return true

  // Check authentication - redirect to login with return URL
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { 
      name: 'login',
      query: { redirect: to.fullPath }
    }
  }
  
  // Redirect authenticated users away from guest pages
  if (to.meta.guest && authStore.isAuthenticated) {
    // Outlet user: redirect ke outlet dashboard mereka (encoded ID)
    if (authStore.isOutletUser && authStore.outletMemberships.length > 0) {
      const firstOutlet = authStore.outletMemberships[0]
      const encoded = firstOutlet.encoded_outlet_id || encodeOutletId(firstOutlet.outlet_id)
      return { path: `/outlets/${encoded}/dashboard` }
    }
    if (authStore.isSuperAdmin) return { name: 'admin-dashboard' }
    return { name: 'dashboard' }
  }

  // Blokir outlet user dari halaman superadmin-only (mencegah akses /dashboard global)
  if (to.meta.superadminOnly && authStore.isAuthenticated && authStore.isOutletUser) {
    if (authStore.outletMemberships.length > 0) {
      const firstOutlet = authStore.outletMemberships[0]
      const encoded = firstOutlet.encoded_outlet_id || encodeOutletId(firstOutlet.outlet_id)
      return { path: `/outlets/${encoded}/dashboard` }
    }
    return { name: 'forbidden' }
  }
  
  // Check permissions (skip for dashboard, error pages, and outlet-scoped routes)
  // Outlet routes use hasOutletPermission (checked inside each view/component), not global permissions.
  const isOutletRoute = to.path.includes('/outlets/') && to.params.outletId
  if (to.meta.permission && !isOutletRoute && !['dashboard', 'admin-dashboard', 'unauthorized', 'forbidden'].includes(to.name)) {
    if (!authStore.isSuperAdmin && !authStore.hasPermission(to.meta.permission)) {
      console.warn('Permission denied for:', to.meta.permission)
      return { name: 'forbidden' }
    }
  }
  
  return true
})

export default router
