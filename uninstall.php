<?php
/**
 * Zuo AI Plus 卸载清理脚本
 * 当用户在 WordPress 后台删除插件时自动触发
 */
if (!defined('ABSPATH')) {
    // 如果直接访问，尝试加载 WordPress
    $wpLoadPaths = [
        __DIR__ . '/../../../wp-load.php',
        dirname(__DIR__, 4) . '/wp-load.php',
    ];
    $loaded = false;
    foreach ($wpLoadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        exit('WordPress not found');
    }
}

if (!current_user_can('manage_options')) {
    exit('Insufficient permissions');
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

delete_option('ai_plus_uninstall_done');
