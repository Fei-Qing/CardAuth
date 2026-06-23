import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

const request = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' }
})

// 请求拦截器
request.interceptors.request.use(config => {
  const token = localStorage.getItem('access_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}, error => Promise.reject(error))

// 响应拦截器
request.interceptors.response.use(
  response => {
    const res = response.data
    const noMessage = response.config?._noMessage
    if (res.code !== 200) {
      if (!noMessage) ElMessage.error(res.message || '请求失败')
      if (res.code === 401) {
        localStorage.clear()
        router.push('/login')
      }
      return Promise.reject(new Error(res.message))
    }
    return res
  },
  error => {
    const noMessage = error.config?._noMessage
    if (error.response?.status === 401) {
      localStorage.clear()
      router.push('/login')
    }
    if (!noMessage) ElMessage.error(error.message || '网络错误')
    return Promise.reject(error)
  }
)

export default request