<?php
/**
 * 错误码定义
 */
namespace ZuoAIPlus\Utils;

class ErrorCodes
{
    // 成功
    const SUCCESS = 0;

    // 通用错误 (1000-1999)
    const UNKNOWN_ERROR = 1000;
    const INVALID_PARAMS = 1001;
    const MISSING_REQUIRED_PARAM = 1002;
    const RATE_LIMIT_EXCEEDED = 1003;

    // 认证授权错误 (2000-2999)
    const AUTH_REQUIRED = 2000;
    const AUTH_FAILED = 2001;
    const INSUFFICIENT_PERMISSIONS = 2002;
    const LICENSE_INVALID = 2003;

    // 资源错误 (3000-3999)
    const POST_NOT_FOUND = 3001;
    const MODEL_NOT_FOUND = 3002;
    const API_KEY_MISSING = 3003;

    // API 错误 (4000-4999)
    const API_REQUEST_FAILED = 4000;
    const API_TIMEOUT = 4001;
    const API_KEY_INVALID = 4002;
    const API_QUOTA_EXCEEDED = 4003;
    const API_MODEL_NOT_SUPPORTED = 4004;

    // 文件操作错误 (5000-5999)
    const FILE_UPLOAD_FAILED = 5001;
    const FILE_TOO_LARGE = 5002;
    const FILE_TYPE_NOT_ALLOWED = 5003;

    /**
     * 获取错误码对应的消息
     */
    public static function getMessage(int $code): string
    {
        $messages = [
            self::SUCCESS => '操作成功',
            self::UNKNOWN_ERROR => '未知错误，请稍后重试',
            self::INVALID_PARAMS => '参数无效',
            self::MISSING_REQUIRED_PARAM => '缺少必要参数',
            self::RATE_LIMIT_EXCEEDED => '请求过于频繁，请稍后再试',
            
            self::AUTH_REQUIRED => '请先登录',
            self::AUTH_FAILED => '认证失败',
            self::INSUFFICIENT_PERMISSIONS => '权限不足',
            self::LICENSE_INVALID => '授权无效，请检查授权状态',
            
            self::POST_NOT_FOUND => '文章不存在',
            self::MODEL_NOT_FOUND => '模型不存在',
            self::API_KEY_MISSING => 'API Key 未配置',
            
            self::API_REQUEST_FAILED => 'AI 服务请求失败',
            self::API_TIMEOUT => 'AI 服务响应超时',
            self::API_KEY_INVALID => 'API Key 无效',
            self::API_QUOTA_EXCEEDED => 'API 配额已用完',
            self::API_MODEL_NOT_SUPPORTED => '当前模型不支持此操作',
            
            self::FILE_UPLOAD_FAILED => '文件上传失败',
            self::FILE_TOO_LARGE => '文件过大',
            self::FILE_TYPE_NOT_ALLOWED => '文件类型不允许',
        ];

        return $messages[$code] ?? '未知错误';
    }

    /**
     * 创建错误响应
     */
    public static function error(string $message, int $code = self::UNKNOWN_ERROR): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'code'    => $code,
            'message' => $message,
            'data'    => ['status' => $code],
        ], $code >= 1000 && $code < 2000 ? 400 : ($code >= 2000 && $code < 3000 ? 401 : 500));
    }

    /**
     * 转换异常为错误码
     */
    public static function fromException(\Throwable $e): array
    {
        $message = $e->getMessage();
        
        if (stripos($message, 'timeout') !== false || stripos($message, '超时') !== false) {
            return ['code' => self::API_TIMEOUT, 'message' => self::getMessage(self::API_TIMEOUT)];
        }
        
        if (stripos($message, 'invalid') !== false || stripos($message, '无效') !== false) {
            return ['code' => self::API_KEY_INVALID, 'message' => self::getMessage(self::API_KEY_INVALID)];
        }
        
        if (stripos($message, 'quota') !== false || stripos($message, '配额') !== false) {
            return ['code' => self::API_QUOTA_EXCEEDED, 'message' => self::getMessage(self::API_QUOTA_EXCEEDED)];
        }
        
        return ['code' => self::API_REQUEST_FAILED, 'message' => $message ?: self::getMessage(self::API_REQUEST_FAILED)];
    }
}