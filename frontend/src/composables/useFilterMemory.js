import { reactive, onMounted } from 'vue'

/**
 * 筛选条件记忆
 */
export function useFilterMemory(storageKey, defaultFilters) {
  const filters = reactive({ ...defaultFilters })

  function saveFilterMemory() {
    localStorage.setItem(storageKey, JSON.stringify(filters))
  }

  function loadFilterMemory() {
    const saved = localStorage.getItem(storageKey)
    if (!saved) return
    try {
      const memory = JSON.parse(saved)
      Object.keys(defaultFilters).forEach(key => {
        if (memory[key] !== undefined) {
          filters[key] = memory[key]
        }
      })
    } catch (e) {
      console.error('加载筛选记忆失败:', e)
    }
  }

  onMounted(loadFilterMemory)

  return {
    filters,
    saveFilterMemory,
    loadFilterMemory
  }
}
