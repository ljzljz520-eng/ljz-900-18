import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const routes = [
  // 默认进入登录页：访问 / 时重定向到 /login
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'Login', component: () => import('@/views/LoginView.vue'), meta: { title: '登录', public: true } },
  { path: '/fix', name: 'Fix', component: () => import('@/views/FixView.vue'), meta: { title: '员工整改', public: true } },
  // 管理后台（/admin、/employees、/summary）：需登录，未登录会重定向到 /login
  {
    path: '/',
    component: () => import('@/components/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: 'admin', name: 'Admin', component: () => import('@/views/AdminView.vue'), meta: { title: '管理员-检查上传', requiresAuth: true } },
      { path: 'employees', name: 'Employees', component: () => import('@/views/EmployeesView.vue'), meta: { title: '员工管理', requiresAuth: true } },
      { path: 'summary', name: 'Summary', component: () => import('@/views/SummaryView.vue'), meta: { title: '汇总看板', requiresAuth: true } },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  const requiresAuth = to.matched.some((r) => r.meta?.requiresAuth)
  if (requiresAuth && !auth.isLoggedIn) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  // 在登录页且有 token 时先校验有效性，避免过期 token 导致：跳 /admin → 401 → 回 /login 的循环
  if (to.path === '/login' && auth.isLoggedIn) {
    try {
      const user = await auth.fetchUser()
      if (user) return { path: to.query.redirect || '/admin' }
    } catch {
      auth.clearAuth()
    }
    return true
  }
})

router.afterEach((to) => {
  if (to.meta?.title) {
    document.title = to.meta.title + ' - 员工卫生考核系统'
  }
})

export default router
