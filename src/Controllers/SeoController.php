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
        // 注册批量优化 cron 钩子
        \add_action('ai_plus_batch_optimize_cron', [$this, 'handleBatchOptimizeCron']);
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

        // 批量优化进度查询
        register_rest_route('ai-plus/v1', '/seo-batch-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleBatchStatus'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        // 批量优化后台处理端点（前端轮询驱动，需要登录权限）
        register_rest_route('ai-plus/v1', '/seo-batch-process/(?P<job_id>[a-zA-Z0-9_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleBatchProcess'],
            'permission_callback' => [$this, 'canEdit'],
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
        if ($err = $this->checkRateLimit('seo_analysis', \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_ANALYSIS_REQUESTS, \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_ANALYSIS_WINDOW)) {
            return $err;
        }

        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content   = sanitize_textarea_field($request->get_param('content'));
        $title     = sanitize_text_field($request->get_param('title') ?: '');

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或 API Key', 'zuo-ai-plus'));
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
        // 诊断结果也要保存分数，让"诊断全部"后分数能持久化显示
        foreach ($result['posts'] as $post_result) {
            if (!empty($post_result['id']) && isset($post_result['score'])) {
                update_post_meta($post_result['id'], \ZuoAIPlus\Models\SeoOptimizer::META_SCORE, $post_result['score']);
            }
        }
        return $this->success($result);
    }

    public function handleStats(): \WP_REST_Response
    {
        return $this->success($this->seo->getStats());
    }

    public function handleBatchOptimize(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制：批量优化每小时3次
        if ($err = $this->checkRateLimit('seo_batch_optimize', \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_BATCH_REQUESTS, \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_BATCH_WINDOW)) {
            return $err;
        }

        $ids = $request->get_param('post_ids');
        if (empty($ids)) {
            return $this->error(__('缺少 post_ids 参数', 'zuo-ai-plus'));
        }

        $ids   = array_map('intval', (array) $ids);
        $model = $request->get_param('model') ?: '';

        // 生成唯一 job ID
        $jobId = 'batch_' . uniqid() . '_' . time();
        $job   = [
            'id'          => $jobId,
            'status'      => 'running',
            'total'       => count($ids),
            'processed'   => 0,
            'success'     => 0,
            'failed'      => 0,
            'current'     => 0,
            'model'       => $model,
            'post_ids'    => $ids,
            'results'     => [],
            'started_at'  => time(),
            'updated_at'  => time(),
        ];

        set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);

        // 同步处理所有文章（分块执行，每次处理1篇，由前端轮询驱动）
        $this->processBatchChunk($jobId);

        return $this->success([
            'job_id'  => $jobId,
            'status'  => 'running',
            'total'   => $job['total'],
            'message' => '批量优化任务已启动',
        ]);
    }

    public function handleBatchStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $jobId = sanitize_text_field($request->get_param('job_id') ?: '');
        if (empty($jobId)) {
            return $this->error(__('缺少 job_id 参数', 'zuo-ai-plus'));
        }

        $job = get_transient('ai_plus_batch_job_' . $jobId);
        if (!$job) {
            return $this->success([
                'status'    => 'not_found',
                'message'   => '任务不存在或已过期（保留1小时）',
            ]);
        }

        $pct = $job['total'] > 0 ? round($job['processed'] / $job['total'] * 100) : 0;

        // 如果任务还在运行中，自动推进处理下一篇（轮询驱动模式）
        if ($job['status'] === 'running' || $job['status'] === 'pending') {
            $this->processBatchChunk($jobId);
            // 重新读取更新后的状态
            $job = get_transient('ai_plus_batch_job_' . $jobId);
            $pct = $job && $job['total'] > 0 ? round($job['processed'] / $job['total'] * 100) : $pct;
        }

        return $this->success([
            'job_id'   => $jobId,
            'status'   => $job['status'],
            'total'    => $job['total'],
            'processed'=> $job['processed'],
            'success'  => $job['success'],
            'failed'   => $job['failed'],
            'progress' => $pct,
            'current'  => $job['current'],
            'message'  => $job['status'] === 'running'
                ? "处理中（第{$job['processed']}/{$job['total']}篇）..."
                : ($job['status'] === 'done' ? "完成（成功{$job['success']}篇，失败{$job['failed']}篇）" : '等待中'),
        ]);
    }

    public function handleBatchProcess(\WP_REST_Request $request): \WP_REST_Response
    {
        $jobId = sanitize_text_field($request->get_param('job_id'));
        if (empty($jobId)) {
            return $this->error(__('缺少 job_id', 'zuo-ai-plus'));
        }

        $this->processBatchChunk($jobId);

        $job = get_transient('ai_plus_batch_job_' . $jobId);
        return $this->success([
            'job_id'   => $jobId,
            'status'   => $job ? $job['status'] : 'not_found',
            'processed'=> $job ? $job['processed'] : 0,
            'total'    => $job ? $job['total'] : 0,
        ]);
    }

    /**
     * 处理批量优化的一块（1篇文章），由轮询驱动逐步执行
     */
    private function processBatchChunk(string $jobId): void
    {
        $job = get_transient('ai_plus_batch_job_' . $jobId);
        if (!$job || !in_array($job['status'], ['pending', 'running'])) {
            return;
        }

        $model = \ZuoAIPlus\Models\Model_Init::getModel($job['model'] ?: 'minimax');
        if (!$model) {
            $job['status'] = 'error';
            $job['error']  = 'AI 模型未配置';
            set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
            return;
        }

        // 标记为运行中
        $job['status']     = 'running';
        $job['updated_at']  = time();

        $postIds = $job['post_ids'];
        $idx     = $job['current'];

        if ($idx >= count($postIds)) {
            $job['status']     = 'done';
            $job['updated_at'] = time();
            set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
            return;
        }

        // 处理当前文章
        $postId = (int) $postIds[$idx];
        $result = $this->seo->optimizePost($postId, $model);

        $job['processed']++;
        $job['current']++;
        $job['updated_at'] = time();

        if (is_wp_error($result)) {
            $job['failed']++;
            $job['results'][$postId] = ['error' => $result->get_error_message()];
        } else {
            $job['success']++;
            $job['results'][$postId] = $result;
        }

        // 检查是否全部完成
        if ($job['current'] >= count($postIds)) {
            $job['status']     = 'done';
        }

        set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
    }

    // 保留 cron 回调作为备用（当后台 HTTP 触发失败时仍可工作）
    public function handleBatchOptimizeCron(string $jobId): void
    {
        $job = get_transient('ai_plus_batch_job_' . $jobId);
        if (!$job || !in_array($job['status'], ['pending', 'running'])) {
            return;
        }

        $model = \ZuoAIPlus\Models\Model_Init::getModel($job['model'] ?: 'minimax');
        if (!$model) {
            $job['status'] = 'error';
            $job['error']  = 'AI 模型未配置';
            set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
            return;
        }

        $postIds = $job['post_ids'];
        $idx     = $job['current'];

        if ($idx >= count($postIds)) {
            $job['status']    = 'done';
            $job['updated_at'] = time();
            set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
            return;
        }

        // 标记为运行中
        $job['status']     = 'running';
        $job['updated_at']  = time();
        set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);

        $postId = (int) $postIds[$idx];
        $result = $this->seo->optimizePost($postId, $model);

        $job['processed']++;
        $job['current']++;
        $job['updated_at'] = time();

        if (is_wp_error($result)) {
            $job['failed']++;
            $job['results'][$postId] = ['error' => $result->get_error_message()];
        } else {
            $job['success']++;
            $job['results'][$postId] = $result;
        }

        set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);

        // 还有更多文章要处理，继续调度
        if ($job['current'] < count($postIds)) {
            wp_schedule_single_event(time() + 5, 'ai_plus_batch_optimize_cron', [$jobId]);
        } else {
            $job['status']    = 'done';
            $job['updated_at'] = time();
            set_transient('ai_plus_batch_job_' . $jobId, $job, HOUR_IN_SECONDS);
        }
    }

    public function handleAuditPost(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('post_id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'post') {
            return $this->error(__('文章不存在', 'zuo-ai-plus'), 404);
        }

        $result = $this->seo->auditPost($post);
        if (!is_wp_error($result) && isset($result['score'])) {
            update_post_meta($id, \ZuoAIPlus\Models\SeoOptimizer::META_SCORE, $result['score']);
        }
        return $this->success($result);
    }

    public function handleOptimizePost(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制：单篇文章优化每分钟5次
        if ($err = $this->checkRateLimit('seo_optimize_post', \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_POST_REQUESTS, \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SEO_POST_WINDOW)) {
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
