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

    /**
     * 检查是否启用调试日志
     */
    protected function isDebugLoggingEnabled(): bool
    {
        // 优先使用选项设置，其次检查WP_DEBUG
        return (bool) get_option('ai_plus_debug_logging', false) 
            || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * 根据请求内容智能判断缓存TTL
     * 不同操作类型使用不同的缓存时间
     */
    protected function getCacheTtl(array $body): int
    {
        // 从请求体中检测操作类型
        $model = $body['model'] ?? '';
        $messages = $body['messages'] ?? [];
        $prompt = '';
        
        // 提取提示词内容用于判断操作类型
        if (!empty($messages) && is_array($messages)) {
            $lastMessage = end($messages);
            $prompt = $lastMessage['content'] ?? '';
        }
        
        // 根据模型和提示词判断操作类型
        // 图片生成：缓存7天（结果稳定，重复请求多）
        if (strpos($model, 'image') !== false || 
            strpos($model, 'cogview') !== false ||
            strpos($prompt, '图片') !== false) {
            return 604800; // 7天
        }
        
        // Slug/别名生成：不缓存（每篇文章不同）
        if (strpos($prompt, 'slug') !== false || 
            strpos($prompt, '别名') !== false) {
            return 0; // 不缓存
        }
        
        // 关键词提取：缓存1小时
        if (strpos($prompt, '关键词') !== false || 
            strpos($prompt, '标签') !== false) {
            return 3600; // 1小时
        }
        
        // 摘要生成：缓存24小时
        if (strpos($prompt, '摘要') !== false || 
            strpos($prompt, 'summarize') !== false) {
            return 86400; // 24小时
        }
        
        // SEO优化：缓存12小时
        if (strpos($prompt, 'SEO') !== false || 
            strpos($prompt, '优化') !== false) {
            return 43200; // 12小时
        }
        
        // 文章生成：缓存24小时
        if (strpos($prompt, '撰写') !== false || 
            strpos($prompt, '文章') !== false) {
            return 86400; // 24小时
        }
        
        // 默认使用用户设置或1小时
        $defaultTtl = intval(get_option('ai_plus_cache_ttl', 3600));
        return $defaultTtl > 0 ? $defaultTtl : 3600;
    }

    /**
     * 记录调试日志（仅在启用时记录）
     */
    protected function logDebug(string $message): void
    {
        if ($this->isDebugLoggingEnabled()) {
            error_log("AI Plus: {$message}");
        }
    }

    /**
     * 优化图片生成提示词以提升质量
     * 自动添加质量标签（如 masterpiece, best quality 等）
     */
    protected function optimizePromptForQuality(string $prompt): string
    {
        $qualityTags = [
            'masterpiece',
            'best quality',
            'highly detailed',
            'professional lighting',
            'sharp focus',
            '8k resolution',
        ];

        $promptLower = strtolower($prompt);
        $tagsToAdd = [];

        foreach ($qualityTags as $tag) {
            if (strpos($promptLower, strtolower($tag)) === false) {
                $tagsToAdd[] = $tag;
            }
        }

        if (!empty($tagsToAdd)) {
            return $prompt . ', ' . implode(', ', $tagsToAdd);
        }

        return $prompt;
    }

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
            'timeout' => 180,  // 增加到3分钟
            'connect_timeout' => 30,  // 连接超时30秒
        ];

        $bodyJson = '';
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
            $bodyJson = json_encode($body);
        }

        // ── 缓存逻辑（仅缓存成功请求）───────────────────────
        $cacheKey = null;
        if (!$skipCache && $bodyJson && get_option('ai_plus_cache_enabled', true)) {
            // 改进缓存键：加入模型名、用户ID、URL、请求体，避免冲突
            $modelInBody = is_array($body) ? ($body['model'] ?? $this->modelId) : $this->modelId;
            $userId = get_current_user_id() ?: 0;
            $cacheKey = 'ai_cache_' . md5($this->name . '|' . $userId . '|' . $modelInBody . '|' . $url . '|' . $bodyJson);
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                $this->logDebug("Cache HIT [{$this->name}] key: " . substr($cacheKey, 0, 20) . '...');
                return $cached;
            }
        }
        // ─────────────────────────────────────────────────────

        // 请求重试机制（最多2次）
        $maxRetries = 2;
        $retryDelay = 1; // 秒
        $lastError = null;
        
        // 记录请求开始时间
        $startTime = microtime(true);
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $this->logDebug("API Request [{$this->name}] Attempt {$attempt}: {$url}");
            
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $elapsed = round((microtime(true) - $startTime) * 1000);
                $this->logDebug("API Request Success [{$this->name}] in {$elapsed}ms");
                break; // 请求成功，跳出重试
            }
            
            $lastError = $response->get_error_message();
            // 错误日志始终记录（重要）
            error_log("AI Plus API Request Failed [{$this->name}] Attempt {$attempt}: {$lastError}");
            $isTimeout = stripos($lastError, 'timeout') !== false || stripos($lastError, 'timed out') !== false;
            
            // 如果是超时错误且还有重试次数，等待后重试
            if ($isTimeout && $attempt < $maxRetries) {
                $this->logDebug("API Request Retrying [{$this->name}] after {$retryDelay}s...");
                sleep($retryDelay);
                continue;
            }
            
            // 非超时错误或已用完重试次数
            break;
        }

        if (is_wp_error($response)) {
            $errorMsg = $response->get_error_message();
            $elapsed = round((microtime(true) - $startTime) * 1000);
            // 错误日志始终记录
            error_log("AI Plus API Request Failed [{$this->name}] after {$elapsed}ms: {$errorMsg}");
            
            // 友好的错误提示
            if (stripos($errorMsg, 'timeout') !== false || stripos($errorMsg, 'timed out') !== false) {
                throw new \Exception('AI服务响应超时（' . round($elapsed/1000) . '秒），可能是网络问题或服务器繁忙，请稍后重试');
            }
            if (stripos($errorMsg, 'cURL error') !== false) {
                throw new \Exception('网络连接失败: ' . esc_html(substr($errorMsg, 0, 100)) . '，请检查网络连接或API配置');
            }
            throw new \Exception('API请求失败: ' . esc_html($errorMsg));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $error = $body['error']['message'] ?? json_encode($body);
            throw new \Exception("API错误 (" . intval($code) . "): " . esc_html($error));
        }

        // 缓存成功响应
        if ($cacheKey) {
            $ttl = $this->getCacheTtl($body);
            if ($ttl > 0) {
                set_transient($cacheKey, $body, $ttl);
                $this->logDebug("Cache SET [{$this->name}] key: " . substr($cacheKey, 0, 20) . "..., TTL: {$ttl}s");
            }
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);
        $this->logDebug("API Request Success [{$this->name}] in {$elapsed}ms");

        return $body;
    }

    /**
     * 清除当前模型的所有缓存（按 modelId 前缀）
     */
    public function flushCache(): void
    {
        global $wpdb;
        $prefix = '_transient_ai_cache_';
        // 使用 esc_like 防止 SQL 注入（虽然 prefix 来自常量，但仍遵循 WPDB 安全规范）
        $like    = $wpdb->esc_like($prefix) . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
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
