<?php
/**
 * 导航网站 Meta 编辑器（Post Meta Box）
 * 文件：src/Admin/views/navigation-meta.php
 */
if (!defined('ABSPATH')) exit;

$postId = get_the_ID();
$meta   = \ZuoAIPlus\Models\NavigationSite::getMeta($postId);
?>
<style>
.nav-meta-box {padding:0;}
.nav-meta-box .section {background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-bottom:16px;overflow:hidden;}
.nav-meta-box .section-title {background:#f6f7f7;border-bottom:1px solid #c3c4c7;padding:10px 14px;font-weight:600;font-size:13px;color:#1d2327;display:flex;align-items:center;gap:6px;}
.nav-meta-box .section-body {padding:14px 16px;}
.nav-meta-box .field-row {margin-bottom:12px;}
.nav-meta-box .field-row:last-child {margin-bottom:0;}
.nav-meta-box label {display:block;font-weight:600;margin-bottom:4px;font-size:13px;color:#1d2327;}
.nav-meta-box .desc {font-size:12px;color:#646970;margin-top:3px;}
.nav-meta-box input[type=text] {width:100%;max-width:560px;padding:6px 8px;}
.nav-meta-box textarea {width:100%;max-width:560px;padding:8px;resize:vertical;min-height:72px;}
.nav-meta-box .btn-ai {background:#2271b1;color:#fff;border:none;padding:6px 14px;cursor:pointer;border-radius:3px;font-size:13px;white-space:nowrap;transition:background .2s;}
.nav-meta-box .btn-ai:hover {background:#135e96;}
.nav-meta-box .btn-ai:disabled {background:#a7aaad;cursor:not-allowed;}
.nav-meta-box .btn-ai-alt {background:#00a32a;}
.nav-meta-box .btn-ai-alt:hover {background:#008a20;}
.nav-meta-box .btn-ai-alt:disabled {background:#a7aaad;}
.nav-meta-box .url-row {display:flex;gap:8px;align-items:center;max-width:560px;}
.nav-meta-box .url-row input {flex:1;}
.nav-meta-box .two-col {display:flex;gap:14px;max-width:560px;}
.nav-meta-box .two-col > div {flex:1;}
@media (max-width: 782px) {
  .nav-meta-box .two-col {flex-direction:column;gap:12px;}
}
.nav-meta-box .fetch-result {margin-top:8px;padding:8px 12px;background:#f0f6fc;border:1px solid #72aee6;border-radius:3px;font-size:13px;display:none;}
.nav-meta-box .fetch-result.show {display:block;}
.nav-meta-box .fetch-result.error {background:#fcf0f1;border-color:#e65054;}
.nav-meta-box .status-row {display:flex;gap:20px;margin-top:4px;}
.nav-meta-box .status-row label {font-weight:400;display:flex;align-items:center;gap:4px;}
</style>

<div class="nav-meta-box">
<input type="hidden" name="nav_meta_nonce" value="<?php echo esc_attr(wp_create_nonce('nav_site_save_' . $postId)); ?>">

    <!-- ═══ 第一区：基本信息 ═══ -->
    <div class="section">
        <div class="section-title">🌐 基本信息</div>
        <div class="section-body">
            <!-- 网址 + AI抓取 -->
            <div class="field-row">
                <label for="nav_url">网站网址 <span style="color:#e65054;">*</span></label>
                <div class="url-row">
                    <input type="text" id="nav_url" name="nav_url" value="<?php echo esc_attr($meta['url'] ?? ''); ?>" placeholder="https://example.com">
                    <button type="button" class="btn-ai" id="btn-fetch-meta">🤖 AI 获取</button>
                </div>
                <p class="desc">输入网址后点击 AI 获取，自动填充下方信息</p>
            </div>
            <div class="fetch-result" id="fetch-result"></div>

            <!-- 名称 + Slug -->
            <div class="field-row">
                <div class="two-col">
                    <div>
                        <label for="nav_name">网站名称</label>
                        <input type="text" id="nav_name" name="nav_name" value="<?php echo esc_attr($meta['name'] ?? ''); ?>" placeholder="网站名称">
                    </div>
                    <div>
                        <label for="nav_slug">URL别名 (Slug)</label>
                        <input type="text" id="nav_slug" name="nav_slug" value="<?php echo esc_attr(get_post_field('post_name', $postId)); ?>" placeholder="baidu-search">
                    </div>
                </div>
            </div>

            <!-- 关键词 -->
            <div class="field-row">
                <label for="nav_keywords">关键词</label>
                <input type="text" id="nav_keywords" name="nav_keywords" value="<?php echo esc_attr($meta['keywords'] ?? ''); ?>" placeholder="关键词1, 关键词2, ...">
            </div>
        </div>
    </div>

    <!-- ═══ 第二区：描述与简介 ═══ -->
    <div class="section">
        <div class="section-title">📝 描述与简介</div>
        <div class="section-body">
            <!-- 网站描述 -->
            <div class="field-row">
                <label for="nav_description">网站描述</label>
                <textarea id="nav_description" name="nav_description" placeholder="简短描述网站内容，用于 SEO..."><?php echo esc_textarea($meta['description'] ?? ''); ?></textarea>
            </div>

            <!-- 网站简介 -->
            <div class="field-row">
                <label for="nav_ai_summary">网站简介</label>
                <textarea id="nav_ai_summary" name="nav_ai_summary" placeholder="详细介绍，300-500字..." style="min-height:200px;"><?php echo esc_textarea($meta['ai_summary'] ?? ''); ?></textarea>
                <div style="margin-top:6px;">
                    <button type="button" class="btn-ai-alt" id="btn-ai-summary">✨ AI 生成简介</button>
                    <span class="desc" style="margin-left:8px;">基于网站名称和描述，AI 自动生成 300-500 字介绍</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ 第三区：图片 ═══ -->
    <div class="section">
        <div class="section-title">🖼️ 图片资源</div>
        <div class="section-body">
            <div class="field-row">
                <div class="two-col">
                    <div>
                        <label for="nav_logo">Logo URL</label>
                        <input type="text" id="nav_logo" name="nav_logo" value="<?php echo esc_attr($meta['logo'] ?? ''); ?>" placeholder="https://example.com/logo.png">
                    </div>
                    <div>
                        <label for="nav_screenshot">截图 URL</label>
                        <input type="text" id="nav_screenshot" name="nav_screenshot" value="<?php echo esc_attr($meta['screenshot'] ?? ''); ?>" placeholder="留空自动生成">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ 第四区：状态 ═══ -->
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
    const btn = document.getElementById('btn-fetch-meta');
    const resultBox = document.getElementById('fetch-result');
    const urlInput = document.getElementById('nav_url');

    if (!btn) return;

    btn.addEventListener('click', function() {
        const url = urlInput.value.trim();
        if (!url) { alert('请先输入网址'); return; }

        btn.disabled = true;
        btn.textContent = '抓取中...';
        resultBox.classList.remove('error');
        resultBox.classList.add('show');
        resultBox.innerHTML = '<em>⏳ 正在抓取网页信息...</em>';

        fetch('<?php echo esc_url(rest_url('ai-plus/v1/nav/fetch')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>'
            },
            body: JSON.stringify({ url: url })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = '🤖 AI 获取';
            if (data.code !== 'success') {
                resultBox.classList.add('error');
                resultBox.innerHTML = '<em>❌ 抓取失败：' + (data.message || '未知错误') + '</em>';
                return;
            }
            var d = data.data || {};
            if (d.name) document.getElementById('nav_name').value = d.name;
            if (d.keywords) document.getElementById('nav_keywords').value = d.keywords;
            if (d.description) document.getElementById('nav_description').value = d.description;
            if (d.logo) document.getElementById('nav_logo').value = d.logo;
            if (d.slug) document.getElementById('nav_slug').value = d.slug;

            resultBox.classList.remove('error');
            resultBox.innerHTML = '<strong>✅ 抓取成功！</strong> 已自动填入名称、关键词、描述、Logo 和 Slug';
            setTimeout(() => resultBox.classList.remove('show'), 5000);
        })
        .catch(e => {
            btn.disabled = false;
            btn.textContent = '🤖 AI 获取';
            resultBox.classList.add('error');
            resultBox.innerHTML = '<em>❌ 抓取失败：' + e.message + '</em>';
        });
    });

    // AI 生成简介
    const btnSummary = document.getElementById('btn-ai-summary');
    if (btnSummary) {
        btnSummary.addEventListener('click', function() {
            const url = urlInput.value.trim();
            const name = document.getElementById('nav_name').value.trim();
            const desc = document.getElementById('nav_description').value.trim();
            if (!url || !name) { alert('请先填写网址和名称（可点 AI 获取自动填充）'); return; }

            btnSummary.disabled = true;
            btnSummary.textContent = '生成中...';
            fetch('<?php echo esc_url(rest_url('ai-plus/v1/nav/ai-summary')); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({ url, name, description: desc })
            })
            .then(r => r.json())
            .then(data => {
                btnSummary.disabled = false;
                btnSummary.textContent = '✨ AI 生成简介';
                if (data.code === 'success' && (data.data || {}).content) {
                    document.getElementById('nav_ai_summary').value = data.data.content;
                } else {
                    alert('AI 简介生成失败：' + (data.message || '未知错误'));
                }
            })
            .catch(() => {
                btnSummary.disabled = false;
                btnSummary.textContent = '✨ AI 生成简介';
            });
        });
    }
})();
</script>
