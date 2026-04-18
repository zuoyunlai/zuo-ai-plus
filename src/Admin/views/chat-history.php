<?php
/**
 * Chat History View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

// Nonce verification
if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ai_plus_admin')) {
    wp_die('安全检查失败，请刷新页面后重试。');
}

global $wpdb;
$table         = $wpdb->prefix . 'ai_plus_chat';
$page          = max(1, intval($_GET['paged'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;
$current_uid   = get_current_user_id();
$cache_key     = "ai_plus_chat_total_uid_{$current_uid}";
$total         = wp_cache_get($cache_key);
$is_admin      = current_user_can('manage_options');

if ($is_admin) {
    // 管理员：看全部用户的记录
    if (false === $total) {
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        wp_cache_set($cache_key, $total, '', HOUR_IN_SECONDS);
    }
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset),
        ARRAY_A
    );
} else {
    // 普通用户/访客：只看自己的记录（user_id=0 为访客）
    if (false === $total) {
        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $current_uid)
        );
        wp_cache_set($cache_key, $total, '', HOUR_IN_SECONDS);
    }
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $current_uid, $per_page, $offset),
        ARRAY_A
    );
}
$total_pages = ceil($total / $per_page);

/**
 * 从消息字段中解析出可显示的用户消息文本
 * 兼容：单层serialize数组、双层serialize、原始字符串
 */
function extract_user_message($msg_raw) {
    // 尝试最多解两次（兼容双重序列化）
    $msg = $msg_raw;
    for ($i = 0; $i < 2; $i++) {
        $unserialized = maybe_unserialize($msg);
        if (!is_array($unserialized)) break;
        $msg = $unserialized;
    }

    // 现在 $msg 应该是最终数组
    if (is_array($msg)) {
        // 结构1: [{role:'user',content:'xxx'}, ...] — 标准对话格式
        foreach ($msg as $m) {
            if (is_array($m) && ($m['role'] ?? '') === 'user') {
                return $m['content'] ?? '';
            }
        }
        // 结构2: {content: 'xxx'} 或 [['content'=>'xxx']]
        $first = $msg[0] ?? $msg;
        if (is_array($first)) {
            return $first['content'] ?? ($first[0]['content'] ?? '');
        }
        return $msg['content'] ?? reset($msg);
    }

    // 不是数组，直接返回原文
    return is_string($msg) ? $msg : print_r($msg, true);
}
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
                <?php $user_msg = extract_user_message($row['message']); ?>
                <tr>
                    <td><?php echo (int) $row['id']; ?></td>
                    <td title="<?php echo esc_attr((string)($row['session_id'] ?? '')); ?>">
                        <?php echo esc_html(mb_substr((string)($row['session_id'] ?? ''), 0, 24)); ?>
                    </td>
                    <td><?php echo esc_html($row['model']); ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?php echo esc_attr($user_msg); ?>">
                        <?php echo esc_html(mb_substr($user_msg, 0, 50)); ?>
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
                   href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_chat_history&paged=' . $i, 'ai_plus_admin')); ?>">
                    <?php echo (int) $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
