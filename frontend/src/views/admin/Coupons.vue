<template>
  <div class="coupons-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><Ticket /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总优惠码数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-active"><div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ activeCount }}</div><div class="stat-label">启用中</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-used"><div class="stat-icon"><el-icon :size="32"><PieChart /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ totalUsed }}</div><div class="stat-label">总使用次数</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="关键词"><el-input v-model="filters.keyword" placeholder="优惠码/名称" clearable style="width: 220px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange"><el-option label="启用" :value="1" /><el-option label="禁用" :value="0" /></el-select>
            </el-form-item>
            <el-form-item label="项目">
              <el-select v-model="filters.project_id" placeholder="全部项目" clearable style="width: 180px" @change="handleFilterChange"><el-option v-for="p in projects" :key="p.id" :label="p.name" :value="String(p.id)" /></el-select>
            </el-form-item>
            <el-form-item><el-button type="primary" @click="handleFilterChange" :icon="Search">搜索</el-button><el-button @click="resetFilters" :icon="Refresh">重置</el-button></el-form-item>
          </el-form>
        </div>
      </transition>

      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" :icon="Plus" @click="openDialog(null)">新增优惠码</el-button>
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="success" :icon="Download">导出 ({{ selectedRows.length }})<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown><el-dropdown-menu><el-dropdown-item command="csv">导出 CSV</el-dropdown-item><el-dropdown-item command="excel">导出 Excel</el-dropdown-item></el-dropdown-menu></template>
          </el-dropdown>
          <el-button type="danger" :icon="Delete" @click="handleBatchDelete" :disabled="!selectedRows.length">批量删除 ({{ selectedRows.length }})</el-button>
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
        <el-table-column prop="id" label="ID" width="60" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="code" label="优惠码" width="140" sortable="custom" v-if="visibleColumns.code"><template #default="{ row }"><el-tag size="small">{{ row.code }}</el-tag></template></el-table-column>
        <el-table-column prop="name" label="名称/备注" min-width="140" sortable="custom" v-if="visibleColumns.name" />
        <el-table-column prop="discount_percent" label="省钱%" width="90" align="center" sortable="custom" v-if="visibleColumns.discount_percent"><template #default="{ row }"><span style="color:#67C23A;font-weight:600">{{ row.discount_percent }}%</span></template></el-table-column>
        <el-table-column label="使用情况" width="130" align="center" v-if="visibleColumns.usage"><template #default="{ row }"><span v-if="row.max_use_count>0">{{ row.used_count }} / {{ row.max_use_count }}</span><span v-else>{{ row.used_count }} / 不限</span></template></el-table-column>
        <el-table-column prop="min_amount" label="最低消费" width="110" sortable="custom" v-if="visibleColumns.min_amount"><template #default="{ row }">¥{{ parseFloat(row.min_amount||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="status" label="状态" width="90" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><el-tag :type="row.status===1?'success':'danger'" size="small">{{ row.status===1?'启用':'禁用' }}</el-tag></template></el-table-column>
        <el-table-column prop="expire_at" label="过期时间" width="170" sortable="custom" v-if="visibleColumns.expire_at"><template #default="{ row }">{{ row.expire_at || '永久有效' }}</template></el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="140" fixed="right" align="center"><template #default="{ row }"><el-button size="small" type="primary" link @click="openDialog(row)">编辑</el-button><el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button></template></el-table-column>
      </el-table>

      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑优惠码' : '新增优惠码'" width="500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <el-form-item label="优惠码" prop="code"><el-input v-model="form.code" placeholder="字母数字组合" :disabled="isEdit" /></el-form-item>
        <el-form-item label="名称/备注" prop="name"><el-input v-model="form.name" placeholder="选填" /></el-form-item>
        <el-form-item label="省钱百分比" prop="discount_percent"><el-input-number v-model="form.discount_percent" :min="0" :max="100" :precision="2" style="width:180px" /><span style="color:#999;margin-left:8px;font-size:12px">例如：10 表示省钱 10%</span></el-form-item>
        <el-form-item label="最大使用次数" prop="max_use_count"><el-input-number v-model="form.max_use_count" :min="0" style="width:180px" /><span style="color:#999;margin-left:8px;font-size:12px">0=不限</span></el-form-item>
        <el-form-item label="最低消费" prop="min_amount"><el-input-number v-model="form.min_amount" :min="0" :precision="2" style="width:180px" /><span style="color:#999;margin-left:8px;font-size:12px">0=无限制</span></el-form-item>
        <el-form-item label="适用项目">
          <el-select v-model="form.project_ids" multiple filterable clearable placeholder="不选则适用全部项目" style="width:100%">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="String(p.id)" />
          </el-select>
          <div style="color:#909399;font-size:12px;margin-top:4px">不选择任何项目 = 适用全部项目；选择后仅适用指定项目</div>
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker v-model="form.expire_at" type="datetime" placeholder="选填，不填则永久有效" format="YYYY-MM-DD HH:mm:ss" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
        </el-form-item>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { Plus, Download, Search, Refresh, Filter, Setting, ArrowDown, Ticket, CircleCheck, PieChart } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import request from '@/api'

const showAdvancedSearch = ref(true)
const projects = ref([])

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/coupons',
  defaultFilters: { keyword: '', status: '', project_id: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('coupons_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'code', label: '优惠码' },
  { prop: 'name', label: '名称/备注' },
  { prop: 'discount_percent', label: '省钱%' },
  { prop: 'usage', label: '使用情况' },
  { prop: 'min_amount', label: '最低消费' },
  { prop: 'status', label: '状态' },
  { prop: 'expire_at', label: '过期时间' },
  { prop: 'created_at', label: '创建时间' },
])

const activeCount = computed(() => list.value.filter(i => i.status === 1).length)
const totalUsed = computed(() => list.value.reduce((sum, i) => sum + (i.used_count || 0), 0))

onMounted(async () => {
  try {
    const res = await request.get('/projects')
    // API 返回 { code:200, data:{ list:[...], total:N } }，取 data.list
    projects.value = res.data?.list || res.data || []
  } catch {
    projects.value = []
  }
})

// 表单
const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const formRef = ref(null)
const submitting = ref(false)
const form = reactive({ code: '', name: '', discount_percent: 10, max_use_count: 0, min_amount: 0, project_ids: [], expire_at: '', status: 1 })
const rules = { code: [{ required: true, message: '请输入优惠码', trigger: 'blur' }], discount_percent: [{ required: true, message: '请输入省钱百分比', trigger: 'blur' }] }

function openDialog(row) {
  if (row) {
    isEdit.value = true; editId.value = row.id
    form.code = row.code; form.name = row.name; form.discount_percent = parseFloat(row.discount_percent); form.max_use_count = row.max_use_count; form.min_amount = parseFloat(row.min_amount)
    // 解析 project_ids：数据库存的是逗号分隔字符串（如 "1,2,3"），空字符串=全部项目
    // 使用 filter(Boolean) 过滤空字符串，避免空字符串 split 产生 [0] 的 bug
    // 保持字符串类型，与 el-option 的 :value="String(p.id)" 一致
    form.project_ids = (row.project_ids && row.project_ids.trim()) ? row.project_ids.split(',').filter(Boolean) : []
    form.expire_at = row.expire_at || ''; form.status = row.status
  } else {
    isEdit.value = false; editId.value = null
    form.code = ''; form.name = ''; form.discount_percent = 10; form.max_use_count = 0; form.min_amount = 0; form.project_ids = []; form.expire_at = ''; form.status = 1
  }
  dialogVisible.value = true
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    const data = { code: form.code.toUpperCase(), name: form.name, discount_percent: form.discount_percent, max_use_count: form.max_use_count, min_amount: form.min_amount, project_ids: form.project_ids.join(','), expire_at: form.expire_at || null, status: form.status }
    if (isEdit.value) { await request.put(`/coupons/${editId.value}`, data); ElMessage.success('更新成功') }
    else { await request.post('/coupons', data); ElMessage.success('创建成功') }
    dialogVisible.value = false; fetchData()
  } catch {} finally { submitting.value = false }
}

async function handleDelete(row) {
  try { await ElMessageBox.confirm(`确定删除优惠码「${row.code}」吗？`, '确认删除', { type: 'warning' }); await request.delete(`/coupons/${row.id}`); ElMessage.success('删除成功'); fetchData() } catch {}
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 个优惠码吗？`, '批量删除', { type: 'warning' })
    for (const row of selectedRows.value) await request.delete(`/coupons/${row.id}`)
    ElMessage.success('批量删除成功'); fetchData()
  } catch {}
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的优惠码'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/coupons/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
</script>

<style scoped>
.coupons-page { max-width:1600px; margin:0 auto; }

/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-active .stat-icon { background:#f0fdf4; color:#22c55e; }
.stat-used .stat-icon { background:#f5f3ff; color:#8b5cf6; }

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
.mono-text { font-family:'SF Mono',Cascadia Code,Courier New,monospace; font-size:13px; letter-spacing:.2px; }
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
