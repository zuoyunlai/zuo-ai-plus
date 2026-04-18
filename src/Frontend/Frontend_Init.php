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
        // 聊天功能开启时，加载样式和脚本（不限页面类型）
        // 浮窗在所有页面渲染，短代码/文章嵌入块在各页面可能出现
        $enabled = \get_option('ai_plus_chat_enabled', '0');
        if ($enabled === '0') return;

        \wp_enqueue_style('ai-plus-frontend', AI_PLUS_PLUGIN_URL . 'Assets/css/frontend.css', [], AI_PLUS_VERSION);
        // marked.js：Markdown → HTML 渲染（CDN，45KB，零依赖）
        \wp_register_script('marked', AI_PLUS_PLUGIN_URL . 'Assets/js/marked.min.js', [], AI_PLUS_VERSION, true);
        \wp_enqueue_script("marked");
        \wp_enqueue_script('ai-plus-frontend', AI_PLUS_PLUGIN_URL . 'Assets/js/frontend.js', ['jquery', 'marked'], AI_PLUS_VERSION, true);

        $defaultModel = \get_option('ai_plus_default_model', 'minimax');
        // 统一配置对象：浮窗、短代码、文章嵌入块共用 aiPlusChat
        \wp_localize_script('ai-plus-frontend', 'aiPlusChat', [
            'apiUrl' => \rest_url('ai-plus/v1/'),
            'nonce'  => \wp_create_nonce('wp_rest'),
            'chatEnabled' => $enabled,
            'defaultModel' => $defaultModel,
        ]);
    }

    public function renderChatWidget(): void
    {
        if (\get_option('ai_plus_chat_enabled', '0') !== '1') return;
        if (\is_admin()) return;

        $defaultModel = \get_option('ai_plus_default_model', 'minimax');

        // 文章上下文：浮窗客服需要知道当前在哪个文章页面
        $articleText = '';
        if (\is_singular()) {
            global $post;
            if ($post) {
                $articleText = \wp_strip_all_tags($post->post_content ?: '');
                if (mb_strlen($articleText, 'utf-8') > 8000) {
                    $articleText = mb_substr($articleText, 0, 8000, 'utf-8');
                }
            }
        }
        ?>
        <div id="ai-plus-chat-btn" onclick="toggleAiChat()">💬</div>

        <div id="ai-plus-chat-window" style="display:none;">
            <input type="hidden" id="ai-chat-article-context" value="<?php echo \esc_attr($articleText); ?>">
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
        // 匿名用户不允许调用聊天接口，显示友好提示
        if (!is_user_logged_in()) {
            return '<div class="ai-plus-embed-chat"><div class="ai-chat-header"><span>AI 对话</span></div><div class="ai-chat-messages embed-messages" style="padding:12px;color:#888;">请先登录后再使用 AI 对话功能。</div></div>';
        }

        $defaultModel = \get_option('ai_plus_default_model', 'minimax');
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

        $htmlId = 'ai-embed-' . (function_exists('wp_generate_uuid4') ? \wp_generate_uuid4() : substr(md5(uniqid(wp_rand(), true)), 0, 13));
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
