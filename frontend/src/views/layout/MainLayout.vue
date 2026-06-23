<template>
  <el-container class="main-layout" :class="{ 'is-mobile': isMobile }">
    <!-- ========== 移动端遮罩层 ========== -->
    <transition name="overlay-fade">
      <div v-if="isMobile && sidebarVisible" class="sidebar-overlay" @click="closeSidebar" />
    </transition>

    <!-- ========== 侧边栏 ========== -->
    <el-aside
      :width="isMobile ? '260px' : (isCollapse ? '72px' : '240px')"
      class="sidebar"
      :class="[`theme-${currentTheme}`, { 'mobile-open': isMobile && sidebarVisible }]"
    >
      <!-- Logo 区域 -->
      <div class="logo" @click="goDashboard">
        <div class="logo-icon">
          <el-icon :size="26"><Key /></el-icon>
        </div>
        <transition name="logo-fade">
          <span v-if="!isCollapse" class="logo-text">CardAuth</span>
        </transition>
      </div>

      <!-- 菜单区域 -->
      <el-scrollbar class="sidebar-scroll">
        <div class="menu-wrapper" ref="menuWrapperRef">
          <div class="menu-slider" :style="menuSliderStyle"></div>
          <el-menu
            :default-active="activeMenu"
            :collapse="isCollapse && !isMobile"
            :collapse-transition="false"
            router
            class="side-menu"
            @select="onMenuSelect"
          >
          <!-- 工作台 -->
          <el-tooltip :content="isCollapse && !isMobile ? '仪表盘' : ''" placement="right" :disabled="!isCollapse || isMobile">
            <el-menu-item index="/dashboard">
              <el-icon><Odometer /></el-icon>
              <span>仪表盘</span>
            </el-menu-item>
          </el-tooltip>

          <!-- 业务管理 -->
          <el-sub-menu index="biz" v-if="isAdmin">
            <template #title>
              <el-icon><Briefcase /></el-icon>
              <span>业务管理</span>
            </template>
            <el-menu-item index="/projects" v-if="isPureAdmin">
              <el-icon><FolderOpened /></el-icon>
              <span>项目管理</span>
            </el-menu-item>
            <el-menu-item index="/products" v-if="canManage">
              <el-icon><Goods /></el-icon>
              <span>商品管理</span>
            </el-menu-item>
            <el-menu-item index="/cards">
              <el-icon><Tickets /></el-icon>
              <span>卡密管理</span>
            </el-menu-item>
            <el-menu-item index="/authorizations">
              <el-icon><Lock /></el-icon>
              <span>授权管理</span>
            </el-menu-item>
          </el-sub-menu>

          <!-- 代理业务 -->
          <el-sub-menu index="agent-biz" v-if="isAgent">
            <template #title>
              <el-icon><Briefcase /></el-icon>
              <span>代理业务</span>
            </template>
            <el-menu-item index="/cards">
              <el-icon><Tickets /></el-icon>
              <span>卡密管理</span>
            </el-menu-item>
            <el-menu-item index="/authorizations">
              <el-icon><Lock /></el-icon>
              <span>授权管理</span>
            </el-menu-item>
          </el-sub-menu>

          <!-- 订单与代理 -->
          <el-sub-menu index="trade" v-if="isPureAdmin">
            <template #title>
              <el-icon><Money /></el-icon>
              <span>订单与代理</span>
            </template>
            <el-menu-item index="/orders">
              <el-icon><Document /></el-icon>
              <span>订单管理</span>
            </el-menu-item>
            <el-menu-item index="/coupons" v-if="user?.role === 'admin'">
              <el-icon><Discount /></el-icon>
              <span>优惠码管理</span>
            </el-menu-item>
            <el-menu-item index="/blacklist">
              <el-icon><CircleClose /></el-icon>
              <span>黑名单管理</span>
            </el-menu-item>
            <el-menu-item index="/agents" v-if="['admin', 'agent'].includes(user?.role)">
              <el-icon><Avatar /></el-icon>
              <span>代理管理</span>
            </el-menu-item>
          </el-sub-menu>

          <!-- 系统管理 -->
          <el-sub-menu index="system" v-if="isPureAdmin">
            <template #title>
              <el-icon><Setting /></el-icon>
              <span>系统管理</span>
            </template>
            <el-menu-item index="/users">
              <el-icon><UserFilled /></el-icon>
              <span>用户管理</span>
            </el-menu-item>
            <el-menu-item index="/payment-config">
              <el-icon><CreditCard /></el-icon>
              <span>支付配置</span>
            </el-menu-item>
            <el-menu-item index="/system">
              <el-icon><Setting /></el-icon>
              <span>系统设置</span>
            </el-menu-item>
            <el-menu-item index="/logs">
              <el-icon><Notebook /></el-icon>
              <span>操作日志</span>
            </el-menu-item>
          </el-sub-menu>
        </el-menu>
        </div>
      </el-scrollbar>

      <!-- 侧边栏底部主题切换 -->
      <div class="sidebar-footer" v-if="!isCollapse || isMobile">
        <div class="theme-label"><el-icon><Brush /></el-icon><span>主题</span></div>
        <div class="theme-dots">
          <button
            v-for="t in themes"
            :key="t.key"
            class="theme-dot"
            :class="{ active: currentTheme === t.key }"
            :style="{ background: t.color }"
            :title="t.label"
            @click="setTheme(t.key)"
          />
        </div>
      </div>
      <div v-else class="sidebar-footer-collapse">
        <el-dropdown placement="right" trigger="click" @command="setTheme">
          <button class="theme-dot-mini" :style="{ background: currentThemeColor }" :title="currentThemeLabel" />
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item v-for="t in themes" :key="t.key" :command="t.key">
                <span class="theme-dot-option" :style="{ background: t.color }"></span>{{ t.label }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </el-aside>

    <!-- ========== 右侧主区域 ========== -->
    <el-container class="right-container">
      <!-- 顶栏 -->
      <el-header class="header">
        <div class="header-left">
          <!-- 移动端汉堡按钮 -->
          <div class="hamburger-btn" @click="toggleSidebar">
            <el-icon :size="20"><Operation /></el-icon>
          </div>
          <!-- 桌面端折叠按钮 -->
          <div class="collapse-btn" @click="isCollapse = !isCollapse">
            <el-icon :size="18"><Fold v-if="!isCollapse" /><Expand v-else /></el-icon>
          </div>
          <el-breadcrumb separator="/" class="header-breadcrumb">
            <el-breadcrumb-item :to="{ path: '/' }">
              <el-icon><HomeFilled /></el-icon>
            </el-breadcrumb-item>
            <el-breadcrumb-item v-for="(item, idx) in breadcrumbs" :key="idx" :to="idx < breadcrumbs.length - 1 ? item.path : undefined">
              {{ item.title }}
            </el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-dropdown trigger="click" @command="setPrimaryTheme" placement="bottom">
            <div class="theme-picker" :title="`界面主题：${currentPrimaryLabel}`">
              <el-icon :size="18" :style="{ color: currentPrimaryColor }"><BrushFilled /></el-icon>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item v-for="t in primaryThemes" :key="t.key" :command="t.key" :class="{ active: currentPrimaryTheme === t.key }">
                  <span class="primary-dot" :style="{ background: t.color }"></span>{{ t.label }}
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
          <el-tag size="small" effect="plain" type="info" round class="role-tag">{{ roleLabel }}</el-tag>
          <el-dropdown @command="handleCommand" trigger="click">
            <span class="user-info">
              <el-avatar :size="32" :src="user?.avatar">
                <el-icon :size="18"><UserFilled /></el-icon>
              </el-avatar>
              <span class="user-name">{{ user?.nickname || user?.username }}</span>
              <el-icon class="user-arrow"><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <el-icon><User /></el-icon>个人中心
                </el-dropdown-item>
                <el-dropdown-item command="changePwd">
                  <el-icon><Lock /></el-icon>修改密码
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided>
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <!-- 页面内容 -->
      <el-main class="main-content">
        <router-view v-slot="{ Component }">
          <transition name="page-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
  </el-container>

  <!-- 修改密码弹窗 -->
  <el-dialog v-model="pwdDialogVisible" title="修改密码" width="440px" :close-on-click-modal="false" class="pwd-dialog">
    <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="80px" @submit.prevent>
      <el-form-item label="原密码" prop="old_password">
        <el-input v-model="pwdForm.old_password" type="password" show-password placeholder="请输入原密码" prefix-icon="Lock" />
      </el-form-item>
      <el-form-item label="新密码" prop="new_password">
        <el-input v-model="pwdForm.new_password" type="password" show-password placeholder="请输入新密码（至少6位）" prefix-icon="Key" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="pwdDialogVisible = false">取消</el-button>
      <el-button type="primary" :loading="pwdLoading" @click="handleChangePwd">确认修改</el-button>
    </template>
  </el-dialog>

  <!-- 个人中心弹窗 -->
  <el-dialog v-model="profileDialogVisible" title="个人中心" width="480px" :close-on-click-modal="false" class="profile-dialog">
    <div class="profile-avatar-section">
      <el-upload
        class="avatar-uploader"
        action="/api/auth/avatar"
        name="avatar"
        :headers="uploadHeaders"
        :show-file-list="false"
        :before-upload="beforeAvatarUpload"
        :on-success="handleAvatarSuccess"
        :on-error="handleAvatarError"
        accept="image/jpeg,image/png,image/gif,image/webp"
      >
        <el-avatar :size="80" :src="profileForm.avatar" class="profile-avatar">
          <el-icon :size="36"><UserFilled /></el-icon>
        </el-avatar>
        <div class="avatar-overlay">
          <el-icon :size="20"><Camera /></el-icon>
          <span>更换头像</span>
        </div>
      </el-upload>
      <p class="avatar-tip">支持 JPG/PNG/GIF/WebP，大小不超过 2MB</p>
    </div>
    <el-form ref="profileFormRef" :model="profileForm" :rules="profileRules" label-width="80px" @submit.prevent>
      <el-form-item label="用户名">
        <el-input :model-value="user?.username" disabled />
      </el-form-item>
      <el-form-item label="昵称" prop="nickname">
        <el-input v-model="profileForm.nickname" placeholder="请输入昵称" maxlength="50" show-word-limit />
      </el-form-item>
      <el-form-item label="邮箱" prop="email">
        <el-input v-model="profileForm.email" placeholder="请输入邮箱" maxlength="100" />
      </el-form-item>
      <el-form-item label="手机号" prop="phone">
        <el-input v-model="profileForm.phone" placeholder="请输入手机号" maxlength="20" />
      </el-form-item>
      <el-form-item label="角色">
        <el-tag size="small" effect="plain">{{ roleLabel }}</el-tag>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="profileDialogVisible = false">取消</el-button>
      <el-button type="primary" :loading="profileLoading" @click="handleSaveProfile">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'
import request from '@/api'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const user = computed(() => userStore.user)
const isCollapse = ref(false)
const activeMenu = computed(() => route.path)
const isAdmin = computed(() => ['admin', 'project_admin'].includes(user.value?.role))
const isAgent = computed(() => user.value?.role === 'agent')
const isPureAdmin = computed(() => user.value?.role === 'admin')
const canManage = computed(() => isAdmin.value)

// ==================== 移动端适配 ====================
const MOBILE_BREAKPOINT = 768
const isMobile = ref(false)
const sidebarVisible = ref(false)

function checkMobile() {
  isMobile.value = window.innerWidth < MOBILE_BREAKPOINT
  if (isMobile.value) {
    isCollapse.value = false
    sidebarVisible.value = false
  }
}

function toggleSidebar() {
  if (isMobile.value) {
    sidebarVisible.value = !sidebarVisible.value
  } else {
    isCollapse.value = !isCollapse.value
  }
}

function closeSidebar() {
  sidebarVisible.value = false
}

function onMenuSelect() {
  if (isMobile.value) {
    sidebarVisible.value = false
  }
}

function goDashboard() {
  closeSidebar()
  router.push('/dashboard')
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

const roleLabel = computed(() => {
  const map = { admin: '超级管理员', project_admin: '项目管理员', agent: '代理' }
  return map[user.value?.role] || user.value?.role
})

// ==================== 菜单滑动指示器 ====================
const menuWrapperRef = ref(null)
const menuSliderStyle = ref({ top: '0px', opacity: 0 })

function updateMenuSlider() {
  nextTick(() => {
    const wrapper = menuWrapperRef.value
    if (!wrapper) return
    const activeItem = wrapper.querySelector('.el-menu-item.is-active')
    if (!activeItem) { menuSliderStyle.value = { top: '0px', opacity: 0 }; return }
    const wrapperRect = wrapper.getBoundingClientRect()
    const itemRect = activeItem.getBoundingClientRect()
    menuSliderStyle.value = {
      top: `${itemRect.top - wrapperRect.top}px`,
      height: `${itemRect.height}px`,
      opacity: 1
    }
  })
}

watch(() => route.path, updateMenuSlider)
watch(isCollapse, () => { setTimeout(updateMenuSlider, 350) })

// ==================== 主题系统 ====================
const STORAGE_THEME_KEY = 'cardauth_sidebar_theme'

const themes = [
  { key: 'dark-blue', label: '深海蓝', color: '#1a1a2e' },
  { key: 'dark', label: '暗夜黑', color: '#141414' },
  { key: 'light', label: '简约白', color: '#ffffff' },
  { key: 'purple', label: '罗兰紫', color: '#2d1b4e' },
  { key: 'green', label: '森林绿', color: '#0d2f23' },
]

const currentTheme = ref('dark-blue')

const currentThemeColor = computed(() => themes.find(t => t.key === currentTheme.value)?.color || '#1a1a2e')
const currentThemeLabel = computed(() => themes.find(t => t.key === currentTheme.value)?.label || '深海蓝')

function setTheme(key) {
  currentTheme.value = key
  localStorage.setItem(STORAGE_THEME_KEY, key)
}

onMounted(() => {
  const saved = localStorage.getItem(STORAGE_THEME_KEY)
  if (saved && themes.some(t => t.key === saved)) {
    currentTheme.value = saved
  }
  updateMenuSlider()
})

// ==================== 界面主题色系统 ====================
const STORAGE_PRIMARY_THEME_KEY = 'cardauth_primary_theme'

const primaryThemes = [
  { key: 'indigo', label: '靛蓝', color: '#6366f1' },
  { key: 'blue', label: '天蓝', color: '#0ea5e9' },
  { key: 'rose', label: '玫瑰', color: '#f43f5e' },
  { key: 'amber', label: '琥珀', color: '#f59e0b' },
  { key: 'emerald', label: '翠绿', color: '#10b981' },
]

const currentPrimaryTheme = ref('indigo')
const currentPrimaryColor = computed(() => primaryThemes.find(t => t.key === currentPrimaryTheme.value)?.color || '#6366f1')
const currentPrimaryLabel = computed(() => primaryThemes.find(t => t.key === currentPrimaryTheme.value)?.label || '靛蓝')

function applyPrimaryTheme(key) {
  const classList = document.body.classList
  primaryThemes.forEach(t => classList.remove(`theme-primary-${t.key}`))
  classList.add(`theme-primary-${key}`)
}

function setPrimaryTheme(key) {
  currentPrimaryTheme.value = key
  localStorage.setItem(STORAGE_PRIMARY_THEME_KEY, key)
  applyPrimaryTheme(key)
}

onMounted(() => {
  const saved = localStorage.getItem(STORAGE_PRIMARY_THEME_KEY)
  if (saved && primaryThemes.some(t => t.key === saved)) {
    currentPrimaryTheme.value = saved
  }
  applyPrimaryTheme(currentPrimaryTheme.value)
})

// ==================== 面包屑 ====================
const parentMap = {
  '/dashboard': [],
  '/projects': [{ path: '/dashboard', title: '业务管理' }],
  '/products': [{ path: '/dashboard', title: '业务管理' }],
  '/cards': [{ path: '/dashboard', title: '业务管理' }],
  '/authorizations': [{ path: '/dashboard', title: '业务管理' }],
  '/orders': [{ path: '/dashboard', title: '订单与代理' }],
  '/coupons': [{ path: '/dashboard', title: '订单与代理' }],
  '/blacklist': [{ path: '/dashboard', title: '订单与代理' }],
  '/agents': [{ path: '/dashboard', title: '订单与代理' }],
  '/users': [{ path: '/dashboard', title: '系统管理' }],
  '/payment-config': [{ path: '/dashboard', title: '系统管理' }],
  '/system': [{ path: '/dashboard', title: '系统管理' }],
  '/logs': [{ path: '/dashboard', title: '系统管理' }],
}
const breadcrumbs = computed(() => {
  const parents = parentMap[route.path] || []
  return [...parents, { title: route.meta.title }]
})

// ==================== 修改密码 ====================
const pwdDialogVisible = ref(false)
const pwdLoading = ref(false)
const pwdFormRef = ref(null)
const pwdForm = ref({ old_password: '', new_password: '' })
const pwdRules = {
  old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  new_password: [{ required: true, min: 6, message: '新密码至少6位', trigger: 'blur' }]
}

function handleCommand(cmd) {
  if (cmd === 'logout') {
    const role = user.value?.role
    userStore.logout()
    router.push(role === 'agent' ? '/agent/login' : '/login')
  } else if (cmd === 'changePwd') {
    pwdForm.value = { old_password: '', new_password: '' }
    pwdDialogVisible.value = true
  } else if (cmd === 'profile') {
    profileForm.value = {
      nickname: user.value?.nickname || '',
      email: user.value?.email || '',
      phone: user.value?.phone || '',
      avatar: user.value?.avatar || ''
    }
    profileDialogVisible.value = true
  }
}

async function handleChangePwd() {
  if (!pwdFormRef.value) return
  await pwdFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdLoading.value = true
    try {
      await request.post('/auth/change-password', pwdForm.value)
      ElMessage.success('密码修改成功，请重新登录')
      pwdDialogVisible.value = false
      const role = user.value?.role
      userStore.logout()
      router.push(role === 'agent' ? '/agent/login' : '/login')
    } catch (e) { /* handled */ }
    finally { pwdLoading.value = false }
  })
}

// ==================== 个人中心 ====================
const profileDialogVisible = ref(false)
const profileLoading = ref(false)
const profileFormRef = ref(null)
const profileForm = ref({ nickname: '', email: '', phone: '', avatar: '' })
const profileRules = {
  nickname: [{ max: 50, message: '昵称最长50个字符', trigger: 'blur' }],
  email: [{ type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }],
  phone: [{ max: 20, message: '手机号最长20位', trigger: 'blur' }]
}

const uploadHeaders = computed(() => ({
  Authorization: `Bearer ${userStore.token}`
}))

function beforeAvatarUpload(file) {
  const isImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)
  if (!isImage) {
    ElMessage.error('仅支持 JPG/PNG/GIF/WebP 格式的图片')
    return false
  }
  const isLt2M = file.size / 1024 / 1024 < 2
  if (!isLt2M) {
    ElMessage.error('图片大小不能超过 2MB')
    return false
  }
  return true
}

function handleAvatarSuccess(res) {
  if (res.code === 200) {
    const avatarUrl = res.data.avatar + '?t=' + Date.now()
    profileForm.value.avatar = avatarUrl
    // 从后端重新拉取用户信息，确保头像 URL 同步
    userStore.fetchUser().then(() => {
      ElMessage.success('头像上传成功')
    }).catch(() => {
      ElMessage.success('头像上传成功')
    })
  } else {
    ElMessage.error(res.message || '头像上传失败')
  }
}

function handleAvatarError(err) {
  ElMessage.error(err?.message || '头像上传失败，请重试')
}

async function handleSaveProfile() {
  if (!profileFormRef.value) return
  await profileFormRef.value.validate(async (valid) => {
    if (!valid) return
    profileLoading.value = true
    try {
      await request.put('/auth/profile', {
        nickname: profileForm.value.nickname,
        email: profileForm.value.email,
        phone: profileForm.value.phone
      })
      // 更新本地用户信息
      userStore.user.nickname = profileForm.value.nickname
      userStore.user.email = profileForm.value.email
      userStore.user.phone = profileForm.value.phone
      ElMessage.success('个人资料更新成功')
      profileDialogVisible.value = false
    } catch (e) { /* handled */ }
    finally { profileLoading.value = false }
  })
}

userStore.fetchUser().catch(() => {})
</script>

<style scoped>
/* ========== CSS 变量：侧边栏主题系统 ========== */
:root {
  /* 默认：深海蓝 */
  --sidebar-bg: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  --sidebar-bg-solid: #1a1a2e;
  --sidebar-text-color: rgba(255, 255, 255, 0.75);
  --sidebar-text-hover: #fff;
  --sidebar-item-hover-bg: rgba(255, 255, 255, 0.1);
  --sidebar-item-active-bg: linear-gradient(135deg, #409EFF 0%, #3a8ee6 100%);
  --sidebar-item-active-text: #fff;
  --sidebar-item-active-shadow: 0 4px 14px rgba(64, 158, 255, 0.4);
  --sidebar-submenu-bg: rgba(0, 0, 0, 0.18);
  --sidebar-icon-color: rgba(255, 255, 255, 0.85);
  --sidebar-arrow-color: rgba(255, 255, 255, 0.45);
  --sidebar-border-color: rgba(255, 255, 255, 0.08);
  --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.2);
  --sidebar-scrollbar-track: transparent;
  --logo-text-color: #fff;
}

/* 暗夜黑 */
.sidebar.theme-dark {
  --sidebar-bg: linear-gradient(180deg, #141414 0%, #1a1a1a 50%, #232323 100%);
  --sidebar-bg-solid: #141414;
  --sidebar-text-color: rgba(255, 255, 255, 0.7);
  --sidebar-text-hover: #fff;
  --sidebar-item-hover-bg: rgba(255, 255, 255, 0.08);
  --sidebar-item-active-bg: linear-gradient(135deg, #5b5b5b 0%, #3d3d3d 100%);
  --sidebar-item-active-text: #fff;
  --sidebar-item-active-shadow: 0 4px 14px rgba(255, 255, 255, 0.12);
  --sidebar-submenu-bg: rgba(0, 0, 0, 0.25);
  --sidebar-icon-color: rgba(255, 255, 255, 0.8);
  --sidebar-arrow-color: rgba(255, 255, 255, 0.4);
  --sidebar-border-color: rgba(255, 255, 255, 0.06);
  --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.15);
}

/* 简约白 */
.sidebar.theme-light {
  --sidebar-bg: #ffffff;
  --sidebar-bg-solid: #ffffff;
  --sidebar-text-color: #606266;
  --sidebar-text-hover: #303133;
  --sidebar-item-hover-bg: #f5f7fa;
  --sidebar-item-active-bg: linear-gradient(135deg, #ecf5ff 0%, #d9ecff 100%);
  --sidebar-item-active-text: #409EFF;
  --sidebar-item-active-shadow: 0 4px 14px rgba(64, 158, 255, 0.18);
  --sidebar-submenu-bg: #f5f7fa;
  --sidebar-icon-color: #606266;
  --sidebar-arrow-color: #909399;
  --sidebar-border-color: #ebeef5;
  --sidebar-scrollbar-thumb: rgba(0, 0, 0, 0.15);
  --logo-text-color: #303133;
}

/* 罗兰紫 */
.sidebar.theme-purple {
  --sidebar-bg: linear-gradient(180deg, #2d1b4e 0%, #3d235e 50%, #4a2b6e 100%);
  --sidebar-bg-solid: #2d1b4e;
  --sidebar-text-color: rgba(255, 255, 255, 0.75);
  --sidebar-text-hover: #fff;
  --sidebar-item-hover-bg: rgba(255, 255, 255, 0.1);
  --sidebar-item-active-bg: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
  --sidebar-item-active-text: #fff;
  --sidebar-item-active-shadow: 0 4px 14px rgba(155, 89, 182, 0.4);
  --sidebar-submenu-bg: rgba(0, 0, 0, 0.18);
  --sidebar-icon-color: rgba(255, 255, 255, 0.85);
  --sidebar-arrow-color: rgba(255, 255, 255, 0.45);
  --sidebar-border-color: rgba(255, 255, 255, 0.08);
  --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.2);
}

/* 森林绿 */
.sidebar.theme-green {
  --sidebar-bg: linear-gradient(180deg, #0d2f23 0%, #144d36 50%, #1b6b49 100%);
  --sidebar-bg-solid: #0d2f23;
  --sidebar-text-color: rgba(255, 255, 255, 0.75);
  --sidebar-text-hover: #fff;
  --sidebar-item-hover-bg: rgba(255, 255, 255, 0.1);
  --sidebar-item-active-bg: linear-gradient(135deg, #27ae60 0%, #219150 100%);
  --sidebar-item-active-text: #fff;
  --sidebar-item-active-shadow: 0 4px 14px rgba(39, 174, 96, 0.4);
  --sidebar-submenu-bg: rgba(0, 0, 0, 0.18);
  --sidebar-icon-color: rgba(255, 255, 255, 0.85);
  --sidebar-arrow-color: rgba(255, 255, 255, 0.45);
  --sidebar-border-color: rgba(255, 255, 255, 0.08);
  --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.2);
}

/* ========== 布局 ========== */
.main-layout {
  height: 100vh;
  overflow: hidden;
}
.right-container {
  flex-direction: column;
}

/* ========== 侧边栏 ========== */
.sidebar {
  background: var(--sidebar-bg);
  background-color: var(--sidebar-bg-solid);
  overflow: hidden;
  transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--sidebar-border-color);
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08);
}

/* Logo 区域 */
.logo {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  border-bottom: 1px solid var(--sidebar-border-color);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s ease;
  position: relative;
}
.logo:hover {
  background: rgba(255, 255, 255, 0.04);
}
.logo-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #409EFF 0%, #3a8ee6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(64, 158, 255, 0.35);
}
.logo-text {
  font-size: 20px;
  font-weight: 800;
  color: var(--logo-text-color);
  letter-spacing: 1px;
  white-space: nowrap;
  background: linear-gradient(135deg, #fff 0%, #c0d6ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.sidebar.theme-light .logo-text {
  background: linear-gradient(135deg, #303133 0%, #606266 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* 滚动条容器 */
.sidebar-scroll {
  flex: 1;
  overflow: hidden;
}
.sidebar-scroll :deep(.el-scrollbar__bar.is-vertical) {
  width: 5px;
}
.sidebar-scroll :deep(.el-scrollbar__thumb) {
  background: var(--sidebar-scrollbar-thumb);
  border-radius: 3px;
}

/* 菜单 */
.side-menu {
  border-right: none !important;
  padding: 10px 0 20px;
  background: transparent;
}

/* 菜单滑动指示器容器 */
.menu-wrapper {
  position: relative;
  height: 100%;
}

/* 菜单滑动指示器 */
.menu-slider {
  position: absolute;
  left: 10px;
  width: 4px;
  border-radius: 2px;
  background: linear-gradient(180deg, #409EFF, #67c2ff);
  box-shadow: 0 0 10px rgba(64, 158, 255, 0.5);
  transition: top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1),
              height 0.35s cubic-bezier(0.34, 1.56, 0.64, 1),
              opacity 0.3s ease;
  pointer-events: none;
  z-index: 5;
}

/* 菜单项通用 */
.side-menu :deep(.el-menu-item),
.side-menu :deep(.el-sub-menu__title) {
  height: 46px;
  line-height: 46px;
  margin: 4px 10px;
  padding: 0 14px !important;
  border-radius: 10px;
  color: var(--sidebar-text-color);
  transition: color 0.25s ease, background-color 0.3s ease, transform 0.25s ease;
  font-size: 14px;
  position: relative;
}
.side-menu :deep(.el-menu-item .el-icon),
.side-menu :deep(.el-sub-menu__title .el-icon) {
  color: var(--sidebar-icon-color);
  font-size: 18px;
  margin-right: 10px;
  transition: color 0.25s ease, transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.side-menu :deep(.el-menu-item span),
.side-menu :deep(.el-sub-menu__title span) {
  transition: color 0.25s ease, transform 0.25s ease;
}

/* 悬停状态 */
.side-menu :deep(.el-menu-item:hover),
.side-menu :deep(.el-sub-menu__title:hover) {
  background-color: var(--sidebar-item-hover-bg) !important;
  color: var(--sidebar-text-hover) !important;
  transform: translateX(4px);
}
.side-menu :deep(.el-menu-item:hover .el-icon),
.side-menu :deep(.el-sub-menu__title:hover .el-icon) {
  color: var(--sidebar-text-hover);
  transform: translateX(2px);
}

/* 选中状态 */
.side-menu :deep(.el-menu-item.is-active) {
  background: var(--sidebar-item-active-bg) !important;
  color: var(--sidebar-item-active-text) !important;
  font-weight: 600;
  box-shadow: var(--sidebar-item-active-shadow);
  transform: translateX(4px);
}
.side-menu :deep(.el-menu-item.is-active .el-icon) {
  color: var(--sidebar-item-active-text);
  transform: scale(1.15);
}
.side-menu :deep(.el-menu-item.is-active span) {
  letter-spacing: 0.3px;
}

/* 子菜单 */
.side-menu :deep(.el-sub-menu .el-menu) {
  background-color: var(--sidebar-submenu-bg) !important;
  border-radius: 10px;
  margin: 0 10px 6px;
  padding: 4px 0;
}
.side-menu :deep(.el-sub-menu .el-menu-item) {
  padding-left: 44px !important;
  height: 40px;
  line-height: 40px;
  font-size: 13px;
  margin: 2px 6px;
  transition: color 0.25s ease, background-color 0.3s ease, transform 0.3s ease, opacity 0.3s ease;
}
.side-menu :deep(.el-sub-menu .el-menu-item:hover) {
  transform: translateX(3px);
}
.side-menu :deep(.el-sub-menu .el-menu-item.is-active) {
  transform: translateX(3px);
}
.side-menu :deep(.el-sub-menu .el-menu-item .el-icon) {
  font-size: 15px;
  margin-right: 8px;
  transition: color 0.25s ease, transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.side-menu :deep(.el-sub-menu .el-menu-item.is-active .el-icon) {
  transform: scale(1.12);
}

/* 子菜单展开动画 */
.side-menu :deep(.el-sub-menu .el-menu) {
  animation: submenu-in 0.35s ease both;
}
@keyframes submenu-in {
  0% { opacity: 0; transform: translateY(-8px); }
  100% { opacity: 1; transform: translateY(0); }
}

/* 展开的父菜单标题 */
.side-menu :deep(.el-sub-menu.is-opened > .el-sub-menu__title) {
  color: var(--sidebar-text-hover) !important;
  font-weight: 500;
}
.side-menu :deep(.el-sub-menu.is-opened > .el-sub-menu__title > .el-icon) {
  color: var(--sidebar-text-hover);
}

/* 箭头 */
.side-menu :deep(.el-sub-menu__icon-arrow) {
  color: var(--sidebar-arrow-color);
  font-size: 12px;
  transition: transform 0.3s ease, color 0.25s ease;
}
.side-menu :deep(.el-sub-menu.is-opened > .el-sub-menu__title .el-sub-menu__icon-arrow) {
  transform: rotate(180deg);
  color: var(--sidebar-text-hover);
}

/* 折叠状态适配 */
.sidebar[aria-expanded] .side-menu :deep(.el-menu-item),
.sidebar[aria-expanded] .side-menu :deep(.el-sub-menu__title) {
  padding: 0 !important;
  justify-content: center;
}

/* 侧边栏底部主题切换 */
.sidebar-footer {
  padding: 14px 16px;
  border-top: 1px solid var(--sidebar-border-color);
  flex-shrink: 0;
  transition: opacity 0.3s ease;
}
.theme-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--sidebar-text-color);
  margin-bottom: 10px;
  opacity: 0.8;
}
.theme-dots {
  display: flex;
  gap: 10px;
  justify-content: space-between;
}
.theme-dot {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.2s ease, border-color 0.2s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.theme-dot:hover {
  transform: scale(1.15);
}
.theme-dot.active {
  border-color: #fff;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.4), 0 2px 6px rgba(0, 0, 0, 0.2);
}
.sidebar.theme-light .theme-dot.active {
  border-color: #409EFF;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.3), 0 2px 6px rgba(0, 0, 0, 0.1);
}

.sidebar-footer-collapse {
  padding: 12px 0;
  border-top: 1px solid var(--sidebar-border-color);
  display: flex;
  justify-content: center;
  flex-shrink: 0;
}
.theme-dot-mini {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.theme-dot-option {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 8px;
  vertical-align: middle;
}

/* ========== 顶栏 ========== */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
  padding: 0 24px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  flex-shrink: 0;
  z-index: 10;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.collapse-btn {
  cursor: pointer;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #606266;
  transition: all 0.25s ease;
}
.collapse-btn:hover {
  background: #f0f2f5;
  color: #409EFF;
  transform: scale(1.05);
}
.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}
.role-tag {
  font-weight: 500;
}
.theme-picker {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background: #f5f7fa;
  transition: all 0.25s ease;
}
.theme-picker:hover {
  background: #e4e7ed;
  transform: scale(1.05);
}
.primary-dot {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 8px;
  vertical-align: middle;
  box-shadow: 0 0 0 2px transparent;
  transition: box-shadow 0.2s ease;
}
:deep(.el-dropdown__item.active) .primary-dot {
  box-shadow: 0 0 0 2px #fff, 0 0 0 4px currentColor;
}
.user-info {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 8px;
  transition: background 0.2s;
}
.user-info:hover {
  background: #f5f7fa;
}
.user-name {
  font-size: 14px;
  color: #333;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ========== 内容区 ========== */
.main-content {
  background: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

/* ========== 过渡动画 ========== */
.logo-fade-enter-active, .logo-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.logo-fade-enter-from, .logo-fade-leave-to {
  opacity: 0;
  transform: translateX(-8px);
}

.page-fade-enter-active, .page-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.page-fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ========== 个人中心弹窗 ========== */
.profile-avatar-section {
  text-align: center;
  margin-bottom: 24px;
}
.avatar-uploader {
  display: inline-block;
  position: relative;
  cursor: pointer;
}
.profile-avatar {
  border: 3px solid #e4e7ed;
  transition: border-color 0.3s;
}
.avatar-uploader:hover .profile-avatar {
  border-color: #409EFF;
}
.avatar-overlay {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 12px;
  padding: 4px 12px;
  border-radius: 0 0 37px 37px;
  display: flex;
  align-items: center;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.3s;
  width: 80px;
  justify-content: center;
}
.avatar-uploader:hover .avatar-overlay {
  opacity: 1;
}
.avatar-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 8px;
}

/* ========== 移动端适配 ========== */
@media (max-width: 767px) {
  /* ===== 遮罩层 ===== */
  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 99;
    -webkit-tap-highlight-color: transparent;
  }

  /* ===== 侧边栏覆层模式 ===== */
  .sidebar {
    position: fixed !important;
    top: 0;
    left: -280px;
    bottom: 0;
    z-index: 100;
    height: 100vh !important;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.3) !important;
  }
  .sidebar.mobile-open {
    left: 0;
  }

  /* ===== 汉堡按钮 ===== */
  .hamburger-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    color: #606266;
    transition: all 0.25s ease;
    -webkit-tap-highlight-color: transparent;
  }
  .hamburger-btn:hover {
    background: #f0f2f5;
    color: #409EFF;
  }
  .hamburger-btn:active {
    transform: scale(0.92);
  }

  /* ===== 隐藏桌面折叠按钮 ===== */
  .collapse-btn {
    display: none !important;
  }

  /* ===== 顶栏 ===== */
  .header {
    padding: 0 12px !important;
    height: 50px !important;
  }
  .header-left {
    gap: 8px !important;
  }
  .header-breadcrumb {
    font-size: 12px;
  }
  .header-right {
    gap: 8px !important;
  }
  .header-right .theme-picker {
    display: none;
  }
  .header-right .role-tag {
    display: none;
  }
  .user-name {
    display: none !important;
  }
  .user-info {
    padding: 4px !important;
    gap: 2px !important;
  }
  .user-arrow {
    display: none;
  }

  /* ===== 内容区 ===== */
  .main-content {
    padding: 12px !important;
  }

  /* ===== 弹窗全屏 ===== */
  :deep(.el-dialog) {
    width: 92% !important;
    max-width: 92vw !important;
    margin-top: 8vh !important;
  }
  :deep(.el-dialog__body) {
    padding: 16px !important;
  }
  :deep(.el-dialog__header) {
    padding: 14px 16px 0 !important;
  }
  :deep(.el-dialog__footer) {
    padding: 12px 16px 16px !important;
  }

  /* ===== 表格横向滚动 ===== */
  :deep(.el-table) {
    font-size: 13px;
  }
  :deep(.el-table .cell) {
    padding: 6px 8px !important;
  }

  /* ===== 表单适配 ===== */
  :deep(.el-form-item) {
    margin-bottom: 12px;
  }
  :deep(.el-form-item__label) {
    font-size: 13px;
  }

  /* ===== 分页适配 ===== */
  :deep(.el-pagination) {
    justify-content: center;
    flex-wrap: wrap;
  }
  :deep(.el-pagination .el-pagination__sizes) {
    display: none;
  }
  :deep(.el-pagination .el-pagination__jump) {
    margin-left: 8px;
  }

  /* ===== 按钮组适配 ===== */
  :deep(.toolbar) {
    flex-wrap: wrap;
    gap: 8px;
  }
  :deep(.toolbar-left),
  :deep(.toolbar-right) {
    flex-wrap: wrap;
    gap: 6px;
  }
  :deep(.toolbar .el-button) {
    font-size: 12px;
    padding: 6px 10px;
  }

  /* ===== 遮罩动画 ===== */
  .overlay-fade-enter-active,
  .overlay-fade-leave-active {
    transition: opacity 0.3s ease;
  }
  .overlay-fade-enter-from,
  .overlay-fade-leave-to {
    opacity: 0;
  }
}

/* ===== 平板适配 ===== */
@media (min-width: 768px) and (max-width: 1023px) {
  .hamburger-btn {
    display: none;
  }

  .main-content {
    padding: 16px !important;
  }

  .header-right .role-tag {
    display: none;
  }
  .header-right .theme-picker {
    display: none;
  }

  :deep(.el-dialog) {
    width: 75% !important;
  }

  :deep(.toolbar) {
    flex-wrap: wrap;
    gap: 8px;
  }
  :deep(.toolbar-left),
  :deep(.toolbar-right) {
    flex-wrap: wrap;
    gap: 6px;
  }
}

/* ===== 桌面端汉堡按钮隐藏 ===== */
@media (min-width: 768px) {
  .hamburger-btn {
    display: none;
  }
}
</style>
