<?php
/**
 * Plugin Name: Zuo AI Plus
 * Description: 集成智谱GLM、阿里通义、MiniMax、Kimi等国内大模型，支持文章生成、SEO优化、文生图、网址导航、AI客服聊天等功能。
 * Version: 1.5.13
 * Author: 左运来
 * Author URI: https://www.yily.top?from=wp-plugin
 * License: GPLv2 or later
 * Text Domain: zuo-ai-plus
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */
if (!defined('ABSPATH')) exit;

// ── 常量(插件常量检查防止重复加载)─────────────────────────────────────────
if (!defined('AI_PLUS_VERSION')) {
    define('AI_PLUS_VERSION', '1.5.18');
}
if (!defined('AI_PLUS_PLUGIN_DIR')) {
    define('AI_PLUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('AI_PLUS_PLUGIN_URL')) {
    define('AI_PLUS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('AI_PLUS_PLUGIN_BASENAME')) {
    define('AI_PLUS_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// ── 类自动加载(所有类统一从此入口加载)────────────────────────────────────
require_once AI_PLUS_PLUGIN_DIR . 'src/Loader.php';

// ── REST API 路由注册(在 rest_api_init 时加载所有 Controller)──────────────
add_action('rest_api_init', function () {
    (new \ZuoAIPlus\Controllers\ContentController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\UtilityController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\ChatController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\ModelsController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\LicenseController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\SeoController())->registerRoutes();
}, 5);

// ── 加载翻译文件 ───────────────────────────────────────────────────────────
// WordPress 4.6+ 会自动从插件根目录的 /languages/ 加载翻译，无需手动调用 load_plugin_textdomain()
// （保留此注释以说明为何此处没有 load_plugin_textdomain 调用）

// ── Admin / Frontend 初始化 ───────────────────────────────────────────────
add_action('plugins_loaded', function () {
    // 版本迁移（每次加载检查，确保升级后自动迁移）
    \ZuoAIPlus\Utils\Activator::migrate();
    new \ZuoAIPlus\Admin\Admin_Init();
    // 导航模块：根据开关决定是否加载
    if (get_option('ai_plus_nav_enabled', '1')) {
        new \ZuoAIPlus\Admin\Navigation_Init();
    }
    new \ZuoAIPlus\Frontend\Frontend_Init();
});

// ── 导航 CPT 注册（必须在 init 之后）──────────────────────────────────────
if (get_option('ai_plus_nav_enabled', '1')) {
    add_action('init', function () {
        \ZuoAIPlus\Models\NavigationSite::register();
        \ZuoAIPlus\Models\NavigationSite::registerMeta();
    }, 5);

    // 添加导航网站的 Meta Box（独立 action）
    add_action('add_meta_boxes', function () {
        if (get_post_type() === 'nav_site') {
            add_meta_box(
                'nav_site_meta',
                '📂 网站信息',
                function () { include AI_PLUS_PLUGIN_DIR . 'src/Admin/views/navigation-meta.php'; },
                'nav_site',
                'normal',
                'high'
            );
        }
    });

    // ── 导航网站模板路径 ──────────────────────────────────────────────────────
    add_filter('template_include', function ($template) {
        $isNavTemplate = false;
        if (is_post_type_archive('nav_site') || is_post_type_archive('nav-sites')) {
            $t = AI_PLUS_PLUGIN_DIR . 'Templates/archive-nav_site.php';
            if (file_exists($t)) {
                $template = $t; $isNavTemplate = true;
                add_filter('pre_get_document_title', function () {
                    return '网址导航 - ' . get_bloginfo('name');
                });
            }
        }
        if (is_singular('nav_site')) {
            $t = AI_PLUS_PLUGIN_DIR . 'Templates/single-nav_site.php';
            if (file_exists($t)) {
                $template = $t; $isNavTemplate = true;
                // 详情页 SEO title + meta + og
                add_filter('pre_get_document_title', function ($title) {
                    $postId = get_queried_object_id();
                    $meta = \ZuoAIPlus\Models\NavigationSite::getMeta($postId);
                    $name = $meta['name'] ?: get_the_title($postId);
                    $desc = $meta['description'] ?: $meta['ai_summary'] ?: '';
                    return $name . ' - ' . get_bloginfo('name');
                });
                add_action('wp_head', function () {
                    $postId = get_queried_object_id();
                    $meta = \ZuoAIPlus\Models\NavigationSite::getMeta($postId);
                    $name = $meta['name'] ?: get_the_title($postId);
                    $desc = $meta['description'] ?: $meta['ai_summary'] ?: '';
                    $logo = $meta['logo'] ?: '';
                    $url  = get_permalink($postId);
                    $cats = get_the_terms($postId, 'nav_category');
                    $firstCat = $cats && !is_wp_error($cats) ? $cats[0]->name : '';
                    printf(
                        "<meta name=\"description\" content=\"%s\">\n",
                        esc_attr(mb_substr($desc, 0, 120, 'utf-8'))
                    );
                    printf(
                        "<meta property=\"og:title\" content=\"%s\">\n" .
                        "<meta property=\"og:description\" content=\"%s\">\n" .
                        "<meta property=\"og:url\" content=\"%s\">\n" .
                        "<meta property=\"og:type\" content=\"article\">\n",
                        esc_attr($name),
                        esc_attr(mb_substr($desc, 0, 80, 'utf-8')),
                        esc_url($url)
                    );
                    if ($logo) {
                        printf("<meta property=\"og:image\" content=\"%s\">\n", esc_url($logo));
                        printf("<meta name=\"twitter:card\" content=\"summary_large_image\">\n");
                    }
                    if ($firstCat) {
                        printf("<meta property=\"article:section\" content=\"%s\">\n", esc_attr($firstCat));
                    }
                }, 2);
            }
        }
        // 导航分类页
        if (is_tax('nav_category')) {
            $t = AI_PLUS_PLUGIN_DIR . 'Templates/taxonomy-nav_category.php';
            if (file_exists($t)) {
                $template = $t; $isNavTemplate = true;
                add_filter('pre_get_document_title', function ($title) {
                    $term = get_queried_object();
                    $site = get_bloginfo('name');
                    return ($term ? $term->name : '分类') . ' - 网站收录 - ' . $site;
                });
            }
        }
        // 导航标签页
        if (is_tax('nav_tag')) {
            $t = AI_PLUS_PLUGIN_DIR . 'Templates/taxonomy-nav_tag.php';
            if (file_exists($t)) {
                $template = $t; $isNavTemplate = true;
                add_filter('pre_get_document_title', function ($title) {
                    $term = get_queried_object();
                    return ($term ? $term->name : '标签') . ' - 标签收录 - ' . get_bloginfo('name');
                });
            }
        }
        // 注册导航页面 CSS/JS 资源（外置文件，可浏览器缓存）
        if ($isNavTemplate) {
            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_style('zuo-nav-css', AI_PLUS_PLUGIN_URL . 'Assets/css/nav.v2.css', [], AI_PLUS_VERSION);
                wp_enqueue_script('zuo-nav-js', AI_PLUS_PLUGIN_URL . 'Assets/js/nav.js', [], AI_PLUS_VERSION, true);
                // 注入动态配置（替代模板中的 PHP 变量）
                $navConfig = [
                    'clickUrl'   => esc_url(rest_url('ai-plus/v1/nav/click')),
                    'restBase'   => esc_url(rest_url('wp/v2/nav-sites')),
                    'archiveUrl' => esc_url(get_post_type_archive_link('nav_site')),
                    'ratingUrl'  => '',
                    'rateUrl'    => '',
                    'weightUrl'  => '',
                    'postId'     => 0,
                    'siteDomain' => '',
                    'siteName'   => '',
                    'sitePermalink' => '',
                    'siteLogo'   => '',
                ];
                // 详情页额外数据
                if (is_singular('nav_site')) {
                    $postId = get_the_ID();
                    $meta = \ZuoAIPlus\Models\NavigationSite::getMeta($postId);
                    $url = $meta['url'] ?? '';
                    $domain = preg_replace('#https?://([^/]+).*#i', '$1', $url);
                    $navConfig['postId'] = $postId;
                    $navConfig['ratingUrl'] = esc_url(rest_url('ai-plus/v1/nav/rating?post_id=' . $postId . '&visitor_id='));
                    $navConfig['rateUrl'] = esc_url(rest_url('ai-plus/v1/nav/rate'));
                    $navConfig['weightUrl'] = esc_url(rest_url('ai-plus/v1/nav/weight'));
                    $navConfig['siteDomain'] = $domain;
                    $navConfig['siteName'] = $meta['name'] ?? get_the_title($postId);
                    $navConfig['sitePermalink'] = get_permalink($postId);
                    $navConfig['siteLogo'] = $meta['logo'] ?? '';
                }
                wp_localize_script('zuo-nav-js', 'zuoNav', $navConfig);
            });
        }
        return $template;
    });
}

// ── 插件激活(建表/建选项)─────────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    // 设置缓存清理定时任务(每天清理一次过期的AI缓存)
    if (!wp_next_scheduled('ai_plus_cleanup_cache')) {
        wp_schedule_event(time(), 'daily', 'ai_plus_cleanup_cache');
    }
    \ZuoAIPlus\Utils\Activator::activate();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// ── Gutenberg 区块注册 ─────────────────────────────────────────────────────
add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'ai-plus-blocks',
        AI_PLUS_PLUGIN_URL . 'Assets/js/blocks.js',
        ['wp-blocks', 'wp-editor', 'wp-element', 'wp-components', 'wp-compose', 'wp-api'],
        AI_PLUS_VERSION,
        true
    );
    wp_localize_script('ai-plus-blocks', 'aiPlusConfig', [
        'apiUrl' => rest_url('ai-plus/v1/'),
        'nonce'  => wp_create_nonce('wp_rest'),
    ]);
});

// ── 聊天区块前端渲染 ────────────────────────────────────────────────────────
add_filter('the_content', function ($content) {
    if (!is_singular() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post = get_post();
    if (!$post) {
        return $content;
    }

    $blocks = parse_blocks($post->post_content);
    $chat_html = '';

    // 文章正文(WordPress 已应用 the_content 过滤器,HTML 已处理)
    // 剥去所有 HTML 标签保留纯文本,限制长度防超限
    $articleText = wp_strip_all_tags($content);
    // esc_attr() 对超长字符串有内部缓冲区限制,截断到 4000 字符确保安全
    if (mb_strlen($articleText, 'utf-8') > 4000) {
        $articleText = mb_substr($articleText, 0, 4000, 'utf-8');
    }

    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') !== 'ai-plus/chat') {
            continue;
        }

        $title    = esc_html($block['attrs']['title'] ?? 'AI 助手');
        $model    = esc_attr($block['attrs']['model'] ?? 'minimax');
        $messages = $block['attrs']['messages'] ?? [];

        $chat_html .= '<div class="ai-plus-chat-rendered" data-model="' . $model . '" style="border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin:24px auto;background:#fff;max-width:700px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">';
        // 用隐藏 input 而非 data 属性:避免 HTML 转义破坏文章内容
        $chat_html .= '<input type="hidden" class="ai-article-context-val" value="' . esc_attr($articleText) . '">';
        $chat_html .= '<div style="font-weight:600;margin-bottom:16px;font-size:16px;color:#1a1a1a;border-bottom:2px solid #f0f0f0;padding-bottom:12px;">' . $title . '</div>';
        $chat_html .= '<div class="ai-plus-chat-messages" style="max-height:400px;overflow-y:auto;margin-bottom:16px;padding:12px;background:#f9fafb;border-radius:8px;">';

        if (empty($messages)) {
            $chat_html .= '<p style="color:#9ca3af;font-size:14px;text-align:center;padding:40px 0;">开始对话吧...</p>';
        } else {
            foreach ($messages as $m) {
                $role   = ($m['role'] ?? '') === 'user' ? 'user' : 'assistant';
                $text   = esc_html($m['content'] ?? '');
                $bg     = $role === 'user' ? '#e7f3ff' : '#f5f5f5';
                $border = $role === 'user' ? '#b3d7ff' : '#e0e0e0';
                $align  = $role === 'user' ? 'right' : 'left';
                $chat_html .= '<div style="margin-bottom:12px;padding:12px 16px;border-radius:12px;background:' . $bg . ';border:1px solid ' . $border . ';font-size:14px;line-height:1.6;box-shadow:0 1px 2px rgba(0,0,0,0.05);">' . nl2br($text) . '</div>';
            }
        }

        $chat_html .= '</div>';
        $chat_html .= '<div style="display:flex;gap:8px;align-items:flex-end;">';
        $chat_html .= '<textarea class="ai-plus-chat-input" rows="2" placeholder="输入消息... (Enter发送,Shift+Enter换行)" style="flex:1;resize:none;font-size:14px;padding:12px 16px;border:1px solid #dcdcde;border-radius:8px;box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);transition:border-color 0.2s;"></textarea>';
        $chat_html .= '<button class="ai-plus-chat-send" style="padding:12px 20px;background:#2271b1;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;white-space:nowrap;font-weight:500;box-shadow:0 2px 4px rgba(0,0,0,0.1);transition:all 0.2s;">发送</button>';
        $chat_html .= '<span class="ai-plus-chat-loading" style="display:none;color:#999;">⏳</span>';
        $chat_html .= '</div></div>';
    }

    return $content . $chat_html;
});

// ── 前端聊天交互脚本(已迁移到 Frontend_Init 统一管理)────────────────────
// 注意:浮窗和所有聊天功能现在统一由 src/Frontend/Frontend_Init.php 加载 frontend.js
// 这里不再重复注册,避免脚本重复加载或条件不一致

// ── 定时清理过期缓存 ─────────────────────────────────────────────────────────
add_action('ai_plus_cleanup_cache', function () {
    global $wpdb;

    // 只清理过期的 AI 缓存 transient（保留未过期的，避免重新生成）
    $timeoutPrefix = $wpdb->esc_like('_transient_timeout_ai_cache_');
    $expired = $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
        $timeoutPrefix . '%', time()
    ));
    if ($expired) {
        foreach ($expired as $timeoutKey) {
            $transientKey = str_replace('_transient_timeout_', '_transient_', $timeoutKey);
            delete_transient(str_replace('_transient_', '', $transientKey));
        }
    }

    // 清理 alloptions 缓存
    wp_cache_delete('alloptions', 'options');

    // 清理旧对话历史（超过30天的记录）
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ai_plus_chat WHERE created_at < %s", gmdate('Y-m-d H:i:s', strtotime('-30 days')))); // @phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AI Plus: Cache cleanup completed (' . count($expired) . ' expired entries removed)'); // @phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
});

// ── 插件停用时清理 ───────────────────────────────────────────────────────────
register_deactivation_hook(__FILE__, function () {
    // 移除定时任务
    wp_clear_scheduled_hook('ai_plus_cleanup_cache');

    // 清理所有缓存
    global $wpdb;
    $prefix = $wpdb->esc_like('_transient_ai_cache_') . '%';
    $timeout_prefix = $wpdb->esc_like('_transient_timeout_ai_cache_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix)); // @phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_prefix)); // @phpcs:ignore WordPress.DB.DirectDatabaseQuery

    wp_cache_delete('alloptions', 'options');
});
