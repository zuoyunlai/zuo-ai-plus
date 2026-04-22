<?php
/**
 * 使用 Chrome Headless 截取网站截图
 * 依赖服务器已安装 google-chrome
 */
if (!defined('ABSPATH')) exit;

function ai_plus_take_screenshot(string $url, int $postId = 0): array
{
    $url = esc_url_raw($url);
    if (!$url) {
        return ['success' => false, 'message' => 'URL无效'];
    }

    // 创建截图存放目录
    $upload_dir = wp_upload_dir();
    $screenshot_dir = $upload_dir['basedir'] . '/nav-screenshots';
    if (!file_exists($screenshot_dir)) {
        wp_mkdir_p($screenshot_dir);
    }

    // 生成文件名
    $hash = md5($url . ($postId ? $postId : time()));
    $filename = 'screenshot-' . $hash . '.png';
    $filepath = $screenshot_dir . '/' . $filename;
    $file_url = $upload_dir['baseurl'] . '/nav-screenshots/' . $filename;

    // Chrome 路径
    $chrome = '/usr/bin/google-chrome';
    if (!file_exists($chrome)) {
        $chrome = 'google-chrome';
    }

    // 临时文件（Chrome 输出到这个文件）
    $tmp_file = '/tmp/chrome-shot-' . $hash . '.png';
    $user_data_dir = '/tmp/chrome-ud-' . $hash;

    // 构建 Chrome 命令
    $cmd = sprintf(
        '%s --headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage ' .
        '--single-process --screenshot=%s --window-size=1280,800 ' .
        '--user-data-dir=%s %s 2>/dev/null',
        escapeshellcmd($chrome),
        escapeshellarg($tmp_file),
        escapeshellarg($user_data_dir),
        escapeshellarg($url)
    );

    $output = [];
    $return_code = 0;
    exec($cmd, $output, $return_code);

    // 清理 user-data-dir
    @exec('rm -rf ' . escapeshellarg($user_data_dir));

    if ($return_code !== 0 || !file_exists($tmp_file)) {
        return ['success' => false, 'message' => '截图失败（Chrome返回码: ' . $return_code . '）'];
    }

    // 移动到 WordPress 上传目录
    if (!rename($tmp_file, $filepath)) {
        @unlink($tmp_file);
        return ['success' => false, 'message' => '文件移动失败'];
    }

    // 注册到媒体库（生成 attachment）
    $att_id = wp_insert_attachment([
        'post_title'     => '网站截图 - ' . parse_url($url, PHP_URL_HOST),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ], $filepath);

    if (!is_wp_error($att_id)) {
        wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $filepath));
        $file_url = wp_get_attachment_url($att_id);
    }

    return [
        'success'       => true,
        'filepath'      => $filepath,
        'file_url'     => $file_url,
        'attachment_id' => is_wp_error($att_id) ? null : $att_id,
        'width'        => 1280,
        'height'       => 800,
    ];
}
