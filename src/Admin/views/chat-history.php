<?php
/**
 * Chat History View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

// Nonce verification
if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ai_plus_chat')) {
    wp_die('Security check failed.');
}

global $wpdb;
$table         = $wpdb->prefix . 'ai_plus_chat';
$page          = max(1, intval($_GET['paged'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;
$cache_key    = "ai_plus_chat_total";
$total         = wp_cache_get($cache_key);

if (false === $total) {
    $total = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table}")
    );
    wp_cache_set($cache_key, $total, '', HOUR_IN_SECONDS);
}

$results     = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset),
    ARRAY_A
);
$total_pages = ceil($total / $per_page);
?>

<div class="wrap">
    <h1>💬 Zuo AI Plus 对话历史</h1>

    <p>共 <?php echo (int) $total; ?> 条记录</p>

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
            <?php if (empty($results)): ?>
                <tr><td colspan="7">暂无记录</td></tr>
            <?php else: foreach ($results as $row): ?>
                <?php $msg = maybe_unserialize($row['message']); ?>
                <tr>
                    <td><?php echo (int) $row['id']; ?></td>
                    <td><?php echo esc_html(substr($row['session_id'], 0, 16)); ?>…</td>
                    <td><?php echo esc_html($row['model']); ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html(mb_substr(is_array($msg) ? ($msg['content'] ?? $row['message']) : $row['message'], 0, 50)); ?>
                    </td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html(mb_substr($row['response'] ?? '', 0, 80)); ?>
                    </td>
                    <td><?php echo (int) $row['tokens']; ?></td>
                    <td><?php echo esc_html($row['created_at']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav" style="margin-top:16px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a class="button <?php echo $i === $page ? 'button-primary' : ''; ?>"
                   href="<?php echo esc_url(wp_nonce_url('?page=ai-plus-chat&paged=' . $i, 'ai_plus_chat')); ?>">
                    <?php echo (int) $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
