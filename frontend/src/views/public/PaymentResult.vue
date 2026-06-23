<template>
  <div class="result-page">
    <div class="result-card">
      <div class="result-icon" :class="isSuccess ? 'success' : 'fail'">
        <svg v-if="isSuccess" viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="16 10 11 15 8 12"/>
        </svg>
        <svg v-else viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
      </div>
      <h2 class="result-title">{{ completeResult?.is_renew ? '续费成功' : (isSuccess ? '支付成功' : '支付未完�?) }}</h2>
      <p class="result-desc">
        <template v-if="completing">正在确认订单�?/template>
        <template v-else-if="completeResult">{{ completeResult.is_renew ? '授权有效期已延长�?' + (completeResult.expire_time || '永久') : '授权已创建，机器人QQ: ' + (completeResult.bot_qq || '') }}</template>
        <template v-else-if="completeError">{{ completeError }}</template>
        <template v-else-if="isSuccess">感谢您的购买，订单已处理</template>
        <template v-else>如已付款请等待系统确认，或联系客�?/template>
      </p>

      <div v-if="params.out_trade_no" class="result-info">
        <div class="info-row">
          <span class="info-label">订单�?/span>
          <span class="info-value mono">{{ params.out_trade_no }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">交易�?/span>
          <span class="info-value mono">{{ params.trade_no || '-' }}</span>
        </div>
        <div class="info-row" v-if="params.money">
          <span class="info-label">金额</span>
          <span class="info-value">¥{{ params.money }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">状�?/span>
          <span class="info-value" :class="isSuccess ? 'text-green' : 'text-orange'">{{ isSuccess ? '交易成功' : params.trade_status || '处理�? }}</span>
        </div>
      </div>

      <div class="result-actions">
        <a href="#/shop" class="btn-primary">返回购买中心</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const params = computed(() => ({
  trade_status: route.query.trade_status || '',
  out_trade_no: route.query.out_trade_no || '',
  trade_no: route.query.trade_no || '',
  money: route.query.money || '',
  type: route.query.type || '',
  pid: route.query.pid || '',
  sign: route.query.sign || '',
  sign_type: route.query.sign_type || '',
}))

const isSuccess = computed(() => params.value.trade_status === 'TRADE_SUCCESS')
const completing = ref(false)
const completeResult = ref(null)
const completeError = ref('')

async function tryComplete() {
  if (!isSuccess.value || completing.value || completeResult.value) return
  completing.value = true
  completeError.value = ''
  try {
    const { default: request } = await import('@/api')
    const res = await request.post('/public/payment/complete', {
      trade_no: params.value.trade_no,
      out_trade_no: params.value.out_trade_no,
      trade_status: params.value.trade_status,
      money: params.value.money,
      type: params.value.type,
      pid: params.value.pid,
      sign: params.value.sign,
      sign_type: params.value.sign_type,
    })
    completeResult.value = res.data
  } catch (err) {
    completeError.value = err?.message || '订单确认失败'
  } finally {
    completing.value = false
  }
}

onMounted(() => {
  document.title = '支付结果 - CardAuth'
  tryComplete()
})
</script>

<style scoped>
.result-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0f4ff 0%, #e8edf5 100%);
  padding: 24px;
}
.result-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0,0,0,0.08);
  padding: 48px 40px;
  max-width: 440px;
  width: 100%;
  text-align: center;
}
.result-icon {
  width: 88px; height: 88px;
  margin: 0 auto 24px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.result-icon.success { background: #ecfdf5; color: #16a34a; }
.result-icon.fail { background: #fef2f2; color: #ef4444; }
.result-title { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0 0 8px; }
.result-desc { font-size: 14px; color: #6b7280; margin: 0 0 28px; }
.result-info {
  text-align: left; background: #f9fafb;
  border-radius: 10px; padding: 16px 20px;
  margin-bottom: 28px;
}
.info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f3f4f6;
}
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 13px; color: #9ca3af; }
.info-value { font-size: 14px; color: #374151; font-weight: 500; }
.text-green { color: #16a34a !important; }
.text-orange { color: #f97316 !important; }
.result-actions { margin-top: 8px; }
.btn-primary {
  display: inline-block; width: 100%; padding: 14px;
  background: #3b82f6; color: #fff; border: none;
  border-radius: 10px; font-size: 15px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: background 0.2s;
}
.btn-primary:hover { background: #2563eb; }
.mono { font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; }
</style>
