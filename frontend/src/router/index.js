import { createRouter, createWebHistory } from 'vue-router'
import { setProductType, setIsAdmin, isPinned, pinnedVertical, product } from '../composables/useAccount.js'
import { post } from '../composables/useApi.js'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/',                 redirect: '/dashboard' },
    { path: '/login',            component: () => import('../views/Login.vue'),          meta: { public: true } },
    { path: '/register',         component: () => import('../views/Register.vue'),        meta: { public: true } },
    { path: '/forgot-password',  component: () => import('../views/ForgotPassword.vue'), meta: { public: true } },
    { path: '/reset-password',   component: () => import('../views/ResetPassword.vue'),  meta: { public: true } },
    { path: '/dashboard',        component: () => import('../views/Dashboard.vue') },
    { path: '/properties',       component: () => import('../views/Properties.vue') },
    { path: '/properties/:id',   component: () => import('../views/PropertyDetail.vue') },
    { path: '/tenants',          component: () => import('../views/Tenants.vue') },
    { path: '/tenants/:id',      component: () => import('../views/TenantDetail.vue') },
    { path: '/rent',             component: () => import('../views/Rent.vue') },
    { path: '/appointments',         component: () => import('../views/Appointments.vue') },
    { path: '/jobs',                 component: () => import('../views/Jobs.vue') },
    { path: '/invoices',             component: () => import('../views/Invoices.vue') },
    { path: '/invoices/new',         component: () => import('../views/InvoiceBuilder.vue') },
    { path: '/invoices/:id',         component: () => import('../views/InvoiceBuilder.vue') },
    { path: '/appointment-payments', component: () => import('../views/AppointmentPayments.vue') },
    { path: '/import',           component: () => import('../views/Import.vue') },
    { path: '/settings',         component: () => import('../views/Settings.vue') },
    { path: '/billing',          component: () => import('../views/Billing.vue') },
    { path: '/admin',            component: () => import('../views/Admin.vue') },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.public) return true

  try {
    // When a vertical is pinned (?vertical= or subdomain), auto-switch the session to the
    // matching account so each vertical has its own isolated data.
    if (isPinned()) {
      try { await post('/api/switch-to-vertical', { vertical: pinnedVertical() }) } catch (_) {}
    }

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 3000)

    const res = await fetch('/api/dashboard', {
      credentials: 'include',
      redirect: 'manual',
      signal: controller.signal
    })
    clearTimeout(timeoutId)

    if (res.type === 'opaqueredirect' || res.status === 0) return '/login'
    if (res.status !== 200) return '/login'
    let dashData = {}
    try {
      dashData = await res.json()
      if (dashData.product_type) setProductType(dashData.product_type)
      if (dashData.is_admin !== undefined) setIsAdmin(dashData.is_admin)
    } catch (_) {}

    // Admin route: only accessible to admin users
    if (to.path === '/admin') {
      return dashData.is_admin ? true : '/dashboard'
    }

    // Expired trial / canceled — send to billing (allow billing + settings through)
    if (!dashData.account_active && to.path !== '/billing' && to.path !== '/settings') {
      return '/billing'
    }

    // Block routes that don't belong to this product's navigation
    // Use matched route patterns so detail pages (/properties/:id) pass when /properties is in nav
    const validPaths = product.value.navigation.map(n => n.to)
    const isValid = to.matched.some(r =>
      validPaths.some(vp => r.path === vp || r.path.startsWith(vp + '/'))
    )
    if (!isValid) return '/dashboard'
  } catch (err) {
    console.warn('API check failed:', err.message)
    return '/login'
  }

  return true
})

export default router
