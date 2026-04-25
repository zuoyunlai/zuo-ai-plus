<?php
/**
 * Kimi（月之暗面）模型
 * 使用 BaseModel 默认的 OpenAI 兼容 chat/completion 实现
 * 文档: https://platform.moonshot.cn/docs
 */
namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class KimiModel extends BaseModel
{
    protected string $name = 'Kimi';
    protected string $endpoint = 'https://api.moonshot.cn/v1';
    protected string $chatPath = '/chat/completions';

    public function __construct(string $apiKey, string $modelId = 'moonshot-v1-8k', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: 'moonshot-v1-8k';
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $opts = []): array
    {
        $body = [
            'model'       => $this->modelId,
            'messages'    => $messages,
            'temperature' => $opts['temperature'] ?? 1.0,
            'max_tokens'  => $opts['max_tokens'] ?? 8192,
        ];

        // 尝试关闭思考过程（Kimi k2.5 支持）
        if (($opts['thinking'] ?? true) === false) {
            $body['thinking'] = ['type' => 'off'];
        }

        $response = $this->request('POST', "{$this->endpoint}/chat/completions", $body, [], false, $opts);

        // 标准 content
        $content = $response['choices'][0]['message']['content'] ?? '';
        // 备用：推理模型内容在 reasoning_content
        if ($content === '') {
            $content = $response['choices'][0]['message']['reasoning_content'] ?? '';
        }

        // 清理推理模型的思考过程标签
        $content = preg_replace('/<think[\\s\\S]*?<\\/think>/iu', '', $content);
        $content = preg_replace('/\\{\\{thinking\\}\\}[\\s\\S]*?\\{\\{\\/thinking\\}\\}/iu', '', $content);
        $content = preg_replace('/<reasoning[\\s\\S]*?<\\/reasoning>/iu', '', $content);
        $content = trim($content);

        return [
            'content' => $content,
            'usage'   => $response['usage'] ?? [],
            'raw'     => $response,
        ];
    }

    public function image(string $prompt, array $opts = []): array
    {
        return [
            'url'            => '',
            'revised_prompt' => $prompt,
            'note'           => 'Kimi暂不支持图像生成，建议使用智谱或通义',
        ];
    }
}
