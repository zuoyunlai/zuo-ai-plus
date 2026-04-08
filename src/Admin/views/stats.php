<?php
/**
 * Usage Statistics View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

// 总对话数
$ai_plus_total_chats = wp_cache_get('ai_plus_stats_total_chats');
if (false === $ai_plus_total_chats) {
    $ai_plus_total_chats = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_plus_chat_history");
    wp_cache_set('ai_plus_stats_total_chats', $ai_plus_total_chats, '', HOUR_IN_SECONDS);
}

// 总Token
$ai_plus_total_tokens = wp_cache_get('ai_plus_stats_total_tokens');
if (false === $ai_plus_total_tokens) {
    $ai_plus_total_tokens = (int) $wpdb->get_var("SELECT COALESCE(SUM(tokens),0) FROM {$wpdb->prefix}ai_plus_chat_history");
    wp_cache_set('ai_plus_stats_total_tokens', $ai_plus_total_tokens, '', HOUR_IN_SECONDS);
}

// 各模型使用量
$ai_plus_model_usage = $wpdb->get_results(
    "SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$wpdb->prefix}ai_plus_chat_history GROUP BY model",
    ARRAY_A
);

// 今日统计
$ai_plus_today = gmdate('Y-m-d');
$ai_plus_today_chats = wp_cache_get('ai_plus_stats_today_chats_' . $ai_plus_today);
if (false === $ai_plus_today_chats) {
    $ai_plus_today_chats = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ai_plus_chat_history WHERE DATE(created_at) = %s", $ai_plus_today)
    );
    wp_cache_set('ai_plus_stats_today_chats_' . $ai_plus_today, $ai_plus_today_chats, '', MINUTE_IN_SECONDS);
}
$ai_plus_today_tokens = wp_cache_get('ai_plus_stats_today_tokens_' . $ai_plus_today);
if (false === $ai_plus_today_tokens) {
    $ai_plus_today_tokens = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT SUM(tokens) FROM {$wpdb->prefix}ai_plus_chat_history WHERE DATE(created_at) = %s", $ai_plus_today)
    ) ?: 0);
    wp_cache_set('ai_plus_stats_today_tokens_' . $ai_plus_today, $ai_plus_today_tokens, '', MINUTE_IN_SECONDS);
}
?>

<div class="wrap">
    <h1>📊 AI Plus 使用统计</h1>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:24px 0;">
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_plus_total_chats) ?></div>
            <div class="ai-stat-label">总对话数</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_plus_total_tokens) ?></div>
            <div class="ai-stat-label">总 Token</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_plus_today_chats) ?></div>
            <div class="ai-stat-label">今日对话</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_plus_today_tokens) ?></div>
            <div class="ai-stat-label">今日 Token</div>
        </div>
    </div>

    <h2>各模型使用分布</h2>
    <table class="widefat" style="max-width:500px;">
        <thead><tr><th>模型</th><th>对话数</th><th>Token</th></tr></thead>
        <tbody>
            <?php foreach ($ai_plus_model_usage as $ai_plus_row): ?>
                <tr>
                    <td><?php echo esc_html($ai_plus_row['model']) ?></td>
                    <td><?php echo (int) $ai_plus_row['cnt']; ?></td>
                    <td><?php echo number_format((int) $ai_plus_row['tokens']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.ai-stat-card {
    background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;
}
.ai-stat-num {font-size:28px;font-weight:bold;color:#2271b1;}
.ai-stat-label {color:#666;margin-top:4px;}
</style>
