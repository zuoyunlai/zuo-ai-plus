<?php
/**
 * Usage Statistics View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 * @phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * @phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$ai_table_chat     = $wpdb->prefix . 'ai_plus_chat';
$ai_table_history  = $wpdb->prefix . 'ai_plus_history';

// 获取WordPress时区设置
$ai_wp_timezone = wp_timezone();
$ai_today = wp_date('Y-m-d', null, $ai_wp_timezone);

// 总对话数（聊天+其他操作）
$ai_total_chats = wp_cache_get('ai_plus_stats_total_chats_v2');
if (false === $ai_total_chats) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_chat_count     = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ai_table_chat}"));
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_history_count  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ai_table_history}"));
    $ai_total_chats = $ai_chat_count + $ai_history_count;
    wp_cache_set('ai_plus_stats_total_chats_v2', $ai_total_chats, '', HOUR_IN_SECONDS);
}

// 总 Token
$ai_total_tokens = wp_cache_get('ai_plus_stats_total_tokens_v2');
if (false === $ai_total_tokens) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_chat_tokens     = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$ai_table_chat}"));
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_history_tokens  = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$ai_table_history}"));
    $ai_total_tokens = $ai_chat_tokens + $ai_history_tokens;
    wp_cache_set('ai_plus_stats_total_tokens_v2', $ai_total_tokens, '', HOUR_IN_SECONDS);
}

// 各模型使用分布
$ai_model_usage = wp_cache_get('ai_plus_stats_model_usage_v2');
if (false === $ai_model_usage) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_chat_models = $wpdb->get_results(
        $wpdb->prepare("SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$ai_table_chat} GROUP BY model"),
        ARRAY_A
    );
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_history_models = $wpdb->get_results(
        $wpdb->prepare("SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$ai_table_history} GROUP BY model"),
        ARRAY_A
    );

    // 合并数据
    $ai_merged = [];
    foreach ($ai_chat_models as $ai_row) {
        $ai_model = $ai_row['model'];
        $ai_merged[$ai_model] = [
            'model'  => $ai_model,
            'cnt'    => ($ai_merged[$ai_model]['cnt'] ?? 0) + $ai_row['cnt'],
            'tokens' => ($ai_merged[$ai_model]['tokens'] ?? 0) + $ai_row['tokens'],
        ];
    }
    foreach ($ai_history_models as $ai_row) {
        $ai_model = $ai_row['model'];
        $ai_merged[$ai_model] = [
            'model'  => $ai_model,
            'cnt'    => ($ai_merged[$ai_model]['cnt'] ?? 0) + $ai_row['cnt'],
            'tokens' => ($ai_merged[$ai_model]['tokens'] ?? 0) + $ai_row['tokens'],
        ];
    }
    $ai_model_usage = array_values($ai_merged);
    wp_cache_set('ai_plus_stats_model_usage_v2', $ai_model_usage, '', HOUR_IN_SECONDS);
}

// 今日操作数（聊天+历史记录）
$ai_today_chats = wp_cache_get('ai_plus_stats_today_chats_' . $ai_today);
if (false === $ai_today_chats) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_today_chat     = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$ai_table_chat} WHERE DATE(created_at) = %s", $ai_today)
    );
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_today_history  = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$ai_table_history} WHERE DATE(created_at) = %s", $ai_today)
    );
    $ai_today_chats = $ai_today_chat + $ai_today_history;
    wp_cache_set('ai_plus_stats_today_chats_' . $ai_today, $ai_today_chats, '', MINUTE_IN_SECONDS);
}

// 今日 Token
$ai_today_tokens = wp_cache_get('ai_plus_stats_today_tokens_' . $ai_today);
if (false === $ai_today_tokens) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_today_chat_tokens    = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$ai_table_chat} WHERE DATE(created_at) = %s", $ai_today)
    ) ?: 0);
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_today_history_tokens = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$ai_table_history} WHERE DATE(created_at) = %s", $ai_today)
    ) ?: 0);
    $ai_today_tokens = $ai_today_chat_tokens + $ai_today_history_tokens;
    wp_cache_set('ai_plus_stats_today_tokens_' . $ai_today, $ai_today_tokens, '', MINUTE_IN_SECONDS);
}

// 今日各类型操作统计
$ai_today_breakdown = wp_cache_get('ai_plus_stats_today_breakdown_' . $ai_today);
if (false === $ai_today_breakdown) {
    // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
    $ai_today_breakdown = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT action_type, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens
             FROM {$ai_table_history}
             WHERE DATE(created_at) = %s
             GROUP BY action_type
             ORDER BY cnt DESC",
            $ai_today
        ),
        ARRAY_A
    );
    wp_cache_set('ai_plus_stats_today_breakdown_' . $ai_today, $ai_today_breakdown, '', MINUTE_IN_SECONDS);
}

// 操作类型中文映射
$ai_action_labels = [
    'chat'             => 'AI对话',
    'generate'         => '文章生成',
    'expand'           => '文章扩写',
    'rewrite'          => '文章改写',
    'summarize'        => '摘要提取',
    'keyword'          => '关键词提取',
    'slug'             => '别名生成',
    'featured_image'   => '特色图生成',
    'image'            => '文生图',
    'seo_optimize'     => 'SEO优化',
    'translate'        => '翻译',
];
?>

<div class="wrap">
    <h1>📊 Zuo AI Plus 使用统计</h1>

    <div class="ai-stats-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_total_chats); ?></div>
            <div class="ai-stat-label">总操作数</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_total_tokens); ?></div>
            <div class="ai-stat-label">总 Token</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_today_chats); ?></div>
            <div class="ai-stat-label">今日操作</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($ai_today_tokens); ?></div>
            <div class="ai-stat-label">今日 Token</div>
        </div>
    </div>

    <h2>今日操作分布</h2>
    <table class="widefat">
        <thead><tr><th>操作类型</th><th>次数</th><th>Token</th></tr></thead>
        <tbody>
            <?php if (empty($ai_today_breakdown)): ?>
                <tr><td colspan="3">今日暂无数据</td></tr>
            <?php else: foreach ($ai_today_breakdown as $ai_row): ?>
                <tr>
                    <td><?php echo esc_html($ai_action_labels[$ai_row['action_type']] ?? $ai_row['action_type']); ?></td>
                    <td><?php echo (int) $ai_row['cnt']; ?></td>
                    <td><?php echo number_format((int) $ai_row['tokens']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h2 class="ai-stats-section-title">各模型使用分布</h2>
    <table class="widefat">
        <thead><tr><th>模型</th><th>使用次数</th><th>Token</th></tr></thead>
        <tbody>
            <?php if (empty($ai_model_usage)): ?>
                <tr><td colspan="3">暂无数据</td></tr>
            <?php else: foreach ($ai_model_usage as $ai_row): ?>
                <tr>
                    <td><?php echo esc_html($ai_row['model']); ?></td>
                    <td><?php echo (int) $ai_row['cnt']; ?></td>
                    <td><?php echo number_format((int) $ai_row['tokens']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<style>
.ai-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin: 24px 0;
}
.ai-stats-section-title {
    margin-top: 24px;
}
.ai-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}
.ai-stat-num {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 8px;
}
.ai-stat-label {
    font-size: 14px;
    color: #646970;
}

/* 移动端响应式 */
@media screen and (max-width: 782px) {
    .ai-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .ai-stat-card {
        padding: 16px 8px;
    }
    .ai-stat-num {
        font-size: 24px;
    }
    .ai-stat-label {
        font-size: 12px;
    }
    .ai-stats-section-title {
        font-size: 16px;
        margin-top: 20px;
    }
}
@media screen and (max-width: 480px) {
    .ai-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
