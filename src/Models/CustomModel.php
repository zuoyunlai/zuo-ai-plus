<?php
/**
 * 自定义模型（兼容 OpenAI 格式的代理）
 */
namespace ZuoAIPlus\Models;

class CustomModel extends BaseModel
{
    protected string $name = '自定义';

    public function __construct(string $apiKey, string $modelId = '', string $baseUrl = '')
    {
        $this->apiKey  = $apiKey;
        $this->modelId = $modelId ?: get_option('ai_plus_custom_model', '');
        
        // 验证URL格式
        if (!empty($baseUrl)) {
            if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException(__('无效的API地址格式', 'zuo-ai-plus'));
            }
            
            // 解析URL组件
            $parsed = parse_url($baseUrl);
            $host = $parsed['host'] ?? '';
            $scheme = $parsed['scheme'] ?? '';
            
            // 必须使用HTTPS
            if ($scheme !== 'https') {
                throw new \InvalidArgumentException(__('API地址必须使用HTTPS协议', 'zuo-ai-plus'));
            }
            
            // 禁止内网地址（防止SSRF）
            if ($this->isInternalHost($host)) {
                throw new \InvalidArgumentException(__('不允许使用内网地址或本地地址', 'zuo-ai-plus'));
            }
        }
        
        // baseUrl 传入时直接使用（无需拼接待用）
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->endpoint = $baseUrl;
    }
    
    /**
     * 检查是否为内网地址
     */
    private function isInternalHost(string $host): bool
    {
        // 检查是否为IP地址
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // 检查是否为私有IP
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }
        
        // 检查主机名
        $internalPatterns = [
            '/^localhost$/i',
            '/^127\./',
            '/^10\./',
            '/^192\.168\./',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./',
            '/\.local$/i',
            '/\.internal$/i',
        ];
        
        foreach ($internalPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }
        
        return false;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $response = $this->request('POST', '/chat/completions', [
            'model'    => $this->modelId,
            'messages' => $messages,
        ], [], false, $opts);

        // 兼容 OpenAI 格式：choices[0].message.content
        $content = $response['choices'][0]['message']['content'] ?? '';
        // 部分代理（如 SiliconFlow）使用 data[0].text
        if (!$content) {
            $content = $response['data'][0]['text'] ?? '';
        }

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response['usage']['total_tokens'] ?? 0,
            ],
            'raw' => $response,
        ];
    }

    public function completion(string $prompt, array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $opts);
    }

    /**
     * 将任意尺寸归一化为最接近的标准尺寸
     * OpenAI 格式支持: 1024x1024 / 1792x1024 / 1024x1792
     */
    private function normalizeSizeToStd(string $size): string
    {
        $dim = strtolower(str_replace(['x', '*', '×'], 'x', $size));
        $parts = explode('x', $dim);
        if (count($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            [$w, $h] = [intval($parts[0]), intval($parts[1])];
            $ratio = $w / max($h, 1);
            // 16:9 ≈ 1.78
            if (abs($ratio - 16/9) < 0.1) return $w > $h ? '1792x1024' : '1024x1792';
            // 1:1
            if (abs($ratio - 1.0) < 0.1) return '1024x1024';
            // 4:3 ≈ 1.33
            if (abs($ratio - 4/3) < 0.1) return $w > $h ? '1024x768' : '768x1024';
            // 3:2 ≈ 1.5
            if (abs($ratio - 3/2) < 0.1) return $w > $h ? '1024x683' : '683x1024';
        }
        // 直接映射已知值
        return match ($dim) {
            '1024x1024', '1:1', 'square' => '1024x1024',
            '1792x1024', '16:9' => '1792x1024',
            '1024x1792', '9:16' => '1024x1792',
            default => '1024x1024',
        };
    }

    /**
     * 文生图：兼容 OpenAI 格式的 /v1/images/generations 端点
     * 图片模型名通过 $opts['model'] 传入（后台配置）
     */
    public function image(string $prompt, array $opts = []): array
    {
        $imgModel = $opts['model'] ?? $this->modelId;
        $rawSize  = $opts['size'] ?? '1024x1024';
        $normalized = $this->normalizeSizeToStd($rawSize);
        $body = [
            'model' => $imgModel,
            'prompt' => mb_substr($prompt, 0, 4000),
            'n' => 1,
            'size' => $normalized,
        ];

        $response = $this->request('POST', '/images/generations', $body, [], false, $opts);

        // OpenAI 格式: data[0].url
        $url = $response['data'][0]['url'] ?? '';
        if (!$url) {
            // 部分代理返回 b64_json
            $url = $response['data'][0]['b64_json'] ?? '';
            if ($url) {
                return ['url' => 'data:image/png;base64,' . $url, 'revised_prompt' => $prompt];
            }
        }

        return ['url' => $url, 'revised_prompt' => $response['data'][0]['revised_prompt'] ?? $prompt];
    }

    public function countTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 2);
    }
}
