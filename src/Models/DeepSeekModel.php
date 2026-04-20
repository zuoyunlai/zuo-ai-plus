<?php
/**
 * DeepSeek 模型
 * 使用 BaseModel 默认的 OpenAI 兼容 chat/completion 实现
 */
namespace ZuoAIPlus\Models;

class DeepSeekModel extends BaseModel
{
    protected string $name = 'DeepSeek';
    protected string $endpoint = 'https://api.deepseek.com/v1';
    protected string $chatPath = '/chat/completions';

    public function __construct(string $apiKey, string $modelId = 'deepseek-chat', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: 'deepseek-chat';
        $this->baseUrl = $baseUrl;
    }

    public function image(string $prompt, array $opts = []): array
    {
        throw new \Exception('DeepSeek 暂不支持图像生成');
    }
}
