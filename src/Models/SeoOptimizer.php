<?php
/**
 * Zuo AI Plus - SEO Optimizer
 * 全站文章 SEO 诊断 + AI 优化模块
 */

namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class SeoOptimizer
{
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

        $results = [];
        foreach ($query->posts as $post) {
            $result = $this->auditPost($post);
            if ($skip_done && get_post_meta($post->ID, self::META_OPTIMIZED, true)) {
                $result['skipped'] = true;
                $result['skip_reason'] = '已优化过';
            }
            $results[] = $result;
        }

        return [
            'posts'      => $results,
            'total'      => (int) $query->found_posts,
            'total_pages'=> $query->max_num_pages,
            'current_page' => $paged,
        ];
    }

    // ── 诊断单篇文章 ──
    public function auditPost($post)
    {
        $post_id   = $post->ID;
        $title     = $post->post_title;
        $content   = wp_strip_all_tags($post->post_content);
        $excerpt   = $post->post_excerpt ?: mb_substr($content, 0, 150, 'utf-8');
        $tags      = wp_get_post_tags($post_id, ['fields' => 'names']);
        $categories = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);

        $title_len = mb_strlen($title, 'utf-8');
        $issues    = [];
        $score     = 100;

        // 标题检查
        if ($title_len < 10) {
            $issues[] = ['type' => 'title', 'severity' => 'high', 'msg' => sprintf(__('标题太短（%d字），建议30-60字', 'zuo-ai-plus'), $title_len)];
            $score -= 30;
        } elseif ($title_len > 60) {
            $issues[] = ['type' => 'title', 'severity' => 'medium', 'msg' => sprintf(__('标题过长（%d字），建议控制在60字以内', 'zuo-ai-plus'), $title_len)];
            $score -= 15;
        }

        // 标签检查
        if (empty($tags)) {
            $issues[] = ['type' => 'tags', 'severity' => 'high', 'msg' => __('没有标签', 'zuo-ai-plus')];
            $score -= 25;
        } else {
            foreach ($tags as $tag) {
                $len = mb_strlen($tag, 'utf-8');
                if ($len > 8) {
                    $issues[] = ['type' => 'tags', 'severity' => 'medium', 'msg' => sprintf(__('标签「%s」过长（%d字），建议中英混合词不超过10字，纯中文不超过6字', 'zuo-ai-plus'), $tag, $len)];
                    $score -= 5;
                }
            }
            if (count($tags) > 6) {
                $issues[] = ['type' => 'tags', 'severity' => 'low', 'msg' => sprintf(__('标签过多（%d个），建议3-5个', 'zuo-ai-plus'), count($tags))];
                $score -= 5;
            }
        }

        // Description 检查（excerpt）
        $excerpt_len = mb_strlen($excerpt, 'utf-8');
        if ($excerpt_len < 50) {
            $issues[] = ['type' => 'description', 'severity' => 'medium', 'msg' => __('摘要缺失或太短，建议80-120字', 'zuo-ai-plus')];
            $score -= 15;
        }

        // 内容长度检查
        if (mb_strlen($content, 'utf-8') < 300) {
            $issues[] = ['type' => 'content', 'severity' => 'low', 'msg' => __('文章内容偏短，建议500字以上', 'zuo-ai-plus')];
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

        // 保存原始值（仅首次）
        if (!get_post_meta($post_id, self::META_OPTIMIZED, true)) {
            $old_tags = wp_get_post_tags($post_id, ['fields' => 'names']);
            update_post_meta($post_id, self::META_ORIG_TITLE, $post->post_title);
            update_post_meta($post_id, self::META_ORIG_TAGS, json_encode($old_tags, JSON_UNESCAPED_UNICODE));
        }

        $title    = $post->post_title;
        $content  = wp_strip_all_tags($post->post_content);
        $tags     = wp_get_post_tags($post_id, ['fields' => 'names']);
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
            if ($need_title) $prompt .= "新标题要求：\n" .
                "1. 基于原标题和内容，写一个更吸引人的标题\n" .
                "2. 30-60字，包含核心关键词\n" .
                "3. 标题必须完整独立，不要包含原标题的任何前缀\n" .
                "4. 直接输出新标题，不要加'新标题：'前缀" . PHP_EOL;
            if ($need_tags) $prompt .= "标签要求（重要）：\n" .
                "1. 从文章核心主题提取3-5个关键词（如：智能家居、极简设计、收纳技巧）\n" .
                "2. 每个标签必须是完整的概念词，严禁将一句话拆分成多个词\n" .
                "3. 错误示例：\"智能, 家居, 极简, 设计\"（这是拆分句子）\n" .
                "4. 正确示例：\"智能家居, 极简设计, 收纳技巧\"（这是完整关键词）\n" .
                "5. 每个标签2-6个中文字，用逗号分隔，直接输出标签列表" . PHP_EOL;
            if ($need_desc) $prompt .= "写一段80-120字的简介，直接输出。" . PHP_EOL;
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

            $opts = ['max_tokens' => 800, 'temperature' => 0.3];
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
        $lines  = array_filter(array_map('trim', explode("\n", $text)), 'strlen');

        if ($need_title) {
            // 优先：带前缀的行
            if (preg_match('/(?:新)?标题[：:]\s*(.+)/u', $text, $m)) {
                $result['title'] = trim($m[1]);
            } elseif (!empty($lines)) {
                // 文本很长（思考内容）：从后往前找第一个合理解标题
                if (mb_strlen($text, 'utf-8') > 200) {
                    for ($i = count($lines) - 1; $i >= 0; $i--) {
                        if (!isset($lines[$i])) continue;
                        $line = trim($lines[$i]);
                        if (!$line) continue;
                        // 跳过思考标记行、分析行
                        if (preg_match('/^[#\-*·\[\(]|^分析|^思考|^结论|^选择|^关键词|^字数|^SEO|^标签|^描述|^打磨|^优化/u', $line)) continue;
                        if (preg_match('/^[0-9]+\./u', $line)) continue; // 编号列表
                        $len = mb_strlen($line, 'utf-8');
                        if (($len >= 10 && $len <= 70 && strpos($line, '：') !== false) || ($len >= 15 && $len <= 70)) {
                            $result['title'] = $line;
                            break;
                        }
                    }
                }
                // 正常情况：取第一行
                if (empty($result['title'])) {
                    foreach ($lines as $line) {
                        $len = mb_strlen($line, 'utf-8');
                        if ($len >= 10 && $len <= 70 && !preg_match('/^[#\-*·\[\(]|^SEO|^标签|^描述|^新标题|^新标签|^优化/u', $line)) {
                            $result['title'] = $line;
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
                $raw_tags = array_map('trim', preg_split('/[,，、]/', $tag_text));
                $tags = [];
                foreach ($raw_tags as $t) {
                    // 移除所有标点符号（保留中文、英文、数字）
                    $pattern = "#[[:punct:]]|\s|\"|'|\"\"|''|（|）|【|】|《|》#u";
                    $t = preg_replace($pattern, '', $t);
                    $len = mb_strlen($t, 'utf-8');
                    // 跳过太短的词
                    if ($len < 2) continue;
                    // 跳过可能是句子拆分的片段（单字或两字以下）
                    if ($len <= 2 && preg_match('/^[\x{4e00}-\x{9fa5}]$/u', $t)) continue;
                    // 只保留完整词汇，长度超过6的跳过（不截断，避免破坏语义）
                    if ($len > 6) continue;
                    $tags[] = $t;
                }
                $result['tags'] = array_slice(array_values(array_unique($tags)), 0, 5);
            }
        }

        if ($need_desc) {
            if (preg_match('/(?:SEO)?描述[：:]\s*(.+)/u', $text, $m)) {
                $result['description'] = trim($m[1]);
            } elseif (count($lines) >= 3) {
                foreach (array_slice($lines, 2) as $line) {
                    if (preg_match('/^[#\-*·]|^标签|^新标题|^优化|^分析/u', $line)) continue;
                    $result['description'] = $line;
                    break;
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
                $updated = wp_update_post($wp_updates, true); // true = 返回WP_Error
                if (is_wp_error($updated)) {
                    throw new \Exception(__('文章更新失败：', 'zuo-ai-plus') . $updated->get_error_message());
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

        foreach ([self::META_OPTIMIZED, self::META_OPTIMIZED_AT, self::META_ORIG_TITLE,
                   self::META_ORIG_TAGS, self::META_NEW_TITLE, self::META_NEW_TAGS,
                   self::META_SCORE, self::META_ISSUES] as $key) {
            delete_post_meta($post_id, $key);
        }

        return true;
    }

    // ── 获取优化统计 ──
    public function getStats()
    {
        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $optimized = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value='1'",
            self::META_OPTIMIZED
        ));
        $avg_score = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(CAST(meta_value AS SIGNED)) FROM {$wpdb->postmeta} WHERE meta_key=%s",
                self::META_SCORE
            )
        );

        return [
            'total'     => $total,
            'optimized' => $optimized,
            'pending'    => $total - $optimized,
            'avg_score' => $avg_score ?: 0,
        ];
    }

    // ── 批量 AI 优化（带进度） ──
    public function batchOptimize($post_ids, $model_name = '')
    {
        if (empty($model_name)) {
            $model_name = get_option('ai_plus_default_model') ?: 'minimax';
        }
        $model = \ZuoAIPlus\Models\Model_Init::getModel($model_name);
        if (!$model) {
            return new \WP_Error('model_error', 'AI 模型未配置');
        }

        $results = [];
        foreach ($post_ids as $post_id) {
            $result = $this->optimizePost((int) $post_id, $model);
            $results[$post_id] = is_wp_error($result)
                ? ['error' => $result->get_error_message()]
                : $result;
            // 避免 AI 限流
            usleep(300000); // 300ms
        }

        return $results;
    }
}
