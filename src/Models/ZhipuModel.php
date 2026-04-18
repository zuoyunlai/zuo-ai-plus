<?php
/**
 * 智谱 GLM 模型
 * 文档: https://open.bigmodel.cn/dev/api
 */
namespace ZuoAIPlus\Models;

class ZhipuModel extends BaseModel
{
    protected string $name = '智谱 GLM';
    protected string $endpoint = 'https://open.bigmodel.cn/api/paas/v4';

    public function __construct(string $apiKey, string $modelId = '', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: get_option('ai_plus_zhipu_model', 'glm-4-flash');
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $body = [
            'model' => $this->modelId,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens' => $opts['max_tokens'] ?? 2048,
        ];

        $response = $this->request('POST', "{$this->endpoint}/chat/completions", $body, [], false, $opts);

        // 推理模型（glm-5 等）：内容在 reasoning_content
        $content = $response['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            $content = $response['choices'][0]['message']['reasoning_content'] ?? '';
        }

        return [
            'content' => $content,
            'usage' => $response['usage'] ?? [],
            'raw' => $response,
        ];
    }

    public function completion(string $prompt, array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $opts);
    }


    /**
     * 将任意尺寸归一化为最接近的标准尺寸
     * cogview-3 只支持: 512x512 / 1024x1024 / 2048x2048
     */
    private function normalizeSizeToStd(string $size): string
    {
        $parts = preg_split('/[^0-9]+/', $size);
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        $w = intval($parts[0] ?? 1024);
        $h = intval($parts[1] ?? 1024);
        $ratio = $w / max($h, 1);
        // cogview-3 只有三种尺寸：正方形比例用 1024x1024
        // 非正方形比例：优先返回 OpenAI 兼容字符串格式（API 会自动处理）
        if (abs($ratio - 1.0) < 0.25) return '1024x1024';
        if ($ratio > 1.5 || $ratio < 0.67) return '1024x1024'; // 竖横太极端用正方
        return '1024x1024'; // cogview-3 不支持非正方，安全默认
    }

    public function image(string $prompt, array $opts = []): array
    {
        // 智画 cogview-3-plus 支持更高质量和更多尺寸
        $rawSize = $opts['size'] ?? '1024x1024';
        $normalized = $this->normalizeSizeToStd($rawSize);

        // 优化提示词以提升质量
        $optimizedPrompt = $this->optimizePromptForQuality($prompt);

        // 优先使用 cogview-3-plus（质量更好），否则回退到 cogview-3
        $modelId = $opts['model'] ?? 'cogview-3-plus';

        $body = [
            'model' => $modelId,
            'prompt' => mb_substr($optimizedPrompt, 0, 1000),
            'size' => $normalized,
        ];

        // cogview-3-plus 支持额外参数
        if ($modelId === 'cogview-3-plus') {
            $body['quality'] = 'high';
            // 添加风格控制（如果指定）
            if (!empty($opts['style'])) {
                $body['style'] = $opts['style'];
            }
        }

        $response = $this->request('POST', "{$this->endpoint}/images/generations", $body, [], false, $opts);

        return [
            'url' => $response['data'][0]['url'] ?? '',
            'revised_prompt' => $response['data'][0]['revised_prompt'] ?? $prompt,
        ];
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
