// 前端单元测试通用工具
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'

// 通用 Element Plus 组件 stub，避免测试环境中 auto-import 和图标变量问题
export const elStubs = {
  'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
  'el-tab-pane': { props: ['label'], template: '<div class="el-tab-pane"><span v-if="label">{{ label }}</span><slot /></div>' },
  'el-card': { template: '<div class="el-card"><slot name="header" /><slot /></div>' },
  'el-form': { template: '<form class="el-form"><slot /></form>' },
  'el-form-item': { props: ['label'], template: '<div class="el-form-item"><label v-if="label">{{ label }}</label><slot /></div>' },
  'el-input': { props: ['modelValue'], template: '<input :value="modelValue" />' },
  'el-input-number': { template: '<input type="number" />' },
  'el-button': { template: '<button><slot /></button>' },
  'el-tag': { template: '<span class="el-tag"><slot /></span>' },
  'el-icon': { template: '<i><slot /></i>' },
  'el-select': { props: ['modelValue'], template: '<select><slot /></select>' },
  'el-option': { props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' },
  'el-radio-group': { props: ['modelValue'], template: '<div class="el-radio-group"><slot /></div>' },
  'el-radio-button': { props: ['label', 'value'], template: '<span>{{ label }}</span>' },
  'el-switch': { props: ['modelValue'], template: '<input type="checkbox" :checked="modelValue" />' },
  'el-date-picker': { props: ['modelValue'], template: '<input :value="modelValue" />' },
  'el-dialog': { template: '<div class="el-dialog" v-show="false"><slot /></div>' },
  'el-upload': { template: '<div class="el-upload"><slot /></div>' },
  'el-dropdown': { template: '<div class="el-dropdown"><slot /><slot name="dropdown" /></div>' },
  'el-dropdown-menu': { template: '<div class="el-dropdown-menu"><slot /></div>' },
  'el-dropdown-item': { props: ['command'], template: '<div class="el-dropdown-item"><slot /></div>' },
  'el-table': { template: '<table class="el-table"><slot /></table>' },
  'el-table-column': { template: '<col />' },
  'el-pagination': { template: '<div class="el-pagination" />' },
  'el-message-box': { template: '<div />' },
  'el-popover': { template: '<div><slot /></div>' },
  'el-tooltip': { template: '<div><slot /></div>' },
  'el-avatar': { template: '<div />' },
  'el-descriptions': { template: '<div><slot /></div>' },
  'el-descriptions-item': { template: '<div><slot /></div>' },
  'el-statistic': { template: '<div><slot /></div>' },
  'el-divider': { template: '<hr />' },
  'el-empty': { template: '<div><slot /></div>' },
  'el-result': { template: '<div><slot /></div>' },
  'el-steps': { template: '<div><slot /></div>' },
  'el-step': { template: '<div><slot /></div>' },
  'el-timeline': { template: '<div><slot /></div>' },
  'el-timeline-item': { template: '<div><slot /></div>' },
  'el-skeleton': { template: '<div />' },
  'el-skeleton-item': { template: '<div />' },
}

export function createTestGlobalConfig() {
  return {
    plugins: [createPinia(), ElementPlus],
    stubs: elStubs
  }
}
