<?php
/**
 * 导航网站 Meta 编辑器（Post Meta Box）
 * 文件：src/Admin/views/navigation-meta.php
 *
 * 流水线逻辑：
 * 1. 输入网址 → AI全量获取（名称/别名/关键词/描述/Logo/截图）
 * 2. 点击 AI生成简介（生成正文）
 * 3. 点击 AI提取标签（从简介中提取标签）
 * 所有字段均可手动修改
 */
if (!defined('ABSPATH')) exit;

$postId = get_the_ID();
$meta   = \ZuoAIPlus\Models\NavigationSite::getMeta($postId);
$logo   = $meta['logo'] ?? '';
$shot   = $meta['screenshot'] ?? '';

// 当前标签
$currentTags = get_the_terms($postId, 'nav_tag');
$tagNames = [];
if ($currentTags && !is_wp_error($currentTags)) {
    foreach ($currentTags as $t) $tagNames[] = $t->name;
}
?>
<style>
.nav-meta-box {padding:0;}
.nav-meta-box .section {background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-bottom:16px;overflow:hidden;}
.nav-meta-box .section-title {background:#f6f7f7;border-bottom:1px solid #c3c4c7;padding:10px 14px;font-weight:600;font-size:13px;color:#1d2327;display:flex;align-items:center;gap:6px;}
.nav-meta-box .section-body {padding:14px 16px;}
.nav-meta-box .field-row {margin-bottom:14px;}
.nav-meta-box .field-row:last-child {margin-bottom:0;}
.nav-meta-box label {display:block;font-weight:600;margin-bottom:5px;font-size:13px;color:#1d2327;}
.nav-meta-box .desc {font-size:12px;color:#646970;margin-top:3px;}
.nav-meta-box input[type=text] {width:100%;max-width:560px;padding:6px 8px;}
.nav-meta-box textarea {width:100%;max-width:560px;padding:8px;resize:vertical;min-height:72px;}
.nav-meta-box .btn {background:#2271b1;color:#fff;border:none;padding:7px 16px;cursor:pointer;border-radius:3px;font-size:13px;white-space:nowrap;transition:background .2s;display:inline-flex;align-items:center;gap:5px;}
.nav-meta-box .btn:hover {background:#135e96;}
.nav-meta-box .btn:disabled {background:#a7aaad;cursor:not-allowed;}
.nav-meta-box .btn-success {background:#00a32a;}.btn-success:hover {background:#008a20;}
.nav-meta-box .btn-secondary {background:#f0f0f1;color:#1d2327;border:1px solid #c3c4c7;}.btn-secondary:hover {background:#e9e9e9;}
.url-row {display:flex;gap:8px;align-items:center;max-width:560px;}
.url-row input {flex:1;}
.two-col {display:flex;gap:14px;max-width:560px;}
@media (max-width: 782px) {.nav-meta-box .two-col {flex-direction:column;gap:12px;}}
.fetch-result {margin-top:8px;padding:8px 12px;background:#f0f6fc;border:1px solid #72aee6;border-radius:3px;font-size:13px;display:none;}
.fetch-result.show {display:block;}
.fetch-result.error {background:#fcf0f1;border-color:#e65054;}
.fetch-result.success {background:#f0fcf4;border-color:#00a32a;}

/* 图片区 */
.img-field-group {display:flex;flex-direction:column;gap:8px;}
.img-url-row {display:flex;gap:6px;align-items:center;}
.img-url-row input {flex:1;min-width:0;}
.img-actions {display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
.img-actions .btn {padding:5px 12px;font-size:12px;}

/* 标签区 */
.tag-display {display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:28px;}
.tag-chip {display:inline-flex;align-items:center;gap:4px;background:#e8f0fd;color:#1d2327;padding:4px 10px;border-radius:9999px;font-size:12px;border:1px solid #c5d9fa;}
.tag-chip .remove-tag {cursor:pointer;color:#646970;font-size:14px;line-height:1;padding:0 2px;}
.tag-chip .remove-tag:hover {color:#d63638;}
.tag-input-row {display:flex;gap:6px;align-items:center;}
.tag-input-row input {flex:1;min-width:0;padding:5px 8px;font-size:13px;}

/* 状态行 */
.status-row {display:flex;gap:20px;margin-top:4px;}
.status-row label {font-weight:400;display:flex;align-items:center;gap:4px;}
</style>

<div class="nav-meta-box">
<input type="hidden" name="nav_meta_nonce" value="<?php echo esc_attr(wp_create_nonce('nav_site_save_' . $postId)); ?>">

    <!-- ═══ 第一区：基本信息 ═══ -->
    <div class="section">
        <div class="section-title">🌐 基本信息</div>
        <div class="section-body">
            <div class="field-row">
                <label for="nav_url">网站网址 <span style="color:#e65054;">*</span></label>
                <div class="url-row">
                    <input type="text" id="nav_url" name="nav_url" value="<?php echo esc_attr($meta['url'] ?? ''); ?>" placeholder="https://example.com">
                    <button type="button" class="btn btn-success" id="btn-fetch-all">🤖 AI 全量获取</button>
                </div>
                <p class="desc">输入网址 → 点击 AI 全量获取，自动填入名称/别名/关键词/描述/Logo/截图</p>
            </div>
            <div class="fetch-result" id="fetch-result"></div>

            <div class="field-row">
                <div class="two-col">
                    <div>
                        <label for="nav_name">网站名称</label>
                        <input type="text" id="nav_name" name="nav_name" value="<?php echo esc_attr($meta['name'] ?? ''); ?>" placeholder="网站名称（AI 获取后自动填充）">
                    </div>
                    <div>
                        <label for="nav_slug">URL别名 (Slug)</label>
                        <input type="text" id="nav_slug" name="nav_slug" value="<?php echo esc_attr(get_post_field('post_name', $postId)); ?>" placeholder="ai-tool (AI 获取后自动填充）">
                    </div>
                </div>
            </div>

            <div class="field-row">
                <label for="nav_keywords">关键词</label>
                <input type="text" id="nav_keywords" name="nav_keywords" value="<?php echo esc_attr($meta['keywords'] ?? ''); ?>" placeholder="关键词1, 关键词2, ...（AI 获取后自动填充）">
            </div>
        </div>
    </div>

    <!-- ═══ 第二区：描述与简介 ═══ -->
    <div class="section">
        <div class="section-title">📝 描述与简介</div>
        <div class="section-body">
            <div class="field-row">
                <label for="nav_description">网站描述</label>
                <textarea id="nav_description" name="nav_description" placeholder="简短描述（AI 获取后自动填充，用于 SEO）"><?php echo esc_textarea($meta['description'] ?? ''); ?></textarea>
            </div>
            <div class="field-row">
                <label for="nav_ai_summary">网站简介（正文）
                    <span class="desc" style="display:inline;margin-left:6px;">AI 生成 300-500 字详细介绍</span>
                </label>
                <textarea id="nav_ai_summary" name="nav_ai_summary" placeholder="详细介绍（AI 生成后自动填充）" style="min-height:220px;"><?php echo esc_textarea($meta['ai_summary'] ?? ''); ?></textarea>
                <div style="margin-top:8px;">
                    <button type="button" class="btn btn-success" id="btn-ai-summary">✨ AI 生成简介</button>
                    <span class="desc" style="margin-left:8px;">根据名称和描述生成 300-500 字正文</span>
                </div>
                <div class="fetch-result" id="summary-result"></div>
            </div>
        </div>
    </div>

    <!-- ═══ 第三区：图片资源 ═══ -->
    <div class="section">
        <div class="section-title">🖼️ 图片资源</div>
        <div class="section-body">
            <div class="field-row">
                <div class="two-col">
                    <!-- Logo -->
                    <div class="img-field-group">
                        <label for="nav_logo">Logo 图片</label>
                        <div class="img-url-row">
                            <input type="text" id="nav_logo" name="nav_logo" value="<?php echo esc_attr($logo); ?>" placeholder="https://...（AI 全量获取后自动填充）">
                        </div>
                        <div class="img-actions">
                            <button type="button" class="btn btn-secondary" id="btn-logo-media">📂 媒体库选择</button>
                            <button type="button" class="btn" id="btn-logo-save">💾 保存到媒体库</button>
                        </div>
                    </div>

                    <!-- 截图 -->
                    <div class="img-field-group">
                        <label for="nav_screenshot">网站截图</label>
                        <div class="img-url-row">
                            <input type="text" id="nav_screenshot" name="nav_screenshot" value="<?php echo esc_attr($shot); ?>" placeholder="AI 全量获取后自动填入（来自截图服务）">
                        </div>
                        <div class="img-actions">
                            <button type="button" class="btn btn-secondary" id="btn-shot-media">📂 媒体库选择</button>
                            <button type="button" class="btn" id="btn-shot-save">💾 保存到媒体库</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="fetch-result" id="img-result"></div>
        </div>
    </div>

    <!-- ═══ 第四区：AI提取标签（按钮区） ═══ -->
    <div class="section" style="padding:10px 14px;display:flex;align-items:center;gap:10px;">
        <button type="button" class="btn btn-success" id="btn-ai-tags">🤖 AI 提取标签</button>
        <span class="desc">根据网站简介自动提取标签，写入右侧导航标签栏</span>
        <div class="fetch-result" id="tag-result" style="margin-left:10px;display:inline-block;"></div>
    </div>

    <!-- ═══ 第五区：推荐状态 ═══ -->
    <div class="section">
        <div class="section-title">🏷️ 推荐状态</div>
        <div class="section-body">
            <div class="status-row">
                <label><input type="radio" name="nav_status" value="featured" <?php checked($meta['status'] ?? '', 'featured'); ?>> ⭐ 推荐</label>
                <label><input type="radio" name="nav_status" value="normal" <?php checked($meta['status'] ?? 'normal', 'normal'); ?>> 普通</label>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    var postId = <?php echo (int) $postId; ?>;
    var nonce = '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>';
    var restUrl = '<?php echo esc_url(rest_url('ai-plus/v1/')); ?>';

    // ── 通用 ───────────────────────────────────────────────
    function show(el, msg, type) {
        el.className = 'fetch-result show ' + (type || '');
        el.innerHTML = '<strong>' + msg + '</strong>';
        el.style.display = 'block';
    }
    function hide(el) { el.style.display = 'none'; }

    // ── 1. AI 全量获取 ──────────────────────────────────────
    document.getElementById('btn-fetch-all').addEventListener('click', function() {
        var url = document.getElementById('nav_url').value.trim();
        if (!url) { alert('请先输入网址'); return; }

        var btn = this;
        btn.disabled = true; btn.textContent = '抓取中...';
        var box = document.getElementById('fetch-result');
        show(box, '⏳ 正在抓取网页信息（curl + AI 分析）...', '');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', restUrl + 'nav/fetch', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            btn.disabled = false; btn.textContent = '🤖 AI 全量获取';

            if (xhr.status !== 200) {
                show(box, '❌ HTTP ' + xhr.status + '：' + xhr.statusText, 'error');
                return;
            }

            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                show(box, '❌ 返回数据解析失败：' + xhr.responseText.substring(0, 100), 'error');
                return;
            }

            if (!data || data.code !== 'success') {
                var msg = (data && data.message) ? data.message : '未知错误';
                show(box, '❌ ' + msg, 'error');
                return;
            }

            var d = data.data || {};
            var filled = [];
            if (d.name) {
                document.getElementById('nav_name').value = d.name;
                filled.push('网站名称');
            }
            if (d.slug) {
                document.getElementById('nav_slug').value = d.slug;
                filled.push('URL别名');
            }
            if (d.keywords) {
                document.getElementById('nav_keywords').value = d.keywords;
                filled.push('关键词');
            }
            if (d.description) {
                document.getElementById('nav_description').value = d.description;
                filled.push('网站描述');
            }
            if (d.logo) {
                document.getElementById('nav_logo').value = d.logo;
                filled.push('Logo');
            }
            if (d.screenshot) {
                document.getElementById('nav_screenshot').value = d.screenshot;
                filled.push('网站截图');
                // 记录截图附件ID，用于保存时设为特色图
                if (d.screenshot_att_id) {
                    var attIdInput = document.getElementById('nav_screenshot_att_id');
                    if (!attIdInput) {
                        attIdInput = document.createElement('input');
                        attIdInput.type = 'hidden';
                        attIdInput.id = 'nav_screenshot_att_id';
                        attIdInput.name = 'nav_screenshot_att_id';
                        attIdInput.value = d.screenshot_att_id;
                        document.querySelector('.nav-meta-box').appendChild(attIdInput);
                    } else {
                        attIdInput.value = d.screenshot_att_id;
                    }
                }
            }

            if (filled.length === 0) {
                show(box, '⚠️ 未抓取到任何内容，请检查网址是否正确或目标网站禁止抓取', 'error');
            } else {
                show(box, '✅ 抓取成功！已填入：' + filled.join('、'), 'success');
                setTimeout(function() { hide(box); }, 5000);
            }
        };
        xhr.onerror = function() {
            btn.disabled = false; btn.textContent = '🤖 AI 全量获取';
            show(box, '❌ 网络错误，请稍后重试', 'error');
        };
        xhr.send(JSON.stringify({ url: url }));
    });

    // ── 2. AI 生成简介 ───────────────────────────────────────
    document.getElementById('btn-ai-summary').addEventListener('click', function() {
        var url  = document.getElementById('nav_url').value.trim();
        var name = document.getElementById('nav_name').value.trim();
        var desc = document.getElementById('nav_description').value.trim();
        if (!url || !name) {
            alert('请先输入网址和网站名称（可点「AI 全量获取」自动填充）');
            return;
        }

        var btn = this;
        btn.disabled = true; btn.textContent = '生成中...';
        var box = document.getElementById('summary-result');
        show(box, '⏳ AI 正在生成网站简介（300-500字）...', '');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', restUrl + 'nav/ai-summary', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            btn.disabled = false; btn.textContent = '✨ AI 生成简介';

            if (xhr.status !== 200) {
                show(box, '❌ HTTP ' + xhr.status, 'error');
                return;
            }

            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                show(box, '❌ 解析失败', 'error'); return;
            }

            if (data.code === 'success' && data.data && data.data.content) {
                document.getElementById('nav_ai_summary').value = data.data.content;
                show(box, '✅ 简介生成成功！已填入正文区域，可自行修改', 'success');
                setTimeout(function() { hide(box); }, 5000);
            } else {
                show(box, '❌ ' + (data.message || '生成失败'), 'error');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false; btn.textContent = '✨ AI 生成简介';
            show(box, '❌ 网络错误', 'error');
        };
        xhr.send(JSON.stringify({ url: url, name: name, description: desc }));
    });

    // ── 3. AI 提取标签 ───────────────────────────────────────
    document.getElementById('btn-ai-tags').addEventListener('click', function() {
        var name = document.getElementById('nav_name').value.trim();
        var url  = document.getElementById('nav_url').value.trim();
        var summary = document.getElementById('nav_ai_summary').value.trim();
        var desc = document.getElementById('nav_description').value.trim() || summary;

        if (!name) {
            alert('请先填写网站名称');
            return;
        }

        var btn = this;
        btn.disabled = true; btn.textContent = '提取中...';
        var box = document.getElementById('tag-result');
        show(box, '⏳ AI 正在分析内容提取标签...', '');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', restUrl + 'nav/ai-tags', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            btn.disabled = false; btn.textContent = '🤖 AI 提取标签';

            if (xhr.status !== 200) {
                show(box, '❌ HTTP ' + xhr.status, 'error');
                return;
            }

            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                show(box, '❌ 解析失败', 'error'); return;
            }

            if (data.success && data.tags && data.tags.length > 0) {
                show(box, '✅ 已提取 ' + data.tags.length + ' 个标签：' + data.tags.join('、'), 'success');

                // 将标签写入右侧 WordPress 原生标签输入框
                var tagInput = document.getElementById('new-tag-nav_tag');
                if (tagInput) {
                    data.tags.forEach(function(tag) {
                        tagInput.value = tag;
                        var addBtn = tagInput.parentElement.querySelector('.button.tagadd');
                        if (addBtn) addBtn.click();
                    });
                    tagInput.value = '';
                    show(box, '✅ 已提取 ' + data.tags.length + ' 个标签并写入右侧标签栏', 'success');
                } else {
                    show(box, '✅ 已提取 ' + data.tags.length + ' 个标签，请手动添加到右侧标签栏', 'success');
                }
                setTimeout(function() { hide(box); }, 5000);
            } else {
                show(box, '❌ ' + (data.message || '未能提取有效标签'), 'error');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false; btn.textContent = '🤖 AI 提取标签';
            show(box, '❌ 网络错误', 'error');
        };
        xhr.send(JSON.stringify({ post_id: postId, name: name, url: url, description: desc }));
    });

    // ── 媒体库选择 ─────────────────────────────────────────
    function navOpenMedia(targetInputId) {
        var frame = wp.media({
            title: '选择图片',
            button: { text: '选 择' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.sizes) {
                var imgUrl = (att.sizes.full && att.sizes.full.url) ? att.sizes.full.url : att.url;
                document.getElementById(targetInputId).value = imgUrl;
            }
        });
        frame.open();
    }

    document.getElementById('btn-logo-media').addEventListener('click', function() {
        navOpenMedia('nav_logo');
    });
    document.getElementById('btn-shot-media').addEventListener('click', function() {
        navOpenMedia('nav_screenshot');
    });

    // ── 保存到媒体库 ───────────────────────────────────────
    function navSaveToMediaLib(btn, inputId, resultId) {
        var url = document.getElementById(inputId).value.trim();
        if (!url) { alert('请先输入图片 URL'); return; }
        btn.disabled = true; btn.textContent = '保存中...';
        var box = document.getElementById(resultId);
        show(box, '⏳ 正在下载图片到媒体库...', '');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', restUrl + 'nav/download-image', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', nonce);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            btn.disabled = false; btn.textContent = '💾 保存到媒体库';

            if (xhr.status !== 200) {
                show(box, '❌ HTTP ' + xhr.status, 'error'); return;
            }
            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) {
                show(box, '❌ 解析失败', 'error'); return;
            }
            if (data.success) {
                document.getElementById(inputId).value = data.url;
                show(box, '✅ 已保存到媒体库（ID: ' + data.attachment_id + '）', 'success');
                setTimeout(function() { hide(box); }, 5000);
            } else {
                show(box, '❌ ' + (data.message || '保存失败'), 'error');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false; btn.textContent = '💾 保存到媒体库';
            show(box, '❌ 网络错误', 'error');
        };
        xhr.send(JSON.stringify({ image_url: url }));
    }

    document.getElementById('btn-logo-save').addEventListener('click', function() {
        navSaveToMediaLib(this, 'nav_logo', 'img-result');
    });
    document.getElementById('btn-shot-save').addEventListener('click', function() {
        navSaveToMediaLib(this, 'nav_screenshot', 'img-result');
    });

})();
</script>
