<?php
/**
 * SEO控制器 - 处理SEO诊断和优化相关接口
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class SeoController extends BaseController
{
    private \ZuoAIPlus\Models\SeoOptimizer $seo;

    public function __construct()
    {
        $this->seo = new \ZuoAIPlus\Models\SeoOptimizer();
    }

    public function registerRoutes(): void
    {
        // 基础SEO接口
        register_rest_route('ai-plus/v1', '/seo', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSeo'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // 全站扫描
        register_rest_route('ai-plus/v1', '/seo-audit', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleAuditAll'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // SEO统计
        register_rest_route('ai-plus/v1', '/seo-stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleStats'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // 批量优化（高危操作：消耗 AI 配额，仅限管理员）
        register_rest_route('ai-plus/v1', '/seo-optimize-batch', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleBatchOptimize'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        // 单篇文章诊断
        register_rest_route('ai-plus/v1', '/seo-audit-post/(?P<post_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleAuditPost'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // 单篇文章优化
        register_rest_route('ai-plus/v1', '/seo-optimize-post/(?P<post_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleOptimizePost'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // SEO调试
        register_rest_route('ai-plus/v1', '/seo-debug/(?P<post_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleDebug'],
            'permission_callback' => [$this, 'canEdit'],
        ]);

        // 重置优化状态
        register_rest_route('ai-plus/v1', '/seo-reset/(?P<post_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleReset'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function handleSeo(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制：SEO分析每分钟10次
        if ($err = $this->checkRateLimit('seo_analysis', 10, 60)) {
            return $err;
        }

        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content   = sanitize_textarea_field($request->get_param('content'));
        $title     = sanitize_text_field($request->get_param('title') ?: '');

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error('未配置该模型或 API Key');
        }

        try {
            $prompt = "请对以下文章内容进行SEO分析和优化建议，输出JSON格式：\n"
                . "标题：{$title}\n内容：{$content}\n"
                . "请返回：{title建议:'', meta_description建议:'', 关键词建议:'', H标签结构建议:'', 内链建议:'', 内容改进建议:''}";
            $result = $model->completion($prompt, ['max_tokens' => 1500, 'temperature' => 0.3]);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function handleAuditAll(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->seo->auditAll([
            'posts_per_page' => (int) $request->get_param('per_page') ?: 100,
            'paged'          => (int) $request->get_param('page') ?: 1,
            'skip_done'      => $request->get_param('skip_done') !== 'false',
        ]);
        return $this->success($result);
    }

    public function handleStats(): \WP_REST_Response
    {
        return $this->success($this->seo->getStats());
    }

    public function handleBatchOptimize(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制：批量优化每小时3次
        if ($err = $this->checkRateLimit('seo_batch_optimize', 3, 3600)) {
            return $err;
        }

        $ids = $request->get_param('post_ids');
        if (empty($ids)) {
            return $this->error(__('缺少 post_ids 参数', 'zuo-ai-plus'));
        }

        $ids    = array_map('intval', (array) $ids);
        $model  = $request->get_param('model') ?: '';
        $result = $this->seo->batchOptimize($ids, $model);

        return $this->success($result);
    }

    public function handleAuditPost(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('post_id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'post') {
            return $this->error(__('文章不存在', 'zuo-ai-plus'), 404);
        }

        return $this->success($this->seo->auditPost($post));
    }

    public function handleOptimizePost(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制：单篇文章优化每分钟5次
        if ($err = $this->checkRateLimit('seo_optimize_post', 5, 60)) {
            return $err;
        }

        $id    = (int) $request->get_param('post_id');
        $model = $request->get_param('model') ?: '';
        $result = $this->seo->optimizePost($id, $model);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success($result);
    }

    public function handleDebug(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('post_id');
        $post = get_post($id);

        if (!$post) {
            return $this->error(__('文章不存在', 'zuo-ai-plus'), 404);
        }

        $modelName = $this->getDefaultModel();
        $model     = $this->getModel($modelName);

        if (!$model) {
            return $this->error(__('AI 模型未配置', 'zuo-ai-plus'));
        }

        $prompt   = $this->buildSeoPrompt($post);
        $aiResult = $model->completion($prompt, ['max_tokens' => 1500, 'temperature' => 0.3]);
        $rawText  = \ZuoAIPlus\Models\Model_Init::extractContent($aiResult);
        $parsed   = $this->seo->parseAiResponse($rawText, true, true, true);

        return $this->success([
            'post_id' => $id,
            'model'   => $modelName,
            'prompt'  => $prompt,
            'raw'     => $rawText,
            'parsed'  => $parsed,
            'updates' => [
                'title'       => $parsed['title'] ?? '',
                'tags'        => $parsed['tags'] ?? [],
                'description' => $parsed['description'] ?? '',
            ],
        ]);
    }

    public function handleReset(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('post_id');
        $this->seo->resetPost($id);
        return $this->success(['ok' => true, 'post_id' => $id]);
    }

    private function buildSeoPrompt(\WP_Post $post): string
    {
        $content = wp_strip_all_tags($post->post_content);
        $tags    = wp_get_post_tags($post->ID, ['fields' => 'names']);
        $cats    = wp_get_post_terms($post->ID, 'category', ['fields' => 'names']);
        $excerpt = $post->post_excerpt;

        $parts = [
            "你是一位资深中文博客 SEO 专家。请根据以下文章信息生成优化方案。",
            "文章标题：{$post->post_title}",
            "现有分类：" . implode('、', $cats),
            "现有标签：" . implode('、', $tags),
            "文章摘要：{$excerpt}",
            "正文前200字：" . mb_substr($content, 0, 200, 'utf-8'),
        ];

        return implode("\n", $parts) . "\n\n【优化要求】\n"
            . "新标题：30-60字，包含核心关键词，SEO 友好，直接返回新标题不要解释\n"
            . "新标签：3-5个，每个2-6个中文字，简洁词组，不要完整句子，用逗号分隔，不要编号和解释\n"
            . "SEO描述：80-120字，涵盖文章主题+价值+关键词，吸引用户点击，直接输出一段话\n\n"
            . "直接输出结果，不要任何解释说明。";
    }
}
