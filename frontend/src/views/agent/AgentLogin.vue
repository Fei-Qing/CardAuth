<template>
  <div class="auth-container">
    <div class="auth-bg">
      <div class="bg-circle c1"></div>
      <div class="bg-circle c2"></div>
      <div class="bg-circle c3"></div>
    </div>
    <div class="auth-card">
      <div class="auth-header">
        <div class="auth-icon">
          <el-icon :size="40" color="#409EFF"><Avatar /></el-icon>
        </div>
        <h2 class="auth-title">代理登录</h2>
        <p class="auth-subtitle">CardAuth 代理合作伙伴中心</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" size="large" class="auth-form">
        <el-form-item prop="account">
          <el-input v-model="form.account" placeholder="用户名 / 邮箱" maxlength="100" clearable>
            <template #prefix>
              <el-icon><User /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
            maxlength="50"
            show-password
            @keyup.enter="handleLogin"
          >
            <template #prefix>
              <el-icon><Lock /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <div class="form-options">
          <el-checkbox v-model="form.remember">记住我</el-checkbox>
          <el-link type="primary" :underline="false" @click="handleForgotPassword">忘记密码？</el-link>
        </div>

        <el-form-item>
          <el-button type="primary" :loading="loading" style="width:100%" size="large" @click="handleLogin">
            登 录
          </el-button>
        </el-form-item>
      </el-form>

      <div class="auth-footer">
        <span>还没有账号？</span>
        <router-link to="/agent/register" class="auth-link">立即注册</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Lock, Avatar } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()
const loading = ref(false)
const formRef = ref(null)

const form = reactive({
  account: '',
  password: '',
  remember: false,
})

const rules = {
  account: [{ required: true, message: '请输入用户名或邮箱', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

onMounted(() => {
  const saved = localStorage.getItem('agent_remember_account')
  if (saved) {
    form.account = saved
    form.remember = true
  }
})

async function handleLogin() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      await userStore.login(form.account, form.password, 'agent')
      if (form.remember) {
        localStorage.setItem('agent_remember_account', form.account)
      } else {
        localStorage.removeItem('agent_remember_account')
      }
      ElMessage.success('登录成功')
      router.push('/dashboard')
    } catch (e) {
      // error handled by interceptor
    } finally {
      loading.value = false
    }
  })
}

function handleForgotPassword() {
  ElMessage.info('请联系管理员重置密码')
}
</script>

<style scoped>
.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  position: relative;
  overflow: hidden;
  padding: 24px;
}
.auth-bg {
  position: absolute;
  inset: 0;
  overflow: hidden;
}
.bg-circle {
  position: absolute;
  border-radius: 50%;
  opacity: 0.06;
  background: #409EFF;
}
.bg-circle.c1 { width: 500px; height: 500px; top: -150px; right: -100px; }
.bg-circle.c2 { width: 300px; height: 300px; bottom: -80px; left: -80px; }
.bg-circle.c3 { width: 200px; height: 200px; top: 40%; left: 60%; }

.auth-card {
  position: relative;
  width: 100%;
  max-width: 420px;
  padding: 40px 36px 32px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.25);
  z-index: 1;
}
.auth-header {
  text-align: center;
  margin-bottom: 28px;
}
.auth-icon {
  margin-bottom: 12px;
}
.auth-title {
  color: #1a1a2e;
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 6px;
}
.auth-subtitle {
  color: #909399;
  font-size: 14px;
}
.auth-form :deep(.el-input__wrapper) {
  border-radius: 8px;
}
.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  font-size: 14px;
}
.auth-footer {
  text-align: center;
  margin-top: 20px;
  color: #606266;
  font-size: 14px;
}
.auth-link {
  color: #409EFF;
  margin-left: 6px;
  text-decoration: none;
  font-weight: 500;
}
.auth-link:hover {
  text-decoration: underline;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 32px 24px 24px;
    border-radius: 12px;
  }
  .auth-title {
    font-size: 22px;
  }
  .form-options {
    flex-direction: column;
    gap: 10px;
    align-items: flex-start;
  }
}
</style>
