<?php
/**
 * 类自动加载器
 * PSR-4 风格自动加载 + 手动排序保证依赖
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */
if (!defined('ABSPATH')) exit;

$ai_base = __DIR__ . '/';

// ── 手动加载顺序敏感的基类 ────────────────────────────────────────────────
require_once $ai_base . 'Models/BaseModel.php';
require_once $ai_base . 'Controllers/BaseController.php';

// ── 自动扫描加载其余类（无依赖顺序要求）────────────────────────────────────
$dirs = ['Models', 'Controllers', 'Admin', 'Utils', 'Frontend'];

foreach ($dirs as $dir) {
    $dirPath = $ai_base . $dir;
    if (!is_dir($dirPath)) continue;
    
    $files = glob($dirPath . '/*.php');
    if (!$files) continue;
    
    foreach ($files as $file) {
        $basename = basename($file);
        // 跳过已手动加载的基类
        if ($basename === 'BaseModel.php' || $basename === 'BaseController.php') {
            continue;
        }
        require_once $file;
    }
}
