<template>
  <div class="agents-page">
    <!-- 统计卡片（代理额度） -->
    <div v-if="!isAdmin && myQuota" class="quota-row">
      <el-row :gutter="20">
        <el-col :xs="12" :sm="8" :md="8" :lg="8">
          <div class="stat-card stat-total"><div class="stat-icon"><el-icon :size="32"><Wallet /></el-icon></div>
            <div class="stat-info"><div class="stat-value">¥{{ myQuota.total_quota?.toFixed(2) }}</div><div class="stat-label">总额度</div></div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="8" :lg="8">
          <div class="stat-card stat-used"><div class="stat-icon"><el-icon :size="32"><Money /></el-icon></div>
            <div class="stat-info"><div class="stat-value">¥{{ myQuota.used_quota?.toFixed(2) }}</div><div class="stat-label">已使用</div></div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="8" :lg="8">
          <div class="stat-card stat-active"><div class="stat-icon"><el-icon :size="32"><Coin /></el-icon></div>
            <div class="stat-info"><div class="stat-value">¥{{ myQuota.remain_quota?.toFixed(2) }}</div><div class="stat-label">剩余可用</div></div>
          </div>
        </el-col>
      </el-row>
    </div>

    <!-- 管理员代理列表 -->
    <el-card shadow="never" class="main-card" v-if="isAdmin">
      <transition name="expand">
        <div v-show="showAdvancedSearch" class="advanced-search">
          <el-form :inline="true" :model="filters" class="search-form" label-width="80px">
            <el-form-item label="关键词"><el-input v-model="filters.keyword" placeholder="用户名/昵称" clearable style="width: 220px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" /></el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange"><el-option label="正常" :value="1" /><el-option label="禁用" :value="0" /></el-select>
            </el-form-item>
            <el-form-item><el-button type="primary" @click="handleFilterChange" :icon="Search">搜索</el-button><el-button @click="resetFilters" :icon="Refresh">重置</el-button></el-form-item>
          </el-form>
        </div>
      </transition>

      <div class="toolbar">
        <div class="toolbar-left">
          <el-button type="primary" :icon="Plus" @click="showRechargeDialog(null)">充值额度</el-button>
          <el-dropdown @command="handleExport" trigger="click" :disabled="!selectedRows.length">
            <el-button type="success" :icon="Download">导出 ({{ selectedRows.length }})<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown><el-dropdown-menu><el-dropdown-item command="csv">导出 CSV</el-dropdown-item><el-dropdown-item command="excel">导出 Excel</el-dropdown-item></el-dropdown-menu></template>
          </el-dropdown>
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
        <el-table-column prop="username" label="用户名" width="140" sortable="custom" v-if="visibleColumns.username" />
        <el-table-column prop="nickname" label="昵称" width="140" sortable="custom" v-if="visibleColumns.nickname" />
        <el-table-column prop="total_quota" label="总额度" width="130" sortable="custom" v-if="visibleColumns.total_quota"><template #default="{ row }">¥{{ parseFloat(row.total_quota||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="used_quota" label="已使用" width="130" sortable="custom" v-if="visibleColumns.used_quota"><template #default="{ row }">¥{{ parseFloat(row.used_quota||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="remain_quota" label="剩余额度" width="130" sortable="custom" v-if="visibleColumns.remain_quota"><template #default="{ row }"><span :class="parseFloat(row.remain_quota)>0?'text-success':'text-danger'">¥{{ parseFloat(row.remain_quota||0).toFixed(2) }}</span></template></el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center" sortable="custom" v-if="visibleColumns.status"><template #default="{ row }"><el-tag :type="row.status===1?'success':'danger'" size="small">{{ row.status===1?'正常':'禁用' }}</el-tag></template></el-table-column>
        <el-table-column prop="last_login_at" label="最后登录" width="180" sortable="custom" v-if="visibleColumns.last_login_at" />
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom" v-if="visibleColumns.created_at" />
        <el-table-column label="操作" width="180" fixed="right" align="center"><template #default="{ row }"><el-button size="small" type="primary" link @click="showRechargeDialog(row)">充值</el-button><el-button size="small" type="warning" link @click="showResetPwdDialog(row)">重置密码</el-button></template></el-table-column>
      </el-table>

      <div class="pagination-wrapper"><el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10,20,50,100,200]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchData" /></div>
    </el-card>

    <!-- 代理快捷授权 -->
    <el-card shadow="never" class="main-card" v-if="!isAdmin" style="margin-top:0">
      <template #header><span>快速授权</span></template>
      <el-form :inline="true" :model="authForm" :rules="authRules" ref="authFormRef">
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="authForm.project_id" placeholder="选择项目" style="width:180px" @change="onAuthProjectChange"><el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" /></el-select>
        </el-form-item>
        <el-form-item label="套餐" prop="card_type_id">
          <el-select v-model="authForm.card_type_id" placeholder="选择套餐" style="width:220px" :disabled="!authForm.project_id"><el-option v-for="t in authCardTypes" :key="t.id" :label="`${t.name} (¥${t.agent_cost})`" :value="t.id" /></el-select>
        </el-form-item>
        <el-form-item><el-button type="primary" :loading="authLoading" @click="handleAuthorize" :icon="Plus">确认授权</el-button></el-form-item>
      </el-form>
    </el-card>

    <!-- 额度变动日志 -->
    <el-card shadow="never" class="main-card" v-if="!isAdmin" style="margin-top:20px">
      <template #header><span>额度变动记录</span></template>
      <el-table :data="quotaLogs" v-loading="logLoading" stripe border class="data-table" :max-height="300">
        <el-table-column prop="change_type" label="类型" width="100"><template #default="{ row }"><el-tag v-if="row.change_type==='recharge'" type="success" size="small">充值</el-tag><el-tag v-else-if="row.change_type==='consume'" type="warning" size="small">消费</el-tag><el-tag v-else-if="row.change_type==='refund'" type="info" size="small">退款</el-tag><el-tag v-else size="small">调整</el-tag></template></el-table-column>
        <el-table-column prop="amount" label="金额" width="120"><template #default="{ row }">¥{{ parseFloat(row.amount||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="balance_before" label="变动前" width="120"><template #default="{ row }">¥{{ parseFloat(row.balance_before||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="balance_after" label="变动后" width="120"><template #default="{ row }">¥{{ parseFloat(row.balance_after||0).toFixed(2) }}</template></el-table-column>
        <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip />
        <el-table-column prop="created_at" label="时间" width="180" />
      </el-table>
    </el-card>

    <!-- 充值弹窗 -->
    <el-dialog v-model="rechargeDialogVisible" title="充值额度" width="400px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="rechargeFormRef" :model="rechargeForm" :rules="rechargeRules" label-width="100px">
        <el-form-item label="代理"><el-input :value="rechargeTarget?.username" disabled /></el-form-item>
        <el-form-item label="充值金额" prop="amount"><el-input-number v-model="rechargeForm.amount" :min="0.01" :precision="2" style="width:100%" placeholder="请输入充值金额" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="rechargeForm.remark" placeholder="备注信息" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="rechargeDialogVisible = false">取消</el-button><el-button type="primary" :loading="rechargeLoading" @click="handleRecharge">确认充值</el-button></template>
    </el-dialog>

    <!-- 重置密码弹窗 -->
    <el-dialog v-model="resetPwdDialogVisible" title="重置代理密码" width="420px" :close-on-click-modal="false" destroy-on-close>
      <div class="reset-pwd-content">
        <p class="reset-pwd-tip">确认重置代理 <strong>{{ resetPwdTarget?.username }}</strong> 的登录密码吗？</p>
        <p v-if="resetPwdResult" class="reset-pwd-result">
          新密码：<code class="new-pwd">{{ resetPwdResult }}</code>
          <el-button size="small" type="success" @click="copyNewPassword" :icon="CopyDocument" style="margin-left:8px">复制</el-button>
        </p>
      </div>
      <template #footer>
        <el-button @click="resetPwdDialogVisible = false">{{ resetPwdResult ? '关闭' : '取消' }}</el-button>
        <el-button v-if="!resetPwdResult" type="warning" :loading="resetPwdLoading" @click="handleResetPassword">确认重置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'
import { useList } from '@/composables/useList'
import { useColumnSettings } from '@/composables/useColumnSettings'
import { Plus, Download, Search, Refresh, Filter, Setting, ArrowDown, Wallet, Money, Coin, CopyDocument } from '@element-plus/icons-vue'
import request from '@/api'

const userStore = useUserStore()
const user = computed(() => userStore.user)
const isAdmin = computed(() => user.value?.role === 'admin')

const showAdvancedSearch = ref(true)
const projects = ref([])
const myQuota = ref(null)
const quotaLogs = ref([])
const logLoading = ref(false)

const { list, loading, page, pageSize, total, selectedRows, filters, tableMaxHeight, fetchData, handleSelectionChange, handleSortChange, handleSizeChange, handleFilterChange, resetFilters, refreshData } = useList({
  apiUrl: '/agents',
  defaultFilters: { keyword: '', status: '' },
  defaultPageSize: 20
})

const { columnOptions, visibleColumns, saveColumnSettings } = useColumnSettings('agents_column_settings', [
  { prop: 'id', label: 'ID' },
  { prop: 'username', label: '用户名' },
  { prop: 'nickname', label: '昵称' },
  { prop: 'total_quota', label: '总额度' },
  { prop: 'used_quota', label: '已使用' },
  { prop: 'remain_quota', label: '剩余额度' },
  { prop: 'status', label: '状态' },
  { prop: 'last_login_at', label: '最后登录' },
  { prop: 'created_at', label: '创建时间' },
])

onMounted(async () => {
  if (isAdmin.value) {
    fetchData()
  } else {
    fetchMyQuota()
    fetchQuotaLogs()
    const res = await request.get('/projects/all')
    projects.value = res.data
  }
})

async function fetchMyQuota() {
  try { myQuota.value = (await request.get('/agents/my-quota')).data } catch {}
}
async function fetchQuotaLogs() {
  logLoading.value = true
  try { quotaLogs.value = (await request.get('/agents/quota-logs', { params: { page_size: 50 } })).data.list } finally { logLoading.value = false }
}

// 充值
const rechargeDialogVisible = ref(false)
const rechargeTarget = ref(null)
const rechargeFormRef = ref(null)
const rechargeForm = ref({ amount: 0, remark: '' })
const rechargeRules = { amount: [{ required: true, message: '请输入金额', trigger: 'blur' }] }
const rechargeLoading = ref(false)
function showRechargeDialog(row) { rechargeTarget.value = row; rechargeForm.value = { amount: 0, remark: '' }; rechargeDialogVisible.value = true }
async function handleRecharge() {
  if (!rechargeFormRef.value) return
  await rechargeFormRef.value.validate(async (valid) => {
    if (!valid) return
    rechargeLoading.value = true
    try {
      await request.post('/agents/recharge', { agent_id: rechargeTarget.value?.id, amount: rechargeForm.value.amount, remark: rechargeForm.value.remark })
      ElMessage.success('充值成功')
      rechargeDialogVisible.value = false
      fetchData()
    } catch {}
    finally { rechargeLoading.value = false }
  })
}

// 重置密码
const resetPwdDialogVisible = ref(false)
const resetPwdTarget = ref(null)
const resetPwdResult = ref('')
const resetPwdLoading = ref(false)
function showResetPwdDialog(row) { resetPwdTarget.value = row; resetPwdResult.value = ''; resetPwdDialogVisible.value = true }
async function handleResetPassword() {
  resetPwdLoading.value = true
  try {
    const res = await request.post(`/agents/${resetPwdTarget.value.id}/reset-password`)
    resetPwdResult.value = res.data?.password || ''
    ElMessage.success('密码已重置，请妥善保存新密码')
  } catch {} finally { resetPwdLoading.value = false }
}
function copyNewPassword() {
  const text = resetPwdResult.value
  if (!text) return
  // 使用 textarea + execCommand 兼容写法
  const ta = document.createElement('textarea')
  ta.value = text
  ta.style.position = 'fixed'; ta.style.left = '-9999px'; ta.style.top = '-9999px'
  document.body.appendChild(ta)
  ta.focus(); ta.select()
  try {
    document.execCommand('copy')
    ElMessage.success('已复制到剪贴板')
  } catch {
    // 降级使用 Clipboard API
    navigator.clipboard?.writeText(text).then(() => ElMessage.success('已复制到剪贴板')).catch(() => ElMessage.error('复制失败，请手动复制'))
  }
  document.body.removeChild(ta)
}

// 授权
const authFormRef = ref(null)
const authForm = ref({ project_id: '', card_type_id: '' })
const authRules = { project_id: [{ required: true, message: '请选择项目', trigger: 'change' }], card_type_id: [{ required: true, message: '请选择套餐', trigger: 'change' }] }
const authCardTypes = ref([])
const authLoading = ref(false)
async function onAuthProjectChange(pid) {
  authForm.value.card_type_id = ''
  if (!pid) { authCardTypes.value = []; return }
  const res = await request.get(`/projects/${pid}/card-types`)
  authCardTypes.value = (res.data || []).filter(t => parseFloat(t.agent_cost) > 0)
}
async function handleAuthorize() {
  await authFormRef.value.validate(async (valid) => {
    if (!valid) return
    authLoading.value = true
    try {
      const res = await request.post('/agents/authorize', authForm.value)
      ElMessage.success(`授权成功！卡密: ${res.data.card_key}`)
      fetchMyQuota(); fetchQuotaLogs()
      authForm.value = { project_id: '', card_type_id: '' }
    } catch {}
    finally { authLoading.value = false }
  })
}

function handleExport(format) {
  if (!selectedRows.value.length) { ElMessage.warning('请先选择要导出的代理'); return }
  const params = new URLSearchParams()
  params.set('ids', selectedRows.value.map(row => row.id).join(','))
  params.append('format', format)
  const token = localStorage.getItem('access_token')
  window.open(`/api/agents/export?${params.toString()}&token=${token}`, '_blank')
  ElMessage.success('导出任务已启动')
}
</script>

<style scoped>
.agents-page { max-width:1600px; margin:0 auto; }
.quota-row { margin-bottom:20px; }
.main-card { margin-bottom:20px; }
/* ========== 主卡片 ========== */
.advanced-search { padding:20px; background:#f5f7fa; border-radius:8px; margin-bottom:20px; }
.search-form { display:flex; flex-wrap:wrap; gap:12px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.toolbar-left,.toolbar-right { display:flex; gap:12px; flex-wrap:wrap; }
.data-table :deep(.el-table__header th) { font-weight:600; color:#303133; }
.text-success { color:#67C23A; }
.text-danger { color:#F56C6C; }
.pagination-wrapper { margin-top:20px; display:flex; justify-content:flex-end; }
.reset-pwd-tip { margin:0 0 16px; font-size:14px; }
.reset-pwd-result { margin:16px 0 0; font-size:14px; display:flex; align-items:center; }
.new-pwd { font-size:18px; font-weight:700; background:#fef0f0; padding:4px 10px; border-radius:4px; color:#e74c3c; margin-left:4px; }
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
