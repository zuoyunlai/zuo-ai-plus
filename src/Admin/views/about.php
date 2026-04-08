<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ai-plus-settings" style="max-width:900px;">
    <h1>⚡ Zuo AI Plus</h1>
    <p style="font-size:16px;color:#555;">为 WordPress 提供 AI 生成、聊天、图片等功能的内容助手插件。</p>

    <hr style="margin:24px 0;">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- 功能概览 -->
        <div>
            <h2 style="font-size:16px;margin-bottom:12px;">🎯 功能概览</h2>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🖊️ 文章生成</td>
                    <td style="padding:6px 0;color:#555;">输入标题 AI 自动生成完整文章</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">📝 内容扩写</td>
                    <td style="padding:6px 0;color:#555;">在现有内容上续写更多细节</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🏷️ SEO 优化</td>
                    <td style="padding:6px 0;color:#555;">生成标题、描述、关键词建议</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🏷️ 自动标签</td>
                    <td style="padding:6px 0;color:#555;">分析内容生成相关标签</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🖼️ 特色图生成</td>
                    <td style="padding:6px 0;color:#555;">根据文章内容生成封面图片</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🎨 图片生成</td>
                    <td style="padding:6px 0;color:#555;">Playground 测试文生图模型</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">💬 网站客服</td>
                    <td style="padding:6px 0;color:#555;">右下角悬浮 AI 助手浮窗</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🧱 Gutenberg 区块</td>
                    <td style="padding:6px 0;color:#555;">聊天区块 / 图片生成区块</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#2271b1;font-weight:600;">🔗 Playground</td>
                    <td style="padding:6px 0;color:#555;">对话测试 / 保存草稿 / 复制</td>
                </tr>
            </table>
        </div>

        <!-- 快速使用 -->
        <div>
            <h2 style="font-size:16px;margin-bottom:12px;">🚀 快速使用</h2>
            <div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:14px 16px;border-radius:4px;margin-bottom:16px;font-size:13px;line-height:1.8;">
                <strong>第一步：配置 API Key</strong><br>
                进入「文本模型」Tab，展开各平台卡片，填入对应的 API Key（其他字段通常留空）。<br><br>
                <strong>第二步：开启客服浮窗</strong><br>
                进入「💬 网站客服」区块，勾选开启，右下角即出现 AI 客服按钮。<br><br>
                <strong>第三步：写文章</strong><br>
                在 Gutenberg 编辑器中使用小助手面板，或插入「Zuo AI Plus 聊天」/「Zuo AI Plus 图片生成」区块。
            </div>

            <h2 style="font-size:16px;margin-bottom:12px;">📋 支持的模型</h2>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f9f9f9;">
                        <th style="text-align:left;padding:6px 8px;font-weight:600;">平台</th>
                        <th style="text-align:left;padding:6px 8px;font-weight:600;">文本模型</th>
                        <th style="text-align:left;padding:6px 8px;font-weight:600;">文生图</th>
                    </tr>
                </thead>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:5px 8px;">通义千问</td><td style="padding:5px 8px;">qwen-turbo</td><td style="padding:5px 8px;">qwen-image-2.0-pro ✅</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:5px 8px;">智谱 GLM</td><td style="padding:5px 8px;">glm-5</td><td style="padding:5px 8px;">cogview-3 ✅</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:5px 8px;">MiniMax</td><td style="padding:5px 8px;">MiniMax-M2.7</td><td style="padding:5px 8px;">image-01 ✅</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:5px 8px;">Kimi</td><td style="padding:5px 8px;">kimi-k2.5</td><td style="padding:5px 8px;">—</td></tr>
                <tr style="border-bottom:1px solid #eee;"><td style="padding:5px 8px;">DeepSeek</td><td style="padding:5px 8px;">deepseek-chat</td><td style="padding:5px 8px;">—</td></tr>
                <tr><td style="padding:5px 8px;">自定义 (代理)</td><td style="padding:5px 8px;">自填</td><td style="padding:5px 8px;">OpenAI 兼容 ✅</td></tr>
            </table>
        </div>

    </div>

    <hr style="margin:28px 0;">

    <!-- Gutenberg 区块说明 -->
    <h2 style="font-size:16px;margin-bottom:12px;">🧱 Gutenberg 区块使用</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
            <h3 style="margin:0 0 8px;font-size:14px;">🤖 Zuo AI Plus 聊天</h3>
            <p style="margin:0;font-size:13px;color:#555;line-height:1.7;">
                在文章中插入聊天窗口。文章发布后，读者可以在页面内实时对话，AI 会结合当前文章内容作答。
            </p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
            <h3 style="margin:0 0 8px;font-size:14px;">🖼️ Zuo AI Plus 图片生成</h3>
            <p style="margin:0;font-size:13px;color:#555;line-height:1.7;">
                插入后输入图片描述，生成后直接替换为图片。支持通义千问、智谱 GLM、MiniMax 三大平台。
            </p>
        </div>
    </div>

    <!-- 客服浮窗说明 -->
    <h2 style="font-size:16px;margin-bottom:12px;">💬 网站客服（前台浮窗）</h2>
    <div style="background:#f0f6fc;border-radius:6px;padding:16px;font-size:13px;line-height:1.9;color:#333;">
        开启后显示在博客前台页面右下角，点击展开对话窗口。<br>
        <strong>内容感知：</strong>AI 自动读取当前文章内容作为上下文，读者提问「这篇文章讲了什么」等问题时，AI 会基于文章内容作答。<br>
        <strong>模型选择：</strong>默认使用「特色图片模型」配置的平台，也可切换其他已配置的文本模型。
    </div>

    <!-- 短代码说明 -->
    <h2 style="font-size:16px;margin-bottom:12px;">📋 短代码（Shortcode）</h2>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px;font-size:13px;margin-bottom:20px;">
        <p style="margin-top:0;color:#333;line-height:1.8;">
            在任意文章或页面编辑器中插入以下短代码，即可在该位置显示 AI 对话窗口：
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f9f9f9;border-bottom:1px solid #eee;">
                    <th style="text-align:left;padding:8px 12px;font-weight:600;">短代码</th>
                    <th style="text-align:left;padding:8px 12px;font-weight:600;">说明</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:8px 12px;"><code style="background:#f0f6fc;padding:2px 8px;border-radius:3px;">[ai_plus_chat]</code></td>
                    <td style="padding:8px 12px;color:#444;">前台嵌入聊天窗口（自动使用默认模型，可切换已配置的所有平台）</td>
                </tr>
                <tr>
                    <td style="padding:8px 12px;"><code style="background:#f0f6fc;padding:2px 8px;border-radius:3px;">[ai_plus_chat model="kimi"]</code></td>
                    <td style="padding:8px 12px;color:#444;">指定默认使用的模型平台（可选值：<code>zhipu</code>、<code>tongyi</code>、<code>minimax</code>、<code>kimi</code>、<code>deepseek</code>、<code>custom</code>）</td>
                </tr>
            </tbody>
        </table>
        <p style="color:#888;font-size:12px;margin-bottom:0;">
            💡 提示：短代码下拉框只显示已配置 API Key 的平台，切换模型实时生效，无需刷新页面。
        </p>
    </div>

    <hr style="margin:28px 0;">
    <p style="color:#999;font-size:12px;">Zuo AI Plus v1.0 · Made with ❤️ · by Zuo AI Plus Team</p>
</div>
