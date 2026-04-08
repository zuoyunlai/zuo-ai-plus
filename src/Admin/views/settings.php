<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ai-plus-settings">
    <h1>⚡ Zuo AI Plus 设置</h1>
    <p style="color:#666;">集成智谱GLM、阿里通义、MiniMax、Kimi 等国内大模型，支持文章生成、SEO优化、图片生成、翻译等功能。</p>

    <hr>

    <form method="post" action="options.php">
        <?php settings_fields('ai_plus_settings'); ?>
        <?php do_settings_sections('ai_plus_settings'); ?>
        <?php submit_button('保存设置'); ?>
    </form>

    <hr>

    <h2>功能模块</h2>
    <table class="widefat" style="max-width:700px;">
        <thead>
            <tr>
                <th>功能</th>
                <th>说明</th>
                <th>状态</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>📝 文章生成</strong></td>
                <td>根据标题/提示生成完整文章、扩写、摘要</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
            <tr>
                <td><strong>🏷️ 关键词/摘要</strong></td>
                <td>自动提取关键词、生成文章摘要</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
            <tr>
                <td><strong>🖼️ 图片生成</strong></td>
                <td>生成文章特色图和内容插图（智谱/通义/MiniMax）</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
            <tr>
                <td><strong>🌐 翻译</strong></td>
                <td>多语言内容翻译发布</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
            <tr>
                <td><strong>💬 客服聊天</strong></td>
                <td>网站客服/对话窗口（短代码 [ai_plus_chat]）</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
            <tr>
                <td><strong>📊 SEO 优化</strong></td>
                <td>标题/描述/关键词建议、H标签结构分析</td>
                <td><span class="ai-plus-badge ai-plus-on">可用</span></td>
            </tr>
        </tbody>
    </table>

    <h2>模型 API Key 申请链接</h2>
    <ul style="line-height:2;">
        <li>🟢 <a href="https://open.bigmodel.cn/" target="_blank">智谱 GLM</a> — 注册即送额度</li>
        <li>🟠 <a href="https://dashscope.console.aliyun.com/" target="_blank">阿里通义千问</a> — 免费额度</li>
        <li>🔵 <a href="https://www.minimaxi.com/" target="_blank">MiniMax</a> — 注册送Token</li>
        <li>🟣 <a href="https://platform.moonshot.cn/" target="_blank">Kimi</a> — Moonshot</li>
    </ul>
</div>

<style>
.ai-plus-settings h1 {font-size:24px;}
.ai-plus-badge {padding:3px 10px;border-radius:12px;font-size:12px;font-weight:bold;}
.ai-plus-on {background:#d4edda;color:#155724;}
.ai-plus-off {background:#f8d7da;color:#721c24;}
</style>
