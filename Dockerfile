# ============================================
# CardAuth Docker - Multi-stage Build
# ============================================

# Stage 1: 前端构建
FROM node:18-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci --registry=https://registry.npmmirror.com
COPY frontend/ ./
RUN npm run build

# Stage 2: PHP 运行时
FROM php:8.1-fpm-alpine

# 安装系统依赖 & PHP 扩展
RUN set -ex \
    && apk add --no-cache \
        nginx \
        supervisor \
        curl \
        tzdata \
        netcat-openbsd \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 后端代码
WORKDIR /var/www
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
COPY backend/ ./

# 前端构建产物
COPY --from=frontend-builder /app/frontend/dist/ ./public/

# 权限
RUN set -ex \
    && mkdir -p storage/cache storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage \
    && rm -rf tests .phpunit.cache

# Nginx 配置
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor 配置 (管理 Nginx + PHP-FPM)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 入口脚本
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
