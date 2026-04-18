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
     *
     * @param array $opts  调用方传入的选项（含 post_id、content_hash 等元数据）
     * @param array $body  调用方实际发给 API 的请求体（用于提取 prompt 等信息）
     */
    protected function getCacheTtl(array $opts, array $body = []): int
    {
        // 优先使用调用方显式指定的 TTL（ContentController 等已根据 action 类型确定 TTL）
        if (isset($opts['cache_ttl'])) {
            return (int) $opts['cache_ttl'];
        }

        // 以下基于 prompt 文本的推断作为后备（仅在其他手段无效时）
        $postId = $opts['post_id'] ?? null;

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

        // SEO 优化请求缓存更长时间（通过 post_id 判断）
        if (!empty($postId)) {
            return 43200; // 12小时
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

    protected function request(string $method, string $url, array $body = [], array $headers = [], bool $skipCache = false, array $opts = []): array
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

        // ── 从 opts 提取缓存元数据（models 通过第六参数传入）─────────────────
        // 这些信息不会出现在发给 API 的 body 中，但用于本地缓存管理
        $postId     = $opts['post_id']     ?? $body['post_id']     ?? null;
        $contentHash = $opts['content_hash'] ?? $body['content_hash'] ?? null;
        // ─────────────────────────────────────────────────────────────────────

        // ── 缓存逻辑（仅缓存成功请求）───────────────────────
        $cacheKey = null;
        if (!$skipCache && $bodyJson && get_option('ai_plus_cache_enabled', true)) {
            $modelInBody = is_array($body) ? ($body['model'] ?? $this->modelId) : $this->modelId;
            $userId = get_current_user_id() ?: 0;

            // 如果有文章ID，使用「可查询」的缓存键结构：post_$postId 前缀在 key 名中（不在哈希里），
            // 这样 flushPostCache() 可以精确删除该文章的缓存而不影响其他文章
            // 注意：userId 不进入 postId/contentHash 的哈希，因为同一篇文章的相同操作结果应共享缓存
            if ($postId) {
                $promptHash = md5($this->name . '|' . $modelInBody . '|' . $bodyJson);
                $cacheKey = 'ai_cache_post_' . $postId . '_' . $promptHash;
            } elseif ($contentHash) {
                $cacheKey = 'ai_cache_' . md5($this->name . '|' . $modelInBody . '|' . 'content_' . $contentHash);
            } else {
                // 通用请求缓存（含 userId，避免同一用户内不同 prompt 的请求互相覆盖）
                $cacheKey = 'ai_cache_' . md5($this->name . '|' . $userId . '|' . $modelInBody . '|' . $url . '|' . $bodyJson);
            }

            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                $this->logDebug("Cache HIT [{$this->name}] key: " . substr($cacheKey, 0, 20) . '...');
                return $cached;
            }
        }
        // ─────────────────────────────────────────────────────

        // 请求重试机制（指数退避策略）
        $maxRetries = 3;
        $baseDelay = 2; // 基础等待时间（秒）
        $maxDelay = 30; // 最大等待时间
        $lastError = null;
        
        // 记录请求开始时间
        $startTime = microtime(true);
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $this->logDebug("API Request [{$this->name}] Attempt {$attempt}/{$maxRetries}: {$url}");
            
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $elapsed = round((microtime(true) - $startTime) * 1000);
                $this->logDebug("API Request Success [{$this->name}] in {$elapsed}ms");
                break; // 请求成功，跳出重试
            }
            
            $lastError = $response->get_error_message();
            // 错误日志始终记录（重要）
            error_log("AI Plus API Request Failed [{$this->name}] Attempt {$attempt}/{$maxRetries}: {$lastError}");
            
            // 判断是否可重试的错误类型
            $isRetryable = $this->isRetryableError($lastError);
            
            // 如果是可重试错误且还有重试次数，使用指数退避等待
            if ($isRetryable && $attempt < $maxRetries) {
                // 指数退避: 2, 4, 8 秒（最多30秒）
                $delay = min($baseDelay * pow(2, $attempt - 1), $maxDelay);
                $jitter = rand(0, 1000) / 1000; // 添加随机抖动
                $waitTime = $delay + $jitter;
                
                $this->logDebug("API Request Retrying [{$this->name}] after {$waitTime}s (exponential backoff)...");
                usleep((int)($waitTime * 1000000));
                continue;
            }
            
            // 不可重试错误或已用完重试次数
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
            $error = $body['error']['message'] ?? ($body['error'] ?? ($body['message'] ?? ''));
            // 完整错误记入日志（仅key名，用于排查），对外显示通用提示
            error_log("AI Plus API Error [{$this->name}] HTTP {$code}: " . json_encode(array_keys($body)) . " | msg: " . mb_substr((string)($body['error']['message'] ?? $body['error'] ?? $body['message'] ?? ''), 0, 200));
            $userMsg = !empty($error) && is_string($error)
                ? ("AI服务返回错误 (" . intval($code) . "): " . esc_html(mb_substr(strip_tags($error), 0, 200)))
                : ("AI服务返回异常 (" . intval($code) . ")，请稍后重试");
            throw new \Exception($userMsg);
        }

        // 缓存成功响应
        // 从请求体（含 prompt）判断 TTL，不用 API 响应体
        if ($cacheKey) {
            $reqBody = !empty($args['body']) ? json_decode($args['body'], true) : [];
            $ttl = $this->getCacheTtl($opts, $reqBody);
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
        if (!get_option('ai_plus_cache_enabled', true)) {
            return; // 缓存已禁用，无需清理
        }
        global $wpdb;
        $prefix = '_transient_ai_cache_';
        $like    = $wpdb->esc_like($prefix) . '%';
        // 分批删除避免大表锁表（每批500条）
        do {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500",
                $like
            ));
        } while ($deleted === 500);
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

    /**
     * 清除特定文章的缓存（使用文章ID）
     */
    public function flushPostCache(int $postId, ?string $modelId = null): void
    {
        global $wpdb;
        // 新缓存 key 结构：ai_cache_post_{$postId}_{hash}
        // post_id 在 key 名中而非哈希里，SQL 可精确匹配
        $postPrefix = 'ai_cache_post_' . $postId . '_';
        $transientLike = '_transient_' . $wpdb->esc_like($postPrefix) . '%';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $transientLike
        ));
        wp_cache_delete('alloptions', 'options');
        $this->logDebug("Cache FLUSH [{$this->name}] post_{$postId}: {$deleted} cache entries deleted");
    }

    protected function isRetryableError(string $error): bool
    {
        $retryablePatterns = [
            'timeout', 'timed out', '超时',
            'connection', '连接',
            'network', '网络',
            'temporarily unavailable', '暂时不可用',
            '429', 'rate limit',
            '500', '502', '503', '504',
            'cURL error 28', 'cURL error 7',
        ];
        
        $errorLower = strtolower($error);
        foreach ($retryablePatterns as $pattern) {
            if (stripos($errorLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
