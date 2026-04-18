<?php
/**
 * AI Plus 管理后台
 */
namespace ZuoAIPlus\Admin;

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

    // 支持文生图的平台及默认模型（不填的平台也可手动输入模型名）
    private $imageModels = [
        'zhipu'   => 'cogview-3',
        'tongyi'  => 'qwen-image-2.0-pro',
        'minimax' => 'image-01',
        'kimi'    => '',  // 可手动填写模型名（如 kimi-v1 等）
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

        // 统计页面专用样式
        if ($page === 'ai_plus_stats') {
            \wp_enqueue_style('ai-plus-stats', AI_PLUS_PLUGIN_URL . 'Assets/css/admin-stats.css', [], AI_PLUS_VERSION);
        }
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
            ['wp-edit-post', 'wp-plugins', 'wp-element', 'wp-data', 'wp-components', 'wp-api', 'wp-blocks', 'wp-block-editor', 'wp-rich-text'],
            AI_PLUS_VERSION,
            true
        );
        $defModel   = \get_option('ai_plus_default_model', 'zhipu');
        // 只传配置状态（是否有 key、是否有 custom 自定义模型），不传实际 key 值
        // 兼容新旧存储格式：新格式数组 / 旧格式字符串
        $apiKeysRaw = \get_option('ai_plus_api_keys', []);
        $apiKeysConfigured = [];
        foreach ($this->models as $k => $m) {
            $saved = $apiKeysRaw[$k] ?? null;
            // 新格式：数组 ['api_key' => '...', 'model' => '...']
            if (is_array($saved)) {
                $hasKey = !empty($saved['api_key']);
            }
            // 旧格式：直接是 API key 字符串
            elseif (is_string($saved)) {
                $hasKey = !empty($saved);
            } else {
                $hasKey = false;
            }
            $apiKeysConfigured[$k] = [
                'configured' => $hasKey,
                'customModel' => is_array($saved) ? ($saved['model'] ?? '') : '',
            ];
        }
        \wp_localize_script('ai-plus-gutenberg-sidebar', 'aiPlusConfig', [
            'apiUrl'       => \rest_url('ai-plus/v1/'),
            'nonce'        => \wp_create_nonce('wp_rest'),
            'models'       => $this->models,
            'apiKeys'      => $apiKeysConfigured,
            'defaultModel' => $defModel,
        ]);
    }

    public function addMenu(): void
    {
        \add_menu_page('Zuo AI Plus', 'Zuo AI Plus', 'manage_options', 'ai_plus', [$this, 'renderPage'], '', 80);
        \add_submenu_page('ai_plus', 'Zuo AI Plus 模型设置', '模型设置', 'manage_options', 'ai_plus', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '文生图', '文生图', 'manage_options', 'ai_plus_image', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '文生文', '文生文', 'manage_options', 'ai_plus_playground', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '📊 统计', '统计', 'manage_options', 'ai_plus_stats', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '🔍 SEO 诊断', 'SEO 诊断', 'manage_options', 'ai_plus_seo', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '授权管理', '授权管理', 'manage_options', 'ai_plus_license', [$this, 'renderPage']);
        \add_submenu_page('ai_plus', '关于', '关于', 'manage_options', 'ai_plus_about', [$this, 'renderPage']);
        // 移除重复的顶层菜单项
        global $submenu;
        if (isset($submenu['ai_plus'][0])) {
            unset($submenu['ai_plus'][0]);
        }
    }

    public function registerSettings(): void
    {
        \register_setting('ai_plus_settings', 'ai_plus_api_keys', ['sanitize_callback' => function($v) {
            // 兼容新旧存储格式：新格式数组 / 旧格式字符串
            if (!is_array($v)) return [];
            $out = [];
            foreach ($v as $k => $item) {
                if (is_array($item)) {
                    $out[$k] = [
                        'api_key'  => isset($item['api_key']) && is_string($item['api_key']) ? sanitize_text_field($item['api_key']) : '',
                        'model'    => isset($item['model']) && is_string($item['model']) ? sanitize_text_field($item['model']) : '',
                        'base_url' => isset($item['base_url']) && is_string($item['base_url']) ? esc_url_raw($item['base_url']) : '',
                        'image_model' => isset($item['image_model']) && is_string($item['image_model']) ? sanitize_text_field($item['image_model']) : '',
                    ];
                } elseif (is_string($item)) {
                    // 旧格式：直接是 API key 字符串
                    $out[$k] = sanitize_text_field($item);
                }
            }
            return $out;
        }]);
        \register_setting('ai_plus_settings', 'ai_plus_default_model', ['sanitize_callback' => function($v) { return sanitize_text_field($v); }]);
        \register_setting('ai_plus_settings', 'ai_plus_image_model', ['sanitize_callback' => function($v) { return sanitize_text_field($v); }]);
        \register_setting('ai_plus_settings', 'ai_plus_image_size', ['sanitize_callback' => function($v) { return sanitize_text_field($v); }]);
        // 迁移旧 key
        $old = \get_option('ai_plus_featured_image_model', '');
        if ($old && !\get_option('ai_plus_image_model', '')) {
            \update_option('ai_plus_image_model', $old);
        }
        \register_setting('ai_plus_settings', 'ai_plus_knowledge_base', ['sanitize_callback' => function($v){ return sanitize_textarea_field($v); }]);
    \register_setting('ai_plus_settings', 'ai_plus_license_key', ['sanitize_callback' => [$this, 'sanitizeLicenseKey']]);
        \register_setting('ai_plus_settings', 'ai_plus_license_server_url', ['sanitize_callback' => [$this, 'sanitizeLicenseUrl']]);
        \register_setting('ai_plus_settings', 'ai_plus_chat_enabled', ['sanitize_callback' => function($v) { return (bool)$v; }]);
        \register_setting('ai_plus_settings', 'ai_plus_cache_enabled', ['sanitize_callback' => function($v) { return (bool)$v; }]);
        \register_setting('ai_plus_settings', 'ai_plus_cache_ttl', ['sanitize_callback' => function($v) { return intval($v); }]);
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
            <h1>Zuo AI Plus</h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus', 'ai_plus_admin')); ?>"        class="nav-tab <?php echo  $tab==='ai_plus'        ? 'nav-tab-active' : '' ?>">模型设置</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_image', 'ai_plus_admin')); ?>"  class="nav-tab <?php echo  $tab==='ai_plus_image'  ? 'nav-tab-active' : '' ?>">文生图</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_playground', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_playground' ? 'nav-tab-active' : '' ?>">文生文</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_stats', 'ai_plus_admin')); ?>"   class="nav-tab <?php echo  $tab==='ai_plus_stats'   ? 'nav-tab-active' : '' ?>">📊 统计</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_seo', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_seo' ? 'nav-tab-active' : '' ?>">🔍 SEO 诊断</a>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai_plus_license', 'ai_plus_admin')); ?>" class="nav-tab <?php echo  $tab==='ai_plus_license' ? 'nav-tab-active' : '' ?>">授权管理</a>
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
                                <textarea name="ai_plus_knowledge_base" rows="4" style="width:100%;max-width:600px;"
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
                                <?php if ($hasImg || $id === 'minimax' || $id === 'kimi'): // minimax/kimi 可手动填模型 ?>
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
                                <div class="ai-model-card__field" style="margin-top:8px;">
                                    <button type="button" class="button button-secondary button-small" style="font-size:12px;" onclick="testModel('<?php echo esc_html($id) ?>')" id="btn_test_<?php echo esc_html($id) ?>">🔗 测试连接</button>
                                    <span id="test_result_<?php echo esc_html($id) ?>" style="font-size:12px;margin-left:8px;vertical-align:middle;"></span>
                                </div>
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
                                            $imgVal = $apiKeys[$k]['image_model'] ?? '';
                                            $showCustom = $k === 'custom' && hasKey($apiKeys['custom'] ?? []) && !empty($apiKeys['custom']['base_url']) && !empty($imgVal);
                                            if ($k === 'custom' && !$showCustom) continue;
                                            // 必须同时有 API Key 和已配置的文生图模型才显示
                                            if ($k !== 'custom' && (!$hasImgKey || empty($imgVal))) continue;
                                        ?>
                                            <option value="<?php echo esc_html($k) ?>" <?php echo  $k === $imgModel ? 'selected' : '' ?>><?php echo esc_html($this->models[$k]['name']) ?> — <?php echo esc_html($imgVal) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">特色图专用模型（通义/智谱/MiniMax/自定义代理）</p>
                            </td>
                        </tr>
                        <tr>
                            <th>特色图尺寸</th>
                            <td>
                                <select name="ai_plus_image_size">
                                    <option value="1024*1024" <?php echo  \get_option('ai_plus_image_size', '1216*832') === '1024*1024' ? 'selected' : '' ?>>正方形 1024×1024（1:1）</option>
                                    <option value="1216*832"  <?php echo  \get_option('ai_plus_image_size', '1216*832') === '1216*832'  ? 'selected' : '' ?>>横版 1216×832（16:9 推荐）</option>
                                    <option value="832*1216"  <?php echo  \get_option('ai_plus_image_size', '1216*832') === '832*1216'  ? 'selected' : '' ?>>竖版 832×1216（9:16）</option>
                                    <option value="1920*1080" <?php echo  \get_option('ai_plus_image_size', '1216*832') === '1920*1080' ? 'selected' : '' ?>>横版高清 1920×1080（HD）</option>
                                    <option value="1080*1920" <?php echo  \get_option('ai_plus_image_size', '1216*832') === '1080*1920' ? 'selected' : '' ?>>竖版高清 1080×1920（HD）</option>
                                </select>
                                <p class="description">生成的特色图尺寸。如果提示「size参数不合法」，请换一个尺寸试试（不同账号模型权限不同）</p>
                            </td>
                        </tr>
                    </table>

                    <!-- AI 缓存设置 -->
                    <h2 class="ai-section-title">⚡ 性能设置 — AI 响应缓存</h2>
                    <table class="form-table">
                        <tr>
                            <th>启用响应缓存</th>
                            <td>
                                <label><input type="checkbox" name="ai_plus_cache_enabled" value="1" <?php echo \checked(\get_option('ai_plus_cache_enabled', '1'), '1', false) ?>>
                                开启后，相同请求（模型+内容相同）在 TTL 时间内直接取缓存，不重复调用 API，省 Token 费用。</label>
                            </td>
                        </tr>
                        <tr>
                            <th>缓存有效期</th>
                            <td>
                                <select name="ai_plus_cache_ttl">
                                    <option value="600" <?php echo  \get_option('ai_plus_cache_ttl', 3600) == 600 ? 'selected' : '' ?>>10 分钟</option>
                                    <option value="1800" <?php echo  \get_option('ai_plus_cache_ttl', 3600) == 1800 ? 'selected' : '' ?>>30 分钟</option>
                                    <option value="3600" <?php echo  \get_option('ai_plus_cache_ttl', 3600) == 3600 ? 'selected' : '' ?>>1 小时（默认）</option>
                                    <option value="7200" <?php echo  \get_option('ai_plus_cache_ttl', 3600) == 7200 ? 'selected' : '' ?>>2 小时</option>
                                    <option value="14400" <?php echo  \get_option('ai_plus_cache_ttl', 3600) == 14400 ? 'selected' : '' ?>>4 小时</option>
                                </select>
                                <p class="description">仅对文章生成、摘要、翻译等文本请求生效。图片生成不受缓存影响。</p>
                            </td>
                        </tr>
                        <tr>
                            <th>手动清除缓存</th>
                            <td>
                                <button type="button" class="button button-secondary" onclick="flushAiCache()" id="btn_flush_cache">🗑️ 清除所有缓存</button>
                                <span id="flush_cache_result" style="margin-left:10px;font-size:13px;"></span>
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

                <script>
                function testModel(modelId) {
                    var card = document.querySelector('[id^="btn_test_' + modelId + '"]').closest('.ai-model-card');
                    var apiKey = card.querySelector('input[name^="ai_plus_api_keys"]').value.trim();
                    var baseUrl = card.querySelector('input[name*="[base_url]"]').value.trim();
                    var modelName = card.querySelector('input[name*="[model]"]').value.trim();
                    var btn = document.getElementById('btn_test_' + modelId);
                    var result = document.getElementById('test_result_' + modelId);

                    if (!apiKey) {
                        result.innerHTML = '<span style="color:#e53e3e;">请先填写 API Key</span>';
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = '测试中...';
                    result.innerHTML = '';

                    var formData = new FormData();
                    formData.append('action', 'ai_plus_test_model');
                    formData.append('nonce', aiPlusAdmin.nonce);
                    formData.append('model_id', modelId);
                    formData.append('api_key', apiKey);
                    formData.append('base_url', baseUrl);
                    formData.append('model_name', modelName);

                    fetch(ajaxurl, {method: 'POST', body: formData})
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = '🔗 测试连接';
                            if (data.success) {
                                result.innerHTML = '<span style="color:#10b981;">' + (data.data.message ? data.data.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '') + ' · ' + (data.data.elapsed || '') + '</span>';
                            } else {
                                result.innerHTML = '<span style="color:#e53e3e;">' + (data.data.message ? data.data.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '测试失败') + '</span>';
                            }
                        })
                        .catch(function(err) {
                            btn.disabled = false;
                            btn.textContent = '🔗 测试连接';
                            result.innerHTML = '<span style="color:#e53e3e;">请求失败: ' + (err.message ? err.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '网络错误') + '</span>';
                        });
                }

                function flushAiCache() {
                    var btn = document.getElementById('btn_flush_cache');
                    var result = document.getElementById('flush_cache_result');
                    btn.disabled = true;
                    btn.textContent = '清除中...';
                    var formData = new FormData();
                    formData.append('action', 'ai_plus_flush_cache');
                    formData.append('nonce', aiPlusAdmin.nonce);
                    fetch(ajaxurl, {method: 'POST', body: formData})
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = '🗑️ 清除所有缓存';
                            if (data.success) {
                                result.innerHTML = '<span style="color:#10b981;">' + (data.data.message ? data.data.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '清除成功') + '</span>';
                            } else {
                                result.innerHTML = '<span style="color:#e53e3e;">' + (data.data.message ? data.data.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '清除失败') + '</span>';
                            }
                        })
                        .catch(function(err) {
                            btn.disabled = false;
                            btn.textContent = '🗑️ 清除所有缓存';
                            result.innerHTML = '<span style="color:#e53e3e;">请求失败: ' + (err.message ? err.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '网络错误') + '</span>';
                        });
                }
                </script>

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
                                $imgModelVal = $apiKeys[$k]['image_model'] ?? '';
                                // 文生图列表：仅显示用户明确配置了文生图模型的平台
                                $hasImgModel = !empty($imgModelVal);
                                $showCustom = $k === 'custom' && hasKey($apiKeys['custom'] ?? []) && !empty($apiKeys['custom']['base_url']) && !empty($imgModelVal);
                                if ($k === 'custom' && !$showCustom) continue;
                                if ($k !== 'custom' && (!$hasImgKey || !$hasImgModel)) continue;
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
                    info('✅ License Key 保存成功', '#0d6efd');
                } else {
                    info('⚠️ Key 保存失败，请手动在「文本模型」页面保存设置', '#856404');
                }
            })
            .catch(function(){
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
                <?php include AI_PLUS_PLUGIN_DIR . 'src/Admin/views/about.php'; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
