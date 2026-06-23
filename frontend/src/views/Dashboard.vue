<template>
  <div class="dashboard">
    <el-row :gutter="10">
      <el-col v-for="card in statCards" :key="card.label" :xs="12" :sm="12" :md="6">
        <el-card shadow="never" class="stat-card" :style="{ borderLeft: '3px solid ' + card.color }">
          <div class="stat-icon" :style="{ background: card.color + '15', color: card.color }">
            <el-icon :size="13"><component :is="card.icon" /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ card.value }}</div>
            <div class="stat-label">{{ card.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 近7天订单趋势 -->
    <el-row :gutter="10" style="margin-top:12px" v-if="data && data.order_trend">
      <el-col :span="24">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <div class="chart-header">
              <span class="chart-header-title"><el-icon><TrendCharts /></el-icon> 近7天订单趋势</span>
              <div class="chart-header-meta">
                <span class="chart-meta-item">总订单 <b>{{ trendSummary.total_orders }}</b></span>
                <span class="chart-meta-divider">|</span>
                <span class="chart-meta-item">总金额 <b>¥{{ trendSummary.total_amount }}</b></span>
              </div>
            </div>
          </template>
          <div ref="chart7Ref" :style="{ height: isMobile ? '200px' : '260px' }"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 近30天收入 + 授权趋势 -->
    <el-row :gutter="10" style="margin-top:12px" v-if="data">
      <el-col :xs="24" :md="12">
        <el-card shadow="hover" class="chart-card" v-if="data.revenue_trend_30">
          <template #header>
            <div class="chart-header">
              <span class="chart-header-title"><el-icon><Money /></el-icon> 近30天收入趋势</span>
              <span class="chart-header-meta">总收入 <b>¥{{ rev30Total }}</b></span>
            </div>
          </template>
          <div ref="rev30Ref" :style="{ height: isMobile ? '200px' : '240px' }"></div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card shadow="hover" class="chart-card" v-if="data.auth_trend_30">
          <template #header>
            <div class="chart-header">
              <span class="chart-header-title"><el-icon><UserFilled /></el-icon> 近30天新增授权</span>
              <span class="chart-header-meta">总新增 <b>{{ auth30Total }}</b></span>
            </div>
          </template>
          <div ref="auth30Ref" :style="{ height: isMobile ? '200px' : '240px' }"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 套餐销售占比 -->
    <el-row :gutter="10" style="margin-top:12px" v-if="data && data.package_distribution && data.package_distribution.length">
      <el-col :span="24">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <div class="chart-header">
              <span class="chart-header-title"><el-icon><PieChart /></el-icon> 套餐销售占比</span>
              <span class="chart-header-meta">按已支付订单金额</span>
            </div>
          </template>
          <div ref="pieRef" :style="{ height: isMobile ? '220px' : '280px' }"></div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useUserStore } from '@/stores/user'
import request from '@/api'
import * as echarts from 'echarts'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const data = ref(null)
const chart7Ref = ref(null)
const rev30Ref = ref(null)
const auth30Ref = ref(null)
const pieRef = ref(null)

const isMobile = ref(window.innerWidth < 768)
function handleResize() { isMobile.value = window.innerWidth < 768 }

const iconMap = {
  '项目总数': 'FolderOpened', '卡密总数': 'Tickets', '总订单': 'Document',
  '总收入': 'Money', '今日收入': 'Coin', '用户总数': 'UserFilled',
  '已使用卡密': 'CircleCheck', '代理总数': 'Avatar', '可用额度': 'Wallet',
  '今日生成': 'TrendCharts', '即将过期': 'WarningFilled',
}

const statCards = computed(() => {
  if (!data.value) return []
  const d = data.value
  const cards = user.value?.role === 'admin'
    ? [
        { label: '项目总数', value: d.total_projects || 0, color: '#409EFF' },
        { label: '卡密总数', value: d.total_cards || 0, color: '#67C23A' },
        { label: '总订单', value: d.total_orders || 0, color: '#E6A23C' },
        { label: '总收入', value: '¥' + (d.total_revenue || 0).toFixed(2), color: '#F56C6C' },
        { label: '今日收入', value: '¥' + (d.today_revenue || 0).toFixed(2), color: '#409EFF' },
        { label: '用户总数', value: d.total_users || 0, color: '#909399' },
        { label: '已使用卡密', value: d.used_cards || 0, color: '#E6A23C' },
        { label: '代理总数', value: d.total_agents || 0, color: '#67C23A' },
      ]
    : [
        { label: '可用额度', value: '¥' + (d.remain_quota || 0).toFixed(2), color: '#409EFF' },
        { label: '卡密总数', value: d.total_authorized || 0, color: '#67C23A' },
        { label: '今日生成', value: d.today_authorized || 0, color: '#E6A23C' },
      ]
  // 如果有即将过期数据，插入第一行
  if (d.expiring_soon !== undefined && d.expiring_soon > 0) {
    cards.unshift({ label: '即将过期', value: d.expiring_soon + ' 个', color: '#F56C6C' })
  }
  return cards.map(c => ({ ...c, icon: iconMap[c.label] || 'Odometer' }))
})

const trendSummary = computed(() => {
  const trend = data.value?.order_trend || []
  const orders = trend.reduce((s, t) => s + (t.count || 0), 0)
  const amount = trend.reduce((s, t) => s + (parseFloat(t.amount) || 0), 0)
  return { total_orders: orders, total_amount: amount.toFixed(2) }
})
const rev30Total = computed(() => {
  const tr = data.value?.revenue_trend_30 || []
  const total = tr.reduce((s, t) => s + (parseFloat(t.amount) || 0), 0)
  return '¥' + total.toFixed(2)
})
const auth30Total = computed(() => {
  const tr = data.value?.auth_trend_30 || []
  return tr.reduce((s, t) => s + (t.count || 0), 0)
})

let chart7Instance = null, rev30Instance = null, auth30Instance = null, pieInstance = null

onMounted(async () => {
  window.addEventListener('resize', handleResize)
  try {
    const res = await request.get('/dashboard')
    data.value = res.data
    await nextTick()
    if (data.value?.order_trend) render7DayChart()
    if (data.value?.revenue_trend_30) renderRev30Chart()
    if (data.value?.auth_trend_30) renderAuth30Chart()
    if (data.value?.package_distribution) renderPieChart()
  } catch (e) { /* handled */ }
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  chart7Instance?.dispose()
  rev30Instance?.dispose()
  auth30Instance?.dispose()
  pieInstance?.dispose()
})

function makeBarLineChart(domRef, dates, counts, amounts, countName, amountName) {
  const el = domRef.value
  if (!el) return
  const chart = echarts.init(el)
  const hasData = amounts.some(v => v > 0)
  chart.setOption({
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'cross' },
      backgroundColor: 'rgba(255,255,255,0.96)',
      borderColor: '#e2e8f0', borderWidth: 1,
      textStyle: { color: '#333', fontSize: 12 },
    },
    legend: { data: [countName, amountName], bottom: 0, textStyle: { fontSize: 11, color: '#94a3b8' }, itemWidth: 12, itemHeight: 8, itemGap: 20 },
    grid: { left: '3%', right: '4%', top: 8, bottom: 30, containLabel: true },
    xAxis: { type: 'category', data: dates, axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { fontSize: 10, color: '#94a3b8' } },
    yAxis: [
      { type: 'value', min: 0, name: countName, nameTextStyle: { fontSize: 10, color: '#94a3b8' }, axisLabel: { fontSize: 10, color: '#94a3b8' }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } }, axisLine: { show: false }, axisTick: { show: false } },
      { type: 'value', min: 0, name: amountName, nameTextStyle: { fontSize: 10, color: '#94a3b8' }, axisLabel: { fontSize: 10, color: '#94a3b8' }, splitLine: { show: false }, axisLine: { show: false }, axisTick: { show: false } },
    ],
    series: [
      { name: countName, type: 'bar', data: counts, barWidth: 16, itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#6366f1' }, { offset: 1, color: '#a5b4fc' }]), borderRadius: [6, 6, 0, 0] } },
      { name: amountName, type: 'line', yAxisIndex: 1, smooth: hasData, data: amounts, symbol: 'circle', symbolSize: 6, showSymbol: false, itemStyle: { color: '#f59e0b' }, lineStyle: { width: 2, color: '#f59e0b' }, areaStyle: hasData ? { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(245,158,11,0.18)' }, { offset: 1, color: 'rgba(245,158,11,0.01)' }]) } : undefined },
    ]
  })
  return chart
}

function render7DayChart() {
  chart7Instance?.dispose()
  const trend = data.value.order_trend
  chart7Instance = makeBarLineChart(chart7Ref,
    trend.map(t => t.date),
    trend.map(t => t.count || 0),
    trend.map(t => parseFloat(t.amount) || 0),
    '订单数', '金额'
  )
}

function renderRev30Chart() {
  rev30Instance?.dispose()
  const trend = data.value.revenue_trend_30
  const dates = trend.map(t => t.date)
  const amounts = trend.map(t => parseFloat(t.amount) || 0)
  const el = rev30Ref.value
  if (!el) return
  rev30Instance = echarts.init(el)
  rev30Instance.setOption({
    tooltip: { trigger: 'axis', backgroundColor: 'rgba(255,255,255,0.96)', borderColor: '#e2e8f0', borderWidth: 1, textStyle: { color: '#333', fontSize: 12 } },
    grid: { left: '3%', right: '4%', top: 8, bottom: 20, containLabel: true },
    xAxis: { type: 'category', data: dates, axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { fontSize: 10, color: '#94a3b8', rotate: dates.length > 15 ? 45 : 0 } },
    yAxis: { type: 'value', min: 0, axisLabel: { fontSize: 10, color: '#94a3b8' }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } } },
    series: [{
      type: 'bar', data: amounts, barWidth: 12,
      itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: '#22c55e' }, { offset: 1, color: '#86efac' }]), borderRadius: [4, 4, 0, 0] }
    }]
  })
}

function renderAuth30Chart() {
  auth30Instance?.dispose()
  const trend = data.value.auth_trend_30
  const dates = trend.map(t => t.date)
  const counts = trend.map(t => t.count || 0)
  const el = auth30Ref.value
  if (!el) return
  auth30Instance = echarts.init(el)
  auth30Instance.setOption({
    tooltip: { trigger: 'axis', backgroundColor: 'rgba(255,255,255,0.96)', borderColor: '#e2e8f0', borderWidth: 1, textStyle: { color: '#333', fontSize: 12 } },
    grid: { left: '3%', right: '4%', top: 8, bottom: 20, containLabel: true },
    xAxis: { type: 'category', data: dates, axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { fontSize: 10, color: '#94a3b8', rotate: dates.length > 15 ? 45 : 0 } },
    yAxis: { type: 'value', min: 0, axisLabel: { fontSize: 10, color: '#94a3b8' }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } } },
    series: [{
      type: 'line', smooth: true, data: counts, symbol: 'circle', symbolSize: 4,
      itemStyle: { color: '#3b82f6' }, lineStyle: { width: 2, color: '#3b82f6' },
      areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(59,130,246,0.2)' }, { offset: 1, color: 'rgba(59,130,246,0.01)' }]) }
    }]
  })
}

function renderPieChart() {
  pieInstance?.dispose()
  const pieData = data.value.package_distribution
  const el = pieRef.value
  if (!el) return
  pieInstance = echarts.init(el)
  pieInstance.setOption({
    tooltip: { trigger: 'item', backgroundColor: 'rgba(255,255,255,0.96)', borderColor: '#e2e8f0', borderWidth: 1, textStyle: { color: '#333', fontSize: 12 }, formatter: '{b}: ¥{c} ({d}%)' },
    legend: { bottom: 0, textStyle: { fontSize: 11, color: '#94a3b8' }, itemWidth: 10, itemHeight: 10 },
    series: [{
      type: 'pie', radius: ['50%', '75%'], center: ['50%', '45%'], avoidLabelOverlap: false,
      itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 3 },
      label: { show: false },
      emphasis: { label: { show: true, fontSize: 13, fontWeight: 'bold' } },
      data: pieData.map(p => ({ name: p.name, value: parseFloat(p.amount) || 0 })),
      color: ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#6366f1']
    }]
  })
}
</script>

<style scoped>
.stat-card { border-radius:8px; transition:all .25s; margin-bottom:6px; border:1px solid #f0f0f0; overflow:hidden; }
.stat-card :deep(.el-card__body) { padding:10px 12px; display:flex; align-items:center; gap:10px; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.08)!important; border-color:#e0e0e0; }
.stat-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-info { flex:1; min-width:0; }
.stat-value { font-size:18px; font-weight:700; color:#1e293b; line-height:1.2; }
.stat-label { color:#94a3b8; font-size:11px; margin-top:1px; white-space:nowrap; }
.chart-card { border-radius:8px; }
.chart-card :deep(.el-card__body) { padding:12px 14px; }
.chart-card :deep(.el-card__header) { padding:10px 14px; }
.chart-header { display:flex; align-items:center; justify-content:space-between; }
.chart-header-title { font-size:13px; font-weight:600; display:flex; align-items:center; gap:4px; }
.chart-header-meta { display:flex; align-items:center; gap:8px; font-size:11px; color:#94a3b8; }
.chart-header-meta b { color:#475569; }
.chart-meta-divider { color:#e2e8f0; }
</style>
