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
          <el-icon :size="40" color="#409EFF"><User /></el-icon>
        </div>
        <h2 class="auth-title">代理注册</h2>
        <p class="auth-subtitle">成为 CardAuth 合作伙伴</p>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" size="large" class="auth-form">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="用户名" maxlength="50" clearable>
            <template #prefix>
              <el-icon><User /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="email">
          <el-input v-model="form.email" placeholder="电子邮箱" maxlength="100" clearable>
            <template #prefix>
              <el-icon><Message /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="phone">
          <el-input v-model="form.phone" placeholder="手机号码" maxlength="11" clearable>
            <template #prefix>
              <el-icon><Phone /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="inviteCode">
          <el-input v-model="form.inviteCode" placeholder="邀请码（上级代理用户名，选填）" maxlength="50" clearable>
            <template #prefix>
              <el-icon><Ticket /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="密码" maxlength="50" show-password>
            <template #prefix>
              <el-icon><Lock /></el-icon>
            </template>
          </el-input>
          <div class="password-strength">
            <div class="strength-bar" :class="strengthClass" :style="{ width: strengthWidth }"></div>
          </div>
          <div class="strength-text">密码强度：{{ strengthText }}</div>
        </el-form-item>

        <el-form-item prop="confirmPassword">
          <el-input v-model="form.confirmPassword" type="password" placeholder="确认密码" maxlength="50" show-password>
            <template #prefix>
              <el-icon><Lock /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item prop="agreement">
          <el-checkbox v-model="form.agreement">
            我已阅读并同意 <el-link type="primary">《代理服务协议》</el-link>
          </el-checkbox>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :loading="loading" style="width:100%" size="large" @click="handleRegister">
            注 册
          </el-button>
        </el-form-item>
      </el-form>

      <div class="auth-footer">
        <span>已有账号？</span>
        <router-link to="/agent/login" class="auth-link">立即登录</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Lock, Message, Phone, Ticket } from '@element-plus/icons-vue'
import request from '@/api'

const router = useRouter()
const loading = ref(false)
const formRef = ref(null)

const form = reactive({
  username: '',
  email: '',
  phone: '',
  inviteCode: '',
  password: '',
  confirmPassword: '',
  agreement: false,
})

const validatePhone = (rule, value, callback) => {
  if (!value) {
    callback(new Error('请输入手机号码'))
  } else if (!/^1[3-9]\d{9}$/.test(value)) {
    callback(new Error('手机号格式不正确'))
  } else {
    callback()
  }
}

const validateConfirmPassword = (rule, value, callback) => {
  if (!value) {
    callback(new Error('请再次输入密码'))
  } else if (value !== form.password) {
    callback(new Error('两次输入的密码不一致'))
  } else {
    callback()
  }
}

const validateAgreement = (rule, value, callback) => {
  if (!value) {
    callback(new Error('请同意代理服务协议'))
  } else {
    callback()
  }
}

const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 50, message: '用户名长度3-50位', trigger: 'blur' },
  ],
  email: [
    { required: true, message: '请输入电子邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  phone: [{ required: true, validator: validatePhone, trigger: 'blur' }],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, max: 50, message: '密码长度6-50位', trigger: 'blur' },
  ],
  confirmPassword: [{ required: true, validator: validateConfirmPassword, trigger: 'blur' }],
  agreement: [{ validator: validateAgreement, trigger: 'change' }],
}

const passwordStrength = computed(() => {
  const pwd = form.password
  if (!pwd) return 0
  let score = 0
  if (pwd.length >= 8) score++
  if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++
  if (/\d/.test(pwd)) score++
  if (/[^a-zA-Z0-9]/.test(pwd)) score++
  return score
})

const strengthClass = computed(() => {
  const map = { 0: '', 1: 'weak', 2: 'medium', 3: 'medium', 4: 'strong' }
  return map[passwordStrength.value]
})

const strengthWidth = computed(() => {
  const map = { 0: '0%', 1: '33%', 2: '50%', 3: '75%', 4: '100%' }
  return map[passwordStrength.value]
})

const strengthText = computed(() => {
  const map = { 0: '未输入', 1: '弱', 2: '中', 3: '良', 4: '强' }
  return map[passwordStrength.value]
})

async function handleRegister() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      await request.post('/auth/agent-register', {
        username: form.username,
        password: form.password,
        confirm_password: form.confirmPassword,
        email: form.email,
        phone: form.phone,
        invite_code: form.inviteCode,
      })
      ElMessage.success('注册成功，请登录')
      router.push('/agent/login')
    } catch (e) {
      // error handled by interceptor
    } finally {
      loading.value = false
    }
  })
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
  max-width: 440px;
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
.password-strength {
  height: 4px;
  background: #e4e7ed;
  border-radius: 2px;
  margin-top: 8px;
  overflow: hidden;
}
.strength-bar {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease, background 0.3s ease;
}
.strength-bar.weak { background: #f56c6c; }
.strength-bar.medium { background: #e6a23c; }
.strength-bar.strong { background: #67c23a; }
.strength-text {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
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
}
</style>
