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
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><el-tag v-if="row.status==='pending'" type="warning" size="small">待支付</el-tag><el-tag v-else-if="row.status==='paid'" type="success" size="small">已支付</el-tag><el-tag v-else-if="row.status==='expired'" type="info" size="small">已过期</el-tag><el-tag v-else type="danger" size="small">已退款</el-tag></template></el-table-column>
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
    <el-dialog v-model="detailVisible" title="订单详情" width="600px" destroy-on-close>
      <el-descriptions :column="2" border v-if="detail">
        <el-descriptions-item label="ID" :span="1">{{ detail.id }}</el-descriptions-item>
        <el-descriptions-item label="状态" :span="1"><el-tag v-if="detail.status==='pending'" type="warning">待支付</el-tag><el-tag v-else-if="detail.status==='paid'" type="success">已支付</el-tag><el-tag v-else-if="detail.status==='expired'" type="info">已过期</el-tag><el-tag v-else type="danger">已退款</el-tag></el-descriptions-item>
        <el-descriptions-item label="订单号" :span="2">{{ detail.order_no }}</el-descriptions-item>
        <el-descriptions-item label="交易号" :span="2">{{ detail.trade_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="项目" :span="1">{{ detail.project_name }}</el-descriptions-item>
        <el-descriptions-item label="套餐" :span="1">{{ detail.card_type_name }}</el-descriptions-item>
        <el-descriptions-item label="金额" :span="1"><span class="price-text">¥{{ parseFloat(detail.amount||0).toFixed(2) }}</span></el-descriptions-item>
        <el-descriptions-item label="支付方式" :span="1">{{ detail.pay_type ? {alipay:'支付宝',wxpay:'微信',qqpay:'QQ钱包'}[detail.pay_type] : '-' }}</el-descriptions-item>
        <el-descriptions-item label="卡密" :span="2"><span v-if="detail.card_key" class="mono-text">{{ detail.card_key }}</span><span v-else class="text-muted">-</span></el-descriptions-item>
        <el-descriptions-item label="联系方式" :span="2">{{ detail.contact_info || '-' }}</el-descriptions-item>
        <el-descriptions-item label="支付时间" :span="1">{{ detail.paid_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间" :span="1">{{ detail.created_at }}</el-descriptions-item>
      </el-descriptions>
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
.stats-row { margin-bottom:20px; }
/* ========== 主卡片 ========== */
.advanced-search { padding:20px; background:#f5f7fa; border-radius:8px; margin-bottom:20px; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.toolbar-left,.toolbar-right { display:flex; gap:12px; flex-wrap:wrap; }
.data-table :deep(.el-table__header th) { font-weight:600; color:#303133; }
.price-text { color:#F56C6C; font-weight:700; }
.mono-text { font-family:'Courier New',monospace; }
.text-muted { color:#c0c4cc; }
.pagination-wrapper { margin-top:20px; display:flex; justify-content:flex-end; }
.column-settings { padding:8px; }
.column-settings-title { font-weight:600; margin-bottom:12px; }
.column-settings .el-checkbox { display:block; margin-bottom:8px; }
.expand-enter-active,.expand-leave-active { transition:all .3s ease; }
.expand-enter-from,.expand-leave-to { opacity:0; max-height:0; overflow:hidden; }
.expand-enter-to,.expand-leave-from { opacity:1; max-height:200px; }
@media(max-width:768px){
  .toolbar { flex-direction:column; align-items:stretch; }
  .toolbar-left,.toolbar-right { width:100%; }
  .search-form { flex-direction:column; }
  .search-form .el-form-item,.search-form .el-select,.search-form .el-input { width:100% !important; }
}
</style>
