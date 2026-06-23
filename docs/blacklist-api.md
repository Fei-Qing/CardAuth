# 黑名单管理接口文档

## 数据模型

### ca_blacklists 表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED | 主键，自增 |
| target_type | ENUM('user','ip') | 目标类型：user=用户ID，ip=IP地址 |
| target_value | VARCHAR(255) | 目标值 |
| status | TINYINT(1) | 状态：0=禁用，1=启用 |
| expire_time | DATETIME | 过期时间，NULL表示永久 |
| operator_id | BIGINT UNSIGNED | 操作人ID |
| operator_name | VARCHAR(50) | 操作人用户名 |
| remark | VARCHAR(500) | 备注 |
| created_at | DATETIME | 创建时间 |
| updated_at | DATETIME | 更新时间 |

唯一约束：`target_type` + `target_value`

## 接口列表

所有接口均需要管理员权限，路径前缀为 `/api`。

### 1. 查询黑名单列表

- **URL**: `GET /api/blacklists`
- **权限**: admin
- **参数**:
  - `target_type`: 可选，user/ip
  - `status`: 可选，0/1
  - `keyword`: 可选，目标值/备注模糊搜索
  - `page`: 可选，默认1
  - `page_size`: 可选，默认20，最大100
  - `sort_by`: 可选，id/created_at/updated_at
  - `sort_order`: 可选，asc/desc
- **响应**:
  ```json
  {
    "code": 200,
    "data": {
      "list": [...],
      "total": 100,
      "page": 1,
      "page_size": 20
    }
  }
  ```

### 2. 添加黑名单

- **URL**: `POST /api/blacklists`
- **权限**: admin
- **参数**:
  - `target_type`: 必填，user/ip
  - `target_value`: 必填
  - `expire_time`: 可选，格式 YYYY-MM-DD HH:mm:ss
  - `remark`: 可选
- **响应**: `{"code":200,"message":"黑名单添加成功"}`

### 3. 批量添加黑名单

- **URL**: `POST /api/blacklists/batch`
- **权限**: admin
- **参数**:
  - `items`: 必填，数组，每个元素包含 target_type, target_value, expire_time, remark
  - 单次最多500条
- **响应**:
  ```json
  {
    "code": 200,
    "data": { "success": 10, "failed": 0 },
    "message": "批量添加完成，成功 10 条，失败 0 条"
  }
  ```

### 4. 更新黑名单

- **URL**: `PUT /api/blacklists/{id}`
- **权限**: admin
- **参数**:
  - `status`: 可选，0/1
  - `expire_time`: 可选
  - `remark`: 可选
- **响应**: `{"code":200,"message":"黑名单更新成功"}`

### 5. 删除黑名单

- **URL**: `DELETE /api/blacklists/{id}`
- **权限**: admin
- **响应**: `{"code":200,"message":"黑名单删除成功"}`

### 6. 批量删除黑名单

- **URL**: `POST /api/blacklists/batch-delete`
- **权限**: admin
- **参数**:
  - `ids`: 必填，整数数组，单次最多500条
- **响应**:
  ```json
  {
    "code": 200,
    "data": { "count": 5 },
    "message": "批量删除成功"
  }
  ```

### 7. 导入黑名单

- **URL**: `POST /api/blacklists/import`
- **权限**: admin
- **Content-Type**: multipart/form-data
- **参数**:
  - `file`: CSV文件，必须包含 target_type、target_value 列
- **响应**:
  ```json
  {
    "code": 200,
    "data": { "success": 10, "failed": 0 },
    "message": "导入完成..."
  }
  ```

### 8. 导出黑名单

- **URL**: `GET /api/blacklists/export`
- **权限**: admin
- **参数**:
  - `ids`: 可选，逗号分隔的ID列表，为空则导出全部
- **响应**: CSV文件下载

## 黑名单拦截

系统通过 `BlacklistMiddleware` 对所有需要认证的接口进行拦截。当请求的当前用户ID或客户端IP存在于启用状态且未过期的黑名单中时，接口返回：

```json
{
  "code": 403,
  "message": "您的账号或IP已被列入黑名单，禁止访问"
}
```

### 缓存机制

黑名单列表缓存于 `backend/storage/cache/blacklist.json`，默认有效期 60 秒。新增、更新、删除黑名单操作会自动刷新缓存。

## 频率限制

黑名单管理接口位于 `/api` 路由组，共享 `RateLimitMiddleware(60, 60, 'api')`，即每 60 秒最多 60 次请求。
