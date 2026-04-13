<?php
/**
 * DeepSeek 模型
 */
namespace ZuoAIPlus\Models;

class DeepSeekModel extends BaseModel
{
    protected string $name = 'DeepSeek';
    protected string $endpoint = 'https://api.deepseek.com/v1';

    public function __construct(string $apiKey, string $modelId = 'deepseek-chat', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: 'deepseek-chat';
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $response = $this->request('POST', '/chat/completions', [
            'model'    => $this->modelId,
            'messages' => $messages,
        ]);
        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage'   => $response['usage'] ?? [],
            'raw'     => $response,
        ];
    }

    public function completion(string $prompt, array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $opts);
    }

    public function image(string $prompt, array $opts = []): array
    {
        throw new \Exception('DeepSeek 暂不支持图像生成');
    }

    public function countTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 2);
    }
}
