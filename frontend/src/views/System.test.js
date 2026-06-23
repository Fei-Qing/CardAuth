import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobalConfig } from '@/test-utils'
import System from './System.vue'

// 模拟 axios 请求
vi.mock('@/api', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ code: 200, data: {} })),
    post: vi.fn(() => Promise.resolve({ code: 200, message: 'success' })),
    put: vi.fn(() => Promise.resolve({ code: 200, message: 'success' })),
  }
}))

// 模拟用户 store
vi.mock('@/stores/user', () => ({
  useUserStore: () => ({
    user: { id: 1, username: 'admin', nickname: '超级管理员', role: 'admin' },
    fetchUser: vi.fn(() => Promise.resolve())
  })
}))

function mountSystem() {
  return mount(System, { global: createTestGlobalConfig() })
}

describe('System.vue', () => {
  beforeEach(() => {
    setActivePinia(createTestGlobalConfig().plugins[0])
  })

  it('渲染系统设置页面并包含两个主要标签页', async () => {
    const wrapper = mountSystem()
    expect(wrapper.text()).toContain('网站基本信息')
    expect(wrapper.text()).toContain('管理员个人信息')
  })

  it('站点配置表单包含关键字段', async () => {
    const wrapper = mountSystem()
    expect(wrapper.text()).toContain('站点名称')
    expect(wrapper.text()).toContain('站点Logo')
    expect(wrapper.text()).toContain('订单过期时间')
  })

  it('个人资料表单包含关键字段', async () => {
    const wrapper = mountSystem()
    expect(wrapper.text()).toContain('昵称')
    expect(wrapper.text()).toContain('邮箱')
    expect(wrapper.text()).toContain('手机号')
  })
})
