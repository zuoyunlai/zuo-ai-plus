<?php
/**
 * 智谱 GLM 模型
 * 文档: https://open.bigmodel.cn/dev/api
 */
namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class ZhipuModel extends BaseModel
{
    protected string $name = '智谱 GLM';
    protected string $endpoint = 'https://open.bigmodel.cn/api/paas/v4';
    protected string $chatPath = '/chat/completions';

    /**
     * 智谱模型名别名映射（产品名 → API 模型 ID）
     * 智谱后台显示的产品名可能是大写如 "GLM-4.7-Flash"，
     * 但 API 要求小写的模型 ID 如 "glm-4-flash"
     */
    private static array $aliasMap = [
        'glm-4.7-flash'  => 'glm-4-flash',
        'glm-4.7'        => 'glm-4',
        'glm-4-air'      => 'glm-4-air',
        'glm-4-airx'     => 'glm-4-airx',
        'glm-4-long'     => 'glm-4-long',
        'glm-4v'         => 'glm-4v',
        'glm-4v-plus'    => 'glm-4v-plus',
        'glm-4-flashx'   => 'glm-4-flashx',
        'cogview-3-flash' => 'cogview-3-flash',
        'cogview-3-plus'  => 'cogview-3-plus',
        'cogview-3'       => 'cogview-3',
    ];

    /**
     * 将用户输入的模型名规范化为 API 识别的模型 ID
     */
    public static function normalizeModelId(string $modelId): string
    {
        $lower = strtolower(trim($modelId));
        return self::$aliasMap[$lower] ?? $lower;
    }

    public function __construct(string $apiKey, string $modelId = '', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->modelId = self::normalizeModelId($modelId ?: get_option('ai_plus_zhipu_model', 'glm-4-flash'));
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
        $modelId = self::normalizeModelId($opts['model'] ?? 'cogview-3-plus');

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
