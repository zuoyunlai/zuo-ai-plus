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
        // baseUrl 传入时直接使用（无需拼接待用）
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->endpoint = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        return $this->request('POST', '/chat/completions', [
            'model'    => $this->modelId,
            'messages' => $messages,
        ]);
    }

    public function completion(string $prompt, array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $opts);
    }

    /**
     * 文生图：兼容 OpenAI 格式的 /v1/images/generations 端点
     * 图片模型名通过 $opts['model'] 传入（后台配置）
     */
    public function image(string $prompt, array $opts = []): array
    {
        $imgModel = $opts['model'] ?? $this->modelId;
        $body = [
            'model' => $imgModel,
            'prompt' => mb_substr($prompt, 0, 4000),
            'n' => 1,
            'size' => $opts['size'] ?? '1024x1024',
        ];

        $response = $this->request('POST', '/images/generations', $body);

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
