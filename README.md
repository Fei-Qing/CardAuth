# CardAuth - 卡密授权管理系统

一个轻量级的卡密（激活码）+ 机器授权管理系统，支持多项目管理、代理分销、在线支付购买，适用于软件授权、会员激活等场景。

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | PHP 7.4+ 自研框架（Router + Middleware + Controller） |
| 数据库 | MySQL 8.0+ |
| 前端 | Vue 3 + Element Plus + Pinia + ECharts |
| 构建 | Vite |

## 功能概览

### 角色体系
- **超级管理员**：全部权限，用户管理、项目管理、系统配置、操作日志
- **项目管理员**：仅管理指定项目（不会看到代理信息），可操作卡密、授权、订单
- **代理**：代理商城、卡密生成/导入（余额扣费）、授权管理，可发展下级代理

### 核心功能
- 项目与 API 密钥管理（每个项目独立密钥，API 鉴权）
- 商品/套餐管理（自定义时长、价格、代理价格）
- 卡密生成、批量导入、状态追踪
- 在线授权（直接创建授权，不依赖卡密；代理按代理价扣费）
- 代理余额体系（充值记录、扣费追溯、额度查询）
- 订单管理（代客手动完成 / 手动确认）
- 在线购买商城（公开页面，支持 epay/codepay 支付回调）
- 黑名单系统（机器码/IP 封禁，可配置封禁策略）
- SMTP 邮件通知（授权快到期自动发送提醒到联系人 QQ 邮箱）
- 仪表盘统计图表（收入趋势、授权趋势、套餐销售占比）
- 操作日志

### 内置页面
- `/` 仪表盘
- `/login` 管理员登录
- `/agent/login` 代理登录
- `/agent/register` 代理注册
- `/shop` 自助购买（公开页面）

## 快速开始

### 方式一：Docker 部署（推荐）

```bash
# 1. 配置环境变量
cp .env.docker .env
# 编辑 .env，设置 JWT_SECRET 和数据库密码

# 2. 一键启动
docker compose up -d

# 3. 访问
http://localhost:8080
```

首次启动自动完成：前端构建 → 数据库初始化 → Nginx + PHP-FPM 启动。

### 方式二：传统部署

#### 环境要求
- Linux 服务器（CentOS 7+ / Ubuntu 18+ / Debian 10+）
- Nginx 1.18+
- PHP 8.1+（需启用 PDO、PDO_MySQL、mbstring、bcmath）
- PHP-FPM
- MySQL 8.0+
- Node.js 18+（仅构建阶段需要，部署后不需要）
- Composer

#### 1. 一键部署脚本

```bash
# 在项目根目录执行，按提示输入配置
sudo bash deploy.sh your-domain.com
```

脚本自动完成：环境检查 → 前端构建 → 文件部署 → 数据库初始化 → Nginx 配置 → 权限设置。

### 方式二：手动部署

#### 1. 初始化数据库
```bash
mysql -u root -p < backend/database/schema.sql
```

#### 2. 配置环境变量
```bash
cd backend
cp .env.example .env
# 编辑 .env，确保:
#   APP_ENV=production
#   APP_DEBUG=false
#   DB_* 填入实际数据库信息
#   JWT_SECRET 填入随机 64 位字符串
```

#### 3. 安装依赖
```bash
cd backend && composer install --no-dev --optimize-autoloader
cd ../frontend && npm install && npm run build
```

构建产物输出到 `frontend/dist/`，将其复制到 `backend/public/`：
```bash
cp -r frontend/dist/* backend/public/
```

#### 4. 配置 Nginx

参考 [deploy/nginx.conf](deploy/nginx.conf)，核心配置：

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /www/wwwroot/your-site/public;
    index index.php index.html;

    # 前端 SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 路由到 PHP
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-74.sock;  # 按实际 PHP 版本调整
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 安全加固
    location ~ /\.(?!well-known) { deny all; }
    location ~* \.(env|sql|log|md)$ { deny all; }
    location ~* ^/(storage|config|routes)/ { deny all; }
}
```

#### 5. 设置权限
```bash
chown -R www:www /www/wwwroot/your-site
chmod -R 755 /www/wwwroot/your-site
chmod 600 /www/wwwroot/your-site/.env
```

#### 6. 默认管理员
| 用户名 | 密码 |
|--------|------|
| admin | admin123456 |

> 首次登录后请立即修改密码。

## SMTP 邮件通知配置

在系统配置 -> SMTP邮件配置中填写 SMTP 信息，系统将自动在授权到期前 N 天发送提醒邮件到联系人的 QQ 邮箱（`contact_qq@qq.com`）。

### 定时任务 (Cron)
建议设置每 6 小时执行一次过期检查：
```bash
# Linux crontab
0 */6 * * * curl -s http://your-domain/api/cron/notify-expiring > /dev/null

# Windows 任务计划程序
curl -s http://your-domain/api/cron/notify-expiring
```

---

# API 文档

## 鉴权说明

| 类型 | 说明 |
|------|------|
| **JWT Token** | 登录后获取，请求头 `Authorization: Bearer <token>`，有效期 24 小时 |
| **API Key** | 项目密钥，仅用于授权验证接口，请求头 `X-Api-Key: <key>` |
| **公开接口** | 无需认证，有频率限制（60秒内 30 次） |

---

## 一、公开接口（无需认证）

### 1.1 查询授权状态（按机器人QQ）

```
GET /api/public/authorizations/query?bot_qq=123456789
```

**响应示例：**
```json
{
  "code": 200,
  "data": {
    "bot_qq": "123456789",
    "total": 2,
    "active_count": 1,
    "expired_count": 1,
    "revoked_count": 0,
    "has_valid_auth": true,
    "list": [
      {
        "id": 1,
        "card_key": "CA-XXXX-XXXX",
        "project_name": "演示项目",
        "bot_qq": "123456789",
        "contact_qq": "987654321",
        "contact_name": "张三",
        "duration_days": 30,
        "status": "active",
        "authorized_at": "2026-01-01 12:00:00",
        "expire_time": "2026-01-31 12:00:00",
        "is_expired": false
      }
    ]
  }
}
```

### 1.2 验证授权有效性（客户端调用）

```
POST /api/public/authorizations/verify
Content-Type: application/json

{
  "bot_qq": "123456789",
  "card_key": "CA-XXXX-XXXX",   // 可选
  "contact_qq": "987654321"      // 可选
}
```

**响应示例（有效）：**
```json
{
  "code": 200,
  "data": {
    "valid": true,
    "bot_qq": "123456789",
    "contact_qq": "987654321",
    "card_key": "CA-XXXX-XXXX",
    "project_name": "演示项目",
    "expire_time": "2026-01-31 12:00:00",
    "days_left": 20,
    "has_valid_auth": true,
    "message": "授权有效"
  }
}
```

**响应示例（无效）：**
```json
{
  "code": 200,
  "data": {
    "valid": false,
    "bot_qq": "123456789",
    "has_valid_auth": false,
    "message": "未找到有效授权"
  }
}
```

### 1.3 API Key 授权验证

```
POST /api/public/verify
X-Api-Key: <项目的 API Key>
Content-Type: application/json

{
  "bot_qq": "123456789",
  "contact_qq": "987654321"     // 可选
}
```

**响应格式同 1.2。**

### 1.4 获取项目列表（商城用）

```
GET /api/public/projects
```

### 1.5 获取项目套餐列表

```
GET /api/public/projects/{project_id}/card-types
```

### 1.6 创建订单

```
POST /api/public/orders
Content-Type: application/json

{
  "project_id": 1,
  "card_type_id": 1,
  "amount": 29.90,
  "pay_type": "wxpay",
  "contact_qq": "987654321",
  "bot_qq": "123456789",
  "coupon_code": ""             // 可选，优惠码
}
```

**响应示例：**
```json
{
  "code": 200,
  "data": {
    "order_no": "2026062317530410859",
    "pay_url": "https://pay.example.com/xxx",
    "is_renew": false,
    "contact_qq_warn": null,
    "contact_qq": "987654321"
  }
}
```

### 1.7 查询订单

```
GET /api/public/orders/query?order_no=2026062317530410859
```

---

## 二、认证接口

### 2.1 管理员登录

```
POST /api/auth/admin-login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123456"
}
```

**响应示例：**
```json
{
  "code": 200,
  "data": {
    "token": "eyJ...",
    "user": { "id": 1, "username": "admin", "role": "admin" }
  }
}
```

### 2.2 代理登录

```
POST /api/auth/agent-login
Content-Type: application/json

{
  "username": "agent001",
  "password": "123456"
}
```

### 2.3 代理注册

```
POST /api/auth/agent-register
Content-Type: application/json

{
  "username": "new_agent",
  "password": "123456",
  "nickname": "新代理"
}
```

### 2.4 刷新 Token

```
POST /api/auth/refresh
Authorization: Bearer <token>
```

### 2.5 获取当前用户信息

```
GET /api/auth/me
Authorization: Bearer <token>
```

---

## 三、仪表盘

### 3.1 仪表盘数据

```
GET /api/dashboard
Authorization: Bearer <token>
```

**响应包含：**
- 统计数字（项目数、卡密数、订单数、收入等）
- `order_trend`: 近 7 天订单趋势
- `revenue_trend_30`: 近 30 天收入趋势
- `auth_trend_30`: 近 30 天新增授权趋势
- `package_distribution`: 套餐销售占比
- `expiring_soon`: 即将过期授权数

---

## 四、项目与套餐管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/projects` | 项目列表 |
| GET | `/api/projects/all` | 全部项目（下拉用） |
| GET | `/api/projects/{id}` | 项目详情 |
| POST | `/api/projects` | 创建项目 |
| PUT | `/api/projects/{id}` | 更新项目 |
| DELETE | `/api/projects/{id}` | 删除项目 |
| POST | `/api/projects/{id}/regenerate-key` | 重新生成 API Key |
| GET | `/api/projects/{id}/card-types` | 某项目的套餐列表 |
| POST | `/api/projects/{id}/card-types` | 创建套餐 |
| PUT | `/api/projects/{id}/card-types/{typeId}` | 更新套餐 |
| DELETE | `/api/projects/{id}/card-types/{typeId}` | 删除套餐 |
| GET | `/api/products` | 全量商品列表 |
| GET | `/api/products/export` | 导出商品 |

### 创建项目

```
POST /api/projects
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "我的项目",
  "description": "项目描述"
}
```

### 创建套餐

```
POST /api/projects/1/card-types
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "月卡",
  "duration_days": 30,
  "price": 29.90,
  "agent_cost": 20.00,
  "sort": 1,
  "status": 1
}
```

---

## 五、卡密管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/cards` | 卡密列表（支持分页、筛选、排序） |
| GET | `/api/cards/stats` | 卡密统计 |
| GET | `/api/cards/{id}` | 卡密详情 |
| POST | `/api/cards/generate` | 批量生成卡密 |
| POST | `/api/cards/import` | 导入卡密 |
| POST | `/api/cards/batch-status` | 批量修改状态 |
| POST | `/api/cards/batch-delete` | 批量删除 |
| PATCH | `/api/cards/{id}/status` | 单个卡密启用/禁用 |
| GET | `/api/cards/export` | 导出卡密（CSV/Excel） |

### 生成卡密

```
POST /api/cards/generate
Authorization: Bearer <token>
Content-Type: application/json

{
  "project_id": 1,
  "card_type_id": 1,
  "count": 100,
  "prefix": "CA"
}
```

---

## 六、授权管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/authorizations` | 授权列表 |
| GET | `/api/authorizations/stats` | 授权统计 |
| GET | `/api/authorizations/{id}` | 授权详情 |
| POST | `/api/authorizations` | 新增授权 |
| PUT | `/api/authorizations/{id}/revoke` | 撤销授权 |
| DELETE | `/api/authorizations/{id}` | 删除授权 |
| POST | `/api/authorizations/batch-delete` | 批量删除 |
| GET | `/api/authorizations/export` | 导出授权 |

### 新增授权

```
POST /api/authorizations
Authorization: Bearer <token>
Content-Type: application/json

{
  "project_id": 1,
  "card_type_id": 1,
  "bot_qq": "123456789",
  "contact_qq": "987654321",
  "contact_name": "张三"
}
```

**续费逻辑**：若 `bot_qq + contact_qq` 已存在活跃授权，自动续费延长过期时间。

### 撤销授权

```
PUT /api/authorizations/{id}/revoke
Authorization: Bearer <token>
Content-Type: application/json

{
  "reason": "用户违规"
}
```

---

## 七、订单管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/orders` | 订单列表 |
| GET | `/api/orders/{id}` | 订单详情 |
| POST | `/api/orders/{id}/complete` | 手动完成订单 |
| GET | `/api/orders/export` | 导出订单 |

---

## 八、用户管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/users` | 用户列表 |
| GET | `/api/users/stats` | 用户统计 |
| POST | `/api/users` | 创建用户 |
| PUT | `/api/users/{id}` | 更新用户 |
| DELETE | `/api/users/{id}` | 删除用户 |
| POST | `/api/users/batch-delete` | 批量删除 |
| GET | `/api/users/export` | 导出用户 |

---

## 九、代理管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/agents` | 代理列表 |
| GET | `/api/agents/my-quota` | 当前代理额度 |
| POST | `/api/agents/recharge` | 充值额度 |
| POST | `/api/agents/adjust-quota` | 调整额度 |
| GET | `/api/agents/quota-logs` | 额度变动日志 |
| POST | `/api/agents/{id}/reset-password` | 重置代理密码 |

---

## 十、优惠券

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/coupons` | 优惠券列表 |
| POST | `/api/coupons` | 创建优惠券 |
| PUT | `/api/coupons/{id}` | 更新优惠券 |
| DELETE | `/api/coupons/{id}` | 删除优惠券 |

---

## 十一、黑名单

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/blacklists` | 黑名单列表 |
| POST | `/api/blacklists` | 添加黑名单 |
| POST | `/api/blacklists/batch` | 批量添加 |
| POST | `/api/blacklists/import` | 导入黑名单 |
| PUT | `/api/blacklists/{id}` | 更新黑名单 |
| DELETE | `/api/blacklists/{id}` | 删除黑名单 |
| POST | `/api/blacklists/batch-delete` | 批量删除 |

---

## 十二、系统配置

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/system/configs?keys=key1,key2` | 获取配置 |
| POST | `/api/system/configs` | 保存配置 |
| GET | `/api/system/smtp-config` | 获取 SMTP 配置 |
| POST | `/api/system/smtp-config` | 保存 SMTP 配置 |
| POST | `/api/system/test-smtp` | 发送测试邮件 |
| POST | `/api/system/notify-expiring` | 手动触发过期提醒 |

---

## 十三、定时任务

```
POST /api/cron/notify-expiring
GET  /api/cron/notify-expiring
```

无需认证，检查即将过期的授权并向联系人 QQ 邮箱发送提醒。

---

## 错误码说明

| code | 说明 |
|------|------|
| 200 | 成功 |
| 400 | 参数错误 |
| 401 | 未登录 / Token 无效 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 429 | 请求过于频繁 |
| 500 | 服务器错误 |

通用错误响应：
```json
{
  "code": 400,
  "message": "参数错误描述"
}
```

## 项目结构

```
card/
├── backend/                  # PHP 后端
│   ├── app/
│   │   ├── Controllers/      # 控制器 (12 个模块)
│   │   ├── Core/             # 框架核心 (Router/Controller/Database/Middleware/Validator)
│   │   ├── Middleware/       # 中间件 (Auth/JWT/RateLimit/ApiKey/Blacklist/Role)
│   │   └── Services/         # 服务层 (Jwt/Blacklist/Permission/Snowflake)
│   ├── config/               # 配置文件
│   ├── database/             # SQL 与 Migration
│   └── routes/               # 路由定义
├── frontend/                 # Vue 3 前端
│   └── src/
│       ├── api/              # 接口封装
│       ├── composables/      # 组合式函数
│       ├── router/           # 路由配置
│       ├── stores/           # Pinia 状态管理
│       └── views/            # 页面组件 (管理后台 + 代理 + 公开)
├── docker/                   # Docker 配置
│   ├── nginx.conf            # Nginx 容器配置
│   ├── supervisord.conf      # Supervisor 进程管理
│   └── entrypoint.sh         # 容器入口脚本
├── deploy/                   # 传统部署配置 (Nginx + 宝塔)
├── Dockerfile                # Docker 多阶段构建
├── docker-compose.yml        # Docker 编排 (MySQL + App)
└── .env.docker               # Docker 环境变量模板
```

## License

MIT
