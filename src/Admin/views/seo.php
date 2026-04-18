<?php
/**
 * SEO Diagnostic Panel View
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
if (!defined('ABSPATH')) exit;

use ZuoAIPlus\Models\SeoOptimizer;

$seo = new SeoOptimizer();
$stats = $seo->getStats();

// 获取已发布的文章列表（带分页）
$paged = max(1, intval($_GET['paged'] ?? 1));
$per_page = 20;
$offset = ($paged - 1) * $per_page;

global $wpdb;
$total_posts = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'"
);
$total_pages = ceil($total_posts / $per_page);

$posts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT ID, post_title, post_date FROM {$wpdb->posts}
         WHERE post_type='post' AND post_status='publish'
         ORDER BY post_date DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ),
    ARRAY_A
);

// 获取每篇文章的SEO数据
$post_ids = array_column($posts, 'ID');
$scores = [];
$optimized_flags = [];
if ($post_ids) {
    $ids_placeholder = implode(',', array_map('intval', $post_ids));
    $score_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$ids_placeholder}) AND meta_key = %s",
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- intval-prepared list
            SeoOptimizer::META_SCORE
        ),
        ARRAY_A
    );
    foreach ($score_rows as $row) {
        $scores[$row['post_id']] = (int) $row['meta_value'];
    }
    $opt_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE post_id IN ({$ids_placeholder}) AND meta_key = %s AND meta_value = '1'",
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- intval-prepared list
            SeoOptimizer::META_OPTIMIZED
        ),
        ARRAY_A
    );
    foreach ($opt_rows as $row) {
        $optimized_flags[$row['post_id']] = true;
    }
}

// 动态获取已配置的平台，格式：平台 => 显示名
$platform_names = [
    'minimax'  => 'MiniMax',
    'zhipu'    => '智谱 GLM',
    'kimi'     => 'Kimi',
    'tongyi'   => '通义千问',
    'deepseek' => 'DeepSeek',
    'custom'   => '自定义',
];
$apiKeys = get_option('ai_plus_api_keys', []);
$model_options = [];
foreach ($platform_names as $key => $label) {
    $cfg = $apiKeys[$key] ?? [];
    $has_key = is_array($cfg) ? !empty($cfg['api_key']) : !empty($cfg);
    if ($has_key) {
        // 显示平台名 + 具体模型ID
        $model_id = is_array($cfg) ? ($cfg['model'] ?: '') : '';
        $display = $model_id ? "{$label}（{$model_id}）" : $label;
        $model_options[$key] = $display;
    }
}
$default_model = get_option('ai_plus_default_model', 'minimax');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-wordpress-admin@3/wordpress-admin.css">
<style>
.seo-stat-grid {display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0 30px;}
.seo-stat-card {background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;}
.seo-stat-num {font-size:32px;font-weight:bold;color:#2271b1;}
.seo-stat-label {color:#666;margin-top:6px;font-size:13px;}
.seo-stat-num.green {color:#00a32a;}
.seo-stat-num.red {color:#d63638;}

.seo-actions {margin-bottom:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.seo-actions .btn {padding:8px 18px;border-radius:3px;font-size:13px;cursor:pointer;border:none;}
.btn-primary {background:#2271b1;color:#fff;}
.btn-primary:hover {background:#135e96;}
.btn-secondary {background:#f0f0f1;color:#2c3338;border:1px solid #c3c4c7;}
.btn-secondary:hover {background:#dcdcde;}
.btn-warning {background:#d63638;color:#fff;}
.btn-warning:hover {background:#a30000;}
.btn-sm {padding:4px 10px;font-size:12px;}

.seo-table-wrap {background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden;}
table.seo-table {width:100%;border-collapse:collapse;font-size:13px;}
.seo-table th {background:#f6f6f6;padding:10px 12px;text-align:left;font-weight:600;border-bottom:1px solid #ddd;}
.seo-table td {padding:10px 12px;border-bottom:1px solid #f0f0f1;vertical-align:middle;}
.seo-table tr:last-child td {border-bottom:none;}
.seo-table tr:hover td {background:#f9f9fa;}

.score-badge {display:inline-block;min-width:36px;padding:3px 8px;border-radius:12px;font-size:12px;font-weight:600;text-align:center;}
.score-good {background:#00a32a20;color:#00a32a;}
.score-warn {background:#f0b42920;color:#92611a;}
.score-bad {background:#d6363820;color:#d63638;}
.score-none {background:#f0f0f1;color:#666;}

.issue-count {display:inline-block;min-width:22px;height:22px;line-height:22px;border-radius:50%;text-align:center;font-size:11px;}
.issue-none {background:#00a32a20;color:#00a32a;}
.issue-low {background:#f0b42920;color:#92611a;}
.issue-high {background:#d6363820;color:#d63638;}

.post-title-cell {max-width:260px;}
.post-title-cell a {font-weight:500;text-decoration:none;color:#1d2327;}
.post-title-cell a:hover {color:#2271b1;text-decoration:underline;}
.post-meta {color:#888;font-size:11px;margin-top:2px;}

.tag-list {display:flex;flex-wrap:wrap;gap:4px;}
.tag-chip {background:#f0f0f1;color:#3c434a;padding:2px 7px;border-radius:3px;font-size:11px;}

.action-btns {display:flex;gap:6px;align-items:center;white-space:nowrap;}

.pagination-wrap {margin-top:16px;display:flex;justify-content:flex-end;}
.pagination-wrap a, .pagination-wrap span {padding:4px 10px;margin-left:4px;border:1px solid #c3c4c7;background:#fff;color:#2c3338;text-decoration:none;font-size:12px;border-radius:3px;}
.pagination-wrap a:hover {background:#f0f0f1;}
.pagination-wrap .current {background:#2271b1;color:#fff;border-color:#2271b1;}

.loading-overlay {position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.85);z-index:99999;display:none;align-items:center;justify-content:center;}
.loading-overlay.active {display:flex;}
.loading-inner {text-align:center;font-size:16px;color:#2271b1;}
.loading-inner .spinner {display:inline-block;width:40px;height:40px;border:3px solid #f0f0f1;border-top-color:#2271b1;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:12px;}
@keyframes spin {to{transform:rotate(360deg);}}

.log-area {background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-family:monospace;font-size:12px;max-height:300px;overflow-y:auto;margin-top:16px;display:none;}
.log-area.show {display:block;}
.log-line {margin:2px 0;}
.log-info {color:#4fc3f7;}
.log-ok {color:#81c784;}
.log-warn {color:#f0b429;}
.log-error {color:#e57373;}

/* ========== 移动端响应式优化 ========== */
@media screen and (max-width: 782px) {
    .seo-stat-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin: 16px 0 20px;
    }
    .seo-stat-card {
        padding: 16px 8px;
    }
    .seo-stat-num {
        font-size: 24px;
    }
    .seo-stat-label {
        font-size: 12px;
    }

    .seo-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .seo-actions .btn,
    .seo-actions select {
        width: 100%;
        padding: 10px;
        font-size: 14px;
    }

    /* 表格横向滚动 */
    .seo-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    table.seo-table {
        min-width: 600px;
    }
    .seo-table th,
    .seo-table td {
        padding: 8px 10px;
        font-size: 12px;
    }
    .post-title-cell {
        max-width: 150px;
    }

    /* 分页 */
    .pagination-wrap {
        flex-wrap: wrap;
        justify-content: center;
    }
    .pagination-wrap a,
    .pagination-wrap span {
        padding: 8px 12px;
        font-size: 13px;
    }

    /* 操作按钮 */
    .action-btns {
        flex-wrap: wrap;
        gap: 4px;
    }
    .action-btns .btn-sm {
        padding: 6px 10px;
        font-size: 11px;
    }
}

@media screen and (max-width: 480px) {
    .seo-stat-grid {
        grid-template-columns: 1fr;
    }
    .seo-stat-num {
        font-size: 28px;
    }
    .tag-list {
        gap: 2px;
    }
    .tag-chip {
        padding: 1px 5px;
        font-size: 10px;
    }
}
</style>

<div class="wrap" style="max-width:1200px;">
    <h1>🔍 Zuo AI Plus SEO 诊断</h1>

    <!-- 统计卡片 -->
    <div class="seo-stat-grid">
        <div class="seo-stat-card">
            <div class="seo-stat-num"><?php echo (int) $stats['total']; ?></div>
            <div class="seo-stat-label">文章总数</div>
        </div>
        <div class="seo-stat-card">
            <div class="seo-stat-num green"><?php echo (int) $stats['optimized']; ?></div>
            <div class="seo-stat-label">已优化</div>
        </div>
        <div class="seo-stat-card">
            <div class="seo-stat-num red"><?php echo (int) $stats['pending']; ?></div>
            <div class="seo-stat-label">待优化</div>
        </div>
        <div class="seo-stat-card">
            <?php $avg = (int) $stats['avg_score']; ?>
            <?php $avg_cls = $avg >= 80 ? 'green' : ($avg >= 60 ? '' : 'red'); ?>
            <div class="seo-stat-num <?php echo $avg_cls; ?>"><?php echo $avg; ?></div>
            <div class="seo-stat-label">平均得分</div>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="seo-actions">
        <button class="btn btn-primary" id="btn-audit-all">🔍 诊断全部文章</button>
        <button class="btn btn-secondary" id="btn-batch-optimize" disabled>🤖 AI 批量优化选中</button>
        <select id="sel-model" style="font-size:13px;padding:6px 8px;border-radius:3px;border:1px solid #c3c4c7;">
            <?php foreach ($model_options as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($default_model, $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
        <span id="selected-count" style="font-size:12px;color:#666;margin-left:8px;"></span>
    </div>

    <!-- 操作日志 -->
    <div class="log-area" id="log-area"></div>

    <!-- 文章列表 -->
    <div class="seo-table-wrap">
        <table class="seo-table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="chk-all"></th>
                    <th>文章</th>
                    <th style="width:70px;">得分</th>
                    <th style="width:70px;">问题</th>
                    <th style="width:140px;">标签</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="seo-tbody">
                <?php foreach ($posts as $post): 
                    $post_id = (int) $post['ID'];
                    $score = $scores[$post_id] ?? null;
                    $is_opt = !empty($optimized_flags[$post_id]);
                    $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
                    $tags = is_array($tags) ? array_slice($tags, 0, 4) : [];
                    
                    // 快速问题评估
                    $issue_count = 0;
                    if ($score !== null) {
                        if ($score < 60) $issue_count = 3;
                        elseif ($score < 80) $issue_count = 2;
                        elseif ($score < 100) $issue_count = 1;
                    }

                    // 颜色判断
                    if ($score === null) {
                        $score_cls = 'score-none'; $score_text = '—';
                    } elseif ($score >= 80) {
                        $score_cls = 'score-good'; $score_text = $score;
                    } elseif ($score >= 60) {
                        $score_cls = 'score-warn'; $score_text = $score;
                    } else {
                        $score_cls = 'score-bad'; $score_text = $score;
                    }

                    if ($issue_count == 0) $icls = 'issue-none';
                    elseif ($issue_count <= 1) $icls = 'issue-low';
                    else $icls = 'issue-high';
                ?>
                <tr data-post-id="<?php echo $post_id; ?>" data-score="<?php echo $score !== null ? $score : ''; ?>">
                    <td><input type="checkbox" class="chk-post" value="<?php echo $post_id; ?>"></td>
                    <td class="post-title-cell">
                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" target="_blank">
                            <?php echo esc_html($post['post_title']); ?>
                        </a>
                        <div class="post-meta">
                            <?php echo esc_html(date_i18n('Y-m-d', strtotime($post['post_date']))); ?>
                            <?php if ($is_opt): ?>&nbsp;<span style="color:#00a32a;font-size:11px;">✅已优化</span><?php endif; ?>
                        </div>
                    </td>
                    <td><span class="score-badge <?php echo $score_cls; ?>"><?php echo $score_text; ?></span></td>
                    <td>
                        <?php if ($score !== null): ?>
                        <span class="issue-count <?php echo $icls; ?>"><?php echo $issue_count; ?></span>
                        <?php else: ?>
                        <span class="issue-count issue-none">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="tag-list">
                            <?php foreach ($tags as $tag): ?>
                                <span class="tag-chip"><?php echo esc_html($tag); ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($tags)): ?><span style="color:#999;font-size:11px;">无标签</span><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn btn-secondary btn-sm btn-audit-one" data-id="<?php echo $post_id; ?>">诊断</button>
                            <button class="btn btn-primary btn-sm btn-optimize-one" data-id="<?php echo $post_id; ?>" data-model="<?php echo esc_attr($default_model); ?>">优化</button>
                            <?php if ($is_opt): ?>
                            <button class="btn btn-warning btn-sm btn-reset-one" data-id="<?php echo $post_id; ?>">重置</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $paged): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <?php 
                $base_url = admin_url('admin.php?page=ai_plus_seo');
                $page_url = add_query_arg('paged', $i, $base_url);
                ?>
                <a href="<?php echo esc_url(wp_nonce_url($page_url, 'ai_plus_admin')); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 加载动画 -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-inner">
        <div class="spinner"></div>
        <div id="loading-msg">处理中...</div>
    </div>
</div>

<script>
(function() {
    const apiBase = '<?php echo rest_url('ai-plus/v1/'); ?>';
    const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

    function api(method, endpoint, data) {
        return fetch(apiBase + endpoint, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: data ? JSON.stringify(data) : undefined
        }).then(r => r.json());
    }

    function log(msg, type='info') {
        const el = document.getElementById('log-area');
        el.classList.add('show');
        const d = document.createElement('div');
        d.className = 'log-line log-' + type;
        d.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
        el.appendChild(d);
        el.scrollTop = el.scrollHeight;
    }

    function loading(msg) {
        document.getElementById('loading-overlay').classList.add('active');
        document.getElementById('loading-msg').textContent = msg;
    }
    function unloaded() {
        document.getElementById('loading-overlay').classList.remove('active');
    }

    // 全选
    document.getElementById('chk-all').addEventListener('change', function() {
        document.querySelectorAll('.chk-post').forEach(c => c.checked = this.checked);
        updateCount();
    });

    document.querySelectorAll('.chk-post').forEach(c => c.addEventListener('change', updateCount));

    function updateCount() {
        const n = document.querySelectorAll('.chk-post:checked').length;
        document.getElementById('selected-count').textContent = n > 0 ? '已选 ' + n + ' 篇' : '';
        document.getElementById('btn-batch-optimize').disabled = n === 0;
    }

    // 更新单行得分
    function updateRow(postId, data) {
        const row = document.querySelector('tr[data-post-id="' + postId + '"]');
        if (!row) return;
        if (data.score !== undefined) {
            row.dataset.score = data.score;
            const badge = row.querySelector('.score-badge');
            let cls, text;
            if (data.score >= 80) { cls = 'score-good'; text = data.score; }
            else if (data.score >= 60) { cls = 'score-warn'; text = data.score; }
            else { cls = 'score-bad'; text = data.score ?? '—'; }
            badge.className = 'score-badge ' + cls;
            badge.textContent = text;
        }
        if (data.issues !== undefined) {
            const ic = row.querySelector('.issue-count');
            let cls, cnt;
            if (!data.issues || data.issues === 0) { cls = 'issue-none'; cnt = 0; }
            else if (data.issues <= 2) { cls = 'issue-low'; cnt = data.issues; }
            else { cls = 'issue-high'; cnt = data.issues; }
            ic.className = 'issue-count ' + cls;
            ic.textContent = cnt;
        }
        if (data.optimized !== undefined) {
            const meta = row.querySelector('.post-meta');
            const existing = meta.querySelector('.opt-badge');
            if (existing) existing.remove();
            if (data.optimized) {
                const sp = document.createElement('span');
                sp.className = 'opt-badge';
                sp.style = 'color:#00a32a;font-size:11px;';
                sp.textContent = ' ✅已优化';
                meta.appendChild(sp);
            }
        }
    }

    // 诊断全部
    document.getElementById('btn-audit-all').addEventListener('click', async function() {
        loading('正在诊断全部文章...');
        log('开始诊断全部文章...');
        try {
            const result = await api('GET', 'seo-audit');
            unloaded();
            if (result && result.posts) {
                let done = 0;
                for (const p of result.posts) {
                    updateRow(p.id, { score: p.score, issues: p.issues ? p.issues.length : 0, optimized: p.optimized });
                    done++;
                    if (done % 10 === 0) log('已诊断 ' + done + ' / ' + result.posts.length + ' 篇', 'info');
                }
                log('诊断完成！共 ' + result.posts.length + ' 篇文章', 'ok');
            } else if (result && result.error) {
                log('诊断失败：' + result.error, 'error');
            } else {
                log('诊断完成', 'ok');
            }
        } catch(e) {
            unloaded();
            log('网络错误：' + e.message, 'error');
        }
    });

    // 诊断单篇

    // 更新 Gutenberg 标签（修复标签不显示问题）
    async function refreshGutenbergTags(postId, newTags) {
        // 先尝试使用 WordPress REST API 刷新文章
        try {
            const response = await fetch('/wp-json/wp/v2/posts/' + postId + '?context=edit', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                }
            });
            
            if (response.ok) {
                const post = await response.json();
                // 标签已通过后端保存，前端只需提示用户
                return { success: true, message: '标签已保存，请手动刷新页面或编辑文章查看' };
            }
        } catch (e) {
            console.warn('刷新文章失败:', e);
        }
        
        return { success: false, message: '标签已保存，但无法自动刷新编辑器' };
    }

    // 优化单篇 - 修改版（不刷新页面，只更新 UI）
    document.querySelectorAll('.btn-optimize-one').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (this.disabled) return;
            const id = this.dataset.id;
            const model = document.getElementById('sel-model').value;
            this.disabled = true;
            this.style.opacity = '0.6';
            loading('正在优化文章 #' + id + '...');
            log('开始优化文章 #' + id + '，使用模型：' + model, 'info');
            try {
                const result = await api('POST', 'seo-optimize-post/' + parseInt(id), { model: model });
                unloaded();
                this.disabled = false;
                this.style.opacity = '';
                if (result && result.skipped) {
                    log('文章 #' + id + ' 已为满分，无需优化', 'info');
                    location.reload();
                } else if (result && !result.error) {
                    log('文章 #' + id + ' 优化成功！新得分 ' + (result.score ?? 'N/A'), 'ok');
                    
                    // 更新标签显示（AI 返回内容做 HTML 转义，防止标签含特殊字符导致渲染异常）
                    if (result.new_tags && Array.isArray(result.new_tags)) {
                        const tagList = document.querySelector(`tr[data-post-id="${id}"] .tag-list`);
                        if (tagList) {
                            // 使用 textContent 安全注入，避免 innerHTML XSS
                            tagList.textContent = result.new_tags.join('，');
                            // 仍然用 innerHTML 渲染为标签块（对已转义文本再次包裹 span）
                            tagList.innerHTML = result.new_tags.map(function(tag) {
                                var esc = tag.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                                return '<span class="tag-chip">' + esc + '</span>';
                            }).join('');
                            log('标签已更新：' + result.new_tags.join('、'), 'ok');
                        }
                    }
                    
                    // 提示用户刷新
                    log('提示：标签已保存，请手动刷新页面或编辑文章查看', 'info');
                } else if (result && result.error) {
                    log('文章 #' + id + ' 优化失败：' + result.error, 'error');
                } else {
                    log('文章 #' + id + ' 响应异常', 'error');
                }
            } catch(e) {
                unloaded();
                this.disabled = false;
                this.style.opacity = '';
                log('文章 #' + id + ' 网络错误：' + e.message, 'error');
            }
        });
    });

    // 诊断单篇 - 同样加防重复点击
    document.querySelectorAll('.btn-audit-one').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (this.disabled) return;
            const id = this.dataset.id;
            this.disabled = true;
            this.style.opacity = '0.6';
            loading('正在诊断文章 #' + id + '...');
            try {
                const result = await api('GET', 'seo-audit-post/' + parseInt(id));
                unloaded();
                this.disabled = false;
                this.style.opacity = '';
                if (result && result.id) {
                    updateRow(id, { score: result.score, issues: result.issues ? result.issues.length : 0, optimized: result.optimized });
                    log('文章 #' + id + ' 诊断完成，得分 ' + result.score, 'ok');
                } else if (result && result.error) {
                    log('文章 #' + id + ' 诊断失败：' + result.error, 'error');
                }
            } catch(e) {
                unloaded();
                this.disabled = false;
                this.style.opacity = '';
                log('文章 #' + id + ' 网络错误', 'error');
            }
        });
    });

    // 批量优化
    document.getElementById('btn-batch-optimize').addEventListener('click', async function() {
        const checked = Array.from(document.querySelectorAll('.chk-post:checked')).map(c => parseInt(c.value));
        if (!checked.length) return;
        const model = document.getElementById('sel-model').value;
        loading('正在批量优化 ' + checked.length + ' 篇文章...');
        log('开始批量优化 ' + checked.length + ' 篇文章，使用模型：' + model, 'info');
        try {
            const result = await api('POST', 'seo-optimize-batch', { post_ids: checked, model: model });
            unloaded();
            let ok = 0, fail = 0;
            for (const id of checked) {
                if (result && result[id]) {
                    const r = result[id];
                    if (r.error) {
                        log('文章 #' + id + ' 失败：' + r.error, 'error');
                        fail++;
                    } else {
                        updateRow(id, { score: r.score, issues: r.issues ? r.issues.length : 0, optimized: true });
                        ok++;
                    }
                }
            }
            log('批量优化完成！成功 ' + ok + ' 篇，失败 ' + fail + ' 篇', ok > 0 ? 'ok' : 'warn');
        } catch(e) {
            unloaded();
            log('批量优化网络错误：' + e.message, 'error');
        }
    });

    // 重置单篇
    document.querySelectorAll('.btn-reset-one').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            if (!confirm('确定要重置文章 #' + id + ' 的优化状态吗？')) return;
            loading('正在重置...');
            try {
                const result = await api('POST', 'seo-reset/' + id);
                unloaded();
                if (result && result.ok) {
                    log('文章 #' + id + ' 已重置', 'ok');
                    // 刷新页面以更新状态
                    location.reload();
                }
            } catch(e) {
                unloaded();
                log('重置失败：' + e.message, 'error');
            }
        });
    });
})();
</script>
