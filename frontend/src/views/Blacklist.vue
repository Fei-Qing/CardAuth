<template>
  <div class="blacklist-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-total">
          <div class="stat-icon"><el-icon :size="32"><Document /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ total }}</div><div class="stat-label">总记录数</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-active">
          <div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ activeCount }}</div><div class="stat-label">启用中</div></div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="8">
        <div class="stat-card stat-expired">
          <div class="stat-icon"><el-icon :size="32"><Timer /></el-icon></div>
          <div class="stat-info"><div class="stat-value">{{ expiredCount }}</div><div class="stat-label">已过期</div></div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <!-- 高级搜索 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="目标类型">
              <el-select v-model="filters.target_type" placeholder="全部类型" clearable style="width: 120px" @change="handleFilterChange">
                <el-option label="用户ID" value="user" />
                <el-option label="IP地址" value="ip" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange">
                <el-option label="启用" :value="1" />
                <el-option label="禁用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item label="关键词">
              <el-input v-model="filters.keyword" placeholder="目标值/备注" clearable style="width: 200px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :icon="Search" @click="handleFilterChange">查询</el-button>
              <el-button :icon="Refresh" @click="resetFilters">重置</el-button>
            </el-form-item>
          </el-form>
        </div>
      </transition>

      <!-- 工具栏 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" :icon="Plus" @click="handleAdd">添加黑名单</el-button>
          <el-button type="danger" :icon="Delete" :disabled="!selectedRows.length" @click="handleBatchDelete">批量删除 ({{ selectedRows.length }})</el-button>
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="info" :icon="Download">导出 ({{ selectedRows.length }})<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown>
              <el-dropdown-menu><el-dropdown-item command="csv">导出 CSV</el-dropdown-item></el-dropdown-menu>
            </template>
          </el-dropdown>
          <el-button type="success" :icon="Upload" @click="importDialogVisible = true">导入</el-button>
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
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="target_type" label="类型" width="100" align="center" v-if="visibleColumns.target_type">
          <template #default="{ row }"><el-tag :type="row.target_type === 'user' ? 'primary' : 'warning'" size="small">{{ row.target_type === 'user' ? '用户ID' : 'IP地址' }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="target_value" label="目标值" min-width="160" show-overflow-tooltip v-if="visibleColumns.target_value" />
        <el-table-column prop="status" label="状态" width="90" align="center" v-if="visibleColumns.status">
          <template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="(val) => handleStatusChange(row, val)" /></template>
        </el-table-column>
        <el-table-column prop="expire_time" label="过期时间" width="160" align="center" sortable="custom" v-if="visibleColumns.expire_time"><template #default="{ row }">{{ row.expire_time || '永久' }}</template></el-table-column>
        <el-table-column prop="operator_name" label="操作人" width="120" align="center" v-if="visibleColumns.operator_name" />
        <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip v-if="visibleColumns.remark" />
        <el-table-column prop="created_at" label="添加时间" width="160" align="center" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 添加/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑黑名单' : '添加黑名单'" width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="目标类型" prop="target_type">
          <el-radio-group v-model="form.target_type" :disabled="isEdit">
            <el-radio-button label="用户ID" value="user" />
            <el-radio-button label="IP地址" value="ip" />
          </el-radio-group>
        </el-form-item>
        <el-form-item label="目标值" prop="target_value"><el-input v-model="form.target_value" placeholder="请输入用户ID或IP地址" :disabled="isEdit" /></el-form-item>
        <el-form-item label="过期时间" prop="expire_time">
          <el-date-picker v-model="form.expire_time" type="datetime" placeholder="留空表示永久有效" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注" prop="remark"><el-input v-model="form.remark" type="textarea" :rows="3" placeholder="可选填" maxlength="500" show-word-limit /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button></template>
    </el-dialog>

    <!-- 导入弹窗 -->
    <el-dialog v-model="importDialogVisible" title="导入黑名单" width="460px" :close-on-click-modal="false">
      <el-upload ref="uploadRef" action="" :auto-upload="false" :limit="1" :on-change="handleFileChange" accept=".csv">
        <el-button type="primary" :icon="Upload">选择 CSV 文件</el-button>
        <template #tip><div class="upload-tip">CSV 需包含 target_type、target_value 列，可选 expire_time、remark 列<br>target_type 取值：user 或 ip</div></template>
      </el-upload>
      <template #footer><el-button @click="importDialogVisible = false">取消</el-button><el-button type="primary" :loading="importLoading" @click="handleImportSubmit">开始导入</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { Search, Refresh, Plus, Delete, Download, Upload, Edit, Filter, Setting, ArrowDown, Document, CircleCheck, Timer } from '@element-plus/icons-vue'
import request from '@/api'

const showAdvancedSearch = ref(true)
const submitting = ref(false)
const importLoading = ref(false)
const dialogVisible = ref(false)
const importDialogVisible = ref(false)
const isEdit = ref(false)
const uploadRef = ref(null)
const importFile = ref(null)
const formRef = ref(null)

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/blacklists',
  defaultFilters: { target_type: '', status: '', keyword: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('blacklist_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'target_type', label: '类型' },
  { prop: 'target_value', label: '目标值' },
  { prop: 'status', label: '状态' },
  { prop: 'expire_time', label: '过期时间' },
  { prop: 'operator_name', label: '操作人' },
  { prop: 'remark', label: '备注' },
  { prop: 'created_at', label: '添加时间' },
])

const activeCount = computed(() => list.value.filter(i => i.status === 1).length)
const expiredCount = computed(() => list.value.filter(i => i.expire_time && new Date(i.expire_time) < new Date()).length)

const form = reactive({
  id: null,
  target_type: 'ip',
  target_value: '',
  expire_time: '',
  remark: '',
})

const rules = {
  target_type: [{ required: true, message: '请选择目标类型', trigger: 'change' }],
  target_value: [{ required: true, message: '请输入目标值', trigger: 'blur' }],
}

function resetForm() {
  form.id = null
  form.target_type = 'ip'
  form.target_value = ''
  form.expire_time = ''
  form.remark = ''
  if (formRef.value) formRef.value.resetFields()
}

function handleAdd() {
  isEdit.value = false
  resetForm()
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  form.id = row.id
  form.target_type = row.target_type
  form.target_value = row.target_value
  form.expire_time = row.expire_time || ''
  form.remark = row.remark || ''
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      if (isEdit.value) {
        await request.put(`/blacklists/${form.id}`, { expire_time: form.expire_time || null, remark: form.remark })
        ElMessage.success('黑名单更新成功')
      } else {
        await request.post('/blacklists', {
          target_type: form.target_type,
          target_value: form.target_value,
          expire_time: form.expire_time || null,
          remark: form.remark,
        })
        ElMessage.success('黑名单添加成功')
      }
      dialogVisible.value = false
      fetchData()
    } catch (e) { /* handled */ }
    finally { submitting.value = false }
  })
}

async function handleStatusChange(row, val) {
  try {
    await request.put(`/blacklists/${row.id}`, { status: val })
    ElMessage.success('状态更新成功')
  } catch (e) {
    row.status = val === 1 ? 0 : 1
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm('确定删除该黑名单吗？', '提示', { type: 'warning' })
    await request.delete(`/blacklists/${row.id}`)
    ElMessage.success('删除成功')
    fetchData()
  } catch (e) { if (e !== 'cancel') console.error(e) }
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 条黑名单吗？`, '警告', { type: 'warning' })
    const ids = selectedRows.value.map(row => row.id)
    await request.post('/blacklists/batch-delete', { ids })
    ElMessage.success('批量删除成功')
    fetchData()
  } catch (e) { if (e !== 'cancel') console.error(e) }
}

function handleExport() {
  if (!selectedRows.value.length) {
    ElMessage.warning('请先选择要导出的记录')
    return
  }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  const token = localStorage.getItem('access_token')
  window.open(`/api/blacklists/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}

function handleFileChange(file) {
  importFile.value = file.raw
}

async function handleImportSubmit() {
  if (!importFile.value) {
    ElMessage.warning('请选择 CSV 文件')
    return
  }
  const data = new FormData()
  data.append('file', importFile.value)
  importLoading.value = true
  try {
    const res = await request.post('/blacklists/import', data, { headers: { 'Content-Type': 'multipart/form-data' } })
    ElMessage.success(res.message || '导入完成')
    importDialogVisible.value = false
    importFile.value = null
    if (uploadRef.value) uploadRef.value.clearFiles()
    fetchData()
  } catch (e) { /* handled */ }
  finally { importLoading.value = false }
}
</script>

<style scoped>
.upload-tip { font-size: 12px; color: #909399; margin-top: 8px; line-height: 1.6; }
</style>
