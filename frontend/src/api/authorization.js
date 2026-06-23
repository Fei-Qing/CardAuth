import request from './index'

/**
 * 授权管理 API
 */
const authorizationApi = {
  /**
   * 授权列表
   */
  list(params) {
    return request.get('/authorizations', { params })
  },

  /**
   * 授权统计
   */
  stats() {
    return request.get('/authorizations/stats')
  },

  /**
   * 授权详情
   */
  detail(id) {
    return request.get(`/authorizations/${id}`)
  },

  /**
   * 新增授权
   */
  create(data) {
    return request.post('/authorizations', data)
  },

  /**
   * 撤销授权
   */
  revoke(id, reason) {
    return request.put(`/authorizations/${id}/revoke`, { reason })
  },

  /**
   * 更新授权
   */
  update(id, data) {
    return request.put(`/authorizations/${id}`, data)
  },

  /**
   * 删除授权
   */
  delete(id) {
    return request.delete(`/authorizations/${id}`)
  }
}

export default authorizationApi