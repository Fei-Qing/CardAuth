<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 公开接口控制器
 * 包含：购买页、查询页、授权验证、支付回调
 */
class PublicController extends Controller
{
    /**
     * 获取可购买的项目列表
     */
    public function projects(): void
    {
        $db = Database::getInstance();
        $projects = $db->fetchAll(
            "SELECT id, name, description FROM {$db->table('projects')} WHERE status = 1 AND deleted_at IS NULL ORDER BY id DESC"
        );
        $this->success($projects);
    }

    /**
     * 获取项目的卡密类型（套餐）列表
     */
    public function cardTypes(int $projectId): void
    {
        $db = Database::getInstance();
        $types = $db->fetchAll(
            "SELECT id, name, description, duration_days, price, original_price FROM {$db->table('card_types')}
             WHERE project_id = ? AND status = 1 ORDER BY sort ASC",
            [$projectId]
        );
        $this->success($types);
    }

    /**
     * 创建订单并发起支付
     */
    public function createOrder(): void
    {
        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'project_id'   => 'required|integer',
            'card_type_id' => 'required|integer',
            'pay_type'     => 'required',
        ])) {
            $this->error($validator->getFirstError());
        }

        $projectId  = (int) $input['project_id'];
        $cardTypeId = (int) $input['card_type_id'];
        $payType    = $input['pay_type'];
        $contactInfo = $input['contact_info'] ?? '';
        $couponCode   = strtoupper(trim($input['coupon_code'] ?? ''));
        $botQq        = trim($input['bot_qq'] ?? '');
        $contactQq    = trim($input['contact_qq'] ?? '');

        if (!in_array($payType, ['alipay', 'wxpay', 'qqpay'])) {
            $this->error('不支持的支付方式');
        }

        $db = Database::getInstance();

        // 验证项目
        $project = $db->fetch(
            "SELECT id, name FROM {$db->table('projects')} WHERE id = ? AND status = 1 AND deleted_at IS NULL",
            [$projectId]
        );
        if (!$project) {
            $this->error('项目不存在或已下架');
        }

        // 验证卡密类型
        $cardType = $db->fetch(
            "SELECT * FROM {$db->table('card_types')} WHERE id = ? AND project_id = ? AND status = 1",
            [$cardTypeId, $projectId]
        );
        if (!$cardType) {
            $this->error('套餐不存在或已下架');
        }

        if ((float) $cardType['price'] < 0) {
            $this->error('套餐价格配置错误');
        }

        $originalAmount = (float) $cardType['price'];
        $finalAmount = $originalAmount;
        $validCoupon = null;

        // 验证优惠码
        if ($couponCode) {
            $validCoupon = $db->fetch(
                "SELECT * FROM {$db->table('coupons')} WHERE code = ? AND status = 1",
                [$couponCode]
            );
            if (!$validCoupon) {
                $this->error('优惠码不存在或已失效');
            }
            if ($validCoupon['expire_at'] && strtotime($validCoupon['expire_at']) < time()) {
                $this->error('优惠码已过期');
            }
            if ($validCoupon['max_use_count'] > 0 && $validCoupon['used_count'] >= $validCoupon['max_use_count']) {
                $this->error('优惠码已达使用上限');
            }
            if ($validCoupon['min_amount'] > 0 && $originalAmount < $validCoupon['min_amount']) {
                $this->error("订单金额需满¥{$validCoupon['min_amount']}");
            }
            if ($validCoupon['project_ids']) {
                $allowedProjects = array_map('trim', explode(',', $validCoupon['project_ids']));
                if (!in_array((string) $projectId, $allowedProjects)) {
                    $this->error('该优惠码不适用于此项目');
                }
            }
            $discountAmount = round($originalAmount * ($validCoupon['discount_percent'] / 100), 2);
            $finalAmount = max(0.01, round($originalAmount - $discountAmount, 2));
        }

        // 检查机器人QQ是否已有授权记录（续费场景 + 联系人QQ一致性检查）
        $existingAuth = null;
        $contactQqWarn = '';
        if ($botQq && $contactQq) {
            $existingAuth = $db->fetch(
                "SELECT id, bot_qq, contact_qq, project_id, expire_time, status FROM {$db->table('authorizations')} WHERE bot_qq = ? LIMIT 1",
                [$botQq]
            );
            if ($existingAuth) {
                // 联系人QQ与历史记录不一致：覆盖为历史QQ并提示
                if ($existingAuth['contact_qq'] !== $contactQq) {
                    $contactQqWarn = "联系人QQ与历史记录不一致（当前提交: {$contactQq}，历史: {$existingAuth['contact_qq']}），已自动更改为历史联系人QQ。如确需更改联系人QQ请联系项目管理员。";
                    $contactQq = $existingAuth['contact_qq'];
                }
            }
        }

        // 生成订单号
        $orderNo = date('YmdHis') . rand(10000, 99999);

        $expireMinutes = 15;
        $expiredAt = date('Y-m-d H:i:s', strtotime("+{$expireMinutes} minutes"));

        $orderId = $db->insert(
            "INSERT INTO {$db->table('orders')} (order_no, project_id, card_type_id, amount, pay_amount, pay_type, status, contact_info, bot_qq, contact_qq, coupon_code, expired_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())",
            [
                $orderNo,
                $projectId,
                $cardTypeId,
                $originalAmount,
                $finalAmount,
                $payType,
                $contactInfo,
                $botQq,
                $contactQq,
                $couponCode,
                $expiredAt,
            ]
        );

        // 优惠码使用计数+1
        if ($validCoupon) {
            $db->execute(
                "UPDATE {$db->table('coupons')} SET used_count = used_count + 1 WHERE id = ?",
                [$validCoupon['id']]
            );
        }

        // 免费商品直接完成订单并授权
        if ($finalAmount <= 0) {
            $orderController = new OrderController();
            $orderController->processOrderPaid($orderId);
            
            $auth = $db->fetch(
                "SELECT bot_qq, contact_qq, expire_time, project_name 
                 FROM {$db->table('authorizations')} WHERE bot_qq = ? AND contact_qq = ?",
                [$botQq, $contactQq]
            );
            
            $this->success([
                'order_id'        => $orderId,
                'order_no'        => $orderNo,
                'amount'          => $originalAmount,
                'pay_amount'      => $finalAmount,
                'coupon_code'     => $couponCode,
                'bot_qq'          => $auth['bot_qq'] ?? $botQq,
                'contact_qq'      => $auth['contact_qq'] ?? $contactQq,
                'project_name'    => $auth['project_name'] ?? '',
                'expire_time'     => $auth['expire_time'] ?? null,
                'status'          => 'paid',
                'is_renew'        => $existingAuth ? true : false,
                'contact_qq_warn' => $contactQqWarn ?: null,
            ], $contactQqWarn ?: '领取成功');
            return;
        }

        // 构建支付参数
        $payUrl = $this->buildPayUrl($orderNo, $finalAmount, $payType, $project['name'] . ' - ' . $cardType['name']);

        $this->success([
            'order_id'        => $orderId,
            'order_no'        => $orderNo,
            'amount'          => $originalAmount,
            'pay_amount'      => $finalAmount,
            'coupon_code'     => $couponCode,
            'pay_url'         => $payUrl,
            'expired_at'      => $expiredAt,
            'is_renew'        => $existingAuth ? true : false,
            'contact_qq'      => $contactQq,
            'contact_qq_warn' => $contactQqWarn ?: null,
        ], $contactQqWarn ?: '订单创建成功');
    }

    /**
     * 构建支付链接
     */
    private function buildPayUrl(string $orderNo, float $amount, string $payType, string $subject): string
    {
        // 优先从数据库读取支付配置，回退到文件配置
        $apiUrl = $this->getPaymentConfig('payment_api_url', config('app.payment.api_url', ''));
        $appId  = $this->getPaymentConfig('payment_app_id', config('app.payment.app_id', ''));
        $appKey = $this->getPaymentConfig('payment_app_key', config('app.payment.app_key', ''));
        $notifyUrl = config('app.payment.notify_url', '');
        $returnUrl = config('app.payment.return_url', '');

        // 相对URL补全为绝对URL（以当前请求的 scheme+host 为基准）
        $currentOrigin = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://';
        $currentOrigin .= $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        if ($notifyUrl && !parse_url($notifyUrl, PHP_URL_HOST)) {
            $notifyUrl = $currentOrigin . (str_starts_with($notifyUrl, '/') ? '' : '/') . $notifyUrl;
        }
        if ($returnUrl && !parse_url($returnUrl, PHP_URL_HOST)) {
            $returnUrl = $currentOrigin . (str_starts_with($returnUrl, '/') ? '' : '/') . $returnUrl;
        }

        $typeMap = ['alipay' => 'alipay', 'wxpay' => 'wxpay', 'qqpay' => 'qqpay'];

        $params = [
            'pid'        => $appId,
            'type'       => $typeMap[$payType] ?? $payType,
            'out_trade_no' => $orderNo,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name'       => $subject,
            'money'      => $amount,
        ];

        // 签名
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = rtrim($signStr, '&');
        $params['sign'] = md5($signStr . $appKey);
        $params['sign_type'] = 'MD5';

        return $apiUrl . '/submit.php?' . http_build_query($params);
    }

    /**
     * 从数据库获取支付配置
     */
    private function getPaymentConfig(string $key, string $default = ''): string
    {
        try {
            $db = Database::getInstance();
            $value = $db->fetchColumn(
                "SELECT `value` FROM {$db->table('configs')} WHERE `key` = ?",
                [$key]
            );
            return $value !== false ? $value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * 支付异步回调
     */
    public function paymentNotify(): void
    {
        $tradeNo    = $_GET['trade_no'] ?? $_POST['trade_no'] ?? '';
        $outTradeNo = $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '';
        $tradeStatus = $_GET['trade_status'] ?? $_POST['trade_status'] ?? '';
        $sign       = $_GET['sign'] ?? $_POST['sign'] ?? '';

        // 验证签名
        $appKey = $this->getPaymentConfig('payment_app_key', config('app.payment.app_key', ''));
        $params = $_GET + $_POST;
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = rtrim($signStr, '&');
        $calcSign = md5($signStr . $appKey);

        if ($calcSign !== $sign) {
            echo 'sign error';
            exit;
        }

        if ($tradeStatus !== 'TRADE_SUCCESS') {
            echo 'fail';
            exit;
        }

        $db = Database::getInstance();
        $order = $db->fetch(
            "SELECT * FROM {$db->table('orders')} WHERE order_no = ? AND status = 'pending'",
            [$outTradeNo]
        );

        if (!$order) {
            echo 'order not found';
            exit;
        }

        // 记录第三方交易号，随后由 processOrderPaid 完成卡密发放与状态更新
        $db->execute(
            "UPDATE {$db->table('orders')} SET trade_no = ? WHERE id = ?",
            [$tradeNo, $order['id']]
        );

        $orderController = new OrderController();
        $orderController->processOrderPaid($order['id']);

        echo 'success';
        exit;
    }

    /**
     * 支付同步完成（前端 return_url 回调触发）
     * 当异步 notify 不可达时（如本地开发环境），由前端提交 return_url 参数来补单
     */
    public function paymentComplete(): void
    {
        $input = $this->getJsonInput();

        $tradeNo     = $input['trade_no'] ?? '';
        $outTradeNo  = $input['out_trade_no'] ?? '';
        $tradeStatus = $input['trade_status'] ?? '';
        $sign        = $input['sign'] ?? '';
        $signType    = $input['sign_type'] ?? '';
        $money       = $input['money'] ?? '';

        if (!$outTradeNo || !$sign) {
            $this->error('参数不完整');
        }

        // 验证签名
        $appKey = $this->getPaymentConfig('payment_app_key', config('app.payment.app_key', ''));
        $params = $input;
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = rtrim($signStr, '&');
        $calcSign = md5($signStr . $appKey);

        if ($calcSign !== $sign) {
            $this->error('签名验证失败');
        }

        if ($tradeStatus !== 'TRADE_SUCCESS') {
            $this->error('交易未成功');
        }

        $db = Database::getInstance();
        $order = $db->fetch(
            "SELECT * FROM {$db->table('orders')} WHERE order_no = ?",
            [$outTradeNo]
        );

        if (!$order) {
            $this->error('订单不存在');
        }

        // 如果已支付，直接返回订单详情
        if ($order['status'] === 'paid') {
            $auth = $db->fetch(
                "SELECT bot_qq, contact_qq, expire_time, project_name 
                 FROM {$db->table('authorizations')} WHERE bot_qq = ? AND contact_qq = ?",
                [$order['bot_qq'] ?? '', $order['contact_qq'] ?? '']
            );
            $this->success([
                'order_no'     => $order['order_no'],
                'status'       => 'paid',
                'bot_qq'       => $auth['bot_qq'] ?? $order['bot_qq'] ?? '',
                'contact_qq'   => $auth['contact_qq'] ?? $order['contact_qq'] ?? '',
                'project_name' => $auth['project_name'] ?? '',
                'expire_time'  => $auth['expire_time'] ?? null,
                'amount'       => $order['amount'],
                'is_renew'     => ($auth['bot_qq'] ?? false) ? true : false,
            ], '订单已支付');
            return;
        }

        // 检测是否续费
        $isRenew = false;
        $botQq = $order['bot_qq'] ?? '';
        $contactQq = $order['contact_qq'] ?? '';
        if ($botQq && $contactQq) {
            $exist = $db->fetch(
                "SELECT id FROM {$db->table('authorizations')} WHERE bot_qq = ? AND contact_qq = ? LIMIT 1",
                [$botQq, $contactQq]
            );
            $isRenew = (bool) $exist;
        }

        // 记录第三方交易号
        $db->execute(
            "UPDATE {$db->table('orders')} SET trade_no = ? WHERE id = ?",
            [$tradeNo, $order['id']]
        );

        // 完成订单
        $orderController = new OrderController();
        $orderController->processOrderPaid($order['id']);

        // 查询生成的授权
        $auth = $db->fetch(
            "SELECT bot_qq, contact_qq, expire_time, project_name 
             FROM {$db->table('authorizations')} WHERE bot_qq = ? AND contact_qq = ?",
            [$order['bot_qq'] ?? '', $order['contact_qq'] ?? '']
        );

        $this->success([
            'order_no'     => $outTradeNo,
            'status'       => 'paid',
            'bot_qq'       => $auth['bot_qq'] ?? $order['bot_qq'] ?? '',
            'contact_qq'   => $auth['contact_qq'] ?? $order['contact_qq'] ?? '',
            'project_name' => $auth['project_name'] ?? '',
            'expire_time'  => $auth['expire_time'] ?? null,
            'amount'       => $order['amount'],
            'is_renew'     => $isRenew,
        ], '支付确认成功');
    }

    /**
     * 查询卡密状态
     */
    public function queryCard(): void
    {
        $cardKey = $this->getInput('card_key', '');

        if (empty($cardKey)) {
            $this->error('请输入卡密');
        }

        $db = Database::getInstance();
        $card = $db->fetch(
            "SELECT c.card_key, c.type, c.status, c.duration_days, c.bind_info, c.bound_at, c.expire_time, c.created_at,
                    p.name AS project_name
             FROM {$db->table('cards')} c
             LEFT JOIN {$db->table('projects')} p ON c.project_id = p.id
             WHERE c.card_key = ?",
            [$cardKey]
        );

        if (!$card) {
            $this->error('卡密不存在');
        }

        $card['bind_info'] = json_decode($card['bind_info'] ?? '{}', true);

        $statusText = match ($card['status']) {
            'unused' => '未使用',
            'used'   => '已使用',
            'disabled' => '已禁用',
            default  => '未知',
        };

        $card['status_text'] = $statusText;
        $card['is_expired'] = $card['expire_time'] && strtotime($card['expire_time']) < time();

        $this->success($card);
    }

    /**
     * 授权验证接口 (客户端调用)
     * Header: X-Api-Key: {project_api_key}
     * POST: {card_key, machine_id, ip, device_info}
     */
    public function verify(): void
    {
        $projectId = $_SERVER['HTTP_X_PROJECT_ID'] ?? 0;
        $input = $this->getJsonInput();

        $cardKey   = $input['card_key'] ?? '';
        $machineId = $input['machine_id'] ?? '';
        $deviceIp  = $input['ip'] ?? clientIp();
        $deviceInfo = $input['device_info'] ?? '';

        if (empty($cardKey) || empty($machineId)) {
            $this->error('参数不完整', 400);
        }

        $db = Database::getInstance();

        $card = $db->fetch(
            "SELECT * FROM {$db->table('cards')} WHERE card_key = ? AND project_id = ?",
            [$cardKey, $projectId]
        );

        if (!$card) {
            // 尝试查找跨项目卡密（兼容）
            $card = $db->fetch(
                "SELECT * FROM {$db->table('cards')} WHERE card_key = ?",
                [$cardKey]
            );
            if (!$card) {
                $this->success(['valid' => false, 'message' => '卡密不存在'], 'ok');
                return;
            }
        }

        if ($card['status'] === 'disabled') {
            $this->success(['valid' => false, 'message' => '卡密已被禁用'], 'ok');
            return;
        }

        if ($card['status'] === 'unused') {
            // 首次激活：绑定机器指纹
            $bindInfo = [
                'machine_id' => $machineId,
                'ip'         => $deviceIp,
                'device_info' => $deviceInfo,
                'first_bind_at' => date('Y-m-d H:i:s'),
            ];

            $expireTime = null;
            if ($card['duration_days'] > 0) {
                $expireTime = date('Y-m-d H:i:s', strtotime("+{$card['duration_days']} days"));
            }

            $db->execute(
                "UPDATE {$db->table('cards')} SET status = 'used', bind_info = ?, bound_at = NOW(), expire_time = ? WHERE id = ?",
                [json_encode($bindInfo, JSON_UNESCAPED_UNICODE), $expireTime, $card['id']]
            );

            $this->success([
                'valid'       => true,
                'message'     => '激活成功',
                'type'        => $card['type'],
                'duration_days' => $card['duration_days'],
                'expire_time' => $expireTime,
                'is_permanent' => $card['duration_days'] == 0,
            ], 'ok');
            return;
        }

        if ($card['status'] === 'used') {
            // 验证绑定
            $bindInfo = json_decode($card['bind_info'] ?? '{}', true);

            if (($bindInfo['machine_id'] ?? '') !== $machineId) {
                $this->success(['valid' => false, 'message' => '设备不匹配，请使用绑定的设备'], 'ok');
                return;
            }

            // 检查是否过期
            if ($card['expire_time'] && strtotime($card['expire_time']) < time()) {
                $this->success(['valid' => false, 'message' => '授权已过期'], 'ok');
                return;
            }

            $this->success([
                'valid'        => true,
                'message'      => '授权有效',
                'type'         => $card['type'],
                'duration_days' => $card['duration_days'],
                'expire_time'  => $card['expire_time'],
                'bound_at'     => $card['bound_at'],
                'is_permanent' => $card['duration_days'] == 0,
                'remaining_days' => $card['expire_time'] ? max(0, ceil((strtotime($card['expire_time']) - time()) / 86400)) : null,
            ], 'ok');
            return;
        }
    }

    /**
     * 查询订单状态
     */
    public function queryOrder(): void
    {
        $orderNo = $this->getInput('order_no', '');

        if (empty($orderNo)) {
            $this->error('缺少订单号');
        }

        $db = Database::getInstance();
        $order = $db->fetch(
            "SELECT o.id, o.order_no, o.amount, o.status, o.paid_at, o.created_at,
                    c.card_key,
                    p.name AS project_name,
                    ct.name AS card_type_name
             FROM {$db->table('orders')} o
             LEFT JOIN {$db->table('cards')} c ON o.card_id = c.id
             LEFT JOIN {$db->table('projects')} p ON o.project_id = p.id
             LEFT JOIN {$db->table('card_types')} ct ON o.card_type_id = ct.id
             WHERE o.order_no = ?",
            [$orderNo]
        );

        if (!$order) {
            $this->error('订单不存在');
        }

        $this->success($order);
    }
}