<?php
/**
 * Kimi（月之暗面）模型
 * 文档: https://platform.moonshot.cn/docs
 */
namespace AI_Plus\Models;

class KimiModel extends BaseModel
{
    protected string $name = 'Kimi';
    protected string $endpoint = 'https://api.moonshot.cn/v1';

    public function __construct(string $apiKey, string $modelId = 'moonshot-v1-8k', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId;
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $body = [
            'model' => $this->modelId,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 1.0,  // Kimi API 仅支持 temperature=1
            'max_tokens' => $opts['max_tokens'] ?? 8192,
        ];

        $response = $this->request('POST', "{$this->endpoint}/chat/completions", $body);

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
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
        // Kimi 暂不直接提供图像生成，通过文生图提示词方式返回
        return [
            'url' => '',
            'revised_prompt' => $prompt,
            'note' => 'Kimi暂不支持图像生成，建议使用智谱或通义',
        ];
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
