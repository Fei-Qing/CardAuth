import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录' }
  },
  {
    path: '/agent/login',
    name: 'AgentLogin',
    component: () => import('@/views/agent/AgentLogin.vue'),
    meta: { title: '代理登录' }
  },
  {
    path: '/agent/register',
    name: 'AgentRegister',
    component: () => import('@/views/agent/AgentRegister.vue'),
    meta: { title: '代理注册' }
  },
  {
    path: '/',
    component: () => import('@/views/layout/MainLayout.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '仪表盘', icon: 'Odometer' }
      },
      {
        path: 'projects',
        name: 'Projects',
        component: () => import('@/views/Projects.vue'),
        meta: { title: '项目管理', icon: 'FolderOpened', role: 'admin' }
      },
      {
        path: 'products',
        name: 'Products',
        component: () => import('@/views/Products.vue'),
        meta: { title: '商品管理', icon: 'Goods', role: 'admin' }
      },
      {
        path: 'cards',
        name: 'Cards',
        component: () => import('@/views/Cards.vue'),
        meta: { title: '卡密管理', icon: 'Tickets' }
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('@/views/Users.vue'),
        meta: { title: '用户管理', icon: 'UserFilled', role: 'admin' }
      },
      {
        path: 'agents',
        name: 'Agents',
        component: () => import('@/views/Agents.vue'),
        meta: { title: '代理管理', icon: 'Avatar' }
      },
      {
        path: 'orders',
        name: 'Orders',
        component: () => import('@/views/Orders.vue'),
        meta: { title: '订单管理', icon: 'Document' }
      },
      {
        path: 'logs',
        name: 'Logs',
        component: () => import('@/views/Logs.vue'),
        meta: { title: '操作日志', icon: 'Notebook', role: 'admin' }
      },
      {
        path: 'authorizations',
        name: 'Authorizations',
        component: () => import('@/views/Authorizations.vue'),
        meta: { title: '授权管理', icon: 'Lock' }
      },
      {
        path: 'coupons',
        name: 'Coupons',
        component: () => import('@/views/admin/Coupons.vue'),
        meta: { title: '优惠码管理', icon: 'Ticket', role: 'admin' }
      },
      {
        path: 'blacklist',
        name: 'Blacklist',
        component: () => import('@/views/Blacklist.vue'),
        meta: { title: '黑名单管理', icon: 'CircleClose', role: 'admin' }
      },
      {
        path: 'payment-config',
        name: 'PaymentConfig',
        component: () => import('@/views/PaymentConfig.vue'),
        meta: { title: '支付配置', icon: 'CreditCard', role: 'admin' }
      },
      {
        path: 'system',
        name: 'System',
        component: () => import('@/views/System.vue'),
        meta: { title: '系统设置', icon: 'Setting', role: 'admin' }
      }
    ]
  },
  {
    path: '/shop',
    name: 'Shop',
    component: () => import('@/views/public/Shop.vue'),
    meta: { title: '购买中心' }
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

function isRoleAllowed(requiredRole, userRole) {
  if (!requiredRole) return true
  if (requiredRole === 'admin') {
    return ['admin', 'project_admin'].includes(userRole)
  }
  return requiredRole === userRole
}

router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - CardAuth` : 'CardAuth'
  const token = localStorage.getItem('access_token')
  const userRole = localStorage.getItem('user_role')
  const publicPaths = ['/login', '/shop', '/agent/login', '/agent/register']
  const isPublic = publicPaths.some(p => to.path.startsWith(p))

  // 未登录访问非公开页面 -> 登录页
  if (!isPublic && !token) {
    return next('/login')
  }

  // 已登录用户访问后台登录页 -> 仪表盘
  if (to.path === '/login' && token) {
    return next('/dashboard')
  }

  // 代理账号访问管理后台专属页面 -> 仪表盘
  if (to.meta.role === 'admin' && userRole === 'agent') {
    return next('/dashboard')
  }

  // 角色权限边界检查
  if (to.meta.role && !isRoleAllowed(to.meta.role, userRole)) {
    return next('/dashboard')
  }

  next()
})

export default router
