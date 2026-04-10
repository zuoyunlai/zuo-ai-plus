<?php
/**
 * AI Plus 管理后台
 */
namespace AI_Plus\Admin;

if (!defined('ABSPATH')) exit;

class Admin_Init
{
    private $models = [
        'zhipu'   => ['name' => '智谱 GLM',   'default' => 'glm-4-flashx',       'url' => 'https://open.bigmodel.cn/api/paas/v4'],
        'tongyi'  => ['name' => '通义千问',  'default' => 'qwen-turbo',          'url' => 'https://dashscope.aliyuncs.com/api/v1'],
        'minimax' => ['name' => 'MiniMax',   'default' => 'MiniMax-M2.7',        'url' => 'https://api.minimax.chat/v1'],
        'kimi'    => ['name' => 'Kimi',      'default' => 'moonshot-v1-8k',      'url' => 'https://api.moonshot.cn/v1'],
        'deepseek' => ['name' => 'DeepSeek', 'default' => 'deepseek-chat',        'url' => 'https://api.deepseek.com/v1'],
        'custom'  => ['name' => '自定义 (代理)', 'default' => '',                          'url' => ''],
    ];

    // 支持文生图的平台及默认模型
    private $imageModels = [
        'zhipu'   => 'cogview-3',
        'tongyi'  => 'qwen-image-2.0-pro',
        'custom'  => '',  // 可选，用户自行填写
    ];

    public function __construct()
    {
        \add_action('admin_menu', [$this, 'addMenu']);
        \add_action('admin_init', [$this, 'registerSettings']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueGutenbergSidebar']);
    }

    public function enqueueAssets(string $page): void
    {
        if (strpos($page, 'ai_plus') === false) return;
        \wp_enqueue_style('ai-plus-admin', AI_PLUS_PLUGIN_URL . 'Assets/css/admin.css', [], AI_PLUS_VERSION);
        \wp_enqueue_script('ai-plus-admin', AI_PLUS_PLUGIN_URL . 'Assets/js/admin.js', ['jquery'], AI_PLUS_VERSION, true);
        \wp_localize_script('ai-plus-admin', 'aiPlusAdmin', [
            'apiUrl' => \rest_url('ai-plus/v1/'),
            'nonce'  => \wp_create_nonce('wp_rest'),
        ]);
    }

    public function enqueueGutenbergSidebar(string $page): void
    {
        if (!\in_array($page, ['post.php', 'post-new.php'], true)) return;
        \wp_enqueue_script(
            'ai-plus-gutenberg-sidebar',
            AI_PLUS_PLUGIN_URL . 'Assets/js/gutenberg-sidebar.js',
            ['wp-edit-post', 'wp-plugins', 'wp-element', 'wp-data', 'wp-components', 'wp-api'],
            AI_PLUS_VERSION,
            true
        );
        \wp_localize_script('ai-plus-gutenberg-sidebar', 'aiPlusConfig', [
            'apiUrl'  => \rest_url('ai-plus/v1/'),
            'nonce'   => \wp_create_nonce('wp_rest'),
            'models'  => $this->models,
            'apiKeys' => \get_option('ai_plus_api_keys', []),
        ]);
    }

    public function addMenu(): void
    {
        \add_menu_page('Zuo AI Plus', 'Zuo AI Plus', 'manage_options', 'ai_plus', [$this, 'renderPage'], '', 80);
        \add_submenu_page('ai_plus', 'Zuo AI Plus 设置', '设置', 'manage_options', 'ai_plus', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', 'Playground', 'Playground', 'manage_options', 'ai_plus_playground', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '图片生成', '图片生成', 'manage_options', 'ai_plus_image', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', 'AI Plus 关于', '关于', 'manage_options', 'ai_plus_about', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', 'AI Plus 统计', '统计', 'manage_options', 'ai_plus_stats', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', 'AI Plus 授权管理', '授权管理', 'manage_options', 'ai_plus_license', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', 'AI Plus SEO 诊断', 'SEO 诊断', 'manage_options', 'ai_plus_seo', [$this, 'renderPage']);
        // 移除重复的顶层菜单项
        global $submenu;
        if (isset($submenu['ai_plus'][0])) {
            unset($submenu['ai_plus'][0]);
        }
    }

    public function registerSettings(): void
    {
        \register_setting('ai_plus_settings', 'ai_plus_api_keys', ['sanitize_callback' => function($v) { return is_array($v) ? $v : []; }]);
        \register_setting('ai_plus_settings', 'ai_plus_default_model', ['sanitize_callback' => function($v) { return sanitize_text_field($v); }]);
        \register_setting('ai_plus_settings', 'ai_plus_image_model', ['sanitize_callback' => function($v) { return sanitize_text_field($v); }]);
        // 迁移旧 key
        $old = \get_option('ai_plus_featured_image_model', '');
        if ($old && !\get_option('ai_plus_image_model', '')) {
            \update_option('ai_plus_image_model', $old);
        }
        \register_setting('ai_plus_settings', 'ai_plus_knowledge_base', ['sanitize_callback' => [$this, 'sanitizeLicenseKey']]);
    \register_setting('ai_plus_settings', 'ai_plus_license_key', ['sanitize_callback' => [$this, 'sanitizeLicenseKey']]);
        \register_setting('ai_plus_settings', 'ai_plus_license_server_url', ['sanitize_callback' => [$this, 'sanitizeLicenseUrl']]);
        \register_setting('ai_plus_settings', 'ai_plus_chat_enabled', ['sanitize_callback' => function($v) { return (bool)$v; }]);
    }

    // 设置项 sanitization callbacks
    public function sanitizeLicenseKey($value) {
        return sanitize_text_field(trim($value));
    }
    public function sanitizeLicenseUrl($value) {
        $v = trim($value);
        if ($v === '') return '';
        return esc_url_raw($v);
    }

    public function renderPage(): void
    {
        // Verify request when switching tabs via URL
        if (isset($_GET['page']) && isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ai_plus_admin')) {
            wp_die('Security check failed.');
        }
        $tab = \sanitize_key(wp_unslash($_GET['page'] ?? 'ai_plus'));
        $h   = function($s) { return \esc_html($s); }; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

        function hasKey($cfg) {
            if (is_array($cfg)) return !empty($cfg['api_key']);
            return !empty($cfg); // old string format
        }


        $apiKeys   = \get_option('ai_plus_api_keys', []);
        $kbBase    = \trim(\get_option('ai_plus_knowledge_base', ''));
        $defModel  = \get_option('ai_plus_default_model', 'zhipu');
        $imgModel  = \get_option('ai_plus_image_model', 'tongyi');
        ?>
        <div class="wrap ai-plus-wrap">
            <h1>AI Plus</h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus', 'ai_plus_admin')); ?>"        class="nav-tab <?php echo  $tab==='ai_plus'        ? 'nav-tab-active' : '' ?>">Zuo AI Plus 文本模型</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_image', 'ai_plus_admin')); ?>"  class="nav-tab <?php echo  $tab==='ai_plus_image'  ? 'nav-tab-active' : '' ?>">图片生成</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_playground', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_playground' ? 'nav-tab-active' : '' ?>">Playground</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_stats', 'ai_plus_admin')); ?>"   class="nav-tab <?php echo  $tab==='ai_plus_stats'   ? 'nav-tab-active' : '' ?>">📊 统计</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_license', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_license' ? 'nav-tab-active' : '' ?>">授权管理</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_seo', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_seo' ? 'nav-tab-active' : '' ?>">🔍 SEO 诊断</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_about', 'ai_plus_admin')); ?>"  class="nav-tab <?php echo  $tab==='ai_plus_about'  ? 'nav-tab-active' : '' ?>">关于</a>
            </h2>

            <?php if ($tab === 'ai_plus'): ?>
                <form method="post" action="options.php">
                    <?php \settings_fields('ai_plus_settings'); ?>

                    <!-- 知识库背景 -->
                    <h2 class="ai-section-title">📚 知识库背景</h2>
                    <table class="form-table">
                        <tr>
                            <th>背景知识（AI 生成时会参考）</th>
                            <td>
                                <textarea name="ai_plus_knowledge_base" rows="4" style="width:600px;"
placeholder="例如：本公司专业生产铝合金衣柜，产品特点包括耐用、环保、美观..."><?php echo esc_html($kbBase); ?></textarea>
                                <p class="description">填写后，AI 在生成文章时会自动参考这段内容作为背景</p>
                            </td>
                        </tr>
                    </table>

                    <!-- 各模型配置 - 卡片式 -->
                    <h2 class="ai-section-title">🔑 各模型 API 配置</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:16px;">
                        每个平台只需填写 API Key，其他字段通常留空使用默认值即可。
                    </p>
                    <div class="ai-models-grid">
                        <?php foreach ($this->models as $id => $m): ?>
                        <?php
                            $savedRaw = $apiKeys[$id] ?? [];
                            $saved   = is_array($savedRaw) ? $savedRaw : ['api_key' => $savedRaw, 'base_url' => '', 'model' => ''];
                            $baseUrl = \esc_attr($saved['base_url'] ?? $m['url']);
                            $apiKey  = $h($saved['api_key'] ?? '');
                            $model   = $h(($saved['model'] ?? '') ?: $m['default']);
                            $imgMdl  = isset($this->imageModels[$id]) ? $h(($saved['image_model'] ?? '') ?: ($this->imageModels[$id] ?? '')) : '';
                            $hasImg  = isset($this->imageModels[$id]);
                            $hasKey  = !empty($saved['api_key']);
                            $imgPlaceholder = $id === 'custom' ? 'OpenAI 兼容格式' : ($this->imageModels[$id] ?? '');
                        ?>
                        <div class="ai-model-card <?php echo  $hasKey ? 'ai-model-card--active' : '' ?>">
                            <div class="ai-model-card__header">
                                <strong><?php echo esc_html($m['name']) ?></strong>
                                <span class="ai-model-card__id"><?php echo esc_html($id) ?></span>
                                <?php if ($hasKey): ?>
                                    <span class="ai-model-card__badge ai-model-card__badge--ok">✅ 已配置</span>
                                <?php else: ?>
                                    <span class="ai-model-card__badge">未配置</span>
                                <?php endif; ?>
                            </div>
                            <div class="ai-model-card__body">
                                <div class="ai-model-card__field">
                                    <label>API Key <em style="color:#999;font-weight:normal;">（必填）</em></label>
                                    <input type="password" name="ai_plus_api_keys[<?php echo esc_html($id) ?>][api_key]" value="<?php echo esc_attr($apiKey); ?>" placeholder="sk-... 或平台 Key" autocomplete="new-password">
                                </div>
                                <div class="ai-model-card__field">
                                    <label>Base URL <em style="color:#999;font-weight:normal;">（通常留空）</em></label>
                                    <input type="text" name="ai_plus_api_keys[<?php echo esc_html($id) ?>][base_url]" value="<?php echo esc_url($baseUrl); ?>" placeholder="<?php echo esc_html($m['url']) ?>">
                                </div>
                                <div class="ai-model-card__field">
                                    <label>文本模型 <em style="color:#999;font-weight:normal;">（通常留空）</em></label>
                                    <input type="text" name="ai_plus_api_keys[<?php echo esc_html($id) ?>][model]" value="<?php echo esc_attr($model); ?>" placeholder="默认：<?php echo esc_html($m['default']) ?>">
                                </div>
                                <?php if ($hasImg): ?>
                                <div class="ai-model-card__field">
                                    <label>文生图模型 <em style="color:#999;font-weight:normal;">（可选）</em></label>
                                    <input type="text" name="ai_plus_api_keys[<?php echo esc_html($id) ?>][image_model]" value="<?php echo esc_attr($imgMdl); ?>" placeholder="<?php echo esc_html($imgPlaceholder) ?>">
                                </div>
                                <?php else: ?>
                                <div class="ai-model-card__field">
                                    <label>文生图</label>
                                    <span style="color:#ccc;font-size:12px;">该平台不支持</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>


                    <!-- 默认模型 -->
                    <h2 class="ai-section-title">⚙️ 默认设置</h2>
                    <table class="form-table">
                        <tr>
                            <th>默认文本模型</th>
                            <td>
                                <select name="ai_plus_default_model">
                                    <option value="">— 不指定 —</option>
                                    <?php foreach ($this->models as $k => $m): ?>
                                        <?php if (hasKey($apiKeys[$k] ?? [])): ?>
                                            <option value="<?php echo esc_html($k) ?>" <?php echo  $k === $defModel ? 'selected' : '' ?>><?php echo esc_html($m['name']) ?> — <?php echo esc_html($apiKeys[$k]['model'] ?: $m['default']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">侧边栏默认使用的文本生成模型</p>
                            </td>
                        </tr>
                        <tr>
                            <th>特色图片模型</th>
                            <td>
                                <select name="ai_plus_image_model">
                                    <?php foreach ($this->imageModels as $k => $imgM): ?>
                                        <?php
                                            $hasImgKey = hasKey($apiKeys[$k] ?? []);
                                            $imgVal = $apiKeys[$k]['image_model'] ?? $imgM;
                                            $showCustom = $k === 'custom' && hasKey($apiKeys['custom'] ?? []) && !empty($apiKeys['custom']['base_url']);
                                            if ($k === 'custom' && !$showCustom) continue;
                                            if ($k !== 'custom' && !$hasImgKey) continue;
                                        ?>
                                            <option value="<?php echo esc_html($k) ?>" <?php echo  $k === $imgModel ? 'selected' : '' ?>><?php echo esc_html($this->models[$k]['name']) ?> — <?php echo esc_html($imgVal) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">特色图专用模型（通义/智谱/MiniMax/自定义代理）</p>
                            </td>
                        </tr>
                    </table>

                    <h2 class="ai-section-title">💬 网站客服</h2>
                    <table class="form-table">
                        <tr>
                            <th>开启客服浮窗</th>
                            <td>
                                <label><input type="checkbox" name="ai_plus_chat_enabled" value="1" <?php echo  \checked(\get_option('ai_plus_chat_enabled', '0'), '1', false) ?>>
                                博客页面右下角显示 AI 客服悬浮按钮（默认关闭）</label>
                            </td>
                        </tr>
                    </table>

                    <?php \submit_button(); ?>
                </form>

            <?php elseif ($tab === 'ai_plus_playground'): ?>
                <h2 class="ai-section-title">🧪 Playground — 文本模型测试</h2>
                <p style="color:#666;">在右侧选择模型，输入内容进行测试。支持多轮对话。</p>
                <div class="ai-playground">
                    <div class="ai-playground-sidebar">
                        <h3>选择模型</h3>
                        <?php foreach ($this->models as $k => $m): ?>
                            <?php if (hasKey($apiKeys[$k] ?? [])): ?>
                                <label class="ai-model-radio">
                                    <input type="radio" name="pg_model" value="<?php echo esc_html($k) ?>" <?php echo  $k === $defModel ? 'checked' : '' ?>>
                                    <?php echo esc_html($m['name']) ?> <span style="color:#999;font-size:11px;">(<?php echo esc_html($apiKeys[$k]['model'] ?: $m['default']) ?>)</span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <h3 style="margin-top:20px;">快捷设置</h3>
                        <label>Max Tokens
                            <input type="number" id="pg_maxtokens" value="2048" min="256" max="8192" step="256">
                        </label>
                    </div>
                    <div class="ai-playground-main">
                        <div id="ai-pg-messages" class="ai-pg-messages"></div>
                        <div class="ai-pg-input">
                            <textarea id="ai-pg-prompt" rows="3" placeholder="输入内容..."></textarea>
                            <button id="ai-pg-send" class="button button-primary">发送</button>
                        </div>
                    </div>
                </div>

            <?php elseif ($tab === 'ai_plus_image'): ?>
                <h2 class="ai-section-title">🎨 图片生成测试</h2>
                <p style="color:#666;">输入图片描述，测试 AI 图像生成效果。</p>
                <div class="ai-playground">
                    <div class="ai-playground-sidebar">
                        <h3>选择模型</h3>
                        <?php
                        $imgDefault = \get_option('ai_plus_image_model', 'tongyi');
                        foreach ($this->imageModels as $k => $imgM): ?>
                            <?php
                                $hasImgKey = hasKey($apiKeys[$k] ?? []);
                                $showCustom = $k === 'custom' && hasKey($apiKeys['custom'] ?? []) && !empty($apiKeys['custom']['base_url']);
                                if ($k === 'custom' && !$showCustom) continue;
                                if ($k !== 'custom' && !$hasImgKey) continue;
                            ?>
                                <label class="ai-model-radio">
                                    <input type="radio" name="img_model" value="<?php echo esc_html($k) ?>" <?php echo  $k === $imgDefault ? 'checked' : '' ?>>
                                    <?php echo esc_html($this->models[$k]['name']) ?> <span style="color:#999;font-size:11px;">(<?php echo esc_html($apiKeys[$k]['image_model'] ?: $imgM) ?>)</span>
                                </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ai-playground-main">
                        <div id="ai-img-result" class="ai-pg-messages" style="text-align:center;"></div>
                        <div class="ai-pg-input">
                            <textarea id="ai-img-prompt" rows="3" placeholder="描述你想要生成的图片..."></textarea>
                            <button id="ai-img-generate" class="button button-primary">生成图片</button>
                        </div>
                    </div>
                </div>


        <?php elseif ($tab === 'ai_plus_stats'): ?>
            <?php include AI_PLUS_PLUGIN_DIR . 'src/Admin/views/stats.php'; ?>

        <?php elseif ($tab === 'ai_plus_license'): ?>
<div class="wrap ai-plus-settings" style="max-width:700px;">
    <h1>🔐 AI Plus 授权管理</h1>
    <p style="color:#555;margin:8px 0 24px;">输入 License Key 激活插件正版授权，激活后可正常使用全部功能。</p>

    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:24px;">
        <h2 style="font-size:14px;margin-bottom:16px;border-bottom:2px solid #2271b1;padding-bottom:6px;display:inline-block;">License 激活</h2>

        <table style="width:100%;font-size:14px;">
            <tr>
                <td style="width:140px;padding:10px 0;font-weight:600;color:#333;">当前状态</td>
                <td style="padding:10px 0;" id="license_status_cell">
                    <span style="color:#72777c;">加载中...</span>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;font-weight:600;color:#333;">绑定域名</td>
                <td style="padding:10px 0;" id="license_domain_cell">
                    <span style="color:#72777c;">—</span>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;font-weight:600;color:#333;">到期时间</td>
                <td style="padding:10px 0;" id="license_expiry_cell">
                    <span style="color:#72777c;">—</span>
                </td>
            </tr>
        </table>

        <hr style="margin:20px 0;border:none;border-top:1px solid #eee;">

        <div style="display:flex;gap:12px;align-items:end;">
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">License Key</label>
                <input type="text" id="license_key_input"
                    value="<?php echo  esc_attr(get_option('ai_plus_license_key', '')) ?>"
                    placeholder="例如：ABCD-EFGH-IJKL-MNOP"
                    style="width:100%;padding:8px 12px;font-size:14px;border:1px solid #dcdcde;border-radius:4px;">
            </div>
            <button id="btn_activate" onclick="aiPlusLicense.activate()" style="padding:8px 24px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;white-space:nowrap;">激活</button>
            <button id="btn_deactivate" onclick="aiPlusLicense.deactivate()" style="padding:8px 24px;background:#d63638;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;display:none;">注销</button>
        </div>
        <p id="license_msg" style="margin-top:10px;font-size:13px;min-height:20px;"></p>
        <p style="margin-top:8px;color:#72777c;font-size:12px;">
            💡 提示：License Key 由插件购买后获得。注销后可重新绑定其他域名。
        </p>
    </div>

    <div id="license_error_box" style="display:none;margin-top:16px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:16px;color:#721c24;font-size:13px;"></div>

    <div style="margin-top:24px;background:#f0f6fc;border-left:4px solid #2271b1;border-radius:4px;padding:16px;font-size:13px;color:#333;line-height:2;">
        <strong>授权说明</strong><br>
        · 每个 License Key 绑定一个域名，不可跨站使用<br>
        · 注销后 Key 可重新绑定其他域名（每月限3次）<br>
        · 到期后插件将暂停使用，请及时续费<br>
        · 如有问题请联系：17854779@qq.com
    </div>
</div>

<script>
var aiPlusLicense = (function() {
    var LICENSE_API = 'https://www.yily.top/licenses/api.php';
    var $key    = document.getElementById('license_key_input');
    var $msg    = document.getElementById('license_msg');
    var $status = document.getElementById('license_status_cell');
    var $domain = document.getElementById('license_domain_cell');
    var $expiry = document.getElementById('license_expiry_cell');
    var $errBox = document.getElementById('license_error_box');
    var $actBtn = document.getElementById('btn_activate');
    var $deaBtn = document.getElementById('btn_deactivate');

    function info(text, color) {
        $msg.style.color = color || '#666';
        $msg.textContent = text;
    }

    function setStatusUI(status, domain, expiry) {
        var badges = {
            'valid':     '<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">✅ 已激活</span>',
            'invalid':   '<span style="background:#f8d7da;color:#721c24;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">❌ 无效</span>',
            'expired':   '<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">⏰ 已过期</span>',
            'inactive':  '<span style="background:#f8d7da;color:#721c24;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">⚠️ 未激活</span>',
            'mismatch':  '<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">🌐 域名不匹配</span>',
        };
        $status.innerHTML = badges[status] || '<span style="color:#72777c;">—</span>';
        $domain.innerHTML  = domain  || '<span style="color:#72777c;">—</span>';
        $expiry.innerHTML  = expiry  || '<span style="color:#72777c;">永久</span>';
        if (status === 'valid') {
            $deaBtn.style.display = 'inline-block';
            $actBtn.textContent = '重新激活';
        } else {
            $deaBtn.style.display = 'none';
            $actBtn.textContent = '激活';
        }
    }

     var storedDomain = null;

     function checkLicense(overrideDomain) {
         var key = $key.value.trim();
         if (!key) {
             setStatusUI('inactive');
             return;
         }
         // 优先用传入域名（激活时记录的真实域名），其次 storedDomain，再次当前浏览器域名
         var checkDomain = overrideDomain || storedDomain || location.host;
         info('校验中...', '#72777c');
         fetch(LICENSE_API + '?action=verify&key=' + encodeURIComponent(key) + '&domain=' + encodeURIComponent(checkDomain))
             .then(function(r){ return r.json(); })
             .then(function(r) {
                 if (r.status === 'valid') {
                     storedDomain = r.domain; // 记住 license 里登记的真实域名
                     setStatusUI('valid', r.domain, r.expiry);
                     info('✅ License 有效（已绑定：' + r.domain + '）', '#155724');
                     $errBox.style.display = 'none';
                     saveKey(key);
                 } else if (r.status === 'expired') {
                     storedDomain = r.domain;
                     setStatusUI('expired');
                     info('❌ ' + (r.msg||''), '#721c24');
                     showError('License 已过期，到期时间：' + (r.expiry||'未知') + '。请续费后重新激活。');
                 } else if (r.status === 'domain_mismatch') {
                     storedDomain = r.domain || storedDomain;
                    storedDomain = r.domain || storedDomain;
                    setStatusUI('mismatch', r.domain || '未知', r.expiry || '未知');
                     info('⚠️ ' + (r.msg||''), '#856404');
                     showError('该 License 已绑定其他域名：' + (r.domain||'未知') + '。如需换域名，请先注销。');
                 } else if (r.status === 'invalid') {
                     setStatusUI('invalid');
                     info('❌ ' + (r.msg||'License 不存在'), '#721c24');
                 } else {
                     info('❌ ' + (r.msg||'校验失败'), '#721c24');
                 }
             })
             .catch(function() {
                 var cached = localStorage.getItem('ai_plus_license_cached');
                 if (cached) {
                     try {
                         var c = JSON.parse(cached);
                         setStatusUI(c.status, c.domain, c.expiry);
                         info('📡 网络异常，显示上次缓存状态', '#856404');
                     } catch(e) {}
                 } else {
                     info('🌐 网络异常，无法校验', '#856404');
                 }
             });
     }


    function saveKey(key) {
        // 通过 admin-ajax 保存（后台页面最可靠的方式）
        var formData = new FormData();
        formData.append('action', 'ai_plus_save_license_key');
        formData.append('nonce', (typeof aiPlusAdmin !== 'undefined' && aiPlusAdmin.nonce) ? aiPlusAdmin.nonce : '');
        formData.append('license_key', key);
        fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) {
                    console.log('License key saved:', r.data);
                } else {
                    console.warn('License key save failed:', r);
                    info('⚠️ Key 保存失败，请手动在「文本模型」页面保存设置', '#856404');
                }
            })
            .catch(function(e){
                console.warn('Save key failed:', e);
                info('⚠️ Key 保存失败（网络问题），请刷新重试', '#856404');
            });
    }

    function showError(msg) {
        $errBox.textContent = '⚠️ ' + msg;
        $errBox.style.display = 'block';
    }

    function activate() {
        var key = $key.value.trim();
        if (!key) { info('请输入 License Key', '#721c24'); return; }
        $actBtn.disabled = true;
        $actBtn.textContent = '激活中...';
        info('', '');
        $errBox.style.display = 'none';

        fetch(LICENSE_API + '?action=activate&key=' + encodeURIComponent(key) + '&domain=' + encodeURIComponent(location.host))
            .then(function(r){ return r.json(); })
            .then(function(r) {
                $actBtn.disabled = false;
                if (r.status === 'activated') {
                    storedDomain = r.domain; // 记住真实域名
                    setStatusUI('valid', r.domain, r.expiry);
                    info('🎉 激活成功！域名：' + r.domain, '#155724');
                    $errBox.style.display = 'none';
                    localStorage.setItem('ai_plus_license_cached', JSON.stringify({
                        status: 'valid', domain: r.domain, expiry: r.expiry
                    }));
                    saveKey(key);
                } else {
                    setStatusUI(r.status);
                    info('❌ ' + (r.msg||'激活失败'), '#721c24');
                    if (r.msg) showError(r.msg);
                }
            })
            .catch(function(e) {
                $actBtn.disabled = false;
                $actBtn.textContent = '激活';
                info('🌐 网络异常，激活失败', '#721c24');
            });
    }

    function deactivate() {
        if (!confirm('确定要注销当前 License 吗？注销后可重新绑定其他域名。')) return;
        var key = $key.value.trim();
        if (!key) { info('没有可注销的 License', '#721c24'); return; }
        $deaBtn.disabled = true;
        $deaBtn.textContent = '注销中...';

        fetch(LICENSE_API + '?action=deactivate&key=' + encodeURIComponent(key))
            .then(function(r){ return r.json(); })
            .then(function(r) {
                $deaBtn.disabled = false;
                if (r.status === 'deactivated') {
                    storedDomain = null;
                    setStatusUI('inactive');
                    info('已注销，可重新绑定其他域名', '#856404');
                    localStorage.removeItem('ai_plus_license_cached');
                    saveKey('');
                } else {
                    info('❌ ' + (r.msg||'注销失败'), '#721c24');
                }
            })
            .catch(function() {
                $deaBtn.disabled = false;
                info('🌐 网络异常', '#721c24');
            });
    }

    // 初始化：自动校验
    $key.addEventListener('input', function() {
        // 清除缓存，强制重新检查
        localStorage.removeItem('ai_plus_license_cached');
        checkLicense();
    });

    // 页面加载时自动检查
    if ($key.value.trim()) {
        checkLicense();
    } else {
        setStatusUI('inactive');
    }

    return { activate: activate, deactivate: deactivate };
})();
</script>

        <?php elseif ($tab === 'ai_plus_seo'): ?>
            <?php include AI_PLUS_PLUGIN_DIR . 'src/Admin/views/seo.php'; ?>

            <?php elseif ($tab === 'ai_plus_about'): ?>
<div class="wrap ai-plus-settings" style="max-width:960px;">
    <h1>⚡ AI Plus</h1>
    <p style="font-size:16px;color:#555;margin-top:4px;">为 WordPress 提供 AI 生成、聊天、图片等内容助手功能。</p>
    <hr style="margin:24px 0;">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:32px;">

        <div>
            <h2 style="font-size:15px;margin-bottom:14px;border-bottom:2px solid #2271b1;padding-bottom:6px;display:inline-block;">🎯 功能概览</h2>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🖊️ 文章生成</td><td style="padding:7px 0 7px 12px;color:#444;">输入标题，AI 自动生成完整文章</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">📝 内容扩写</td><td style="padding:7px 0 7px 12px;color:#444;">在现有内容上续写更多细节</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🏷️ SEO 优化</td><td style="padding:7px 0 7px 12px;color:#444;">生成标题、描述、关键词建议</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🏷️ 自动标签</td><td style="padding:7px 0 7px 12px;color:#444;">分析内容生成相关标签</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🖼️ 特色图生成</td><td style="padding:7px 0 7px 12px;color:#444;">根据文章内容生成封面图片</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🎨 图片生成 Playground</td><td style="padding:7px 0 7px 12px;color:#444;">对话测试文生图模型，支持保存到媒体库</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">💬 网站客服浮窗</td><td style="padding:7px 0 7px 12px;color:#444;">前台右下角 AI 助手，内容感知</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;font-weight:600;color:#2271b1;">🧱 Gutenberg 区块</td><td style="padding:7px 0 7px 12px;color:#444;">聊天区块 / 图片生成区块，发布后可交互</td></tr>
                <tr><td style="padding:7px 0;font-weight:600;color:#2271b1;">🔗 Playground</td><td style="padding:7px 0 7px 12px;color:#444;">对话测试 / 保存草稿（Markdown→HTML）/ 复制</td></tr>
            </table>
        </div>

        <div>
            <h2 style="font-size:15px;margin-bottom:14px;border-bottom:2px solid #2271b1;padding-bottom:6px;display:inline-block;">🚀 快速开始</h2>
            <div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:14px 16px;border-radius:4px;margin-bottom:16px;font-size:13px;line-height:2;">
                <strong>第一步：配置 API Key</strong><br>
                进入「文本模型」Tab，为所需平台填入 API Key（其他字段留空即可）。<br><br>
                <strong>第二步：开启网站客服</strong><br>
                进入「💬 网站客服」Tab，勾选开启，前台右下角出现 AI 助手浮窗。<br><br>
                <strong>第三步：开始写文章</strong><br>
                在 Gutenberg 编辑器中使用「AI Plus 小助手」侧边栏，或在文章中插入聊天/图片区块。
            </div>

            <h2 style="font-size:15px;margin-bottom:10px;border-bottom:2px solid #2271b1;padding-bottom:6px;display:inline-block;">📋 支持的模型</h2>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#f9f9f9;"><th style="text-align:left;padding:5px 8px;font-weight:600;">平台</th><th style="text-align:left;padding:5px 8px;">文本模型</th><th style="text-align:left;padding:5px 8px;">文生图</th></tr></thead>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:4px 8px;">通义千问</td><td style="padding:4px 8px;">qwen-turbo</td><td style="padding:4px 8px;">✅ qwen-image-2.0-pro</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:4px 8px;">智谱 GLM</td><td style="padding:4px 8px;">glm-5</td><td style="padding:4px 8px;">✅ cogview-3</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:4px 8px;">MiniMax</td><td style="padding:4px 8px;">MiniMax-M2.7</td><td style="padding:4px 8px;">✅ image-01</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:4px 8px;">Kimi</td><td style="padding:4px 8px;">kimi-k2.5</td><td style="padding:4px 8px;">—</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:4px 8px;">DeepSeek</td><td style="padding:4px 8px;">deepseek-chat</td><td style="padding:4px 8px;">—</td></tr>
                <tr><td style="padding:4px 8px;">自定义代理</td><td style="padding:4px 8px;">自填</td><td style="padding:4px 8px;">✅ OpenAI 兼容</td></tr>
            </table>
        </div>
    </div>

    <hr style="margin:0 0 28px;">

    <h2 style="font-size:15px;margin-bottom:14px;">🧱 Gutenberg 区块说明</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
            <h3 style="margin:0 0 8px;font-size:14px;">🤖 AI Plus 聊天</h3>
            <p style="margin:0;font-size:13px;color:#555;line-height:1.8;">在文章中插入聊天窗口。文章发布后，读者可以在页面内实时对话，AI 会结合当前文章内容（自动获取）作答，支持上下文记忆。</p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
            <h3 style="margin:0 0 8px;font-size:14px;">🖼️ AI Plus 图片生成</h3>
            <p style="margin:0;font-size:13px;color:#555;line-height:1.8;">插入后输入图片描述，点「生成」直接替换为图片。支持通义千问、智谱 GLM、MiniMax，可切换模型。</p>
        </div>
    </div>

    <h2 style="font-size:15px;margin-bottom:14px;">💬 网站客服说明</h2>
    <div style="background:#f0f6fc;border-radius:6px;padding:16px;font-size:13px;line-height:2.1;color:#333;">
        <strong>开启方式：</strong>「💬 网站客服」Tab → 勾选开启<br>
        <strong>内容感知：</strong>自动读取当前文章内容作为上下文，读者提问与文章相关的问题时，AI 基于文章内容作答<br>
        <strong>模型切换：</strong>浮窗内可切换已配置的其他文本模型<br>
        <strong>会话记忆：</strong>同一会话内保留聊天历史
    </div>

    <!-- 翻译功能 -->
    <h2 style="font-size:15px;margin-bottom:14px;">🌐 翻译功能</h2>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;font-size:13px;line-height:2;margin-bottom:28px;">
        在 Gutenberg 编辑器右侧边栏「🌐 翻译」面板中使用：<br>
        <strong>第一步：</strong>在编辑器中写入或粘贴要翻译的内容<br>
        <strong>第二步：</strong>选择源语言（留空 = 自动检测）和目标语言<br>
        <strong>第三步：</strong>点击「翻译并替换编辑器内容」，AI 翻译后直接覆盖编辑器<br>
        <strong>支持语言：</strong>自动检测、英语、中文简体、中文繁体、日语、韩语、法语、德语、西班牙语、葡萄牙语、俄语、阿拉伯语、意大利语、泰语、越南语（共 14 种）
    </div>

    <!-- 短代码 -->
    <h2 style="font-size:15px;margin-bottom:14px;">📋 短代码（Shortcode）</h2>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;font-size:13px;margin-bottom:28px;">
        在任意文章或页面中插入 AI 聊天窗口：<br>
        <code style="background:#f0f6fc;padding:2px 8px;border-radius:3px;margin:8px 0;display:inline-block;">[ai_plus_chat]</code><br>
        可选参数：<code>model</code> — 指定默认模型，如 <code>model="kimi"</code> / <code>model="deepseek"</code><br>
        可选平台值：<code>zhipu</code>（智谱）· <code>tongyi</code>（通义千问）· <code>minimax</code>（MiniMax）· <code>kimi</code>（Kimi）· <code>deepseek</code>（DeepSeek）· <code>custom</code>（自定义代理）<br>
        <strong>效果：</strong>下拉框只显示已配置 API Key 的平台，切换后实时生效，无需刷新页面。
    </div>

    <!-- API 申请地址 -->
    <h2 style="font-size:15px;margin-bottom:14px;">🔗 支持的模型 & API 申请地址</h2>
    <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:28px;">
        <thead><tr style="background:#f0f0f0;"><th style="padding:7px 10px;border:1px solid #ddd;text-align:left;font-weight:600;">平台</th><th style="padding:7px 10px;border:1px solid #ddd;text-align:left;font-weight:600;">文本模型</th><th style="padding:7px 10px;border:1px solid #ddd;text-align:left;font-weight:600;">文生图模型</th><th style="padding:7px 10px;border:1px solid #ddd;text-align:left;font-weight:600;">API 申请地址</th></tr></thead>
        <tbody>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">通义千问</td><td style="padding:6px 10px;">qwen-turbo / qwen-plus / qwen-max</td><td style="padding:6px 10px;">qwen-image-2.0-pro</td><td style="padding:6px 10px;"><a href="https://dashscope.console.aliyun.com/" target="_blank">dashscope.console.aliyun.com</a></td></tr>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">智谱 GLM</td><td style="padding:6px 10px;">glm-5 / glm-4-plus / glm-4-flashx</td><td style="padding:6px 10px;">cogview-3</td><td style="padding:6px 10px;"><a href="https://open.bigmodel.cn/" target="_blank">open.bigmodel.cn</a></td></tr>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">MiniMax</td><td style="padding:6px 10px;">MiniMax-M2.7 / abab-7.5</td><td style="padding:6px 10px;">image-01</td><td style="padding:6px 10px;"><a href="https://www.minimax.io/" target="_blank">minimax.io（API Hub）</a></td></tr>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">Kimi</td><td style="padding:6px 10px;">kimi-k2.5 / moonshot-v1-128k</td><td style="padding:6px 10px;">—</td><td style="padding:6px 10px;"><a href="https://platform.moonshot.cn/" target="_blank">platform.moonshot.cn</a></td></tr>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">DeepSeek</td><td style="padding:6px 10px;">deepseek-chat / deepseek-coder</td><td style="padding:6px 10px;">—</td><td style="padding:6px 10px;"><a href="https://platform.deepseek.com/" target="_blank">platform.deepseek.com</a></td></tr>
            <tr style="border:1px solid #ddd;"><td style="padding:6px 10px;">自定义</td><td style="padding:6px 10px;">任意 OpenAI 兼容模型</td><td style="padding:6px 10px;">✅ OpenAI 兼容</td><td style="padding:6px 10px;">按需配置 Base URL + API Key</td></tr>
        </tbody>
    </table>

    <hr style="margin:28px 0 20px;">
    <p style="color:#999;font-size:12px;">Zuo AI Plus</p>
</div>
<?php endif; ?>
        </div>
        <?php
    }
}
