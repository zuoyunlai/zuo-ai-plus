<?php
/**
 * Zuo AI Plus - SEO Optimizer
 * 全站文章 SEO 诊断 + AI 优化模块
 */
// @phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table ops use $wpdb::prepare() correctly

namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class SeoOptimizer
{
    // ── 统一 SEO 标准常量 ──
    public const SEO_STANDARDS = [
        'title' => [
            'min_chars' => 30,      // 最少 30 字符
            'max_chars' => 60,      // 最多 60 字符
            'keyword_position' => 'front', // 关键词靠前
            'include_intent_words' => true, // 包含搜索意图词
        ],
        'excerpt' => [
            'min_chars' => 80,      // 最少 80 字符
            'max_chars' => 120,     // 最多 120 字符
            'structure' => 'keyword + value_point + action_guide', // 关键词+价值点+行动引导
        ],
        'tags' => [
            'min_count' => 3,       // 最少 3 个
            'max_count' => 5,       // 最多 5 个
            'preferred_count' => 4, // 推荐 4 个
            'min_chars_per_tag' => 2, // 每个标签最少 2 字符
            'max_chars_per_tag' => 6, // 每个标签最多 6 字符
            'no_single_chars' => true, // 禁止单个汉字
            'no_brand_names' => true,  // 禁止品牌名
            'format' => '完整的中文名词词组',
        ],
        'content' => [
            'min_chars' => 500,     // 最少 500 字
            'recommended_chars' => 800, // 推荐 800 字
            'structure' => 'introduction + 2-4_sections + conclusion',
        ],
    ];

    // 标题标准描述文本
    public static function getTitleStandardDesc(): string
    {
        /* translators: 1: min title length, 2: max title length */
        return sprintf(__('标题长度：%1$d-%2$d个汉字，含核心关键词，包含搜索意图词', 'zuo-ai-plus'),
            self::SEO_STANDARDS['title']['min_chars'],
            self::SEO_STANDARDS['title']['max_chars']);
    }

    // 摘要标准描述文本
    public static function getExcerptStandardDesc(): string
    {
        /* translators: 1: min summary length, 2: max summary length */
        return sprintf(__('摘要长度：%1$d-%2$d个汉字，含关键词+价值点+行动引导', 'zuo-ai-plus'),
            self::SEO_STANDARDS['excerpt']['min_chars'],
            self::SEO_STANDARDS['excerpt']['max_chars']);
    }

    // 标签标准描述文本
    public static function getTagsStandardDesc(): string
    {
        /* translators: 1: min tag count, 2: max tag count, 3: min tag length, 4: max tag length */
        return sprintf(__('标签：%1$d-%2$d个，每个%3$d-%4$d字，SEO友好的完整名词词组', 'zuo-ai-plus'),
            self::SEO_STANDARDS['tags']['min_count'],
            self::SEO_STANDARDS['tags']['max_count'],
            self::SEO_STANDARDS['tags']['min_chars_per_tag'],
            self::SEO_STANDARDS['tags']['max_chars_per_tag']);
    }

    // 内容标准描述文本
    public static function getContentStandardDesc(): string
    {
        /* translators: 1: min content length, 2: recommended content length */
        return sprintf(__('内容字数：%1$d字以上，推荐%2$d字以上', 'zuo-ai-plus'),
            self::SEO_STANDARDS['content']['min_chars'],
            self::SEO_STANDARDS['content']['recommended_chars']);
    }

    // ── Post Meta Keys ──
    const META_OPTIMIZED    = '_seo_optimized';
    const META_OPTIMIZED_AT = '_seo_optimized_at';
    const META_ORIG_TITLE   = '_seo_original_title';
    const META_ORIG_TAGS    = '_seo_original_tags';
    const META_NEW_TITLE    = '_seo_optimized_title';
    const META_NEW_TAGS     = '_seo_optimized_tags';
    const META_SCORE        = '_seo_score';
    const META_ISSUES       = '_seo_issues';

    // ── 全站扫描 ──
    public function auditAll($args = [])
    {
        $posts_per_page = $args['posts_per_page'] ?? 100;
        $paged          = $args['paged'] ?? 1;
        $skip_done      = $args['skip_done'] ?? false;

        $query = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'         => $paged,
            'orderby'       => 'date',
            'order'         => 'DESC',
        ]);

        // 优化：批量预取所有文章的标签和分类，消除 N+1 查询
        // 从 2*N 次查询（每篇2次）降为 2 次批量查询
        $post_ids = array_column($query->posts, 'ID');
        $allTags = [];
        $allCats = [];
        if ($post_ids) {
            $terms = wp_get_object_terms($post_ids, ['post_tag', 'category'], ['fields' => 'all_with_object_id']);
            foreach ($terms as $t) {
                if ($t->taxonomy === 'post_tag') {
                    $allTags[$t->object_id][] = $t->name;
                } else {
                    $allCats[$t->object_id][] = $t->name;
                }
            }
        }

        $results = [];
        foreach ($query->posts as $post) {
            $terms = [
                'tags' => $allTags[$post->ID] ?? [],
                'categories' => $allCats[$post->ID] ?? [],
            ];
            $result = $this->auditPost($post, $terms);
            if ($skip_done && get_post_meta($post->ID, self::META_OPTIMIZED, true)) {
                $result['skipped'] = true;
                $result['skip_reason'] = '已优化过';
            }
            $results[] = $result;
        }

        return [
            'posts'        => $results,
            'total'        => (int) $query->found_posts,
            'total_pages'  => $query->max_num_pages,
            'current_page' => $paged,
            'skipped_count' => $skip_done ? count(array_filter($results, fn($r) => !empty($r['skipped']))) : 0,
        ];
    }

    // ── 诊断单篇文章 ──
    public function auditPost($post, $args = [])
    {
        if (!is_object($post) || !isset($post->ID)) {
            return new \WP_Error('invalid_post', '无效的文章对象');
        }
        $post_id   = (int) $post->ID;
        $title     = $post->post_title;
        $content   = wp_strip_all_tags($post->post_content);
        $excerpt   = $post->post_excerpt ?: mb_substr($content, 0, 150, 'utf-8');
        // 优先使用 auditAll 预取的数据，避免 N+1 查询；无预取时按需查询（单个文章时无影响）
        $tags      = $args['tags'] ?? wp_get_post_tags($post_id, ['fields' => 'names']);
        $categories = $args['categories'] ?? wp_get_post_terms($post_id, 'category', ['fields' => 'names']);

        $title_len = mb_strlen($title, 'utf-8');
        $issues    = [];
        $score     = 100;

        // 统一SEO标准检查：标题
        if ($title_len < self::SEO_STANDARDS['title']['min_chars']) {
            /* translators: %d is the actual title length */
            $issues[] = ['type' => 'title', 'severity' => 'high', 
                /* translators: 1: current length, 2: min standard, 3: max standard */
                'msg' => sprintf(__('标题过短（%1$d字），统一SEO标准%2$d-%3$d字', 'zuo-ai-plus'), 
                    $title_len, 
                    self::SEO_STANDARDS['title']['min_chars'],
                    self::SEO_STANDARDS['title']['max_chars'])];
            $score -= 30;
        } elseif ($title_len > self::SEO_STANDARDS['title']['max_chars']) {
            /* translators: %d is the actual title length */
            $issues[] = ['type' => 'title', 'severity' => 'medium', 
                /* translators: 1: current length, 2: min standard, 3: max standard */
                'msg' => sprintf(__('标题过长（%1$d字），统一SEO标准%2$d-%3$d字', 'zuo-ai-plus'),
                    $title_len,
                    self::SEO_STANDARDS['title']['min_chars'],
                    self::SEO_STANDARDS['title']['max_chars'])];
            $score -= 15;
        }

        // 标签检查
        if (empty($tags)) {
            $issues[] = ['type' => 'tags', 'severity' => 'high', 'msg' => __('没有标签', 'zuo-ai-plus')];
            $score -= 25;
        } else {
            foreach ($tags as $tag) {
                $len = mb_strlen($tag, 'utf-8');
                // 标签长度检查（统一SEO标准）
                if ($len < self::SEO_STANDARDS['tags']['min_chars_per_tag']) {
                    /* translators: %1$s is the tag name, %2$d is the tag length */
                    $issues[] = ['type' => 'tags', 'severity' => 'medium', 
                        /* translators: 1: tag name, 2: current length, 3: min standard, 4: max standard */
                        'msg' => sprintf(__('标签「%1$s」太短（%2$d字），统一SEO标准%3$d-%4$d字（含义完整的词汇）', 'zuo-ai-plus'), 
                            $tag, $len,
                            self::SEO_STANDARDS['tags']['min_chars_per_tag'],
                            self::SEO_STANDARDS['tags']['max_chars_per_tag'])];
                    $score -= 5;
                } elseif ($len > self::SEO_STANDARDS['tags']['max_chars_per_tag']) {
                    /* translators: %1$s is the tag name, %2$d is the tag length */
                    $issues[] = ['type' => 'tags', 'severity' => 'medium', 
                        /* translators: 1: tag name, 2: current length, 3: min standard, 4: max standard */
                        'msg' => sprintf(__('标签「%1$s」过长（%2$d字），统一SEO标准%3$d-%4$d字（含义完整的词汇）', 'zuo-ai-plus'), 
                            $tag, $len,
                            self::SEO_STANDARDS['tags']['min_chars_per_tag'],
                            self::SEO_STANDARDS['tags']['max_chars_per_tag'])];
                    $score -= 5;
                }
            }
            // 标签数量检查（统一SEO标准）
            if (count($tags) > self::SEO_STANDARDS['tags']['max_count']) {
                /* translators: %d is the number of tags */
                $issues[] = ['type' => 'tags', 'severity' => 'low', 
                    /* translators: 1: current count, 2: min standard, 3: max standard */
                    'msg' => sprintf(__('标签过多（%1$d个），统一SEO标准%2$d-%3$d个', 'zuo-ai-plus'), 
                        count($tags),
                        self::SEO_STANDARDS['tags']['min_count'],
                        self::SEO_STANDARDS['tags']['max_count'])];
                $score -= 5;
            }
        }

        // 统一SEO标准检查：摘要
        $excerpt_len = mb_strlen($excerpt, 'utf-8');
        if ($excerpt_len < self::SEO_STANDARDS['excerpt']['min_chars']) {
            $issues[] = ['type' => 'description', 'severity' => 'medium', 
                /* translators: 1: current length, 2: min standard, 3: max standard */
                'msg' => sprintf(__('摘要缺失或太短（%1$d字），统一SEO标准%2$d-%3$d字', 'zuo-ai-plus'),
                    $excerpt_len,
                    self::SEO_STANDARDS['excerpt']['min_chars'],
                    self::SEO_STANDARDS['excerpt']['max_chars'])];
            $score -= 15;
        } elseif ($excerpt_len > self::SEO_STANDARDS['excerpt']['max_chars']) {
            $issues[] = ['type' => 'description', 'severity' => 'medium', 
                /* translators: 1: current length, 2: min standard, 3: max standard */
                'msg' => sprintf(__('摘要过长（%1$d字），统一SEO标准%2$d-%3$d字', 'zuo-ai-plus'),
                    $excerpt_len,
                    self::SEO_STANDARDS['excerpt']['min_chars'],
                    self::SEO_STANDARDS['excerpt']['max_chars'])];
            $score -= 10;
        }

        // 统一SEO标准检查：内容长度
        $content_len = mb_strlen($content, 'utf-8');
        if ($content_len < self::SEO_STANDARDS['content']['min_chars']) {
            $issues[] = ['type' => 'content', 'severity' => 'low', 
                /* translators: 1: current length, 2: min standard, 3: recommended length */
                'msg' => sprintf(__('文章内容偏短（%1$d字），统一SEO标准%2$d字以上，推荐%3$d字', 'zuo-ai-plus'),
                    $content_len,
                    self::SEO_STANDARDS['content']['min_chars'],
                    self::SEO_STANDARDS['content']['recommended_chars'])];
            $score -= 10;
        }

        $score = max(0, $score);

        // 优化状态
        $optimized   = (bool) get_post_meta($post_id, self::META_OPTIMIZED, true);
        $optimized_at = get_post_meta($post_id, self::META_OPTIMIZED_AT, true);

        return [
            'id'            => $post_id,
            'title'         => $title,
            'url'           => get_permalink($post_id),
            'date'          => get_the_date('Y-m-d H:i', $post_id),
            'tags'          => $tags,
            'categories'    => $categories,
            'title_length'  => $title_len,
            'excerpt_length'=> $excerpt_len,
            'content_length'=> mb_strlen($content, 'utf-8'),
            'score'         => $score,
            'issues'        => $issues,
            'optimized'     => $optimized,
            'optimized_at'  => $optimized_at,
            'skipped'       => false,
            'skip_reason'   => '',
        ];
    }

    // ── AI 优化单篇文章 ──
    public function optimizePost($post_id, $model = null)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            return new \WP_Error('invalid_post', '文章不存在或非公开文章');
        }

        // 保存原始值：仅在真正首次优化时存档（orig_tags 为空或不存在）
        // 注意：'[]' 表示原始无标签，也需重新保存判断
        $orig_title_archived = get_post_meta($post_id, self::META_ORIG_TITLE, true);
        $orig_tags_archived  = get_post_meta($post_id, self::META_ORIG_TAGS, true);
        $orig_tags_decoded   = is_string($orig_tags_archived) ? json_decode($orig_tags_archived, true) : null;
        $has_valid_orig_tags = is_array($orig_tags_decoded) && count($orig_tags_decoded) > 0;
        if (empty($orig_title_archived) || !$has_valid_orig_tags) {
            // 首次优化，或原始标签存档无效，重新存档
            $old_tags = wp_get_post_tags($post_id, ['fields' => 'names']);
            update_post_meta($post_id, self::META_ORIG_TITLE, $post->post_title);
            update_post_meta($post_id, self::META_ORIG_TAGS, json_encode($old_tags, JSON_UNESCAPED_UNICODE));
            $orig_tags_archived = json_encode($old_tags, JSON_UNESCAPED_UNICODE); // 更新局部变量
        }

        $title    = $post->post_title;
        $content  = wp_strip_all_tags($post->post_content);
        // 始终使用存档的原始标签，不受重置影响
        $tags_raw = $orig_tags_archived ? json_decode($orig_tags_archived, true) : null;
        $tags     = is_array($tags_raw) ? $tags_raw : wp_get_post_tags($post_id, ['fields' => 'names']);
        $cats     = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
        $excerpt  = $post->post_excerpt;

        $audit = $this->auditPost($post);
        $issues = array_column($audit['issues'], 'type');

        // 构建 prompt（避开内容过滤词）
        // 始终尝试优化所有内容，但重点修复有问题的部分
        $has_title_issue = in_array('title', $issues);
        $has_tags_issue  = in_array('tags', $issues) || empty($tags);
        $has_desc_issue  = in_array('description', $issues) || empty($excerpt);

        $need_title = true; // 总是尝试优化标题（AI决定是否真的需要改）
        $need_tags  = true; // 总是尝试优化标签
        $need_desc  = $has_desc_issue; // 描述只在有问题时优化

        if ($need_title || $need_tags || $need_desc) {
            $content_snippet = mb_substr($content, 0, 150, 'utf-8');

            $prompt = "写作任务：" . PHP_EOL;
            $prompt .= "原标题（供参考，新标题不要包含）：{$title}" . PHP_EOL;
            if ($cats) $prompt .= "分类：" . implode('、', $cats) . PHP_EOL;
            if ($tags) $prompt .= "现有标签：" . implode('、', $tags) . PHP_EOL;
            if ($excerpt) $prompt .= "摘要：" . mb_substr($excerpt, 0, 100, 'utf-8') . PHP_EOL;
            $prompt .= "内容要点：" . mb_substr($content_snippet, 0, 100, 'utf-8') . PHP_EOL;
            $prompt .= PHP_EOL;
            $titleMin = self::SEO_STANDARDS['title']['min_chars'];
            $titleMax = self::SEO_STANDARDS['title']['max_chars'];
            if ($need_title) $prompt .= "【新标题生成规则】必须严格遵守以下所有规则：\n" .
                "规则1-长度：新标题必须恰好{$titleMin}-{$titleMax}个汉字（不得少于{$titleMin}字，也不得超过{$titleMax}字）\n" .
                "规则2-结构：在标题前加入搜索意图词（指南/教程/方案/推荐/评测/对比/怎么/如何/哪个等），核心关键词靠前\n" .
                "规则3-质量：必须包含文章核心关键词，语言自然流畅，不堆砌关键词\n" .
                "规则4-格式：不要【】、『】、《》、「」等特殊符号，不要（X字）提示，不要星号*\n" .
                "规则5-输出：只输出标题内容，不要任何前缀、编号、引号、解释或思考过程\n" .
                "⚠️ 警告：标题字数必须恰好{$titleMin}-{$titleMax}字，不足{$titleMin}字或超过{$titleMax}字的内容将被判定为不合格\n" . PHP_EOL;
            $tagMinCount = self::SEO_STANDARDS['tags']['min_count'];
            $tagMaxCount = self::SEO_STANDARDS['tags']['max_count'];
            $tagPreferred = self::SEO_STANDARDS['tags']['preferred_count'];
            $tagMinChars = self::SEO_STANDARDS['tags']['min_chars_per_tag'];
            $tagMaxChars = self::SEO_STANDARDS['tags']['max_chars_per_tag'];
            if ($need_tags) $prompt .= "【标签生成规则】必须严格遵守以下所有规则：\n" .
                "规则1-数量：必须输出{$tagMinCount}-{$tagMaxCount}个标签，推荐{$tagPreferred}个\n" .
                "规则2-长度：每个标签{$tagMinChars}-{$tagMaxChars}个汉字，禁止单个汉字，禁止超过{$tagMaxChars}字\n" .
                "规则3-质量：必须是含义完整的中文名词词组（如：智能家居、极简设计、衣柜收纳），不能是句子片段或虚词\n" .
                "规则4-禁止：不要单个汉字，不要虚词（的/了/着/等），不要句子片段（会被/作为/各有等），不要太宽泛的词（技术/方法/产品），不要品牌名、公司名、日期、无意义词\n" .
                "规则5-输出：只输出标签词汇，用中文逗号「，」分隔，不要编号、不要前缀、不要任何解释\n" .
                "⚠️ 警告：标签数量必须{$tagMinCount}-{$tagMaxCount}个，每个标签{$tagMinChars}-{$tagMaxChars}字，不合格将被判定为无效\n" . PHP_EOL;
            $descMin = self::SEO_STANDARDS['excerpt']['min_chars'];
            $descMax = self::SEO_STANDARDS['excerpt']['max_chars'];
            if ($need_desc) $prompt .= "【摘要生成规则】必须严格遵守以下所有规则：\n" .
                "规则1-长度：摘要必须恰好{$descMin}-{$descMax}个汉字（不得少于{$descMin}字，也不得超过{$descMax}字）\n" .
                "规则2-结构：必须包含（1）文章核心关键词（2）文章价值点（3）行动引导（如：本文将详细分析.../您将了解.../让我们一起来探讨...）\n" .
                "规则3-格式：直接输出摘要，不要任何前缀、编号、引号、解释或思考过程，不要换行\n" .
                "规则4-输出：只输出纯中文摘要内容，不要其他任何文字\n" .
                "⚠️ 警告：摘要字数必须恰好{$descMin}-{$descMax}字，不足{$descMin}字或超过{$descMax}字的内容将被判定为不合格\n" . PHP_EOL;
        } else {
            // 无需强制优化，但也标记为已处理，避免重复触发
            update_post_meta($post_id, self::META_OPTIMIZED, true);
            update_post_meta($post_id, self::META_OPTIMIZED_AT, current_time('mysql'));
            update_post_meta($post_id, self::META_SCORE, $audit['score']);
            return [
                'post_id'   => $post_id,
                'title'     => $title,
                'skipped'   => true,
                'skip_reason' => __('SEO 评分良好，无需强制优化', 'zuo-ai-plus'),
                'score'     => $audit['score'],
            ];
        }

        if (!$model) {
            $model = \ZuoAIPlus\Models\Model_Init::getModel(
                get_option('ai_plus_default_model') ?: 'minimax'
            );
        } elseif (is_string($model)) {
            // REST API 传入的是模型名字符串，需转换为对象
            $model = \ZuoAIPlus\Models\Model_Init::getModel($model);
        }

        // 备用模型列表
        $default_name = get_option('ai_plus_default_model', 'minimax');
        $all_models   = ['minimax', 'zhipu', 'kimi'];
        $model_names  = array_filter($all_models, fn($m) => $m !== $default_name);
        array_unshift($model_names, $default_name);

        $raw_text   = '';
        $last_error = '';
        $used_model = '';

        foreach ($model_names as $mname) {
            if (!$mname) continue;
            $m = \ZuoAIPlus\Models\Model_Init::getModel($mname);
            if (!$m) continue;

            $opts = [
                'max_tokens' => 800, 
                'temperature' => 0.3,
                'post_id' => $post_id,  // 传递文章ID，优化缓存策略
            ];
            // Kimi 关闭思考过程
            if ($mname === 'kimi') {
                $opts['thinking'] = ['type' => 'budget', 'budget_tokens' => 1];
            }

            for ($try = 0; $try < 2; $try++) {
                try {
                    $ai_result = $m->completion($prompt, $opts);
                    // 优先检测 API 错误（从 raw 响应直接检测）
                    $raw_resp   = $ai_result['raw'] ?? [];
                    $status_ok  = empty($raw_resp['base_resp']['status_code']) || $raw_resp['base_resp']['status_code'] === 0;
                    $has_choices = !empty($ai_result['raw']['choices']);
                    if (!$status_ok || !$has_choices) {
                        $last_error = 'API错误: ' . ($raw_resp['base_resp']['status_msg'] ?? $raw_resp['error']['message'] ?? 'unknown');
                        $raw_text   = '';
                        continue;
                    }
                    $raw_text  = \ZuoAIPlus\Models\Model_Init::extractContent($ai_result);
                    // 预检：如果返回内容看起来是内部思考/prompt内容，拒绝
                    if (preg_match('/then the|wait:|output format says|we should not|just output|should not include/i', $raw_text)) {
                        $last_error = 'Thinking content detected in response';
                        $raw_text   = '';
                        continue;
                    }
                    if (strlen($raw_text) > 5 && strpos($raw_text, '"error"') === false && strpos($raw_text, 'base_resp') === false) {
                        $used_model = $mname;
                        break 2;
                    }
                    $raw_text = '';
                } catch (\Exception $e) {
                    $last_error = $e->getMessage();
                    $raw_text   = '';
                }
            }
        }

        // AI 失败，使用规则引擎保底
        $parsed = [];  // 初始化，防止 AI 失败分支未定义
        if ($raw_text === '') {
            $fallback = $this->generateFallback($post, $need_title, $need_tags, $need_desc);
            $updates  = array_filter($fallback, fn($v) => !empty($v));
            if (empty($updates)) {
                return new \WP_Error('ai_error', 'AI 生成失败，且规则生成也无内容：' . $last_error);
            }
        } else {
            $parsed = $this->parseAiResponse($raw_text, $need_title, $need_tags, $need_desc);

            // 修复：清理标题叠加问题（AI可能在原标题前加修饰语）
            if (!empty($parsed['title']) && !empty($title)) {
                $new_title = $parsed['title'];
                $original_clean = trim($title, '《》');
                $new_clean = trim($new_title, '《》');

                // 只有当新标题以原标题"完全"开头（可能是AI把原标题和新标题连在一起了）
                // 才需要截取，且新标题必须明显比原标题长
                if (mb_strpos($new_title, $title) === 0 && mb_strlen($new_title) > mb_strlen($title) + 5) {
                    $parsed['title'] = trim(mb_substr($new_title, mb_strlen($title)), '《》：: ');
                } elseif (mb_strpos($new_title, $original_clean) === 0 && mb_strlen($new_title) > mb_strlen($original_clean) + 5) {
                    $parsed['title'] = trim(mb_substr($new_title, mb_strlen($original_clean)), '《》：: ');
                } else {
                    // 正常情况：直接使用AI生成的新标题
                    $parsed['title'] = $new_clean;
                }

                // 确保去除多余的书名号和冒号
                $parsed['title'] = trim($parsed['title'], '《》：: ');

                // 最终检查：如果清理后的标题太短或和原标题几乎一样，保留原标题
                if (mb_strlen($parsed['title'], 'utf-8') < 10) {
                    $parsed['title'] = $new_clean; // 回退到清理前的版本
                }
            }

            // 如果解析仍无结果，尝试规则保底
            if (empty($parsed['title']) && empty($parsed['tags']) && empty($parsed['description'])) {
                $fallback = $this->generateFallback($post, $need_title, $need_tags, $need_desc);
                foreach ($fallback as $k => $v) {
                    if (!isset($parsed[$k]) || empty($parsed[$k])) {
                        $parsed[$k] = $v;
                    }
                }
            }
            // 从解析结果构建更新内容
            $updates = [];
            if (!empty($parsed['title']))       $updates['title']       = $parsed['title'];
            if (!empty($parsed['tags']))        $updates['tags']         = $parsed['tags'];
            if (!empty($parsed['description'])) $updates['description']  = $parsed['description'];
        }

        if (empty($updates)) {
            return new \WP_Error('ai_error', '未能提取到任何可更新的内容');
        }

        // ── 后处理：校验并补足长度（确保符合统一SEO标准）───────────────
        $titleMin = self::SEO_STANDARDS['title']['min_chars'];
        $titleMax = self::SEO_STANDARDS['title']['max_chars'];
        $descMin = self::SEO_STANDARDS['excerpt']['min_chars'];
        $descMax = self::SEO_STANDARDS['excerpt']['max_chars'];

        // 标题长度校验：要求30-60字
        if (!empty($updates['title'])) {
            $title_len = mb_strlen($updates['title'], 'utf-8');
            if ($title_len < $titleMin) {
                // 标题太短，补足到30-40字：依次追加不同后缀（不重复追加同一个词）
                $suffix_pool = ['全面指南', '深度解析', '实用技巧', '优化方案', '高效方法', '专业建议', '核心要点', '实战技巧', '一文读懂', '完整攻略'];
                $suffix_idx = 0;
                while (mb_strlen($updates['title'], 'utf-8') < $titleMin && $suffix_idx < count($suffix_pool)) {
                    $updates['title'] .= $suffix_pool[$suffix_idx];
                    $suffix_idx++;
                }
                // 如果仍不足30字，不要再追加，保留当前结果（避免无意义重复）
            }
            if (mb_strlen($updates['title'], 'utf-8') > $titleMax) {
                // 标题过长，截断到60字以内
                $updates['title'] = mb_substr($updates['title'], 0, $titleMax, 'utf-8');
            }
        }
        // 摘要长度校验：要求80-120字
        if (!empty($updates['description'])) {
            $desc_len = mb_strlen($updates['description'], 'utf-8');
            if ($desc_len < $descMin) {
                // 摘要太短，补足到80字：追加价值点+行动引导
                $shortage = $descMin - $desc_len;
                $append = '本文将深入分析并提供实用方案，帮助您快速掌握核心要点。';
                if ($shortage > mb_strlen($append, 'utf-8')) {
                    $append = mb_substr($append, 0, $shortage, 'utf-8');
                }
                $updates['description'] .= $append;
            }
            if (mb_strlen($updates['description'], 'utf-8') > $descMax) {
                // 摘要过长，截断到120字以内
                $updates['description'] = mb_substr($updates['description'], 0, $descMax, 'utf-8');
                // 确保以完整的句子结束
                if (substr($updates['description'], -1) !== '。') {
                    $last_period = mb_strrpos($updates['description'], '。', 0, 'utf-8');
                    if ($last_period !== false && $last_period > 0) {
                        $updates['description'] = mb_substr($updates['description'], 0, $last_period + 1, 'utf-8');
                    }
                }
            }
        }

        $new_score = $this->applyOptimizations($post_id, $updates);

        if (is_wp_error($new_score)) {
            return $new_score;
        }

        // 记录优化状态
        update_post_meta($post_id, self::META_OPTIMIZED, true);
        update_post_meta($post_id, self::META_OPTIMIZED_AT, current_time('mysql'));
        if (!empty($parsed['title'])) {
            update_post_meta($post_id, self::META_NEW_TITLE, $parsed['title']);
        }
        update_post_meta($post_id, self::META_NEW_TAGS, json_encode($parsed['tags'] ?? [], JSON_UNESCAPED_UNICODE));
        update_post_meta($post_id, self::META_SCORE, $new_score);

        return [
            'post_id'    => $post_id,
            'title'      => $title,
            'new_title'  => $parsed['title'] ?? $title,
            'new_tags'   => $parsed['tags'] ?? $tags,
            'score'      => $new_score,
            'before_score' => $audit['score'],
            'updated'    => !empty($updates),
        ];
    }

    // ── 解析 AI 返回内容 ──
    public function parseAiResponse($text, $need_title, $need_tags, $need_desc)
    {
        $result = [];
        $text   = trim($text);

        // 清理 Markdown 链接 [text](url) → text
        $text = preg_replace('/\[(.*?)\]\(.*?\)/u', '$1', $text);
        // 清理 Markdown 粗体/斜体标记
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text);
        $text = preg_replace('/__(.+?)__/u', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/u', '$1', $text);
        $text = preg_replace('/_(.+?)_/u', '$1', $text);
        // 清理残余独立 URL
        $text = preg_replace('/https?:\/\/[^\s]+/u', '', $text);
        $lines  = array_filter(array_map('trim', explode("\n", $text)), 'strlen');

        if ($need_title) {
            // 优先：带前缀的行
            if (preg_match('/(?:新)?标题[：:]\s*(.+)/u', $text, $m)) {
                $raw_title = trim($m[1]);
                    // 防御性清理：去掉所有形式的字数提示（"（20字）"/"（X字）"/"（约X字）"等）
                    $raw_title = preg_replace('/[（（][^）]*?[字个篇条][）)]/u', '', $raw_title);
                    // 去掉英文前缀（如 "Original title:" / "New title:" / "Suggested title:" 等）
                    $raw_title = preg_replace('/^(?:original\s*title|new\s*title|suggested\s*title|recommended\s*title|final\s*title|optimized\s*title)[：:]\s*/i', '', $raw_title);
                    $raw_title = preg_replace('/（[^）]*?\d[^）]*?[字个篇条][）)]/u', '', $raw_title);
                    $raw_title = preg_replace('/（[^）]*?[字个篇条]）/u', '', $raw_title);
                    $raw_title = preg_replace('/^\s*\*+\s*/', '', $raw_title);
                    $raw_title = preg_replace('/\*+$/', '', $raw_title);
                    $raw_title = trim($raw_title);
                    // 必须有中文（防止提取到纯英文提示词/思考内容）
                    // 拒绝含英文计数/思考关键词的标题（如 "Let's count...Characters"）
                    if (preg_match('/count|character|word.?count/i', $raw_title)
                        && preg_match('/^[a-zA-Z]/', $raw_title)
                        && preg_match('/[\x{4e00}-\x{9fa5}]/u', $raw_title)) {
                        // 含英文思考词+英文开头+含中文 = 拒绝
                    } elseif (preg_match('/[\x{4e00}-\x{9fa5}]/u', $raw_title)) {
                        $result['title'] = $raw_title;
                    }
            } elseif (!empty($lines)) {
                // 文本很长（思考内容）：从后往前找第一个合理解标题
                if (mb_strlen($text, 'utf-8') > 200) {
                    for ($i = count($lines) - 1; $i >= 0; $i--) {
                        if (!isset($lines[$i])) continue;
                        $line = trim($lines[$i]);
                        if (!$line) continue;
                        // 跳过思考标记行、分析行
                        if (preg_match('/^[#\-*·\[\(]|^分析|^思考|^结论|^选择|^关键词|^字数|^SEO|^标签|^描述|^打磨|^优化|^内容|^要点|^写作|^任务|^格式|^输出|^提示|^说明|^参考|^注意|^直接|^标题[：:]/u', $line)) continue;
                        // 跳过含提示词/思考内容的行
                        if (preg_match('/count|counting|character|characters|word.?count|字数|字符|长度|length|prefix|content|label|tag|note|text|output|format|input|result|生成|写给|给你的|参考的|输出格式|写作任务|标签为|直接写|前缀|不要写|新标题[：:]|original\s*title|new\s*title|suggested\s*title|suggested\s*title/iu', $line)) continue;
                        if (preg_match('/^[0-9]+\./u', $line)) continue; // 编号列表
                        // 标题必须包含至少一个中文字符（防止纯英文/符号行被误选）
                        if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $line)) continue;
                        $len = mb_strlen($line, 'utf-8');
                        // 提高阈值：严格模式要求10字+，宽松模式要求20字+
                        if (($len >= 10 && $len <= 70 && strpos($line, '：') !== false) || ($len >= 20 && $len <= 70)) {
                            // 统一去掉英文前缀
                            $line = preg_replace('/^(?:original\s*title|new\s*title|suggested\s*title|recommended\s*title|final\s*title|optimized\s*title)[：:]\s*/i', '', $line);
                            $result['title'] = trim($line);
                            break;
                        }
                    }
                }
                // 正常情况：取第一行（更严格过滤）
                if (empty($result['title'])) {
                    foreach ($lines as $line) {
                        $len = mb_strlen($line, 'utf-8');
                        // 必须有中文，且不在skip列表中，且不含提示词
                        if ($len >= 6 && $len <= 30
                            && !preg_match('/^[#\-*·\[\(]|^SEO|^标签|^描述|^新标题|^新标签|^优化|^内容|^要点|^分析|^思考|^写作|^任务|^格式|^输出|^提示|^直接|^参考|^prefix|^content|^label|^note|^text|^output/u', $line)
                            && !preg_match('/count|counting|character|characters|word.?count|字数|字符|长度|length|prefix|content|label|tag|note|text|output|format|生成|写给|标签为|直接写|前缀|不要写|新标题[：:]|original\s*title|new\s*title|suggested\s*title/iu', $line)
                            && preg_match('/[\x{4e00}-\x{9fa5}]/u', $line)) {
                            // 统一去掉英文前缀
                            $line = preg_replace('/^(?:original\s*title|new\s*title|suggested\s*title|recommended\s*title|final\s*title|optimized\s*title)[：:]\s*/i', '', $line);
                            $result['title'] = trim($line);
                            break;
                        }
                    }
                }
            }
        }

        if ($need_tags) {
            $tag_text = '';
            if (preg_match('/(?:新)?标签[：:]\s*(.+)/u', $text, $m)) {
                $tag_text = $m[1];
            } elseif (mb_strlen($text, 'utf-8') > 200) {
                // 长文本：从后往前找第一个含逗号的行
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    if (!isset($lines[$i])) continue;
                    $line = trim($lines[$i]);
                    if (!$line) continue;
                    if (strpos($line, '，') !== false || strpos($line, ',') !== false) {
                        if (!preg_match('/^[#\-*·]|^分析|^思考|^结论|^选择|^SEO|^描述|^优化/u', $line)) {
                            $tag_text = $line;
                            break;
                        }
                    }
                }
            } elseif (count($lines) >= 2) {
                foreach (array_slice($lines, 1) as $line) {
                    if (preg_match('/^[#\-*·]|^SEO|^描述|^新标题|^优化|^分析/u', $line)) continue;
                    $tag_text = $line;
                    break;
                }
            }
            if ($tag_text) {
                $raw_tags = array_map('trim', mb_split('[,，、]', $tag_text));
                $tags = [];
                foreach ($raw_tags as $t) {
                    // 强制剥离 HTML 标签，防止 AI 返回含 HTML 的标签名导致存储型 XSS
                    $t = wp_strip_all_tags($t);
                    // 移除所有标点符号（保留中文、英文、数字）
                    $pattern = "#[[:punct:]]|\s|\"|'|\"\"|''|（|）|【|】|《|》#u";
                    $t = preg_replace($pattern, '', $t);
                    $len = mb_strlen($t, 'utf-8');
                    // 标签长度：2-6字（严格符合SEO标准）
                    if ($len < self::SEO_STANDARDS['tags']['min_chars_per_tag'] || $len > self::SEO_STANDARDS['tags']['max_chars_per_tag']) continue;
                    // 跳过无中文的标签
                    if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $t)) continue;
                    // 跳过虚词/句子片段（不以名词性词组结尾的）
                    if (preg_match('/^(的|了|在|和|与|或|被|把|从|向|到|由|给|对|为|是|有|将|会|能|可|也|都|就|才|又|还|已|曾|将|着|过|得|地|般|之|其|这|那|些|某|每|各|该|此|它|他|她|我|你)/u', $t)) continue;
                    // 跳过动词/谓语开头的句子片段
                    if (preg_match('/^(会被|作为|应当|需要|可以|能够|应该|属于|分为|成为|用于|适用|适合|提供|支持|包含|涵盖|代表|体现|反映|表明|说明|证明|意味着|相当于)/u', $t)) continue;
                    // 跳过含虚词链的句子片段（如"各有其独特的"）
                    if (preg_match('/的$|了$|着$|过$|得$|地$|也是|或是|而是|还是|各有|均为|均可|均可|两种|多个|几种/u', $t)) continue;
                    $tags[] = $t;
                }
                $result['tags'] = array_slice(array_values(array_unique($tags)), 0, 4);
            }
        }

        if ($need_desc) {
            // 优先：带「摘要：」前缀的行
            if (preg_match('/摘要[：:]\s*(.+)/u', $text, $m)) {
                $desc = trim($m[1]);
                // 防御性清理：去掉末尾的英文统计提示（如 "...Characters: 95"）
                $desc = preg_replace('/[,，.]?[\s]*[A-Za-z\s]*[Cc]haracters?[：:]?\s*\d+\s*$/u', '', $desc);
                $desc = preg_replace('/[,，.]?[\s]*[A-Za-z\s]*[Cc]har[\s:]*\d+[\s]*$/u', '', $desc);
                $desc = preg_replace('/[,，.]?[\s]*字数[：:]?\s*\d+\s*$/u', '', $desc);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $desc)) {
                    $result['description'] = trim($desc);
                }
            } else {
                // 长文本（包含推理过程）：从后往前找合理描述行
                if (mb_strlen($text, 'utf-8') > 200) {
                    for ($i = count($lines) - 1; $i >= 0; $i--) {
                        if (!isset($lines[$i])) continue;
                        $line = trim($lines[$i]);
                        if (!$line) continue;
                        // 跳过标题类、分析类、标签类行
                        if (preg_match('/^[#\-*·\[\(]|^分析|^思考|^结论|^选择|^关键词|^字数|^SEO|^标签|^描述|^打磨|^优化|^内容|^要点|^写作|^任务|^格式|^输出|^提示|^说明|^参考|^注意|^直接|^标题|^新标题|^新标签/u', $line)) continue;
                        // 跳过含思考/计数关键词的英文行
                        if (preg_match('/count|counting|character|characters|word.?count|字数|字符|长度|length|prefix|content|label|tag|note|text|output|format|生成|写给|给你的|参考的|输出格式|写作任务|标签为|直接写|前缀|不要写|original\s*title|new\s*title|suggested\s*title|recommended\s*title|final\s*title|optimized\s*title|thus|therefore|hence|so\s+we|let\s+us|we\s+can/iu', $line)) continue;
                        // 跳过纯英文或无中文行
                        if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $line)) continue;
                        // 跳过太短或太长的行（描述合理范围 30-130 字）
                        $len = mb_strlen($line, 'utf-8');
                        if ($len < 30 || $len > 130) continue;
                        // 防御性清理末尾统计
                        $line = preg_replace('/[,，.]?[\s]*[A-Za-z\s]*[Cc]haracters?[：:]?\s*\d+\s*$/u', '', $line);
                        $line = preg_replace('/[,，.]?[\s]*字数[：:]?\s*\d+\s*$/u', '', $line);
                        $result['description'] = trim($line);
                        break;
                    }
                } elseif (count($lines) >= 3) {
                    // 正常情况：从第3行起找合理描述行
                    foreach (array_slice($lines, 2) as $line) {
                        if (preg_match('/^[#\-*·]|^标签|^新标题|^新标签|^优化|^分析/u', $line)) continue;
                        if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $line)) continue;
                        $len = mb_strlen(trim($line), 'utf-8');
                        if ($len < 30 || $len > 130) continue;
                        $result['description'] = trim($line);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    // ── 基于文章内容自动生成（AI 失败时的保底） ──
    public function generateFallback($post, $need_title, $need_tags, $need_desc)
    {
        $result = [];
        $title  = $post->post_title;
        $excerpt = $post->post_excerpt ?: mb_substr(wp_strip_all_tags($post->post_content), 0, 150, 'utf-8');

        if ($need_title) {
            $len = mb_strlen($title, 'utf-8');
            if ($len < 10) {
                // 太短：扩写
                $result['title'] = $title . '：实用指南';
            } elseif ($len > 60) {
                // 太长：截断
                $result['title'] = mb_substr($title, 0, 55, 'utf-8') . '...';
            } else {
                $result['title'] = $title;
            }
        }

        if ($need_tags) {
            // 从标题和摘要中提取 2-6 字的有意义词
            $text = $title . ' ' . $excerpt;
            preg_match_all('/[\x{4e00}-\x{9fa5}]{2,6}/u', $text, $matches);
            $seen = [];
            $tags = [];
            foreach ($matches[0] as $w) {
                // 过滤掉常见无意义词
                # 去重停用词（1-2字无意义词，使用 array_flip 避免 in_array 重复遍历）
                static $stop = null;
                if ($stop === null) {
                    $stop = array_flip(['是的','的一','和的','在的','了','和','在','是','的','个','与','或','及','为','之','其','以','而','则','于','从','被','并','等','各','此','该','有时','或者']);
                }
                if (isset($stop[$w])) continue;
                if (isset($seen[$w])) continue;
                $seen[$w] = true;
                $tags[] = $w;
                if (count($tags) >= 5) break;
            }
            $result['tags'] = $tags;
        }

        if ($need_desc) {
            $result['description'] = mb_substr($excerpt, 0, 100, 'utf-8');
        }

        return $result;
    }

    // ── 应用优化到文章（带事务回滚） ──
    private function applyOptimizations($post_id, $updates)
    {
        // 调试：检查当前用户上下文
        $uid = get_current_user_id();
        $user = $uid ? get_user_by('id', $uid) : null;
        if (!$uid) {
            return new \WP_Error('rest_forbidden', __('无法确定当前登录用户（UID=0），请确认 WordPress 认证正常', 'zuo-ai-plus'), ['status' => 401]);
        }

        // 权限检查：当前用户必须有编辑这篇文章的权限
        $post = get_post($post_id);
        if (!current_user_can('edit_post', $post_id) && !current_user_can('edit_others_posts')) {
            return new \WP_Error('rest_cannot_edit', __('当前账户没有文章编辑权限', 'zuo-ai-plus') . '（UID=' . $uid . '，角色=' . ($user ? implode(',', $user->roles) : 'unknown') . '），' . __('请确认账户有编辑者或更高权限', 'zuo-ai-plus'), ['status' => 401]);
        }

        // 备份原始数据（用于回滚）
        $backup = [
            'post_title'   => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'tags'         => wp_get_post_tags($post_id, ['fields' => 'names']),
        ];

        $wp_updates = ['ID' => $post_id];
        $tag_update_success = false;
        $post_update_success = false;

        try {
            // 步骤1：更新标签
            if (!empty($updates['tags'])) {
                $tag_ids = [];
                foreach ($updates['tags'] as $tag_name) {
                    $tag_name = trim($tag_name);
                    if (mb_strlen($tag_name, 'utf-8') < 2) continue;
                    $term = get_term_by('name', $tag_name, 'post_tag');
                    if ($term) {
                        $tag_ids[] = $term->term_id;
                    } else {
                        $new = wp_insert_term($tag_name, 'post_tag');
                        if (!is_wp_error($new)) {
                            $tag_ids[] = $new['term_id'];
                        }
                    }
                }
                $set = wp_set_object_terms($post_id, $tag_ids, 'post_tag');
                if (is_wp_error($set)) {
                    throw new \Exception(__('标签更新失败：', 'zuo-ai-plus') . $set->get_error_message());
                }
                $tag_update_success = true;
            }

            // 步骤2：更新文章数据
            if (!empty($updates['title'])) {
                $wp_updates['post_title'] = wp_strip_all_tags($updates['title']);
            }

            if (!empty($updates['description'])) {
                $wp_updates['post_excerpt'] = wp_strip_all_tags($updates['description']);
            }

            if (count($wp_updates) > 1) {
                // 前置检查：若文章正被活跃编辑（锁未过期），给出明确提示而非笼统报错
                $lock_meta = get_post_meta($post_id, '_edit_lock', true);
                if ($lock_meta) {
                    list($lock_time, $lock_uid) = explode(':', $lock_meta, 2);
                    $lock_age = time() - (int)$lock_time;
                    if ($lock_age < 180) {
                        $lock_user = $lock_uid && $lock_uid > 0 ? get_user_by('id', (int)$lock_uid) : null;
                        $user_name = $lock_user ? $lock_user->display_name : ("UID={$lock_uid}");
                        throw new \Exception(sprintf(
                            /* translators: %1$s is the user display name or UID, %2$d is seconds remaining */
                            __('文章正被其他用户编辑中（锁定者：%1$s，剩余约%2$d秒后自动解除）。请稍后再试，或在 Gutenberg 编辑器中关闭该文章后重试。', 'zuo-ai-plus'),
                            $user_name,
                            180 - $lock_age
                        ));
                    }
                }

                $updated = wp_update_post($wp_updates, true); // true = 返回WP_Error
                if (is_wp_error($updated)) {
                    $err_msg = $updated->get_error_message();
                    // 细化数据库写入失败的常见原因
                    if (strpos($err_msg, 'database') !== false || strpos($err_msg, 'Could not') !== false) {
                        throw new \Exception(__('文章更新失败：数据库写入被拒绝。可能是文章正被其他编辑器占用，或服务器超时。建议：1) 确认 Gutenberg 编辑器中是否已关闭该文章；2) 稍后重试。', 'zuo-ai-plus'));
                    }
                    throw new \Exception(__('文章更新失败：', 'zuo-ai-plus') . $err_msg);
                }
                $post_update_success = true;
            }

            // 步骤3：更新优化标记
            update_post_meta($post_id, self::META_OPTIMIZED, true);
            update_post_meta($post_id, self::META_OPTIMIZED_AT, current_time('mysql'));

            return $this->auditPost(get_post($post_id))['score'];

        } catch (\Exception $e) {
            // 回滚：恢复原始数据
            if ($tag_update_success && !empty($backup['tags'])) {
                $orig_tag_ids = [];
                foreach ($backup['tags'] as $tag_name) {
                    $term = get_term_by('name', $tag_name, 'post_tag');
                    if ($term) {
                        $orig_tag_ids[] = (int) $term->term_id;
                    }
                }
                if (!empty($orig_tag_ids)) {
                    wp_set_object_terms($post_id, $orig_tag_ids, 'post_tag');
                }
            }

            if ($post_update_success) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_title'   => $backup['post_title'],
                    'post_excerpt' => $backup['post_excerpt'],
                ], true);
            }

            return new \WP_Error('optimization_failed', $e->getMessage());
        }
    }

    // ── 重置单篇文章优化状态 ──
    public function resetPost($post_id)
    {
        $orig_title = get_post_meta($post_id, self::META_ORIG_TITLE, true);
        $orig_tags  = get_post_meta($post_id, self::META_ORIG_TAGS, true);

        if ($orig_title) {
            wp_update_post(['ID' => $post_id, 'post_title' => $orig_title]);
        }

        if ($orig_tags) {
            $tags = json_decode($orig_tags, true);
            if (is_array($tags)) {
                $tag_ids = [];
                foreach ($tags as $tag_name) {
                    $term = get_term_by('name', $tag_name, 'post_tag');
                    if ($term) $tag_ids[] = $term->term_id;
                }
                wp_set_object_terms($post_id, $tag_ids, 'post_tag');
            }
        }

        // 清除优化状态 + 原始存档（重置应完全回到优化前状态，下次优化重新存档）
        foreach ([self::META_OPTIMIZED, self::META_OPTIMIZED_AT,
                   self::META_NEW_TITLE, self::META_NEW_TAGS,
                   self::META_SCORE, self::META_ISSUES,
                   self::META_ORIG_TITLE, self::META_ORIG_TAGS] as $key) {
            delete_post_meta($post_id, $key);
        }

        // 清除 AI 缓存：精确删除该文章的缓存（新 key 结构：ai_cache_post_{$postId}_{hash}）
        if (get_option('ai_plus_cache_enabled', true)) {
            /* @phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching */
            global $wpdb;
            $postPrefix = 'ai_cache_post_' . $post_id . '_';
            $likeData = '_transient_' . $wpdb->esc_like($postPrefix) . '%';
            $likeTimeout = '_transient_timeout_' . $wpdb->esc_like($postPrefix) . '%';
            // 同时删除数据和对应的 timeout 记录
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $likeData, $likeTimeout
            ));
            /* @phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching */
        }

        return true;
    }

    // ── 获取优化统计 ──
    public function getStats()
    {
        /* @phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching */
        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s",
            'post', 'publish'
        ));
        $optimized = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id=p.ID WHERE pm.meta_key=%s AND pm.meta_value='1' AND p.post_status='publish' AND p.post_type='post'",
            self::META_OPTIMIZED
        ));
        // 清理孤立的 meta 记录（已删除文章的残留数据）
        // 注意：MySQL 9.0+ 不支持多表 DELETE 带 LIMIT，改用子查询方式
        $orphan_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id=p.ID WHERE p.ID IS NULL AND pm.meta_key IN (%s,%s,%s) LIMIT 500",
            self::META_OPTIMIZED, self::META_OPTIMIZED_AT, self::META_SCORE
        ));
        if ($orphan_ids) {
            $placeholders = implode(',', array_fill(0, count($orphan_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ({$placeholders})",
                ...$orphan_ids
            ));
        }
        $avg_score = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(CAST(meta_value AS SIGNED)) FROM {$wpdb->postmeta} WHERE meta_key=%s",
                self::META_SCORE
            )
        );
        /* @phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching */

        return [
            'total'     => $total,
            'optimized' => $optimized,
            'pending'    => max(0, $total - $optimized),
            'avg_score' => $avg_score ?: 0,
        ];
    }

    // ── 批量 AI 优化（带超时保护） ──
    public function batchOptimize($post_ids, $model_name = '')
    {
        if (empty($model_name)) {
            $model_name = get_option('ai_plus_default_model') ?: 'minimax';
        }
        $model = \ZuoAIPlus\Models\Model_Init::getModel($model_name);
        if (!$model) {
            return new \WP_Error('model_error', 'AI 模型未配置');
        }

        $maxBatchSeconds = 300; // 整批超时5分钟
        $batchStart = microtime(true);
        $results = [];

        foreach ($post_ids as $post_id) {
            // 检查整批是否已超时
            $elapsed = microtime(true) - $batchStart;
            if ($elapsed >= $maxBatchSeconds) {
                $results[$post_id] = [
                    'error' => '整批处理超时（已运行' . round($elapsed) . '秒），剩余文章未处理',
                    '_timeout' => true,
                ];
                break;
            }

            $result = $this->optimizePost((int) $post_id, $model);
            $results[$post_id] = is_wp_error($result)
                ? ['error' => $result->get_error_message()]
                : $result;

            // 避免 AI 限流（根据剩余时间动态调整，等待时间过半时减少延迟）
            $elapsedAfter = microtime(true) - $batchStart;
            $remaining = max(0, $maxBatchSeconds - $elapsedAfter);
            // 如果剩余时间不足1/3，减少等待时间（优先完成已开始的请求）
            $sleep = ($remaining < $maxBatchSeconds / 3) ? 100000 : 300000;
            usleep((int) $sleep);
        }

        return $results;
    }
}
