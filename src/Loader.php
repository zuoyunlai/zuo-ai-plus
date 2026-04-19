<?php
/**
 * 类自动加载器
 * 按正确顺序加载所有类文件，不依赖 composer autoload
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */
if (!defined('ABSPATH')) exit;

$ai_base = __DIR__ . '/';

// ── Models ──────────────────────────────────────────────────────────────────
require_once $ai_base . 'Models/BaseModel.php';
require_once $ai_base . 'Models/ZhipuModel.php';
require_once $ai_base . 'Models/TongyiModel.php';
require_once $ai_base . 'Models/MiniMaxModel.php';
require_once $ai_base . 'Models/KimiModel.php';
require_once $ai_base . 'Models/DeepSeekModel.php';
require_once $ai_base . 'Models/CustomModel.php';
require_once $ai_base . 'Models/SeoOptimizer.php';
require_once $ai_base . 'Models/Model_Init.php';

// ── Controllers（基类先于子类）────────────────────────────────────────────────
require_once $ai_base . 'Controllers/BaseController.php';
require_once $ai_base . 'Controllers/ContentController.php';
require_once $ai_base . 'Controllers/UtilityController.php';
require_once $ai_base . 'Controllers/ChatController.php';
require_once $ai_base . 'Controllers/ModelsController.php';
require_once $ai_base . 'Controllers/LicenseController.php';
require_once $ai_base . 'Controllers/SeoController.php';

// ── Admin ─────────────────────────────────────────────────────────────────────
require_once $ai_base . 'Admin/Admin_Init.php';

// ── Utils ─────────────────────────────────────────────────────────────────────
require_once $ai_base . 'Utils/Activator.php';
require_once $ai_base . 'Utils/MarkdownConverter.php';
require_once $ai_base . 'Utils/AjaxHandler.php';

// ── Frontend ──────────────────────────────────────────────────────────────────
require_once $ai_base . 'Frontend/Frontend_Init.php';
