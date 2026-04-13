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
            $issues[] = ['type' => 'title', 'severity' => 'high', 'msg' => "标题太短（{$title_len}字），建议30-60字"];
            $score -= 30;
        } elseif ($title_len > 60) {
            $issues[] = ['type' => 'title', 'severity' => 'medium', 'msg' => "标题过长（{$title_len}字），建议控制在60字以内"];
            $score -= 15;
        }

        // 标签检查
        if (empty($tags)) {
            $issues[] = ['type' => 'tags', 'severity' => 'high', 'msg' => '没有标签'];
            $score -= 25;
        } else {
            foreach ($tags as $tag) {
                $len = mb_strlen($tag, 'utf-8');
                if ($len > 8) {
                    $issues[] = ['type' => 'tags', 'severity' => 'medium', 'msg' => "标签「{$tag}」过长（{$len}字），建议中英混合词不超过10字，纯中文不超过6字"];
                    $score -= 5;
                }
            }
            if (count($tags) > 6) {
                $issues[] = ['type' => 'tags', 'severity' => 'low', 'msg' => "标签过多（" . count($tags) . "个），建议3-5个"];
                $score -= 5;
            }
        }

        // Description 检查（excerpt）
        $excerpt_len = mb_strlen($excerpt, 'utf-8');
        if ($excerpt_len < 50) {
            $issues[] = ['type' => 'description', 'severity' => 'medium', 'msg' => '摘要缺失或太短，建议80-120字'];
            $score -= 15;
        }

        // 内容长度检查
        if (mb_strlen($content, 'utf-8') < 300) {
            $issues[] = ['type' => 'content', 'severity' => 'low', 'msg' => '文章内容偏短，建议500字以上'];
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
        $need_title = in_array('title', $issues);
        $need_tags  = in_array('tags', $issues);
        $need_desc  = in_array('description', $issues);

        if ($need_title || $need_tags || $need_desc) {
            $content_snippet = mb_substr($content, 0, 150, 'utf-8');

            $prompt = "写作任务：" . PHP_EOL;
            $prompt .= "标题：{$title}" . PHP_EOL;
            if ($cats) $prompt .= "分类：" . implode('、', $cats) . PHP_EOL;
            if ($tags) $prompt .= "现有标签：" . implode('、', $tags) . PHP_EOL;
            if ($excerpt) $prompt .= "摘要：" . mb_substr($excerpt, 0, 100, 'utf-8') . PHP_EOL;
            $prompt .= "内容要点：" . mb_substr($content_snippet, 0, 100, 'utf-8') . PHP_EOL;
            $prompt .= PHP_EOL;
            if ($need_title) $prompt .= "请改写一个更吸引人的标题，30-60字，含关键词，直接输出。" . PHP_EOL;
            if ($need_tags) $prompt .= "推荐3-5个标签，每个2-6字，用逗号分隔，直接输出。" . PHP_EOL;
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
                'skip_reason' => 'SEO 评分良好，无需强制优化',
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
            // 如果解析仍无结果，尝试规则保底
            if (empty($parsed['title']) && empty($parsed['tags']) && empty($parsed['description'])) {
                $fallback = $this->generateFallback($post, $need_title, $need_tags, $need_desc);
                foreach ($fallback as $k => $v) {
                    if (!isset($parsed[$k]) || empty($parsed[$k])) {
                        $parsed[$k] = $v;
                    }
                }
            }
            $updates = [];
            if (!empty($parsed['title']))       $updates['title']       = $parsed['title'];
            if (!empty($parsed['tags']))        $updates['tags']         = $parsed['tags'];
            if (!empty($parsed['description'])) $updates['description']  = $parsed['description'];
        }

        if (empty($updates)) {
            return new \WP_Error('ai_error', '未能提取到任何可更新的内容');
        }

        // 应用优化
        $updates = [];
        if (!empty($parsed['title'])) {
            $updates['title'] = $parsed['title'];
        }
        if (!empty($parsed['tags'])) {
            $updates['tags'] = $parsed['tags'];
        }
        if (!empty($parsed['description'])) {
            $updates['description'] = $parsed['description'];
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
                        $line = trim($lines[$i]);
                        // 跳过思考标记行、分析行
                        if (preg_match('/^[#\-*·\[\(]|^分析|^思考|^结论|^选择|^关键词|^字数|^SEO|^标签|^描述|^打磨|^优化/u', $line)) continue;
                        if (preg_match('/^[0-9]+\./u', $line)) continue; // 编号列表
                        $len = mb_strlen($line, 'utf-8');
                        if ($len >= 10 && $len <= 70 && strpos($line, '：') !== false || ($len >= 15 && $len <= 70)) {
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
                    $line = trim($lines[$i]);
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
                $tags = array_filter(
                    array_map('trim', preg_split('/[,，]/', $tag_text)),
                    fn($t) => mb_strlen($t, 'utf-8') >= 2 && mb_strlen($t, 'utf-8') <= 8
                );
                $result['tags'] = array_slice(array_values($tags), 0, 5);
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
                if (in_array($w, ['的是','的一','和的','在的','是的','了','和','在','是','的','了','个','与','或','及','为','与','的','之','其','以','而','则','则','于','从','被','并','等','各','此','该','有时','或者'])) continue;
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

    // ── 应用优化到文章 ──
    private function applyOptimizations($post_id, $updates)
    {
        // 调试：检查当前用户上下文
        $uid = get_current_user_id();
        $user = $uid ? get_user_by('id', $uid) : null;
        if (!$uid) {
            return new \WP_Error('rest_forbidden', '无法确定当前登录用户（UID=0），请确认 WordPress 认证正常', ['status' => 401]);
        }

        // 权限检查：当前用户必须有编辑这篇文章的权限
        $post = get_post($post_id);
        if (!current_user_can('edit_post', $post_id) && !current_user_can('edit_others_posts')) {
            return new \WP_Error('rest_cannot_edit', '当前账户没有文章编辑权限（UID=' . $uid . '，角色=' . ($user ? implode(',', $user->roles) : 'unknown') . '），请确认账户有编辑者或更高权限', ['status' => 401]);
        }

        $wp_updates = ['ID' => $post_id];

        if (!empty($updates['title'])) {
            $wp_updates['post_title'] = wp_strip_all_tags($updates['title']);
        }

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
                return $set;
            }
        }

        if (!empty($updates['description'])) {
            $wp_updates['post_excerpt'] = wp_strip_all_tags($updates['description']);
        }

        if (count($wp_updates) > 1) {
            $updated = wp_update_post($wp_updates);
            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        return $this->auditPost(get_post($post_id))['score'];
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
