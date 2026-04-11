<?php
/**
 * 模型工厂初始化
 */
namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class Model_Init
{
    private static array $instances = [];

    public function __construct()
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route('ai-plus/v1', '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handleChat'],
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route('ai-plus/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGenerate'],
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route('ai-plus/v1', '/image', [
            'methods' => 'POST',
            'callback' => [$this, 'handleImage'],
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route('ai-plus/v1', '/translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTranslate'],
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route('ai-plus/v1', '/seo', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSeo'],
            'permission_callback' => '__return_true',
        ]);

        // ── SEO 诊断优化 ──
        $seo = new \ZuoAIPlus\Models\SeoOptimizer();

        // 全站 SEO 扫描
        register_rest_route('ai-plus/v1', '/seo-audit', [
            'methods' => 'GET',
            'callback' => function($req) use ($seo) {
                return new \WP_REST_Response($seo->auditAll([
                    'posts_per_page' => (int) $req->get_param('per_page') ?: 100,
                    'paged'         => (int) $req->get_param('page') ?: 1,
                    'skip_done'     => $req->get_param('skip_done') !== 'false',
                ]), 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // SEO 优化统计
        register_rest_route('ai-plus/v1', '/seo-stats', [
            'methods' => 'GET',
            'callback' => function() use ($seo) {
                return new \WP_REST_Response($seo->getStats(), 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // 批量 AI 优化
        register_rest_route('ai-plus/v1', '/seo-optimize-batch', [
            'methods' => 'POST',
            'callback' => function($req) use ($seo) {
                $ids = $req->get_param('post_ids');
                if (empty($ids)) {
                    return new \WP_REST_Response(['error' => '缺少 post_ids 参数'], 400);
                }
                $ids = array_map('intval', (array) $ids);
                $model = $req->get_param('model') ?: '';
                $result = $seo->batchOptimize($ids, $model);
                return new \WP_REST_Response($result, 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // 重置单篇文章优化状态
        register_rest_route('ai-plus/v1', '/seo-reset/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => function($req) use ($seo) {
                $id = (int) $req->get_param('post_id');
                $seo->resetPost($id);
                return new \WP_REST_Response(['ok' => true, 'post_id' => $id], 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // ── 单篇文章 SEO 诊断 ──
        register_rest_route('ai-plus/v1', '/seo-audit-post/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => function($req) use ($seo) {
                $id = (int) $req->get_param('post_id');
                $post = get_post($id);
                if (!$post || $post->post_type !== 'post') {
                    return new \WP_REST_Response(['error' => '文章不存在'], 404);
                }
                $result = $seo->auditPost($post);
                return new \WP_REST_Response($result, 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // ── 单篇文章 AI 优化 ──
        register_rest_route('ai-plus/v1', '/seo-optimize-post/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => function($req) use ($seo) {
                $id = (int) $req->get_param('post_id');
                $model = $req->get_param('model') ?: '';
                $result = $seo->optimizePost($id, $model);
                
                if (is_wp_error($result)) {
                    return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
                }
                return new \WP_REST_Response($result, 200);
            },
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-plus/v1', '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'getModels'],
            'permission_callback' => '__return_true',
        ]);

        // 保存 license key（后台私有接口）
        \register_rest_route('ai-plus/v1', '/save-license-key', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSaveLicenseKey'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);

        // 别名生成（英文或拼音 slug）
        \register_rest_route('ai-plus/v1', '/slug', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSlug'],
            'permission_callback' => '__return_true',
        ]);

        // 摘要提取（直接写入摘要字段）
        \register_rest_route('ai-plus/v1', '/summarize', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSummarize'],
            'permission_callback' => '__return_true',
        ]);

        // 关键词提取（直接写入标签）
        \register_rest_route('ai-plus/v1', '/keywords', [
            'methods' => 'POST',
            'callback' => [$this, 'handleKeywords'],
            'permission_callback' => '__return_true',
        ]);

        // 特色图设置
        \register_rest_route('ai-plus/v1', '/featured-image', [
            'methods' => 'POST',
            'callback' => [$this, 'handleFeaturedImage'],
            'permission_callback' => '__return_true',
        ]);

        // 特色图下载+设置（REST API，替代 admin-ajax）

        // 标签创建

        // 标签保存

        // 标签创建（REST API，替代 admin-ajax）
        \register_rest_route('ai-plus/v1', '/tags-create', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTagsCreate'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // 标签保存（REST API，可接受ID数组或名称数组）
        \register_rest_route('ai-plus/v1', '/tags-save', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTagsSave'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // 特色图下载+设置（REST API）
        \register_rest_route('ai-plus/v1', '/featured-image-set', [
            'methods' => 'POST',
            'callback' => [$this, 'handleFeaturedImageSet'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // 保存草稿（含 markdown→HTML 转换）
        \register_rest_route('ai-plus/v1', '/save-draft', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSaveDraft'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    public static function getModel(string $name): ?BaseModel
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $apiKeys = \get_option('ai_plus_api_keys', []);
        $cfg = $apiKeys[$name] ?? [];

        // 兼容旧格式（字符串）和新格式（数组）
        if (is_array($cfg)) {
            $apiKey  = $cfg['api_key'] ?? '';
            $baseUrl = $cfg['base_url'] ?? '';
            $modelId = $cfg['model'] ?: '';
        } else {
            $apiKey  = $cfg;
            $baseUrl = '';
            $modelId = '';
        }

        if (empty($apiKey)) return null;

        $modelMap = [
            'zhipu'   => ZhipuModel::class,
            'tongyi'  => TongyiModel::class,
            'minimax' => MiniMaxModel::class,
            'kimi'    => KimiModel::class,
            'deepseek'=> DeepSeekModel::class,
            'custom'  => CustomModel::class,
        ];

        if (!isset($modelMap[$name])) return null;

        self::$instances[$name] = new $modelMap[$name]($apiKey, $modelId, $baseUrl);
        return self::$instances[$name];
    }

    public function handleChat(\WP_REST_Request $request): \WP_REST_Response
    {
        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $messages  = $request->get_param('messages');
        if (!is_array($messages) || empty($messages)) {
            return new \WP_REST_Response(['error' => '参数错误'], 400);
        }
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                return new \WP_REST_Response(['error' => '消息格式错误'], 400);
            }
        }
        $context   = \sanitize_textarea_field($request->get_param('context') ?: '');
        $sessionId = \sanitize_text_field($request->get_param('session_id') ?: '');

        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }

        // 注入知识库背景（公司/品牌信息）
        $chatMessages = $messages;
        $kb = trim(\get_option('ai_plus_knowledge_base', ''));
        if ($kb) {
            $kbMsg = [
                'role'    => 'system',
                'content' => '【品牌/公司背景知识】以下是你所属公司/品牌的官方信息，访客询问地址、电话、营业时间、联系方式等问题时，优先以这里的信息为准回答：\n' . $kb
            ];
            array_unshift($chatMessages, $kbMsg);
        }

        // 注入文章上下文作为系统消息
        if ($context) {
            $sysMsg = [
                'role'    => 'system',
                'content' => '【当前文章内容】以下是当前文章的全部内容，答题时请以它为依据：\n' . $context
            ];
            array_unshift($chatMessages, $sysMsg);
        }

        try {
            $result = $model->chat($chatMessages);

            // 保存对话记录（insert 不需要缓存）
            global $wpdb; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table = $wpdb->prefix . 'ai_plus_chat';
            $wpdb->insert($table, [
                'session_id' => $sessionId,
                'user_id' => \get_current_user_id(),
                'role' => 'assistant',
                'message' => \maybe_serialize($messages),
                'response' => is_array($result) ? self::extractContent($result) : ($result['content'] ?? $result),
                'model' => $modelName,
                'tokens' => $result['usage']['total_tokens'] ?? 0,
            ]);

            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function handleGenerate(\WP_REST_Request $request): \WP_REST_Response
    {
        $userModel = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $action = \sanitize_text_field($request->get_param('action'));
        $content = \sanitize_textarea_field($request->get_param('content') ?: '');
        $extraPrompt = \sanitize_textarea_field($request->get_param('extra_prompt') ?: '');

        // 特色图/图像生成（需 License 授权）
        if (in_array($action, ['featured_image', 'image'])) {
            if ($err = $this->checkLicense()) return $err;
            $imgApiKeys = \get_option('ai_plus_api_keys', []);
            // featured_image 固定用后台配置的特色图片模型（ Playground 可自行选择）
            $imgPlatform = $action === 'featured_image'
                ? (\get_option('ai_plus_image_model') ?: 'tongyi')
                : (\sanitize_text_field($request->get_param('model') ?: '') ?: (\get_option('ai_plus_image_model') ?: 'tongyi'));
            $imgModelId = $imgApiKeys[$imgPlatform]['image_model'] ?? '';
            $imgModel = self::getModel($imgPlatform);
            if (!$imgModel) {
                return new \WP_REST_Response(['error' => '该模型未配置或API Key无效，请检查后台设置'], 400);
            }
            $model = $imgModel;
            $modelName = $imgPlatform;
        } else {
            $model = self::getModel($userModel);
            $imgModel = null;
            $modelName = $userModel;
        }

        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }

        // 产品知识库
        $kb = trim(\get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【产品/品牌背景知识】\n" . $kb . "\n") : '';

        // 用户补充要求
        $extraBlock = $extraPrompt ? ("\n\n【用户补充要求】" . $extraPrompt . "\n") : '';

        // 根据动作拼接 prompt
        $prompt = match ($action) {
            'generate' => "你是一位专业编辑。请根据用户给出的文章标题撰写一篇结构完整、内容丰富的文章，要求有引言、正文、结论，不少于800字。{$kbBlock}{$extraBlock}\n文章标题：{$content}",
            'expand'   => "请仔细阅读用户提供的文章段落，在其后直接续写内容，增加更多细节、案例和论述。不要重复已有内容，不要写标题，直接在原文基础上续写2-3段。{$kbBlock}{$extraBlock}\n{$content}",
            'summarize'=> "请为以下内容生成100字以内的摘要，语言精炼：\n{$content}",
            'keyword'  => "请为以下文章提取3-5个SEO友好的关键词标签，用逗号分隔。\n规则：\n- 每个标签必须是2-6个中文字的短词（如：「AI绘图」「工业设计」「衣柜设计」）\n- 标签要简洁，是文章核心主题的单词或词组，不要完整句子\n- 不要用品牌名、公司名或超长词组\n- 只输出标签，用逗号分隔，不要编号、不要任何解释\n文章内容：\n{$content}",
            'category' => "判断以下内容最适合的WordPress分类名称，只返回分类名称：\n{$content}",
            'slug'     => "请根据标题生成一个简短的WordPress别名（slug），只输出英文或拼音slug，不要任何说明、不要引号、不要多余字符。标题：{$content}",
            'title_optimize' => "你是一位SEO标题专家。请根据用户给出的原始文章标题，生成一个完全不同的、SEO友好的最佳标题。

要求：
- 新标题必须与原文主题高度相关，不能偏离原意
- 字数控制在30-60字（汉字），在搜索引擎中展示完整且有吸引力
- 在标题前面加入搜索意图词（如：指南/教程/方案/推荐/评测/对比/怎么/如何/哪个/2024等）以提升点击率
- 核心关键词尽量靠前放置
- 语言自然流畅，不要生硬堆砌关键词
- 不要使用特殊符号（如【】、『』等），用 - 或 | 分隔即可
- 直接输出新标题，不要任何解释、不要引号

原始标题：
{$content}",
            'featured_image' => "根据以下文章内容（纯文本），提取核心主题、产品或场景，用英文生成一段精准的AI绘图提示词。

规则：
- 图片主体必须与文章核心内容高度相关
- 主体：是什么产品/场景，具体外观、材质、颜色
- 光照：自然光（natural lighting）或工作室柔光（soft studio light），冷暖适中
- 背景：浅灰或纯白（light gray / white background）
- 风格：写实摄影风格，ultra realistic, 8K resolution, shallow depth of field
- 构图：居中或三分之一构图（centered or rule of thirds）
- 只输出英文描述，25-60个单词，不要引号、编号或任何解释

文章内容：
{$content}",
            'image'    => "根据以下文章内容，生成一段详细的英文AI绘画提示词。要求：写实摄影风格，高清8K，包含主体描述、光照、背景、构图，20-60个英文单词，直接输出提示词不要解释：\n{$content}",
            default    => "请根据以下内容续写文章正文。不要写标题（不要写 # 或 ## 标题），不要重复已有内容，直接写正文段落，用 - 或 1. 列表组织信息：\n{$content}",
        };

        try {
            // 生成/扩写 需要更多 token（8192），摘要/关键词 2048 足够
            $maxTokens = $action === 'generate' ? 8192 : ($action === 'featured_image' ? 1024 : 2048);
            $result = $model->completion($prompt, ['max_tokens' => $maxTokens]);
            $text = trim($result['content'] ?? $result['text'] ?? (is_string($result) ? $result : ''));

            // 文章生成/扩写时：Markdown → Gutenberg HTML
            if (in_array($action, ['generate', 'expand']) && $text) {
                $text = \ZuoAIPlus\Utils\MarkdownConverter::convert($text);
            }

            // 特色图生成：使用专用图片模型生成真实图片
            // featured_image/image: 先用文本模型生成图片描述，再调用图片模型生成图片
            if (in_array($action, ['featured_image', 'image'])) {
                if (empty($text)) {
                    return new \WP_REST_Response([
                        'error' => '未生成图片描述，请检查文章内容或换一个模型试试',
                        'url'   => '',
                    ], 200);
                }
                if (!$imgModel) {
                    return new \WP_REST_Response([
                        'error' => '图片模型未配置，请在后台设置里配置特色图片模型',
                        'url'   => '',
                    ], 400);
                }
                try {
                    $imageOpts = $imgModelId ? ['model' => $imgModelId] : [];
                    $imageResult = $imgModel->image($text, $imageOpts);
                    if (!empty($imageResult['url'])) {
                        return new \WP_REST_Response([
                            'content'      => $text,
                            'image_prompt' => $text,
                            'url'          => $imageResult['url'],
                            'model'        => $imgModelId ?: $imgPlatform,
                        ], 200);
                    }
                } catch (\Throwable $imgErr) {
                    return new \WP_REST_Response([
                        'content'  => $text,
                        'image_prompt' => $text,
                        'url'      => '',
                        'error'    => '图片生成失败：' . $imgErr->getMessage(),
                        'model'    => $imgModelId ?: $imgPlatform,
                    ], 200);
                }
            }

            // generate/expand: 用 Markdown 转好的 HTML 覆盖 content 返回
            if (in_array($action, ['generate', 'expand']) && isset($text)) {
                $result['content'] = $text;
            }
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function handleImage(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkLicense()) return $err;

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $prompt = \sanitize_textarea_field($request->get_param('prompt'));

        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }

        try {
            $result = $model->image($prompt);
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function handleTranslate(\WP_REST_Request $request): \WP_REST_Response
    {

        $modelName  = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $content    = \sanitize_textarea_field($request->get_param('content'));
        $sourceLang = \sanitize_text_field($request->get_param('source_lang') ?: 'auto');
        $targetLang = \sanitize_text_field($request->get_param('target_lang') ?: 'zh');

        $langMap = [
            'auto' => ['name' => '自动检测', 'zhipu' => 'auto',  'tongyi' => '自动检测',  'minimax' => 'auto',  'kimi' => 'auto', 'deepseek' => 'auto', 'custom' => 'auto'],
            'en'   => ['name' => '英文',      'zhipu' => 'English','tongyi' => '英文',    'minimax' => 'English','kimi' => 'English','deepseek' => 'English','custom' => 'English'],
            'zh'   => ['name' => '中文',      'zhipu' => 'Chinese','tongyi' => '中文',    'minimax' => 'Chinese','kimi' => 'Chinese','deepseek' => 'Chinese','custom' => 'Chinese'],
            'zt'   => ['name' => '繁体中文',  'zhipu' => 'Chinese','tongyi' => '中文',    'minimax' => 'Chinese','kimi' => 'Chinese','deepseek' => 'Chinese','custom' => 'Chinese'],
            'ja'   => ['name' => '日文',      'zhipu' => 'Japanese','tongyi' => '日文',   'minimax' => 'Japanese','kimi' => 'Japanese','deepseek' => 'Japanese','custom' => 'Japanese'],
            'ko'   => ['name' => '韩文',      'zhipu' => 'Korean','tongyi' => '韩文',    'minimax' => 'Korean','kimi' => 'Korean','deepseek' => 'Korean','custom' => 'Korean'],
            'fr'   => ['name' => '法文',      'zhipu' => 'French','tongyi' => '法文',    'minimax' => 'French','kimi' => 'French','deepseek' => 'French','custom' => 'French'],
            'de'   => ['name' => '德文',      'zhipu' => 'German','tongyi' => '德文',    'minimax' => 'German','kimi' => 'German','deepseek' => 'German','custom' => 'German'],
            'es'   => ['name' => '西班牙文',  'zhipu' => 'Spanish','tongyi' => '西班牙文','minimax' => 'Spanish','kimi' => 'Spanish','deepseek' => 'Spanish','custom' => 'Spanish'],
            'pt'   => ['name' => '葡萄牙文',  'zhipu' => 'Portuguese','tongyi' => '葡萄牙文','minimax' => 'Portuguese','kimi' => 'Portuguese','deepseek' => 'Portuguese','custom' => 'Portuguese'],
            'ru'   => ['name' => '俄文',      'zhipu' => 'Russian','tongyi' => '俄文',   'minimax' => 'Russian','kimi' => 'Russian','deepseek' => 'Russian','custom' => 'Russian'],
            'ar'   => ['name' => '阿拉伯文',  'zhipu' => 'Arabic','tongyi' => '阿拉伯文','minimax' => 'Arabic','kimi' => 'Arabic','deepseek' => 'Arabic','custom' => 'Arabic'],
            'it'   => ['name' => '意大利文',  'zhipu' => 'Italian','tongyi' => '意大利文','minimax' => 'Italian','kimi' => 'Italian','deepseek' => 'Italian','custom' => 'Italian'],
            'th'   => ['name' => '泰文',      'zhipu' => 'Thai','tongyi' => '泰文',       'minimax' => 'Thai','kimi' => 'Thai','deepseek' => 'Thai','custom' => 'Thai'],
            'vi'   => ['name' => '越南文',    'zhipu' => 'Vietnamese','tongyi' => '越南文','minimax' => 'Vietnamese','kimi' => 'Vietnamese','deepseek' => 'Vietnamese','custom' => 'Vietnamese'],
        ];

        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }

        try {
            $srcInfo = $langMap[$sourceLang] ?? $langMap['auto'];
            $tgtInfo = $langMap[$targetLang] ?? $langMap['zh'];
            $srcName = $srcInfo['name'];
            $tgtName = $tgtInfo['name'];
            $srcCode = $srcInfo[$modelName] ?? $sourceLang;
            $tgtCode = $tgtInfo[$modelName] ?? $targetLang;

            $sys   = '你是一位专业翻译专家，只返回翻译结果，不添加任何解释、评论或额外内容。保持原文格式（Markdown/HTML）。';
            $user  = $sourceLang === 'auto'
                ? "请将以下内容翻译成{$tgtName}，只返回翻译结果：\n{$content}"
                : "请将以下{$srcName}内容翻译成{$tgtName}，只返回翻译结果：\n{$content}";

            $msgs   = [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
            $result = $model->chat($msgs);

            $text = is_array($result) ? self::extractContent($result) : $result;
            return new \WP_REST_Response(['content' => $text], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }


    public function handleSeo(\WP_REST_Request $request): \WP_REST_Response
    {

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $content = \sanitize_textarea_field($request->get_param('content'));
        $title = \sanitize_text_field($request->get_param('title') ?: '');

        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }

        try {
            $prompt = "请对以下文章内容进行SEO分析和优化建议，输出JSON格式：\n" .
                "标题：{$title}\n内容：{$content}\n" .
                "请返回：{title建议:'', meta_description建议:'', 关键词建议:'', H标签结构建议:'', 内链建议:'', 内容改进建议:''}";
            $result = $model->completion($prompt);
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // 生成文章别名（英文或拼音 slug）
    public function handleSlug(\WP_REST_Request $request): \WP_REST_Response
    {

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $title = \sanitize_text_field($request->get_param('title') ?: '');
        $content = \sanitize_textarea_field($request->get_param('content') ?: '');

        if (empty($title) && empty($content)) {
            return new \WP_REST_Response(['error' => '标题或内容不能为空'], 400);
        }

        $kb = trim(\get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        $text = $title ?: mb_substr($content, 0, 100);
        $prompt = "请根据以下文章标题或内容生成一个适合WordPress URL的英文或拼音别名（slug），只输出slug本身，不要任何说明，不要空格，不要特殊字符。\n{$kbBlock}\n{$text}";

        try {
            // 优先用用户选择的模型
            $model = self::getModel($modelName);
            $result = $model ? $model->completion($prompt, ['max_tokens' => 1500, 'temperature' => 0.3]) : null;
            $slug = self::extractContent($result);

            // 如果内容为空（推理模型场景），换用 glm-4-flash 直接获取结果
            if (empty(trim($slug))) {
                $apiKeys = \get_option('ai_plus_api_keys', []);
                $zhipuKey = $apiKeys['zhipu'] ?? '';
                if ($zhipuKey) {
                    $fallback = new ZhipuModel($zhipuKey, 'glm-4-flash');
                    $result2 = $fallback->completion($prompt, ['max_tokens' => 64, 'temperature' => 0.3]);
                    $slug = $result2['content'] ?? '';
                }
            }

            // 清理：只保留字母数字和横线，转小写
            $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $slug);
            $slug = strtolower(trim($slug, '-'));
            return new \WP_REST_Response(['slug' => $slug ?: ''], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }


    public function handleSaveDraft(\WP_REST_Request $request)
    {

        $content = \sanitize_textarea_field($request->get_param('content') ?: '');
        $title   = \sanitize_text_field($request->get_param('title') ?: ('AI 生成-' . gmdate('Y-m-d H:i')));

        if (empty($content)) {
            return new \WP_REST_Response(['error' => '内容不能为空'], 400);
        }

        // Markdown → HTML
        $html = \ZuoAIPlus\Utils\MarkdownConverter::convert($content);

        $post_id = \wp_insert_post([
            'post_title'   => $title,
            'post_content' => $html,
            'post_status'  => 'draft',
            'post_author'  => \get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 500);
        }

        return new \WP_REST_Response([
            'id'      => $post_id,
            'edit_url'=> \admin_url('post.php?post=' . $post_id . '&action=edit'),
        ], 200);
    }


    /**
     * 统一提取 AI 响应内容
     */
    public static function extractContent(array $result): string
    {
        // OpenAI compatible: choices[0].message.content
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        // 直接 content 字段
        if (!empty($result['content'])) {
            return $result['content'];
        }
        // OpenAI text 字段
        if (!empty($result['text'])) {
            return $result['text'];
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    // 保存 license key（REST）
    public function handleSaveLicenseKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $key = \sanitize_text_field($request->get_param('license_key') ?: '');
        \update_option('ai_plus_license_key', $key, true);
        \delete_transient('ai_plus_license_status');
        return new \WP_REST_Response(['saved' => true, 'key' => $key], 200);
    }

    
    /**
     * License 校验（有 Key 但无效 → 返回 403 Response，通过 → 返回 null）
     */
    protected function checkLicense(): ?\WP_REST_Response
    {
        $key = get_option('ai_plus_license_key', '');
        if (empty($key)) {
            return new \WP_REST_Response([
                'error' => '图片功能需要激活正版授权，请在「授权管理」页面输入 License Key'
            ], 403);
        }

        // 有 Key 则实时校验（不发网络请求，直接读 API 返回）
        $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $resp = wp_remote_get(
            get_option('ai_plus_license_server_url', 'https://www.yily.top/licenses/api.php') . '?action=verify&key=' . urlencode($key) . '&domain=' . urlencode($domain),
            ['timeout' => 8, 'sslverify' => true]
        );

        if (is_wp_error($resp)) {
            // 网络异常：降级处理，不阻断（宽容模式）
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $st = $body['status'] ?? 'unknown';

        if ($st !== 'valid') {
            $msg = $st === 'expired' ? 'License 已过期，请联系续费：17854779@qq.com'
                : ($st === 'domain_mismatch' ? 'License 域名不匹配，请在该域名下激活'
                : 'License 无效或未激活，请联系：17854779@qq.com');
            return new \WP_REST_Response(['error' => $msg], 403);
        }

        return null;
    }


public function getModels(): array
    {
        return [
            ['id' => 'zhipu', 'name' => '智谱 GLM'],
            ['id' => 'tongyi', 'name' => '阿里通义千问'],
            ['id' => 'minimax', 'name' => 'MiniMax'],
            ['id' => 'kimi', 'name' => 'Kimi'],
        ];
    }

    // 摘要提取 → 直接写入摘要字段
    public function handleSummarize(\WP_REST_Request $request): \WP_REST_Response
    {

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $content = \sanitize_textarea_field($request->get_param('content') ?: '');
        if (empty($content)) {
            return new \WP_REST_Response(['error' => '文章内容不能为空'], 400);
        }
        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }
        $kb = trim(\get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        $prompt = "请为以下文章内容生成一段50字以内的摘要，语言精炼准确，直接输出摘要不要其他说明。{$kbBlock}\n" . $content;
        try {
            $result = $model->completion($prompt, ['max_tokens' => 1500, 'temperature' => 0.5]);
            // ZhipuModel::completion returns ['content' => ..., 'usage' => ..., 'raw' => ...]
            $text = self::extractContent($result);
            $text = is_string($text) ? trim($text) : '';
            return new \WP_REST_Response(['excerpt' => $text], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // 关键词提取 → 返回标签数组
    public function handleKeywords(\WP_REST_Request $request): \WP_REST_Response
    {

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $content = \sanitize_textarea_field($request->get_param('content') ?: '');
        if (empty($content)) {
            return new \WP_REST_Response(['error' => '文章内容不能为空'], 400);
        }
        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }
        $kb = trim(\get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        $prompt = "请为以下文章提取3-5个SEO友好的关键词标签，用逗号分隔。\n规则：\n- 每个标签必须是2-6个中文字的短词（如：「AI绘图」「工业设计」「衣柜设计」）\n- 标签要简洁，是文章核心主题的单词或词组，不要完整句子\n- 不要用品牌名、公司名或超长词组\n- 只输出标签，用逗号分隔，不要编号、不要任何解释{$kbBlock}\n文章内容：\n" . $content;
        try {
            $result = $model->completion($prompt, ['max_tokens' => 800, 'temperature' => 0.3]);
            $text = self::extractContent($result);
            // 解析逗号分隔的标签，并过滤超长标签
            $tags = array_filter(
                array_map('trim', preg_split('/[,，]/', trim($text, "，。、\n"))),
                fn($tag) => mb_strlen($tag, 'utf-8') >= 2 && mb_strlen($tag, 'utf-8') <= 6
            );
            return new \WP_REST_Response(['tags' => array_slice(array_values($tags), 0, 5)], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // 特色图 → 下载图片并设置为特色图
    public function handleFeaturedImage(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkLicense()) return $err;

        $modelName = \sanitize_text_field($request->get_param('model') ?: \get_option('ai_plus_default_model'));
        $prompt = \sanitize_textarea_field($request->get_param('prompt') ?: '');
        if (empty($prompt)) {
            return new \WP_REST_Response(['error' => '图片描述不能为空'], 400);
        }
        $model = self::getModel($modelName);
        if (!$model) {
            return new \WP_REST_Response(['error' => '未配置该模型或API Key'], 400);
        }
        try {
            $result = $model->image($prompt);
            $url = $result['url'] ?? '';
            if (empty($url)) {
                return new \WP_REST_Response(['error' => '图片生成失败，该模型可能不支持图片生成'], 400);
            }
            return new \WP_REST_Response(['url' => $url, 'revised_prompt' => $result['revised_prompt'] ?? ''], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // 创建标签（REST）
    public function handleTagsCreate(\WP_REST_Request $request): \WP_REST_Response
    {

        $name = \sanitize_text_field($request->get_param('name') ?: '');
        if (empty($name)) { return new \WP_REST_Response(['error' => '标签名称为空'], 400); }
        $result = \wp_insert_term($name, 'post_tag');
        if (\is_wp_error($result)) { return new \WP_REST_Response(['error' => $result->get_error_message()], 400); }
        return new \WP_REST_Response(['term_id' => $result['term_id'], 'name' => $name], 200);
    }

    // 保存标签到文章（REST）
    public function handleTagsSave(\WP_REST_Request $request): \WP_REST_Response
    {

        $post_id = intval($request->get_param('post_id') ?: 0);
        if (!$post_id) { return new \WP_REST_Response(['error' => '无法获取文章ID'], 400); }

        // 支持 tag_ids (ID数组)、tag_names (名称数组)，或逗号分隔的字符串
        $tag_ids = $request->get_param('tag_ids');
        $tag_names = $request->get_param('tag_names');
        $tags_raw = $request->get_param('tags');  // 兼容前端传的单字符串（逗号分隔）

        // 如果收到逗号分隔的字符串，先拆分
        if (empty($tag_names) && !empty($tags_raw)) {
            if (is_string($tags_raw)) {
                $tag_names = array_filter(array_map('trim', explode(',', $tags_raw)));
            } elseif (is_array($tags_raw)) {
                $tag_names = $tags_raw;
            }
        }

        $term_ids = [];

        // 处理名称数组：查找或创建
        if (!empty($tag_names) && is_array($tag_names)) {
            foreach ($tag_names as $name) {
                $name = \sanitize_text_field(trim($name));
                if (empty($name)) continue;
                $term = \get_term_by('name', $name, 'post_tag');
                if ($term) {
                    $term_ids[] = intval($term->term_id);
                } else {
                    $new = \wp_insert_term($name, 'post_tag');
                    if (!\is_wp_error($new)) {
                        $term_ids[] = intval($new['term_id']);
                    }
                }
            }
        }

        // 处理ID数组
        if (!empty($tag_ids) && is_array($tag_ids)) {
            $term_ids = array_merge($term_ids, array_map('intval', $tag_ids));
        }

        $term_ids = array_unique(array_filter($term_ids));
        \wp_set_object_terms($post_id, $term_ids, 'post_tag');
        return new \WP_REST_Response(['success' => true, 'tag_ids' => $term_ids, 'tag_names' => $tag_names], 200);
    }

    // 特色图下载+设置（REST）
    public function handleFeaturedImageSet(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkLicense()) return $err;
        $image_url = \esc_url_raw($request->get_param('image_url') ?: '');
        $post_id = intval($request->get_param('post_id') ?: 0);
        if (empty($image_url)) { return new \WP_REST_Response(['error' => '图片URL不能为空'], 400); }
        if (!$post_id) { return new \WP_REST_Response(['error' => '无法获取文章ID'], 400); }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $tmp_file = \sys_get_temp_dir() . '/feat_' . uniqid() . '.jpg';
        $response = \wp_remote_request($image_url, [
            'method' => 'GET',
            'timeout' => 60,
            'stream' => true,
            'filename' => $tmp_file,
        ]);
        if (\is_wp_error($response)) {
            return new \WP_REST_Response(['error' => '下载图片失败: ' . $response->get_error_message()], 400);
        }
        $code = \wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new \WP_REST_Response(['error' => '下载图片失败: HTTP ' . $code], 400);
        }
        $tmp = $tmp_file;
        $att_id = \media_handle_sideload(['name' => 'featured-' . $post_id . '-' . time() . '.jpg', 'tmp_name' => $tmp], $post_id);
        if (\is_wp_error($att_id)) { \wp_delete_file($tmp); return new \WP_REST_Response(['error' => '保存图片失败: ' . $att_id->get_error_message()], 400); }
        \set_post_thumbnail($post_id, $att_id);
        return new \WP_REST_Response(['success' => true, 'attachment_id' => $att_id, 'url' => \wp_get_attachment_url($att_id)], 200);
    }
}
