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
        <el-table-column prop="duration_days" label="有效天数" width="100" align="center" sortable="custom" v-if="visibleColumns.duration_days"><template #default="{ row }"><span class="days-pill" :class="row.duration_days==0?'days-permanent':'days-limited'">{{ row.duration_days==0?'永久':row.duration_days+'天' }}</span></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><span v-if="row.status==='active' && !row.is_expired" class="status-pill status-active">有效</span><span v-else-if="row.is_expired" class="status-pill status-expired">已过期</span><span v-else-if="row.status==='revoked'" class="status-pill status-revoked">已撤销</span><span v-else class="status-pill status-unknown">{{ row.status }}</span></template></el-table-column>
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
    <el-dialog v-model="detailDialogVisible" width="520px" destroy-on-close :show-close="false">
      <template #header="{ close }">
        <div class="detail-header">
          <div class="detail-header-left">
            <div class="detail-avatar" :class="detailStatusClass">
              <svg v-if="detail?.status === 'active' && !detail?.is_expired" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <svg v-else-if="detail?.is_expired" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <svg v-else viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div>
              <h4 class="detail-header-title">授权详情 #{{ detail?.id }}</h4>
              <p class="detail-header-sub">{{ detail?.project_name || '-' }}</p>
            </div>
          </div>
          <div class="detail-header-right">
            <span class="detail-status-badge" :class="detailStatusClass">{{ detailStatusText }}</span>
            <button class="detail-close-btn" @click="close" type="button" aria-label="关闭"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
        </div>
      </template>
      <div v-if="detail" class="detail-body">
        <div class="detail-row-group">
          <span class="detail-row-label">机器人与联系人</span>
          <div class="detail-row"><span class="detail-row-key">机器人QQ</span><span class="detail-row-val mono">{{ detail.bot_qq }}</span></div>
          <div class="detail-row"><span class="detail-row-key">联系人QQ</span><span class="detail-row-val mono">{{ detail.contact_qq }}</span></div>
          <div class="detail-row"><span class="detail-row-key">联系人</span><span class="detail-row-val">{{ detail.contact_name || '-' }}</span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">授权信息</span>
          <div class="detail-row"><span class="detail-row-key">项目</span><span class="detail-row-val">{{ detail.project_name || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">有效天数</span><span class="detail-row-val"><span v-if="detail.duration_days == 0" class="detail-tag green">永久</span><span v-else class="detail-tag blue">{{ detail.duration_days }}天</span></span></div>
          <div class="detail-row"><span class="detail-row-key">授权时间</span><span class="detail-row-val">{{ detail.authorized_at || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">过期时间</span><span class="detail-row-val" :class="{ 'text-red': detail.is_expired }">{{ detail.duration_days == 0 ? '永久有效' : (detail.expire_time || '-') }}</span></div>
        </div>
        <div v-if="detail.revoked_at" class="detail-row-group">
          <span class="detail-row-label">撤销记录</span>
          <div class="detail-row"><span class="detail-row-key">撤销时间</span><span class="detail-row-val text-red">{{ detail.revoked_at }}</span></div>
          <div class="detail-row" v-if="detail.revoke_reason"><span class="detail-row-key">撤销原因</span><span class="detail-row-val">{{ detail.revoke_reason }}</span></div>
        </div>
        <div v-if="detail.remark" class="detail-row-group"><span class="detail-row-label">备注</span><p class="detail-remark">{{ detail.remark }}</p></div>
      </div>
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
const detailStatusClass = computed(() => {
  if (!detail.value) return ''
  if (detail.value.is_expired) return 'expired'
  if (detail.value.status === 'revoked') return 'revoked'
  return 'active'
})
const detailStatusText = computed(() => {
  if (!detail.value) return ''
  if (detail.value.is_expired) return '已过期'
  if (detail.value.status === 'revoked') return '已撤销'
  return '有效'
})
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

/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-active .stat-icon { background:#f0fdf4; color:#22c55e; }
.stat-expired .stat-icon { background:#fef3c7; color:#f59e0b; }
.stat-revoked .stat-icon { background:#fef2f2; color:#ef4444; }
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

/* 状态 pill */
.status-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; letter-spacing:.2px; }
.status-active { background:#f0fdf4; color:#16a34a; }
.status-expired { background:#fef3c7; color:#d97706; }
.status-revoked { background:#fef2f2; color:#dc2626; }
.status-unknown { background:#f4f4f5; color:#71717a; }

/* 有效天数 pill */
.days-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; }
.days-permanent { background:#f0fdf4; color:#16a34a; }
.days-limited { background:#eff6ff; color:#2563eb; }

.mono-text { font-family:'SF Mono','Cascadia Code','Courier New',monospace; font-size:13px; letter-spacing:.2px; }
.text-success { color:#16a34a; }
.text-danger { color:#dc2626; }
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
.detail-avatar.active { background:#f0f9eb; color:#67c23a; }
.detail-avatar.expired { background:#fdf6ec; color:#e6a23c; }
.detail-avatar.revoked { background:#fef0f0; color:#f56c6c; }
.detail-header-title { font-size:16px; font-weight:700; color:#1d1d1f; margin:0; line-height:1.3; }
.detail-header-sub { font-size:13px; color:#86868b; margin:2px 0 0; }
.detail-header-right { display:flex; align-items:center; gap:10px; }
.detail-status-badge { font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; letter-spacing:.3px; }
.detail-status-badge.active { background:#f0f9eb; color:#67c23a; }
.detail-status-badge.expired { background:#fdf6ec; color:#e6a23c; }
.detail-status-badge.revoked { background:#fef0f0; color:#f56c6c; }
.detail-close-btn { width:32px; height:32px; border-radius:50%; border:none; background:#f5f5f7; color:#86868b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; }
.detail-close-btn:hover { background:#e8e8ed; color:#1d1d1f; }

.detail-body { display:flex; flex-direction:column; gap:20px; margin-top:8px; }
.detail-row-group { background:#f8f9fb; border-radius:12px; padding:16px 18px 14px; }
.detail-row-label { display:block; font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
.detail-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; }
.detail-row + .detail-row { border-top:1px solid #f0f0f4; }
.detail-row-key { font-size:13px; color:#6e6e73; }
.detail-row-val { font-size:13px; font-weight:600; color:#1d1d1f; text-align:right; max-width:60%; word-break:break-all; }
.detail-row-val.mono { font-family:'SF Mono','Courier New',monospace; font-size:13px; letter-spacing:.3px; }
.detail-tag { display:inline-block; padding:2px 10px; border-radius:6px; font-size:12px; font-weight:600; }
.detail-tag.green { background:#f0f9eb; color:#67c23a; }
.detail-tag.blue { background:#ecf5ff; color:#409eff; }
.text-red { color:#f56c6c !important; }
.detail-remark { font-size:13px; color:#1d1d1f; margin:0; line-height:1.6; }

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
</style>
