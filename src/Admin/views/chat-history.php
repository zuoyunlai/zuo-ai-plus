<?php
/**
 * Chat History View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 * @phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * @phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */
if (!defined('ABSPATH')) exit;

// Nonce verification
if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ai_plus_admin')) {
    wp_die('安全检查失败，请刷新页面后重试。');
}

global $wpdb;
$ai_table      = $wpdb->prefix . 'ai_plus_chat';
$ai_page       = max(1, intval($_GET['paged'] ?? 1));
$ai_per_page   = 20;
$ai_offset     = ($ai_page - 1) * $ai_per_page;
$ai_uid        = get_current_user_id();
$ai_cache_key  = "ai_plus_chat_total_uid_{$ai_uid}";
$ai_total      = wp_cache_get($ai_cache_key);
$ai_is_admin   = current_user_can('manage_options');

if ($ai_is_admin) {
    // 管理员：看全部用户的记录
    if (false === $ai_total) {
        // @phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
        $ai_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ai_table}"));
        wp_cache_set($ai_cache_key, $ai_total, '', HOUR_IN_SECONDS);
    }
    $ai_results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$ai_table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $ai_per_page, $ai_offset),
        ARRAY_A
    );
} else {
    // 普通用户/访客：只看自己的记录（user_id=0 为访客）
    if (false === $ai_total) {
        $ai_total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$ai_table} WHERE user_id = %d", $ai_uid)
        );
        wp_cache_set($ai_cache_key, $ai_total, '', HOUR_IN_SECONDS);
    }
    $ai_results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$ai_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $ai_uid, $ai_per_page, $ai_offset),
        ARRAY_A
    );
}
$ai_total_pages = ceil($ai_total / $ai_per_page);

/**
 * 从消息字段中解析出可显示的用户消息文本
 * 兼容：单层serialize数组、双层serialize、原始字符串
 *
 * @param mixed $msg_raw 原始消息字段
 * @return string 可显示的纯文本
 */
function ai_plus_extract_user_message($msg_raw) {
    // 尝试最多解两次（兼容双重序列化）
    $msg = $msg_raw;
    for ($i = 0; $i < 2; $i++) {
        $unserialized = maybe_unserialize($msg);
        if (!is_array($unserialized)) {
            break;
        }
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
    return is_string($msg) ? $msg : strval($msg);
}
?>

<div class="wrap">
    <h1>💬 Zuo AI Plus 对话历史</h1>

    <p>共 <?php echo (int) $ai_total; ?> 条记录</p>

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
            <?php if (empty($ai_results)): ?>
                <tr><td colspan="7">暂无记录</td></tr>
            <?php else: foreach ($ai_results as $ai_row): ?>
                <?php $ai_user_msg = ai_plus_extract_user_message($ai_row['message']); ?>
                <tr>
                    <td><?php echo (int) $ai_row['id']; ?></td>
                    <td title="<?php echo esc_attr((string)($ai_row['session_id'] ?? '')); ?>">
                        <?php echo esc_html(mb_substr((string)($ai_row['session_id'] ?? ''), 0, 24)); ?>
                    </td>
                    <td><?php echo esc_html($ai_row['model']); ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?php echo esc_attr($ai_user_msg); ?>">
                        <?php echo esc_html(mb_substr($ai_user_msg, 0, 50)); ?>
                    </td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html(mb_substr($ai_row['response'] ?? '', 0, 80)); ?>
                    </td>
                    <td><?php echo (int) $ai_row['tokens']; ?></td>
                    <td><?php echo esc_html($ai_row['created_at']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($ai_total_pages > 1): ?>
        <div class="tablenav" style="margin-top:16px;">
            <?php for ($ai_i = 1; $ai_i <= $ai_total_pages; $ai_i++): ?>
                <a class="button <?php echo $ai_i === $ai_page ? 'button-primary' : ''; ?>"
                   href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_chat_history&paged=' . intval($ai_i), 'ai_plus_admin')); ?>">
                    <?php echo (int) $ai_i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
