<?php
/**
 * 类自动加载器
 * PSR-4 风格自动加载 + 手动排序保证依赖
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */
if (!defined('ABSPATH')) exit;

$ai_base = __DIR__ . '/';

// ── 手动加载顺序敏感的基类（必须最先加载）─────────────────────────────────
require_once $ai_base . 'Models/BaseModel.php';
require_once $ai_base . 'Controllers/BaseController.php';

// ── 静态 classmap（无依赖顺序要求，替换 glob 扫描减少文件系统 IO）─────────
$files = [
    // Models
    'Models/Model_Init.php',
    'Models/ZhipuModel.php',
    'Models/TongyiModel.php',
    'Models/MiniMaxModel.php',
    'Models/KimiModel.php',
    'Models/DeepSeekModel.php',
    'Models/CustomModel.php',
    'Models/NavigationSite.php',
    'Models/SeoOptimizer.php',
    // Controllers
    'Controllers/ContentController.php',
    'Controllers/ChatController.php',
    'Controllers/ModelsController.php',
    'Controllers/LicenseController.php',
    'Controllers/NavigationController.php',
    'Controllers/SeoController.php',
    'Controllers/UtilityController.php',
    // Admin
    'Admin/Admin_Init.php',
    'Admin/Navigation_Init.php',
    // Utils
    'Utils/Activator.php',
    'Utils/AjaxHandler.php',
    'Utils/Constants.php',
    'Utils/Crypto.php',
    'Utils/ErrorCodes.php',
    'Utils/Logger.php',
    'Utils/MarkdownConverter.php',
    // Frontend
    'Frontend/Frontend_Init.php',
];

foreach ($files as $file) {
    require_once $ai_base . $file;
}
