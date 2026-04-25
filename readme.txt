=== Zuo AI Plus ===
Contributors: zuoyunlai
Tags: ai, content generator, translation, seo, image generator, navigation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.5.32
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: zuo-ai-plus
Domain Path: /languages

Integrate powerful AI models into WordPress. Generate articles, translate content, optimize SEO, power an AI chatbot, and manage a smart navigation directory.

== Description ==

Zuo AI Plus brings powerful AI capabilities directly to your WordPress dashboard, with an integrated AI-powered navigation module.

= Supported Models =

* **Zhipu GLM** (glm-5, glm-5-turbo, glm-4.7-flashx, glm-4-flash, cogview-3) — text + image generation
* **Alibaba Tongyi Qianwen** (qwen3.6-plus, wanx2.1-image) — text + image generation
* **MiniMax** (MiniMax-M2.7, MiniMax-M2.7-highspeed) — text + image generation
* **Kimi / Moonshot** (kimi-k2.5, moonshot-v1-8k) — long-context tasks
* **DeepSeek** (deepseek-v3.2, deepseek-coder, deepseek-chat) — coding and reasoning
* **DashScope** (glm-5.1) — via Alibaba Cloud platform
* **Custom OpenAI-compatible endpoint** — bring your own model

= Key Features =

**Content Creation:**
* **AI Article Generation** — One-click full-length article generation from a title
* **Content Rewrite** — Rewrite/polish existing content while keeping original meaning
* **Writing Style/Tone** — Professional, casual, warm, academic styles
* **Content Expansion** — Continue writing existing drafts seamlessly
* **Smart Continue** — Auto-detect continue direction based on context
* **Title Optimization** — AI-generated SEO-friendly titles (30-60 chars)
* **Auto Summary** — One-click 100-word summary generation
* **Auto Tagging** — AI-powered tag extraction (3-5 SEO-friendly tags)
* **Slug Generation** — Auto-generate WordPress-friendly URL aliases
* **Knowledge Base** — Background knowledge for AI to reference your brand/product
* **Translation** — 12 languages, translate directly in Gutenberg editor

**AI Navigation Module:**
* **AI Full Fetch** — Enter a URL, auto-fetch name/keywords/description/logo/screenshot
* **Auto Screenshot → Featured Image** — Chrome Headless captures screenshot, registers as WordPress attachment, auto-sets as featured image with AI-generated alt/caption/description + smart Chinese metadata fallback
* **AI Generated Summary** — 300-500 word detailed site introduction
* **AI Tag Extraction** — Extract nav_tag taxonomy tags, auto-write to WordPress native tag box
* **SEO Weight Capsules** — Display search engine rankings (Baidu/360/Sogou/Toutiao/Mobile) as colored capsules
* **Category Tree** — Hierarchical nav_category with expand/collapse sidebar
* **Quick Links** — All/Favorites/Recent quick access shortcuts
* **Favorites & History** — LocalStorage-based personalization
* **Share & QR Code** — Mobile-friendly sharing modal with QR code
* **Rating System** — Star ratings per site
* **Dark Mode** — Full dark mode support with CSS variables, auto-syncs with theme toggle
* **Responsive Design** — 480px/768px/900px breakpoints, mobile-first card grid
* **Breadcrumb Navigation** — With Schema.org BreadcrumbList structured data
* **Schema.org Markup** — WebSite + Organization JSON-LD for SEO

**Other Features:**
* **Featured Image Generation** — Generate contextual cover images via AI
* **Inline Image Generation** — Insert AI-generated images anywhere in articles
* **SEO Diagnosis** — Batch site-wide SEO scoring, analysis & AI optimization
* **Customer Service Widget** — Floating AI chat, content-aware responses
* **Gutenberg Blocks** — Interactive chat + image generation blocks
* **Shortcode** — Embed `[ai_plus_chat]` anywhere with model selection
* **Playground** — Chat interface for testing all models
* **Usage Statistics** — Track tokens, operations, model distribution
* **Quick Model Switch** — Switch AI models anywhere in admin

= Screenshot Strategy (3-Tier) =

1. **og:image** — Target site's own preview image (fastest, zero cost)
2. **Chrome Headless** — Local screenshot saved to `uploads/nav-screenshots/` (7-day cache), registered as WordPress media attachment
3. **thum.io** — Online fallback screenshot service

= Requirements =

* WordPress 6.0 or higher
* PHP 8.1 or higher
* API keys from your chosen model provider (all offer free tier or credits)
* Chrome/Chromium for local screenshot capture (optional, falls back to thum.io)

== Installation ==

1. Upload the `zuo-ai-plus` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **Zuo AI Plus → Text Models** and enter your API keys
4. Start using the Gutenberg sidebar or navigation module

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Each model requires its own API key. All supported providers offer free tiers or credits.

= How does the navigation module work? =

Create a new nav_site post, enter a URL, click "AI Full Fetch" — the plugin automatically fetches the site's name, keywords, description, logo, takes a screenshot (via Chrome Headless or thum.io fallback), sets it as the featured image, and generates AI-powered alt text, title, and description for the image.

= What about screenshots? =

The plugin uses a 3-tier screenshot strategy: 1) og:image from the target site, 2) local Chrome Headless screenshot saved to uploads/nav-screenshots/ (7-day cache), 3) thum.io fallback. Screenshots are registered as WordPress media attachments with AI-generated metadata.

== Changelog ==

= 1.5.32 =
* Fix: SEO batch endpoint exposed without auth (`__return_true` → `canEdit`)
* Fix: Markdown link syntax leaking into SEO titles (`[text](url)` now stripped)
* Enhancement: Loader classmap — replaced glob scans with static file list for zero IO overhead
* Enhancement: ContentController refactored — title/summarize/keyword post-processing extracted into dedicated private methods
* Enhancement: Model layer deduplicated — BaseModel default `image()` and inherited `completion()` removed from DeepSeek/Custom/MiniMax/Tongyi

= 1.5.20 =
* Fix: Admin model settings page — all models now pass connection test with correct default IDs and token limits
* Fix: MiniMax default model ID corrected to MiniMax-M2.7 (was MiniMax-Text-01, an image model)
* Fix: Empty modelName fallback protection added for MiniMax, Kimi, DeepSeek, and Custom models
* Fix: AJAX test connection max_tokens increased from 20 to 500 to prevent truncation during thinking-tag responses
* Fix: PHP 8.5 UTF-8 multibyte trimming bug in tag extraction — removed destructive trim/preg_replace before mb_split
* Fix: Sidebar tag extraction now correctly renders in Gutenberg panel with 2-6 character filter
* Fix: Classic theme default block styles dequeued to prevent ugly black buttons in frontend
* Fix: Gutenberg block frontend styling unified (buttons, quotes, pullquotes, separators, embeds, cover, columns, groups)
* Fix: Dark mode support added for all Gutenberg blocks with consistent glass-morphism button style
* Enhancement: Image generation quality boosted for Tongyi Wanxiang, Zhipu CogView, and MiniMax models
* Enhancement: Added quality tags (masterpiece, best quality, 8k) and prompt_optimizer for image generation

= 1.5.19 =
* Fix: Featured image Chinese metadata smart fallback — when AI doesn't return Chinese metadata, automatically generates from post title + content
* Fix: Frontend double-wrapper property access bug in featured-image-set JavaScript

= 1.5.13 =
* New: Navigation module — AI-powered website directory with full fetch, summary, tags
* New: Auto screenshot → featured image with AI-generated alt/caption/description
* New: SEO weight capsules (Baidu/360/Sogou/Toutiao/Mobile rankings)
* New: Schema.org BreadcrumbList + WebSite JSON-LD structured data
* New: Full dark mode support with CSS variables
* New: Responsive design (480/768/900px breakpoints)
* Fix: PHP exec() namespace prefix (\exec) for screenshot capture
* Fix: Remove PHP disable_functions restriction for exec/shell_exec
* Fix: Tag display — removed # prefix, fixed PHP code leakage
* Fix: SEO capsule spacing — added missing seo-weight-grid CSS class
* Fix: Dark mode — 20+ hardcoded colors converted to CSS variables
* Fix: Mobile quick links — adaptive layout instead of forced 3-column
* Fix: Card footer — flex-wrap enabled for tag overflow
* Fix: Related sites — mobile 2-column grid

= 1.3.1 =
* Initial public release
* AI content generation, translation, SEO optimization
* Customer service widget
* Model playground
