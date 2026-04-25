<?php
/**
 * 授权控制器 - 已放宽为可选捐赠标识（WordPress.org 合规）
 *
 * WordPress.org 插件目录要求：插件不得要求激活码才能使用核心功能。
 * 本控制器保留端点以保持向后兼容，但不再限制任何功能。
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
        delete_transient('ai_plus_license_verified');

        return $this->success(['saved' => true, 'key' => $this->maskKey($key)]);
    }

    public function handleCheckLicense(): \WP_REST_Response
    {
        $key = get_option('ai_plus_license_key', '');

        return $this->success([
            'has_key' => !empty($key),
            'status'  => 'valid',
            'message' => __('所有功能免费可用。输入 License Key 仅作为对开发者的支持标识。', 'zuo-ai-plus'),
        ]);
    }

    private function maskKey(string $key): string
    {
        if (strlen($key) <= 8) return $key;
        return substr($key, 0, 4) . '****' . substr($key, -4);
    }
}
