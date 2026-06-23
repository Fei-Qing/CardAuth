<template>
  <div class="logs-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><Document /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总日志数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-today"><div class="stat-icon"><el-icon :size="32"><Calendar /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ todayCount }}</div><div class="stat-label">今日日志</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-users"><div class="stat-icon"><el-icon :size="32"><User /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ uniqueUsers }}</div><div class="stat-label">操作用户数</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="90px">
            <el-form-item label="操作类型"><el-input v-model="filters.action" placeholder="输入操作类型" clearable style="width: 160px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="用户ID"><el-input v-model="filters.user_id" placeholder="用户ID" clearable style="width: 130px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="用户名"><el-input v-model="filters.username" placeholder="用户名" clearable style="width: 150px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="日期范围">
              <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" style="width: 260px" @change="handleDateChange" />
            </el-form-item>
            <el-form-item><el-button type="primary" @click="handleFilterChange" :icon="Search">搜索</el-button><el-button @click="resetFilters" :icon="Refresh">重置</el-button></el-form-item>
          </el-form>
        </div>
      </transition>

      <div class="toolbar">
        <div class="toolbar-left">
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
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

      <el-table ref="tableRef" :data="list" v-loading="loading" stripe border highlight-current-row @selection-change="handleSelectionChange" @sort-change="handleSortChange" row-key="id" :max-height="tableMaxHeight" class="data-table">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="username" label="操作用户" width="130" sortable="custom" v-if="visibleColumns.username" />
        <el-table-column prop="nickname" label="昵称" width="120" sortable="custom" v-if="visibleColumns.nickname" />
        <el-table-column prop="action" label="操作类型" width="160" sortable="custom" v-if="visibleColumns.action"><template #default="{ row }"><el-tag type="info" size="small">{{ row.action }}</el-tag></template></el-table-column>
        <el-table-column prop="target_type" label="目标类型" width="120" sortable="custom" v-if="visibleColumns.target_type" />
        <el-table-column prop="target_id" label="目标ID" width="90" align="center" sortable="custom" v-if="visibleColumns.target_id" />
        <el-table-column prop="detail" label="详情" min-width="220" show-overflow-tooltip v-if="visibleColumns.detail"><template #default="{ row }">{{ row.detail || '-' }}</template></el-table-column>
        <el-table-column prop="ip" label="IP" width="140" sortable="custom" v-if="visibleColumns.ip" />
        <el-table-column prop="created_at" label="时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
      </el-table>

      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Download, Search, Refresh, Filter, Setting, ArrowDown, Document, Calendar, User } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'

const showAdvancedSearch = ref(true)
const dateRange = ref([])

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/logs',
  defaultFilters: { action: '', user_id: '', username: '', start_date: '', end_date: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('logs_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'username', label: '操作用户' },
  { prop: 'nickname', label: '昵称' },
  { prop: 'action', label: '操作类型' },
  { prop: 'target_type', label: '目标类型' },
  { prop: 'target_id', label: '目标ID' },
  { prop: 'detail', label: '详情' },
  { prop: 'ip', label: 'IP' },
  { prop: 'created_at', label: '时间' },
])

const todayCount = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return list.value.filter(i => i.created_at && i.created_at.startsWith(today)).length
})
const uniqueUsers = computed(() => new Set(list.value.map(i => i.user_id)).size)

function handleDateChange(val) {
  if (val) { filters.start_date = val[0]; filters.end_date = val[1] }
  else { filters.start_date = ''; filters.end_date = '' }
  handleFilterChange()
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的日志'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/logs/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
</script>

<style scoped>
.logs-page { max-width:1600px; margin:0 auto; }
.stats-row { margin-bottom:20px; }
/* ========== 主卡片 ========== */
.advanced-search { padding:20px; background:#f5f7fa; border-radius:8px; margin-bottom:20px; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.toolbar-left,.toolbar-right { display:flex; gap:12px; flex-wrap:wrap; }
.data-table :deep(.el-table__header th) { font-weight:600; color:#303133; }
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
  .search-form .el-form-item,.search-form .el-select,.search-form .el-input,.search-form .el-date-picker { width:100% !important; }
}
</style>
