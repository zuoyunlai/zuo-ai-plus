<?php
/**
 * AI 模型基类
 */
namespace AI_Plus\Models;

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

    protected function request(string $method, string $url, array $body = [], array $headers = []): array
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
            'timeout' => 120,
        ];

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('API请求失败: ' . esc_html($response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $error = $body['error']['message'] ?? json_encode($body);
            throw new \Exception("API错误 (" . intval($code) . "): " . esc_html($error));
        }

        return $body;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
