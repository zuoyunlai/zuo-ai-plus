<?php
/**
 * AI 模型基类
 */
namespace ZuoAIPlus\Models;

abstract class BaseModel
{
    protected string $name;
    protected string $apiKey;
    protected string $endpoint;
    protected string $baseUrl;
    protected string $modelId;

    abstract public function chat(array $messages, array $opts = []): array;
    abstract public function completion(string $prompt, array $opts = []): array;
    abstract public function image(string $prompt, array $opts = []): array;
    abstract public function countTokens(string $text): int;

    protected function request(string $method, string $url, array $body = [], array $headers = [], bool $skipCache = false): array
    {
        // 如果传入完整URL（https://开头）直接使用；否则拼接待用 baseUrl 或 endpoint
        if (strpos($url, 'http') !== 0) {
            $url = ($this->baseUrl ?: $this->endpoint) . $url;
        }
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $headers);

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 120,
        ];

        $bodyJson = '';
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
            $bodyJson = json_encode($body);
        }

        // ── 缓存逻辑（仅缓存成功请求）───────────────────────
        $cacheKey = null;
        if (!$skipCache && $bodyJson && get_option('ai_plus_cache_enabled', true)) {
            // 从 body 中提取 model 字段用于缓存区分
            $modelInBody = is_array($body) ? ($body['model'] ?? $this->modelId) : $this->modelId;
            $cacheKey = 'ai_cache_' . md5($modelInBody . '|' . $url . '|' . $bodyJson);
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        // ─────────────────────────────────────────────────────

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('API请求失败: ' . esc_html($response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $error = $body['error']['message'] ?? json_encode($body);
            throw new \Exception("API错误 (" . intval($code) . "): " . esc_html($error));
        }

        // 缓存成功响应
        if ($cacheKey) {
            $ttl = intval(get_option('ai_plus_cache_ttl', 3600));
            if ($ttl > 0) {
                set_transient($cacheKey, $body, $ttl);
            }
        }

        return $body;
    }

    /**
     * 清除当前模型的所有缓存（按 modelId 前缀）
     */
    public function flushCache(): void
    {
        global $wpdb;
        $prefix = '_transient_ai_cache_';
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'");
        wp_cache_delete('alloptions', 'options');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }
}
