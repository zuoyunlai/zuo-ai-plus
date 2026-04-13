<?php
/**
 * Plugin Name: Zuo AI Plus

 * Description: 集成智谱GLM、阿里通义、MiniMax、Kimi等国内大模型，支持文章生成、摘要摘要、图文生成、翻译、SEO优化、客服聊天等功能。
 * Version: 1.2.2
 * Author: 左运来
 * Author URI: https://www.yily.top?from=wp-plugin
 * License:     GPLv2 or later
 * Text Domain: zuo-ai-plus
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */
if (!defined('ABSPATH')) exit;

// ================== 常量（防止重复加载时重定义）====================
if (!defined('AI_PLUS_VERSION')) define('AI_PLUS_VERSION', '1.2.0');
if (!defined('AI_PLUS_PLUGIN_DIR')) define('AI_PLUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('AI_PLUS_PLUGIN_URL')) define('AI_PLUS_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('AI_PLUS_PLUGIN_BASENAME')) define('AI_PLUS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// ================== 自动加载 ==================
require_once AI_PLUS_PLUGIN_DIR . 'src/Loader.php';

// ================== 核心类初始化 ==================
add_action('plugins_loaded', function () {
    // 国际化由 WordPress 4.6+ 自动加载，无需手动调用 load_plugin_textdomain()

    // 加载 AJAX 处理器
    require_once AI_PLUS_PLUGIN_DIR . 'src/Utils/AjaxHandler.php';

    // 初始化各模块
    new \ZuoAIPlus\Admin\Admin_Init();
    new \ZuoAIPlus\Models\Model_Init();
    new \ZuoAIPlus\Frontend\Frontend_Init();
});


// ================== License 校验（每天一次）====================
add_action('plugins_loaded', function () {
    $key = get_option('ai_plus_license_key', '');
    if (empty($key)) return; // 未设置 License，不拦截（演示模式）

    // 每天校验一次（用 transient 缓存）
    $cached = get_transient('ai_plus_license_status');
    if ($cached !== false) {
        if ($cached === 'invalid' || $cached === 'expired') {
            add_action('admin_notices', function () {
                $msg = $cached === 'expired'
                    ? '<strong>Zuo AI Plus 授权已过期</strong>，请联系 17854779@qq.com 续费。'
                    : '<strong>Zuo AI Plus License 无效</strong>，请在「授权管理」页面重新激活。';
                echo '<div class="notice notice-error"><p>⚠️ ' . wp_kses_post($msg) . ' <a href="?page=ai_plus_license">去激活 →</a></p></div>';
            });
        }
        return;
    }

    // 发起校验请求
    $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $response = wp_remote_get(
        'https://www.yily.top/licenses/api.php?action=verify&key=' . urlencode($key) . '&domain=' . urlencode($domain),
        ['timeout' => 10, 'sslverify' => true]
    );

    if (is_wp_error($response)) {
        // 网络异常，不拦截（容错）
        set_transient('ai_plus_license_status', 'network_error', HOUR_IN_SECONDS);
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status = $body['status'] ?? 'unknown';

    if ($status === 'valid') {
        set_transient('ai_plus_license_status', 'valid', DAY_IN_SECONDS);
    } else {
        $cache_time = ($status === 'expired') ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
        set_transient('ai_plus_license_status', $status, $cache_time);

        add_action('admin_notices', function () use ($status) {
            if ($status === 'expired') {
                echo '<div class="notice notice-error is-dismissible"><p>⚠️ <strong>Zuo AI Plus 授权已过期</strong>，请联系 17854779@qq.com 续费。 <a href="?page=ai_plus_license">重新激活 →</a></p></div>';
            } elseif ($status === 'domain_mismatch') {
                echo '<div class="notice notice-error is-dismissible"><p>⚠️ <strong>Zuo AI Plus License 域名不匹配</strong>，请在「授权管理」页面重新激活。 <a href="?page=ai_plus_license">查看 →</a></p></div>';
            } elseif ($status === 'invalid') {
                echo '<div class="notice notice-error is-dismissible"><p>⚠️ <strong>Zuo AI Plus License 无效</strong>，请购买正版授权：17854779@qq.com <a href="?page=ai_plus_license">激活 →</a></p></div>';
            }
        });
    }
}, 5);


// ================== 激活/停用钩子 ==================
// 激活时手动加载所有类文件（不依赖 autoloader）
register_activation_hook(__FILE__, function () {
    $base = AI_PLUS_PLUGIN_DIR . 'src/';
    require_once $base . 'Models/BaseModel.php';
    require_once $base . 'Models/ZhipuModel.php';
    require_once $base . 'Models/TongyiModel.php';
    require_once $base . 'Models/MiniMaxModel.php';
    require_once $base . 'Models/KimiModel.php';
    require_once $base . 'Models/SeoOptimizer.php';
    require_once $base . 'Utils/Activator.php';
    \ZuoAIPlus\Utils\Activator::activate();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// Gutenberg Blocks 注册
add_action('enqueue_block_editor_assets', function () {
    $base = plugin_dir_url(__FILE__);
    wp_enqueue_script(
        'ai-plus-blocks',
        $base . 'Assets/js/blocks.js',
        ['wp-blocks', 'wp-editor', 'wp-element', 'wp-components', 'wp-compose', 'wp-api'],
        AI_PLUS_VERSION,
        true
    );
    wp_localize_script('ai-plus-blocks', 'aiPlusConfig', [
        'apiUrl' => rest_url('ai-plus/v1/'),
        'nonce'  => wp_create_nonce('wp_rest'),
    ]);
});

// 聊天区块服务端渲染（仅前端页面，不在 REST API 请求中运行）
add_action('init', function () {
    // 确保 REST API 初始化
}, 20);

// 聊天区块前端渲染
add_filter('the_content', function ($content) {
    if (!is_singular() || !in_the_loop() || !is_main_query()) return $content;

    $post = get_post();
    if (!$post) return $content;

    $blocks = parse_blocks($post->post_content);
    $chat_html = '';

    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') !== 'ai-plus/chat') continue;

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
                $role    = ($m['role'] ?? '') === 'user' ? 'user' : 'assistant';
                $text    = esc_html($m['content'] ?? '');
                $bg      = $role === 'user' ? '#e7f3ff' : '#f5f5f5';
                $border  = $role === 'user' ? '#b3d7ff' : '#e0e0e0';
                $align   = $role === 'user' ? 'right' : 'left';
                $chat_html .= '<div style="margin-bottom:8px;padding:8px 12px;border-radius:8px;background:' . $bg . ';border:1px solid ' . $border . ';font-size:14px;line-height:1.6;text-align:' . $align . ';">' . nl2br($text) . '</div>';
            }
        }

        $chat_html .= '</div>';
        // 交互输入区
        $chat_html .= '<div style="display:flex;gap:8px;align-items:flex-end;">';
        $chat_html .= '<textarea class="ai-plus-chat-input" rows="2" placeholder="输入消息... (Enter发送，Shift+Enter换行)" style="flex:1;resize:none;font-size:14px;padding:8px;border:1px solid #dcdcde;border-radius:4px;"></textarea>';
        $chat_html .= '<button class="ai-plus-chat-send" style="padding:8px 16px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;white-space:nowrap;">发送</button>';
        $chat_html .= '<span class="ai-plus-chat-loading" style="display:none;color:#999;">⏳</span>';
        $chat_html .= '</div></div>';
    }

    return $content . $chat_html;
});

// 前端聊天交互脚本
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return;
    wp_enqueue_script(
        'ai-plus-frontend-chat',
        plugin_dir_url(__FILE__) . 'Assets/js/frontend-chat.js',
        ['jquery'],
        AI_PLUS_VERSION,
        true
    );
    wp_localize_script('ai-plus-frontend-chat', 'aiPlusConfig', [
        'apiUrl' => rest_url('ai-plus/v1/'),
        'nonce'  => wp_create_nonce('wp_rest'),
    ]);
});
