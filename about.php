<?php
/**
 * Zuo AI Plus 关于页面
 * Version: 1.5.19
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap about-wrap">
    <style>
        .zuo-about-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:40px 40px 50px;border-radius:12px;margin:20px 0 30px;position:relative;overflow:hidden}
        .zuo-about-header h1{color:#fff;font-size:2.6em;margin:0 0 10px;font-weight:700}
        .zuo-about-header p{font-size:1.2em;opacity:.9;margin:0;max-width:600px;line-height:1.6}
        .zuo-about-header .version-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:4px 16px;font-size:.85em;margin-bottom:15px;display:inline-block}
        .zuo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:30px 0}
        .zuo-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .zuo-card h3{font-size:1.15em;margin:0 0 12px;display:flex;align-items:center;gap:8px;color:#1a202c}
        .zuo-card p{color:#4a5568;font-size:.95em;line-height:1.65;margin:0}
        .zuo-section{margin-bottom:35px}
        .zuo-section h2{font-size:1.4em;margin:0 0 16px;padding-bottom:8px;border-bottom:2px solid #667eea;color:#1a202c}
        .zuo-tag{background:#edf2f7;border-radius:6px;padding:2px 10px;font-size:.8em;color:#4a5568;margin-right:6px}
        .zuo-feature-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
        .zuo-feature{background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;display:flex;align-items:flex-start;gap:10px;font-size:.92em;color:#2d3748;line-height:1.5}
        .zuo-feature strong{min-width:90px;color:#1a202c}
        .about-footer{text-align:center;padding:30px;color:#718096;font-size:.9em;margin-top:20px}
        .about-footer a{color:#667eea;text-decoration:none}
        /* 导航模块主介绍卡片 */
        .nav-intro-card{background:linear-gradient(135deg,#f0f4ff 0%,#e8f0fe 100%);border:1px solid #c7d9f8;border-radius:12px;padding:28px 30px;margin-bottom:24px}
        .nav-intro-card h3{font-size:1.3em;color:#1a202c;margin:0 0 10px}
        .nav-intro-card p{color:#3a4a6b;font-size:1em;line-height:1.7;margin:0 0 14px}
        .nav-intro-card .nav-cta{display:inline-flex;align-items:center;gap:6px;background:#4f66e8;color:#fff;border-radius:8px;padding:8px 18px;text-decoration:none;font-size:.9em;margin-top:4px}
        .nav-intro-card .nav-cta:hover{background:#3d52d0}
        .nav-intro-card .nav-cta .arrow{font-size:1.1em}
        /* 截图策略简化 */
        .screenshot-tips{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:16px}
        .screenshot-tip{background:#f8f9fc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;font-size:.88em;color:#4a5568;line-height:1.6}
        .screenshot-tip b{color:#1a202c}
        /* 快速开始卡片样式 */
        .step-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;display:flex;align-items:flex-start;gap:16px;margin-bottom:12px}
        .step-card .step-num{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:50%;width:32px;height:32px;min-width:32px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95em}
        .step-card .step-body h4{font-size:1em;color:#1a202c;margin:0 0 4px}
        .step-card .step-body p{color:#4a5568;font-size:.9em;margin:0;line-height:1.6}
        .step-card .step-body code{background:#edf2f7;padding:1px 6px;border-radius:4px;font-size:.88em;color:#667eea}
        /* 版本历史 */
        .changelog{margin-top:12px}
        .changelog-item{ padding:10px 0;border-bottom:1px solid #f0f0f0}
        .changelog-item:last-child{border-bottom:none}
        .changelog-item .ver{font-weight:600;color:#1a202c;margin-right:8px}
        .changelog-item .date{color:#a0aec0;font-size:.85em;margin-left:6px}
    </style>

    <div class="zuo-about-header">
        <div class="version-badge">v1.5.19 · 2026-04-23</div>
        <h1>Zuo AI Plus</h1>
        <p>集成国内主流大模型的 WordPress AI 内容助手，附带智能导航目录模块。<br>支持文章生成、SEO 优化、文生图、客服聊天、网址导航……</p>
    </div>

    <div class="zuo-section">
        <h2>支持模型</h2>
        <div class="zuo-grid">
            <div class="zuo-card">
                <h3>🧠 文本模型</h3>
                <p>智谱 GLM-5 / GLM-5-Turbo / GLM-4-Flashx、阿里通义千问、MiniMax M2.7、Kimi K2.5、DeepSeek V3.2 / Coder、DashScope GLM-5.1，以及 <strong>任意 OpenAI 兼容接口</strong>。</p>
            </div>
            <div class="zuo-card">
                <h3>🎨 图像模型</h3>
                <p>智谱 CogView-3、阿里通义万相（wanx2.1）、MiniMax 图像生成，支持本地 Chrome Headless 截图 + AI metadata 全自动处理。</p>
            </div>
        </div>
    </div>

    <div class="zuo-section">
        <h2>核心功能</h2>
        <div class="zuo-feature-list">
            <div class="zuo-feature"><strong>⚡ 文章生成</strong> 输入标题/提示，一键生成完整文章</div>
            <div class="zuo-feature"><strong>🔄 内容改写</strong> 保持原意优化表达，多种文风可选</div>
            <div class="zuo-feature"><strong>📝 内容续写</strong> 智能续写现有草稿，自动判断方向</div>
            <div class="zuo-feature"><strong>🏷️ 自动标签</strong> AI 提取 3-5 个 SEO 友好标签</div>
            <div class="zuo-feature"><strong>🎯 标题优化</strong> 生成 30-60 字符 SEO 友好标题</div>
            <div class="zuo-feature"><strong>📖 自动摘要</strong> 一键生成 100 字摘要</div>
            <div class="zuo-feature"><strong>🔗 别名生成</strong> 自动生成 WordPress URL slug</div>
            <div class="zuo-feature"><strong>🖼️ 特色图</strong> AI 生成封面图，或截图自动设为特色图</div>
            <div class="zuo-feature"><strong>🌐 多语言翻译</strong> 中英日韩法德等 12 种语言</div>
            <div class="zuo-feature"><strong>🔍 SEO 诊断</strong> 全站批量诊断评分 + AI 一键优化</div>
            <div class="zuo-feature"><strong>💬 客服聊天</strong> 浮动 AI 聊天窗口，短代码嵌入</div>
            <div class="zuo-feature"><strong>📊 使用统计</strong> Token 消耗、操作次数、各模型分布</div>
        </div>
    </div>

    <div class="zuo-section">
        <h2>🆕 AI 导航模块</h2>

        <!-- 主介绍卡片 -->
        <div class="nav-intro-card">
            <h3>🧭 智能网址导航目录</h3>
            <p>输入任意网站 URL，AI 自动完成：抓取名称/关键词/描述/Logo → Chrome 截图设为特色图 → 生成 300-500 字详细介绍 → 提取分类标签写入 WordPress。整个过程一键完成，无需手动编辑。</p>
            <p style="margin-bottom:16px">适合做 <strong>资源导航站、工具收藏夹、链接集合页</strong>，每一个网站都有完整的截图、介绍、SEO 权重排名和结构化数据。</p>
            <a class="nav-cta" href="<?php echo admin_url('edit.php?post_type=nav_site'); ?>">
                去管理导航内容 <span class="arrow">→</span>
            </a>
        </div>

        <div class="zuo-feature-list" style="margin-top:0">
            <div class="zuo-feature"><strong>🔗 AI 全量获取</strong> 输入网址，自动抓取名称/关键词/描述/Logo/截图</div>
            <div class="zuo-feature"><strong>📸 特色图自动化</strong> Chrome 截图注册为 WP 媒体附件，自动设特色图 + AI alt/标题/描述</div>
            <div class="zuo-feature"><strong>✨ AI 生成简介</strong> 300-500 字详细网站介绍</div>
            <div class="zuo-feature"><strong>🤖 标签自动提取</strong> 从简介中提取 nav_tag 标签，自动写入 WordPress 标签盒</div>
            <div class="zuo-feature"><strong>📊 SEO 权重胶囊</strong> 百度/360/搜狗/头条/移动排名彩色胶囊</div>
            <div class="zuo-feature"><strong>🌳 分类树</strong> 层级分类展开/折叠侧栏</div>
            <div class="zuo-feature"><strong>🌙 暗色模式</strong> CSS 变量完整支持，与主题切换同步</div>
            <div class="zuo-feature"><strong>📱 响应式</strong> 480/768/900px 三断点，移动端优先</div>
            <div class="zuo-feature"><strong>🔍 结构化数据</strong> Schema.org BreadcrumbList + WebSite JSON-LD</div>
            <div class="zuo-feature"><strong>📲 分享&二维码</strong> 移动端分享弹窗 + QR Code</div>
        </div>

        <!-- 截图策略说明 -->
        <div style="margin-top:24px;padding:18px 22px;background:#f8f9fc;border:1px solid #e2e8f0;border-radius:10px">
            <h4 style="margin:0 0 10px;font-size:1em;color:#1a202c">📸 截图策略（3 层优先级，自动降级）</h4>
            <div class="screenshot-tips">
                <div class="screenshot-tip"><b>① og:image</b><br>优先读取目标网站自带的 og:image 元标签，速度最快，零成本消耗。</div>
                <div class="screenshot-tip"><b>② Chrome Headless</b><br>本地 Chromium 无头模式截图，保存到 <code>uploads/nav-screenshots/</code>（7 天缓存），注册为 WP 媒体附件。</div>
                <div class="screenshot-tip"><b>③ thum.io 兜底</b><br>前两者均失败时，调用 thum.io 在线截图服务作为最终兜底方案。</div>
            </div>
        </div>
    </div>

    <div class="zuo-section">
        <h2>快速开始</h2>

        <div class="step-card">
            <div class="step-num">1</div>
            <div class="step-body">
                <h4>上传并激活插件</h4>
                <p>将 <code>zuo-ai-plus</code> 文件夹上传到 WordPress 的 <code>/wp-content/plugins/</code> 目录，然后在「插件」菜单中找到并<strong>激活</strong>。</p>
            </div>
        </div>

        <div class="step-card">
            <div class="step-num">2</div>
            <div class="step-body">
                <h4>配置 API Key</h4>
                <p>进入 <strong>Zuo AI Plus → 文本模型</strong>（或菜单顶部 AI 图标），填入你所用模型的 API Key。各主流模型（智谱、阿里通义、MiniMax、Kimi 等）均提供免费额度。</p>
            </div>
        </div>

        <div class="step-card">
            <div class="step-num">3</div>
            <div class="step-body">
                <h4>使用 AI 内容助手</h4>
                <p>在任意文章的 <strong>Gutenberg 编辑器</strong>右侧边栏，找到「Zuo AI Plus」面板。输入标题即可一键生成文章，也可以使用改写、续写、翻译、摘要等功能。</p>
            </div>
        </div>

        <div class="step-card">
            <div class="step-num">4</div>
            <div class="step-body">
                <h4>使用 AI 导航模块</h4>
                <p>进入 <strong>导航网站 → 添加导航网站</strong>，粘贴任意网址，点击「AI 全量获取」，插件自动完成：截图 → 特色图 → 简介 → 标签，全部一步搞定。</p>
            </div>
        </div>

        <div class="step-card">
            <div class="step-num">5</div>
            <div class="step-body">
                <h4>将导航嵌入前台页面</h4>
                <p>在任意页面/文章编辑器中添加 Gutenberg 区块 <code>AI Plus 导航</code>，或在前端模板中调用导航页面（<code>nav_site</code> post type 归档）。</p>
            </div>
        </div>
    </div>

    <div class="zuo-section">
        <h2>版本历史</h2>
        <div class="changelog">
            <div class="changelog-item">
                <span class="ver">v1.5.19</span>
                <span class="date">2026-04-23</span>
                特色图中文 metadata 智能备用 + 双层包装访问 bug 修复
            </div>
            <div class="changelog-item">
                <span class="ver">v1.5.13</span>
                <span class="date">2026-04-22</span>
                AI 导航模块正式发布（全量获取/简介/标签/截图/特色图）
            </div>
            <div class="changelog-item">
                <span class="ver">v1.3.1</span>
                <span class="date">2026-04-xx</span>
                初始公开版本：文章生成、SEO 优化、客服聊天、模型测试场
            </div>
        </div>
    </div>

    <div class="about-footer">
        <p>作者：<a href="https://www.yily.top" target="_blank">左运来</a> ·
        插件地址：<a href="https://gitee.com/zuoyunlai/zuo-ai-plus" target="_blank">Gitee</a> ·
        问题反馈：<a href="https://www.yily.top" target="_blank">yily.top</a></p>
    </div>
</div>
