<?php
/**
 * 插件魔法数字常量集中管理
 *
 * @package ZuoAIPlus\Utils
 */
namespace ZuoAIPlus\Utils;

if (!defined('ABSPATH')) exit;

final class Constants
{
    /*═════════════════════════════════════════════════════════════
     * 速率限制配置（max_requests, window_seconds）
     *═════════════════════════════════════════════════════════════*/

    // 文章生成
    public const RATE_LIMIT_GENERATE_REQUESTS = 15;
    public const RATE_LIMIT_GENERATE_WINDOW    = 60; // 秒

    // 扩写
    public const RATE_LIMIT_EXPAND_REQUESTS   = 25;
    public const RATE_LIMIT_EXPAND_WINDOW     = 60;

    // 改写
    public const RATE_LIMIT_REWRITE_REQUESTS  = 25;
    public const RATE_LIMIT_REWRITE_WINDOW    = 60;

    // 摘要
    public const RATE_LIMIT_SUMMARIZE_REQUESTS = 20;
    public const RATE_LIMIT_SUMMARIZE_WINDOW   = 60;

    // 关键词提取
    public const RATE_LIMIT_KEYWORD_REQUESTS  = 20;
    public const RATE_LIMIT_KEYWORD_WINDOW     = 60;

    // Slug 生成
    public const RATE_LIMIT_SLUG_REQUESTS      = 30;
    public const RATE_LIMIT_SLUG_WINDOW        = 60;

    // 特色图 / 文生图
    public const RATE_LIMIT_IMAGE_REQUESTS     = 3;
    public const RATE_LIMIT_IMAGE_WINDOW        = 60;

    // SEO 分析
    public const RATE_LIMIT_SEO_ANALYSIS_REQUESTS = 10;
    public const RATE_LIMIT_SEO_ANALYSIS_WINDOW   = 60;

    // SEO 单篇优化
    public const RATE_LIMIT_SEO_POST_REQUESTS  = 5;
    public const RATE_LIMIT_SEO_POST_WINDOW    = 60;

    // 批量 SEO 优化
    public const RATE_LIMIT_SEO_BATCH_REQUESTS = 3;
    public const RATE_LIMIT_SEO_BATCH_WINDOW   = 3600; // 1 小时

    // 导航点击冷却（同一 IP 对同一文章）
    public const RATE_LIMIT_NAV_CLICK_WINDOW   = 60; // 秒

    // 评分频率限制（同一 IP 每小时最多评分 N 篇不同网站）
    public const RATE_LIMIT_RATING_MAX_PER_IP  = 5;
    public const RATE_LIMIT_RATING_WINDOW      = 3600; // 1 小时

    /*═════════════════════════════════════════════════════════════
     * 缓存 TTL（秒）
     *═════════════════════════════════════════════════════════════*/

    // 热门网站列表
    public const CACHE_TTL_POPULAR_SITES       = 3600; // 1 小时

    // SEO 权重缓存
    public const CACHE_TTL_SEO_WEIGHT          = 86400; // 24 小时

    // 网站状态缓存
    public const CACHE_TTL_SITE_STATUS         = 21600; // 6 小时

    // AI 内容缓存（生成/扩写/改写）
    public const CACHE_TTL_CONTENT_GENERATE     = 86400; // 24 小时

    // AI 摘要缓存
    public const CACHE_TTL_SUMMARIZE           = 86400; // 24 小时

    // AI 关键词缓存
    public const CACHE_TTL_KEYWORD             = 3600;  // 1 小时

    // AI Slug 不缓存
    public const CACHE_TTL_SLUG               = 0;

    // AI 标题优化缓存
    public const CACHE_TTL_TITLE_OPTIMIZE     = 86400; // 24 小时

    // 图片生成缓存（7 天）
    public const CACHE_TTL_IMAGE               = 604800;

    // 默认缓存 TTL
    public const CACHE_TTL_DEFAULT            = 3600;  // 1 小时

    // 截图本地缓存（7 天）
    public const CACHE_TTL_SCREENSHOT         = 604800; // 7 天

    /*═════════════════════════════════════════════════════════════
     * 内容长度限制
     *═════════════════════════════════════════════════════════════*/

    // 摘要建议字数上限
    public const SUMMARY_MAX_LENGTH            = 4000;

    // Slug 最大长度（字符）
    public const SLUG_MAX_LENGTH              = 30;

    // 标签最大长度（字符）
    public const TAG_MAX_LENGTH               = 16;

    // 标题最小/最大长度（字符）
    public const TITLE_MIN_LENGTH             = 6;
    public const TITLE_MAX_LENGTH             = 30;

    // 摘要最小/最大长度（字符）
    public const SUMMARY_MIN_LENGTH           = 80;
    public const SUMMARY_MAX_CHARS            = 120;

    /*═════════════════════════════════════════════════════════════
     * SEO 权重字段映射
     *═════════════════════════════════════════════════════════════*/

    public const SEO_WEIGHT_MAP = [
        'BaiduPCWeight'      => 'baidu',
        'BaiduMobileWeight'  => 'baidu_m',
        'HaoSouWeight'       => '360',
        'SMWeight'           => 'sogou',
        'TouTiaoWeight'      => 'toutiao',
    ];

    /*═════════════════════════════════════════════════════════════
     * 其他
     *═════════════════════════════════════════════════════════════*/

    // Gutenberg 侧边栏 debounce 延迟（毫秒）
    public const SIDEBAR_DEBOUNCE_MS          = 500;

    // 截图最小文件大小（字节）
    public const SCREENSHOT_MIN_SIZE           = 1000;

    // 每页最多网站数量
    public const SITES_PER_PAGE_MAX           = 50;

    // 批量操作每次处理上限
    public const BATCH_LIMIT_MAX              = 50;
}
