<?php
/**
 * 控制器基类 - 提供公共方法和权限检查
 *
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

abstract class BaseController
{
    /**
     * 检查当前用户是否有编辑权限
     */
    /**
     * WordPress REST API permission_callback 要求 public 方法
     */
    public function canEdit(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * WordPress REST API permission_callback 要求 public 方法
     */
    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * 返回成功响应
     */
    protected function success(array $data = [], int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response($data, $status);
    }

    /**
     * 返回错误响应
     */
    protected function error(string $message, int $status = 400): \WP_REST_Response
    {
        return new \WP_REST_Response(['error' => $message], $status);
    }

    /**
     * 获取模型实例
     */
    protected function getModel(string $name): ?\ZuoAIPlus\Models\BaseModel
    {
        return \ZuoAIPlus\Models\Model_Init::getModel($name);
    }

    /**
     * 获取默认模型名称
     */
    protected function getDefaultModel(): string
    {
        return (string) apply_filters('ai_plus_default_model', get_option('ai_plus_default_model', 'minimax'));
    }

    /**
     * 注入知识库背景到消息列表
     */
    protected function injectKnowledgeBase(array &$messages): void
    {
        $kb = trim((string) get_option('ai_plus_knowledge_base', ''));
        if ($kb) {
            array_unshift($messages, [
                'role'    => 'system',
                'content' => "【品牌/公司背景知识】以下是你所属公司/品牌的官方信息，访客询问地址、电话、营业时间、联系方式等问题时，优先以这里的信息为准回答：\n" . $kb
            ]);
        }
    }

    /**
     * 注入文章上下文到消息列表
     */
    protected function injectContext(array &$messages, string $context): void
    {
        if ($context) {
            array_unshift($messages, [
                'role'    => 'system',
                'content' => "【当前文章内容】以下是当前文章的全部内容，答题时请以它为依据：\n" . $context
            ]);
        }
    }

    /**
     * 记录AI操作到历史表
     *
     * @param string $action_type 操作类型
     * @param string $model 模型名称
     * @param array $result API返回结果
     * @param int|null $post_id 关联的文章ID
     * @param string|null $prompt 提示词
     */
    protected function logHistory(string $action_type, string $model, array $result, ?int $post_id = null, ?string $prompt = null): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_plus_history';

        // 检查表是否存在（static 缓存，避免每次操作都查 SHOW TABLES）
        static $tableExists = null;
        if ($tableExists === null) {
            $tableExists = (bool) $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
        }

        if (!$tableExists) {
            error_log('[ZuoAI] logHistory: table ' . $table . ' does not exist, skipping');
            return; // 表不存在时静默返回
        }

        // 提取token使用量
        $tokens = 0;
        if (!empty($result['usage'])) {
            $tokens = $result['usage']['total_tokens'] ?? ($result['usage']['prompt_tokens'] ?? 0) + ($result['usage']['completion_tokens'] ?? 0);
        }

        // 提取结果内容
        $result_content = '';
        if (is_array($result)) {
            if (isset($result['content'])) {
                $result_content = $result['content'];
            } elseif (isset($result['choices'][0]['message']['content'])) {
                $result_content = $result['choices'][0]['message']['content'];
            } elseif (isset($result['text'])) {
                $result_content = $result['text'];
            }
        }

        $wpdb->insert($table, [
            'post_id'     => $post_id,
            'action_type' => $action_type,
            'prompt'      => $prompt ? mb_substr($prompt, 0, 1000) : null,
            'result'      => mb_substr($result_content, 0, 2000),
            'model'       => $model,
            'tokens'      => $tokens,
            'created_at'  => current_time('mysql'),
        ]);
    }

    /**
     * 检查API请求速率限制
     *
     * @param string $action 操作类型
     * @param int $maxRequests 最大请求数
     * @param int $windowSeconds 时间窗口（秒）
     * @return ?\WP_REST_Response 超出限制时返回错误响应，否则返回null
     */
    protected function checkRateLimit(string $action, int $maxRequests = 10, int $windowSeconds = 60): ?\WP_REST_Response
    {
        // 匿名用户（userId=0）按 IP 区分，避免不同匿名用户共享同一限速桶
        $userId = get_current_user_id();
        if ($userId === 0) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? md5(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))) : 'anon';
            $transientKey = 'ai_plus_rate_' . $action . '_ip_' . $ip;
        } else {
            $transientKey = 'ai_plus_rate_' . $action . '_user_' . $userId;
        }
        $current = get_transient($transientKey) ?: 0;

        if ($current >= $maxRequests) {
            return $this->error(__('请求过于频繁，请稍后再试', 'zuo-ai-plus'), 429);
        }

        set_transient($transientKey, $current + 1, $windowSeconds);
        return null;
    }

    /**
     * License 校验
     *
     * 网络异常时：连续失败超过 3 次才放宽限制（防止服务器宕机时完全无法使用）
     */
    protected function checkLicense(): ?\WP_REST_Response
    {
        $key = trim((string) get_option('ai_plus_license_key', ''));
        if (empty($key)) {
            return $this->error(__('图片功能需要激活正版授权，请在「授权管理」页面输入 License Key', 'zuo-ai-plus'), 403);
        }

        // 连续失败计数，超限则放行
        $fail_count = (int) get_transient('ai_plus_license_fail_count');
        if ($fail_count >= 3) {
            return null; // 许可证服务器连续故障，宽容处理
        }

        // 验证结果本地缓存5分钟，减少远程调用频率
        $cached = get_transient('ai_plus_license_verified');
        if ($cached !== false) {
            return null; // 已验证，直接放行
        }

        $server_url = trim((string) get_option('ai_plus_license_server_url', ''));
        if ($server_url === '') {
            $server_url = 'https://www.yily.top/licenses/api.php';
        }
        $domain = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== ''
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))
            : parse_url(home_url(), PHP_URL_HOST);
        $verify_url = add_query_arg([
            'action' => 'verify',
            'key'    => urlencode($key),
            'domain' => urlencode($domain),
        ], $server_url);

        $resp = wp_remote_get($verify_url, [
            'timeout'    => 8,
            'sslverify'  => true,
            'user-agent' => 'ZuoAIPlus/' . AI_PLUS_VERSION . '; ' . home_url(),
        ]);

        if (is_wp_error($resp)) {
            // 网络异常 → 计数 +1，连续 3 次失败后放宽
            $fail_count++;
            set_transient('ai_plus_license_fail_count', $fail_count, HOUR_IN_SECONDS);
            if ($fail_count >= 3) {
                return null; // 宽容
            }
            return $this->error(__('许可证服务器连接失败', 'zuo-ai-plus') . '（' . $resp->get_error_message() . '），' . __('请稍后重试', 'zuo-ai-plus'), 503);
        }

        // 连接成功 → 重置失败计数
        delete_transient('ai_plus_license_fail_count');

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $st   = $body['status'] ?? 'unknown';

        if ($st === 'valid') {
            // 验证成功，缓存5分钟（避免每次生成图片都远程验证一次）
            set_transient('ai_plus_license_verified', '1', 5 * MINUTE_IN_SECONDS);
            return null;
        }

        $msg = match ($st) {
            'expired'        => __('License 已过期，请联系续费：17854779@qq.com', 'zuo-ai-plus'),
            'domain_mismatch' => __('License 域名不匹配，请在该域名下激活', 'zuo-ai-plus'),
            default           => __('License 无效或未激活，请联系：17854779@qq.com', 'zuo-ai-plus'),
        };

        return $this->error($msg, 403);
    }
}
