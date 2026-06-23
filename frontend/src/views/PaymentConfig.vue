<template>
  <div class="page-container">
    <el-card shadow="never" class="config-card">
      <template #header>
        <div class="card-header">
          <span><el-icon><CreditCard /></el-icon> 支付配置</span>
          <el-tag type="warning" effect="plain" size="small">易支付 (EPay) 接口</el-tag>
        </div>
      </template>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" label-position="right" v-loading="loading">
        <el-divider content-position="left">
          <el-icon><Connection /></el-icon> 接口参数
        </el-divider>

        <el-form-item label="支付网关" prop="api_url">
          <el-input v-model="form.api_url" placeholder="易支付网关地址，如 https://pay.example.com">
            <template #prepend>URL</template>
          </el-input>
          <div class="form-tip">易支付平台提供的支付网关地址，通常以 /submit.php 结尾</div>
        </el-form-item>

        <el-form-item label="商户ID" prop="app_id">
          <el-input v-model="form.app_id" placeholder="易支付商户ID (PID)">
            <template #prepend>PID</template>
          </el-input>
        </el-form-item>

        <el-form-item label="商户密钥" prop="app_key">
          <el-input v-model="form.app_key" type="password" show-password placeholder="易支付商户密钥 (KEY)">
            <template #prepend">KEY</template>
          </el-input>
        </el-form-item>

        <el-divider content-position="left">
          <el-icon><Link /></el-icon> 回调地址
        </el-divider>

        <el-form-item label="异步通知地址">
          <el-input v-model="notifyUrl" readonly>
            <template #append>
              <el-button :icon="CopyDocument" @click="copyText(notifyUrl)">复制</el-button>
            </template>
          </el-input>
          <div class="form-tip">将此地址填入易支付后台的「异步通知地址」</div>
        </el-form-item>

        <el-form-item label="同步跳转地址">
          <el-input v-model="returnUrl" readonly>
            <template #append>
              <el-button :icon="CopyDocument" @click="copyText(returnUrl)">复制</el-button>
            </template>
          </el-input>
          <div class="form-tip">支付完成后用户浏览器跳转地址</div>
        </el-form-item>

        <el-divider content-position="left">
          <el-icon><Coin /></el-icon> 支付方式
        </el-divider>

        <el-form-item label="启用支付方式">
          <el-checkbox-group v-model="form.pay_types">
            <el-checkbox label="alipay" border>
              <el-icon color="#1677ff"><BankCard /></el-icon> 支付宝
            </el-checkbox>
            <el-checkbox label="wxpay" border>
              <el-icon color="#07c160"><ChatDotRound /></el-icon> 微信支付
            </el-checkbox>
            <el-checkbox label="qqpay" border>
              <el-icon color="#12b7f5"><Chicken /></el-icon> QQ钱包
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-divider />
        <el-form-item>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit" :icon="Check">
            保存配置
          </el-button>
          <el-button @click="handleTest" :loading="testLoading" :icon="Connection">
            测试连接
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 测试结果弹窗 -->
    <el-dialog v-model="testDialogVisible" title="连接测试结果" width="420px" :close-on-click-modal="false">
      <el-result
        :icon="testResult.success ? 'success' : 'error'"
        :title="testResult.success ? '连接成功' : '连接失败'"
        :sub-title="testResult.message"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api'

const formRef = ref(null)
const loading = ref(false)
const submitLoading = ref(false)
const testLoading = ref(false)
const testDialogVisible = ref(false)
const testResult = reactive({ success: false, message: '' })

const form = ref({
  api_url: '',
  app_id: '',
  app_key: '',
  pay_types: ['alipay', 'wxpay'],
})

const rules = {
  api_url: [{ required: true, message: '请输入支付网关地址', trigger: 'blur' }],
  app_id: [{ required: true, message: '请输入商户ID', trigger: 'blur' }],
  app_key: [{ required: true, message: '请输入商户密钥', trigger: 'blur' }],
}

const notifyUrl = computed(() => {
  return (window.location.origin || 'http://localhost:3000').replace(':3000', ':8080') + '/api/public/payment/notify'
})

const returnUrl = computed(() => {
  return (window.location.origin || 'http://localhost:3000') + '/#/result'
})

onMounted(() => fetchConfig())

async function fetchConfig() {
  loading.value = true
  try {
    const res = await request.get('/system/configs?keys=payment_api_url,payment_app_id,payment_app_key,payment_pay_types')
    const data = res.data || {}
    form.value.api_url = data.payment_api_url || ''
    form.value.app_id = data.payment_app_id || ''
    form.value.app_key = data.payment_app_key || ''
    form.value.pay_types = data.payment_pay_types ? JSON.parse(data.payment_pay_types) : ['alipay', 'wxpay']
  } catch (e) {
    // 使用默认值
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      await request.post('/system/configs', {
        configs: {
          payment_api_url: form.value.api_url,
          payment_app_id: form.value.app_id,
          payment_app_key: form.value.app_key,
          payment_pay_types: JSON.stringify(form.value.pay_types),
        }
      })
      ElMessage.success('支付配置保存成功')
    } catch (e) { /* handled */ }
    finally { submitLoading.value = false }
  })
}

async function handleTest() {
  if (!form.value.api_url || !form.value.app_id || !form.value.app_key) {
    ElMessage.warning('请先填写完整的接口参数')
    return
  }
  testLoading.value = true
  try {
    const res = await request.post('/system/test-payment', {
      api_url: form.value.api_url,
      app_id: form.value.app_id,
      app_key: form.value.app_key,
    })
    testResult.success = res.code === 200
    testResult.message = res.message || '连接测试完成'
    testDialogVisible.value = true
  } catch (e) {
    testResult.success = false
    testResult.message = '连接测试失败，请检查参数'
    testDialogVisible.value = true
  } finally {
    testLoading.value = false
  }
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.info('复制失败，请手动复制')
  })
}
</script>

<style scoped>
.page-container {
  max-width: 800px;
}
.config-card {
  border-radius: 8px;
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
  margin-top: 4px;
  line-height: 1.5;
}
.el-divider {
  margin: 24px 0 20px;
}
.el-divider .el-icon {
  margin-right: 6px;
}
</style>