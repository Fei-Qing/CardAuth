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

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="用户详情" size="500px" direction="rtl">
      <div v-if="currentUser" class="detail-content">
        <div class="detail-header">
          <el-avatar :size="80" :src="currentUser.avatar" :icon="UserFilled">{{ currentUser.nickname?.charAt(0) || currentUser.username.charAt(0) }}</el-avatar>
          <div class="detail-header-info">
            <h3>{{ currentUser.nickname || currentUser.username }}</h3>
            <el-tag :type="getRoleTagType(currentUser.role)" effect="dark">{{ getRoleLabel(currentUser.role) }}</el-tag>
            <el-tag :type="currentUser.status === 1 ? 'success' : 'danger'" size="small">{{ currentUser.status === 1 ? '正常' : '禁用' }}</el-tag>
          </div>
        </div>
        <el-descriptions :column="1" border>
          <el-descriptions-item label="用户ID">{{ currentUser.id }}</el-descriptions-item>
          <el-descriptions-item label="用户名">{{ currentUser.username }}</el-descriptions-item>
          <el-descriptions-item label="昵称">{{ currentUser.nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ currentUser.email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="角色"><el-tag :type="getRoleTagType(currentUser.role)">{{ getRoleLabel(currentUser.role) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="currentUser.status === 1 ? 'success' : 'danger'">{{ currentUser.status === 1 ? '正常' : '禁用' }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="最后登录时间">{{ currentUser.last_login_at || '从未登录' }}</el-descriptions-item>
          <el-descriptions-item label="最后登录IP">{{ currentUser.last_login_ip || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentUser.created_at }}</el-descriptions-item>
          <el-descriptions-item label="备注" v-if="currentUser.remark">{{ currentUser.remark }}</el-descriptions-item>
        </el-descriptions>
        <div class="detail-actions">
          <el-button type="primary" @click="showEditDialog(currentUser)">编辑用户</el-button>
          <el-button type="danger" @click="handleDelete(currentUser)" :disabled="currentUser.role === 'admin'">删除用户</el-button>
        </div>
      </div>
    </el-drawer>
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
.user-cell { display: flex; align-items: center; gap: 10px; }
.user-info { display: flex; flex-direction: column; }
.user-name { font-weight: 500; }
.user-id { font-size: 12px; color: #909399; }
.time-cell { display: flex; align-items: center; gap: 6px; }
.detail-content { padding: 0 10px; }
.detail-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
.detail-header-info h3 { margin: 0 0 10px 0; }
.detail-actions { margin-top: 24px; display: flex; gap: 12px; }
.role-option { display: flex; align-items: center; gap: 8px; }
.role-desc { color: #909399; font-size: 12px; }
</style>
