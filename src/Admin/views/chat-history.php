<?php
/**
 * Chat History View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

// Nonce verification
if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ai_plus_chat_history')) {
    wp_die('Security check failed.');
}

global $wpdb;
$ai_plus_table      = $wpdb->prefix . 'ai_plus_chat';
$ai_plus_page       = max(1, intval($_GET['paged'] ?? 1));
$ai_plus_per_page   = 20;
$ai_plus_offset     = ($ai_plus_page - 1) * $ai_plus_per_page;

$ai_plus_cache_key  = "ai_plus_chat_total";
$ai_plus_total      = wp_cache_get($ai_plus_cache_key);
if (false === $ai_plus_total) {
    $ai_plus_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_plus_chat_history");
    wp_cache_set($ai_plus_cache_key, $ai_plus_total, '', HOUR_IN_SECONDS);
}

$ai_plus_results    = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ai_plus_chat_history ORDER BY created_at DESC LIMIT %d OFFSET %d", $ai_plus_per_page, $ai_plus_offset),
    ARRAY_A
);
$ai_plus_total_pages = ceil($ai_plus_total / $ai_plus_per_page);
?>

<div class="wrap">
    <h1>💬 AI 对话历史</h1>

    <p>共 <?php echo (int) $ai_plus_total; ?> 条记录</p>

    <table class="widefat" style="margin-top:16px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>会话ID</th>
                <th>模型</th>
                <th>用户消息</th>
                <th>AI回复</th>
                <th>Token</th>
                <th>时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ai_plus_results)): ?>
                <tr><td colspan="7">暂无记录</td></tr>
            <?php else: foreach ($ai_plus_results as $ai_plus_row): ?>
                <tr>
                    <td><?php echo (int) $ai_plus_row['id']; ?></td>
                    <td><?php echo esc_html(substr($ai_plus_row['session_id'], 0, 16)) ?>...</td>
                    <td><?php echo esc_html($ai_plus_row['model']) ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html(substr(maybe_unserialize($ai_plus_row['message'])['content'] ?? $ai_plus_row['message'], 0, 50)) ?>
                    </td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html(substr($ai_plus_row['response'], 0, 80)) ?>
                    </td>
                    <td><?php echo (int) $ai_plus_row['tokens']; ?></td>
                    <td><?php echo esc_html($ai_plus_row['created_at']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($ai_plus_total_pages > 1): ?>
        <div class="tablenav" style="margin-top:16px;">
            <?php for ($ai_plus_i = 1; $ai_plus_i <= $ai_plus_total_pages; $ai_plus_i++): ?>
                <a class="button <?php echo $ai_plus_i === (int)$ai_plus_page ? 'button-primary' : ''; ?>"
                   href="<?php echo esc_url(wp_nonce_url('?page=ai-plus-chat&paged=' . $ai_plus_i, 'ai_plus_chat_history')); ?>"><?php echo (int) $ai_plus_i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
