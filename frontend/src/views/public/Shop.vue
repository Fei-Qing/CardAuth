﻿﻿﻿<template>
<div class="shop-app">
    <!-- ========== 全屏 Loading ========== -->
    <transition name="loader-fade">
      <div v-if="pageLoading" class="page-loader">
        <div class="loader-ring">
          <svg viewBox="0 0 40 40" width="40" height="40">
            <circle cx="20" cy="20" r="17" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="80 200" />
          </svg>
        </div>
        <span class="loader-text">加载中...</span>
      </div>
    </transition>

    <!-- ========== 动态背景 ========== -->
    <div class="bg-canvas">
      <div class="bg-grid"></div>
      <div class="bg-orb orb-a"></div>
      <div class="bg-orb orb-b"></div>
      <div class="bg-orb orb-c"></div>
    </div>

    <!-- ========== 顶部导航 ========== -->
    <header class="shop-header">
      <div class="header-bar">
        <div class="brand" @click="activeTab = 'buy'">
          <div class="brand-logo">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <div class="brand-text">
            <span class="brand-name">{{ currentProject?.name || '授权管理系统' }}</span>
            <span class="brand-sub">专业软件授权平台</span>
          </div>
        </div>

        <nav class="nav-tabs" ref="navTabsRef">
          <div class="nav-slider" :style="navSliderStyle"></div>
          <button
            v-for="tab in tabs"
            :key="tab.key"
            ref="navTabRefs"
            class="nav-tab"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
            type="button"
          >
            <component :is="tab.icon" class="tab-icon" />
            <span>{{ tab.label }}</span>
          </button>
        </nav>
      </div>
    </header>

    <!-- ========== 主内容 ========== -->
    <main class="shop-main">

      <!-- ===== 1. 授权购买 ===== -->
      <transition name="panel" mode="out-in">
        <section :key="activeTab" class="panel">
          <div v-if="activeTab === 'buy'">
          <!-- Hero -->
          <div class="hero">
            <div class="hero-text">
              <h2>{{ currentProject?.name || '全部商品' }}</h2>
              <p>精选方案 · 在线下单 · 自动交付</p>
            </div>
            <div class="hero-stat">
              <span class="stat-num">{{ products.length }}</span>
              <span class="stat-unit">项商品</span>
            </div>
          </div>

          <!-- 项目切换 -->
          <div v-if="projects.length > 1" class="project-switcher" ref="projectSwitcherRef">
            <div class="project-slider" :style="projectSliderStyle"></div>
            <button
              v-for="p in projects"
              :key="p.id"
              ref="projectChipRefs"
              class="project-chip"
              :class="{ active: currentProjectId === p.id }"
              @click="switchProject(p)"
              type="button"
            >{{ p.name }}</button>
          </div>

          <!-- 骨架屏 -->
          <div v-if="loading.projects || loading.products" class="product-grid">
            <div v-for="n in 3" :key="n" class="skeleton-card">
              <div class="sk-bar sk-w60"></div>
              <div class="sk-bar sk-w30"></div>
              <div class="sk-bar sk-wfull"></div>
              <div class="sk-bar sk-wfull"></div>
              <div class="sk-bar sk-w80"></div>
              <div class="sk-price-bar"></div>
              <div class="sk-btn-bar"></div>
            </div>
          </div>

          <!-- 空状态 -->
          <div v-else-if="!products.length" class="empty-state">
            <div class="empty-icon">
              <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <line x1="3.27" y1="6.96" x2="12" y2="12.01"/><line x1="12" y1="22.08" x2="12" y2="12"/>
              </svg>
            </div>
            <p class="empty-title">暂无可用商品</p>
            <p class="empty-desc">请联系管理员上架商品</p>
          </div>

          <!-- 商品网格 -->
          <div v-else :key="currentProjectId" class="product-grid">
            <article
              v-for="product in products"
              :key="product.id"
              class="product-card"
              :class="{ featured: product.sort === 1 }"
            >
              <div v-if="product.sort === 1" class="card-badge">热门</div>

              <div class="card-header">
                <h3 class="card-name">{{ product.name }}</h3>
                <span class="card-duration">{{ product.duration_days == 0 ? '永久' : product.duration_days + '天' }}</span>
              </div>

              <ul class="card-features">
                <li v-if="product.description">{{ product.description }}</li>
                <li v-else-if="product.duration_days == 0">永久有效，一次付费终身使用</li>
                <li v-else>有效期 {{ product.duration_days }} 天，自动交付卡密</li>
              </ul>

              <div class="card-price">
                <span class="price-cur">¥</span>
                <span class="price-amount">{{ parseFloat(product.price).toFixed(0) }}</span>
                <span v-if="product.original_price && product.original_price > product.price" class="price-discount">
                  -{{ Math.round((1 - product.price / product.original_price) * 100) }}%
                </span>
                <span v-if="product.original_price && product.original_price > product.price" class="price-original">¥{{ parseFloat(product.original_price).toFixed(0) }}</span>
              </div>

              <button class="card-buy-btn" @click="handleBuyNow(product)" type="button">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                立即购买
              </button>
            </article>
          </div>
          </div>

          <!-- ===== 2. 在线授权 ===== -->
          <div v-else-if="activeTab === 'activate'">
          <div class="center-card">
            <div class="center-icon icon-purple">
              <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </div>
            <h2 class="center-title">在线授权</h2>
            <p class="center-desc">使用已有卡密完成机器人授权绑定</p>

            <template v-if="activateStep === 'form'">
              <div class="form-area">
                <div class="form-field">
                  <label>卡密 <em>*</em></label>
                  <input v-model="activateForm.card_key" class="form-input" placeholder="请输入卡密" />
                </div>
                <div class="form-field">
                  <label>机器人QQ <em>*</em></label>
                  <input v-model="activateForm.bot_qq" class="form-input" placeholder="5-15位数字" maxlength="15" />
                </div>
                <div class="form-field">
                  <label>联系人QQ <em>*</em></label>
                  <input v-model="activateForm.contact_qq" class="form-input" placeholder="5-15位数字" maxlength="15" />
                </div>
                <div class="form-field">
                  <label>联系人名称</label>
                  <input v-model="activateForm.contact_name" class="form-input" placeholder="选填" maxlength="50" />
                </div>
                <button class="btn-submit btn-purple" :disabled="loading.activate" @click="handleActivate" type="button">
                  <span v-if="loading.activate" class="spinner"></span><span v-else>确认授权</span>
                </button>
              </div>
            </template>

          </div>
          </div>

          <!-- ===== 3. 授权查询 ===== -->
          <div v-else-if="activeTab === 'query'">
          <div class="query-tabs" ref="queryTabsRef">
            <div class="query-slider" :style="querySliderStyle"></div>
            <button v-for="qt in queryTabs" :key="qt.key" ref="queryTabRefs" class="query-tab" :class="{ active: queryTab === qt.key }" @click="queryTab = qt.key; clearQueryResults()" type="button">{{ qt.label }}</button>
          </div>

          <transition name="query-switch" mode="out-in">
            <!-- 卡密查询 -->
            <div v-if="queryTab === 'card'" key="card" class="center-card">
              <div class="center-icon icon-green">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
              <h2 class="center-title">卡密查询</h2>
              <p class="center-desc">查询卡密的授权状态、绑定信息及有效期</p>

              <div class="form-field">
                <label>卡密</label>
                <input v-model="queryCardKey" class="form-input" placeholder="请输入卡密" @keyup.enter="handleQueryCard" :disabled="loading.query" />
              </div>
              <button class="btn-submit btn-green full" :disabled="!queryCardKey.trim() || loading.query" @click="handleQueryCard" type="button">
                <span v-if="loading.query" class="spinner"></span><span v-else>立即查询</span>
              </button>
            </div>

            <!-- 机器人QQ查询 -->
            <div v-else-if="queryTab === 'bot'" key="bot" class="center-card">
              <div class="center-icon icon-orange">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <h2 class="center-title">授权查询</h2>
              <p class="center-desc">按机器人QQ号查询授权绑定状态</p>

              <div class="form-field">
                <label>机器人QQ</label>
                <input v-model="queryBotQq" class="form-input" placeholder="请输入机器人QQ号" @keyup.enter="handleQueryBot" :disabled="loading.authQuery" />
              </div>
              <button class="btn-submit btn-warn full" :disabled="!queryBotQq.trim() || loading.authQuery" @click="handleQueryBot" type="button">
                <span v-if="loading.authQuery" class="spinner"></span><span v-else>查询授权</span>
              </button>
            </div>
          </transition>
          </div>
        </section>
      </transition>

      <!-- 卡密查询结果弹窗 -->
      <teleport to="body">
        <transition name="modal">
          <div v-if="cardDialogVisible && cardResult" class="modal-overlay" @click.self="cardDialogVisible = false">
            <div class="modal-box card-result-modal" @click.stop>
              <button class="modal-close" @click="cardDialogVisible = false" type="button">&times;</button>
              <div class="modal-title">卡密查询结果</div>

              <div class="cr-status" :class="cardResult.status">
                <span class="cr-status-dot"></span>
                <span class="cr-status-text">{{ cardResult.status_text }}</span>
              </div>

              <div class="cr-body">
                <div class="cr-row"><span>卡密</span><code class="mono">{{ cardResult.card_key }}</code></div>
                <div class="cr-row"><span>项目</span><span>{{ cardResult.project_name }}</span></div>
                <div class="cr-row"><span>类型</span><span>{{ cardResult.type }}</span></div>
                <div class="cr-row"><span>有效期</span><span>{{ cardResult.expire_time || '永久有效' }}</span></div>
                <div class="cr-divider"></div>
                <div class="cr-row"><span>机器人QQ</span><span class="mono">{{ cardResult.bot_qq || '-' }}</span></div>
                <div class="cr-row"><span>联系人QQ</span><span class="mono">{{ cardResult.contact_qq || '-' }}</span></div>
                <div class="cr-row" v-if="cardResult.bind_info?.machine_id"><span>绑定设备</span><span class="mono">{{ cardResult.bind_info.machine_id }}</span></div>
                <div class="cr-row" v-if="cardResult.bound_at"><span>绑定时间</span><span>{{ cardResult.bound_at }}</span></div>
              </div>

              <div class="modal-footer">
                <button class="btn-submit btn-blue full" @click="cardDialogVisible = false" type="button">知道了</button>
              </div>
            </div>
          </div>
        </transition>
      </teleport>

      <!-- 在线授权成功弹窗 -->
      <teleport to="body">
        <transition name="modal">
          <div v-if="activateSuccessDialogVisible && activateResult" class="modal-overlay" @click.self="resetActivate">
            <div class="modal-box activate-success-modal" @click.stop>
              <button class="modal-close" @click="resetActivate" type="button">&times;</button>
              <div class="act-success-anim">
                <div class="act-success-ring">
                  <svg class="act-check" viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
              </div>
              <h3 class="act-success-title">{{ activateResult?.is_renew ? '续费成功' : '授权成功' }}</h3>
              <p class="act-success-sub">{{ activateResult?.is_renew ? '授权有效期已延长' : '卡密已成功绑定到以下机器人' }}</p>
              <div class="act-success-body">
                <div class="act-row"><span>所属项目</span><strong>{{ activateResult.project_name }}</strong></div>
                <div class="act-row"><span>机器人QQ</span><strong class="mono">{{ activateResult.bot_qq }}</strong></div>
                <div class="act-row"><span>联系人QQ</span><strong class="mono">{{ activateResult.contact_qq }}</strong></div>
                <div class="act-row"><span>卡密</span><strong class="mono">{{ activateResult.card_key }}</strong></div>
                <div class="act-row" v-if="activateResult.expire_time"><span>到期时间</span><strong>{{ activateResult.expire_time }}</strong></div>
                <div class="act-row" v-else><span>有效期</span><strong>永久有效</strong></div>
              </div>
              <div class="modal-footer">
                <button class="btn-submit btn-blue full" @click="resetActivate" type="button">继续授权</button>
              </div>
            </div>
          </div>
        </transition>
      </teleport>

      <!-- 在线授权失败弹窗 -->
      <teleport to="body">
        <transition name="modal">
          <div v-if="activateErrorDialogVisible" class="modal-overlay" @click.self="activateErrorDialogVisible = false">
            <div class="modal-box activate-error-modal" @click.stop>
              <button class="modal-close" @click="activateErrorDialogVisible = false" type="button">&times;</button>
              <div class="act-error-anim">
                <div class="act-error-ring">
                  <svg class="act-cross" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
              </div>
              <h3 class="act-error-title">授权失败</h3>
              <p class="act-error-sub">请核对信息后重试</p>
              <div class="act-error-body">
                <p>{{ activateError }}</p>
              </div>
              <div class="modal-footer">
                <button class="btn-submit btn-gray full" @click="activateErrorDialogVisible = false" type="button">知道了</button>
              </div>
            </div>
          </div>
        </transition>
      </teleport>

      <!-- 机器人QQ查询结果弹窗 -->
      <teleport to="body">
        <transition name="modal">
          <div v-if="queryBotDialogVisible && authQueryResult" class="modal-overlay" @click.self="queryBotDialogVisible = false">
            <div class="modal-box query-result-modal" @click.stop>
              <button class="modal-close" @click="queryBotDialogVisible = false" type="button">&times;</button>
              <div class="modal-title">查询结果</div>

              <div class="qr-summary">
                <div class="qr-stat" :class="{ ok: authQueryResult.has_valid_auth }">
                  <span class="qr-icon">{{ authQueryResult.has_valid_auth ? '✓' : '✗' }}</span>
                  <span class="qr-text">{{ authQueryResult.has_valid_auth ? '有有效授权' : '无有效授权' }}</span>
                </div>
                <div class="qr-grid">
                  <div class="qr-cell"><span class="qr-num">{{ authQueryResult.total }}</span><span class="qr-label">总记录</span></div>
                  <div class="qr-cell"><span class="qr-num ok">{{ authQueryResult.active_count }}</span><span class="qr-label">有效</span></div>
                  <div class="qr-cell"><span class="qr-num warn">{{ authQueryResult.expired_count }}</span><span class="qr-label">已过期</span></div>
                </div>
              </div>

              <div v-if="authQueryResult.list.length" class="qr-list">
                <div v-for="item in authQueryResult.list" :key="item.id" class="qr-item">
                  <div class="qr-item-head">
                    <span class="qr-qq">{{ item.bot_qq }}</span>
                    <span class="qr-badge" :class="item.is_expired ? 'expired' : (item.status === 'revoked' ? 'revoked' : 'active')">{{ item.is_expired ? '已过期' : (item.status === 'revoked' ? '已撤销' : '有效') }}</span>
                  </div>
                  <div class="qr-item-body">
                    <div class="qr-row"><span>所属项目</span><span>{{ item.project_name_full || item.project_name }}</span></div>
                    <div class="qr-row"><span>首次授权</span><span>{{ item.authorized_at || '-' }}</span></div>
                    <div class="qr-row"><span>到期时间</span><span>{{ item.expire_time || '永久有效' }}</span></div>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button class="btn-submit btn-blue full" @click="queryBotDialogVisible = false" type="button">知道了</button>
              </div>
            </div>
          </div>
        </transition>
      </teleport>
    </main>

    <!-- ========== 底部 ========== -->
    <footer class="shop-footer">
      <p>CardAuth 授权管理系统 &copy; {{ new Date().getFullYear() }}</p>
      <p class="footer-sub">Powered by Vue3 + Element Plus</p>
    </footer>

    <!-- ========== 购买弹窗 ========== -->
    <teleport to="body">
      <transition name="modal">
        <div v-if="buyDialogVisible" class="modal-overlay" @click.self="buyDialogVisible = false">
          <div class="modal-box" @click.stop>
            <button class="modal-close" @click="buyDialogVisible = false" type="button">&times;</button>

            <template v-if="buyStep === 'confirm'">
              <div class="modal-product-header">
                <div class="modal-product-icon">
                  <IconBuy />
                </div>
                <div class="modal-product-info">
                  <div class="modal-product-title">{{ currentProject?.name }} - {{ buyingProduct?.name }}</div>
                  <div class="modal-product-meta">{{ buyingProduct?.duration_days == 0 ? '永久授权' : buyingProduct?.duration_days + '天授权' }}</div>
                </div>
                <div class="modal-product-price">
                  <span class="mp-cur">¥</span>
                  <span class="mp-amount">{{ parseFloat(buyingProduct?.price || 0).toFixed(0) }}</span>
                </div>
              </div>

              <div class="modal-body">
                <div class="form-field modal-field">
                  <label>机器人QQ <em>*</em></label>
                  <input v-model="buyBotQq" class="form-input" placeholder="5-15位数字" maxlength="15" />
                </div>
                <div class="form-field modal-field">
                  <label>联系人QQ <em>*</em></label>
                  <input v-model="buyContactQq" class="form-input" placeholder="5-15位数字" maxlength="15" />
                </div>
                <div class="form-field modal-field">
                  <label>联系人名称</label>
                  <input v-model="buyContactName" class="form-input" placeholder="选填" maxlength="50" />
                </div>

                <div class="form-field modal-field">
                  <label>优惠码</label>
                  <div class="coupon-row">
                    <input v-model="buyCouponCode" class="form-input flex-1" placeholder="选填" />
                    <button class="btn-sm btn-green" :disabled="!buyCouponCode.trim() || loading.buyCoupon" @click="handleCheckBuyCoupon" type="button">
                      <span v-if="loading.buyCoupon" class="spinner sm"></span><span v-else>验证</span>
                    </button>
                  </div>
                  <span v-if="buyCouponResult" class="coupon-hint" :class="{ ok: buyCouponResult.valid, fail: !buyCouponResult.valid }">{{ buyCouponResult.message }}<template v-if="buyCouponResult.valid"> -¥{{ buyCouponResult.discount_amount }}</template></span>
                </div>

                <div class="form-field modal-field">
                  <label>支付方式</label>
                  <div class="pay-opts">
                    <label v-for="opt in payOptions" :key="opt.value" class="pay-opt" :class="{ active: payType === opt.value }">
                      <input type="radio" v-model="payType" :value="opt.value" hidden />
                      <span class="pay-icon" v-html="opt.icon"></span>
                      <span class="pay-label">{{ opt.label }}</span>
                      <span class="pay-check"><svg viewBox="0 0 16 16" width="12" height="12" fill="currentColor"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg></span>
                    </label>
                  </div>
                </div>

                <div class="m-total">
                  <div class="total-line">
                    <span class="total-label">应付金额</span>
                    <span v-if="buyCouponResult?.valid" class="m-old-price">¥{{ parseFloat(buyingProduct?.price || 0).toFixed(2) }}</span>
                    <span class="m-total-price">¥{{ buyFinalAmount.toFixed(2) }}</span>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn-ghost" @click="buyDialogVisible = false" type="button">取消</button>
                <button class="btn-submit btn-blue" :disabled="loading.pay" @click="handlePay" type="button">
                  <span v-if="loading.pay" class="spinner"></span><span v-else>立即支付</span>
                </button>
              </div>
            </template>

            <template v-else-if="buyStep === 'success'">
              <div class="success-block">
                <div class="success-ring">
                  <svg class="check-svg" viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 class="modal-title">购买成功</h3>
                <p class="success-sub">卡密已自动绑定到机器人QQ，请妥善保存</p>
                <div class="key-box">
                  <code class="mono">{{ orderResult?.card_key }}</code>
                  <button class="btn-sm btn-green" @click="copyCardKey" type="button">复制</button>
                </div>
                <div class="success-rows">
                  <div class="s-row"><span>订单号</span><span class="mono">{{ orderResult?.order_no }}</span></div>
                  <div class="s-row"><span>商品</span><span>{{ currentProject?.name }} {{ buyingProduct?.name }}</span></div>
                  <div class="s-row"><span>机器人QQ</span><strong class="mono">{{ buyBotQq }}</strong></div>
                  <div class="s-row"><span>联系人QQ</span><strong class="mono">{{ buyContactQq }}</strong></div>
                </div>
                <div class="modal-footer">
                  <button class="btn-submit btn-blue full" @click="buyDialogVisible = false; activeTab = 'query'" type="button">去查询授权</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick, h } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api'

/* ===== 图标组件 ===== */
const IconBuy = () => h('svg', { viewBox: '0 0 16 16', width: 16, height: 16, fill: 'currentColor' }, [
  h('path', { d: 'M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z' })
])
const IconActivate = () => h('svg', { viewBox: '0 0 16 16', width: 16, height: 16, fill: 'currentColor' }, [
  h('path', { d: 'M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z' })
])
const IconQuery = () => h('svg', { viewBox: '0 0 16 16', width: 16, height: 16, fill: 'currentColor' }, [
  h('path', { d: 'M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z' })
])
const CheckIcon = () => h('svg', { viewBox: '0 0 16 16', width: 14, height: 14, fill: 'currentColor' }, [
  h('path', { d: 'M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z' })
])

/* ===== 导航 ===== */
const activeTab = ref('buy')
const tabs = [
  { key: 'buy', label: '购买授权', icon: IconBuy },
  { key: 'activate', label: '在线授权', icon: IconActivate },
  { key: 'query', label: '授权查询', icon: IconQuery },
]
const navTabsRef = ref(null)
const navTabRefs = ref([])
const navSliderStyle = ref({ width: '0px', transform: 'translateX(0px)', opacity: 0 })

function updateNavSlider() {
  nextTick(() => {
    const container = navTabsRef.value
    const buttons = navTabRefs.value
    if (!container || !buttons || !buttons.length) return
    const activeIndex = tabs.findIndex(t => t.key === activeTab.value)
    const activeBtn = buttons[activeIndex]
    if (!activeBtn) return
    const containerRect = container.getBoundingClientRect()
    const btnRect = activeBtn.getBoundingClientRect()
    navSliderStyle.value = {
      width: `${btnRect.width}px`,
      transform: `translateX(${btnRect.left - containerRect.left}px)`,
      opacity: 1
    }
  })
}

watch(activeTab, updateNavSlider)
onMounted(updateNavSlider)

const queryTabsRef = ref(null)
const queryTabRefs = ref([])
const querySliderStyle = ref({ width: '0px', transform: 'translateX(0px)', opacity: 0 })

function updateQuerySlider() {
  nextTick(() => {
    const container = queryTabsRef.value
    const buttons = queryTabRefs.value
    if (!container || !buttons || !buttons.length) return
    const activeIndex = queryTabs.findIndex(t => t.key === queryTab.value)
    const activeBtn = buttons[activeIndex]
    if (!activeBtn) return
    const containerRect = container.getBoundingClientRect()
    const btnRect = activeBtn.getBoundingClientRect()
    querySliderStyle.value = {
      width: `${btnRect.width}px`,
      transform: `translateX(${btnRect.left - containerRect.left}px)`,
      opacity: 1
    }
  })
}

/* ===== 加载状态 ===== */
const loading = reactive({ projects: false, products: false, query: false, authQuery: false, pay: false, buyCoupon: false, activate: false, submit: false })
const pageLoading = ref(true)
const initialLoaded = ref(false)

/* ===== 项目 & 商品 ===== */
const projects = ref([])
const currentProjectId = ref(0)
const products = ref([])
const currentProject = computed(() => projects.value.find(p => p.id === currentProjectId.value))

const projectSwitcherRef = ref(null)
const projectChipRefs = ref([])
const projectSliderStyle = ref({ width: '0px', transform: 'translateX(0px)', opacity: 0 })

function updateProjectSlider() {
  nextTick(() => {
    const container = projectSwitcherRef.value
    const buttons = projectChipRefs.value
    if (!container || !buttons || !buttons.length) return
    const activeIndex = projects.value.findIndex(p => p.id === currentProjectId.value)
    const activeBtn = buttons[activeIndex]
    if (!activeBtn) return
    const containerRect = container.getBoundingClientRect()
    const btnRect = activeBtn.getBoundingClientRect()
    projectSliderStyle.value = {
      width: `${btnRect.width}px`,
      transform: `translateX(${btnRect.left - containerRect.left}px)`,
      opacity: 1
    }
  })
}

watch(currentProjectId, updateProjectSlider)

/* ===== 购买 ===== */
const buyDialogVisible = ref(false)
const buyStep = ref('confirm')
const buyingProduct = ref(null)
const payType = ref('alipay')
const buyBotQq = ref('')
const buyContactQq = ref('')
const buyContactName = ref('')
const orderResult = ref(null)
const buyCouponCode = ref('')
const buyCouponResult = ref(null)
const buyFinalAmount = computed(() => {
  if (buyCouponResult.value?.valid) return buyCouponResult.value.final_amount
  return parseFloat(buyingProduct.value?.price || 0)
})
const payOptions = [
  { value: 'alipay', label: '支付宝', icon: '<svg viewBox="0 0 24 24" width="18" height="18" fill="#1677ff"><circle cx="12" cy="12" r="10"/></svg>' },
  { value: 'wxpay', label: '微信', icon: '<svg viewBox="0 0 24 24" width="18" height="18" fill="#07c160"><circle cx="12" cy="12" r="10"/></svg>' },
  { value: 'qqpay', label: 'QQ钱包', icon: '<svg viewBox="0 0 24 24" width="18" height="18" fill="#12b7f5"><circle cx="12" cy="12" r="10"/></svg>' },
]

/* ===== 查询 ===== */
const queryTab = ref('card')
const queryTabs = [{ key: 'card', label: '卡密查询' }, { key: 'bot', label: '机器人QQ查询' }]
const queryCardKey = ref('')

watch(queryTab, updateQuerySlider)
watch(activeTab, () => { if (activeTab.value === 'query') updateQuerySlider() })

const cardResult = ref(null)
const cardDialogVisible = ref(false)
const queryBotQq = ref('')
const authQueryResult = ref(null)
const queryBotDialogVisible = ref(false)

/* ===== 在线授权 ===== */
const activateStep = ref('form')
const activateForm = reactive({ card_key: '', bot_qq: '', contact_qq: '', contact_name: '' })
const activateResult = ref(null)
const activateSuccessDialogVisible = ref(false)
const activateErrorDialogVisible = ref(false)
const activateError = ref('')

/* ===== 初始化 ===== */
onMounted(async () => {
  pageLoading.value = true
  loading.projects = true
  try {
    const res = await request.get('/public/projects')
    projects.value = res.data || []
    if (projects.value.length > 0) await switchProject(projects.value[0])
  } catch { /* handled */ }
  finally {
    loading.projects = false
    updateProjectSlider()
  }
  initialLoaded.value = true
  pageLoading.value = false
})

async function switchProject(project) { currentProjectId.value = project.id; await fetchProducts(project.id); updateProjectSlider() }

async function fetchProducts(projectId) {
  loading.products = true
  try {
    const res = await request.get(`/public/projects/${projectId}/card-types`)
    products.value = (res.data || []).map(p => ({ ...p, original_price: p.original_price ? parseFloat(p.original_price) : 0 }))
  } catch { products.value = [] }
  finally { loading.products = false }
}

/* ===== 购买流程 ===== */
function handleBuyNow(product) {
  buyingProduct.value = product; buyStep.value = 'confirm'; payType.value = 'alipay'
  buyBotQq.value = ''; buyContactQq.value = ''; buyContactName.value = ''
  buyCouponCode.value = ''; buyCouponResult.value = null; orderResult.value = null
  buyDialogVisible.value = true
}

async function handleCheckBuyCoupon() {
  if (!buyCouponCode.value.trim()) return
  loading.buyCoupon = true; buyCouponResult.value = null
  try {
    const res = await request.post('/public/coupons/validate', { code: buyCouponCode.value.trim(), project_id: currentProjectId.value, amount: parseFloat(buyingProduct.value?.price || 0) })
    buyCouponResult.value = res.data
  } catch { buyCouponResult.value = null }
  finally { loading.buyCoupon = false }
}

async function handlePay() {
  if (!buyBotQq.value.trim()) { ElMessage.error('请输入机器人QQ'); return }
  if (!/^\d{5,15}$/.test(buyBotQq.value.trim())) { ElMessage.error('机器人QQ格式不正确（5-15位数字）'); return }
  if (!buyContactQq.value.trim()) { ElMessage.error('请输入联系人QQ'); return }
  if (!/^\d{5,15}$/.test(buyContactQq.value.trim())) { ElMessage.error('联系人QQ格式不正确（5-15位数字）'); return }
  loading.pay = true
  try {
    const res = await request.post('/public/orders', {
      project_id: currentProjectId.value, card_type_id: buyingProduct.value.id, pay_type: payType.value,
      contact_info: `${buyContactQq.value.trim()}${buyContactName.value.trim() ? ' (' + buyContactName.value.trim() + ')' : ''}`,
      coupon_code: buyCouponResult.value?.valid ? buyCouponCode.value.trim() : '',
      bot_qq: buyBotQq.value.trim(), contact_qq: buyContactQq.value.trim(),
    })
    if (res.data.pay_url) {
      ElMessage.success('订单创建成功，5秒后跳转支付页面')
      setTimeout(() => { window.open(res.data.pay_url, '_blank') }, 5000)
    }
    pollOrderStatus(res.data.order_no)
  } catch { /* handled */ }
  finally { loading.pay = false }
}

function pollOrderStatus(orderNo) {
  const timer = setInterval(async () => {
    try {
      const res = await request.get('/public/orders/query', { params: { order_no: orderNo } })
      if (res.data.status === 'paid') { clearInterval(timer); orderResult.value = res.data; buyStep.value = 'success'; ElMessage.success('支付成功！') }
    } catch { clearInterval(timer) }
  }, 3000)
  setTimeout(() => clearInterval(timer), 180000)
}

function copyCardKey() {
  if (orderResult.value?.card_key) { navigator.clipboard.writeText(orderResult.value.card_key); ElMessage.success('已复制卡密') }
}

/* ===== 在线授权 ===== */
async function handleActivate() {
  const { card_key, bot_qq, contact_qq } = activateForm
  if (!card_key.trim()) { activateError.value = '请输入卡密'; activateErrorDialogVisible.value = true; return }
  if (!bot_qq.trim()) { activateError.value = '请输入机器人QQ'; activateErrorDialogVisible.value = true; return }
  if (!/^\d{5,15}$/.test(bot_qq.trim())) { activateError.value = '机器人QQ格式不正确'; activateErrorDialogVisible.value = true; return }
  if (!contact_qq.trim()) { activateError.value = '请输入联系人QQ'; activateErrorDialogVisible.value = true; return }
  if (!/^\d{5,15}$/.test(contact_qq.trim())) { activateError.value = '联系人QQ格式不正确'; activateErrorDialogVisible.value = true; return }
  loading.activate = true
  try {
    const res = await request.post('/public/authorizations/activate', {
      card_key: card_key.trim(), bot_qq: bot_qq.trim(), contact_qq: contact_qq.trim(),
      contact_name: activateForm.contact_name.trim(),
    }, { _noMessage: true })
    activateResult.value = res.data; activateSuccessDialogVisible.value = true
  } catch (err) {
    activateError.value = err?.message || '授权失败，请检查卡密和QQ格式'
    activateErrorDialogVisible.value = true
  }
  finally { loading.activate = false }
}

function resetActivate() {
  activateStep.value = 'form'; activateForm.card_key = ''; activateForm.bot_qq = ''
  activateForm.contact_qq = ''; activateForm.contact_name = ''
  activateResult.value = null; activateSuccessDialogVisible.value = false; activateErrorDialogVisible.value = false; activateError.value = ''
}

/* ===== 查询 ===== */
async function handleQueryCard() {
  if (!queryCardKey.value.trim()) return
  loading.query = true; cardResult.value = null; cardDialogVisible.value = false
  try {
    const res = await request.get('/public/cards/query', { params: { card_key: queryCardKey.value.trim() } })
    cardResult.value = res.data
    cardDialogVisible.value = true
  } catch { /* handled */ }
  finally { loading.query = false }
}

async function handleQueryBot() {
  if (!queryBotQq.value.trim()) return
  loading.authQuery = true; authQueryResult.value = null; queryBotDialogVisible.value = false
  try {
    const res = await request.get('/public/authorizations/query', { params: { bot_qq: queryBotQq.value.trim() } })
    authQueryResult.value = res.data
    queryBotDialogVisible.value = true
  } catch { /* handled */ }
  finally { loading.authQuery = false }
}

function clearQueryResults() { cardResult.value = null; cardDialogVisible.value = false; authQueryResult.value = null; queryBotDialogVisible.value = false }
</script>

<style scoped>
/* ================================================================
   0. 超拟态设计系统 (Neumorphism)
   ================================================================ */
.shop-app {
  /* 白色主题 */
  --bg-base: #f5f5f5;
  --bg-surface: rgba(255, 255, 255, 0.85);
  --bg-surface-solid: #ffffff;
  --bg-elevated: rgba(255, 255, 255, 0.92);
  --bg-inset: rgba(0, 0, 0, 0.04);
  --border: rgba(0, 0, 0, 0.08);
  --border-strong: rgba(0, 0, 0, 0.14);
  /* 柔和阴影 */
  --shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.06);
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
  --shadow-glow: 0 0 24px rgba(91, 125, 177, 0.12);
  /* 文字 */
  --text-primary: #1a1a2e;
  --text-secondary: #6b7280;
  --text-tertiary: #9ca3af;
  /* 强调色 — 莫兰迪蓝 */
  --accent: #5b7db1;
  --accent-light: #8da4c9;
  --accent-glow: rgba(91, 125, 177, 0.3);
  --success: #7aab8c;
  --warning: #c9a85b;
  --danger: #c48b8b;
  --purple: #7e9dc1;
  /* 圆角 */
  --r-xl: 24px;
  --r-lg: 18px;
  --r-md: 12px;
  --r-sm: 8px;
  /* 动效 */
  --ease: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
  --dur: 0.3s;

  min-height: 100vh;
  position: relative;
  overflow-x: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
  color: var(--text-primary);
  background: var(--bg-base);
  -webkit-font-smoothing: antialiased;
}

/* ================================================================
   1. 动态背景
   ================================================================ */
.bg-canvas {
  position: fixed; inset: 0; z-index: 0;
  background:
    radial-gradient(ellipse 80% 50% at 20% 0%, rgba(99, 102, 241, 0.06) 0%, transparent 55%),
    radial-gradient(ellipse 60% 40% at 85% 15%, rgba(236, 72, 153, 0.04) 0%, transparent 50%),
    radial-gradient(ellipse 70% 50% at 50% 100%, rgba(6, 182, 212, 0.04) 0%, transparent 55%),
    linear-gradient(180deg, #f5f5f5 0%, #fafafa 50%, #f5f5f5 100%);
}
.bg-grid {
  position: absolute; inset: 0;
  background-image: linear-gradient(rgba(0,0,0,0.04) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(0,0,0,0.04) 1px, transparent 1px);
  background-size: 48px 48px;
  mask-image: radial-gradient(ellipse at center, black 20%, transparent 75%);
}
.bg-orb {
  position: absolute; border-radius: 50%; filter: blur(140px); opacity: 0.1;
  will-change: transform;
  animation: orb-drift 25s ease-in-out infinite alternate;
}
.orb-a { width: 520px; height: 520px; top: -8%; left: -5%; background: #5b7db1; animation-duration: 30s; }
.orb-b { width: 420px; height: 420px; top: 30%; right: -8%; background: #7ea8c4; animation-duration: 25s; animation-delay: -8s; }
.orb-c { width: 380px; height: 380px; bottom: -5%; left: 30%; background: #8eb8c9; animation-duration: 28s; animation-delay: -15s; }

@keyframes orb-drift {
  0% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(40px, -50px) scale(1.08); }
  100% { transform: translate(-20px, 30px) scale(0.95); }
}

/* ================================================================
   1.5 全屏 Loading
   ================================================================ */
.page-loader {
  position: fixed; inset: 0; z-index: 9999;
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 16px;
  background: rgba(245, 245, 245, 0.96);
}
.loader-ring svg {
  color: var(--accent);
  animation: loader-spin 1s linear infinite;
}
.loader-text {
  font-size: 14px; color: var(--text-tertiary);
  letter-spacing: 2px;
}
@keyframes loader-spin {
  to { transform: rotate(360deg); }
}
.loader-fade-leave-active { transition: opacity 0.4s ease; }
.loader-fade-leave-to { opacity: 0; }

/* ================================================================
   2. 顶部导航
   ================================================================ */
.shop-header {
  position: sticky; top: 0; z-index: 100;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-bottom: 1px solid var(--border);
}
.header-bar {
  max-width: 1200px; margin: 0 auto;
  padding: 14px 28px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 24px; flex-wrap: wrap;
}
.brand { display: flex; align-items: center; gap: 12px; cursor: pointer; }
.brand-logo {
  width: 42px; height: 42px; border-radius: var(--r-md);
  background: linear-gradient(135deg, var(--accent), #7e9dc1);
  box-shadow: var(--shadow-sm), 0 0 16px rgba(99, 102, 241, 0.3);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  transition: all var(--dur) var(--ease);
}
.brand:hover .brand-logo {
  box-shadow: var(--shadow-md), 0 0 24px rgba(99, 102, 241, 0.45);
  transform: translateY(-2px);
}
.brand-text { display: flex; flex-direction: column; gap: 2px; }
.brand-name { font-size: 17px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.2px; }
.brand-sub { font-size: 11px; color: var(--text-tertiary); }

.nav-tabs {
  display: flex; gap: 4px;
  position: relative;
  padding: 4px;
  background: var(--bg-inset);
  border-radius: var(--r-md);
  border: 1px solid var(--border);
}
.nav-slider {
  position: absolute; top: 4px; left: 0;
  height: calc(100% - 8px);
  border-radius: calc(var(--r-md) - 2px);
  background: linear-gradient(135deg, rgba(91,125,177,0.9), rgba(110,150,195,0.9));
  box-shadow: 0 4px 14px rgba(91,125,177,0.35);
  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
  pointer-events: none;
  z-index: 0;
}
.nav-tab {
  position: relative; z-index: 1;
  display: flex; align-items: center; gap: 6px;
  padding: 9px 18px; border: none; background: transparent;
  font-size: 14px; color: var(--text-secondary); cursor: pointer;
  border-radius: calc(var(--r-md) - 2px); font-family: inherit; user-select: none;
  transition: color 0.3s ease, transform 0.2s ease;
}
.nav-tab:hover { color: var(--text-primary); }
.nav-tab:active { transform: scale(0.97); }
.nav-tab.active { color: #fff; }
.nav-tab .tab-icon { transition: transform 0.3s var(--ease-bounce); }
.nav-tab.active .tab-icon { transform: scale(1.1); }
.tab-icon { display: flex; align-items: center; }

/* ================================================================
   3. 主内容
   ================================================================ */
.shop-main {
  position: relative; z-index: 1;
  max-width: 1200px; margin: 0 auto;
  padding: 32px 28px 60px;
}
.panel { position: relative; width: 100%; }

/* 面板过渡 - 可打断的丝滑切换 */
.panel-enter-active {
  transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1),
              transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  will-change: transform, opacity;
}
.panel-leave-active {
  position: absolute; width: 100%;
  transition: opacity 0.15s ease-out,
              transform 0.2s ease-out;
  will-change: transform, opacity;
}
.panel-enter-from { opacity: 0; transform: translateY(24px) scale(0.96); }
.panel-leave-to { opacity: 0; transform: translateY(-10px) scale(0.98); }

/* 面板内部元素依次入场 */
.panel > * {
  opacity: 0;
  transform: translateY(10px);
  animation: panel-child-in 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.panel > *:nth-child(1) { animation-delay: 0.04s; }
.panel > *:nth-child(2) { animation-delay: 0.08s; }
.panel > *:nth-child(3) { animation-delay: 0.12s; }
.panel > *:nth-child(4) { animation-delay: 0.16s; }
.panel > *:nth-child(5) { animation-delay: 0.20s; }
.panel > *:nth-child(6) { animation-delay: 0.24s; }
@keyframes panel-child-in {
  to { opacity: 1; transform: translateY(0); }
}

/* ================================================================
   4. Hero
   ================================================================ */
.hero {
  display: flex; align-items: center; justify-content: space-between;
  padding: 28px 32px; margin-bottom: 24px;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transition: box-shadow var(--dur) var(--ease), border-color var(--dur) var(--ease);
}
.hero:hover { box-shadow: var(--shadow-lg), var(--shadow-glow); border-color: var(--border-strong); }
.hero-text h2 { font-size: 22px; font-weight: 700; margin: 0 0 6px; }
.hero-text p { font-size: 14px; color: var(--text-secondary); margin: 0; }
.hero-stat { text-align: right; }
.hero-stat .stat-num { font-size: 32px; font-weight: 800; background: linear-gradient(135deg, var(--accent-light), #a3bfd9); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent; display: block; line-height: 1; }
.hero-stat .stat-unit { font-size: 12px; color: var(--text-tertiary); }

/* ================================================================
   5. 项目切换
   ================================================================ */
.project-switcher {
  display: flex; gap: 6px; flex-wrap: wrap;
  position: relative;
  margin-bottom: 24px;
  padding: 4px;
  background: var(--bg-inset);
  border-radius: 28px;
  border: 1px solid var(--border);
  width: fit-content;
}
.project-slider {
  position: absolute; top: 4px; left: 0;
  height: calc(100% - 8px);
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(91,125,177,0.9), rgba(110,150,195,0.9));
  box-shadow: 0 4px 16px rgba(91,125,177,0.35);
  transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1),
              width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1),
              opacity 0.3s ease;
  pointer-events: none;
  z-index: 0;
}
.project-chip {
  position: relative; z-index: 1;
  padding: 9px 20px; border-radius: 24px;
  background: transparent;
  border: none;
  font-size: 14px; color: var(--text-secondary); cursor: pointer;
  transition: color 0.3s ease, transform 0.2s ease; font-family: inherit;
}
.project-chip:hover { color: var(--text-primary); }
.project-chip:active { transform: scale(0.97); }
.project-chip.active { color: #fff; }

/* ================================================================
   6. 骨架屏
   ================================================================ */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.skeleton-card {
  padding: 28px 24px; border-radius: var(--r-lg);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-md);
}
.sk-bar {
  height: 14px; border-radius: var(--r-sm); margin-bottom: 10px;
  background: linear-gradient(90deg, rgba(0,0,0,0.04) 25%, rgba(0,0,0,0.08) 50%, rgba(0,0,0,0.04) 75%);
  background-size: 200% 100%; animation: shimmer 1.5s infinite;
}
.sk-w60 { width: 60%; } .sk-w30 { width: 30%; } .sk-w80 { width: 80%; } .sk-wfull { width: 100%; }
.sk-price-bar { height: 40px; border-radius: var(--r-md); margin-top: 20px; background: linear-gradient(90deg, rgba(0,0,0,0.04) 25%, rgba(0,0,0,0.08) 50%, rgba(0,0,0,0.04) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
.sk-btn-bar { height: 46px; border-radius: var(--r-md); margin-top: 16px; background: linear-gradient(90deg, rgba(91,125,177,0.08) 25%, rgba(91,125,177,0.16) 50%, rgba(91,125,177,0.08) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ================================================================
   7. 空状态
   ================================================================ */
.empty-state {
  text-align: center; padding: 80px 0;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.empty-icon {
  width: 80px; height: 80px; margin: 0 auto 20px;
  border-radius: 50%;
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-tertiary);
}
.empty-title { font-size: 18px; color: var(--text-secondary); margin: 0 0 6px; }
.empty-desc { font-size: 14px; color: var(--text-tertiary); margin: 0; }

/* ================================================================
   8. 商品卡片
   ================================================================ */
.product-card {
  position: relative; overflow: hidden;
  padding: 26px 24px 22px;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transition: all var(--dur) var(--ease);
  animation: card-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.product-card:nth-child(1) { animation-delay: 0.05s; }
.product-card:nth-child(2) { animation-delay: 0.12s; }
.product-card:nth-child(3) { animation-delay: 0.19s; }
.product-card:nth-child(4) { animation-delay: 0.26s; }
.product-card:nth-child(5) { animation-delay: 0.33s; }
.product-card:nth-child(6) { animation-delay: 0.40s; }
.product-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-lg), var(--shadow-glow);
  border-color: var(--border-strong);
}
.product-card:active { transform: translateY(-2px); }
.product-card.featured { border-color: rgba(91,125,177,0.35); }
.product-card.featured::before {
  content: ''; position: absolute; inset: 0; border-radius: var(--r-xl);
  background: linear-gradient(135deg, rgba(91,125,177,0.08), transparent 55%);
  pointer-events: none;
}
@keyframes card-in {
  0% { opacity: 0; transform: translateY(24px) scale(0.96); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

.card-badge {
  position: absolute; top: 16px; right: 16px;
  padding: 4px 12px; border-radius: 20px;
  font-size: 11px; font-weight: 600;
  background: linear-gradient(135deg, #c9a85b, #c48b8b);
  color: white;
  box-shadow: 0 4px 12px rgba(201,168,91,0.3);
}

.card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; gap: 12px; }
.card-name { font-size: 18px; font-weight: 700; margin: 0; line-height: 1.35; }
.card-duration {
  padding: 4px 12px; border-radius: 8px; font-size: 12px;
  background: var(--bg-inset); color: var(--text-secondary);
  border: 1px solid var(--border);
  flex-shrink: 0;
}

.card-features { list-style: none; padding: 0; margin: 0 0 22px; display: flex; flex-direction: column; gap: 10px; }
.card-features li {
  font-size: 13px; color: var(--text-secondary); line-height: 1.6;
  padding: 8px 12px; background: var(--bg-card); border-radius: 6px;
  border-left: 3px solid var(--accent-light);
}

.card-price { display: flex; align-items: baseline; gap: 4px; margin-bottom: 18px; }
.price-cur { font-size: 16px; font-weight: 700; color: var(--danger); }
.price-amount { font-size: 36px; font-weight: 800; color: var(--danger); line-height: 1; }
.price-discount {
  padding: 3px 8px; border-radius: 6px;
  font-size: 12px; font-weight: 700;
  background: rgba(196,139,139,0.16); color: var(--danger);
  border: 1px solid rgba(196,139,139,0.35);
  margin-left: 4px;
}
.price-original { font-size: 15px; color: var(--text-tertiary); text-decoration: line-through; margin-left: 8px; }

.card-buy-btn {
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, var(--accent), #7e9dc1);
  border: none;
  border-radius: var(--r-md);
  color: #fff; font-size: 15px; font-weight: 600;
  cursor: pointer; transition: all var(--dur) var(--ease);
  font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}
.card-buy-btn:hover {
  box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
  transform: translateY(-2px);
}
.card-buy-btn:active { transform: translateY(0); }

/* ================================================================
   9. 居中卡片
   ================================================================ */
.center-card {
  max-width: 520px; margin: 0 auto;
  text-align: center; padding: 44px 32px;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.center-icon {
  width: 68px; height: 68px; margin: 0 auto 18px;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
}
.center-icon.icon-purple { color: var(--purple); }
.center-icon.icon-green { color: var(--success); }
.center-icon.icon-orange { color: var(--warning); }
.center-title { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
.center-desc { font-size: 14px; color: var(--text-secondary); margin: 0 0 28px; }

/* ================================================================
   10. 表单
   ================================================================ */
.form-area { text-align: left; }
.form-field { margin-bottom: 18px; }
.form-field label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 7px; }
.form-field label em { color: var(--danger); font-style: normal; }
.form-input {
  width: 100%; padding: 13px 16px; box-sizing: border-box;
  background: var(--bg-inset);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  color: var(--text-primary); font-size: 14px; outline: none;
  transition: all var(--dur) var(--ease); font-family: inherit;
  box-shadow: var(--shadow-sm);
}
.form-input::placeholder { color: var(--text-tertiary); }
.form-input:focus {
  border-color: rgba(91,125,177,0.5);
  box-shadow: 0 0 0 3px rgba(91,125,177,0.12), var(--shadow-sm);
}
.form-input.sm { padding: 10px 14px; font-size: 13px; }
.flex-1 { flex: 1; }

/* ================================================================
   11. 按钮系统
   ================================================================ */
.btn-sm {
  padding: 9px 16px; border: none; border-radius: var(--r-sm);
  font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit; user-select: none;
  display: flex; align-items: center; gap: 6px;
  transition: all var(--dur) var(--ease);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  color: var(--text-primary);
}
.btn-sm:active { transform: translateY(0); }
.btn-green { color: var(--success); }
.btn-green:hover { background: rgba(122,171,140,0.1); border-color: rgba(122,171,140,0.3); }
.btn-green:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

.btn-action {
  padding: 12px 28px; border: none; border-radius: var(--r-md);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  color: var(--text-primary); font-size: 14px; font-weight: 600;
  cursor: pointer; white-space: nowrap; min-width: 110px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: all var(--dur) var(--ease); font-family: inherit;
}
.btn-action:hover { border-color: var(--accent-light); color: var(--accent-light); }
.btn-action:active { transform: scale(0.98); }
.btn-action:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-warn { color: var(--warning); }
.btn-warn:hover { border-color: var(--warning); background: rgba(251,191,36,0.08); }

.btn-submit {
  width: 100%; padding: 14px; border: none; border-radius: var(--r-md);
  font-size: 15px; font-weight: 600; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all var(--dur) var(--ease); font-family: inherit; margin-top: 8px;
  color: #fff;
  box-shadow: var(--shadow-sm);
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-purple { background: linear-gradient(135deg, var(--purple), #6b8db8); }
.btn-purple:hover { box-shadow: 0 8px 24px rgba(126,157,193,0.35); }
.btn-blue { background: linear-gradient(135deg, var(--accent), #7e9dc1); }
.btn-blue:hover { box-shadow: 0 8px 24px rgba(91,125,177,0.35); }
.btn-green { background: linear-gradient(135deg, var(--success), #8fbf9f); }
.btn-green:hover { box-shadow: 0 8px 24px rgba(122,171,140,0.3); }
.btn-warn { background: linear-gradient(135deg, #c9a85b, #d4b86a); color: #1f2937; }
.btn-warn:hover { box-shadow: 0 8px 24px rgba(201,168,91,0.3); }
.btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-outline:hover { border-color: var(--border-strong); color: var(--text-primary); }
.btn-submit.full { width: 100%; }

.btn-ghost {
  flex: 1; padding: 14px; border: none; border-radius: var(--r-md);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  color: var(--text-secondary); font-size: 15px; cursor: pointer;
  font-family: inherit; transition: all var(--dur) var(--ease);
}
.btn-ghost:hover { border-color: var(--border-strong); color: var(--text-primary); }
.btn-ghost:active { transform: translateY(0); }

/* ================================================================
   12. 搜索栏
   ================================================================ */
.search-bar {
  display: flex; gap: 0; border-radius: var(--r-md); overflow: hidden;
  background: var(--bg-inset);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
}
.search-bar .form-input { border: none; border-radius: var(--r-md) 0 0 var(--r-md); background: transparent; box-shadow: none; }
.search-bar .form-input:focus { box-shadow: none; }
.search-bar .btn-action { border-radius: 0 var(--r-md) var(--r-md) 0; background: var(--bg-elevated); border: none; border-left: 1px solid var(--border); }
.search-bar .btn-action:hover { background: var(--bg-surface); color: var(--accent-light); }

/* ================================================================
   13. 查询子标签
   ================================================================ */
.query-tabs {
  display: flex; justify-content: center; gap: 4px;
  margin-bottom: 24px; padding: 5px;
  background: var(--bg-inset);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  max-width: 300px; margin-left: auto; margin-right: auto;
  position: relative;
}
.query-slider {
  position: absolute; top: 5px; left: 0;
  height: calc(100% - 10px);
  border-radius: var(--r-sm);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease;
  pointer-events: none;
  z-index: 0;
}
.query-tab {
  flex: 1; padding: 9px 0; border: none; background: none;
  font-size: 14px; color: var(--text-tertiary); cursor: pointer;
  border-radius: var(--r-sm); transition: color 0.3s var(--ease);
  font-family: inherit;
  position: relative; z-index: 1;
}
.query-tab:hover { color: var(--text-secondary); }
.query-tab.active { color: var(--text-primary); font-weight: 600; }

/* 查询子页切换动画 */
.query-switch-enter-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.query-switch-leave-active {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: absolute;
}
.query-switch-enter-from {
  opacity: 0;
  transform: translateX(30px) scale(0.96);
}
.query-switch-leave-to {
  opacity: 0;
  transform: translateX(-20px) scale(0.97);
}

/* ================================================================
   14. 详情卡片
   ================================================================ */
.detail-card {
  margin-top: 24px; border-radius: var(--r-lg); overflow: hidden;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-md);
  text-align: left;
}
.detail-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 24px;
  background: var(--bg-inset);
  border-bottom: 1px solid var(--border);
}
.detail-title { font-weight: 600; font-size: 15px; }
.detail-status { padding: 4px 12px; border-radius: 8px; font-size: 12px; font-weight: 500; }
.detail-status.unused { background: var(--bg-elevated); color: var(--text-tertiary); border: 1px solid var(--border); }
.detail-status.used { background: rgba(122,171,140,0.1); color: var(--success); border: 1px solid rgba(122,171,140,0.25); }
.detail-status.disabled { background: rgba(196,139,139,0.1); color: var(--danger); border: 1px solid rgba(196,139,139,0.25); }

.detail-rows { padding: 4px 0; }
.d-row { display: flex; align-items: center; padding: 12px 24px; }
.d-row:last-child { border-bottom: none; }
.d-label { width: 90px; font-size: 13px; color: var(--text-tertiary); flex-shrink: 0; }
.d-val { font-size: 14px; color: var(--text-secondary); }
.d-val.bold { font-weight: 600; color: var(--text-primary); }
.d-val.mono, .mono { font-family: 'Courier New', monospace; }
.d-divider { height: 1px; background: var(--border); margin: 0 24px; }

/* ================================================================
   15. 授权查询结果
   ================================================================ */
.auth-result { margin-top: 24px; text-align: left; }
.auth-stats { display: flex; gap: 12px; justify-content: center; margin-bottom: 20px; flex-wrap: wrap; }
.stat-block {
  flex: 1; min-width: 70px; padding: 16px 12px; text-align: center;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-sm);
  transition: all var(--dur) var(--ease);
}
.stat-block:hover { border-color: var(--border-strong); transform: translateY(-2px); }
.stat-icon { font-size: 24px; font-weight: 700; display: block; }
.stat-num { font-size: 24px; font-weight: 700; display: block; color: var(--text-primary); }
.stat-label { font-size: 12px; color: var(--text-tertiary); margin-top: 4px; display: block; }

.auth-list { display: flex; flex-direction: column; gap: 12px; }
.auth-item {
  padding: 18px 24px; text-align: left;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-sm);
  transition: all var(--dur) var(--ease);
}
.auth-item:hover { border-color: var(--border-strong); transform: translateX(4px); }
.auth-item-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.auth-qq { font-weight: 600; font-size: 16px; font-family: monospace; }
.auth-badge { padding: 4px 12px; border-radius: 8px; font-size: 12px; font-weight: 500; border: 1px solid var(--border); }
.auth-badge.active { color: var(--success); background: rgba(122,171,140,0.08); border-color: rgba(122,171,140,0.2); }
.auth-badge.expired { color: var(--warning); background: rgba(251,191,36,0.08); border-color: rgba(251,191,36,0.2); }
.auth-badge.revoked { color: var(--danger); background: rgba(196,139,139,0.08); border-color: rgba(196,139,139,0.2); }
.auth-item-body { display: flex; flex-direction: column; gap: 6px; }
.auth-f { display: flex; gap: 8px; font-size: 13px; color: var(--text-secondary); }
.af-label { color: var(--text-tertiary); width: 50px; flex-shrink: 0; }
.auth-f code {
  font-family: 'Courier New', monospace;
  background: var(--bg-inset); padding: 2px 8px;
  border-radius: 4px; font-size: 12px; color: var(--text-secondary);
  border: 1px solid var(--border);
}

/* ================================================================
   16. 成功动画
   ================================================================ */
.success-block { text-align: center; }
.success-ring {
  width: 80px; height: 80px; margin: 0 auto 20px;
  border-radius: 50%;
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-md), 0 0 24px rgba(122,171,140,0.2);
  display: flex; align-items: center; justify-content: center;
  color: var(--success);
  animation: ring-pop 0.5s var(--ease-bounce) both;
}
@keyframes ring-pop {
  0% { transform: scale(0); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
.success-title { font-size: 22px; font-weight: 700; margin: 0 0 20px; }
.success-sub { font-size: 14px; color: var(--text-secondary); margin: 0 0 20px; }
.success-rows { text-align: left; margin-bottom: 20px; }
.s-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 0;
  font-size: 14px;
}
.s-row:last-child { border-bottom: none; }
.s-row span { color: var(--text-tertiary); }
.s-row strong { color: var(--text-primary); }
.s-row .mono { font-family: 'Courier New', monospace; }

.key-box {
  display: flex; align-items: center; gap: 12px; justify-content: center;
  margin-bottom: 20px;
}
.key-box code {
  font-family: 'Courier New', monospace; font-size: 16px; font-weight: 600;
  padding: 12px 24px; border-radius: var(--r-md);
  background: var(--bg-inset);
  color: var(--accent-light);
  border: 1px solid var(--border);
}

/* ================================================================
   17. 标准模态窗
   ================================================================ */
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal-box {
  position: relative; width: 100%; max-width: 480px;
  max-height: 90vh; overflow-y: auto;
  padding: 28px;
  background: #ffffff;
  border-radius: var(--r-lg);
  box-shadow: 0 24px 70px rgba(0,0,0,0.35);
  color: #1f2937;
}
.modal-close {
  position: absolute; top: 14px; right: 14px;
  width: 32px; height: 32px; border-radius: 8px;
  background: transparent; border: none;
  color: #9ca3af; font-size: 22px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s ease;
}
.modal-close:hover { background: #f3f4f6; color: #374151; }
.modal-title { font-size: 20px; font-weight: 700; text-align: center; margin: 0 0 24px; color: #111827; }
.modal-body { margin-bottom: 20px; }

/* 商品头卡 */
.modal-product-header {
  display: flex; align-items: center; gap: 14px;
  padding: 16px;
  margin-bottom: 24px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}
.modal-product-icon {
  width: 44px; height: 44px; border-radius: 10px;
  background: #eef2ff;
  display: flex; align-items: center; justify-content: center;
  color: #5b7db1; flex-shrink: 0;
}
.modal-product-info { flex: 1; min-width: 0; }
.modal-product-title { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px; }
.modal-product-meta { font-size: 13px; color: #6b7280; }
.modal-product-price {
  display: flex; align-items: baseline; gap: 2px;
  color: #c48b8b; font-weight: 700;
}
.mp-cur { font-size: 14px; }
.mp-amount { font-size: 28px; line-height: 1; }

/* 弹窗表单（浅色模式） */
.modal-field { margin-bottom: 18px; }
.modal-field label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.modal-field label em { color: #c48b8b; font-style: normal; }
.modal-field .form-input {
  width: 100%; padding: 10px 14px; box-sizing: border-box;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  color: #111827; font-size: 14px; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.modal-field .form-input::placeholder { color: #9ca3af; }
.modal-field .form-input:focus {
  border-color: #5b7db1;
  box-shadow: 0 0 0 3px rgba(91,125,177,0.1);
}

.m-total {
  margin-top: 20px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
}
.total-line {
  display: flex; align-items: baseline; justify-content: flex-end; gap: 10px;
}
.total-label { font-size: 14px; color: #6b7280; margin-right: auto; }
.m-old-price { font-size: 14px; color: #9ca3af; text-decoration: line-through; }
.m-total-price { font-size: 26px; font-weight: 700; color: #c48b8b; }
.modal-footer { display: flex; gap: 12px; margin-top: 8px; }
.modal-footer .btn-submit { flex: 1; margin-top: 0; }

/* 支付方式 */
.pay-opts { display: flex; gap: 8px; flex-wrap: wrap; }
.pay-opt {
  flex: 1; min-width: 90px;
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; border-radius: 8px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  font-size: 14px; color: #4b5563; cursor: pointer;
  transition: all 0.2s ease;
}
.pay-opt:hover { border-color: #5b7db1; color: #111827; }
.pay-opt.active { border-color: #5b7db1; background: #eef2ff; color: #4a6fa3; }
.pay-icon { display: flex; align-items: center; }
.pay-label { flex: 1; }
.pay-check {
  width: 16px; height: 16px; border-radius: 50%;
  background: #5b7db1; color: #fff;
  display: flex; align-items: center; justify-content: center;
  transform: scale(0);
  transition: transform 0.2s ease;
}
.pay-opt.active .pay-check { transform: scale(1); }

/* 优惠码 */
.coupon-row { display: flex; gap: 8px; align-items: center; }
.coupon-row .btn-sm { flex-shrink: 0; }
.coupon-hint { display: block; margin-top: 6px; font-size: 12px; }
.coupon-hint.ok { color: #7aab8c; }
.coupon-hint.fail { color: #c48b8b; }

/* 弹窗内按钮 */
.modal-footer .btn-ghost {
  flex: 1; padding: 12px; border-radius: 8px;
  background: #f3f4f6; border: none;
  color: #4b5563; font-size: 14px; font-weight: 600;
  cursor: pointer; transition: background 0.2s;
}
.modal-footer .btn-ghost:hover { background: #e5e7eb; }
.modal-footer .btn-submit.btn-blue {
  background: linear-gradient(135deg, #5b7db1, #7e9dc1); color: #fff; border: none;
  padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600;
  box-shadow: 0 4px 14px rgba(91,125,177,0.3);
}
.modal-footer .btn-submit.btn-blue:hover { background: linear-gradient(135deg, #4a6fa3, #6b8db8); box-shadow: 0 6px 18px rgba(91,125,177,0.4); }
.modal-footer .btn-submit.btn-blue:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-footer .btn-submit.btn-gray {
  background: #f3f4f6; color: #4b5563; border: none;
  padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600;
  box-shadow: none;
}
.modal-footer .btn-submit.btn-gray:hover { background: #e5e7eb; }
.modal-footer .btn-submit.btn-gray:disabled { opacity: 0.6; cursor: not-allowed; }

/* 成功块 */
.success-block .success-ring {
  background: #dcfce7;
  border: 1px solid #bbf7d0;
  box-shadow: 0 0 24px rgba(122,171,140,0.25);
  color: #7aab8c;
}
.success-block .modal-title { margin-bottom: 8px; }
.success-block .success-sub { color: #6b7280; }
.success-block .key-box code {
  background: #f3f4f6;
  color: #4a6fa3;
  border: 1px solid #e5e7eb;
}
.success-block .s-row { border-bottom: 1px solid #f3f4f6; }
.success-block .s-row span { color: #6b7280; }
.success-block .s-row strong { color: #111827; }
.success-block .btn-sm.btn-green {
  background: #dcfce7; color: #7aab8c; border: none;
}
.success-block .btn-submit.btn-blue.full { background: linear-gradient(135deg, #5b7db1, #7e9dc1); color: #fff; }

/* ================================================================
   17.5 查询结果弹窗
   ================================================================ */
.query-result-modal { max-width: 480px; }
.qr-summary {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  margin-bottom: 18px;
}
.qr-stat {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-bottom: 14px;
  font-size: 16px; font-weight: 600;
  color: #c48b8b;
}
.qr-stat.ok { color: #7aab8c; }
.qr-icon {
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: currentColor; color: #fff; font-size: 14px;
}
.qr-stat.ok .qr-icon { background: #7aab8c; }
.qr-stat:not(.ok) .qr-icon { background: #c48b8b; }
.qr-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
.qr-cell {
  text-align: center; padding: 10px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}
.qr-num { display: block; font-size: 20px; font-weight: 700; color: #111827; }
.qr-num.ok { color: #7aab8c; }
.qr-num.warn { color: #c9a85b; }
.qr-label { font-size: 12px; color: #6b7280; }
.qr-list {
  max-height: 320px; overflow-y: auto;
  display: flex; flex-direction: column; gap: 10px;
  margin-bottom: 18px;
}
.qr-item {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
}
.qr-item-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px;
}
.qr-qq { font-size: 16px; font-weight: 700; color: #111827; }
.qr-badge {
  padding: 3px 10px; border-radius: 20px;
  font-size: 12px; font-weight: 600;
}
.qr-badge.active { background: #dcfce7; color: #7aab8c; }
.qr-badge.expired { background: #fee2e2; color: #c48b8b; }
.qr-badge.revoked { background: #f3f4f6; color: #6b7280; }
.qr-item-body { display: flex; flex-direction: column; gap: 6px; }
.qr-row {
  display: flex; justify-content: space-between; gap: 10px;
  font-size: 13px;
}
.qr-row span:first-child { color: #6b7280; }
.qr-row span:last-child { color: #111827; word-break: break-all; text-align: right; }
.qr-row code { color: #4a6fa3; background: #eef2ff; padding: 2px 6px; border-radius: 4px; }

/* ================================================================
   17.6 卡密查询结果弹窗
   ================================================================ */
.card-result-modal { max-width: 460px; }
.cr-status {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-bottom: 18px;
  padding: 10px 16px; border-radius: 10px;
  background: #f3f4f6; color: #374151;
  font-size: 14px; font-weight: 600;
}
.cr-status.unused { background: #dbeafe; color: #1d4ed8; }
.cr-status.active { background: #dcfce7; color: #7aab8c; }
.cr-status.expired { background: #fee2e2; color: #c48b8b; }
.cr-status.revoked { background: #f3f4f6; color: #6b7280; }
.cr-status-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: currentColor;
}
.cr-body {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  margin-bottom: 18px;
}
.cr-row {
  display: flex; justify-content: space-between; gap: 12px;
  padding: 8px 0;
  font-size: 14px;
  border-bottom: 1px solid #e5e7eb;
}
.cr-row:last-child { border-bottom: none; }
.cr-row span:first-child { color: #6b7280; flex-shrink: 0; }
.cr-row span:last-child { color: #111827; word-break: break-all; text-align: right; }
.cr-row code { color: #4a6fa3; background: #eef2ff; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
.cr-divider { height: 1px; background: #d1d5db; margin: 10px 0; }

/* ================================================================
   17.7 在线授权成功/失败弹窗
   ================================================================ */
.activate-success-modal, .activate-error-modal { max-width: 420px; text-align: center; }
.act-success-anim, .act-error-anim {
  display: flex; justify-content: center;
  margin-bottom: 16px;
}
.act-success-ring, .act-error-ring {
  width: 90px; height: 90px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  animation: act-pop-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.act-success-ring { background: #dcfce7; color: #7aab8c; }
.act-error-ring { background: #fee2e2; color: #c48b8b; }
.act-check {
  stroke-dasharray: 30; stroke-dashoffset: 30;
  animation: act-draw 0.5s ease 0.25s forwards;
}
.act-cross {
  transform: scale(0);
  animation: act-cross-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s forwards;
}
.act-success-title, .act-error-title {
  font-size: 20px; font-weight: 700;
  margin-bottom: 6px;
}
.act-success-title { color: #7aab8c; }
.act-error-title { color: #c48b8b; }
.act-success-sub, .act-error-sub {
  font-size: 13px; color: #6b7280;
  margin-bottom: 18px;
}
.act-success-body, .act-error-body {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 18px;
  text-align: left;
}
.act-error-body {
  text-align: center;
  color: #c48b8b;
  font-size: 14px;
}
.act-row {
  display: flex; justify-content: space-between; gap: 12px;
  padding: 8px 0;
  font-size: 14px;
  border-bottom: 1px solid #e5e7eb;
}
.act-row:last-child { border-bottom: none; }
.act-row span { color: #6b7280; flex-shrink: 0; }
.act-row strong { color: #111827; word-break: break-all; text-align: right; font-weight: 600; }
.act-row .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

@keyframes act-pop-in {
  0% { transform: scale(0); opacity: 0; }
  70% { transform: scale(1.15); }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes act-draw {
  to { stroke-dashoffset: 0; }
}
@keyframes act-cross-pop {
  to { transform: scale(1); }
}

/* ================================================================
   18. 弹窗过渡
   ================================================================ */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-active .modal-box { transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease; }
.modal-leave-active .modal-box { transition: transform 0.2s ease, opacity 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box { transform: scale(0.9) translateY(30px); opacity: 0; }
.modal-leave-to .modal-box { transform: scale(0.96) translateY(12px); opacity: 0; }

/* ================================================================
   19. 底部
   ================================================================ */
.shop-footer {
  position: relative; z-index: 1;
  text-align: center; padding: 32px 28px;
  border-top: 1px solid var(--border);
}
.shop-footer p { font-size: 13px; color: var(--text-tertiary); margin: 0; }
.footer-sub { font-size: 12px; margin-top: 4px; }

/* ================================================================
   20. Spinner
   ================================================================ */
.spinner {
  display: inline-block; width: 16px; height: 16px;
  border: 2px solid rgba(0,0,0,0.12);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.55s linear infinite;
}
.spinner.sm { width: 14px; height: 14px; border-width: 2px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ================================================================
   21. 响应式
   ================================================================ */
@media (max-width: 768px) {
  .header-bar { padding: 14px 16px; }
  .brand-name { font-size: 16px; }
  .nav-tab { padding: 8px 14px; font-size: 13px; }
  .nav-tab span { display: none; }
  .shop-main { padding: 20px 16px 40px; }
  .hero { padding: 20px; flex-direction: column; gap: 12px; text-align: center; }
  .hero-stat { text-align: center; }
  .product-grid { grid-template-columns: 1fr; }
  .center-card { padding: 32px 20px; }
  .modal-box { padding: 24px 18px; }
  .m-row { flex-direction: column; gap: 6px; }
  .m-label { width: auto; }
  .search-bar { flex-direction: column; }
  .search-bar .form-input { border-radius: var(--r-md) var(--r-md) 0 0; }
  .search-bar .btn-action { border-radius: 0 0 var(--r-md) var(--r-md); width: 100%; }
}

@media (max-width: 480px) {
  .nav-tab { padding: 8px 10px; }
  .product-card { padding: 20px 16px; }
  .price-amount { font-size: 32px; }
  .auth-stats { gap: 8px; }
  .stat-block { min-width: 60px; padding: 12px 8px; }
}
</style>