<template>
  <div class="products-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total">
          <div class="stat-icon"><el-icon :size="32"><Goods /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总商品数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-active">
          <div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ activeCount }}</div><div class="stat-label">启用中</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-inactive">
          <div class="stat-icon"><el-icon :size="32"><CircleClose /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ inactiveCount }}</div><div class="stat-label">已禁用</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-forever">
          <div class="stat-icon"><el-icon :size="32"><Timer /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ foreverCount }}</div><div class="stat-label">永久套餐</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <!-- 高级搜索 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="项目">
              <el-select v-model="filters.project_id" placeholder="全部项目" clearable style="width: 180px" @change="handleFilterChange">
                <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange">
                <el-option label="启用" :value="1" />
                <el-option label="禁用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item label="关键词">
              <el-input v-model="filters.keyword" placeholder="商品名称" clearable style="width: 200px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" />
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
          <el-button v-if="canManage" type="primary" :icon="Plus" @click="showCreateDialog">新增商品</el-button>
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="success" :icon="Download">导出 ({{ selectedRows.length }})<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown>
              <el-dropdown-menu><el-dropdown-item command="csv">导出 CSV</el-dropdown-item><el-dropdown-item command="excel">导出 Excel</el-dropdown-item></el-dropdown-menu>
            </template>
          </el-dropdown>
          <el-button type="danger" :icon="Delete" @click="handleBatchDelete" :disabled="!selectedRows.length">批量删除 ({{ selectedRows.length }})</el-button>
        </div>
        <div class="toolbar-right">
          <el-button @click="showAdvancedSearch = !showAdvancedSearch" :icon="Filter">{{ showAdvancedSearch ? '收起搜索' : '高级搜索' }}</el-button>
          <el-popover placement="bottom" :width="280" trigger="click">
            <template #reference><el-button :icon="Setting">列设置</el-button></template>
            <div class="column-settings"><div class="column-settings-title">显示/隐藏列</div>
              <el-checkbox v-for="col in columnOptions" :key="col.prop" v-model="col.visible" :label="col.label" @change="saveColumnSettings" />
            </div>
          </el-popover>
          <el-button @click="refreshData" :icon="Refresh" circle />
        </div>
      </div>

      <!-- 表格 -->
      <el-table ref="tableRef" :data="list" v-loading="loading" stripe border highlight-current-row @selection-change="handleSelectionChange" @sort-change="handleSortChange" row-key="id" :max-height="tableMaxHeight" class="data-table">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="project_name" label="所属项目" width="140" sortable="custom" v-if="visibleColumns.project_name" />
        <el-table-column prop="name" label="商品名称" min-width="150" sortable="custom" v-if="visibleColumns.name" />
        <el-table-column prop="duration_days" label="有效期" width="110" align="center" sortable="custom" v-if="visibleColumns.duration_days">
          <template #default="{ row }"><el-tag :type="row.duration_days == 0 ? 'success' : 'warning'" size="small">{{ row.duration_days == 0 ? '永久' : row.duration_days + '天' }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="original_price" label="原价" width="110" sortable="custom" v-if="visibleColumns.original_price"><template #default="{ row }">¥{{ parseFloat(row.original_price||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="price" label="售价" width="110" sortable="custom" v-if="visibleColumns.price"><template #default="{ row }"><span class="price-text">¥{{ parseFloat(row.price||0).toFixed(2) }}</span></template></el-table-column>
        <el-table-column prop="agent_cost" label="代理价格" width="120" sortable="custom" v-if="visibleColumns.agent_cost && user?.role === 'admin'"><template #default="{ row }">¥{{ parseFloat(row.agent_cost||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="sort" label="排序" width="80" sortable="custom" align="center" v-if="visibleColumns.sort" />
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status">
          <template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" :disabled="!canManage" /></template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="180" fixed="right" align="center" v-if="canManage">
          <template #default="{ row }">
            <el-button size="small" type="primary" link :icon="Edit" @click="showEditDialog(row)">编辑</el-button>
            <el-button size="small" type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" />
      </div>
    </el-card>

    <!-- 创建/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑商品' : '新增商品'" width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="所属项目" prop="project_id"><el-select v-model="form.project_id" placeholder="请选择项目" style="width:100%" :disabled="isEdit"><el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" /></el-select></el-form-item>
        <el-form-item label="商品名称" prop="name"><el-input v-model="form.name" placeholder="如：月卡、季卡、年卡、永久卡" /></el-form-item>
        <el-form-item label="商品简介" prop="description"><el-input v-model="form.description" type="textarea" :rows="2" placeholder="简要描述商品特性，前台购买页会展示" maxlength="255" show-word-limit /></el-form-item>
        <el-form-item label="有效天数" prop="duration_days"><el-input-number v-model="form.duration_days" :min="0" style="width:100%" /><span style="color:#999;margin-left:8px">0=永久</span></el-form-item>
        <el-form-item label="售价" prop="price"><el-input-number v-model="form.price" :min="0" :precision="2" style="width:100%" /></el-form-item>
        <el-form-item label="原价" prop="original_price"><el-input-number v-model="form.original_price" :min="0" :precision="2" style="width:100%" /><span style="color:#999;font-size:12px;margin-left:8px">用于显示划线价，留空则等于售价</span></el-form-item>
        <el-form-item label="代理价格" prop="agent_cost" v-if="user?.role === 'admin'"><el-input-number v-model="form.agent_cost" :min="0" :precision="2" style="width:100%" /></el-form-item>
        <el-form-item label="排序" prop="sort"><el-input-number v-model="form.sort" :min="0" style="width:100%" /></el-form-item>
        <el-form-item label="状态" prop="status"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="submitLoading" @click="handleSubmit">确认</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useUserStore } from '@/stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { Plus, Delete, Download, Search, Refresh, Filter, Setting, Edit, ArrowDown, Goods, CircleCheck, CircleClose, Timer } from '@element-plus/icons-vue'
import request from '@/api'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const canManage = computed(() => ['admin', 'project_admin'].includes(user.value?.role))

const projects = ref([])
const showAdvancedSearch = ref(true)

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/products',
  defaultFilters: { project_id: '', status: '', keyword: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('products_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'project_name', label: '所属项目' },
  { prop: 'name', label: '商品名称' },
  { prop: 'duration_days', label: '有效期' },
  { prop: 'original_price', label: '原价' },
  { prop: 'price', label: '售价' },
  { prop: 'agent_cost', label: '代理价格' },
  { prop: 'sort', label: '排序' },
  { prop: 'status', label: '状态' },
  { prop: 'created_at', label: '创建时间' },
])

const activeCount = computed(() => list.value.filter(i => i.status === 1).length)
const inactiveCount = computed(() => list.value.filter(i => i.status === 0).length)
const foreverCount = computed(() => list.value.filter(i => i.duration_days == 0).length)

onMounted(async () => {
  const res = await request.get('/projects/all')
  projects.value = res.data
})

// 表单
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const form = ref({ project_id: null, name: '', duration_days: 30, price: 0, original_price: 0, agent_cost: 0, sort: 0, status: 1 })
const formRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  price: [{ required: true, type: 'number', message: '请输入价格', trigger: 'blur' }],
}
const submitLoading = ref(false)
let editingId = null

function showCreateDialog() {
  isEdit.value = false; editingId = null
  form.value = { project_id: null, name: '', description: '', duration_days: 30, price: 0, original_price: 0, agent_cost: 0, sort: 0, status: 1 }
  dialogVisible.value = true
}

function showEditDialog(row) {
  isEdit.value = true; editingId = row.id
  form.value = { project_id: row.project_id, name: row.name, description: row.description ?? '', duration_days: row.duration_days, price: row.price, original_price: row.original_price ?? 0, agent_cost: row.agent_cost, sort: row.sort, status: row.status }
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const pid = form.value.project_id
      if (isEdit.value) {
        await request.put(`/projects/${pid}/card-types/${editingId}`, form.value)
        ElMessage.success('更新成功')
      } else {
        await request.post(`/projects/${pid}/card-types`, form.value)
        ElMessage.success('创建成功')
      }
      dialogVisible.value = false
      fetchData()
    } catch (e) { console.error(e) }
    finally { submitLoading.value = false }
  })
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm('确定删除该商品？', '提示', { type: 'warning' })
    await request.delete(`/projects/${row.project_id}/card-types/${row.id}`)
    ElMessage.success('删除成功')
    fetchData()
  } catch (e) { if (e !== 'cancel') console.error(e) }
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 个商品吗？`, '批量删除', { type: 'warning' })
    // 后端需支持 batch-delete
    for (const row of selectedRows.value) {
      await request.delete(`/projects/${row.project_id}/card-types/${row.id}`)
    }
    ElMessage.success('批量删除成功')
    fetchData()
  } catch (e) { if (e !== 'cancel') console.error(e) }
}

async function handleStatusChange(row) {
  try {
    await request.put(`/projects/${row.project_id}/card-types/${row.id}`, { status: row.status })
    ElMessage.success('状态更新成功')
    fetchData()
  } catch (e) {
    row.status = row.status === 1 ? 0 : 1
    console.error(e)
  }
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的数据'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/products/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
</script>

<style scoped>
.products-page { max-width:1600px; margin:0 auto; }

/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-active .stat-icon { background:#f0fdf4; color:#22c55e; }
.stat-inactive .stat-icon { background:#fef2f2; color:#ef4444; }
.stat-forever .stat-icon { background:#f5f3ff; color:#8b5cf6; }

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
