<?php
/**
 * 导航网站管理后台
 */
namespace ZuoAIPlus\Admin;

if (!defined('ABSPATH')) exit;

class Navigation_Init
{
    public function __construct()
    {
        // Meta 字段保存
        add_action('save_post_nav_site', [$this, 'saveMeta'], 10, 3);
        // 管理列表定制
        add_filter('manage_nav_site_posts_columns', [$this, 'addListColumns']);
        add_action('manage_nav_site_posts_custom_column', [$this, 'renderListColumn'], 10, 2);
        // REST API 注册
        add_action('rest_api_init', function () {
            (new \ZuoAIPlus\Controllers\NavigationController())->registerRoutes();
        });
        // 批量导入菜单
        add_action('admin_menu', [$this, 'addImportMenu']);
        // 处理批量导入
        add_action('admin_init', [$this, 'handleBulkImport']);
        // 状态检测菜单
        add_action('admin_menu', [$this, 'addToolsMenu']);
    }

    /**
     * 添加批量导入菜单
     */
    public function addImportMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=nav_site',
            '批量导入',
            '批量导入',
            'manage_options',
            'nav-bulk-import',
            [$this, 'renderImportPage']
        );
    }

    /**
     * 渲染批量导入页面
     */
    public function renderImportPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('无权访问', 'zuo-ai-plus'));
        }
        ?>
        <div class="wrap">
            <h1>批量导入导航网站</h1>
            <p>每行一个网站，格式：<code>名称|网址|分类ID|描述</code>（后两项可选）</p>
            <p>示例：<code>百度|https://www.baidu.com|1|中国最大的搜索引擎</code></p>
            
            <form method="post">
                <?php wp_nonce_field('nav_bulk_import', 'nav_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bulk_data">网站数据</label></th>
                        <td>
                            <textarea name="bulk_data" id="bulk_data" rows="15" cols="80" 
                                placeholder="每行一个网站，格式：名称|网址|分类ID|描述
百度|https://www.baidu.com|1|搜索引擎
淘宝|https://www.taobao.com|2|购物网站"></textarea>
                            <p class="description">支持批量导入，每行一个网站，用 | 分隔</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('开始导入', 'primary', 'import_submit'); ?>
            </form>

            <h2>现有分类</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>分类名称</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $categories = get_terms(['taxonomy' => 'nav_category', 'hide_empty' => false]);
                    foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat->term_id; ?></td>
                        <td><?php echo esc_html($cat->name); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * 处理批量导入
     */
    public function handleBulkImport(): void
    {
        if (!isset($_POST['import_submit']) || !isset($_POST['nav_import_nonce'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['nav_import_nonce'])), 'nav_bulk_import')) {
            wp_die(__('安全验证失败', 'zuo-ai-plus'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('无权访问', 'zuo-ai-plus'));
        }

        $bulkData = sanitize_textarea_field($_POST['bulk_data'] ?? '');
        if (empty($bulkData)) {
            add_settings_error('nav_import', 'empty_data', '请输入导入数据', 'error');
            return;
        }

        // 防止大数据量导入时超时
        ignore_user_abort(true);
        set_time_limit(0);

        $lines = explode("\n", $bulkData);
        $imported = 0;
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) < 2) {
                $errors[] = "格式错误: $line";
                continue;
            }

            $name = trim($parts[0]);
            $url = esc_url_raw(trim($parts[1]));
            $catId = isset($parts[2]) ? intval($parts[2]) : 0;
            $description = isset($parts[3]) ? sanitize_text_field(trim($parts[3])) : '';

            if (empty($name) || empty($url)) {
                $errors[] = "名称或网址为空: $line";
                continue;
            }

            // 验证 URL 格式
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = "URL 格式无效: $url";
                continue;
            }

            // 检查是否已存在（使用 prepare 防止 SQL 注入）
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'nav_url' AND meta_value = %s 
                 LIMIT 1",
                $url
            ));
            if ($existing) {
                $errors[] = "已存在: $name";
                continue;
            }

            // 创建网站
            $postData = [
                'post_title'   => sanitize_text_field($name),
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'nav_site',
            ];
            $postId = wp_insert_post($postData);

            if (is_wp_error($postId)) {
                $errors[] = "创建失败: $name";
                continue;
            }

            // 保存元数据
            update_post_meta($postId, 'nav_name', sanitize_text_field($name));
            update_post_meta($postId, 'nav_url', $url);
            update_post_meta($postId, 'nav_description', $description);

            // 设置分类
            if ($catId > 0) {
                wp_set_object_terms($postId, [$catId], 'nav_category');
            }

            $imported++;
        }

        if ($imported > 0) {
            add_settings_error('nav_import', 'success', "成功导入 $imported 个网站", 'success');
        }
        if (!empty($errors)) {
            add_settings_error('nav_import', 'errors', '错误: ' . implode(', ', array_slice($errors, 0, 5)), 'error');
        }

        // 重定向回导入页面
        wp_redirect(add_query_arg(['page' => 'nav-bulk-import', 'settings-updated' => 'true'], admin_url('edit.php?post_type=nav_site')));
        exit;
    }

    /**
     * 添加工具菜单
     */
    public function addToolsMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=nav_site',
            '工具',
            '工具',
            'manage_options',
            'nav-tools',
            [$this, 'renderToolsPage']
        );
    }

    /**
     * 渲染工具页面
     */
    public function renderToolsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('无权访问', 'zuo-ai-plus'));
        }

        // 获取统计
        $totalSites = wp_count_posts('nav_site')->publish;
        $totalClicks = 0;
        $offlineSites = [];

        $allSites = get_posts([
            'post_type'      => 'nav_site',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($allSites as $site) {
            $clicks = (int) get_post_meta($site->ID, 'nav_clicks', true);
            $totalClicks += $clicks;

            $status = get_post_meta($site->ID, 'nav_status_check', true);
            if ($status && ($status['is_online'] === false)) {
                $offlineSites[] = [
                    'id'    => $site->ID,
                    'title' => $site->post_title,
                    'url'   => get_post_meta($site->ID, 'nav_url', true),
                    'msg'   => $status['message'],
                ];
            }
        }
        ?>
        <div class="wrap">
            <h1>导航工具</h1>

            <h2>统计信息</h2>
            <table class="wp-list-table widefat fixed striped" style="max-width: 400px;">
                <tr>
                    <td>网站总数</td>
                    <td><strong><?php echo number_format($totalSites); ?></strong></td>
                </tr>
                <tr>
                    <td>总点击数</td>
                    <td><strong><?php echo number_format($totalClicks); ?></strong></td>
                </tr>
                <tr>
                    <td>失效网站</td>
                    <td><strong style="color: #e65054;"><?php echo count($offlineSites); ?></strong></td>
                </tr>
            </table>

            <h2 style="margin-top: 30px;">批量检测</h2>
            <p>检测所有网站的可访问状态（每次最多50个）</p>
            <button type="button" class="button button-primary" id="bulk-check-btn" onclick="startBulkCheck()">
                开始检测
            </button>
            <div id="check-progress" style="margin-top: 15px; display: none;">
                <p>检测中... <span id="check-count">0</span> / <?php echo $totalSites; ?></p>
                <div id="check-results"></div>
            </div>

            <?php if (!empty($offlineSites)): ?>
            <h2 style="margin-top: 30px; color: #e65054;">⚠️ 失效网站</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>网站</th>
                        <th>网址</th>
                        <th>错误</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offlineSites as $site): ?>
                    <tr>
                        <td><a href="<?php echo admin_url('post.php?post=' . $site['id'] . '&action=edit'); ?>"><?php echo esc_html($site['title']); ?></a></td>
                        <td><?php echo esc_html($site['url']); ?></td>
                        <td><?php echo esc_html($site['msg']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $site['id'] . '&action=edit'); ?>" class="button button-small">编辑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        function startBulkCheck() {
            var btn = document.getElementById('bulk-check-btn');
            var progress = document.getElementById('check-progress');
            var count = document.getElementById('check-count');
            var results = document.getElementById('check-results');

            btn.disabled = true;
            btn.textContent = '检测中...';
            progress.style.display = 'block';

            var checked = 0;
            var total = <?php echo $totalSites; ?>;

            function checkBatch() {
                fetch('<?php echo esc_url(rest_url("ai-plus/v1/nav/bulk-check-status")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                    },
                    body: JSON.stringify({ limit: 10 })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        checked += res.checked;
                        count.textContent = checked;

                        res.data.forEach(function(item) {
                            var color = item.status.is_online ? '#00a32a' : '#e65054';
                            results.innerHTML += '<div style="color:' + color + '; font-size: 12px;">' +
                                item.title + ': ' + item.status.message + '</div>';
                        });

                        if (checked < total) {
                            setTimeout(checkBatch, 1000);
                        } else {
                            btn.textContent = '检测完成';
                            location.reload();
                        }
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = '检测失败，重试';
                });
            }

            checkBatch();
        }
        </script>
        <?php
    }

    /**
     * 保存导航网站 Meta
     */
    public function saveMeta(int $postId, \WP_Post $post, bool $update): void
    {
        if ($post->post_type !== 'nav_site') return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $postId)) return;
        if (!isset($_POST['nav_meta_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nav_meta_nonce'])), 'nav_site_save_' . $postId)) {
            return;
        }

        // 普通文本字段
        $textFields = ['nav_url', 'nav_name', 'nav_keywords', 'nav_description', 'nav_ai_summary', 'nav_status'];
        foreach ($textFields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($postId, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // URL 字段需要验证
        $urlFields = ['nav_logo', 'nav_screenshot'];
        foreach ($urlFields as $field) {
            if (isset($_POST[$field])) {
                $url = esc_url_raw($_POST[$field]);
                update_post_meta($postId, $field, $url);
            }
        }

        // 同步标题和 Slug
        if (!empty($_POST['nav_name'])) {
            $postData = ['ID' => $postId, 'post_title' => sanitize_text_field($_POST['nav_name'])];
            // 更新 slug
            if (!empty($_POST['nav_slug'])) {
                $postData['post_name'] = sanitize_title($_POST['nav_slug']);
            }
            remove_action('save_post_nav_site', [$this, 'saveMeta']);
            wp_update_post($postData);
            add_action('save_post_nav_site', [$this, 'saveMeta'], 10, 3);
        }

        // 设置特色图（网站截图）
        if (!empty($_POST['nav_screenshot_att_id'])) {
            $attId = (int) $_POST['nav_screenshot_att_id'];
            if ($attId > 0) {
                set_post_thumbnail($postId, $attId);
            }
        }

        // 保存导航标签（nav_tag 分类）
        if (isset($_POST['nav_tags_json'])) {
            $tagsJson = wp_kses_post($_POST['nav_tags_json']);
            $tags = json_decode($tagsJson, true);
            if (is_array($tags) && !empty($tags)) {
                $termIds = [];
                foreach ($tags as $tagName) {
                    $tagName = sanitize_text_field(trim($tagName));
                    if (mb_strlen($tagName, 'utf-8') < 2) continue;
                    $term = get_term_by('name', $tagName, 'nav_tag');
                    if ($term) {
                        $termIds[] = (int) $term->term_id;
                    } else {
                        $new = wp_insert_term($tagName, 'nav_tag');
                        if (!is_wp_error($new)) $termIds[] = (int) $new['term_id'];
                    }
                }
                wp_set_object_terms($postId, $termIds, 'nav_tag');
            } else {
                wp_set_object_terms($postId, [], 'nav_tag');
            }
        }
    }

    /**
     * 管理列表添加列
     */
    public function addListColumns(array $columns): array
    {
        $new = [];
        foreach ($columns as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['nav_url_col']     = '网址';
                $new['nav_clicks_col']  = '点击';
                $new['nav_status_col']  = '状态';
            }
        }
        $new['date'] = $columns['date'] ?? '日期';
        return $new;
    }

    /**
     * 渲染列表列内容
     */
    public function renderListColumn(string $column, int $postId): void
    {
        if ($column === 'nav_url_col') {
            $url = get_post_meta($postId, 'nav_url', true);
            if ($url) {
                $statusCheck = get_post_meta($postId, 'nav_status_check', true);
                $isOnline = $statusCheck['is_online'] ?? null;
                $dot = '';
                if ($isOnline === true) {
                    $dot = '<span style="color:#00a32a;" title="正常">●</span> ';
                } elseif ($isOnline === false) {
                    $dot = '<span style="color:#e65054;" title="' . esc_attr($statusCheck['message'] ?? '异常') . '">●</span> ';
                }
                echo $dot . '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(parse_url($url, PHP_URL_HOST)) . '</a>';
            } else {
                echo '—';
            }
        } elseif ($column === 'nav_clicks_col') {
            $clicks = (int) get_post_meta($postId, 'nav_clicks', true);
            echo $clicks > 0 ? number_format($clicks) : '—';
        } elseif ($column === 'nav_status_col') {
            $status = get_post_meta($postId, 'nav_status', true);
            echo $status === 'featured' ? '⭐ 推荐' : '普通';
        }
    }
}
