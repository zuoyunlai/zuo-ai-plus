<?php
/**
 * 阿里通义千问模型
 * 文档: https://help.aliyun.com/zh/dashscope
 */
namespace ZuoAIPlus\Models;

class TongyiModel extends BaseModel
{
    protected string $name = '阿里通义千问';
    protected string $endpoint = 'https://dashscope.aliyuncs.com/api/v1';

    public function __construct(string $apiKey, string $modelId = 'qwen-turbo', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: 'qwen-turbo';
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $body = [
            'model' => $this->modelId,
            'input' => ['messages' => $messages],
            'parameters' => [
                'temperature' => $opts['temperature'] ?? 0.7,
                'max_tokens' => $opts['max_tokens'] ?? 2048,
            ],
        ];

        $response = $this->request('POST', "{$this->endpoint}/services/aigc/text-generation/generation", $body, [], false, $opts);

        return [
            'content' => $response['output']['text'] ?? '',
            'usage' => $response['usage'] ?? [],
            'raw' => $response,
        ];
    }

    public function completion(string $prompt, array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $opts);
    }


    /**
     * 将用户尺寸归一化为 Tongyi API接受的尺寸字符串（如 1280*720）
     */
    private function normalizeSizeForTongyi(string $size): string
    {
        $dim = strtolower(str_replace(['x', '*', '×', '：', ':'], '*', $size));
        // 处理 W*H 格式
        $parts = explode('*', $dim);
        if (count($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            [$w, $h] = [intval($parts[0]), intval($parts[1])];
            $ratio = round($w / $h, 2);
            // 16:9 ≈ 1.78
            if (abs($ratio - 16/9) < 0.1)  return $w > $h ? '1280*720' : '720*1280';
            if (abs($ratio - 9/16) < 0.1)  return $w > $h ? '720*1280' : '1280*720';
            if (abs($ratio - 1.0) < 0.1)  return '1024*1024';
            if (abs($ratio - 4/3) < 0.1)  return '1344*960';
            if (abs($ratio - 3/4) < 0.1)  return '960*1344';
            // 3:2 ≈ 1.5
            if (abs($ratio - 3/2) < 0.1)  return $w > $h ? '1344*960' : '960*1344';
            if (abs($ratio - 2/3) < 0.1)  return $w > $h ? '960*1344' : '1344*960';
        }
        // 直接映射已知值
        return match ($dim) {
            '1024*1024', '1*1', 'square'  => '1024*1024',
            '1280*720', '1920*1080', '16*9' => '1280*720',
            '720*1280', '1080*1920', '9*16' => '720*1280',
            '1344*960', '3*2'              => '1344*960',
            '960*1344', '2*3'              => '960*1344',
            '1216*832'                   => '1344*960', // 1216/832≈1.461，接近 3:2 → 1344*960
            '832*1216'                  => '960*1344',
            default                         => '1280*720',
        };
    }

    public function image(string $prompt, array $opts = []): array
    {
        // 清理并优化提示词
        $optimizedPrompt = $this->optimizePromptForQuality($prompt);

        // qwen-image-2.0-pro 使用 multimodal-generation 端点（更强prompt遵循能力）
        $response = $this->request('POST', "{$this->endpoint}/services/aigc/multimodal-generation/generation", [
            'model' => $opts['model'] ?? 'qwen-image-2.0-pro',
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [['text' => mb_substr($optimizedPrompt, 0, 1200)]],
                    ]
                ]
            ],
            'parameters' => [
                'size' => $this->normalizeSizeForTongyi($opts['size'] ?? get_option('ai_plus_image_size', '1280*720')),
                'prompt_extend' => true,
                'watermark' => false,
                'style' => $opts['style'] ?? '<auto>',
                'quality' => 'high',
            ],
        ], [], false, $opts);

        // 解析新格式: output.choices[0].message.content[0].image
        $imageUrl = $response['output']['choices'][0]['message']['content'][0]['image'] ?? '';
        if ($imageUrl) {
            return ['url' => $imageUrl, 'revised_prompt' => $prompt];
        }

        // 降级: 尝试旧格式 output.image_url
        $imageUrl = $response['output']['image_url'] ?? '';
        if ($imageUrl) {
            return ['url' => $imageUrl, 'revised_prompt' => $response['output']['revised_prompt'] ?? $prompt];
        }

        // 完整响应记日志（限key防止过长），对外给通用提示
        error_log('AI Plus TongyiModel image error: ' . json_encode(array_keys($response)));
        throw new \Exception('图片生成失败，请检查通义千问 API Key 配额或模型配置');
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
