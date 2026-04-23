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
        <div class="zuo-feature-list">
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
    </div>

    <div class="zuo-section">
        <h2>截图策略（3 层优先级）</h2>
        <div class="zuo-grid">
            <div class="zuo-card">
                <h3>① og:image</h3>
                <p>优先读取目标网站 HTML 中的 <code>og:image</code> 元标签，速度最快，成本为零。</p>
            </div>
            <div class="zuo-card">
                <h3>② Chrome Headless</h3>
                <p>本地 Chrome/Chromium 无头模式截图，保存到 <code>uploads/nav-screenshots/</code>（7 天缓存），注册为 WordPress 媒体附件。</p>
            </div>
            <div class="zuo-card">
                <h3>③ thum.io 兜底</h3>
                <p>前两者均失败时，调用 thum.io 在线截图服务作为最终兜底方案。</p>
            </div>
        </div>
    </div>

    <div class="zuo-section">
        <h2>快速开始</h2>
        <ol style="line-height:1.8;color:#2d3748">
            <li>上传 <code>zuo-ai-plus</code> 到 <code>/wp-content/plugins/</code></li>
            <li>在 WordPress 插件菜单激活</li>
            <li>进入 <strong>Zuo AI Plus → 文本模型</strong> 配置 API Key</li>
            <li>开始使用 Gutenberg 侧边栏或导航模块</li>
        </ol>
    </div>

    <div class="zuo-section">
        <h2>版本历史</h2>
        <div class="zuo-card" style="font-size:.9em;color:#4a5568">
            <code>
1.5.19 · 2026-04-23  特色图中文metadata智能备用 + 双层包装访问bug修复<br>
1.5.13 · 2026-04-22  AI导航模块正式发布<br>
1.3.1  · 2026-04-xx  初始公开版本
            </code>
        </div>
    </div>

    <div class="about-footer">
        <p>作者：<a href="https://www.yily.top" target="_blank">左运来</a> · 
        插件地址：<a href="https://gitee.com/zuoyunlai/zuo-ai-plus" target="_blank">Gitee</a> · 
        问题反馈：<a href="https://www.yily.top" target="_blank">yily.top</a></p>
    </div>
</div>
