<?php
if (!defined('ABSPATH')) exit;
$version = defined('AI_PLUS_VERSION') ? AI_PLUS_VERSION : '1.2.0';
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

/* ========== 移动端响应式优化 ========== */
@media screen and (max-width: 782px) {
    .ai-about-wrap {
        padding: 0 10px;
    }
    .ai-about-hero {
        flex-direction: column;
        text-align: center;
        padding: 24px 20px;
        gap: 16px;
    }
    .ai-about-hero-icon {
        font-size: 40px;
    }
    .ai-about-hero h1 {
        font-size: 22px;
    }
    .ai-about-hero p {
        font-size: 13px;
    }

    .ai-about-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .ai-card {
        padding: 16px;
    }
    .ai-card h2 {
        font-size: 14px;
    }
    .ai-card table {
        font-size: 12px;
    }
    .ai-card td {
        padding: 6px 0;
    }
    .ai-card td:first-child {
        width: auto;
        min-width: 70px;
        white-space: normal;
        word-break: break-all;
    }

    .ai-full-card {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .ai-subcard {
        padding: 14px;
    }

    .ai-tip {
        padding: 14px 16px;
        font-size: 13px;
        line-height: 1.8;
    }

    /* 表格横向滚动 */
    .ai-card table {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .ai-card tbody {
        display: table;
        width: 100%;
        min-width: 280px;
    }
}

@media screen and (max-width: 480px) {
    .ai-about-hero {
        padding: 20px 16px;
    }
    .ai-about-hero h1 {
        font-size: 20px;
    }
    .ai-about-hero-icon {
        font-size: 36px;
    }

    .ai-card {
        padding: 14px 12px;
        border-radius: 8px;
        overflow: hidden;
    }
    .ai-card h2 {
        font-size: 13px;
        margin-bottom: 12px;
    }
    .ai-card table {
        font-size: 11px;
    }
    .ai-card td {
        padding: 5px 0;
    }
    .ai-card td:first-child {
        min-width: 60px;
        padding-right: 8px;
    }
    .ai-card code {
        font-size: 10px;
        padding: 1px 4px;
    }

    .ai-new-tag {
        font-size: 9px;
        padding: 1px 5px;
    }

    .ai-subcard h3 {
        font-size: 12px;
    }
    .ai-subcard p {
        font-size: 11px;
    }

    .ai-tip {
        font-size: 12px;
        padding: 12px 14px;
    }

    .ai-footer {
        font-size: 11px;
    }
}
</style>

<div class="ai-about-wrap">

    <!-- Hero -->
    <div class="ai-about-hero">
        <div class="ai-about-hero-icon">🤖</div>
        <div>
            <h1>Zuo AI Plus 关于</h1>
            <p>为 WordPress 打造的一站式 AI 内容助手 · 写作 · 优化 · 配图 · 客服</p>
            <span class="version-badge">v<?php echo esc_html($version); ?></span>
        </div>
    </div>

    <!-- 快速入门 -->
    <div class="ai-tip">
        <strong>🚀 快速入门</strong><br>
        <strong>第一步：</strong>进入「Zuo AI Plus 文本模型」Tab，展开各平台卡片，填入 API Key<br>
        <strong>第二步：</strong>在文章编辑器右侧的 AI Plus 面板直接使用所有功能<br>
        <strong>SEO 诊断：</strong>进入「🔍 SEO 诊断」Tab 批量查看并优化全站文章<br>
        <strong>知识库：</strong>在模型设置中添加背景知识，让 AI 更懂你的品牌
    </div>

    <!-- 功能列表 + 支持模型 -->
    <div class="ai-about-grid">

        <!-- 功能概览 -->
        <div class="ai-card">
            <h2>🎯 功能列表</h2>
            <table>
                <tr>
                    <td>🖊️ 文章生成</td>
                    <td>输入标题，AI 自动生成结构完整、内容丰富的文章</td>
                </tr>
                <tr>
                    <td>📝 内容续写</td>
                    <td>在现有内容基础上续写更多细节、案例和论述</td>
                </tr>
                <tr>
                    <td>✏️ 内容改写 <span class="ai-new-tag">NEW</span></td>
                    <td>重写/润色现有内容，保持原意不变，优化表达方式</td>
                </tr>
                <tr>
                    <td>🎭 风格语气 <span class="ai-new-tag">NEW</span></td>
                    <td>多种文字风格选择：专业正式、轻松随性、温暖亲切等</td>
                </tr>
                <tr>
                    <td>🎯 标题优化 <span class="ai-new-tag">NEW</span></td>
                    <td>AI 自动生成 SEO 友好的最佳标题（30-60字，搜索意图词增强）</td>
                </tr>
                <tr>
                    <td>📋 内容摘要</td>
                    <td>一键生成 100 字以内的精准摘要</td>
                </tr>
                <tr>
                    <td>🏷️ 自动标签 <span class="ai-new-tag">NEW</span></td>
                    <td>分析文章内容，自动提取 3-5 个 SEO 友好关键词标签</td>
                </tr>
                <tr>
                    <td>🔗 别名生成</td>
                    <td>根据标题自动生成 WordPress 友好 URL 别名（slug）</td>
                </tr>
                <tr>
                    <td>🌐 翻译</td>
                    <td>支持 12 种语言互译，直接替换编辑器内容</td>
                </tr>
                <tr>
                    <td>📚 知识库 <span class="ai-new-tag">NEW</span></td>
                    <td>设置背景知识，AI 生成内容时自动参考品牌/产品信息</td>
                </tr>
                <tr>
                    <td>🖼️ 特色图生成</td>
                    <td>根据文章内容生成封面图，自动下载并设为特色图</td>
                </tr>
                <tr>
                    <td>🖼️ 插图生成 <span class="ai-new-tag">NEW</span></td>
                    <td>在文章任意位置插入 AI 生成的配图，自动上传媒体库</td>
                </tr>
                <tr>
                    <td>🎨 图片生成</td>
                    <td>Playground 自由测试各大平台文生图模型</td>
                </tr>
                <tr>
                    <td>🔍 SEO 诊断 <span class="ai-new-tag">核心</span></td>
                    <td>全站文章批量诊断 · 得分分析 · 问题定位 · AI 一键优化</td>
                </tr>
                <tr>
                    <td>💬 网站客服</td>
                    <td>前台右下角悬浮 AI 助手，感知当前文章上下文</td>
                </tr>
                <tr>
                    <td>🧱 Gutenberg 区块</td>
                    <td>聊天区块（实时对话）+ 图片生成区块（编辑器内生图）</td>
                </tr>
                <tr>
                    <td>📋 Shortcode</td>
                    <td>任意页面嵌入 AI 对话窗口，支持指定模型</td>
                </tr>
                <tr>
                    <td>🔄 Playground</td>
                    <td>自由对话测试，保存草稿，复制内容</td>
                </tr>
                <tr>
                    <td>⚖️ 智能续写 <span class="ai-new-tag">NEW</span></td>
                    <td>根据上下文自动判断续写方向，无需手动选择</td>
                </tr>
                <tr>
                    <td>🔧 多模型切换 <span class="ai-new-tag">NEW</span></td>
                    <td>后台任意位置快速切换 AI 模型，灵活对比测试</td>
                </tr>
                <tr>
                    <td>📊 使用统计 <span class="ai-new-tag">NEW</span></td>
                    <td>实时追踪 Token 消耗、操作次数、各模型使用分布</td>
                </tr>
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
                <tr>
                    <td><code style="background:#f0f6fc;padding:2px 8px;border-radius:3px;font-size:12px;">[ai_plus_chat]</code></td>
                    <td>前台嵌入聊天窗口（自动用默认模型）</td>
                </tr>
                <tr>
                    <td><code style="background:#f0f6fc;padding:2px 8px;border-radius:3px;font-size:12px;">[ai_plus_chat model="kimi"]</code></td>
                    <td>指定默认模型（zhipu / tongyi / minimax / kimi / deepseek / custom）</td>
                </tr>
            </table>
        </div>

    </div>

    <!-- 🔍 SEO 诊断功能详解 -->
    <div class="ai-card ai-full" style="margin-bottom:28px;">
        <h2>🔍 SEO 诊断功能详解 <span class="ai-new-tag">核心功能</span></h2>
        <div class="ai-full-card">
            <div class="ai-subcard">
                <h3>📊 诊断维度</h3>
                <p>
                    · <strong>标题长度</strong>（建议 30-60 字，含核心关键词）<br>
                    · <strong>标签质量</strong>（建议 3-5 个，单标签≤6字，SEO友好）<br>
                    · <strong>摘要完整性</strong>（建议 80-120 字，含关键词+价值点+行动引导）<br>
                    · <strong>内容字数</strong>（建议 500 字以上）<br>
                    · <strong>综合 SEO 得分</strong>（0-100 分，≥95 分为优秀）
                </p>
            </div>
            <div class="ai-subcard">
                <h3>🤖 AI 批量优化</h3>
                <p>
                    一键对选中文章进行 AI 优化：<br>
                    · 自动重写标题（SEO友好 + 搜索意图词增强 + 关键词靠前）<br>
                    · 智能生成 / 补全摘要描述<br>
                    · 提取相关标签（3-5个，SEO友好）<br>
                    · 支持切换不同 AI 模型（通义千问 / 智谱 / MiniMax / Kimi）
                </p>
            </div>
            <div class="ai-subcard">
                <h3>🎯 标题优化（Gutenberg 集成）</h3>
                <p>
                    在文章编辑器右侧 AI Plus 面板中：<br>
                    · 选中标题内容 → 点击「🎯 优化标题」<br>
                    · AI 结合正文内容，生成符合 SEO 的最佳标题<br>
                    · 关键词靠前，搜索意图词增强<br>
                    · 直接替换原标题，一键完成
                </p>
            </div>
            <div class="ai-subcard">
                <h3>📈 统计面板</h3>
                <p>
                    · 全站文章总数<br>
                    · 已优化 / 待优化 文章数<br>
                    · 全站平均 SEO 得分<br>
                    · 支持分页查看和单篇操作<br>
                    · 一键诊断全部文章
                </p>
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

    <p class="ai-footer">Zuo AI Plus v<?php echo esc_html($version); ?> · Made with ❤️ by 左运来 · <a href="https://www.yily.top" target="_blank" style="color:#6366f1;">yily.top</a></p>
</div>
