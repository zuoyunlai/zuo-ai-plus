<?php
/**
 * MiniMax 模型
 * 文档: https://www.minimaxi.com/document
 */
namespace ZuoAIPlus\Models;

class MiniMaxModel extends BaseModel
{
    protected string $name = 'MiniMax';
    protected string $endpoint = 'https://api.minimax.chat/v1';

    public function __construct(string $apiKey, string $modelId = 'MiniMax-M2.7', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: 'MiniMax-M2.7';
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

        $response = $this->request('POST', "{$this->endpoint}/text/chatcompletion_v2", $body);

        // 优先取 content
        $text = $response['content'] ?? '';
        if (!$text) {
            // 标准 chat/completions 格式
            $text = $response['choices'][0]['message']['content'] ?? '';
        }
        if (!$text) {
            // 推理模型（MiniMax-M2.7 等）：内容在 reasoning_content
            $text = $response['choices'][0]['message']['reasoning_content'] ?? '';
        }

        return [
            'content' => $text,
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
        $body = [
            'model' => $opts['model'] ?? 'image-01',
            'prompt' => $prompt,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 120,
        ];

        $response = wp_remote_request("{$this->endpoint}/text2imageimagegeneration", $args);
        $body_resp = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'url' => $body_resp['data'][0]['url'] ?? '',
            'revised_prompt' => $prompt,
        ];
    }

    public function countTokens(string $text): int
    {
        return (int) (mb_strlen($text) / 2);
    }
}
