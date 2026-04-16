<?php
/**
 * 授权控制器 - 处理License相关接口
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class LicenseController extends BaseController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-plus/v1', '/save-license-key', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveLicenseKey'],
            'permission_callback' => function () { return $this->canManage(); },
        ]);

        register_rest_route('ai-plus/v1', '/check-license', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleCheckLicense'],
            'permission_callback' => function () { return $this->canManage(); },
        ]);
    }

    public function handleSaveLicenseKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $key = sanitize_text_field($request->get_param('license_key') ?: '');

        update_option('ai_plus_license_key', $key, true);
        delete_transient('ai_plus_license_status');

        return $this->success(['saved' => true, 'key' => $this->maskKey($key)]);
    }

    public function handleCheckLicense(): \WP_REST_Response
    {
        $key    = get_option('ai_plus_license_key', '');
        $status = $this->verifyLicense($key);

        return $this->success([
            'has_key' => !empty($key),
            'status'  => $status['status'],
            'message' => $status['message'],
        ]);
    }

    private function maskKey(string $key): string
    {
        if (strlen($key) <= 8) return $key;
        return substr($key, 0, 4) . '****' . substr($key, -4);
    }

    private function verifyLicense(string $key): array
    {
        if (empty($key)) {
            return ['status' => 'missing', 'message' => '未配置License Key'];
        }

        $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $server = get_option('ai_plus_license_server_url', 'https://www.yily.top/licenses/api.php');

        $resp = wp_remote_get(
            $server . '?action=verify&key=' . urlencode($key) . '&domain=' . urlencode($domain),
            ['timeout' => 8, 'sslverify' => true]
        );

        if (is_wp_error($resp)) {
            return ['status' => 'network_error', 'message' => '网络异常，无法验证'];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $st   = $body['status'] ?? 'unknown';

        $messages = [
            'valid'            => '授权有效',
            'expired'          => 'License 已过期',
            'domain_mismatch'  => 'License 域名不匹配',
            'invalid'          => 'License 无效或未激活',
            'unknown'          => '未知状态',
        ];

        return [
            'status'  => $st,
            'message' => $messages[$st] ?? $messages['unknown'],
        ];
    }
}
