<?php
/**
 * 模型控制器 - 处理模型列表和相关信息
 */
// @phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table ops use $wpdb::prepare() correctly
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class ModelsController extends BaseController
{
    // 支持的模型配置
    private const MODELS_CONFIG = [
        'zhipu'    => ['name' => '智谱 GLM',     'default' => 'glm-4-flashx'],
        'tongyi'   => ['name' => '阿里通义千问', 'default' => 'qwen-turbo'],
        'minimax'  => ['name' => 'MiniMax',      'default' => 'MiniMax-M2.7'],
        'kimi'     => ['name' => 'Kimi',         'default' => 'moonshot-v1-8k'],
        'deepseek' => ['name' => 'DeepSeek',     'default' => 'deepseek-chat'],
        'custom'   => ['name' => '自定义模型',   'default' => ''],
    ];

    // 支持文生图的模型
    private const IMAGE_MODELS = [
        'zhipu'   => 'cogview-3',
        'tongyi'  => 'qwen-image-2.0-pro',
        'custom'  => '',
    ];

    public function registerRoutes(): void
    {
        register_rest_route('ai-plus/v1', '/models', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getModels'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('ai-plus/v1', '/models/config', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getModelsConfig'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 健康检查接口
        register_rest_route('ai-plus/v1', '/health', [
            'methods'             => 'GET',
            'callback'            => [$this, 'healthCheck'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * 获取模型列表（前端展示用）
     */
    public function getModels(): \WP_REST_Response
    {
        $models = [];
        foreach (self::MODELS_CONFIG as $id => $config) {
            $models[] = [
                'id'   => $id,
                'name' => $config['name'],
            ];
        }

        return $this->success($models);
    }

    /**
     * 获取完整模型配置（后台管理用，仅返回基本信息，不泄露 API Key 状态）
     */
    public function getModelsConfig(): \WP_REST_Response
    {
        $apiKeys = (array) get_option('ai_plus_api_keys', []);
        $models  = [];

        foreach (self::MODELS_CONFIG as $id => $config) {
            $cfg          = $apiKeys[$id] ?? [];
            $apiKeyValue  = is_array($cfg) ? ($cfg['api_key'] ?? '') : ($cfg ?? '');
            $models[$id] = [
                'id'          => $id,
                'name'        => $config['name'],
                'default'     => $config['default'],
                // 仅返回是否已配置，不暴露 Key 是否存在
                'configured'  => !empty($apiKeyValue),
            ];
        }

        return $this->success([
            'models'        => $models,
            'image_models'  => self::IMAGE_MODELS,
            'default'       => get_option('ai_plus_default_model', 'minimax'),
            'image_default' => get_option('ai_plus_image_model', 'tongyi'),
        ]);
    }

    /**
     * 获取模型配置（静态方法，供其他类使用）
     */
    public static function getModelConfig(string $modelId): ?array
    {
        return self::MODELS_CONFIG[$modelId] ?? null;
    }

    /**
     * 检查模型是否支持图片生成
     */
    public static function supportsImage(string $modelId): bool
    {
        return isset(self::IMAGE_MODELS[$modelId]);
    }

    /**
     * 获取图片模型ID
     */
    public static function getImageModelId(string $modelId): string
    {
        return self::IMAGE_MODELS[$modelId] ?? '';
    }

    /**
     * 健康检查 - 验证 API 连接状态
     */
    public function healthCheck(): \WP_REST_Response
    {
        $apiKeys = (array) get_option('ai_plus_api_keys', []);
        $results = [];
        $allHealthy = true;

        foreach (self::MODELS_CONFIG as $id => $config) {
            $cfg = $apiKeys[$id] ?? [];
            $apiKey = is_array($cfg) ? ($cfg['api_key'] ?? '') : ($cfg ?? '');
            $hasKey = !empty($apiKey);

            $results[$id] = [
                'name'     => $config['name'],
                'enabled'  => $hasKey,
                'status'   => $hasKey ? 'configured' : 'not_configured',
            ];

            if (!$hasKey) {
                $allHealthy = false;
            }
        }

        // 检查数据库连接
        global $wpdb;
        $dbOk = true;
        try {
            // @phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- health check probe
            $wpdb->get_var('SELECT 1');
        } catch (\Exception $e) {
            $dbOk = false;
            $allHealthy = false;
        }

        $status = [
            'status'    => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => current_time('mysql'),
            'models'    => $results,
            'database'  => $dbOk ? 'ok' : 'error',
            'version'   => AI_PLUS_VERSION,
        ];

        return $this->success($status);
    }
}
