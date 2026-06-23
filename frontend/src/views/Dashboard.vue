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
          <div ref="chartRef" :style="{ height: isMobile ? '200px' : '260px' }"></div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useUserStore } from '@/stores/user'
import request from '@/api'
import * as echarts from 'echarts'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const data = ref(null)
const chartRef = ref(null)

const isMobile = ref(window.innerWidth < 768)

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

const iconMap = {
  '项目总数': 'FolderOpened',
  '卡密总数': 'Tickets',
  '总订单': 'Document',
  '总收入': 'Money',
  '今日收入': 'Coin',
  '用户总数': 'UserFilled',
  '已使用卡密': 'CircleCheck',
  '代理总数': 'Avatar',
  '可用额度': 'Wallet',
  '卡密总数': 'Tickets',
  '今日生成': 'TrendCharts',
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
  return cards.map(c => ({ ...c, icon: iconMap[c.label] || 'Odometer' }))
})

const trendSummary = computed(() => {
  const trend = data.value?.order_trend || []
  const orders = trend.reduce((s, t) => s + (t.count || 0), 0)
  const amount = trend.reduce((s, t) => s + (parseFloat(t.amount) || 0), 0)
  return {
    total_orders: orders,
    total_amount: amount.toFixed(2)
  }
})

onMounted(async () => {
  window.addEventListener('resize', handleResize)
  try {
    const res = await request.get('/dashboard')
    data.value = res.data
    if (data.value?.order_trend) {
      renderChart()
    }
  } catch (e) { /* handled */ }
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

function renderChart() {
  if (!chartRef.value) return
  const chart = echarts.init(chartRef.value)
  const trend = data.value.order_trend
  const dates = trend.map(t => t.date)
  const counts = trend.map(t => t.count || 0)
  const amounts = trend.map(t => parseFloat(t.amount) || 0)
  const hasData = amounts.some(v => v > 0)

  chart.setOption({
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'cross', crossStyle: { color: '#999' } },
      backgroundColor: 'rgba(255,255,255,0.96)',
      borderColor: '#e2e8f0',
      borderWidth: 1,
      textStyle: { color: '#333', fontSize: 12 },
      formatter: (params) => {
        const bar = params[0], line = params[1]
        return `
          <div style="font-weight:600;margin-bottom:4px;font-size:13px">${bar.axisValue}</div>
          <div style="display:flex;align-items:center;gap:6px;margin:3px 0">
            <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:${bar.color};flex-shrink:0"></span>
            <span>订单数：<b>${bar.value}</b> 单</span>
          </div>
          <div style="display:flex;align-items:center;gap:6px;margin:3px 0">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${line.color};flex-shrink:0"></span>
            <span>金额：<b>¥${line.value.toFixed(2)}</b></span>
          </div>
        `
      }
    },
    legend: {
      data: ['订单数', '金额'],
      bottom: 0,
      textStyle: { fontSize: 11, color: '#94a3b8' },
      itemWidth: 12,
      itemHeight: 8,
      itemGap: 20
    },
    grid: { left: '3%', right: '4%', top: 8, bottom: 30, containLabel: true },
    xAxis: {
      type: 'category',
      data: dates,
      axisLine: { lineStyle: { color: '#e2e8f0' } },
      axisTick: { show: false },
      axisLabel: { fontSize: 10, color: '#94a3b8' }
    },
    yAxis: [
      {
        type: 'value',
        min: 0,
        name: '单',
        nameTextStyle: { fontSize: 10, color: '#94a3b8', padding: [0, 0, 0, 0] },
        axisLabel: { fontSize: 10, color: '#94a3b8' },
        splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
        axisLine: { show: false },
        axisTick: { show: false }
      },
      {
        type: 'value',
        min: 0,
        name: '元',
        nameTextStyle: { fontSize: 10, color: '#94a3b8', padding: [0, 0, 0, 0] },
        axisLabel: { fontSize: 10, color: '#94a3b8' },
        splitLine: { show: false },
        axisLine: { show: false },
        axisTick: { show: false }
      }
    ],
    series: [
      {
        name: '订单数',
        type: 'bar',
        data: counts,
        barWidth: 16,
        itemStyle: {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: '#6366f1' },
            { offset: 1, color: '#a5b4fc' }
          ]),
          borderRadius: [6, 6, 0, 0],
          borderColor: 'transparent'
        },
        emphasis: {
          itemStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: '#4f46e5' },
              { offset: 1, color: '#818cf8' }
            ])
          }
        }
      },
      {
        name: '金额',
        type: 'line',
        yAxisIndex: 1,
        smooth: hasData,
        data: amounts,
        symbol: 'circle',
        symbolSize: 6,
        showSymbol: false,
        itemStyle: { color: '#f59e0b' },
        lineStyle: { width: 2, color: '#f59e0b' },
        areaStyle: hasData ? {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: 'rgba(245,158,11,0.18)' },
            { offset: 1, color: 'rgba(245,158,11,0.01)' }
          ])
        } : undefined,
        emphasis: {
          focus: 'series',
          itemStyle: { borderWidth: 2, borderColor: '#fff' }
        }
      }
    ]
  })
  window.addEventListener('resize', () => chart.resize())
}
</script>

<style scoped>
.stat-card {
  border-radius: 8px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  margin-bottom: 6px;
  border: 1px solid #f0f0f0;
  overflow: hidden;
}
.stat-card :deep(.el-card__body) {
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
  border-color: #e0e0e0;
}
.stat-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-info {
  flex: 1;
  min-width: 0;
}
.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
}
.stat-label {
  color: #94a3b8;
  font-size: 11px;
  margin-top: 1px;
  white-space: nowrap;
}
.chart-card {
  border-radius: 8px;
}
.chart-card :deep(.el-card__body) {
  padding: 12px 14px;
}
.chart-card :deep(.el-card__header) {
  padding: 10px 14px;
}
.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.chart-header-title {
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}
.chart-header-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: #94a3b8;
}
.chart-header-meta b {
  color: #475569;
}
.chart-meta-divider {
  color: #e2e8f0;
}
</style>