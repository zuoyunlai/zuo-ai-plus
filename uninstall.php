<?php
/**
 * Zuo AI Plus 卸载清理脚本
 * 当用户在 WordPress 后台删除插件时自动触发
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// ── 清理 Options ────────────────────────────────────────────────────────────
$optionPatterns = [
    'zuo_ai_plus_',
    'ai_plus_',
    'zuo_ai_db_version',
];

foreach ($optionPatterns as $pattern) {
    $allOptions = get_option_all();
    foreach ($allOptions as $key => $value) {
        if (strpos($key, $pattern) === 0) {
            delete_option($key);
        }
    }
}

// ── 清理数据表 ─────────────────────────────────────────────────────────────
global $wpdb;

$tables = [
    $wpdb->prefix . 'ai_plus_chat',
    $wpdb->prefix . 'ai_plus_history',
    $wpdb->prefix . 'ai_plus_cache',
    $wpdb->prefix . 'ai_plus_templates',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// ── 清理 Transients ─────────────────────────────────────────────────────────
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_ai_cache_%',
        '_transient_timeout_ai_cache_%'
    )
);

// ── 清理定时任务 ─────────────────────────────────────────────────────────────
wp_clear_scheduled_hook('ai_plus_cleanup_cache');
wp_clear_scheduled_hook('ai_plus_stats_cron');
wp_clear_scheduled_hook('ai_plus_nav_log_cleanup');

// ── 清理导航相关 Transients ──────────────────────────────────────────────
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_plus_nav_%' OR option_name LIKE '_transient_timeout_ai_plus_nav_%'"
);

// ── 清理导航相关 Post Meta（可选，保留数据）──────────────────────────────
// 如需完全清除导航数据，取消以下注释：
// $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('nav_views','nav_view_log','nav_clicks','nav_click_log','nav_ratings','nav_url','nav_name','nav_logo','nav_description','nav_keywords','nav_screenshot','nav_ai_summary','nav_status')");

delete_option('ai_plus_uninstall_done');
