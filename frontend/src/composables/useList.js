import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import request from '@/api'

/**
 * 通用列表管理 composable
 * 提供分页、筛选、排序、选择、批量操作、刷新等能力
 */
export function useList(options) {
  const {
    apiUrl,
    statsUrl = null,
    defaultFilters = {},
    defaultPageSize = 20,
    fetchParams = null,
    processData = null,
    onError = null
  } = options

  const list = ref([])
  const loading = ref(false)
  const page = ref(1)
  const pageSize = ref(defaultPageSize)
  const total = ref(0)
  const stats = ref(null)
  const selectedRows = ref([])
  const sortParams = ref({ prop: '', order: '' })

  const filters = reactive({ ...defaultFilters })

  const tableMaxHeight = ref(500)

  function buildParams() {
    const params = {
      page: page.value,
      page_size: pageSize.value,
      ...filters
    }

    if (sortParams.value.prop) {
      params.sort_by = sortParams.value.prop
      params.sort_order = sortParams.value.order === 'ascending' ? 'asc' : 'desc'
    }

    if (fetchParams) {
      Object.assign(params, fetchParams())
    }

    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    return params
  }

  async function fetchData() {
    loading.value = true
    try {
      const res = await request.get(apiUrl, { params: buildParams() })
      let data = res.data.list || []
      if (processData) {
        data = data.map(processData)
      }
      list.value = data
      total.value = res.data.total || 0
    } catch (e) {
      console.error(`获取列表失败: ${apiUrl}`, e)
      if (onError) onError(e)
    } finally {
      loading.value = false
    }
  }

  async function fetchStats() {
    if (!statsUrl) return
    try {
      const res = await request.get(statsUrl)
      stats.value = res.data
    } catch (e) {
      console.error('获取统计数据失败:', e)
    }
  }

  function refreshData() {
    fetchData()
    fetchStats()
  }

  function handleSelectionChange(selection) {
    selectedRows.value = selection
  }

  function handleSortChange({ prop, order }) {
    sortParams.value = { prop, order }
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

  function resetFilters() {
    Object.keys(filters).forEach(key => {
      filters[key] = defaultFilters[key] !== undefined ? defaultFilters[key] : ''
    })
    handleFilterChange()
  }

  function calculateTableHeight() {
    const windowHeight = window.innerHeight
    tableMaxHeight.value = Math.max(300, windowHeight - 450)
  }

  onMounted(() => {
    calculateTableHeight()
    window.addEventListener('resize', calculateTableHeight)
    fetchData()
    fetchStats()
  })

  onUnmounted(() => {
    window.removeEventListener('resize', calculateTableHeight)
  })

  return {
    list,
    loading,
    page,
    pageSize,
    total,
    stats,
    selectedRows,
    sortParams,
    filters,
    tableMaxHeight,
    fetchData,
    fetchStats,
    refreshData,
    handleSelectionChange,
    handleSortChange,
    handleSizeChange,
    handleFilterChange,
    resetFilters,
    calculateTableHeight
  }
}
