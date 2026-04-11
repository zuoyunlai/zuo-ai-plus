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
        $this->modelId = $modelId;
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

        $response = $this->request('POST', "{$this->endpoint}/services/aigc/text-generation/generation", $body);

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

    public function image(string $prompt, array $opts = []): array
    {
        // qwen-image-2.0-pro 使用 multimodal-generation 端点（更强prompt遵循能力）
        $response = $this->request('POST', "{$this->endpoint}/services/aigc/multimodal-generation/generation", [
            'model' => $opts['model'] ?? 'qwen-image-2.0-pro',
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [['text' => mb_substr($prompt, 0, 800)]],
                    ]
                ]
            ],
            'parameters' => [
                'size' => '1216*832',
                'prompt_extend' => true,
                'watermark' => false,
            ],
        ]);

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

        throw new \Exception('图片生成失败: ' . esc_html($response['message'] ?? json_encode($response)));
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
