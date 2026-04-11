<?php
/**
 * 前端初始化 - 聊天窗口和短代码
 */
namespace ZuoAIPlus\Frontend;

if (!defined('ABSPATH')) exit;

class Frontend_Init
{
    public function __construct()
    {
        \add_action('wp_footer', [$this, 'renderChatWidget']);
        \add_shortcode('ai_plus_chat', [$this, 'shortcodeChat']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
    }

    public function enqueueFrontend(): void
    {
        // 短代码页面也需要加载样式，所以只要不是明确关闭就加载
        $enabled = \get_option('ai_plus_chat_enabled', '0');
        if ($enabled === '0') return;

        \wp_enqueue_style('ai-plus-frontend', AI_PLUS_PLUGIN_URL . 'Assets/css/frontend.css', [], AI_PLUS_VERSION);
        \wp_enqueue_script('ai-plus-frontend', AI_PLUS_PLUGIN_URL . 'Assets/js/frontend.js', ['jquery'], AI_PLUS_VERSION, true);

        \wp_localize_script('ai-plus-frontend', 'aiPlusChat', [
            'apiUrl' => \rest_url('ai-plus/v1/'),
            'nonce' => \wp_create_nonce('wp_rest'),
            'chatEnabled' => $enabled,
        ]);
    }

    public function renderChatWidget(): void
    {
        if (\get_option('ai_plus_chat_enabled', '0') !== '1') return;
        if (\is_admin()) return;

        $defaultModel = \get_option('ai_plus_default_model', 'zhipu');
        $models = [
            'zhipu' => '智谱 GLM',
            'tongyi' => '阿里通义千问',
            'minimax' => 'MiniMax',
            'kimi' => 'Kimi',
        ];
        ?>
        <div id="ai-plus-chat-btn" onclick="toggleAiChat()">💬</div>

        <div id="ai-plus-chat-window" style="display:none;">
            <div class="ai-chat-header">
                <span>AI 客服</span>
                <button onclick="toggleAiChat()" style="margin-left:auto;background:none;border:none;color:#fff;cursor:pointer;font-size:16px;">✕</button>
            </div>
            <div id="ai-chat-messages" class="ai-chat-messages"></div>
            <div class="ai-chat-input">
                <input type="text" id="ai-chat-msg" placeholder="输入问题..." onkeypress="if(event.key==='Enter')sendAiChat()">
                <button onclick="sendAiChat()">发送</button>
            </div>
        </div>
        <?php
    }

    public function shortcodeChat($atts): string
    {
        $defaultModel = \get_option('ai_plus_default_model', 'zhipu');
        $atts = \shortcode_atts(['model' => $defaultModel], $atts);
        $model = \sanitize_text_field($atts['model']);

        // 动态读取已配置的平台列表（与后台 Playground 一致）
        $platforms = [
            'zhipu'   => ['name' => '智谱 GLM',    'default' => 'glm-4-flashx'],
            'tongyi'  => ['name' => '通义千问',   'default' => 'qwen-turbo'],
            'minimax' => ['name' => 'MiniMax',    'default' => 'MiniMax-M2.7'],
            'kimi'    => ['name' => 'Kimi',        'default' => 'moonshot-v1-8k'],
            'deepseek'=> ['name' => 'DeepSeek',    'default' => 'deepseek-chat'],
            'custom'  => ['name' => '自定义',     'default' => ''],
        ];
        $apiKeys = \get_option('ai_plus_api_keys', []);
        $options = '';
        foreach ($platforms as $id => $p) {
            $cfg = $apiKeys[$id] ?? [];
            if (empty($cfg['api_key'])) continue; // 只显示已配置的平台
            $modelName = $cfg['model'] ?: $p['default'];
            $label = $p['name'] . ' — ' . $modelName;
            $sel = \selected($model, $id, false);
            $options .= '<option value="' . \esc_attr($id) . '"' . $sel . '>' . \esc_html($label) . '</option>';
        }

        $htmlId = 'ai-embed-' . \wp_generate_uuid4();
        return \sprintf(
            '<div class="ai-plus-embed-chat" id="%s" data-model="%s">' .
            '<div class="ai-chat-header"><span>AI 对话</span><select class="embed-model-sel" style="margin-left:auto;font-size:12px;">%s</select></div>' .
            '<div class="ai-chat-messages embed-messages"></div>' .
            '<div class="ai-chat-input"><input type="text" class="embed-msg-input" placeholder="输入问题，按 Enter 发送..."><button class="embed-send-btn">发送</button></div>' .
            '</div>',
            \esc_attr($htmlId),
            \esc_attr($model),
            $options
        );
    }
}
