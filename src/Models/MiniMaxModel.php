<?php
/**
 * MiniMax 模型
 * 文档: https://www.minimaxi.com/document
 *
 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

        $response = $this->request('POST', "{$this->endpoint}/text/chatcompletion_v2", $body, [], false, $opts);

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


    /**
     * 将用户尺寸（如 1216*832）转换为 MiniMax aspect_ratio
     * 1216*832 ≈ 3:2，实际用 3:2
     */
    private function normalizeSizeToAspectRatio(string $size): string
    {
        // 先归一化分隔符
        $dim = strtolower(str_replace(['x', '*', '×'], 'x', $size));
        return match ($dim) {
            '1024x1024', '1:1', 'square'           => '1:1',
            '1280x720', '1920x1080', '16:9'         => '16:9',
            '720x1280', '1080x1920', '9:16'         => '9:16',
            '1280x853', '853x1280', '3:2', '2:3'    => '3:2',
            '1216x832', '832x1216'                  => '3:2', // ≈3:2
            '1440x960', '960x1440'                  => '3:2',
            '1344x960', '960x1344'                  => '3:2',
            default                                    => '16:9', // 安全默认值
        };
    }

    public function image(string $prompt, array $opts = []): array
    {
        $rawSize = $opts['size'] ?? '1280x720';
        $aspect_ratio = $this->normalizeSizeToAspectRatio($rawSize);

        // 优化提示词以提升质量（调用基类方法）
        $optimizedPrompt = $this->optimizePromptForQuality($prompt);

        $body = [
            'model'        => $opts['model'] ?? 'image-01',
            'prompt'       => mb_substr($optimizedPrompt, 0, 1200),
            'aspect_ratio' => $aspect_ratio,
            'prompt_optimizer' => true,
        ];

        // 图片生成结果不应缓存（prompt 相同也会因时间戳等因素不同），直接请求
        $body_resp = $this->request('POST', "{$this->endpoint}/text2image/imagegeneration", $body, [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], true, $opts);

        // 解析图片 URL：优先取 data[0].url，若返回 AIGC 合规元数据（含 AIGC 节点）则取 data[0].AIGC.url
        $data0 = $body_resp['data'][0] ?? [];
        $url   = '';
        $aigc  = $data0['AIGC']['url'] ?? '';  // AIGC 合规节点含真实 URL

        if (!empty($aigc)) {
            // AIGC 节点优先（部分接口将图片 URL 放在这里）
            $url = $aigc;
        } elseif (!empty($data0['url'])) {
            $url = $data0['url'];
        }

        // 如果 URL 仍然为空（API 返回纯 AIGC 合规数据但无图片 URL），记录日志并给通用提示
        if (empty($url)) {
            error_log('AI Plus MiniMaxModel image: no URL in response, AIGC keys: ' . json_encode(array_keys($data0['AIGC'] ?? [])));
            throw new \Exception('图片生成返回无效内容，请检查 MiniMax API 配额或模型配置');
        }

        return [
            'url'            => $url,
            'revised_prompt' => $data0['revised_prompt'] ?? $prompt,
            'aigc_label'     => $data0['AIGC']['Label'] ?? '',
        ];
    }

    public function countTokens(string $text): int
    {
        // 估算值：中英文混合文本按字符数/2估算，不精确（1个汉字≈2token，英文≈0.75token）
        return (int) ceil(mb_strlen($text) / 2);
    }
}
