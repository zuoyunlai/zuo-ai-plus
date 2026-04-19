<?php
/**
 * 统一日志工具类
 *
 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */
namespace ZuoAIPlus\Utils;

class Logger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    private static bool $debugEnabled = false;

    /**
     * 初始化日志配置
     */
    public static function init(): void
    {
        self::$debugEnabled = (bool) get_option('ai_plus_debug_logging', false) 
            || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * 记录日志（统一格式）
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $timestamp = gmdate('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[AI Plus][{$timestamp}][{$level}] {$message}{$contextStr}";
        
        // 错误级别始终记录
        if ($level === self::LEVEL_ERROR) {
            error_log($logMessage);
            return;
        }
        
        // 其他级别仅在调试模式下记录
        if (self::$debugEnabled) {
            error_log($logMessage);
        }
    }

    /**
     * 调试日志
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * 信息日志
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * 警告日志
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * 错误日志
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * 记录 API 请求日志
     */
    public static function logApiRequest(string $model, string $action, int $durationMs, bool $success, ?string $error = null): void
    {
        $context = [
            'model' => $model,
            'action' => $action,
            'duration_ms' => $durationMs,
            'success' => $success,
        ];
        
        if ($error) {
            $context['error'] = $error;
            self::error("API Request Failed", $context);
        } else {
            self::info("API Request Success", $context);
        }
    }

    /**
     * 记录用户操作日志
     */
    public static function logUserAction(int $userId, string $action, string $target, array $details = []): void
    {
        $context = [
            'user_id' => $userId,
            'action' => $action,
            'target' => $target,
            'details' => $details,
        ];
        
        self::info("User Action", $context);
    }
}

// 初始化
Logger::init();