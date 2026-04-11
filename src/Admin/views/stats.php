<?php
/**
 * Usage Statistics View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'ai_plus_chat';
$today = gmdate('Y-m-d');

// 总对话数（缓存 1 小时）
$total_chats = wp_cache_get('ai_plus_stats_total_chats');
if (false === $total_chats) {
    $total_chats = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table}")
    );
    wp_cache_set('ai_plus_stats_total_chats', $total_chats, '', HOUR_IN_SECONDS);
}

// 总 Token（缓存 1 小时）
$total_tokens = wp_cache_get('ai_plus_stats_total_tokens');
if (false === $total_tokens) {
    $total_tokens = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$table}")
    );
    wp_cache_set('ai_plus_stats_total_tokens', $total_tokens, '', HOUR_IN_SECONDS);
}

// 各模型使用分布（缓存 1 小时）
$model_usage = wp_cache_get('ai_plus_stats_model_usage');
if (false === $model_usage) {
    $model_usage = $wpdb->get_results(
        "SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$table} GROUP BY model",
        ARRAY_A
    );
    wp_cache_set('ai_plus_stats_model_usage', $model_usage, '', HOUR_IN_SECONDS);
}

// 今日对话数（缓存 1 分钟）
$today_chats = wp_cache_get('ai_plus_stats_today_chats_' . $today);
if (false === $today_chats) {
    $today_chats = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s", $today)
    );
    wp_cache_set('ai_plus_stats_today_chats_' . $today, $today_chats, '', MINUTE_IN_SECONDS);
}

// 今日 Token（缓存 1 分钟）
$today_tokens = wp_cache_get('ai_plus_stats_today_tokens_' . $today);
if (false === $today_tokens) {
    $today_tokens = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$table} WHERE DATE(created_at) = %s", $today)
    ) ?: 0);
    wp_cache_set('ai_plus_stats_today_tokens_' . $today, $today_tokens, '', MINUTE_IN_SECONDS);
}
?>

<div class="wrap">
    <h1>📊 Zuo AI Plus 使用统计</h1>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:24px 0;">
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($total_chats); ?></div>
            <div class="ai-stat-label">总对话数</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($total_tokens); ?></div>
            <div class="ai-stat-label">总 Token</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($today_chats); ?></div>
            <div class="ai-stat-label">今日对话</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($today_tokens); ?></div>
            <div class="ai-stat-label">今日 Token</div>
        </div>
    </div>

    <h2>各模型使用分布</h2>
    <table class="widefat">
        <thead><tr><th>模型</th><th>对话数</th><th>Token</th></tr></thead>
        <tbody>
            <?php if (empty($model_usage)): ?>
                <tr><td colspan="3">暂无数据</td></tr>
            <?php else: foreach ($model_usage as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['model']); ?></td>
                    <td><?php echo (int) $row['cnt']; ?></td>
                    <td><?php echo number_format((int) $row['tokens']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
