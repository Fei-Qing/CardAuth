-- Active: 1782007065396@@127.0.0.1@3306@card_auth
#!/bin/bash
# ============================================
# CardAuth 一键部署到宝塔面板脚本
# 版本: 1.0.0
# 适用: CentOS 7+ / Ubuntu 18+ / Debian 10+
# ============================================
set -e

# ==================== 颜色输出 ====================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()  { echo -e "${GREEN}[INFO]${NC}  $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step()  { echo -e "\n${BLUE}========================================${NC}"; echo -e "${CYAN}$1${NC}"; echo -e "${BLUE}========================================${NC}"; }

# ==================== 配置变量（请按需修改） ====================
# 宝塔网站根目录 (宝塔默认: /www/wwwroot/)
BT_WWW_ROOT="/www/wwwroot"

# 项目部署目录名（将创建为 ${BT_WWW_ROOT}/${SITE_NAME}）
SITE_NAME="card-auth"

# 域名（用于生成 Nginx 配置）
DOMAIN="${1:-your-domain.com}"

# 数据库配置
DB_NAME="card_auth"
DB_USER="card_auth"
DB_PASS="123456"
DB_PREFIX="ca_"
DB_HOST="127.0.0.1"
DB_PORT="3306"

# JWT 密钥（留空自动生成随机64位密钥）
JWT_SECRET=""

# 支付配置（部署后可修改）
PAYMENT_TYPE="epay"
PAYMENT_API_URL="https://pay.example.com"
PAYMENT_APP_ID=""
PAYMENT_APP_KEY=""

# 部署路径
DEPLOY_DIR="${BT_WWW_ROOT}/${SITE_NAME}"
BACKEND_DIR="${DEPLOY_DIR}"
PUBLIC_DIR="${BACKEND_DIR}/public"
FRONTEND_DIR="$(cd "$(dirname "$0")" && pwd)/frontend"

# ==================== 检查环境 ====================
check_environment() {
    log_step "Step 1/7: 检查运行环境"

    # 检查是否为 root
    if [[ $EUID -ne 0 ]]; then
        log_error "请使用 root 用户运行此脚本"
        exit 1
    fi

    # 检查宝塔
    if [ ! -f /etc/init.d/bt ] && [ ! -f /etc/init.d/btpanel ]; then
        log_warn "未检测到宝塔面板，继续部署但不会自动配置网站"
    else
        log_info "检测到宝塔面板"
    fi

    # 检查 PHP
    if ! command -v php &>/dev/null; then
        log_error "PHP 未安装，请先在宝塔面板中安装 PHP"
        exit 1
    fi
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
    log_info "PHP 版本: ${PHP_VERSION}"

    # 检查 PHP 扩展
    for ext in pdo pdo_mysql json mbstring bcmath; do
        if php -m | grep -qi "^${ext}$"; then
            log_info "PHP 扩展 ${ext} ✓"
        else
            log_error "PHP 扩展 ${ext} 未安装，请在宝塔面板中安装"
            exit 1
        fi
    done

    # 检查 MySQL
    if ! command -v mysql &>/dev/null; then
        log_warn "MySQL 客户端未找到，将跳过数据库导入"
    else
        log_info "MySQL 客户端可用"
    fi

    # 检查 Node.js
    if ! command -v node &>/dev/null; then
        log_warn "Node.js 未安装，尝试安装..."
        install_nodejs
    fi
    NODE_VERSION=$(node -v 2>/dev/null || echo "未安装")
    log_info "Node.js 版本: ${NODE_VERSION}"
}

# ==================== 安装 Node.js ====================
install_nodejs() {
    if command -v yum &>/dev/null; then
        curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
        yum install -y nodejs
    elif command -v apt &>/dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
        apt install -y nodejs
    else
        log_error "无法自动安装 Node.js，请手动安装 Node.js 18+"
        exit 1
    fi
}

# ==================== 构建前端 ====================
build_frontend() {
    log_step "Step 2/7: 构建前端项目"

    if [ ! -d "$FRONTEND_DIR" ]; then
        log_error "前端目录不存在: ${FRONTEND_DIR}"
        exit 1
    fi

    cd "$FRONTEND_DIR"

    # 安装依赖（已有 node_modules 则跳过，节省时间）
    if [ -d "node_modules" ] && [ "$FORCE_INSTALL" != "true" ]; then
        log_info "node_modules 已存在，跳过 npm install（使用 --force-install 强制重装）"
    else
        log_info "安装前端依赖（可能需要几分钟）..."
        npm install --registry=https://registry.npmmirror.com
    fi

    # 构建（自动处理 rollup 原生模块缺失问题）
    log_info "构建生产版本..."
    BUILD_OUTPUT=$(npm run build 2>&1)
    BUILD_EXIT=$?

    if [ $BUILD_EXIT -ne 0 ]; then
        # 检查是否是 rollup 原生平台依赖缺失（npm 已知 bug）
        if echo "$BUILD_OUTPUT" | grep -q "@rollup/rollup-"; then
            log_warn "检测到 rollup 原生模块缺失（npm bug），自动修复中..."
            log_info "清理 node_modules 和 package-lock.json..."
            rm -rf node_modules package-lock.json
            log_info "重新安装依赖..."
            npm install --registry=https://registry.npmmirror.com
            log_info "重新构建..."
            npm run build
        else
            echo "$BUILD_OUTPUT"
            log_error "前端构建失败"
            exit 1
        fi
    fi

    if [ ! -d "dist" ]; then
        log_error "前端构建失败，dist 目录不存在"
        exit 1
    fi

    log_info "前端构建完成"
}

# ==================== 部署文件 ====================
deploy_files() {
    log_step "Step 3/7: 部署文件到 ${DEPLOY_DIR}"

    # 创建目录
    if [ ! -d "$DEPLOY_DIR" ]; then
        mkdir -p "$DEPLOY_DIR"
        log_info "创建部署目录: ${DEPLOY_DIR}"
    fi

    # 复制后端文件（排除不需要的文件）
    log_info "复制后端文件..."
    BACKEND_SRC="$(cd "$(dirname "$0")" && pwd)/backend"
    rsync -av --delete \
        --exclude='.git' \
        --exclude='.env' \
        --exclude='vendor' \
        --exclude='tests' \
        --exclude='storage/cache/*.json' \
        --exclude='storage/cache/*.lock' \
        --exclude='*.txt' \
        --exclude='tmp_*.php' \
        --exclude='composer.phar' \
        --exclude='.phpunit.result.cache' \
        --exclude='node_modules' \
        "$BACKEND_SRC/" "$BACKEND_DIR/" 2>/dev/null || \
        cp -r "$BACKEND_SRC"/* "$BACKEND_DIR/" 2>/dev/null

    # 安装 Composer 依赖
    if [ -f "$BACKEND_DIR/composer.json" ]; then
        log_info "安装 PHP 依赖 (Composer)..."
        cd "$BACKEND_DIR"
        if command -v composer &>/dev/null; then
            composer install --no-dev --optimize-autoloader
        elif [ -f "$BACKEND_DIR/composer.phar" ]; then
            php composer.phar install --no-dev --optimize-autoloader
        elif [ -f "$BACKEND_DIR/vendor/autoload.php" ]; then
            log_info "vendor 目录已存在，跳过 Composer 安装"
        else
            log_warn "Composer 未安装且 vendor 目录不存在，请手动执行: composer install"
        fi
    fi

    # 复制前端构建产物到 public 目录
    log_info "部署前端文件到 ${PUBLIC_DIR}..."
    mkdir -p "$PUBLIC_DIR"
    if [ -d "$FRONTEND_DIR/dist" ]; then
        cp -r "$FRONTEND_DIR/dist"/* "$PUBLIC_DIR/"
    fi

    # 确保 public/.htaccess 存在
    if [ ! -f "$PUBLIC_DIR/.htaccess" ]; then
        cat > "$PUBLIC_DIR/.htaccess" << 'HTACCESS'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [QSA,L]
HTACCESS
    fi

    # 创建必要的目录
    mkdir -p "$BACKEND_DIR/storage/cache"
    chmod -R 755 "$BACKEND_DIR/storage"
}

# ==================== 配置 .env ====================
configure_env() {
    log_step "Step 4/7: 配置环境变量"

    # 生成随机密码和密钥
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(openssl rand -base64 12 2>/dev/null || echo "card_$(date +%s)_pass")
    fi
    if [ -z "$JWT_SECRET" ]; then
        JWT_SECRET=$(openssl rand -base64 48 2>/dev/null | tr -d '\n' || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | head -c64)
    fi

    # 检测当前服务器 IP
    SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}')
    APP_URL="http://${SERVER_IP}"

    cat > "$BACKEND_DIR/.env" << EOF
APP_DEBUG=false
APP_URL=${APP_URL}

DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_PREFIX=${DB_PREFIX}

JWT_SECRET=${JWT_SECRET}

PAYMENT_TYPE=${PAYMENT_TYPE}
PAYMENT_API_URL=${PAYMENT_API_URL}
PAYMENT_APP_ID=${PAYMENT_APP_ID}
PAYMENT_APP_KEY=${PAYMENT_APP_KEY}
EOF

    log_info ".env 配置文件已生成"
    log_info "  - 数据库密码: ${DB_PASS}"
    log_info "  - JWT 密钥: ${JWT_SECRET:0:16}..."
}

# ==================== 导入数据库 ====================
import_database() {
    log_step "Step 5/7: 初始化数据库"

    SCHEMA_FILE="$BACKEND_DIR/database/schema.sql"

    if [ ! -f "$SCHEMA_FILE" ]; then
        log_error "数据库结构文件不存在: ${SCHEMA_FILE}"
        return 1
    fi

    if ! command -v mysql &>/dev/null; then
        log_warn "MySQL 客户端不可用，请手动导入数据库"
        log_info "数据库文件: ${SCHEMA_FILE}"
        log_info "请在宝塔面板 → 数据库 中导入该文件"
        return 0
    fi

    # 尝试获取 MySQL root 密码
    MYSQL_ROOT_PASS=""
    if [ -f /www/server/panel/data/default.db ]; then
        # 宝塔默认数据库密码存储位置
        log_info "尝试从宝塔面板获取数据库密码..."
        if command -v python3 &>/dev/null; then
            MYSQL_ROOT_PASS=$(python3 -c "
import sqlite3
try:
    conn = sqlite3.connect('/www/server/panel/data/default.db')
    cur = conn.cursor()
    cur.execute(\"SELECT mysql_root FROM config WHERE id=1\")
    row = cur.fetchone()
    if row: print(row[0])
    conn.close()
except: pass
" 2>/dev/null)
        fi
    fi

    if [ -z "$MYSQL_ROOT_PASS" ]; then
        log_warn "无法自动获取 MySQL root 密码"
        read -r -p "请输入 MySQL root 密码（留空跳过数据库导入）: " MYSQL_ROOT_PASS
        if [ -z "$MYSQL_ROOT_PASS" ]; then
            log_info "跳过数据库导入，请手动导入"
            return 0
        fi
    fi

    # 创建数据库并导入
    log_info "创建数据库 ${DB_NAME}..."
    mysql -h"${DB_HOST}" -P"${DB_PORT}" -uroot -p"${MYSQL_ROOT_PASS}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

    # 创建用户并授权
    log_info "创建数据库用户 ${DB_USER}..."
    mysql -h"${DB_HOST}" -P"${DB_PORT}" -uroot -p"${MYSQL_ROOT_PASS}" -e "
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
" 2>/dev/null

    # 导入表结构
    log_info "导入数据库表结构..."
    mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "$SCHEMA_FILE" 2>/dev/null

    if [ $? -eq 0 ]; then
        log_info "数据库导入成功"
        log_info "  - 数据库名: ${DB_NAME}"
        log_info "  - 用户名: ${DB_USER}"
        log_info "  - 密码: ${DB_PASS}"
    else
        log_error "数据库导入失败，请手动导入"
    fi
}

# ==================== 设置权限 ====================
set_permissions() {
    log_step "Step 6/7: 设置文件权限"

    # 设置所有者
    chown -R www:www "$DEPLOY_DIR" 2>/dev/null || chown -R www-data:www-data "$DEPLOY_DIR" 2>/dev/null || true

    # 目录权限
    find "$DEPLOY_DIR" -type d -exec chmod 755 {} \; 2>/dev/null
    # 文件权限
    find "$DEPLOY_DIR" -type f -exec chmod 644 {} \; 2>/dev/null

    # storage 目录需要写权限
    chmod -R 775 "$BACKEND_DIR/storage" 2>/dev/null

    # .env 文件保护
    chmod 600 "$BACKEND_DIR/.env" 2>/dev/null

    log_info "文件权限设置完成"
}

# ==================== 输出配置信息 ====================
output_config() {
    log_step "Step 7/7: 部署完成！配置信息"

    cat << EOF

${GREEN}╔══════════════════════════════════════════════════════════╗
║          CardAuth 授权管理系统 - 部署完成！                ║
╚══════════════════════════════════════════════════════════╝${NC}

${CYAN}【数据库信息】${NC}
  数据库名:   ${DB_NAME}
  用户名:     ${DB_USER}
  密码:       ${DB_PASS}
  主机:       ${DB_HOST}:${DB_PORT}

${CYAN}【默认管理员账号】${NC}
  用户名:     admin
  密码:       admin123456
  ${RED}⚠ 请登录后立即修改密码！${NC}

${CYAN}【宝塔面板配置】${NC}
  1. 打开宝塔面板 → 网站 → 添加站点
  2. 域名:     ${DOMAIN}
  3. 根目录:   ${PUBLIC_DIR}
  4. PHP版本:  选择 PHP 7.4+ 或 PHP 8.x

${CYAN}【Nginx 伪静态规则】${NC}
  在宝塔面板 → 网站 → ${DOMAIN} → 伪静态 中添加:

EOF
    cat << 'NGINX_RULES'
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }

  location /api {
      try_files $uri $uri/ /index.php?$query_string;
  }

  # PHP 处理
  location ~ \.php$ {
      fastcgi_pass unix:/tmp/php-cgi-74.sock;
      fastcgi_index index.php;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      include fastcgi_params;
  }

  # 静态资源缓存
  location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
      expires 30d;
      add_header Cache-Control "public, immutable";
  }

  # 禁止访问敏感文件
  location ~ /\.(?!well-known) {
      deny all;
  }
  location ~ /(storage|vendor|composer\.(json|lock)|\.env) {
      deny all;
      return 404;
  }
NGINX_RULES

    cat << EOF

${CYAN}【快速命令】${NC}
  修改管理员密码:
    cd ${BACKEND_DIR} && php -r "
      require 'vendor/autoload.php';
      echo password_hash('新密码', PASSWORD_BCRYPT) . PHP_EOL;
    "
    # 然后将输出的哈希值更新到 ca_users 表中

${CYAN}【宝塔面板快速设置命令】${NC}
  # 如果已安装宝塔命令行工具，可使用以下命令添加站点：
  btpython /www/server/panel/tools.py site_add \\
    -d ${DOMAIN} \\
    -p ${PUBLIC_DIR} \\
    -php 74

${GREEN}部署路径: ${DEPLOY_DIR}${NC}
${GREEN}网站根目录: ${PUBLIC_DIR}${NC}

EOF
}

# ==================== 交互式配置 ====================
interactive_config() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗"
    echo -e "║       CardAuth 一键部署脚本 - 交互式配置                 ║"
    echo -e "╚══════════════════════════════════════════════════════════╝${NC}"
    echo ""

    read -r -p "请输入域名 [默认: ${DOMAIN}]: " input
    DOMAIN="${input:-${DOMAIN}}"

    read -r -p "请输入网站目录名 [默认: ${SITE_NAME}]: " input
    SITE_NAME="${input:-${SITE_NAME}}"
    DEPLOY_DIR="${BT_WWW_ROOT}/${SITE_NAME}"
    BACKEND_DIR="${DEPLOY_DIR}"
    PUBLIC_DIR="${BACKEND_DIR}/public"

    read -r -p "请输入数据库名 [默认: ${DB_NAME}]: " input
    DB_NAME="${input:-${DB_NAME}}"

    read -r -p "请输入数据库用户名 [默认: ${DB_USER}]: " input
    DB_USER="${input:-${DB_USER}}"

    read -r -p "请输入数据库密码（留空自动生成）: " input
    DB_PASS="${input:-${DB_PASS}}"

    echo ""
    echo -e "${YELLOW}配置确认:${NC}"
    echo "  域名:       ${DOMAIN}"
    echo "  部署目录:   ${DEPLOY_DIR}"
    echo "  数据库名:   ${DB_NAME}"
    echo "  数据库用户: ${DB_USER}"
    echo "  数据库密码: ${DB_PASS:-自动生成}"
    echo ""

    read -r -p "确认开始部署? (y/n) [默认: y]: " confirm
    if [ "${confirm}" = "n" ] || [ "${confirm}" = "N" ]; then
        echo "已取消部署"
        exit 0
    fi
}

# ==================== 主流程 ====================
main() {
    echo -e "${CYAN}"
    echo "   ____              _      _         _   "
    echo "  / ___|__ _ _ __ __| |    / \  _   _| |_ "
    echo " | |   / _\` | '__/ _\` |   / _ \| | | | __|"
    echo " | |__| (_| | | | (_| |  / ___ \ |_| | |_ "
    echo "  \____\__,_|_|  \__,_| /_/   \_\__,_|\__|"
    echo "                                          "
    echo "  授权管理系统 - 一键部署脚本 v1.0.0"
    echo -e "${NC}"

    # 解析参数
    for arg in "$@"; do
        case "$arg" in
            --force-install)
                FORCE_INSTALL=true
                log_info "强制重新安装前端依赖"
                ;;
            -i|--interactive)
                ;;
            *)
                DOMAIN="$arg"
                ;;
        esac
    done

    # 交互式配置
    if [ -z "$DOMAIN" ]; then
        interactive_config
    else
        log_info "使用命令行参数域名: ${DOMAIN}"
    fi

    # 执行部署步骤
    check_environment
    build_frontend
    deploy_files
    configure_env
    import_database
    set_permissions
    output_config

    log_info "部署脚本执行完毕！"
}

# 运行主函数
main "$@"