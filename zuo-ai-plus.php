<?php
/**
 * Plugin Name: Zuo AI Plus
 * Description: 集成智谱GLM、阿里通义、MiniMax、Kimi等国内大模型，支持文章生成、摘要摘要、图文生成、翻译、SEO优化、客服聊天等功能。
 * Version: 1.2.4
 * Author: 左运来
 * Author URI: https://www.yily.top?from=wp-plugin
 * License: GPLv2 or later
 * Text Domain: zuo-ai-plus
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */
if (!defined('ABSPATH')) exit;

// ── 常量（插件常量检查防止重复加载）─────────────────────────────────────────
if (!defined('AI_PLUS_VERSION')) {
    define('AI_PLUS_VERSION', '1.2.4');
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

// ── 类自动加载（所有类统一从此入口加载）────────────────────────────────────
require_once AI_PLUS_PLUGIN_DIR . 'src/Loader.php';

// ── REST API 路由注册（在 rest_api_init 时加载所有 Controller）──────────────
add_action('rest_api_init', function () {
    (new \ZuoAIPlus\Controllers\ContentController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\UtilityController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\ChatController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\ModelsController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\LicenseController())->registerRoutes();
    (new \ZuoAIPlus\Controllers\SeoController())->registerRoutes();
}, 5);

// ── 加载翻译文件 ───────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    load_plugin_textdomain('zuo-ai-plus', false, dirname(AI_PLUS_PLUGIN_BASENAME) . '/languages/');
});

// ── Admin / Frontend 初始化 ───────────────────────────────────────────────
add_action('plugins_loaded', function () {
    new \ZuoAIPlus\Admin\Admin_Init();
    new \ZuoAIPlus\Frontend\Frontend_Init();
});

// ── 插件激活（建表/建选项）─────────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    // 设置缓存清理定时任务（每天清理一次过期的AI缓存）
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

    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') !== 'ai-plus/chat') {
            continue;
        }

        $title    = esc_html($block['attrs']['title'] ?? 'AI 助手');
        $model    = esc_attr($block['attrs']['model'] ?? 'zhipu');
        $messages = $block['attrs']['messages'] ?? [];

        $chat_html .= '<div class="ai-plus-chat-rendered" data-model="' . $model . '" style="border:1px solid #dcdcde;border-radius:4px;padding:16px;margin:16px 0;background:#fff;max-width:700px;">';
        $chat_html .= '<div style="font-weight:bold;margin-bottom:12px;font-size:15px;">' . $title . '</div>';
        $chat_html .= '<div class="ai-plus-chat-messages" style="max-height:300px;overflow-y:auto;margin-bottom:12px;">';

        if (empty($messages)) {
            $chat_html .= '<p style="color:#999;font-size:13px;">开始对话吧...</p>';
        } else {
            foreach ($messages as $m) {
                $role   = ($m['role'] ?? '') === 'user' ? 'user' : 'assistant';
                $text   = esc_html($m['content'] ?? '');
                $bg     = $role === 'user' ? '#e7f3ff' : '#f5f5f5';
                $border = $role === 'user' ? '#b3d7ff' : '#e0e0e0';
                $align  = $role === 'user' ? 'right' : 'left';
                $chat_html .= '<div style="margin-bottom:8px;padding:8px 12px;border-radius:8px;background:' . $bg . ';border:1px solid ' . $border . ';font-size:14px;line-height:1.6;text-align:' . $align . ';">' . nl2br($text) . '</div>';
            }
        }

        $chat_html .= '</div>';
        $chat_html .= '<div style="display:flex;gap:8px;align-items:flex-end;">';
        $chat_html .= '<textarea class="ai-plus-chat-input" rows="2" placeholder="输入消息... (Enter发送，Shift+Enter换行)" style="flex:1;resize:none;font-size:14px;padding:8px;border:1px solid #dcdcde;border-radius:4px;"></textarea>';
        $chat_html .= '<button class="ai-plus-chat-send" style="padding:8px 16px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;white-space:nowrap;">发送</button>';
        $chat_html .= '<span class="ai-plus-chat-loading" style="display:none;color:#999;">⏳</span>';
        $chat_html .= '</div></div>';
    }

    return $content . $chat_html;
});

// ── 前端聊天交互脚本 ────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) {
        return;
    }
    wp_enqueue_script(
        'ai-plus-frontend-chat',
        AI_PLUS_PLUGIN_URL . 'Assets/js/frontend-chat.js',
        ['jquery'],
        AI_PLUS_VERSION,
        true
    );
    wp_localize_script('ai-plus-frontend-chat', 'aiPlusConfig', [
        'apiUrl' => rest_url('ai-plus/v1/'),
        'nonce'  => wp_create_nonce('wp_rest'),
    ]);
});

// ── 定时清理过期缓存 ─────────────────────────────────────────────────────────
add_action('ai_plus_cleanup_cache', function () {
    global $wpdb;
    
    // 清理过期的 transient（WordPress 的 set_transient 会在过期后自动删除，
    // 但这里定期清理可以优化数据库空间）
    $time = time();
    $prefix = $wpdb->esc_like('_transient_ai_cache_') . '%';
    $timeout_prefix = $wpdb->esc_like('_transient_timeout_ai_cache_') . '%';
    
    // 删除已过期的 transient timeout 记录
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
        $timeout_prefix,
        $time
    ));
    
    // 删除孤立的 transient 值（没有对应 timeout 的记录）
    $wpdb->query($wpdb->prepare(
        "DELETE o FROM {$wpdb->options} o 
         LEFT JOIN {$wpdb->options} t ON t.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
         WHERE o.option_name LIKE %s AND t.option_id IS NULL",
        $prefix
    ));
    
    // 清理 alloptions 缓存
    wp_cache_delete('alloptions', 'options');
    
    // DEBUG: 缓存清理完成（仅在调试模式记录）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AI Plus: Cache cleanup completed');
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
    
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_prefix));
    
    wp_cache_delete('alloptions', 'options');
});
