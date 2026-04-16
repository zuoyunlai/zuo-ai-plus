<?php
/**
 * 类自动加载器
 * 按正确顺序加载所有类文件，不依赖 composer autoload
 */
if (!defined('ABSPATH')) exit;

$base = __DIR__ . '/';

// ── Models ──────────────────────────────────────────────────────────────────
require_once $base . 'Models/BaseModel.php';
require_once $base . 'Models/ZhipuModel.php';
require_once $base . 'Models/TongyiModel.php';
require_once $base . 'Models/MiniMaxModel.php';
require_once $base . 'Models/KimiModel.php';
require_once $base . 'Models/DeepSeekModel.php';
require_once $base . 'Models/CustomModel.php';
require_once $base . 'Models/SeoOptimizer.php';
require_once $base . 'Models/Model_Init.php';

// ── Controllers（基类先于子类）────────────────────────────────────────────────
require_once $base . 'Controllers/BaseController.php';
require_once $base . 'Controllers/ContentController.php';
require_once $base . 'Controllers/UtilityController.php';
require_once $base . 'Controllers/ChatController.php';
require_once $base . 'Controllers/ModelsController.php';
require_once $base . 'Controllers/LicenseController.php';
require_once $base . 'Controllers/SeoController.php';

// ── Admin ─────────────────────────────────────────────────────────────────────
require_once $base . 'Admin/Admin_Init.php';

// ── Utils ─────────────────────────────────────────────────────────────────────
require_once $base . 'Utils/Activator.php';
require_once $base . 'Utils/MarkdownConverter.php';
require_once $base . 'Utils/AjaxHandler.php';

// ── Frontend ──────────────────────────────────────────────────────────────────
require_once $base . 'Frontend/Frontend_Init.php';
