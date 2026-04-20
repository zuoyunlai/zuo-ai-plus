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
    protected string $chatPath = '/chat/completions';

    public function __construct(string $apiKey, string $modelId = '', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId ?: get_option('ai_plus_zhipu_model', 'glm-4-flash');
        $this->baseUrl = $baseUrl;
    }

    /**
     * 将任意尺寸归一化为 cogview 支持的标准尺寸
     */
    private function normalizeSizeToStd(string $size): string
    {
        $parts = preg_split('/[^0-9]+/', $size);
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        $w = intval($parts[0] ?? 1024);
        $h = intval($parts[1] ?? 1024);
        // cogview-3 只支持正方形
        return '1024x1024';
    }

    public function image(string $prompt, array $opts = []): array
    {
        $rawSize = $opts['size'] ?? '1024x1024';
        $normalized = $this->normalizeSizeToStd($rawSize);
        $optimizedPrompt = $this->optimizePromptForQuality($prompt);
        $modelId = $opts['model'] ?? 'cogview-3-plus';

        $body = [
            'model'  => $modelId,
            'prompt' => mb_substr($optimizedPrompt, 0, 1000),
            'size'   => $normalized,
        ];

        if ($modelId === 'cogview-3-plus') {
            $body['quality'] = 'high';
            if (!empty($opts['style'])) {
                $body['style'] = $opts['style'];
            }
        }

        $response = $this->request('POST', "{$this->endpoint}/images/generations", $body, [], false, $opts);

        return [
            'url'            => $response['data'][0]['url'] ?? '',
            'revised_prompt' => $response['data'][0]['revised_prompt'] ?? $prompt,
        ];
    }
}
