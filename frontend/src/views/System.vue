<template>
  <div class="page-container">
    <el-tabs v-model="activeTab" type="border-card" class="system-tabs">
      <!-- 网站基本信息 -->
      <el-tab-pane name="site" label="网站基本信息">
        <template #label>
          <span><el-icon><OfficeBuilding /></el-icon> 网站基本信息</span>
        </template>
        <el-card shadow="never" class="config-card" v-loading="siteLoading">
          <template #header>
            <div class="card-header">
              <span>站点配置</span>
              <el-tag type="info" effect="plain" size="small">全局生效</el-tag>
            </div>
          </template>
          <el-form ref="siteFormRef" :model="siteForm" :rules="siteRules" label-width="140px" label-position="right">
            <el-form-item label="站点名称" prop="site_name">
              <el-input v-model="siteForm.site_name" placeholder="请输入站点名称" maxlength="100" show-word-limit />
            </el-form-item>
            <el-form-item label="站点Logo" prop="site_logo">
              <el-input v-model="siteForm.site_logo" placeholder="请输入Logo URL 地址" />
            </el-form-item>
            <el-form-item label="站点关键词" prop="site_keywords">
              <el-input v-model="siteForm.site_keywords" placeholder="用于 SEO，多个关键词用逗号分隔" />
            </el-form-item>
            <el-form-item label="站点描述" prop="site_description">
              <el-input v-model="siteForm.site_description" type="textarea" :rows="3" placeholder="请输入站点描述" maxlength="255" show-word-limit />
            </el-form-item>
            <el-form-item label="卡密前缀" prop="card_key_prefix">
              <el-input v-model="siteForm.card_key_prefix" placeholder="如 CA" maxlength="20" />
            </el-form-item>
            <el-form-item label="订单过期时间" prop="order_expire_minutes">
              <el-input-number v-model="siteForm.order_expire_minutes" :min="1" :max="1440" />
              <span class="form-tip">分钟，订单创建后超过该时间未支付将自动关闭</span>
            </el-form-item>
            <el-form-item label="站点备案号" prop="site_icp">
              <el-input v-model="siteForm.site_icp" placeholder="如 京ICP备12345678号" maxlength="100" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="siteSubmitting" @click="handleSaveSite" :icon="Check">保存配置</el-button>
              <el-button @click="fetchSiteConfig" :icon="RefreshRight">重置</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>

      <!-- 管理员个人信息 -->
      <el-tab-pane name="profile" label="管理员个人信息">
        <template #label>
          <span><el-icon><User /></el-icon> 管理员个人信息</span>
        </template>
        <el-card shadow="never" class="config-card" v-loading="profileLoading">
          <template #header>
            <div class="card-header">
              <span>个人资料</span>
              <el-tag type="success" effect="plain" size="small">{{ user?.username }}</el-tag>
            </div>
          </template>
          <el-form ref="profileFormRef" :model="profileForm" :rules="profileRules" label-width="120px" label-position="right">
            <el-form-item label="用户名">
              <el-input v-model="profileForm.username" disabled />
            </el-form-item>
            <el-form-item label="昵称" prop="nickname">
              <el-input v-model="profileForm.nickname" placeholder="请输入昵称" maxlength="50" show-word-limit />
            </el-form-item>
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="profileForm.email" placeholder="请输入邮箱地址" maxlength="100" />
            </el-form-item>
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="profileForm.phone" placeholder="请输入手机号" maxlength="20" />
            </el-form-item>
            <el-form-item label="角色">
              <el-tag>{{ roleText }}</el-tag>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="profileSubmitting" @click="handleSaveProfile" :icon="Check">保存资料</el-button>
              <el-button @click="fetchProfile" :icon="RefreshRight">重置</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { OfficeBuilding, User, Check, RefreshRight } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import request from '@/api'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const activeTab = ref('site')

// 网站基本信息
const siteFormRef = ref(null)
const siteLoading = ref(false)
const siteSubmitting = ref(false)
const siteForm = ref({
  site_name: '',
  site_logo: '',
  site_keywords: '',
  site_description: '',
  card_key_prefix: '',
  order_expire_minutes: 15,
  site_icp: '',
})
const siteRules = {
  site_name: [{ required: true, message: '请输入站点名称', trigger: 'blur' }],
  order_expire_minutes: [{ required: true, message: '请输入订单过期时间', trigger: 'blur' }],
}

// 管理员个人信息
const profileFormRef = ref(null)
const profileLoading = ref(false)
const profileSubmitting = ref(false)
const profileForm = ref({
  username: '',
  nickname: '',
  email: '',
  phone: '',
})
const profileRules = {
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
}

const roleText = computed(() => {
  const map = { admin: '超级管理员', project_admin: '项目管理员', agent: '代理' }
  return map[user.value?.role] || user.value?.role || '-'
})

onMounted(() => {
  fetchSiteConfig()
  fetchProfile()
})

async function fetchSiteConfig() {
  siteLoading.value = true
  try {
    const keys = 'site_name,site_logo,site_keywords,site_description,card_key_prefix,order_expire_minutes,site_icp'
    const res = await request.get(`/system/configs?keys=${keys}`)
    const data = res.data || {}
    siteForm.value.site_name = data.site_name || 'CardAuth授权管理系统'
    siteForm.value.site_logo = data.site_logo || ''
    siteForm.value.site_keywords = data.site_keywords || ''
    siteForm.value.site_description = data.site_description || ''
    siteForm.value.card_key_prefix = data.card_key_prefix || 'CA'
    siteForm.value.order_expire_minutes = parseInt(data.order_expire_minutes || '15', 10)
    siteForm.value.site_icp = data.site_icp || ''
  } catch (e) {
    // 使用默认值
  } finally {
    siteLoading.value = false
  }
}

async function handleSaveSite() {
  if (!siteFormRef.value) return
  await siteFormRef.value.validate(async (valid) => {
    if (!valid) return
    siteSubmitting.value = true
    try {
      await request.post('/system/configs', {
        configs: {
          site_name: siteForm.value.site_name,
          site_logo: siteForm.value.site_logo,
          site_keywords: siteForm.value.site_keywords,
          site_description: siteForm.value.site_description,
          card_key_prefix: siteForm.value.card_key_prefix,
          order_expire_minutes: String(siteForm.value.order_expire_minutes),
          site_icp: siteForm.value.site_icp,
        }
      })
      ElMessage.success('网站基本信息保存成功')
    } catch (e) { /* handled */ }
    finally { siteSubmitting.value = false }
  })
}

async function fetchProfile() {
  profileLoading.value = true
  try {
    await userStore.fetchUser()
    const u = userStore.user || {}
    profileForm.value = {
      username: u.username || '',
      nickname: u.nickname || '',
      email: u.email || '',
      phone: u.phone || '',
    }
  } catch (e) {
    ElMessage.error('获取个人信息失败')
  } finally {
    profileLoading.value = false
  }
}

async function handleSaveProfile() {
  if (!profileFormRef.value) return
  await profileFormRef.value.validate(async (valid) => {
    if (!valid) return
    profileSubmitting.value = true
    try {
      await request.put('/auth/profile', {
        nickname: profileForm.value.nickname,
        email: profileForm.value.email,
        phone: profileForm.value.phone,
      })
      ElMessage.success('个人资料保存成功')
      await userStore.fetchUser()
    } catch (e) { /* handled */ }
    finally { profileSubmitting.value = false }
  })
}
</script>

<style scoped>
.page-container {
  max-width: 900px;
}
.system-tabs :deep(.el-tabs__content) {
  padding: 0;
}
.config-card {
  border-radius: 8px;
  margin-top: -1px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 16px;
  font-weight: 600;
}
.card-header .el-icon {
  margin-right: 6px;
  vertical-align: middle;
}
.form-tip {
  font-size: 12px;
  color: #909399;
  margin-left: 12px;
}
</style>
