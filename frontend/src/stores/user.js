import { defineStore } from 'pinia'
import { ref } from 'vue'
import request from '@/api'

export const useUserStore = defineStore('user', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('access_token') || '')

  /**
   * 登录
   * @param {string} account 用户名或邮箱
   * @param {string} password 密码
   * @param {'admin'|'agent'} portal 登录入口
   */
  async function login(account, password, portal = 'admin') {
    const endpoint = portal === 'agent' ? '/auth/agent-login' : '/auth/admin-login'
    const res = await request.post(endpoint, { username: account, password })
    user.value = res.data.user
    token.value = res.data.token.access_token
    localStorage.setItem('access_token', res.data.token.access_token)
    localStorage.setItem('refresh_token', res.data.token.refresh_token)
    localStorage.setItem('user_role', res.data.user.role)
    return res
  }

  async function fetchUser() {
    const res = await request.get('/auth/me')
    user.value = res.data
    localStorage.setItem('user_role', res.data.role)
    return res
  }

  function logout() {
    user.value = null
    token.value = ''
    localStorage.clear()
  }

  return { user, token, login, fetchUser, logout }
})
