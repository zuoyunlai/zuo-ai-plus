=== Zuo AI Plus ===
Contributors: zuoyunlai
Tags: ai, content generator, translation, seo, image generator
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: zuo-ai-plus
Domain Path: /languages

Integrate powerful AI models into WordPress. Generate articles, translate content, optimize SEO, and power an AI-powered chatbot.

== Description ==

Zuo AI Plus brings powerful AI capabilities directly to your WordPress dashboard.

= Supported Models =

* **Zhipu GLM** (glm-5, glm-4-flashx, cogview-3) — text + image generation
* **Alibaba Tongyi Qianwen** (qwen-turbo, qwen-plus, wanx2.1-image) — text + image generation
* **MiniMax** (MiniMax-M2.7, image-01) — text + image generation
* **Kimi / Moonshot** (kimi-k2.5, moonshot-v1-8k) — excellent for long-context tasks
* **DeepSeek** (deepseek-chat, deepseek-coder) — coding and reasoning
* **Custom OpenAI-compatible endpoint** — bring your own model

= Key Features =

* **AI Article Generation** — One-click full-length article generation from a title
* **Content Rewrite** — Rewrite/polish existing content while keeping original meaning
* **Writing Style/Tone** — Multiple styles: professional, casual, warm, academic, etc.
* **Content Expansion** — Continue writing existing drafts seamlessly
* **Smart Continue** — Auto-detect continue direction based on context
* **Title Optimization** — AI-generated SEO-friendly titles (30-60 chars)
* **Auto Summary** — One-click 100-word summary generation
* **Auto Tagging** — AI-powered tag extraction (3-5 SEO-friendly tags)
* **Slug Generation** — Auto-generate WordPress-friendly URL aliases
* **Knowledge Base** — Background knowledge for AI to reference your brand/product
* **Translation** — 12 languages, translate directly in Gutenberg editor
* **Featured Image Generation** — Generate contextual cover images via AI
* **Inline Image Generation** — Insert AI-generated images anywhere in articles
* **SEO Diagnosis** — Batch site-wide SEO scoring, analysis & AI optimization
* **Customer Service Widget** — Floating AI chat, content-aware responses
* **Gutenberg Blocks** — Interactive chat + image generation blocks
* **Shortcode** — Embed `[ai_plus_chat]` anywhere with model selection
* **Playground** — Chat interface for testing all models
* **Usage Statistics** — Track tokens, operations, model distribution
* **Quick Model Switch** — Switch AI models anywhere in admin

= Requirements =

* WordPress 6.0 or higher
* PHP 8.1 or higher
* API keys from your chosen model provider (all offer free tier or credits)

== Installation ==

1. Upload the `zuo-ai-plus` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **Zuo AI Plus → Text Models** and enter your API keys
4. Start using the Gutenberg sidebar or floating customer service widget

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Each model requires its own API key. All supported providers offer free tiers or credits. You only pay for what you use.

= Which model should I choose? =

glm-5 (Zhipu) and qwen-turbo (Tongyi) are recommended for beginners due to generous free quotas. Kimi and DeepSeek excel at long-context tasks.

= Does it work with page builders? =

Use the `[ai_plus_chat]` shortcode to embed chat widgets in Elementor, Classic editor, or any page builder.

= How does the customer service widget work? =

Enable it in Zuo AI Plus → Customer Service settings. It automatically reads your article content and answers visitor questions contextually.

== Screenshots ==

1. Gutenberg sidebar AI panel
2. API key settings
3. Customer service floating widget
4. Shortcode chat embed

== Changelog ==

= 1.2.3 =
* 新增内容改写功能：重写/润色现有内容，保持原意优化表达
* 新增风格语气选择：专业正式、轻松随性、温暖亲切等多种文风
* 新增知识库功能：设置背景知识，AI生成时自动参考品牌信息
* 新增插图生成功能：在文章任意位置插入AI生成的配图
* 新增智能续写：根据上下文自动判断续写方向
* 新增多模型快速切换：后台任意位置切换AI模型对比测试
* 新增使用统计面板：实时追踪Token消耗、操作次数、模型分布
* 移动端后台全面优化：所有页面适配手机/平板访问
* 修复后台统计功能：同时统计聊天对话和其他AI操作
* 更新关于页面：完整展示20项核心功能

= 1.2.0 =
* 全新导航系统：模型设置 / 文生图 / 文生文 / 统计 / SEO诊断 / 授权管理 / 关于
* SEO诊断功能：批量诊断得分、AI一键优化、满分文章智能跳过
* 新增各模型 API Key 测试连接按钮（实时验证 Key 有效性）
* 新增 AI 响应缓存：相同请求在 TTL 内取缓存，省 Token 费用，支持手动清除
* 修复SEO优化按钮点击无反应Bug（REST参数类型错误+按钮双监听器）
* 修复满分文章优化后未标记已优化Bug
* 修复Gutenberg侧边栏标签保存失败Bug
* 修复聊天历史/统计页面表名错误Bug
* 修复SeoOptimizer SQL注入风险
* 清除所有调试代码（console.log/var_dump/file_put_contents）
* 统一命名空间为 ZuoAIPlus
* 重写关于页面：新增SEO诊断详细介绍、标题优化说明、API申请地址
* 删除冗余文件，优化插件结构

= 1.1.18 =
* Fixed all unslash warnings in AjaxHandler.php and ai-plus.php
* Fixed ai_plus_the_content hook name
* Fixed Admin_Init.php $h closure and nonce unslash
* Added nonce verification to license save handler

= 1.1.17 =
* Fixed EscapeOutput ERROR in chat-history pagination
* Fixed UnnecessaryPrepare in stats.php and chat-history.php
* Fixed ABSPATH placement in AjaxHandler.php (after namespace)
* Shortened readme short description to ≤150 chars

= 1.1.16 =.EscapeOutput and DB PreparedSQL errors
* Added ABSPATH checks to all PHP files
* Fixed unlink() → wp_delete_file()
* Fixed date() → gmdate()
* Fixed readme.txt short description and tags

= 1.1.15 =
* Fixed WordPress plugin check errors and warnings
* Added proper escaping and sanitization
* Improved code quality for WordPress.org submission

= 1.1.14 =
* Fixed plugin URI and author URI validation
* Updated license header

= 1.1.13 =
* Renamed plugin to Zuo AI Plus
* Fixed navigation tab display issues
* Updated contact information

= 1.1.12 =
* Added license server URL configuration
* Prepared for WordPress.org submission

= 1.1.11 =
* Added usage statistics page
* Updated contact email

== Upgrade Notice ==

= 1.1.15 =
Upgrade for improved WordPress compatibility and code quality.

== Links ==

Support: 17854779@qq.com
Plugin Page: https://www.yily.top?from=wp-plugin
