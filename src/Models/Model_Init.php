<?php
/**
 * 模型工厂 - 负责模型实例化和内容提取
 * 路由处理已迁移到 Controllers/ 目录
 */
namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class Model_Init
{
    private static array $instances = [];
    private static array $modelMap = [
        'zhipu'    => ZhipuModel::class,
        'tongyi'   => TongyiModel::class,
        'minimax'  => MiniMaxModel::class,
        'kimi'     => KimiModel::class,
        'deepseek' => DeepSeekModel::class,
        'custom'   => CustomModel::class,
    ];

    /**
     * 获取模型实例（单例模式）
     */
    public static function getModel(string $name): ?BaseModel
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $apiKeys = \get_option('ai_plus_api_keys', []);
        $cfg     = $apiKeys[$name] ?? [];

        // 兼容旧格式（字符串）和新格式（数组）
        if (is_array($cfg)) {
            $apiKey  = $cfg['api_key'] ?? '';
            $baseUrl = $cfg['base_url'] ?? '';
            $modelId = $cfg['model'] ?: '';
        } else {
            $apiKey  = $cfg;
            $baseUrl = '';
            $modelId = '';
        }

        if (empty($apiKey)) {
            return null;
        }

        if (!isset(self::$modelMap[$name])) {
            return null;
        }

        $class = self::$modelMap[$name];
        self::$instances[$name] = new $class($apiKey, $modelId, $baseUrl);

        return self::$instances[$name];
    }

    /**
     * 统一提取 AI 响应内容
     */
    public static function extractContent(array $result): string
    {
        // 检测 API 错误（优先检测）
        if (!empty($result['raw']['base_resp']['status_code']) && $result['raw']['base_resp']['status_code'] !== 0) {
            return '';
        }
        if (!empty($result['error']['code']) || !empty($result['error']['message'])) {
            return '';
        }

        // 标准 content 字段
        if (isset($result['choices'][0]['message']['content'])) {
            $c = trim($result['choices'][0]['message']['content']);
            if ($c !== '') return $c;
        }

        // 推理模型：内容在 reasoning_content
        if (isset($result['choices'][0]['message']['reasoning_content'])) {
            $c = trim($result['choices'][0]['message']['reasoning_content']);
            if ($c !== '') return $c;
        }

        // 直接 content 字段
        if (!empty($result['content'])) {
            return $result['content'];
        }

        // OpenAI text 字段
        if (!empty($result['text'])) {
            return $result['text'];
        }

        // choices 为 null 或空（API 错误另一种表现）
        if (isset($result['choices']) && empty($result['choices'])) {
            return '';
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 清除所有模型实例（用于测试或重新加载配置）
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
}
