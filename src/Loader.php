<?php
if (!defined("ABSPATH")) exit;
/**
 * 类自动加载器 - 手动实现，不依赖 composer autoload
 * 按正确顺序加载所有类文件
 */
require_once __DIR__ . '/Models/BaseModel.php';
require_once __DIR__ . '/Models/ZhipuModel.php';
require_once __DIR__ . '/Models/TongyiModel.php';
require_once __DIR__ . '/Models/MiniMaxModel.php';
require_once __DIR__ . '/Models/KimiModel.php';
require_once __DIR__ . '/Models/DeepSeekModel.php';
require_once __DIR__ . '/Models/CustomModel.php';
require_once __DIR__ . '/Models/SeoOptimizer.php';
require_once __DIR__ . '/Models/Model_Init.php';
require_once __DIR__ . '/Admin/Admin_Init.php';
require_once __DIR__ . '/Utils/Activator.php';
require_once __DIR__ . '/Utils/MarkdownConverter.php';
require_once __DIR__ . '/Utils/AjaxHandler.php';
require_once __DIR__ . '/Frontend/Frontend_Init.php';
