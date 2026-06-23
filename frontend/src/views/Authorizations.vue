<template>
  <div class="authorizations-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><Lock /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.total || total }}</div><div class="stat-label">总授权数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-active"><div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.active || activeCount }}</div><div class="stat-label">有效授权</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-expired"><div class="stat-icon"><el-icon :size="32"><Timer /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.expired || expiredCount }}</div><div class="stat-label">已过期</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-revoked"><div class="stat-icon"><el-icon :size="32"><CircleClose /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.revoked || revokedCount }}</div><div class="stat-label">已撤销</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <!-- 高级搜索 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="100px">
            <el-form-item label="机器人QQ"><el-input v-model="filters.bot_qq" placeholder="机器人QQ" clearable style="width: 150px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="联系人QQ"><el-input v-model="filters.contact_qq" placeholder="联系人QQ" clearable style="width: 150px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange">
                <el-option label="有效" value="active" /><el-option label="已过期" value="expired" /><el-option label="已撤销" value="revoked" />
              </el-select>
            </el-form-item>
            <el-form-item label="卡密"><el-input v-model="filters.card_key" placeholder="卡密搜索" clearable style="width: 180px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
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
          <el-button type="primary" :icon="Plus" @click="showCreateDialog">新增授权</el-button>
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

      <!-- 表格 -->
      <el-table ref="tableRef" :data="list" v-loading="loading" stripe border highlight-current-row @selection-change="handleSelectionChange" @sort-change="handleSortChange" row-key="id" :max-height="tableMaxHeight" class="data-table">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="bot_qq" label="机器人QQ" width="140" sortable="custom" v-if="visibleColumns.bot_qq"><template #default="{ row }"><span class="mono-text">{{ row.bot_qq }}</span></template></el-table-column>
        <el-table-column prop="contact_qq" label="联系人QQ" width="140" sortable="custom" v-if="visibleColumns.contact_qq"><template #default="{ row }"><span class="mono-text">{{ row.contact_qq }}</span></template></el-table-column>
        <el-table-column prop="contact_name" label="联系人" width="110" v-if="visibleColumns.contact_name"><template #default="{ row }">{{ row.contact_name || '-' }}</template></el-table-column>
        <el-table-column prop="card_key" label="卡密" min-width="180" show-overflow-tooltip sortable="custom" v-if="visibleColumns.card_key" />
        <el-table-column prop="project_name" label="项目" width="130" sortable="custom" v-if="visibleColumns.project_name" />
        <el-table-column prop="duration_days" label="有效天数" width="110" align="center" sortable="custom" v-if="visibleColumns.duration_days"><template #default="{ row }"><el-tag :type="row.duration_days==0?'success':'info'" size="small">{{ row.duration_days==0?'永久':row.duration_days+'天' }}</el-tag></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><el-tag v-if="row.status==='active' && !row.is_expired" type="success" size="small">有效</el-tag><el-tag v-else-if="row.is_expired" type="warning" size="small">已过期</el-tag><el-tag v-else-if="row.status==='revoked'" type="danger" size="small">已撤销</el-tag><el-tag v-else type="info" size="small">{{ row.status }}</el-tag></template></el-table-column>
        <el-table-column prop="expire_time" label="过期时间" width="180" sortable="custom" v-if="visibleColumns.expire_time"><template #default="{ row }"><span v-if="row.duration_days==0" class="text-success">永久有效</span><span v-else :class="{'text-danger':row.is_expired}">{{ row.expire_time || '-' }}</span></template></el-table-column>
        <el-table-column prop="authorized_at" label="授权时间" width="180" sortable="custom" v-if="visibleColumns.authorized_at" />
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link :icon="View" @click="showDetail(row)">详情</el-button>
            <el-button v-if="row.status==='active'" size="small" type="danger" link @click="handleRevoke(row)">撤销</el-button>
            <el-button size="small" type="danger" link plain @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 新增授权弹窗 -->
    <el-dialog v-model="createDialogVisible" title="新增授权" width="500px" :close-on-click-modal="false" destroy-on-close @closed="resetCreateForm">
      <el-alert v-if="user?.role === 'agent'" title="代理提示" type="warning" :closable="false" show-icon style="margin-bottom: 16px;">
        <template #default>
          <p style="margin: 0; line-height: 1.6;">创建授权将按所选套餐的代理价格扣减您的余额。</p>
        </template>
      </el-alert>
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="110px">
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="createForm.project_id" placeholder="请选择项目" style="width: 100%" @change="onCreateProjectChange">
            <el-option v-for="p in allProjects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="套餐" prop="card_type_id">
          <el-select v-model="createForm.card_type_id" placeholder="请选择套餐" style="width: 100%" :disabled="!createForm.project_id">
            <el-option v-for="t in createCardTypes" :key="t.id" :label="isAgent ? `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.agent_cost})` : `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.price})`" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isAgent && createCostPreview > 0" label="费用">
          <span style="color: #e6a23c; font-weight: 600; font-size: 16px;">¥{{ createCostPreview.toFixed(2) }}</span>
          <span style="color: #909399; font-size: 12px; margin-left: 8px;">（将从代理余额扣除）</span>
        </el-form-item>
        <el-form-item label="机器人QQ" prop="bot_qq"><el-input v-model="createForm.bot_qq" placeholder="5-15位数字" maxlength="15" /></el-form-item>
        <el-form-item label="联系人QQ" prop="contact_qq"><el-input v-model="createForm.contact_qq" placeholder="5-15位数字" maxlength="15" /></el-form-item>
        <el-form-item label="联系人名称"><el-input v-model="createForm.contact_name" placeholder="选填" maxlength="50" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="createDialogVisible = false">取消</el-button><el-button type="primary" :loading="createLoading" @click="submitCreate">确认授权</el-button></template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailDialogVisible" title="授权详情" width="600px" destroy-on-close>
      <el-descriptions v-if="detail" :column="2" border>
        <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
        <el-descriptions-item label="状态"><el-tag v-if="detail.status==='active' && !detail.is_expired" type="success">有效</el-tag><el-tag v-else-if="detail.is_expired" type="warning">已过期</el-tag><el-tag v-else type="danger">{{ detail.status }}</el-tag></el-descriptions-item>
        <el-descriptions-item label="卡密" :span="2"><span class="mono-text">{{ detail.card_key }}</span></el-descriptions-item>
        <el-descriptions-item label="项目">{{ detail.project_name }}</el-descriptions-item>
        <el-descriptions-item label="机器人QQ"><span class="mono-text">{{ detail.bot_qq }}</span></el-descriptions-item>
        <el-descriptions-item label="联系人QQ"><span class="mono-text">{{ detail.contact_qq }}</span></el-descriptions-item>
        <el-descriptions-item label="联系人">{{ detail.contact_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="有效天数"><el-tag :type="detail.duration_days==0?'success':'info'">{{ detail.duration_days==0?'永久':detail.duration_days+'天' }}</el-tag></el-descriptions-item>
        <el-descriptions-item label="授权时间">{{ detail.authorized_at }}</el-descriptions-item>
        <el-descriptions-item label="过期时间"><span :class="{'text-danger':detail.is_expired}">{{ detail.duration_days==0?'永久有效':(detail.expire_time||'-') }}</span></el-descriptions-item>
        <el-descriptions-item v-if="detail.revoked_at" label="撤销时间">{{ detail.revoked_at }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.revoke_reason" label="撤销原因" :span="2">{{ detail.revoke_reason }}</el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ detail.remark || '-' }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>

    <!-- 撤销确认弹窗 -->
    <el-dialog v-model="revokeDialogVisible" title="撤销授权" width="450px" destroy-on-close>
      <el-form :model="revokeForm" label-width="80px"><el-form-item label="撤销原因"><el-input v-model="revokeForm.reason" placeholder="请输入撤销原因" maxlength="200" /></el-form-item></el-form>
      <template #footer><el-button @click="revokeDialogVisible = false">取消</el-button><el-button type="danger" :loading="revokeLoading" @click="confirmRevoke">确认撤销</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import authorizationApi from '@/api/authorization'
import request from '@/api'
import { useUserStore } from '@/stores/user'
import { Plus, Delete, Download, Search, Refresh, Filter, Setting, View, ArrowDown, Lock, CircleCheck, CircleClose, Timer } from '@element-plus/icons-vue'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const isAdmin = computed(() => user.value?.role === 'admin')
const isAgent = computed(() => user.value?.role === 'agent')

const allProjects = ref([])
const showAdvancedSearch = ref(true)

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, stats, fetchData, fetchStats, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/authorizations',
  statsUrl: '/authorizations/stats',
  defaultFilters: { bot_qq: '', contact_qq: '', status: '', card_key: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('authorizations_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'bot_qq', label: '机器人QQ' },
  { prop: 'contact_qq', label: '联系人QQ' },
  { prop: 'contact_name', label: '联系人' },
  { prop: 'card_key', label: '卡密', visible: false },
  { prop: 'project_name', label: '项目' },
  { prop: 'duration_days', label: '有效天数' },
  { prop: 'status', label: '状态' },
  { prop: 'expire_time', label: '过期时间' },
  { prop: 'authorized_at', label: '授权时间' },
])

const activeCount = computed(() => list.value.filter(i => i.status === 'active' && !i.is_expired).length)
const expiredCount = computed(() => list.value.filter(i => i.is_expired).length)
const revokedCount = computed(() => list.value.filter(i => i.status === 'revoked').length)

// 新增
const createDialogVisible = ref(false)
const createLoading = ref(false)
const createFormRef = ref(null)
const createForm = reactive({ project_id: '', card_type_id: '', bot_qq: '', contact_qq: '', contact_name: '' })
const createCardTypes = ref([])
const createCostPreview = computed(() => {
  if (!isAgent.value || !createForm.card_type_id) return 0
  const selected = createCardTypes.value.find(t => t.id === createForm.card_type_id)
  return selected ? parseFloat(selected.agent_cost) || 0 : 0
})
const createRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  card_type_id: [{ required: true, message: '请选择套餐', trigger: 'change' }],
  bot_qq: [{ required: true, message: '请输入机器人QQ', trigger: 'blur' }, { pattern: /^\d{5,15}$/, message: '请输入5-15位数字', trigger: 'blur' }],
  contact_qq: [{ required: true, message: '请输入联系人QQ', trigger: 'blur' }, { pattern: /^\d{5,15}$/, message: '请输入5-15位数字', trigger: 'blur' }]
}

function showCreateDialog() { createDialogVisible.value = true }
function resetCreateForm() {
  createForm.project_id = ''; createForm.card_type_id = ''; createForm.bot_qq = ''; createForm.contact_qq = ''; createForm.contact_name = ''
  createCardTypes.value = []
  createFormRef.value?.resetFields()
}
function onCreateProjectChange(pid) {
  createForm.card_type_id = ''
  createCardTypes.value = []
  if (!pid) return
  request.get(`/projects/${pid}/card-types`).then(res => {
    let types = res.data || []
    if (isAgent.value) {
      types = types.filter(t => parseFloat(t.agent_cost) > 0)
    }
    createCardTypes.value = types
  }).catch(() => {})
}
async function submitCreate() {
  const valid = await createFormRef.value.validate().catch(() => false)
  if (!valid) return
  createLoading.value = true
  try {
    await authorizationApi.create({ ...createForm })
    ElMessage.success('授权成功')
    createDialogVisible.value = false
    fetchData(); fetchStats()
  } finally { createLoading.value = false }
}

// 详情
const detailDialogVisible = ref(false)
const detail = ref(null)
async function showDetail(row) {
  try {
    const res = await authorizationApi.detail(row.id)
    detail.value = res.data
    detailDialogVisible.value = true
  } catch {}
}

// 撤销
const revokeDialogVisible = ref(false)
const revokeLoading = ref(false)
const revokingId = ref(null)
const revokeForm = reactive({ reason: '' })
function handleRevoke(row) { revokingId.value = row.id; revokeForm.reason = ''; revokeDialogVisible.value = true }
async function confirmRevoke() {
  revokeLoading.value = true
  try {
    await authorizationApi.revoke(revokingId.value, revokeForm.reason)
    ElMessage.success('授权已撤销')
    revokeDialogVisible.value = false
    fetchData(); fetchStats()
  } finally { revokeLoading.value = false }
}

// 删除
async function handleDelete(id) {
  try {
    await ElMessageBox.confirm('确定要删除此授权记录吗？此操作不可恢复。', '确认删除', { type: 'warning' })
    await authorizationApi.delete(id)
    ElMessage.success('已删除')
    fetchData(); fetchStats()
  } catch {}
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 条授权记录吗？`, '批量删除', { type: 'warning' })
    for (const row of selectedRows.value) await authorizationApi.delete(row.id)
    ElMessage.success('批量删除成功')
    fetchData(); fetchStats()
  } catch {}
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的授权'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/authorizations/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}

async function fetchProjects() {
  try {
    const res = await request.get('/projects', { params: { page_size: 999 } })
    allProjects.value = res.data.list || []
  } catch (e) { /* ignore */ }
}

onMounted(() => {
  fetchProjects()
})
</script>

<style scoped>
.authorizations-page { max-width:1600px; margin:0 auto; }
.stats-row { margin-bottom:20px; }
/* ========== 主卡片 ========== */
.advanced-search { padding:20px; background:#f5f7fa; border-radius:8px; margin-bottom:20px; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.toolbar-left,.toolbar-right { display:flex; gap:12px; flex-wrap:wrap; }
.data-table :deep(.el-table__header th) { font-weight:600; color:#303133; }
.mono-text { font-family:'Courier New',monospace; }
.text-success { color:#67C23A; }
.text-danger { color:#F56C6C; }
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
