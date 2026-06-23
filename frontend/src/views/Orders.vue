<template>
  <div class="orders-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><Document /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总订单数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-paid"><div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">¥{{ paidAmount }}</div><div class="stat-label">已支付金额</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-pending"><div class="stat-icon"><el-icon :size="32"><Timer /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ pendingCount }}</div><div class="stat-label">待支付</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-refunded"><div class="stat-icon"><el-icon :size="32"><CircleClose /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ refundedCount }}</div><div class="stat-label">已退款</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <!-- 高级搜索 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 140px" @change="handleFilterChange">
                <el-option label="待支付" value="pending" />
                <el-option label="已支付" value="paid" />
                <el-option label="已过期" value="expired" />
                <el-option label="已退款" value="refunded" />
              </el-select>
            </el-form-item>
            <el-form-item label="支付方式">
              <el-select v-model="filters.pay_type" placeholder="全部方式" clearable style="width: 130px" @change="handleFilterChange">
                <el-option label="支付宝" value="alipay" />
                <el-option label="微信" value="wxpay" />
                <el-option label="QQ钱包" value="qqpay" />
              </el-select>
            </el-form-item>
            <el-form-item label="关键词">
              <el-input v-model="filters.keyword" placeholder="订单号/交易号/卡密" clearable style="width: 220px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleFilterChange" :icon="Search">搜索</el-button>
              <el-button @click="resetFilters" :icon="Refresh">重置</el-button>
            </el-form-item>
          </el-form>
        </div>
      </transition>

      <!-- 工具栏 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-dropdown v-if="isAdmin" @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="success" :icon="Download">导出 ({{ selectedRows.length }})<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown><el-dropdown-menu><el-dropdown-item command="csv">导出 CSV</el-dropdown-item><el-dropdown-item command="excel">导出 Excel</el-dropdown-item></el-dropdown-menu></template>
          </el-dropdown>
        </div>
        <div class="toolbar-right">
          <el-button @click="showAdvancedSearch = !showAdvancedSearch" :icon="Filter">{{ showAdvancedSearch ? '收起搜索' : '高级搜索' }}</el-button>
          <el-popover placement="bottom" :width="280" trigger="click">
            <template #reference><el-button :icon="Setting">列设置</el-button></template>
            <div class="column-settings"><div class="column-settings-title">显示/隐藏列</div><el-checkbox v-for="col in columnOptions" :key="col.prop" v-model="col.visible" :label="col.label" @change="saveColumnSettings" /></div>
          </el-popover>
          <el-button @click="refreshData" :icon="Refresh" circle />
        </div>
      </div>

      <!-- 表格 -->
      <el-table ref="tableRef" :data="list" v-loading="loading" stripe border highlight-current-row @selection-change="handleSelectionChange" @sort-change="handleSortChange" row-key="id" :max-height="tableMaxHeight" class="data-table">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="order_no" label="订单号" width="180" sortable="custom" v-if="visibleColumns.order_no" />
        <el-table-column prop="project_name" label="项目" width="130" sortable="custom" v-if="visibleColumns.project_name" />
        <el-table-column prop="card_type_name" label="套餐" width="110" sortable="custom" v-if="visibleColumns.card_type_name" />
        <el-table-column prop="amount" label="金额" width="100" sortable="custom" v-if="visibleColumns.amount"><template #default="{ row }"><span class="price-text">¥{{ parseFloat(row.amount||0).toFixed(2) }}</span></template></el-table-column>
        <el-table-column prop="pay_type" label="支付方式" width="100" align="center" v-if="visibleColumns.pay_type"><template #default="{ row }"><el-tag v-if="row.pay_type==='alipay'" type="primary" size="small">支付宝</el-tag><el-tag v-else-if="row.pay_type==='wxpay'" type="success" size="small">微信</el-tag><el-tag v-else-if="row.pay_type==='qqpay'" type="info" size="small">QQ</el-tag><span v-else class="text-muted">-</span></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><span class="status-pill" :class="'status-' + row.status">{{ row.status === 'pending' ? '待支付' : row.status === 'paid' ? '已支付' : row.status === 'expired' ? '已过期' : '已退款' }}</span></template></el-table-column>
        <el-table-column prop="trade_no" label="交易号" width="200" show-overflow-tooltip sortable="custom" v-if="visibleColumns.trade_no" />
        <el-table-column prop="card_key" label="卡密" width="180" show-overflow-tooltip v-if="visibleColumns.card_key"><template #default="{ row }"><span v-if="row.card_key" class="mono-text">{{ row.card_key }}</span><span v-else class="text-muted">-</span></template></el-table-column>
        <el-table-column prop="paid_at" label="支付时间" width="180" sortable="custom" v-if="visibleColumns.paid_at" />
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="140" fixed="right" align="center" v-if="isAdmin">
          <template #default="{ row }">
            <el-button v-if="row.status==='pending'" size="small" type="success" link @click="handleComplete(row)">补单</el-button>
            <el-button size="small" type="primary" link :icon="View" @click="showDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" width="520px" destroy-on-close :show-close="false">
      <template #header="{ close }">
        <div class="detail-header">
          <div class="detail-header-left">
            <div class="detail-avatar" :class="'order-' + detail?.status">
              <svg v-if="detail?.status==='paid'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else-if="detail?.status==='pending'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <svg v-else-if="detail?.status==='expired'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <svg v-else viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div>
              <h4 class="detail-header-title">订单详情 #{{ detail?.id }}</h4>
              <p class="detail-header-sub">{{ detail?.order_no }}</p>
            </div>
          </div>
          <div class="detail-header-right">
            <span class="detail-status-badge" :class="'order-' + detail?.status">{{ statusText }}</span>
            <button class="detail-close-btn" @click="close" type="button" aria-label="关闭"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
        </div>
      </template>
      <div v-if="detail" class="detail-body">
        <div class="detail-row-group">
          <span class="detail-row-label">订单信息</span>
          <div class="detail-row"><span class="detail-row-key">订单号</span><span class="detail-row-val mono">{{ detail.order_no }}</span></div>
          <div class="detail-row"><span class="detail-row-key">交易号</span><span class="detail-row-val mono">{{ detail.trade_no || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">金额</span><span class="detail-row-val price-text">¥{{ parseFloat(detail.amount||0).toFixed(2) }}</span></div>
          <div class="detail-row"><span class="detail-row-key">支付方式</span><span class="detail-row-val">{{ detail.pay_type ? {alipay:'支付宝',wxpay:'微信',qqpay:'QQ钱包'}[detail.pay_type] : '-' }}</span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">项目与套餐</span>
          <div class="detail-row"><span class="detail-row-key">项目</span><span class="detail-row-val">{{ detail.project_name }}</span></div>
          <div class="detail-row"><span class="detail-row-key">套餐</span><span class="detail-row-val">{{ detail.card_type_name }}</span></div>
          <div class="detail-row"><span class="detail-row-key">卡密</span><span class="detail-row-val"><span v-if="detail.card_key" class="mono-text">{{ detail.card_key }}</span><span v-else class="text-muted">-</span></span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">联系方式</span>
          <div class="detail-row" style="border-top:none"><span class="detail-row-val" style="max-width:100%">{{ detail.contact_info || '-' }}</span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">时间</span>
          <div class="detail-row"><span class="detail-row-key">创建时间</span><span class="detail-row-val">{{ detail.created_at }}</span></div>
          <div class="detail-row"><span class="detail-row-key">支付时间</span><span class="detail-row-val">{{ detail.paid_at || '-' }}</span></div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { useUserStore } from '@/stores/user'
import { Download, Search, Refresh, Filter, Setting, View, ArrowDown, Document, CircleCheck, CircleClose, Timer } from '@element-plus/icons-vue'
import request from '@/api'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const isAdmin = computed(() => ['admin', 'project_admin'].includes(user.value?.role))

const showAdvancedSearch = ref(true)
const detailVisible = ref(false)
const detail = ref(null)

const statusText = computed(() => {
  if (!detail.value) return ''
  const map = { pending: '待支付', paid: '已支付', expired: '已过期', refunded: '已退款' }
  return map[detail.value.status] || detail.value.status
})

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/orders',
  defaultFilters: { status: '', pay_type: '', keyword: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('orders_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'order_no', label: '订单号' },
  { prop: 'project_name', label: '项目' },
  { prop: 'card_type_name', label: '套餐' },
  { prop: 'amount', label: '金额' },
  { prop: 'pay_type', label: '支付方式' },
  { prop: 'status', label: '状态' },
  { prop: 'trade_no', label: '交易号' },
  { prop: 'card_key', label: '卡密' },
  { prop: 'paid_at', label: '支付时间' },
  { prop: 'created_at', label: '创建时间' },
])

const paidAmount = computed(() => list.value.filter(i => i.status === 'paid').reduce((sum, i) => sum + parseFloat(i.amount || 0), 0).toFixed(2))
const pendingCount = computed(() => list.value.filter(i => i.status === 'pending').length)
const refundedCount = computed(() => list.value.filter(i => i.status === 'refunded').length)

async function showDetail(row) {
  try {
    const res = await request.get(`/orders/${row.id}`)
    detail.value = res.data
    detailVisible.value = true
  } catch (e) { console.error(e) }
}

async function handleComplete(row) {
  try {
    await ElMessageBox.confirm('确定手动补单？将自动发货卡密。', '提示', { type: 'warning' })
    await request.post(`/orders/${row.id}/complete`)
    ElMessage.success('补单成功')
    fetchData()
  } catch (e) { if (e !== 'cancel') console.error(e) }
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的订单'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/orders/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
</script>

<style scoped>
.orders-page { max-width:1600px; margin:0 auto; }

/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-paid .stat-icon { background:#f0fdf4; color:#22c55e; }
.stat-pending .stat-icon { background:#fef3c7; color:#f59e0b; }
.stat-refunded .stat-icon { background:#fef2f2; color:#ef4444; }

.stat-info { display:flex; flex-direction:column; min-width:0; }
.stat-value { font-size:22px; font-weight:700; color:#1d1d1f; line-height:1.1; letter-spacing:-.3px; }
.stat-label { font-size:12px; color:#9ca3af; margin-top:2px; font-weight:500; }

/* ========== 主卡片 ========== */
.main-card { margin-bottom:20px; border-radius:16px; border:1px solid #f0f0f4; }
.main-card:deep(.el-card__body) { padding:24px; }
.advanced-search { padding:20px 24px; background:#f8f9fb; border-radius:14px; margin-bottom:20px; border:1px solid #f0f0f4; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; padding:0; }
.toolbar-left,.toolbar-right { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.toolbar :deep(.el-button) { border-radius:10px; font-weight:500; }

/* ========== 表格 ========== */
.data-table { border-radius:12px; overflow:hidden; }
.data-table:deep(.el-table__header-wrapper) { border-radius:12px 12px 0 0; }
.data-table:deep(.el-table__header th) { background:#f8f9fb; font-weight:600; color:#374151; font-size:13px; padding:14px 0; border-color:#f0f0f4; }
.data-table:deep(.el-table__body td) { padding:12px 0; font-size:13px; color:#374151; border-color:#f5f5f7; }
.data-table:deep(.el-table__row:hover > td) { background:#f8faff !important; }
.data-table:deep(.el-table__row--striped td) { background:#fcfcfd; }
.data-table:deep(.el-table__row--striped:hover > td) { background:#f8faff !important; }
.price-text { color:#F56C6C; font-weight:700; }
.text-muted { color:#d1d5db; }
.mono-text { font-family:'SF Mono',Cascadia Code,Courier New,monospace; font-size:13px; letter-spacing:.2px; }
.pagination-wrapper { margin-top:20px; display:flex; justify-content:flex-end; }
.column-settings { padding:8px; }
.column-settings-title { font-weight:600; margin-bottom:12px; }
.column-settings .el-checkbox { display:block; margin-bottom:8px; }
.expand-enter-active,.expand-leave-active { transition:all .3s ease; }
.expand-enter-from,.expand-leave-to { opacity:0; max-height:0; overflow:hidden; }
.expand-enter-to,.expand-leave-from { opacity:1; max-height:200px; }

/* ========== 详情弹窗 ========== */
.detail-header { display:flex; align-items:center; justify-content:space-between; padding:4px 0; }
.detail-header-left { display:flex; align-items:center; gap:14px; }
.detail-avatar { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.detail-avatar.order-paid { background:#f0f9eb; color:#67c23a; }
.detail-avatar.order-pending { background:#fdf6ec; color:#e6a23c; }
.detail-avatar.order-expired { background:#f4f4f5; color:#909399; }
.detail-avatar.order-refunded { background:#fef0f0; color:#f56c6c; }
.detail-header-title { font-size:16px; font-weight:700; color:#1d1d1f; margin:0; line-height:1.3; }
.detail-header-sub { font-size:12px; color:#86868b; margin:2px 0 0; font-family:'SF Mono',Cascadia Code,Courier New,monospace; }
.detail-header-right { display:flex; align-items:center; gap:10px; }
.detail-status-badge { font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; letter-spacing:.3px; }
.detail-status-badge.order-paid { background:#f0f9eb; color:#67c23a; }
.detail-status-badge.order-pending { background:#fdf6ec; color:#e6a23c; }
.detail-status-badge.order-expired { background:#f4f4f5; color:#909399; }
.detail-status-badge.order-refunded { background:#fef0f0; color:#f56c6c; }
.detail-close-btn { width:32px; height:32px; border-radius:50%; border:none; background:#f5f5f7; color:#86868b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; }
.detail-close-btn:hover { background:#e8e8ed; color:#1d1d1f; }
.detail-body { display:flex; flex-direction:column; gap:20px; margin-top:8px; }
.detail-row-group { background:#f8f9fb; border-radius:12px; padding:16px 18px 14px; }
.detail-row-label { display:block; font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
.detail-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; }
.detail-row + .detail-row { border-top:1px solid #f0f0f4; }
.detail-row-key { font-size:13px; color:#6e6e73; }
.detail-row-val { font-size:13px; font-weight:600; color:#1d1d1f; text-align:right; max-width:60%; word-break:break-all; }
.detail-row-val.mono { font-family:'SF Mono','Courier New',monospace; font-size:12px; letter-spacing:.3px; }

@media(max-width:768px){
  .toolbar { flex-direction:column; align-items:stretch; }
  .toolbar-left,.toolbar-right { width:100%; }
  .search-form { flex-direction:column; }
  .search-form .el-form-item,.search-form .el-select,.search-form .el-input { width:100% !important; }
  .main-card:deep(.el-card__body) { padding:14px; }
  .advanced-search { padding:14px; }
  .stat-card { padding:10px 12px; }
  .stat-icon { width:36px; height:36px; border-radius:8px; }
  .stat-icon :deep(.el-icon) { font-size:18px !important; }
  .stat-value { font-size:18px; }
  .stat-label { font-size:11px; }
}
@media(max-width:480px){
  .stats-row :deep(.el-col) { margin-bottom:12px; }
}
</style>
