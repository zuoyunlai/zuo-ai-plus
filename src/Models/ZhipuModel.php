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

        $response = $this->request('POST', "{$this->endpoint}/chat/completions", $body);

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

    public function image(string $prompt, array $opts = []): array
    {
        // 智画功能
        $body = [
            'model' => $opts['model'] ?? 'cogview-3',
            'prompt' => $prompt,
            'size' => $opts['size'] ?? '1024x1024',
        ];

        $response = $this->request('POST', "{$this->endpoint}/images/generations", $body);

        return [
            'url' => $response['data'][0]['url'] ?? '',
            'revised_prompt' => $response['data'][0]['revised_prompt'] ?? '',
        ];
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
