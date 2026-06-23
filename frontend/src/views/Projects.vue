<template>
  <div class="projects-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><FolderOpened /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总项目数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-active"><div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ activeCount }}</div><div class="stat-label">启用项目</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-inactive"><div class="stat-icon"><el-icon :size="32"><CircleClose /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ inactiveCount }}</div><div class="stat-label">禁用项目</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="关键词"><el-input v-model="filters.keyword" placeholder="项目名称" clearable style="width: 220px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange"><el-option label="启用" :value="1" /><el-option label="禁用" :value="0" /></el-select>
            </el-form-item>
            <el-form-item><el-button type="primary" @click="handleFilterChange" :icon="Search">搜索</el-button><el-button @click="resetFilters" :icon="Refresh">重置</el-button></el-form-item>
          </el-form>
        </div>
      </transition>

      <div class="toolbar">
        <div class="toolbar-left">
          <el-button v-if="canManage" type="primary" :icon="Plus" @click="showCreateDialog">创建项目</el-button>
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
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="name" label="项目名称" min-width="160" sortable="custom" v-if="visibleColumns.name" />
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip v-if="visibleColumns.description" />
        <el-table-column prop="api_key" label="API Key" min-width="220" show-overflow-tooltip v-if="visibleColumns.api_key"><template #default="{ row }"><div class="key-cell"><span class="mono-text">{{ row.api_key }}</span><el-icon class="copy-icon" @click="copyToClipboard(row.api_key)" title="复制"><DocumentCopy /></el-icon></div></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" :disabled="!canManage" /></template></el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="260" fixed="right" align="center" v-if="canManage">
          <template #default="{ row }">
            <el-button size="small" type="primary" link :icon="Edit" @click="showEditDialog(row)">编辑</el-button>
            <el-button size="small" type="warning" link @click="showCardTypeDialog(row)">套餐</el-button>
            <el-button size="small" link @click="handleRegenerateKey(row)">重置Key</el-button>
            <el-button size="small" type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 创建/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑项目' : '创建项目'" width="500px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="80px">
        <el-form-item label="项目名称" prop="name"><el-input v-model="form.name" placeholder="请输入项目名称" /></el-form-item>
        <el-form-item label="描述" prop="description"><el-input v-model="form.description" type="textarea" :rows="3" placeholder="项目描述" /></el-form-item>
        <el-form-item label="状态" prop="status"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="submitLoading" @click="handleSubmit">确认</el-button></template>
    </el-dialog>

    <!-- 套餐管理弹窗 -->
    <el-dialog v-model="typeDialogVisible" :title="'套餐管理 - ' + curProject?.name" width="800px" :close-on-click-modal="false" destroy-on-close>
      <div style="margin-bottom:12px"><el-button type="primary" size="small" @click="showTypeForm(null)" :icon="Plus">新增套餐</el-button></div>
      <el-table :data="cardTypes" v-loading="typeLoading" stripe border class="data-table" :max-height="360">
        <el-table-column prop="name" label="名称" width="120" />
        <el-table-column prop="duration_days" label="有效天数" width="100"><template #default="{ row }">{{ row.duration_days==0?'永久':row.duration_days+'天' }}</template></el-table-column>
        <el-table-column prop="price" label="售价(元)" width="100"><template #default="{ row }">¥{{ parseFloat(row.price||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="agent_cost" label="代理价格(元)" width="120"><template #default="{ row }">¥{{ parseFloat(row.agent_cost||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column prop="status" label="状态" width="90" align="center"><template #default="{ row }"><el-tag :type="row.status===1?'success':'danger'" size="small">{{ row.status===1?'启用':'禁用' }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="150" align="center"><template #default="{ row }"><el-button size="small" type="primary" link @click="showTypeForm(row)">编辑</el-button><el-button size="small" type="danger" link @click="deleteCardType(row)">删除</el-button></template></el-table-column>
      </el-table>
    </el-dialog>

    <!-- 套餐编辑弹窗 -->
    <el-dialog v-model="typeFormVisible" :title="typeForm.id ? '编辑套餐' : '新增套餐'" width="450px" :close-on-click-modal="false" append-to-body destroy-on-close>
      <el-form ref="typeFormRef" :model="typeForm" :rules="typeFormRules" label-width="100px">
        <el-form-item label="名称" prop="name"><el-input v-model="typeForm.name" placeholder="如：月卡、年卡" /></el-form-item>
        <el-form-item label="有效天数" prop="duration_days"><el-input-number v-model="typeForm.duration_days" :min="0" placeholder="0=永久" style="width:100%" /></el-form-item>
        <el-form-item label="售价" prop="price"><el-input-number v-model="typeForm.price" :min="0" :precision="2" style="width:100%" /></el-form-item>
        <el-form-item label="代理价格" prop="agent_cost"><el-input-number v-model="typeForm.agent_cost" :min="0" :precision="2" style="width:100%" /></el-form-item>
        <el-form-item label="排序" prop="sort"><el-input-number v-model="typeForm.sort" :min="0" style="width:100%" /></el-form-item>
        <el-form-item label="状态" prop="status"><el-switch v-model="typeForm.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="typeFormVisible = false">取消</el-button><el-button type="primary" :loading="typeSubmitLoading" @click="handleTypeSubmit">确认</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useUserStore } from '@/stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { Plus, Delete, Download, Search, Refresh, Filter, Setting, Edit, ArrowDown, FolderOpened, CircleCheck, CircleClose, DocumentCopy } from '@element-plus/icons-vue'
import request from '@/api'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const canManage = computed(() => ['admin', 'project_admin'].includes(user.value?.role))
const showAdvancedSearch = ref(true)

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/projects',
  defaultFilters: { keyword: '', status: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('projects_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'name', label: '项目名称' },
  { prop: 'description', label: '描述' },
  { prop: 'api_key', label: 'API Key' },
  { prop: 'status', label: '状态' },
  { prop: 'created_at', label: '创建时间' },
])

const activeCount = computed(() => list.value.filter(i => i.status === 1).length)
const inactiveCount = computed(() => list.value.filter(i => i.status === 0).length)

// 项目表单
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const form = ref({ name: '', description: '', status: 1 })
const formRules = { name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }] }
const submitLoading = ref(false)
let editingId = null

function showCreateDialog() { isEdit.value = false; editingId = null; form.value = { name: '', description: '', status: 1 }; dialogVisible.value = true }
function showEditDialog(row) { isEdit.value = true; editingId = row.id; form.value = { name: row.name, description: row.description, status: row.status }; dialogVisible.value = true }
async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      if (isEdit.value) { await request.put(`/projects/${editingId}`, form.value); ElMessage.success('更新成功') }
      else { const res = await request.post('/projects', form.value); ElMessage.success('创建成功，API Key: ' + res.data.api_key) }
      dialogVisible.value = false; fetchData()
    } catch {} finally { submitLoading.value = false }
  })
}
async function handleStatusChange(row) {
  try { await request.put(`/projects/${row.id}`, { status: row.status }); ElMessage.success('状态更新成功'); fetchData() }
  catch { row.status = row.status === 1 ? 0 : 1 }
}
async function handleRegenerateKey(row) {
  try {
    await ElMessageBox.confirm('确定重置API Key？旧Key将立即失效。', '提示', { type: 'warning' })
    const res = await request.post(`/projects/${row.id}/regenerate-key`)
    ElMessage.success('新API Key: ' + res.data.api_key); fetchData()
  } catch {}
}
async function handleDelete(row) {
  try { await ElMessageBox.confirm('确定删除该项目？', '提示', { type: 'warning' }); await request.delete(`/projects/${row.id}`); ElMessage.success('删除成功'); fetchData() } catch {}
}
async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 个项目吗？`, '批量删除', { type: 'warning' })
    for (const row of selectedRows.value) await request.delete(`/projects/${row.id}`)
    ElMessage.success('批量删除成功'); fetchData()
  } catch {}
}
function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的项目'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/projects/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制')).catch(() => ElMessage.error('复制失败')) }

// 套餐管理
const typeDialogVisible = ref(false)
const curProject = ref(null)
const cardTypes = ref([])
const typeLoading = ref(false)
const typeFormVisible = ref(false)
const typeFormRef = ref(null)
const typeForm = ref({ name: '', duration_days: 30, price: 0, agent_cost: 0, sort: 0, status: 1 })
const typeFormRules = { name: [{ required: true, message: '请输入名称', trigger: 'blur' }], price: [{ required: true, type: 'number', message: '请输入价格', trigger: 'blur' }] }
const typeSubmitLoading = ref(false)

async function showCardTypeDialog(row) {
  curProject.value = row; typeDialogVisible.value = true; typeLoading.value = true
  try { cardTypes.value = (await request.get(`/projects/${row.id}/card-types`)).data } finally { typeLoading.value = false }
}
function showTypeForm(row) { typeForm.value = row ? { ...row } : { name: '', duration_days: 30, price: 0, agent_cost: 0, sort: 0, status: 1 }; typeFormVisible.value = true }
async function handleTypeSubmit() {
  if (!typeFormRef.value) return
  await typeFormRef.value.validate(async (valid) => {
    if (!valid) return
    typeSubmitLoading.value = true
    try {
      const pid = curProject.value.id
      if (typeForm.value.id) await request.put(`/projects/${pid}/card-types/${typeForm.value.id}`, typeForm.value)
      else await request.post(`/projects/${pid}/card-types`, typeForm.value)
      ElMessage.success('操作成功'); typeFormVisible.value = false; showCardTypeDialog(curProject.value)
    } catch {} finally { typeSubmitLoading.value = false }
  })
}
async function deleteCardType(row) {
  try { await ElMessageBox.confirm('确定删除该套餐？', '提示', { type: 'warning' }); await request.delete(`/projects/${curProject.value.id}/card-types/${row.id}`); ElMessage.success('删除成功'); showCardTypeDialog(curProject.value) } catch {}
}
</script>

<style scoped>
.projects-page { max-width:1600px; margin:0 auto; }
.stats-row { margin-bottom:20px; }
/* ========== 主卡片 ========== */
.advanced-search { padding:20px; background:#f5f7fa; border-radius:8px; margin-bottom:20px; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.toolbar-left,.toolbar-right { display:flex; gap:12px; flex-wrap:wrap; }
.data-table :deep(.el-table__header th) { font-weight:600; color:#303133; }
.key-cell { display:flex; align-items:center; gap:8px; }
.copy-icon { color:#409EFF; cursor:pointer; }
.mono-text { font-family:'Courier New',monospace; }
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
