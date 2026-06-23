<template>
  <div class="login-container">
    <div class="login-bg">
      <div class="bg-circle c1"></div>
      <div class="bg-circle c2"></div>
      <div class="bg-circle c3"></div>
    </div>
    <div class="login-card">
      <div class="login-icon">
        <el-icon :size="48" color="#409EFF"><Key /></el-icon>
      </div>
      <h2 class="login-title">CardAuth</h2>
      <p class="login-subtitle">授权管理系统</p>
      <el-form ref="formRef" :model="form" :rules="rules" size="large" class="login-form">
        <el-form-item prop="username">
          <el-input
            v-model="form.username"
            placeholder="用户名"
            prefix-icon="User"
            clearable
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
            prefix-icon="Lock"
            show-password
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" style="width:100%" size="large" @click="handleLogin">
            登 录
          </el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'

const router = useRouter()
const userStore = useUserStore()
const loading = ref(false)
const formRef = ref(null)
const form = reactive({ username: '', password: '' })
const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

async function handleLogin() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      await userStore.login(form.username, form.password, 'admin')
      ElMessage.success('登录成功')
      router.push('/dashboard')
    } catch (e) {
      // error handled by interceptor
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  position: relative;
  overflow: hidden;
}
.login-bg {
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

.login-card {
  position: relative;
  width: 400px;
  padding: 48px 40px 36px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.25);
  z-index: 1;
}
.login-icon {
  text-align: center;
  margin-bottom: 12px;
}
.login-title {
  text-align: center;
  color: #1a1a2e;
  font-size: 26px;
  font-weight: 700;
  margin-bottom: 4px;
  letter-spacing: 2px;
}
.login-subtitle {
  text-align: center;
  color: #909399;
  font-size: 14px;
  margin-bottom: 32px;
}
.login-form :deep(.el-input__wrapper) {
  border-radius: 8px;
}
</style>