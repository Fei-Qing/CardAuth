<template>
  <div class="cards-page">
    <!-- ========== 统计卡片 ========== -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total">
          <div class="stat-icon">
            <el-icon :size="32"><Tickets /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.total || 0 }}</div>
            <div class="stat-label">总卡密数</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-unused">
          <div class="stat-icon">
            <el-icon :size="32"><CircleCheck /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.unused || 0 }}</div>
            <div class="stat-label">未使用</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-used">
          <div class="stat-icon">
            <el-icon :size="32"><CircleClose /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.used || 0 }}</div>
            <div class="stat-label">已使用</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-disabled">
          <div class="stat-icon">
            <el-icon :size="32"><Remove /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.disabled || 0 }}</div>
            <div class="stat-label">已禁用</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- ========== 主卡片 ========== -->
    <el-card shadow="never" class="main-card">
      <!-- 高级搜索面板 -->
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="项目">
              <el-select v-model="filters.project_id" placeholder="全部项目" clearable style="width: 180px" @change="handleFilterChange">
                <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 140px" @change="handleFilterChange">
                <el-option label="未使用" value="unused" />
                <el-option label="已使用" value="used" />
                <el-option label="已禁用" value="disabled" />
              </el-select>
            </el-form-item>
            <el-form-item label="卡密">
              <el-input v-model="filters.keyword" placeholder="输入卡密搜索" clearable style="width: 200px" @keyup.enter="handleFilterChange" @clear="handleFilterChange" />
            </el-form-item>
            <el-form-item label="生成时间">
              <el-date-picker
                v-model="dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                value-format="YYYY-MM-DD"
                style="width: 260px"
                @change="handleDateChange"
              />
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
          <el-button type="primary" @click="showGenerateDialog" :icon="Plus">批量生成</el-button>
          <el-button type="success" @click="handleImport" :icon="Upload">导入</el-button>
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="info" :icon="Download">
              导出 ({{ selectedRows.length }})
              <el-icon class="el-icon--right"><ArrowDown /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="csv">导出 CSV</el-dropdown-item>
                <el-dropdown-item command="excel">导出 Excel</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
          <el-button type="danger" @click="handleBatchDelete" :disabled="!selectedRows.length" :icon="Delete">
            批量删除 ({{ selectedRows.length }})
          </el-button>
          <el-button type="warning" @click="handleBatchStatus" :disabled="!selectedRows.length" :icon="Switch">
            批量状态
          </el-button>
        </div>
        <div class="toolbar-right">
          <el-button @click="showAdvancedSearch = !showAdvancedSearch" :icon="Filter">
            {{ showAdvancedSearch ? '收起搜索' : '高级搜索' }}
          </el-button>
          <el-popover placement="bottom" :width="280" trigger="click">
            <template #reference>
              <el-button :icon="Setting">列设置</el-button>
            </template>
            <div class="column-settings">
              <div class="column-settings-title">显示/隐藏列</div>
              <el-checkbox
                v-for="col in columnOptions"
                :key="col.prop"
                v-model="col.visible"
                :label="col.label"
                @change="saveColumnSettings"
              />
            </div>
          </el-popover>
          <el-button @click="refreshData" :icon="Refresh" circle />
        </div>
      </div>

      <!-- 数据表格 -->
      <el-table
        ref="tableRef"
        :data="list"
        v-loading="loading"
        stripe
        border
        highlight-current-row
        @selection-change="handleSelectionChange"
        @sort-change="handleSortChange"
        row-key="id"
        :max-height="tableMaxHeight"
        class="data-table"
      >
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" sortable="custom" align="center" v-if="visibleColumns.id" />
        <el-table-column prop="card_key" label="卡密" min-width="200" show-overflow-tooltip sortable="custom" v-if="visibleColumns.card_key">
          <template #default="{ row }">
            <div class="card-key-cell">
              <el-icon class="copy-icon" @click="copyToClipboard(row.card_key)" title="复制"><DocumentCopy /></el-icon>
              <span class="card-key-text">{{ row.card_key }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="project_name" label="项目" width="130" sortable="custom" v-if="visibleColumns.project_name" />
        <el-table-column prop="card_type_name" label="类型" width="110" sortable="custom" v-if="visibleColumns.card_type_name" />
        <el-table-column prop="bot_qq" label="机器人QQ" width="140" sortable="custom" v-if="visibleColumns.bot_qq">
          <template #default="{ row }">
            <span v-if="row.bot_qq" class="mono-text">{{ row.bot_qq }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="duration_days" label="有效天数" width="110" align="center" sortable="custom" v-if="visibleColumns.duration_days">
          <template #default="{ row }">
            <el-tag :type="row.duration_days == 0 ? 'success' : 'info'" size="small">
              {{ row.duration_days == 0 ? '永久' : row.duration_days + '天' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'unused'" type="info" size="small">未使用</el-tag>
            <el-tag v-else-if="row.status === 'used'" type="success" size="small">已使用</el-tag>
            <el-tag v-else type="danger" size="small">已禁用</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="expire_time" label="过期时间" width="180" sortable="custom" v-if="visibleColumns.expire_time">
          <template #default="{ row }">
            <span v-if="row.duration_days == 0" class="text-success">永久有效</span>
            <span v-else :class="{ 'text-danger': row.expire_time && new Date(row.expire_time) < new Date() }">
              {{ row.expire_time || '-' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="生成时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="showDetail(row)" :icon="View">详情</el-button>
            <el-button 
              v-if="row.status !== 'used'" 
              size="small" 
              :type="row.status === 'disabled' ? 'success' : 'danger'" 
              link
              @click="toggleStatus(row)"
              :icon="row.status === 'disabled' ? 'CircleCheck' : 'CircleClose'"
            >
              {{ row.status === 'disabled' ? '启用' : '禁用' }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100, 200]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <!-- ========== 生成卡密弹窗 ========== -->
    <el-dialog v-model="genDialogVisible" title="批量生成卡密" width="500px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="genFormRef" :model="genForm" :rules="genRules" label-width="100px">
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="genForm.project_id" placeholder="请选择项目" style="width: 100%">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="套餐类型" prop="card_type_id">
          <el-select v-model="genForm.card_type_id" placeholder="请选择套餐" style="width: 100%" :disabled="!genForm.project_id">
            <el-option 
              v-for="t in genCardTypes" 
              :key="t.id" 
              :label="isAgent ? `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.agent_cost})` : `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.price})`" 
              :value="t.id" 
            />
          </el-select>
        </el-form-item>
        <el-form-item label="生成数量" prop="count">
          <el-input-number v-model="genForm.count" :min="1" :max="10000" style="width: 100%" />
        </el-form-item>
        <el-form-item v-if="isAgent && genCostPreview > 0" label="预估费用">
          <span style="color: #e6a23c; font-weight: 600; font-size: 16px;">¥{{ genCostPreview.toFixed(2) }}</span>
          <span style="color: #909399; font-size: 12px; margin-left: 8px;">（将从代理余额扣除）</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="genForm.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="genDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="genLoading" @click="handleGenerate">确认生成</el-button>
      </template>
    </el-dialog>

    <!-- ========== 详情弹窗 ========== -->
    <el-dialog v-model="detailVisible" title="卡密详情" width="600px" destroy-on-close>
      <el-descriptions :column="2" border v-if="detail">
        <el-descriptions-item label="ID" :span="1">{{ detail.id }}</el-descriptions-item>
        <el-descriptions-item label="状态" :span="1">
          <el-tag v-if="detail.status === 'unused'" type="info">未使用</el-tag>
          <el-tag v-else-if="detail.status === 'used'" type="success">已使用</el-tag>
          <el-tag v-else type="danger">已禁用</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="卡密" :span="2">
          <div class="card-key-detail">
            <span class="mono-text">{{ detail.card_key }}</span>
            <el-button type="primary" size="small" @click="copyToClipboard(detail.card_key)" :icon="DocumentCopy">复制</el-button>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="项目" :span="1">{{ detail.project_name }}</el-descriptions-item>
        <el-descriptions-item label="类型" :span="1">{{ detail.card_type_name }}</el-descriptions-item>
        <el-descriptions-item label="有效天数" :span="1">
          <el-tag :type="detail.duration_days == 0 ? 'success' : 'info'">
            {{ detail.duration_days == 0 ? '永久' : detail.duration_days + '天' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="过期时间" :span="1">
          <span v-if="detail.duration_days == 0" class="text-success">永久有效</span>
          <span v-else>{{ detail.expire_time || '-' }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="机器人QQ" :span="1">
          <span v-if="detail.bot_qq" class="mono-text">{{ detail.bot_qq }}</span>
          <span v-else class="text-muted">-</span>
        </el-descriptions-item>
        <el-descriptions-item label="绑定时间" :span="1">{{ detail.bound_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="生成时间" :span="2">{{ detail.created_at }}</el-descriptions-item>
        <el-descriptions-item label="绑定信息" :span="2">
          <pre v-if="detail.bind_info && Object.keys(detail.bind_info).length" class="bind-info-pre">{{ JSON.stringify(detail.bind_info, null, 2) }}</pre>
          <span v-else class="text-muted">无</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>

    <!-- ========== 导入弹窗 ========== -->
    <el-dialog v-model="importDialogVisible" title="导入卡密" width="500px" :close-on-click-modal="false" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item label="选择文件">
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            accept=".csv,.xlsx,.xls"
            :on-change="handleFileChange"
            :on-exceed="handleExceed"
          >
            <el-button type="primary" :icon="Upload">选择文件</el-button>
            <template #tip>
              <div class="el-upload__tip">支持 CSV、Excel 格式，文件大小不超过 10MB</div>
            </template>
          </el-upload>
        </el-form-item>
        <el-form-item label="导入项目">
          <el-select v-model="importProjectId" placeholder="请选择项目" style="width: 100%" @change="handleImportProjectChange">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="套餐">
          <el-select v-model="importCardTypeId" placeholder="请选择套餐" style="width: 100%" :disabled="!importProjectId">
            <el-option v-for="t in importCardTypes" :key="t.id" :label="isAgent ? `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.agent_cost})` : `${t.name} (${t.duration_days == 0 ? '永久' : t.duration_days + '天'} / ¥${t.price})`" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isAgent && importCostPreview > 0" label="预估费用">
          <span style="color: #e6a23c; font-weight: 600; font-size: 16px;">¥{{ importCostPreview.toFixed(2) }}</span>
          <span style="color: #909399; font-size: 12px; margin-left: 8px;">（将从代理余额扣除）</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="importLoading" @click="handleImportSubmit" :disabled="!importFile || !importProjectId || !importCardTypeId">开始导入</el-button>
      </template>
    </el-dialog>

    <!-- ========== 批量状态弹窗 ========== -->
    <el-dialog v-model="batchStatusDialogVisible" title="批量状态更新" width="400px" :close-on-click-modal="false" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item label="选中数量">
          <span>{{ selectedRows.length }} 条卡密</span>
        </el-form-item>
        <el-form-item label="目标状态">
          <el-select v-model="batchStatus" style="width: 100%">
            <el-option label="未使用" value="unused" />
            <el-option label="已禁用" value="disabled" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchStatusDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchStatusLoading" @click="handleBatchStatusSubmit">确认更新</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Delete, Download, Upload, Search, Refresh, Filter, Setting,
  View, DocumentCopy, ArrowDown, Tickets, CircleCheck, CircleClose, Remove, Switch
} from '@element-plus/icons-vue'
import request from '@/api'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const isAdmin = computed(() => user.value?.role === 'admin')
const isAgent = computed(() => user.value?.role === 'agent')

/* ===== 状态 ===== */
const list = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const stats = ref(null)
const projects = ref([])
const selectedRows = ref([])
const dateRange = ref([])
const showAdvancedSearch = ref(true)
const tableRef = ref(null)
const tableMaxHeight = ref(500)

const filters = reactive({
  project_id: '',
  status: '',
  keyword: '',
  start_date: '',
  end_date: ''
})

// 列设置
const columnOptions = ref([
  { prop: 'id', label: 'ID', visible: true },
  { prop: 'card_key', label: '卡密', visible: true },
  { prop: 'project_name', label: '项目', visible: true },
  { prop: 'card_type_name', label: '类型', visible: true },
  { prop: 'bot_qq', label: '机器人QQ', visible: true },
  { prop: 'duration_days', label: '有效天数', visible: true },
  { prop: 'status', label: '状态', visible: true },
  { prop: 'expire_time', label: '过期时间', visible: true },
  { prop: 'created_at', label: '生成时间', visible: true },
])

const visibleColumns = computed(() => {
  const obj = {}
  columnOptions.value.forEach(col => {
    obj[col.prop] = col.visible
  })
  return obj
})

// 生成
const genDialogVisible = ref(false)
const genFormRef = ref(null)
const genForm = ref({ project_id: '', card_type_id: '', count: 10, remark: '' })
const genRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  card_type_id: [{ required: true, message: '请选择套餐', trigger: 'change' }],
  count: [{ required: true, message: '请输入数量', trigger: 'blur' }],
}
const genLoading = ref(false)
const genCardTypes = ref([])
const genCostPreview = computed(() => {
  if (!isAgent.value) return 0
  const selected = genCardTypes.value.find(t => t.id === genForm.value.card_type_id)
  const cost = selected ? parseFloat(selected.agent_cost) || 0 : 0
  return cost * (genForm.value.count || 0)
})

// 详情
const detailVisible = ref(false)
const detail = ref(null)

// 导入
const importDialogVisible = ref(false)
const importLoading = ref(false)
const importFile = ref(null)
const importProjectId = ref('')
const importCardTypeId = ref('')
const importCardTypes = ref([])
const importLineCount = ref(0)
const uploadRef = ref(null)

const importCostPreview = computed(() => {
  if (!isAgent.value || !importCardTypeId.value || !importLineCount.value) return 0
  const selected = importCardTypes.value.find(t => t.id === importCardTypeId.value)
  const cost = selected ? parseFloat(selected.agent_cost) || 0 : 0
  return cost * importLineCount.value
})

// 批量状态
const batchStatusDialogVisible = ref(false)
const batchStatusLoading = ref(false)
const batchStatus = ref('unused')

/* ===== 生命周期 ===== */
onMounted(() => {
  loadColumnSettings()
  loadFilterMemory()
  fetchProjects()
  fetchData()
  fetchStats()
  calculateTableHeight()
  window.addEventListener('resize', calculateTableHeight)
})

onUnmounted(() => {
  window.removeEventListener('resize', calculateTableHeight)
})

/* ===== 数据获取 ===== */
async function fetchProjects() {
  try {
    const res = await request.get('/projects/all')
    projects.value = res.data
  } catch (e) {
    console.error('获取项目列表失败:', e)
  }
}

async function fetchData() {
  loading.value = true
  saveFilterMemory()
  try {
    const params = {
      page: page.value,
      page_size: pageSize.value,
      ...filters
    }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })
    const res = await request.get('/cards', { params })
    list.value = res.data.list
    total.value = res.data.total
  } catch (e) {
    console.error('获取卡密列表失败:', e)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await request.get('/cards/stats')
    stats.value = res.data
  } catch (e) {
    console.error('获取统计数据失败:', e)
  }
}

function refreshData() {
  fetchData()
  fetchStats()
  ElMessage.success('刷新成功')
}

/* ===== 事件处理 ===== */
function handleSelectionChange(selection) {
  selectedRows.value = selection
}

function handleSortChange({ prop, order }) {
  // 前端排序或后端排序
  fetchData()
}

function handleSizeChange(size) {
  pageSize.value = size
  page.value = 1
  fetchData()
}

function handleFilterChange() {
  page.value = 1
  fetchData()
}

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

function resetFilters() {
  filters.project_id = ''
  filters.status = ''
  filters.keyword = ''
  filters.start_date = ''
  filters.end_date = ''
  dateRange.value = []
  handleFilterChange()
}

/* ===== 生成卡密 ===== */
function showGenerateDialog() {
  genForm.value = { project_id: '', card_type_id: '', count: 10, remark: '' }
  genCardTypes.value = []
  genDialogVisible.value = true
}

watch(() => genForm.value.project_id, async (pid) => {
  if (!pid) {
    genCardTypes.value = []
    return
  }
  try {
    const res = await request.get(`/projects/${pid}/card-types`)
    let types = res.data || []
    // 代理只能看到有代理价格的套餐
    if (isAgent.value) {
      types = types.filter(t => parseFloat(t.agent_cost) > 0)
    }
    genCardTypes.value = types
  } catch (e) {
    console.error('获取套餐列表失败:', e)
  }
})

async function handleGenerate() {
  if (!genFormRef.value) return
  await genFormRef.value.validate(async (valid) => {
    if (!valid) return
    genLoading.value = true
    try {
      const res = await request.post('/cards/generate', genForm.value)
      ElMessage.success(res.message || '生成成功')
      genDialogVisible.value = false
      fetchData()
      fetchStats()
    } catch (e) {
      console.error('生成卡密失败:', e)
    } finally {
      genLoading.value = false
    }
  })
}

/* ===== 状态切换 ===== */
async function toggleStatus(row) {
  const newStatus = row.status === 'disabled' ? 'unused' : 'disabled'
  const action = newStatus === 'disabled' ? '禁用' : '启用'
  try {
    await ElMessageBox.confirm(`确定要${action}该卡密吗？`, '提示', {
      type: 'warning'
    })
    await request.patch(`/cards/${row.id}/status`, { status: newStatus })
    ElMessage.success(`${action}成功`)
    fetchData()
    fetchStats()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('状态切换失败:', e)
    }
  }
}

/* ===== 详情 ===== */
async function showDetail(row) {
  try {
    const res = await request.get(`/cards/${row.id}`)
    detail.value = res.data
    detailVisible.value = true
  } catch (e) {
    console.error('获取详情失败:', e)
  }
}

/* ===== 导出 ===== */
function handleExport(format) {
  if (!selectedRows.value.length) {
    ElMessage.warning('请先选择要导出的卡密')
    return
  }
  const params = new URLSearchParams()
  // 使用逗号分隔的 id 列表，避免 URL 重复参数在 PHP 中只保留最后一个值
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/cards/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}

/* ===== 导入 ===== */
function handleImport() {
  importFile.value = null
  importProjectId.value = ''
  importCardTypeId.value = ''
  importCardTypes.value = []
  importLineCount.value = 0
  if (uploadRef.value) {
    uploadRef.value.clearFiles()
  }
  importDialogVisible.value = true
}

function handleFileChange(file) {
  importFile.value = file.raw
  // 估算 CSV 行数（减去表头）
  const reader = new FileReader()
  reader.onload = (e) => {
    const text = e.target.result
    const lines = text.split('\n').filter(l => l.trim())
    importLineCount.value = Math.max(0, lines.length - 1)
  }
  reader.readAsText(file.raw)
}

function handleImportProjectChange(pid) {
  importCardTypeId.value = ''
  importCardTypes.value = []
  if (!pid) return
  request.get(`/projects/${pid}/card-types`).then(res => {
    let types = res.data || []
    if (isAgent.value) {
      types = types.filter(t => parseFloat(t.agent_cost) > 0)
    }
    importCardTypes.value = types
  }).catch(() => {})
}

function handleExceed() {
  ElMessage.warning('只能上传一个文件')
}

async function handleImportSubmit() {
  if (!importFile.value || !importProjectId.value || !importCardTypeId.value) return
  importLoading.value = true
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    formData.append('project_id', importProjectId.value)
    formData.append('card_type_id', importCardTypeId.value)
    const res = await request.post('/cards/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    ElMessage.success(res.message || '导入成功')
    importDialogVisible.value = false
    fetchData()
    fetchStats()
  } catch (e) {
    console.error('导入失败:', e)
  } finally {
    importLoading.value = false
  }
}

/* ===== 批量操作 ===== */
async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定要删除选中的 ${selectedRows.value.length} 条卡密吗？此操作不可恢复！`, '警告', {
      type: 'warning',
      confirmButtonText: '确定删除',
      cancelButtonText: '取消'
    })
    const ids = selectedRows.value.map(row => row.id)
    await request.post('/cards/batch-delete', { ids })
    ElMessage.success('批量删除成功')
    fetchData()
    fetchStats()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('批量删除失败:', e)
    }
  }
}

function handleBatchStatus() {
  if (!selectedRows.value.length) return
  batchStatus.value = 'unused'
  batchStatusDialogVisible.value = true
}

async function handleBatchStatusSubmit() {
  batchStatusLoading.value = true
  try {
    const ids = selectedRows.value.map(row => row.id)
    await request.post('/cards/batch-status', { ids, status: batchStatus.value })
    ElMessage.success('批量状态更新成功')
    batchStatusDialogVisible.value = false
    fetchData()
    fetchStats()
  } catch (e) {
    console.error('批量状态更新失败:', e)
  } finally {
    batchStatusLoading.value = false
  }
}

/* ===== 列设置 ===== */
function saveColumnSettings() {
  localStorage.setItem('cards_column_settings', JSON.stringify(columnOptions.value))
}

function loadColumnSettings() {
  const saved = localStorage.getItem('cards_column_settings')
  if (saved) {
    try {
      const settings = JSON.parse(saved)
      columnOptions.value = settings
    } catch (e) {
      console.error('加载列设置失败:', e)
    }
  }
}

/* ===== 筛选记忆 ===== */
function saveFilterMemory() {
  localStorage.setItem('cards_filter_memory', JSON.stringify(filters))
}

function loadFilterMemory() {
  const saved = localStorage.getItem('cards_filter_memory')
  if (saved) {
    try {
      const memory = JSON.parse(saved)
      Object.assign(filters, memory)
    } catch (e) {
      console.error('加载筛选记忆失败:', e)
    }
  }
}

/* ===== 工具函数 ===== */
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

function calculateTableHeight() {
  const windowHeight = window.innerHeight
  tableMaxHeight.value = windowHeight - 450
}
</script>

<style scoped>
.cards-page {
  max-width: 1600px;
  margin: 0 auto;
}

/* ========== 统计卡片 ========== */
.stats-row {
  margin-bottom: 20px;
}

/* ========== 主卡片 ========== */
.main-card {
  margin-bottom: 20px;
}

/* ========== 高级搜索 ========== */
.advanced-search {
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
  margin-bottom: 20px;
}

.search-form {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

/* ========== 工具栏 ========== */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.toolbar-left,
.toolbar-right {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* ========== 表格 ========== */
.cards-table {
  width: 100%;
}

.cards-table :deep(.el-table__header) {
  background: #f5f7fa;
}

.cards-table :deep(.el-table__header th) {
  font-weight: 600;
  color: #303133;
}

.cards-table :deep(.el-table__row) {
  transition: background 0.2s ease;
}

.cards-table :deep(.el-table__row:hover > td) {
  background: #ecf5ff !important;
}

.card-key-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.copy-icon {
  cursor: pointer;
  color: #409EFF;
  transition: color 0.2s ease;
}

.copy-icon:hover {
  color: #66b1ff;
}

.card-key-text {
  font-family: 'Courier New', monospace;
  font-size: 13px;
}

.mono-text {
  font-family: 'Courier New', monospace;
}

.text-muted {
  color: #c0c4cc;
}

.text-success {
  color: #67C23A;
}

.text-danger {
  color: #F56C6C;
}

.card-key-detail {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bind-info-pre {
  font-size: 12px;
  max-height: 200px;
  overflow: auto;
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  margin: 0;
}

/* ========== 分页 ========== */
.pagination-wrapper {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

/* ========== 列设置 ========== */
.column-settings {
  padding: 8px;
}

.column-settings-title {
  font-weight: 600;
  margin-bottom: 12px;
  color: #303133;
}

.column-settings .el-checkbox {
  display: block;
  margin-bottom: 8px;
}

/* ========== 过渡动画 ========== */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
  overflow: hidden;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 200px;
}

/* ========== 响应式 ========== */
@media (max-width: 768px) {
  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  
  .toolbar-left,
  .toolbar-right {
    width: 100%;
    justify-content: flex-start;
  }
  
  .search-form {
    flex-direction: column;
  }
  
  .search-form .el-form-item {
    width: 100%;
  }
  
  .search-form .el-select,
  .search-form .el-input,
  .search-form .el-date-picker {
    width: 100% !important;
  }
}

@media (max-width: 480px) {
  .stats-row :deep(.el-col) {
    margin-bottom: 12px;
  }
}
</style>
