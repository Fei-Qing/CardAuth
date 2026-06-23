<template>
  <div class="users-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total">
          <div class="stat-icon"><el-icon :size="32"><UserFilled /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.total || total }}</div><div class="stat-label">总用户数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-admin">
          <div class="stat-icon"><el-icon :size="32"><User /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.admin || 0 }}</div><div class="stat-label">管理员</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-project">
          <div class="stat-icon"><el-icon :size="32"><Avatar /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.projectAdmin || 0 }}</div><div class="stat-label">项目管理员</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-agent">
          <div class="stat-icon"><el-icon :size="32"><Avatar /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ stats?.agent || 0 }}</div><div class="stat-label">代理</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <!-- 高级搜索 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="关键词">
              <el-input v-model="filters.keyword" placeholder="搜索用户名/昵称/邮箱" clearable style="width: 220px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" />
            </el-form-item>
            <el-form-item label="角色">
              <el-select v-model="filters.role" placeholder="角色筛选" clearable style="width: 140px" @change="handleFilterChange">
                <el-option label="全部角色" value="" />
                <el-option label="超级管理员" value="admin" />
                <el-option label="项目管理员" value="project_admin" />
                <el-option label="代理" value="agent" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="状态筛选" clearable style="width: 120px" @change="handleFilterChange">
                <el-option label="正常" :value="1" />
                <el-option label="禁用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item label="创建时间">
              <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" style="width: 260px" @change="handleDateChange" />
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
          <el-button type="primary" @click="showCreateDialog" :icon="Plus">创建用户</el-button>
          <el-button type="danger" :icon="Delete" @click="handleBatchDelete" :disabled="!selectedRows.length">批量删除 ({{ selectedRows.length }})</el-button>
          <el-button type="success" :icon="Download" @click="handleExport">导出</el-button>
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

      <!-- 数据表格 -->
      <el-table ref="tableRef" :data="list" v-loading="loading" stripe border highlight-current-row @selection-change="handleSelectionChange" @sort-change="handleSortChange" row-key="id" :max-height="tableMaxHeight" class="data-table">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="80" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="username" label="用户名" width="140" sortable="custom" v-if="visibleColumns.username">
          <template #default="{ row }">
            <div class="user-cell">
              <el-avatar :size="36" :src="row.avatar" :icon="UserFilled" />
              <div class="user-info"><div class="user-name">{{ row.username }}</div><div class="user-id">ID: {{ row.id }}</div></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="昵称" width="140" sortable="custom" v-if="visibleColumns.nickname"><template #default="{ row }"><span>{{ row.nickname || '-' }}</span></template></el-table-column>
        <el-table-column prop="email" label="邮箱" min-width="200" show-overflow-tooltip v-if="visibleColumns.email" />
        <el-table-column prop="role" label="角色" width="130" align="center" v-if="visibleColumns.role"><template #default="{ row }"><el-tag :type="getRoleTagType(row.role)" effect="dark" size="default">{{ getRoleLabel(row.role) }}</el-tag></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" v-if="visibleColumns.status"><template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" :disabled="row.role === 'admin'" /></template></el-table-column>
        <el-table-column prop="last_login_at" label="最后登录" width="180" sortable="custom" v-if="visibleColumns.last_login_at"><template #default="{ row }"><div class="time-cell"><el-icon><Clock /></el-icon><span>{{ row.last_login_at || '从未登录' }}</span></div></template></el-table-column>
        <el-table-column prop="last_login_ip" label="登录IP" width="140" show-overflow-tooltip v-if="visibleColumns.last_login_ip"><template #default="{ row }"><span>{{ row.last_login_ip || '-' }}</span></template></el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at"><template #default="{ row }"><div class="time-cell"><el-icon><Calendar /></el-icon><span>{{ row.created_at }}</span></div></template></el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link :icon="View" @click="showDetailDialog(row)">详情</el-button>
            <el-button type="warning" link :icon="Edit" @click="showEditDialog(row)">编辑</el-button>
            <el-button type="danger" link :icon="Delete" @click="handleDelete(row)" :disabled="row.role === 'admin'">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 创建/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑用户' : '创建用户'" width="600px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" label-position="right">
        <el-form-item label="用户名" prop="username"><el-input v-model="form.username" :disabled="isEdit" placeholder="请输入用户名" :prefix-icon="User" maxlength="50" show-word-limit /></el-form-item>
        <el-form-item label="密码" :prop="isEdit ? '' : 'password'"><el-input v-model="form.password" type="password" show-password :placeholder="isEdit ? '留空则不修改密码' : '请输入密码（至少6位）'" :prefix-icon="Lock" maxlength="50" /></el-form-item>
        <el-form-item label="昵称" prop="nickname"><el-input v-model="form.nickname" placeholder="请输入昵称" :prefix-icon="UserFilled" maxlength="50" show-word-limit /></el-form-item>
        <el-form-item label="邮箱" prop="email"><el-input v-model="form.email" placeholder="请输入邮箱" :prefix-icon="Message" maxlength="100" /></el-form-item>
        <el-form-item label="角色" prop="role" v-if="!isEdit">
          <el-select v-model="form.role" placeholder="请选择角色" style="width: 100%">
            <el-option label="超级管理员" value="admin"><div class="role-option"><el-tag type="danger" size="small">超级管理员</el-tag><span class="role-desc">拥有所有权限</span></div></el-option>
            <el-option label="项目管理员" value="project_admin"><div class="role-option"><el-tag type="primary" size="small">项目管理员</el-tag><span class="role-desc">管理项目和商品</span></div></el-option>
            <el-option label="代理" value="agent"><div class="role-option"><el-tag type="warning" size="small">代理</el-tag><span class="role-desc">查看和管理自己的数据</span></div></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="负责项目" prop="project_ids" v-if="form.role === 'project_admin'">
          <el-select v-model="form.project_ids" multiple placeholder="请选择负责的项目" style="width: 100%">
            <el-option v-for="p in allProjects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="正常" inactive-text="禁用" /></el-form-item>
        <el-form-item label="备注" prop="remark"><el-input v-model="form.remark" type="textarea" :rows="3" placeholder="备注信息（选填）" maxlength="200" show-word-limit /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="submitLoading" @click="handleSubmit">{{ isEdit ? '保存修改' : '确认创建' }}</el-button></template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" width="520px" destroy-on-close :show-close="false">
      <template #header="{ close }">
        <div class="detail-header">
          <div class="detail-header-left">
            <div class="detail-avatar" :class="currentUser?.status === 1 ? 'active' : 'disabled'">
              <svg v-if="currentUser?.status === 1" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <svg v-else viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            <div>
              <h4 class="detail-header-title">{{ currentUser?.nickname || currentUser?.username }}</h4>
              <p class="detail-header-sub">ID: #{{ currentUser?.id }} · {{ currentUser?.username }}</p>
            </div>
          </div>
          <div class="detail-header-right">
            <span class="detail-status-badge" :class="currentUser?.status === 1 ? 'active' : 'disabled'">{{ currentUser?.status === 1 ? '正常' : '已禁用' }}</span>
            <button class="detail-close-btn" @click="close" type="button" aria-label="关闭"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
        </div>
      </template>
      <div v-if="currentUser" class="detail-body">
        <div class="detail-row-group">
          <span class="detail-row-label">基本信息</span>
          <div class="detail-row"><span class="detail-row-key">用户名</span><span class="detail-row-val">{{ currentUser.username }}</span></div>
          <div class="detail-row"><span class="detail-row-key">昵称</span><span class="detail-row-val">{{ currentUser.nickname || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">邮箱</span><span class="detail-row-val">{{ currentUser.email || '-' }}</span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">角色与状态</span>
          <div class="detail-row"><span class="detail-row-key">角色</span><span class="detail-row-val"><span class="role-pill" :class="'role-' + currentUser.role">{{ getRoleLabel(currentUser.role) }}</span></span></div>
          <div class="detail-row"><span class="detail-row-key">状态</span><span class="detail-row-val"><span :class="currentUser.status === 1 ? 'text-success' : 'text-danger'">{{ currentUser.status === 1 ? '正常' : '已禁用' }}</span></span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">登录记录</span>
          <div class="detail-row"><span class="detail-row-key">最后登录</span><span class="detail-row-val">{{ currentUser.last_login_at || '从未登录' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">登录 IP</span><span class="detail-row-val mono">{{ currentUser.last_login_ip || '-' }}</span></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">时间</span>
          <div class="detail-row"><span class="detail-row-key">创建时间</span><span class="detail-row-val">{{ currentUser.created_at }}</span></div>
        </div>
        <div v-if="currentUser.remark" class="detail-row-group">
          <span class="detail-row-label">备注</span>
          <p class="detail-remark">{{ currentUser.remark }}</p>
        </div>
        <div class="detail-actions">
          <el-button type="primary" @click="showEditDialog(currentUser)">编辑用户</el-button>
          <el-button type="danger" @click="handleDelete(currentUser)" :disabled="currentUser.role === 'admin'">删除用户</el-button>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { Plus, Delete, Download, Search, Refresh, Filter, Setting, Edit, View, User, UserFilled, Avatar, Lock, Message, Clock, Calendar } from '@element-plus/icons-vue'
import request from '@/api'

const showAdvancedSearch = ref(true)
const dateRange = ref([])
const allProjects = ref([])

const { list, loading, page, pageSize, total, stats, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/users',
  statsUrl: '/users/stats',
  defaultFilters: { keyword: '', role: '', status: '', start_date: '', end_date: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('users_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'username', label: '用户名' },
  { prop: 'nickname', label: '昵称' },
  { prop: 'email', label: '邮箱' },
  { prop: 'role', label: '角色' },
  { prop: 'status', label: '状态' },
  { prop: 'last_login_at', label: '最后登录' },
  { prop: 'last_login_ip', label: '登录IP' },
  { prop: 'created_at', label: '创建时间' },
])

const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const form = ref({
  username: '',
  password: '',
  nickname: '',
  email: '',
  role: 'agent',
  status: 1,
  remark: '',
  project_ids: []
})
const formRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 50, message: '长度在 3 到 50 个字符', trigger: 'blur' }
  ],
  password: [
    { required: true, min: 6, message: '密码至少6位', trigger: 'blur' }
  ],
  role: [
    { required: true, message: '请选择角色', trigger: 'change' }
  ],
  email: [
    { type: 'email', message: '请输入正确的邮箱地址', trigger: 'blur' }
  ]
}
const submitLoading = ref(false)
let editingId = null

const detailVisible = ref(false)
const currentUser = ref(null)

function handleDateChange(val) {
  if (val) {
    filters.start_date = val[0]
    filters.end_date = val[1]
  } else {
    filters.start_date = ''
    filters.end_date = ''
  }
  handleFilterChange()
}

async function handleStatusChange(row) {
  try {
    await request.put(`/users/${row.id}`, { status: row.status })
    ElMessage.success('状态更新成功')
  } catch (e) {
    row.status = row.status === 1 ? 0 : 1
    console.error('更新状态失败:', e)
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      `确定要删除用户 "${row.username}" 吗？此操作不可恢复。`,
      '删除确认',
      { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' }
    )
    await request.delete(`/users/${row.id}`)
    ElMessage.success('删除成功')
    refreshData()
  } catch (e) {
    if (e !== 'cancel') console.error('删除失败:', e)
  }
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 个用户吗？此操作不可恢复。`,
      '批量删除确认',
      { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' }
    )
    const ids = selectedRows.value.map(u => u.id)
    await request.post('/users/batch-delete', { ids })
    ElMessage.success('批量删除成功')
    refreshData()
  } catch (e) {
    if (e !== 'cancel') console.error('批量删除失败:', e)
  }
}

function handleExport() {
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.length ? selectedRows.value.map(u => u.id).join(',') : '')
  params.append('format', 'csv')
  const token = localStorage.getItem('access_token')
  window.open(`/api/users/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}

function showCreateDialog() {
  isEdit.value = false
  editingId = null
  form.value = { username: '', password: '', nickname: '', email: '', role: 'agent', status: 1, remark: '', project_ids: [] }
  dialogVisible.value = true
}

function showEditDialog(row) {
  isEdit.value = true
  editingId = row.id
  let pids = []
  try { pids = typeof row.project_ids === 'string' ? JSON.parse(row.project_ids) : (row.project_ids || []) } catch (e) { pids = [] }
  form.value = {
    username: row.username,
    password: '',
    nickname: row.nickname || '',
    email: row.email || '',
    role: row.role,
    status: row.status,
    remark: row.remark || '',
    project_ids: pids
  }
  dialogVisible.value = true
}

function showDetailDialog(row) {
  currentUser.value = row
  detailVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      const payload = {
        nickname: form.value.nickname,
        email: form.value.email,
        status: form.value.status,
        remark: form.value.remark
      }
      if (form.value.password) payload.password = form.value.password
      if (form.value.role === 'project_admin' || (isEdit.value && form.value.project_ids !== undefined)) {
        payload.project_ids = form.value.project_ids
      }
      if (!isEdit.value) {
        payload.username = form.value.username
        payload.role = form.value.role
        await request.post('/users', payload)
        ElMessage.success('创建成功')
      } else {
        await request.put(`/users/${editingId}`, payload)
        ElMessage.success('更新成功')
      }
      dialogVisible.value = false
      refreshData()
    } catch (e) {
      console.error('提交失败:', e)
    } finally {
      submitLoading.value = false
    }
  })
}

function getRoleTagType(role) {
  const map = { admin: 'danger', project_admin: 'primary', agent: 'warning' }
  return map[role] || 'info'
}

function getRoleLabel(role) {
  const map = { admin: '超级管理员', project_admin: '项目管理员', agent: '代理' }
  return map[role] || role
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
.users-page { max-width:1600px; margin:0 auto; }

/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-admin .stat-icon { background:#fef2f2; color:#ef4444; }
.stat-project .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-agent .stat-icon { background:#fef3c7; color:#f59e0b; }
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

.user-cell { display:flex; align-items:center; gap:10px; }
.user-info { display:flex; flex-direction:column; }
.user-name { font-weight:500; }
.user-id { font-size:12px; color:#909399; }
.time-cell { display:flex; align-items:center; gap:6px; }

/* ========== 详情弹窗 ========== */
.detail-header { display:flex; align-items:center; justify-content:space-between; padding:4px 0; }
.detail-header-left { display:flex; align-items:center; gap:14px; }
.detail-avatar { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.detail-avatar.active { background:#f0f9eb; color:#67c23a; }
.detail-avatar.disabled { background:#fef0f0; color:#f56c6c; }
.detail-header-title { font-size:16px; font-weight:700; color:#1d1d1f; margin:0; line-height:1.3; }
.detail-header-sub { font-size:13px; color:#86868b; margin:2px 0 0; }
.detail-header-right { display:flex; align-items:center; gap:10px; }
.detail-status-badge { font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; letter-spacing:.3px; }
.detail-status-badge.active { background:#f0f9eb; color:#67c23a; }
.detail-status-badge.disabled { background:#fef0f0; color:#f56c6c; }
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
.detail-remark { font-size:13px; color:#1d1d1f; margin:0; line-height:1.6; }
.detail-actions { margin-top:4px; display:flex; gap:12px; justify-content:flex-end; }

/* 角色 pill */
.role-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; letter-spacing:.2px; }
.role-pill.role-admin { background:#fef2f2; color:#dc2626; }
.role-pill.role-project_admin { background:#eff6ff; color:#2563eb; }
.role-pill.role-agent { background:#fef3c7; color:#d97706; }
.role-pill.role-user { background:#f4f4f5; color:#71717a; }
.text-success { color:#16a34a; }
.text-danger { color:#dc2626; }
.role-option { display:flex; align-items:center; gap:8px; }
.role-desc { color:#909399; font-size:12px; }
.pagination-wrapper { margin-top:20px; display:flex; justify-content:flex-end; }

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
