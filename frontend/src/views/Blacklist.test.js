import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobalConfig } from '@/test-utils'
import Blacklist from './Blacklist.vue'

vi.mock('@/api', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({
      code: 200,
      data: {
        list: [
          { id: 1, target_type: 'ip', target_value: '192.168.1.1', status: 1, expire_time: null, operator_name: 'admin', remark: 'test', created_at: '2026-06-20 23:00:00' }
        ],
        total: 1,
        page: 1,
        page_size: 20,
      }
    })),
    post: vi.fn(() => Promise.resolve({ code: 200, message: 'success' })),
    put: vi.fn(() => Promise.resolve({ code: 200, message: 'success' })),
    delete: vi.fn(() => Promise.resolve({ code: 200, message: 'success' })),
  }
}))

function mountBlacklist() {
  const config = createTestGlobalConfig()
  // 使用真实表格组件以便验证数据渲染
  delete config.stubs['el-table']
  delete config.stubs['el-table-column']
  return mount(Blacklist, { global: config })
}

describe('Blacklist.vue', () => {
  beforeEach(() => {
    setActivePinia(createTestGlobalConfig().plugins[0])
  })

  it('渲染黑名单管理页面包含必要元素', async () => {
    const wrapper = mountBlacklist()
    expect(wrapper.text()).toContain('黑名单管理')
    expect(wrapper.text()).toContain('添加黑名单')
  })

  it('包含目标类型和状态筛选', async () => {
    const wrapper = mountBlacklist()
    expect(wrapper.text()).toContain('目标类型')
    expect(wrapper.text()).toContain('状态')
  })

  it('表格数据加载后显示目标值', async () => {
    const wrapper = mountBlacklist()
    await new Promise(r => setTimeout(r, 0))
    expect(wrapper.text()).toContain('192.168.1.1')
  })
})
