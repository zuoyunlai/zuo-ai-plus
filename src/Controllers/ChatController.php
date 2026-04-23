<?php
/**
 * 聊天控制器 - 处理聊天相关REST API
 *
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class ChatController extends BaseController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-plus/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleChat'],
            'permission_callback' => [$this, 'canEdit'],
        ]);
    }

    public function handleChat(\WP_REST_Request $request): \WP_REST_Response
    {
        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $messages  = $request->get_param('messages');

        if (!is_array($messages) || empty($messages)) {
            return $this->error(__('参数错误', 'zuo-ai-plus'));
        }

        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                return $this->error(__('消息格式错误', 'zuo-ai-plus'));
            }
        }

        $context   = sanitize_textarea_field($request->get_param('context') ?: '');
        $sessionId = sanitize_text_field($request->get_param('session_id') ?: '');

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        $chatMessages = $messages;
        $this->injectKnowledgeBase($chatMessages);
        $this->injectContext($chatMessages, $context);

        try {
            // 将 session_id 传给 chat() 用于缓存 key 计算
            $result = $model->chat($chatMessages, ['session_id' => $sessionId]);
            $this->saveChatHistory($sessionId, $messages, $result, $modelName);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    private function saveChatHistory(string $sessionId, array $messages, array $result, string $modelName): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_plus_chat';

        // 验证表是否存在
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        )) === $table;

        if (!$tableExists) {
            error_log('[ZuoAI] saveChatHistory: table ' . $table . ' does not exist, skipping');
            return; // 表不存在时静默返回，不影响主流程
        }

        $wpdb->insert($table, [
            'session_id' => $sessionId,
            'user_id'    => get_current_user_id(),
            'role'       => 'assistant',
            'message'    => maybe_serialize($messages),
            'response'   => is_array($result)
                ? wp_strip_all_tags(\ZuoAIPlus\Models\Model_Init::extractContent($result))
                : wp_strip_all_tags($result['content'] ?? $result),
            'model'      => $modelName,
            'tokens'     => $result['usage']['total_tokens'] ?? 0,
        ]);
    }
}
