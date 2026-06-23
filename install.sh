#!/bin/bash
set -e

echo "=========================================="
echo "   项目依赖一键安装"
echo "=========================================="

# ---------- 前端 ----------
echo ""
echo "[1/2] 安装前端依赖..."
cd "$(dirname "$0")/frontend"

# 阿里镜像加速
npm config set registry https://registry.npmmirror.com

npm install
echo "前端依赖安装完成"

# ---------- 后端 ----------
echo ""
echo "[2/2] 安装后端依赖..."
cd "$(dirname "$0")/backend"

# 阿里镜像加速
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ 2>/dev/null || true

composer install
echo "后端依赖安装完成"

echo ""
echo "=========================================="
echo "   全部安装完成!"
echo "=========================================="
