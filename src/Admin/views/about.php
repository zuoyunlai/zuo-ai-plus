<?php
/* @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals */
if (!defined('ABSPATH')) exit;
$ai_version = defined('AI_PLUS_VERSION') ? AI_PLUS_VERSION : '1.5.19';
/* @phpcs:enable WordPress.NamingConventions.PrefixAllGlobals */
?>
<style>
.ai-about-wrap {font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 960px; color: #1e1e1e;}
.ai-about-hero {background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 12px; padding: 32px 36px; margin-bottom: 28px; display: flex; align-items: center; gap: 24px;}
.ai-about-hero-icon {font-size: 48px;}
.ai-about-hero h1 {color: #fff; margin: 0 0 6px; font-size: 26px; font-weight: 700;}
.ai-about-hero p {color: rgba(255,255,255,0.8); margin: 0; font-size: 14px;}
.version-badge {display: inline-block; background: rgba(255,255,255,0.2); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px;}
.ai-about-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;}
.ai-card {background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);}
.ai-card h2 {font-size: 14px; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 8px;}
.ai-card table {width: 100%; border-collapse: collapse; font-size: 13px;}
.ai-card td {padding: 7px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;}
.ai-card td:first-child {font-weight: 600; color: #6366f1; width: 110px; white-space: nowrap;}
.ai-new-tag {background: #6366f1; color: #fff; font-size: 10px; padding: 1px 7px; border-radius: 10px; font-weight: 600; margin-left: 6px; vertical-align: middle;}
.ai-tip {background: #f0f6fc; border-left: 4px solid #6366f1; border-radius: 0 6px 6px 0; padding: 14px 18px; font-size: 13px; line-height: 1.9; margin-bottom: 28px;}
.ai-tip strong {color: #1e1e1e;}
.ai-full {grid-column: 1 / -1;}
.ai-full-card {display: grid; grid-template-columns: 1fr 1fr; gap: 16px;}
.ai-subcard {background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;}
.ai-subcard h3 {margin: 0 0 8px; font-size: 13px; font-weight: 600;}
.ai-subcard p {margin: 0; font-size: 12px; color: #666; line-height: 1.7;}
.ai-footer {text-align: center; color: #aaa; font-size: 12px; padding-top: 8px;}

/* 导航模块主卡片 */
.ai-nav-intro {background: linear-gradient(135deg, #eff4ff 0%, #e8f0fe 100%); border: 1px solid #c7d9f8; border-radius: 12px; padding: 24px 28px; margin-bottom: 24px;}
.ai-nav-intro h3 {font-size: 15px; color: #1e1e1e; margin: 0 0 10px; display: flex; align-items: center; gap: 8px;}
.ai-nav-intro p {color: #3a4a6b; font-size: 13px; line-height: 1.75; margin: 0 0 14px;}
.ai-nav-intro .nav-cta {display: inline-flex; align-items: center; gap: 6px; background: #4f66e8; color: #fff; border-radius: 8px; padding: 8px 18px; text-decoration: none; font-size: 13px; font-weight: 500;}
.ai-nav-intro .nav-cta:hover {background: #3d52d0;}
.ai-nav-intro .nav-cta .arrow {font-size: 15px;}

/* 截图策略 */
.ai-screenshot-tips {display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 14px;}
.ai-screenshot-tip {background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #4a5568; line-height: 1.65;}
.ai-screenshot-tip b {color: #1e1e1e; display: block; margin-bottom: 4px; font-size: 12px;}
.ai-screenshot-tip code {background: #f0f6fc; padding: 1px 5px; border-radius: 3px; font-size: 11px; color: #6366f1;}

/* 快速入门步骤卡片 */
.ai-steps {display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px;}
.ai-step-card {background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px; display: flex; align-items: flex-start; gap: 16px;}
.ai-step-num {background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-radius: 50%; width: 30px; height: 30px; min-width: 30px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;}
.ai-step-body h4 {font-size: 13px; color: #1e1e1e; margin: 0 0 4px; font-weight: 600;}
.ai-step-body p {color: #555; font-size: 12px; margin: 0; line-height: 1.6;}
.ai-step-body p code {background: #f0f6fc; padding: 1px 6px; border-radius: 3px; font-size: 11px; color: #6366f1;}
.ai-step-body strong {color: #1e1e1e;}

/* 版本历史 */
.ai-changelog {margin-top: 12px;}
.ai-changelog-item {padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px;}
.ai-changelog-item:last-child {border-bottom: none;}
.ai-changelog-item .ver {font-weight: 600; color: #1e1e1e; margin-right: 8px;}
.ai-changelog-item .date {color: #aaa; font-size: 11px; margin-left: 6px;}
.ai-changelog-item .log {color: #555;}

/* ========== 移动端响应式优化 ========== */
@media screen and (max-width: 782px) {
    .ai-about-wrap {padding: 0 10px;}
    .ai-about-hero {flex-direction: column; text-align: center; padding: 24px 20px; gap: 16px;}
    .ai-about-hero-icon {font-size: 40px;}
    .ai-about-hero h1 {font-size: 22px;}
    .ai-about-hero p {font-size: 13px;}

    .ai-about-grid {grid-template-columns: 1fr; gap: 16px;}
    .ai-card {padding: 16px;}
    .ai-card table {font-size: 12px;}
    .ai-card td {padding: 6px 0;}
    .ai-card td:first-child {width: auto; min-width: 70px; white-space: normal; word-break: break-all;}

    .ai-full-card {grid-template-columns: 1fr; gap: 12px;}

    .ai-tip {padding: 14px 16px; font-size: 13px; line-height: 1.8;}

    .ai-card table {display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;}
    .ai-card tbody {display: table; width: 100%; min-width: 280px;}

    /* 导航模块 */
    .ai-nav-intro {padding: 18px 16px;}
    .ai-screenshot-tips {grid-template-columns: 1fr; gap: 8px;}
    .ai-nav-intro .nav-cta {width: 100%; justify-content: center;}

    /* 步骤卡片 */
    .ai-step-card {padding: 14px 16px; gap: 12px;}
    .ai-step-num {width: 26px; height: 26px; min-width: 26px; font-size: 12px;}
    .ai-step-body h4 {font-size: 12px;}
    .ai-step-body p {font-size: 11px;}
}

@media screen and (max-width: 480px) {
    .ai-about-hero {padding: 20px 16px;}
    .ai-about-hero h1 {font-size: 20px;}
    .ai-about-hero-icon {font-size: 36px;}
    .ai-card {padding: 14px 12px; border-radius: 8px;}
    .ai-card h2 {font-size: 13px; margin-bottom: 12px;}
    .ai-card table {font-size: 11px;}
    .ai-card td {padding: 5px 0;}
    .ai-card td:first-child {min-width: 60px; padding-right: 8px;}
    .ai-card code {font-size: 10px; padding: 1px 4px;}
    .ai-new-tag {font-size: 9px; padding: 1px 5px;}
    .ai-subcard h3 {font-size: 12px;}
    .ai-subcard p {font-size: 11px;}
    .ai-tip {font-size: 12px; padding: 12px 14px;}
    .ai-footer {font-size: 11px;}
}
</style>

<div class="ai-about-wrap">

    <!-- Hero -->
    <div class="ai-about-hero">
        <div class="ai-about-hero-icon">🤖</div>
        <div>
            <h1>Zuo AI Plus 关于</h1>
            <p>为 WordPress 打造的一站式 AI 内容助手 · 写作 · 优化 · 配图 · 客服</p>
            <span class="version-badge">v<?php echo esc_html($ai_version); ?></span>
        </div>
    </div>

    <!-- 快速入门 -->
    <div class="ai-steps">
        <div class="ai-step-card">
            <div class="ai-step-num">1</div>
            <div class="step-body">
                <h4>上传并激活插件</h4>
                <p>将 <code>zuo-ai-plus</code> 文件夹上传到 WordPress 的 <code>/wp-content/plugins/</code> 目录，然后在「插件」菜单中找到并<strong>激活</strong>。</p>
            </div>
        </div>
        <div class="ai-step-card">
            <div class="ai-step-num">2</div>
            <div class="step-body">
                <h4>配置 API Key</h4>
                <p>进入「<strong>Zuo AI Plus → 文本模型</strong>」Tab，展开各平台卡片填入 API Key。各主流模型（智谱、阿里通义、MiniMax、Kimi 等）均提供免费额度。</p>
            </div>
        </div>
        <div class="ai-step-card">
            <div class="ai-step-num">3</div>
            <div class="step-body">
                <h4>使用 AI 内容助手</h4>
                <p>在任意文章的 <strong>Gutenberg 编辑器</strong>右侧边栏找到「Zuo AI Plus」面板。输入标题即可一键生成文章，也可以使用改写、续写、翻译、摘要等功能。</p>
            </div>
        </div>
        <div class="ai-step-card">
            <div class="ai-step-num">4</div>
            <div class="step-body">
                <h4>使用 AI 导航模块</h4>
                <p>进入 <strong>导航网站 → 添加导航网站</strong>，粘贴任意网址，点击「AI 全量获取」，插件自动完成：截图 → 特色图 → 简介 → 标签，全部一步搞定。</p>
            </div>
        </div>
        <div class="ai-step-card">
            <div class="ai-step-num">5</div>
            <div class="step-body">
                <h4>将导航嵌入前台页面</h4>
                <p>在任意页面编辑器中添加 Gutenberg 区块 <code>AI Plus 导航</code>，或直接访问导航页面（<code>nav_site</code> post type 归档）。</p>
            </div>
        </div>
    </div>

    <!-- 功能列表 + 支持模型 -->
    <div class="ai-about-grid">

        <!-- 功能概览 -->
        <div class="ai-card">
            <h2>🎯 功能列表</h2>
            <table>
                <tr><td>🖊️ 文章生成</td><td>输入标题，AI 自动生成结构完整、内容丰富的文章</td></tr>
                <tr><td>📝 内容续写</td><td>在现有内容基础上续写更多细节、案例和论述</td></tr>
                <tr><td>✏️ 内容改写 <span class="ai-new-tag">NEW</span></td><td>重写/润色现有内容，保持原意不变，优化表达方式</td></tr>
                <tr><td>🎭 风格语气 <span class="ai-new-tag">NEW</span></td><td>多种文字风格选择：专业正式、轻松随性、温暖亲切等</td></tr>
                <tr><td>🎯 标题优化 <span class="ai-new-tag">NEW</span></td><td>AI 自动生成 SEO 友好的最佳标题（30-60字，搜索意图词增强）</td></tr>
                <tr><td>📋 内容摘要</td><td>一键生成 100 字以内的精准摘要</td></tr>
                <tr><td>🏷️ 自动标签 <span class="ai-new-tag">NEW</span></td><td>分析文章内容，自动提取 3-5 个 SEO 友好关键词标签</td></tr>
                <tr><td>🔗 别名生成</td><td>根据标题自动生成 WordPress 友好 URL 别名（slug）</td></tr>
                <tr><td>🌐 翻译</td><td>支持 12 种语言互译，直接替换编辑器内容</td></tr>
                <tr><td>📚 知识库 <span class="ai-new-tag">NEW</span></td><td>设置背景知识，AI 生成内容时自动参考品牌/产品信息</td></tr>
                <tr><td>🖼️ 特色图生成</td><td>根据文章内容生成封面图，自动下载并设为特色图</td></tr>
                <tr><td>🖼️ 插图生成 <span class="ai-new-tag">NEW</span></td><td>在文章任意位置插入 AI 生成的配图，自动上传媒体库</td></tr>
                <tr><td>🔍 SEO 诊断 <span class="ai-new-tag">核心</span></td><td>全站文章批量诊断 · 得分分析 · 问题定位 · AI 一键优化</td></tr>
                <tr><td>🧭 网址导航 <span class="ai-new-tag">NEW</span></td><td>AI全量获取网站信息，自动截图设特色图，SEO权重胶囊显示</td></tr>
                <tr><td>📸 网站截图 <span class="ai-new-tag">NEW</span></td><td>Chrome Headless截取网站截图，自动设为特色图，AI生成图片alt/标题/描述</td></tr>
                <tr><td>🏷️ AI提取标签 <span class="ai-new-tag">NEW</span></td><td>从网站简介自动提取导航标签，写入WP原生标签栏</td></tr>
                <tr><td>🌙 暗色模式 <span class="ai-new-tag">NEW</span></td><td>CSS变量驱动的全暗色模式支持，自动跟随主题切换</td></tr>
                <tr><td>💬 网站客服</td><td>前台右下角悬浮 AI 助手，感知当前文章上下文</td></tr>
                <tr><td>🧱 Gutenberg 区块</td><td>聊天区块（实时对话）+ 图片生成区块（编辑器内生图）</td></tr>
                <tr><td>📋 Shortcode</td><td>任意页面嵌入 AI 对话窗口，支持指定模型</td></tr>
                <tr><td>🔄 Playground</td><td>自由对话测试，保存草稿，复制内容</td></tr>
                <tr><td>⚖️ 智能续写 <span class="ai-new-tag">NEW</span></td><td>根据上下文自动判断续写方向，无需手动选择</td></tr>
                <tr><td>🔧 多模型切换 <span class="ai-new-tag">NEW</span></td><td>后台任意位置快速切换 AI 模型，灵活对比测试</td></tr>
                <tr><td>📊 使用统计 <span class="ai-new-tag">NEW</span></td><td>实时追踪 Token 消耗、操作次数、各模型使用分布</td></tr>
            </table>
        </div>

        <!-- 支持模型 -->
        <div class="ai-card">
            <h2>⚙️ 支持的模型</h2>
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid #f0f0f0;">
                        <th style="text-align:left;padding:4px 0;font-size:12px;color:#888;">平台</th>
                        <th style="text-align:left;padding:4px 0;font-size:12px;color:#888;">文本模型</th>
                        <th style="text-align:left;padding:4px 0;font-size:12px;color:#888;">文生图</th>
                    </tr>
                </thead>
                <tr><td>通义千问</td><td>qwen-turbo / qwen-plus</td><td>wanx2.1-image ✅</td></tr>
                <tr><td>智谱 GLM</td><td>glm-4-flashx / glm-5</td><td>cogview-3 ✅</td></tr>
                <tr><td>MiniMax</td><td>MiniMax-M2.7 / MiniMax-M2</td><td>image-01 ✅</td></tr>
                <tr><td>Kimi</td><td>moonshot-v1-8k / kimi-k2.5</td><td>—</td></tr>
                <tr><td>DeepSeek</td><td>deepseek-chat / deepseek-coder</td><td>—</td></tr>
                <tr><td>自定义（代理）</td><td>自填 · OpenAI 兼容</td><td>OpenAI 兼容 ✅</td></tr>
            </table>

            <h2 style="margin-top:20px;">📋 Shortcode 短代码</h2>
            <table>
                <tr><td><code>[ai_plus_chat]</code></td><td>前台嵌入聊天窗口（自动用默认模型）</td></tr>
                <tr><td><code>[ai_plus_chat model="kimi"]</code></td><td>指定默认模型（zhipu/tongyi/minimax/kimi/deepseek/custom）</td></tr>
            </table>
        </div>

    </div>

    <!-- 🔍 SEO 诊断功能详解 -->
    <div class="ai-card ai-full" style="margin-bottom:28px;">
        <h2>🔍 SEO 诊断功能详解 <span class="ai-new-tag">核心功能</span></h2>
        <div class="ai-full-card">
            <div class="ai-subcard">
                <h3>📊 诊断维度</h3>
                <p>· <strong>标题长度</strong>（建议 30-60 字，含核心关键词）<br>· <strong>标签质量</strong>（建议 3-5 个，单标签≤6字，SEO友好）<br>· <strong>摘要完整性</strong>（建议 80-120 字，含关键词+价值点+行动引导）<br>· <strong>内容字数</strong>（建议 500 字以上）<br>· <strong>综合 SEO 得分</strong>（0-100 分，≥95 分为优秀）</p>
            </div>
            <div class="ai-subcard">
                <h3>🤖 AI 批量优化</h3>
                <p>一键对选中文章进行 AI 优化：<br>· 自动重写标题（SEO友好 + 搜索意图词增强 + 关键词靠前）<br>· 智能生成 / 补全摘要描述<br>· 提取相关标签（3-5个，SEO友好）<br>· 支持切换不同 AI 模型（通义千问 / 智谱 / MiniMax / Kimi）</p>
            </div>
            <div class="ai-subcard">
                <h3>🎯 标题优化（Gutenberg 集成）</h3>
                <p>在文章编辑器右侧 AI Plus 面板中：<br>· 选中标题内容 → 点击「🎯 优化标题」<br>· AI 结合正文内容，生成符合 SEO 的最佳标题<br>· 关键词靠前，搜索意图词增强<br>· 直接替换原标题，一键完成</p>
            </div>
            <div class="ai-subcard">
                <h3>📈 统计面板</h3>
                <p>· 全站文章总数<br>· 已优化 / 待优化 文章数<br>· 全站平均 SEO 得分<br>· 支持分页查看和单篇操作<br>· 一键诊断全部文章</p>
            </div>
        </div>
    </div>

    <!-- 🧭 AI 导航模块详解 -->
    <div class="ai-card ai-full" style="margin-bottom:28px;">
        <h2>🧭 AI 导航模块详解 <span class="ai-new-tag">NEW</span></h2>

        <!-- 主介绍卡片 -->
        <div class="ai-nav-intro">
            <h3>🧭 智能网址导航目录</h3>
            <p>输入任意网站 URL，AI 自动完成：抓取名称/关键词/描述/Logo → Chrome 截图设为特色图 → 生成 300-500 字详细介绍 → 提取分类标签写入 WordPress。整个过程一键完成，无需手动编辑。</p>
            <p style="margin-bottom:14px">适合做 <strong>资源导航站、工具收藏夹、链接集合页</strong>，每一个网站都有完整的截图、介绍、SEO 权重排名和结构化数据。</p>
            <a class="nav-cta" href="<?php echo admin_url('edit.php?post_type=nav_site'); ?>">
                去管理导航内容 <span class="arrow">→</span>
            </a>
        </div>

        <div class="ai-full-card">
            <div class="ai-subcard">
                <h3>🔗 AI 全量获取</h3>
                <p>输入网址，自动抓取：名称、关键词、描述、Logo、截图。一步到位，无需逐项手动填写。</p>
            </div>
            <div class="ai-subcard">
                <h3>📸 特色图自动化</h3>
                <p>Chrome Headless 截图自动注册为 WP 媒体附件，设为特色图，同时由 AI 生成 alt 文本、标题、描述。AI 未返回中文 metadata 时，自动从文章标题+内容提取备用。</p>
            </div>
            <div class="ai-subcard">
                <h3>✨ AI 生成简介</h3>
                <p>自动生成 300-500 字的网站详细介绍，适合作为导航目录中每个网站的说明文字。</p>
            </div>
            <div class="ai-subcard">
                <h3>🤖 标签自动提取</h3>
                <p>从简介中提取 nav_tag 分类标签，自动写入 WordPress 原生标签栏，无缝衔接 WP SEO。</p>
            </div>
            <div class="ai-subcard">
                <h3>📊 SEO 权重胶囊</h3>
                <p>百度/360/搜狗/头条/移动排名以彩色胶囊形式直观显示，方便访客快速判断网站权重。</p>
            </div>
            <div class="ai-subcard">
                <h3>🌙 暗色模式 + 📱 响应式</h3>
                <p>CSS 变量驱动的暗色模式，自动跟随主题切换。480/768/900px 三断点，移动端优先适配。</p>
            </div>
        </div>

        <!-- 截图策略 -->
        <div style="margin-top:16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px 18px;">
            <h4 style="font-size:13px; color:#1e1e1e; margin:0 0 10px;">📸 截图策略（3 层优先级，自动降级）</h4>
            <div class="ai-screenshot-tips">
                <div class="ai-screenshot-tip">
                    <b>① og:image</b>
                    优先读取目标网站自带的 <code>og:image</code> 元标签，速度最快，零成本消耗。
                </div>
                <div class="ai-screenshot-tip">
                    <b>② Chrome Headless</b>
                    本地 Chromium 无头模式截图，保存到 <code>uploads/nav-screenshots/</code>（7 天缓存），注册为 WP 媒体附件。
                </div>
                <div class="ai-screenshot-tip">
                    <b>③ thum.io 兜底</b>
                    前两者均失败时，调用 thum.io 在线截图服务作为最终兜底方案。
                </div>
            </div>
        </div>
    </div>

    <!-- 🧱 Gutenberg 区块 -->
    <div class="ai-card ai-full">
        <h2>🧱 Gutenberg 编辑器集成</h2>
        <div class="ai-full-card">
            <div class="ai-subcard">
                <h3>🤖 Zuo AI Plus 聊天</h3>
                <p>在文章中插入实时 AI 对话窗口。文章发布后，读者可以在页面内与 AI 实时对话，AI 会结合当前文章内容作为上下文作答。</p>
            </div>
            <div class="ai-subcard">
                <h3>🖼️ Zuo AI Plus 图片生成</h3>
                <p>在编辑器内直接输入图片描述，生成后替换为真实图片。支持通义千问、智谱 GLM、MiniMax 三大平台。</p>
            </div>
        </div>
    </div>

    <!-- 版本历史 -->
    <div class="ai-card ai-full">
        <h2>📋 版本历史</h2>
        <div class="ai-changelog">
            <div class="ai-changelog-item">
                <span class="ver">v1.5.19</span><span class="date">2026-04-23</span>
                <span class="log">特色图中文 metadata 智能备用 + 双层包装访问 bug 修复</span>
            </div>
            <div class="ai-changelog-item">
                <span class="ver">v1.5.13</span><span class="date">2026-04-22</span>
                <span class="log">AI 导航模块正式发布（全量获取/简介/标签/截图/特色图）</span>
            </div>
            <div class="ai-changelog-item">
                <span class="ver">v1.3.1</span><span class="date">2026-04-xx</span>
                <span class="log">初始公开版本：文章生成、SEO 优化、客服聊天、模型测试场</span>
            </div>
        </div>
    </div>

    <p class="ai-footer">Zuo AI Plus v<?php echo esc_html($ai_version); ?> · Made with ❤️ by 左运来 · <a href="https://www.yily.top" target="_blank" style="color:#6366f1;">yily.top</a></p>
</div>
