# CardAuth - 卡密授权管理系统

> 半成品，持续开发中。

一个轻量级的卡密（激活码）+ 机器授权管理系统，支持多项目管理、代理分销、在线支付购买，适用于软件授权、会员激活等场景。

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | PHP 7.4+ 自研框架（Router + Middleware + Controller） |
| 数据库 | MySQL 8.0+ |
| 前端 | Vue 3 + Element Plus + Pinia |
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
- 操作日志

### 内置页面
- `/` 仪表盘
- `/login` 管理员登录
- `/agent/login` 代理登录
- `/agent/register` 代理注册
- `/shop` 自助购买（公开页面）

## 快速开始

### 环境要求
- PHP 7.4+（需启用 PDO、PDO_MySQL）
- MySQL 8.0+
- Node.js 16+

### 1. 初始化数据库
```bash
mysql -u root -p < backend/database/schema.sql
```

### 2. 配置后端
```bash
cd backend
cp .env.example .env
# 编辑 .env 填入数据库信息
```

### 3. 安装 PHP 依赖
```bash
cd backend
php composer.phar install
```

### 4. 启动
```bash
# 一键启动（前端 + 后端）
.\start.ps1

# 或分别启动
cd backend && php -S 0.0.0.0:8080 -t backend
cd frontend && npm install && npm run dev
```

### 5. 访问
- 管理后台：`http://localhost:3000`
- 代理登录：`http://localhost:3000/#/agent/login`
- 购买商城：`http://localhost:3000/#/shop`

### 6. 默认管理员
数据库默认会插入 `admin / admin123` 的超级管理员账号（需在 `schema.sql` 末尾自行添加 INSERT 语句，或在用户管理页面注册后手动修改角色）。

## 项目结构

```
card/
├── backend/                  # PHP 后端
│   ├── app/
│   │   ├── Controllers/      # 控制器 (11 个模块)
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
└── deploy/                   # 部署配置 (Nginx + 宝塔)
```

## 待完善
- [ ] 支付回调验签完善
- [ ] 代理层级佣金结算
- [ ] 数据统计与报表导出
- [ ] 单元测试与 E2E 测试覆盖
- [ ] Docker 化部署

## License

MIT
