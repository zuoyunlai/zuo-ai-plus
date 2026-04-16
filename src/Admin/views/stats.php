<?php
/**
 * Usage Statistics View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_chat = $wpdb->prefix . 'ai_plus_chat';
$table_history = $wpdb->prefix . 'ai_plus_history';

// 获取WordPress时区设置
$wp_timezone = wp_timezone();
$today = wp_date('Y-m-d', null, $wp_timezone);

// 总对话数（聊天+其他操作）
$total_chats = wp_cache_get('ai_plus_stats_total_chats_v2');
if (false === $total_chats) {
    $chat_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_chat}");
    $history_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_history}");
    $total_chats = $chat_count + $history_count;
    wp_cache_set('ai_plus_stats_total_chats_v2', $total_chats, '', HOUR_IN_SECONDS);
}

// 总 Token
$total_tokens = wp_cache_get('ai_plus_stats_total_tokens_v2');
if (false === $total_tokens) {
    $chat_tokens = (int) $wpdb->get_var("SELECT COALESCE(SUM(tokens),0) FROM {$table_chat}");
    $history_tokens = (int) $wpdb->get_var("SELECT COALESCE(SUM(tokens),0) FROM {$table_history}");
    $total_tokens = $chat_tokens + $history_tokens;
    wp_cache_set('ai_plus_stats_total_tokens_v2', $total_tokens, '', HOUR_IN_SECONDS);
}

// 各模型使用分布
$model_usage = wp_cache_get('ai_plus_stats_model_usage_v2');
if (false === $model_usage) {
    // 合并聊天和历史记录的模型使用数据
    $chat_models = $wpdb->get_results(
        "SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$table_chat} GROUP BY model",
        ARRAY_A
    );
    $history_models = $wpdb->get_results(
        "SELECT model, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens FROM {$table_history} GROUP BY model",
        ARRAY_A
    );
    
    // 合并数据
    $merged = [];
    foreach ($chat_models as $row) {
        $model = $row['model'];
        $merged[$model] = [
            'model' => $model,
            'cnt' => ($merged[$model]['cnt'] ?? 0) + $row['cnt'],
            'tokens' => ($merged[$model]['tokens'] ?? 0) + $row['tokens'],
        ];
    }
    foreach ($history_models as $row) {
        $model = $row['model'];
        $merged[$model] = [
            'model' => $model,
            'cnt' => ($merged[$model]['cnt'] ?? 0) + $row['cnt'],
            'tokens' => ($merged[$model]['tokens'] ?? 0) + $row['tokens'],
        ];
    }
    $model_usage = array_values($merged);
    wp_cache_set('ai_plus_stats_model_usage_v2', $model_usage, '', HOUR_IN_SECONDS);
}

// 今日操作数（聊天+历史记录）
$today_chats = wp_cache_get('ai_plus_stats_today_chats_' . $today);
if (false === $today_chats) {
    $today_chat = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table_chat} WHERE DATE(created_at) = %s", $today)
    );
    $today_history = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table_history} WHERE DATE(created_at) = %s", $today)
    );
    $today_chats = $today_chat + $today_history;
    wp_cache_set('ai_plus_stats_today_chats_' . $today, $today_chats, '', MINUTE_IN_SECONDS);
}

// 今日 Token
$today_tokens = wp_cache_get('ai_plus_stats_today_tokens_' . $today);
if (false === $today_tokens) {
    $today_chat_tokens = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$table_chat} WHERE DATE(created_at) = %s", $today)
    ) ?: 0);
    $today_history_tokens = (int) ($wpdb->get_var(
        $wpdb->prepare("SELECT COALESCE(SUM(tokens),0) FROM {$table_history} WHERE DATE(created_at) = %s", $today)
    ) ?: 0);
    $today_tokens = $today_chat_tokens + $today_history_tokens;
    wp_cache_set('ai_plus_stats_today_tokens_' . $today, $today_tokens, '', MINUTE_IN_SECONDS);
}

// 今日各类型操作统计
$today_breakdown = wp_cache_get('ai_plus_stats_today_breakdown_' . $today);
if (false === $today_breakdown) {
    $today_breakdown = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT action_type, COUNT(*) as cnt, SUM(COALESCE(tokens,0)) as tokens 
             FROM {$table_history} 
             WHERE DATE(created_at) = %s 
             GROUP BY action_type 
             ORDER BY cnt DESC",
            $today
        ),
        ARRAY_A
    );
    wp_cache_set('ai_plus_stats_today_breakdown_' . $today, $today_breakdown, '', MINUTE_IN_SECONDS);
}

// 操作类型中文映射
$action_labels = [
    'chat' => 'AI对话',
    'generate' => '文章生成',
    'expand' => '文章扩写',
    'rewrite' => '文章改写',
    'summarize' => '摘要提取',
    'keyword' => '关键词提取',
    'slug' => '别名生成',
    'featured_image' => '特色图生成',
    'image' => '文生图',
    'seo_optimize' => 'SEO优化',
    'translate' => '翻译',
];
?>

<div class="wrap">
    <h1>📊 Zuo AI Plus 使用统计</h1>

    <div class="ai-stats-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($total_chats); ?></div>
            <div class="ai-stat-label">总操作数</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($total_tokens); ?></div>
            <div class="ai-stat-label">总 Token</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($today_chats); ?></div>
            <div class="ai-stat-label">今日操作</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-num"><?php echo number_format($today_tokens); ?></div>
            <div class="ai-stat-label">今日 Token</div>
        </div>
    </div>

    <h2>今日操作分布</h2>
    <table class="widefat">
        <thead><tr><th>操作类型</th><th>次数</th><th>Token</th></tr></thead>
        <tbody>
            <?php if (empty($today_breakdown)): ?>
                <tr><td colspan="3">今日暂无数据</td></tr>
            <?php else: foreach ($today_breakdown as $row): ?>
                <tr>
                    <td><?php echo esc_html($action_labels[$row['action_type']] ?? $row['action_type']); ?></td>
                    <td><?php echo (int) $row['cnt']; ?></td>
                    <td><?php echo number_format((int) $row['tokens']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h2 class="ai-stats-section-title">各模型使用分布</h2>
    <table class="widefat">
        <thead><tr><th>模型</th><th>使用次数</th><th>Token</th></tr></thead>
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
