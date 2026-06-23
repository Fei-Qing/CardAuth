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
        <el-table-column prop="duration_days" label="有效天数" width="100" align="center" sortable="custom" v-if="visibleColumns.duration_days"><template #default="{ row }"><span class="days-pill" :class="row.duration_days==0?'days-permanent':'days-limited'">{{ row.duration_days==0?'永久':row.duration_days+'天' }}</span></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><span class="status-pill" :class="'status-' + row.status">{{ row.status === 'unused' ? '未使用' : row.status === 'used' ? '已使用' : '已禁用' }}</span></template></el-table-column>
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
    <el-dialog v-model="detailVisible" width="520px" destroy-on-close :show-close="false">
      <template #header="{ close }">
        <div class="detail-header">
          <div class="detail-header-left">
            <div class="detail-avatar" :class="detailStatusClass">
              <svg v-if="detail?.status === 'unused'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <svg v-else-if="detail?.status === 'used'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <svg v-else viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            <div>
              <h4 class="detail-header-title">卡密详情 #{{ detail?.id }}</h4>
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
          <span class="detail-row-label">卡密信息</span>
          <div class="detail-row"><span class="detail-row-key">卡密</span><span class="detail-row-val mono">{{ detail.card_key }}</span></div>
          <div class="detail-row" style="justify-content:flex-end; border-top:none; padding-top:4px;"><el-button type="primary" size="small" @click="copyToClipboard(detail.card_key)" :icon="DocumentCopy" plain>复制卡密</el-button></div>
        </div>
        <div class="detail-row-group">
          <span class="detail-row-label">套餐与项目</span>
          <div class="detail-row"><span class="detail-row-key">项目</span><span class="detail-row-val">{{ detail.project_name || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">类型</span><span class="detail-row-val">{{ detail.card_type_name }}</span></div>
          <div class="detail-row"><span class="detail-row-key">有效天数</span><span class="detail-row-val"><span v-if="detail.duration_days == 0" class="detail-tag green">永久</span><span v-else class="detail-tag blue">{{ detail.duration_days }}天</span></span></div>
          <div class="detail-row"><span class="detail-row-key">过期时间</span><span class="detail-row-val">{{ detail.duration_days == 0 ? '永久有效' : (detail.expire_time || '-') }}</span></div>
          <div class="detail-row"><span class="detail-row-key">生成时间</span><span class="detail-row-val">{{ detail.created_at }}</span></div>
        </div>
        <div class="detail-row-group" v-if="detail.status !== 'unused'">
          <span class="detail-row-label">使用记录</span>
          <div class="detail-row"><span class="detail-row-key">机器人QQ</span><span class="detail-row-val mono">{{ detail.bot_qq || '-' }}</span></div>
          <div class="detail-row"><span class="detail-row-key">绑定时间</span><span class="detail-row-val">{{ detail.bound_at || '-' }}</span></div>
        </div>
        <div class="detail-row-group" v-if="detail.bind_info && Object.keys(detail.bind_info).length">
          <span class="detail-row-label">绑定详情</span>
          <pre class="detail-remark">{{ JSON.stringify(detail.bind_info, null, 2) }}</pre>
        </div>
      </div>
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
const statusMap = { unused: '未使用', used: '已使用', disabled: '已禁用' }
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
const detailStatusClass = computed(() => {
  if (!detail.value) return ''
  if (detail.value.status === 'used') return 'used'
  if (detail.value.status === 'disabled') return 'revoked'
  return 'unused'
})
const detailStatusText = computed(() => {
  if (!detail.value) return ''
  if (detail.value.status === 'used') return '已使用'
  if (detail.value.status === 'disabled') return '已禁用'
  return '未使用'
})

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
/* ========== 统计卡片 ========== */
.stats-row { margin-bottom:20px; }
.stat-card { display:flex; align-items:center; gap:12px; padding:14px 18px; border-radius:12px; background:#fff; border:1px solid #f0f0f4; transition:all .25s; cursor:default; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.05); }
.stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-icon :deep(.el-icon) { font-size:20px !important; }
.stat-total .stat-icon { background:#eff6ff; color:#3b82f6; }
.stat-unused .stat-icon { background:#f0fdf4; color:#22c55e; }
.stat-used .stat-icon { background:#f5f3ff; color:#8b5cf6; }
.stat-disabled .stat-icon { background:#fef2f2; color:#ef4444; }
.stat-info { display:flex; flex-direction:column; min-width:0; }
.stat-value { font-size:22px; font-weight:700; color:#1d1d1f; line-height:1.1; letter-spacing:-.3px; }
.stat-label { font-size:12px; color:#9ca3af; margin-top:2px; font-weight:500; }

/* ========== 主卡片 ========== */
.main-card { margin-bottom:20px; border-radius:16px; border:1px solid #f0f0f4; }
.main-card:deep(.el-card__body) { padding:24px; }

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
.status-unused { background:#f4f4f5; color:#71717a; }
.status-used { background:#f0fdf4; color:#16a34a; }
.status-disabled { background:#fef2f2; color:#dc2626; }

/* 有效天数 pill */
.days-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; }
.days-permanent { background:#f0fdf4; color:#16a34a; }
.days-limited { background:#eff6ff; color:#2563eb; }

.card-key-cell { display:flex; align-items:center; gap:8px; }
.copy-icon { cursor:pointer; color:#9ca3af; transition:color .2s; font-size:15px; }
.copy-icon:hover { color:#3b82f6; }
.card-key-text { font-family:'SF Mono','Cascadia Code','Courier New',monospace; font-size:13px; letter-spacing:.2px; }

.mono-text { font-family:'SF Mono','Cascadia Code','Courier New',monospace; font-size:13px; letter-spacing:.2px; }
.text-muted { color:#d1d5db; }
.text-success { color:#16a34a; }
.text-danger { color:#dc2626; }

/* ========== 详情弹窗 ========== */
.detail-header { display:flex; align-items:center; justify-content:space-between; padding:4px 0; }
.detail-header-left { display:flex; align-items:center; gap:14px; }
.detail-avatar { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.detail-avatar.unused { background:#f4f4f5; color:#909399; }
.detail-avatar.used { background:#f0f9eb; color:#67c23a; }
.detail-avatar.revoked { background:#fef0f0; color:#f56c6c; }
.detail-header-title { font-size:16px; font-weight:700; color:#1d1d1f; margin:0; line-height:1.3; }
.detail-header-sub { font-size:13px; color:#86868b; margin:2px 0 0; }
.detail-header-right { display:flex; align-items:center; gap:10px; }
.detail-status-badge { font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; letter-spacing:.3px; }
.detail-status-badge.unused { background:#f4f4f5; color:#909399; }
.detail-status-badge.used { background:#f0f9eb; color:#67c23a; }
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
.detail-remark { font-size:12px; color:#1d1d1f; margin:0; line-height:1.6; max-height:200px; overflow:auto; background:#f0f0f4; padding:12px; border-radius:8px; }

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
