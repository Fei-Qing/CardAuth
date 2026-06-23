import { ref, computed } from 'vue'

/**
 * 表格列显示/隐藏设置
 */
export function useColumnSettings(storageKey, defaultColumns) {
  const columnOptions = ref(defaultColumns.map(col => ({
    ...col,
    visible: col.visible !== false
  })))

  function saveColumnSettings() {
    localStorage.setItem(storageKey, JSON.stringify(columnOptions.value))
  }

  function loadColumnSettings() {
    const saved = localStorage.getItem(storageKey)
    if (!saved) return
    try {
      const settings = JSON.parse(saved)
      // 合并已保存的设置和默认列，防止新增列丢失
      const map = new Map(settings.map(s => [s.prop, s.visible]))
      columnOptions.value = defaultColumns.map(col => ({
        ...col,
        visible: map.has(col.prop) ? map.get(col.prop) : (col.visible !== false)
      }))
    } catch (e) {
      console.error('加载列设置失败:', e)
    }
  }

  const visibleColumns = computed(() => {
    const obj = {}
    columnOptions.value.forEach(col => {
      obj[col.prop] = col.visible
    })
    return obj
  })

  loadColumnSettings()

  return {
    columnOptions,
    visibleColumns,
    saveColumnSettings
  }
}
