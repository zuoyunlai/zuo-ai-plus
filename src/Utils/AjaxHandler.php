<?php
/**
 * AJAX 处理器
 */
namespace ZuoAIPlus\Utils;

if (!defined("ABSPATH")) exit;

function require_login(): void {
    if (!\is_user_logged_in()) {
        \wp_send_json_error(['message' => '请先登录'], 401);
    }
}

// 生成并设置特色图
\add_action('wp_ajax_ai_plus_featured_image', function () {
    if (!\is_user_logged_in()) {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            wp_send_json_error(['message' => 'Unauthorized'], 401);
        }
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $image_prompt = isset($_POST['image_prompt']) ? sanitize_text_field(wp_unslash($_POST['image_prompt'])) : '';

    if (!$post_id) { \wp_send_json_error(['message' => '无法获取文章ID']); }
    if (empty($image_prompt)) { \wp_send_json_error(['message' => '图片描述不能为空']); }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $width = 1200;
    $height = 630;
    $img = \imagecreatetruecolor($width, $height);
    if (!$img) { \wp_send_json_error(['message' => '无法创建图片']); }

    $bg = \imagecolorallocate($img, 26, 42, 74);
    $tc = \imagecolorallocate($img, 255, 255, 255);
    $sc = \imagecolorallocate($img, 100, 181, 246);
    $lc = \imagecolorallocate($img, 100, 181, 246);
    \imagefill($img, 0, 0, $bg);
    \imageline($img, 60, intval($height / 2 - 60), $width - 60, intval($height / 2 - 60), $lc);
    \imageline($img, 60, intval($height / 2 + 80), $width - 60, intval($height / 2 + 80), $lc);

    $font_size = 5;
    $display_text = mb_substr($image_prompt, 0, 40);
    if (mb_strlen($image_prompt) > 40) { $display_text .= '...'; }

    $fontW = \imagefontwidth($font_size);
    $fontH = \imagefontheight($font_size);
    $text_len = mb_strlen($display_text) * $fontW;
    $x = intval(($width - $text_len) / 2);
    $y = intval(($height - $fontH) / 2);

    \imagestring($img, $font_size, max(0, $x), max(0, $y), $display_text, $tc);

    $subtitle = 'AI Generated Featured Image';
    $sub_len = mb_strlen($subtitle) * \imagefontwidth(3);
    \imagestring($img, 3, intval(($width - $sub_len) / 2), $height - 40, $subtitle, $sc);

    $tmp = \tempnam(\sys_get_temp_dir(), 'feat_');
    \imagejpeg($img, $tmp, 90);
    \imagedestroy($img);

    $filename = 'featured-' . $post_id . '-' . time() . '.jpg';

    $att_id = \media_handle_sideload([
        'name'     => $filename,
        'tmp_name' => $tmp,
    ], $post_id);

    if (\is_wp_error($att_id)) {
        \wp_delete_file($tmp);
        \wp_send_json_error(['message' => '保存图片失败: ' . $att_id->get_error_message()]);
    }

    \set_post_thumbnail($post_id, $att_id);

    $thumbnail_url = \wp_get_attachment_url($att_id);
    \wp_send_json_success([
        'attachment_id' => $att_id,
        'url'          => $thumbnail_url,
    ]);
});

// 保存文章内容
// 保存 License Key
add_action('wp_ajax_ai_plus_save_license_key', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }
    $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
    $server_url = isset($_POST['license_server_url']) ? sanitize_url(wp_unslash($_POST['license_server_url'])) : '';
    update_option('ai_plus_license_key', $key, true);
    update_option('ai_plus_license_server_url', $server_url, true);
    delete_transient('ai_plus_license_status');
    wp_send_json_success(['saved' => true, 'key' => $key]);
});

add_action('wp_ajax_ai_plus_save_content', function () {
    if (!\is_user_logged_in()) {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : (isset($_POST['rest_nonce']) ? sanitize_text_field(wp_unslash($_POST['rest_nonce'])) : '');
        if (!\wp_verify_nonce($nonce, 'wp_rest') && !\wp_verify_nonce($nonce, 'ai_plus_nonce')) {
            \wp_send_json_error(['message' => 'Unauthorized'], 401);
        }
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

    if (!$post_id) { \wp_send_json_error(['message' => '文章ID无效']); }

    // 移除 Gutenberg HTML 包裹标签
    $content = preg_replace('#<!-- wp:html -->\s*<div class="wp-block-html">(.*?)</div>\s*<!-- /wp:html -->#s', '$1', $content);

    \wp_update_post([
        'ID'           => $post_id,
        'post_content' => $content,
    ]);

    $html = apply_filters('ai_plus_the_content', $content);
    \wp_send_json_success([
        'post_id'    => $post_id,
        'content_len'=> mb_strlen($content),
        'html'       => $html,
    ]);
});

// 写入别名
\add_action('wp_ajax_ai_plus_save_slug', function () {
    if (!\is_user_logged_in()) { require_login(); }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';

    if (!$post_id) { \wp_send_json_error(['message' => '文章ID无效']); }

    \wp_update_post(['ID' => $post_id, 'post_name' => $slug]);
    \wp_send_json_success(['slug' => $slug]);
});

// 写入标签
\add_action('wp_ajax_ai_plus_save_tags', function () {
    if (!\is_user_logged_in()) { require_login(); }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked with isset, value is unslashed below
    $_tag_names = isset($_POST['tag_names']) ? wp_unslash($_POST['tag_names']) : null;
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked with isset, value is unslashed below
    $_tags      = isset($_POST['tags'])      ? wp_unslash($_POST['tags'])      : null;
    $raw = is_array($_tag_names) ? $_tag_names : (is_array($_tags) ? $_tags : []);
    $tags = is_array($raw) ? array_map('sanitize_text_field', $raw) : (is_string($raw) ? sanitize_text_field($raw) : []);

    if (!$post_id) { \wp_send_json_error(['message' => '文章ID无效']); }
    if (!is_array($tags)) { $tags = explode(',', $tags); }

    $tag_ids = [];
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (is_numeric($tag)) {
            $tag_ids[] = intval($tag);
        } else {
            $t = \wp_insert_term($tag, 'post_tag');
            if (!\is_wp_error($t)) { $tag_ids[] = $t['term_id']; }
        }
    }

    \wp_set_post_terms($post_id, $tag_ids, 'post_tag');
    $names = array_map(function ($id) { return \get_term($id)->name; }, $tag_ids);
    \wp_send_json_success(['tags' => $names]);
});
