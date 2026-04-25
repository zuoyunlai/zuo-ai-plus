<?php
/**
 * 内容生成控制器 - 处理文章生成、扩写、图片生成等
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class ContentController extends BaseController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-plus/v1', '/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleGenerate'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        register_rest_route('ai-plus/v1', '/image', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleImage'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        register_rest_route('ai-plus/v1', '/save-draft', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveDraft'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);
    }

    public function handleGenerate(\WP_REST_Request $request): \WP_REST_Response
    {
        // 速率限制检查
        $action = sanitize_text_field($request->get_param('action'));
        $rateLimitConfig = [
            'generate'       => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_GENERATE_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_GENERATE_WINDOW],
            'expand'         => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_EXPAND_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_EXPAND_WINDOW],
            'rewrite'        => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_REWRITE_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_REWRITE_WINDOW],
            'summarize'      => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SUMMARIZE_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SUMMARIZE_WINDOW],
            'keyword'        => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_KEYWORD_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_KEYWORD_WINDOW],
            'slug'           => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SLUG_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_SLUG_WINDOW],
            'featured_image' => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_IMAGE_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_IMAGE_WINDOW],
            'image'          => ['max' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_IMAGE_REQUESTS, 'window' => \ZuoAIPlus\Utils\Constants::RATE_LIMIT_IMAGE_WINDOW],
        ];

        if (isset($rateLimitConfig[$action])) {
            $config = $rateLimitConfig[$action];
            if ($err = $this->checkRateLimit($action, $config['max'], $config['window'])) {
                return $err;
            }
        }

        $userModel   = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content     = sanitize_textarea_field($request->get_param('content') ?: '');
        $extraPrompt = sanitize_textarea_field($request->get_param('extra_prompt') ?: '');
        $postId      = $request->get_param('post_id') ?: null; // 获取文章ID

        if (in_array($action, ['featured_image', 'image'])) {
            if ($err = $this->checkLicense()) {
                return $err;
            }
        }

        $modelInfo = $this->resolveModel($action, $request, $userModel);
        $model     = $modelInfo['model'];
        $imgModel  = $modelInfo['img_model'] ?? null;
        $modelName = $modelInfo['name'];

        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        $prompt    = $this->buildPrompt($action, $content, $extraPrompt);
        $maxTokens = $this->getMaxTokens($action);

        try {
            // 传递文章ID、内容哈希和显式 TTL(不依赖 prompt 文本推断)
            $cacheOpts = [
                'post_id'   => $postId,
                // 显式指定缓存 TTL,避免 BaseModel::getCacheTtl() 从 prompt 文本误判操作类型
                'cache_ttl' => $this->getCacheTtlForAction($action),
            ];
            if ($postId) {
                $contentHash = md5($content);
                $cacheOpts['content_hash'] = $contentHash;
            }

            $result = $model->completion($prompt, array_merge(['max_tokens' => $maxTokens], $cacheOpts));
            $text   = trim($result['content'] ?? $result['text'] ?? (is_string($result) ? $result : ''));

            // Markdown → HTML 转换
            if (in_array($action, ['generate', 'expand', 'rewrite']) && $text) {
                $text = \ZuoAIPlus\Utils\MarkdownConverter::convert($text);
            }

            // 特色图处理
            if (in_array($action, ['featured_image', 'image'])) {
                return $this->handleImageGeneration($text, $imgModel, $modelName, $modelInfo['img_model_id'] ?? '');
            }

            // 关键词/摘要/slug 在模型层已处理,AI 返回的 text 即最终结果
            if (in_array($action, ['generate', 'expand', 'rewrite'])) {
                $result['content'] = $text;
            }

            // 统一后验处理:title_optimize / summarize / keyword
            $postResult = $this->applyPostProcessing($action, $text, $result);
            if ($postResult instanceof \WP_REST_Response) {
                return $postResult;
            }
            $result = $postResult;
            // 记录操作历史
            $this->logHistory($action, $userModel, $result);

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // ── 后验处理方法 ──────────────────────────────────────────────────────

    /**
     * 根据 action 分派到对应的后验处理方法
     * @return array|\WP_REST_Response 处理后的 $result，或 error response
     */
    private function applyPostProcessing(string $action, string $text, array $result)
    {
        return match ($action) {
            'title_optimize' => $this->postProcessTitle($text, $result),
            'summarize'      => $this->postProcessSummarize($text, $result),
            'keyword'        => $this->postProcessKeyword($text, $result),
            default          => $result,
        };
    }

    /**
     * 标题后验: 确保返回干净的中文标题，拒绝思考内容
     */
    private function postProcessTitle(string $text, array $result)
    {
        $text = trim($text);
        // 预检: 拒绝含思考/prompt 内容
        if (preg_match('/then the|wait:|output format says|we should not|just output|should not include/i', $text)) {
            return $this->error(__('标题生成失败(模型输出了内部思考内容),请重试或切换模型', 'zuo-ai-plus'), 422);
        }
        // 必须含中文
        if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $text)) {
            return $this->error(__('标题生成失败(未检测到中文),请重试', 'zuo-ai-plus'), 422);
        }
        // 清理 Markdown 链接 [text](url) → text
        $text = preg_replace('/\[(.*?)\]\(.*?\)/u', '$1', $text);
        $text = preg_replace('/https?:\/\/[^\s]+/u', '', $text);
        $text = trim($text);
        // 清理残余格式词(如"新标题:"前缀)
        $text = preg_replace('/^(?:新)?标题[:：]\s*/u', '', $text);
        $text = trim($text);
        // 清理"(X字)"等字数提示
        $text = preg_replace('/[((][^)]*?[字个篇条][))]/u', '', $text);
        $text = trim($text);
        // 最终检查: 长度 6-30 字
        $len = mb_strlen($text, 'utf-8');
        if ($len < 6 || $len > 30) {
            /* translators: %d: title length */
            return $this->error(sprintf(__("标题长度不符(%d字),请重试", "zuo-ai-plus"), $len), 422);
        }
        $result['content'] = $text;
        return $result;
    }

    /**
     * 摘要后验: 过滤推理过程文字，只保留纯中文摘要
     */
    private function postProcessSummarize(string $text, array $result)
    {
        $text = trim($text);
        // 检测是否为推理过程文字
        $hasReasoning = preg_match('/[a-zA-Z]{10,}/', $text)
            && (preg_match('/We (have|need|can|must|should|will)|Let\'s (count|try|produce)|Thus|Therfore|The user wants|The summary should|I need to|I should|Simplify|Shorter|above \d+|below \d+|within \d+-\d+/i', $text)
                || preg_match('/["\']{3,}/', $text));

        if ($hasReasoning) {
            // 尝试提取纯中文摘要段
            $paragraphs = preg_split('/\n\n+/', $text);
            $chinesePara = '';
            foreach (array_reverse($paragraphs) as $p) {
                $p = trim($p);
                if (preg_match('/[\x{4e00}-\x{9fa5}]{10,}/u', $p) && mb_strlen($p, 'utf-8') < 300) {
                    $chinesePara = $p;
                    break;
                }
            }
            $text = $chinesePara ?: '';
        }
        // 清理前缀标记
        $text = preg_replace('/^(?:摘要[:：]?\s*|Summary[:：]?\s*)/ui', '', $text);
        $text = trim($text);
        $result['content'] = $text;
        return $result;
    }

    /**
     * 标签后验: 过滤无意义标签，只保留完整独立词汇
     */
    private function postProcessKeyword(string $text, array $result)
    {
        $tags_raw = array_map('trim', preg_split('/[,,、\n]+/u', trim($text)));
        $valid = [];
        foreach ($tags_raw as $tag) {
            // 移除所有标点、空格
            $tag = preg_replace('/[[:punct:]]/u', '', $tag);
            $tag = preg_replace('/\s+/', '', $tag);
            $tag = trim($tag);
            $len = mb_strlen($tag, 'utf-8');
            if ($len < 3) continue;
            if ($len > 16) continue;
            if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $tag)) continue;
            if (preg_match('/^[a-zA-Z0-9\s]+$/', $tag)) continue;
            // 排除含英文+数字混合的乱码标签
            if (preg_match('/[a-zA-Z]{3,}[0-9]|[0-9][a-zA-Z]{2,}/', $tag)) continue;
            // 排除以句子结构词开头/结尾的碎片
            if (preg_match('/^[的了在和与为于把被从到上下前后里中外之所能以而且或但如果因为所以虽然以及通过进行能够应该]/u', $tag)) continue;
            if (preg_match('/[的了在和与为于把被从到上下前后里中外之所能以而且或但如果因为所以虽然以及通过进行能够应该句]$/u', $tag)) continue;
            // 排除标题型/说明型碎片词
            if (preg_match('/^(内容|文章|主题|要点|特点|优势|劣势|方法|原因|结果|问题|方案)/u', $tag)) continue;
            // 排除思考/prompt 残留
            if (preg_match('/then the|wait:|output format|we should|should not|just output|newtitle|标签/i', $tag)) continue;
            // 跳过包含明确句子结构的
            if (preg_match('/作为|诞生|属于|用于|称为|由于|因此|然而|并且|以及|虽然|但是|可是/u', $tag)) continue;
            // 排除"的"在中间的长标签
            if (mb_strlen($tag, 'utf-8') > 4 && mb_strpos($tag, '的') !== false && mb_strpos($tag, '的') > 0) continue;
            $valid[] = $tag;
        }
        if (empty($valid)) {
            return $this->error(__('标签生成失败(模型输出了无效内容),请重试', 'zuo-ai-plus'), 422);
        }
        $result['content'] = implode(',', array_slice($valid, 0, 4));
        return $result;
    }

    public function handleImage(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkLicense()) {
            return $err;
        }

        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $prompt    = sanitize_textarea_field($request->get_param('prompt'));

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        // 同 featured_image 一样生成带中文元数据的提示词,统一解析
        $rawPrompt = $this->buildImagePrompt($prompt);

        // 先让 AI 生成英文绘图提示词 + 中文元数据
        try {
            $metaResult = $model->completion($rawPrompt, ['max_tokens' => 1024]);
            $rawText = trim($metaResult['content'] ?? $metaResult['text'] ?? (is_string($metaResult) ? $metaResult : ''));
        } catch (\Exception $e) {
            return $this->error(__('生成提示词失败:', 'zuo-ai-plus') . $e->getMessage(), 500);
        }

        // 统一解析中文图片元数据
        $meta = $this->parseChineseImageMetadata($rawText, $prompt);
        $englishPrompt = $meta['english_prompt'];
        $chineseDesc   = $meta['chinese_desc'];
        $chineseAlt    = $meta['chinese_alt'];

        // 再用英文提示词调用绘图模型
        try {
            $result = $model->image($englishPrompt);
            $imageUrl = is_array($result) ? ($result['url'] ?? '') : ($result['image_url'] ?? '');
            return $this->success([
                'url'           => $imageUrl,
                'image_prompt'  => $englishPrompt,
                'chinese_desc'  => $chineseDesc,
                'chinese_alt'   => $chineseAlt,
                'content'       => $chineseDesc,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function handleSaveDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        $content = sanitize_textarea_field($request->get_param('content') ?: '');
        $title   = sanitize_text_field($request->get_param('title') ?: ('AI 生成-' . gmdate('Y-m-d H:i')));

        if (empty($content)) {
            return $this->error(__('内容不能为空', 'zuo-ai-plus'));
        }

        $html = \ZuoAIPlus\Utils\MarkdownConverter::convert($content);

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $html,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $this->error($post_id->get_error_message(), 500);
        }

        return $this->success([
            'id'       => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        ]);
    }

    private function resolveModel(string $action, \WP_REST_Request $request, string $userModel): array
    {
        // Slug/关键词/改写优先使用非推理模型(MiniMax reasoning 会污染输出)
        // 依次尝试智谱→通义，都不可用时回退到用户选择的模型
        if (in_array($action, ['slug', 'keyword', 'rewrite'], true)) {
            $apiKeys = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
            // 优先非推理模型列表：通义优先（智谱经常429/余额不足）
            foreach (['tongyi', 'zhipu'] as $preferred) {
                $cfg = $apiKeys[$preferred] ?? null;
                $key = is_array($cfg) ? ($cfg['api_key'] ?? '') : (is_string($cfg) ? $cfg : '');
                if ($key) {
                    $model = $this->getModel($preferred);
                    if ($model) {
                        return ['model' => $model, 'name' => $preferred];
                    }
                }
            }
            // 非推理模型均不可用,使用用户选择的模型
            $fallbackModel = $this->getModel($userModel);
            return [
                'model' => $fallbackModel,
                'name'  => $userModel,
            ];
        }

        if (!in_array($action, ['featured_image', 'image'])) {
            return [
                'model' => $this->getModel($userModel),
                'name'  => $userModel,
            ];
        }

        $imgApiKeys  = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
        $imgPlatform = $action === 'featured_image'
            ? (get_option('ai_plus_image_model') ?: 'tongyi')
            : (sanitize_text_field($request->get_param('model') ?: '') ?: (get_option('ai_plus_image_model') ?: 'tongyi'));

        $imgModelId = $imgApiKeys[$imgPlatform]['image_model'] ?? '';
        $imgModel   = $this->getModel($imgPlatform);

        return [
            'model'        => $imgModel,
            'img_model'    => $imgModel,
            'img_model_id' => $imgModelId,
            'name'         => $imgPlatform,
        ];
    }

    private function getMaxTokens(string $action): int
    {
        return match ($action) {
            'generate'       => 8192,
            'rewrite'        => 4096,
            'expand'         => 4096,
            'featured_image' => 1024,
            'summarize'      => 2000,
            'keyword'        => 2000,
            'slug'           => 40,
            'title_optimize' => 2000,
            'category'       => 200,
            default          => 2048,
        };
    }

    /**
     * 特色图生成:解析中文元数据(图片说明/替代文本)+ 英文提示词,分别处理
     */
    private function handleImageGeneration(string $rawPrompt, $imgModel, string $modelName, string $imgModelId): \WP_REST_Response
    {
        if (empty(trim($rawPrompt))) {
            return $this->success([
                'error' => '未生成图片描述,请检查文章内容或换一个模型试试',
                'url'   => '',
            ]);
        }

        if (!$imgModel) {
            return $this->error(__('图片模型未配置,请在后台设置里配置特色图片模型', 'zuo-ai-plus'));
        }

        // 统一解析中文图片元数据
        $meta = $this->parseChineseImageMetadata($rawPrompt);
        $englishPrompt = $meta['english_prompt'];
        $chineseDesc   = $meta['chinese_desc'];
        $chineseAlt    = $meta['chinese_alt'];

                try {
            $sizeOpt = get_option('ai_plus_image_size', '1216*832');
            $opts    = [
                'size'   => $sizeOpt,
                'model'  => $imgModelId ?: null,
                'style'  => 'photorealistic',
            ];
            $imageResult = $imgModel->image($englishPrompt, $opts);

            if (!empty($imageResult['url'])) {
                return $this->success([
                    'content'       => $chineseDesc ?: $englishPrompt,
                    'image_prompt' => $englishPrompt,
                    'chinese_desc' => $chineseDesc,
                    'chinese_alt'  => $chineseAlt,
                    'url'          => $imageResult['url'],
                    'model'        => $imgModelId ?: $modelName,
                ]);
            }
        } catch (\Throwable $imgErr) {
            return $this->error(__('图片生成失败:', 'zuo-ai-plus') . $imgErr->getMessage());
        }

        return $this->success([
            'content'       => $chineseDesc ?: $englishPrompt,
            'chinese_desc' => $chineseDesc,
            'chinese_alt'  => $chineseAlt,
            'url'          => '',
        ]);
    }

    /**
     * 统一解析 AI 返回的图片元数据(中文图片描述 + 英文绘图提示词)
     * 合并 handleImage() 和 handleImageGeneration() 中的两套重复解析逻辑。
     *
     * @param string $rawText  AI 返回的原始文本(含英文提示词 + 中文元数据标记)
     * @param string $userPrompt 用户原始输入(兜底时使用)
     * @return array { english_prompt, chinese_desc, chinese_alt }
     */
    private function parseChineseImageMetadata(string $rawText, string $userPrompt = ''): array
    {
        // 防止超长AI响应导致正则性能问题,最多处理前5000字符
        if (mb_strlen($rawText, 'utf-8') > 5000) {
            $rawText = mb_substr($rawText, 0, 5000, 'utf-8');
        }
        $chineseDesc   = '';
        $chineseAlt    = '';
        $englishPrompt = $rawText;

        // 尝试多种中文标记定位元数据区块
        $zhMarkers = ['【中文图片描述】', '中文图片描述', '图片描述', '图片说明'];
        $posZh = false;
        $foundMarker = '';
        foreach ($zhMarkers as $marker) {
            $pos = mb_strpos($rawText, $marker, 0, 'utf-8');
            if ($pos !== false) {
                $posZh = $pos;
                $foundMarker = $marker;
                break;
            }
        }

        if ($posZh !== false) {
            // 有结构化输出:英文提示词在前,中文元数据在标记之后
            $englishPrompt = trim(mb_substr($rawText, 0, $posZh, 'utf-8'));
            $metaBlock = mb_substr($rawText, $posZh + mb_strlen($foundMarker, 'utf-8'), null, 'utf-8');

            // 提取图片说明(多种格式兜底)
            $descPatterns = [
                '/图片说明[:：]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|替代文本|$)/u',
                '/说明[:：]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|$)/u',
                '/\[([^\]]{2,40})\]\s*(?:\n|替代文本|$)/u',
            ];
            foreach ($descPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $candidate = trim($m[1]);
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                        $chineseDesc = $candidate;
                        break;
                    }
                }
            }

            // 提取替代文本(多种格式兜底)
            $altPatterns = [
                '/替代文本[:：]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
                '/替代[:：]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
                '/alt[:：]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/ui',
            ];
            foreach ($altPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $candidate = trim($m[1]);
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                        $chineseAlt = $candidate;
                        break;
                    }
                }
            }
        }

        // 兜底:从全文中扫描图片说明(任意位置)
        if (!$chineseDesc) {
            if (preg_match('/图片说明[:：]\s*\[?([^\]\n\[]{2,40}?)\]?\s*(?:\n|$)/u', $rawText, $m)) {
                $candidate = trim($m[1]);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseDesc = $candidate;
                }
            }
        }

        // 兜底:从全文中扫描替代文本(任意位置)
        if (!$chineseAlt) {
            if (preg_match('/替代文本[:：]\s*\[?([^\]\n\[]{2,25}?)\]?\s*(?:\n|$)/u', $rawText, $m)) {
                $candidate = trim($m[1]);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseAlt = $candidate;
                }
            }
        }

        // 最终兜底:无法解析时使用用户原始输入
        if (!$chineseDesc) {
            if (preg_match('/[\x{4e00}-\x{9fa5}]{4,}/u', $userPrompt, $m)) {
                $chineseDesc = mb_substr($m[0], 0, 30, 'utf-8');
            } else {
                $autoMeta = $this->buildAutoChineseMetadata($rawText);
                $chineseDesc = $autoMeta['desc'];
            }
        }

        // 确保替代文本不为空
        if (!$chineseAlt && $chineseDesc) {
            $chineseAlt = mb_substr($chineseDesc, 0, 18, 'utf-8');
        }

        // 清理英文提示词前的标记文字
        $englishPrompt = trim(preg_replace('/^【[^】]+】\s*/um', '', $englishPrompt));

        return [
            'english_prompt' => $englishPrompt,
            'chinese_desc'  => $chineseDesc,
            'chinese_alt'   => $chineseAlt,
        ];
    }

    private function buildPrompt(string $action, string $content, string $extraPrompt): string
    {
        $kb      = trim(get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【产品/品牌背景知识】\n" . $kb . "\n") : '';
        $extra   = $extraPrompt ? ("\n\n【用户补充要求】" . $extraPrompt . "\n") : '';

        return match ($action) {
            // SEO 友好的文章生成 prompt
            'generate' => "你是一位资深中文SEO内容专家。请根据标题撰写一篇符合统一SEO标准的高质量文章。\n\n【统一SEO标准】\n- 内容字数:最少500字,推荐800字以上,结构完整(引言+正文+总结)\n- 标题质量:文章内不重复标题,但内容包含核心关键词,语言自然流畅\n- 关键词融入:文章中合理融入核心关键词和相关长尾词\n- 结构要求:引言(100-200字),2-4个正文小节(每节200-300字),总结(80-120字)\n\n【写作要求】\n- 语言专业但不生硬,贴近真实阅读体验\n- 避免使用列表式结构(如 '1.','2.'),多用自然段落叙述\n- 不要写文章标题(标题已在WordPress中单独填写)\n- 直接输出正文内容,不要任何前缀、编号或解释\n\n{$kbBlock}{$extra}\n文章标题:{$content}",

            'rewrite' => "你是一位资深文案改写专家。请将以下文章进行改写。{$kbBlock}{$extra}\n改写要求:\n1. 严格保持原意,不得删减或扭曲核心信息\n2. 换一种表达方式,句式和词汇都要有变化\n3. 提升可读性,结构更清晰\n4. 不要遗漏重要信息\n5. 直接输出改写结果,不要任何前缀、编号或解释\n\n原文:\n{$content}",

            'expand'  => "你是一位内容扩展写作专家。请在原文基础上续写内容。{$kbBlock}{$extra}\n续写要求:\n1. 不重复已有内容,在结尾处自然衔接\n2. 增加更多细节、案例和论述\n3. 保持与原文一致的风格语气\n4. 直接续写,不要标题、不要编号\n\n原文:\n{$content}",

            'summarize' => "请为以下文章生成一段符合统一SEO标准的文章摘要。\n\n【统一SEO摘要标准】\n- 字数:80-120个汉字(严格限制)\n- 内容结构:包含文章核心关键词 + 文章价值点 + 行动引导(例如:'本文将详细分析...'、'您将了解...'、'让我们一起来探讨...')\n- 语言风格:精炼、吸引点击、有说服力\n\n【输出要求】\n- 直接输出摘要,不要任何前缀、编号或解释\n- 不要换行,不要包含引号\n- 绝对不要输出任何思考过程、计数过程、英文说明或内部提示\n- 只输出纯中文摘要内容,不要其他任何文字\n\n文章正文:{$content}",

            'keyword' => $this->buildKeywordPrompt($content),

            'category' => "判断以下内容最适合的WordPress分类名称,只返回分类名称:\n{$content}",

            'slug' => "请根据标题生成一个简短的WordPress别名(slug),只输出英文或拼音slug,不要任何说明、不要引号、不要多余字符。标题:{$content}",

            'title_optimize' => $this->buildTitleOptimizePrompt($content),

            // 特色图:同时输出英文绘图提示词 + 中文图片元数据
            'featured_image', 'image' => $this->buildImagePrompt($content),

            default => "请根据以下内容续写文章正文。不要写标题(不要写 # 或 ## 标题),不要重复已有内容,直接写正文段落,用 - 或 1. 列表组织信息:\n{$content}",
        };
    }

    private function buildKeywordPrompt(string $content): string
    {
        return "你是一位SEO关键词专家。请为以下文章提取符合统一SEO标准的关键词标签。\n\n【统一SEO标签标准】\n- 数量:3-5个,推荐4个\n- 长度:每个标签2-6个汉字,禁止单个汉字或过长词汇\n- 质量:必须是含义完整的中文名词词组(如:智能家居、极简设计、衣柜收纳),不能是句子片段、主谓宾短语、说明性标题\n- SEO友好:核心关键词优先,避免宽泛词(如:技术、方法、产品),不要品牌名、公司名、日期\n- 禁止词:不能以结构助词开头/结尾(的、了、在、和、与、为、于、所、以、而)\n\n【输出格式】\n- 只输出标签词汇,用中文逗号「,」分隔\n- 不要编号、不要任何前缀或解释,直接输出词汇\n\n文章内容:\n{$content}";
    }

    private function buildTitleOptimizePrompt(string $content): string
    {
        $s = SeoOptimizer::SEO_STANDARDS;
        $tMin = $s['title']['min_chars'];
        $tMax = $s['title']['max_chars'];
        return "你是一位中文SEO标题专家。请根据原始标题生成一个符合百度SEO标准的最佳标题。\n\n【SEO标题标准】\n- 字数：{$tMin}-{$tMax}个汉字（关键词丰富但自然流畅）\n- 核心关键词：必须包含文章的核心关键词，关键词靠前放置\n- 搜索意图：在标题中加入搜索意图词（如：指南、教程、方案、推荐、评测、对比、怎么、如何、哪个等）以提升点击率\n- 语言风格：自然流畅，不要生硬堆砌关键词，不要学术腔调\n\n【禁止事项】\n- 不要使用【】、『』、《》、「」等特殊符号\n- 不要包含'（X字）'、'（约X字）'、'（N个字）'等字数提示\n- 不要星号*、引号或其他特殊符号\n\n【输出要求】\n- 直接输出新标题，不要任何前缀、编号、解释或引号\n- 标题不要包含原始标题内容（避免重复）\n\n原始标题：\n{$content}";
    }

    /**
     * 特色图提示词构建:生成高质量英文绘图提示词 + 中文图片元数据
     * 输出格式:英文提示词(直接给绘图模型用)+ 【中文图片描述】模块(给 WordPress 元数据用)
     */
    private function buildImagePrompt(string $content): string
    {
        return "你是一位专业的AI绘图提示词工程师。请根据用户描述,生成一份高质量的英文AI绘图提示词。\n\n"
            . "=== 英文AI绘图提示词(80-150个英文单词)===\n"
            . "直接输出英文提示词,不要加【英文AI绘图提示词】标记,格式如下:\n"
            . "主体描述, 材质细节, 色彩方案, 光照条件, 背景环境, 摄影风格, 构图方式, masterpiece, best quality, highly detailed, professional lighting, sharp focus, 8k uhd\n\n"
            . "必须包含以下要素,用英文逗号分隔:\n"
            . "1. Subject(主体):详细描述主体对象、姿态、位置\n"
            . "2. Material(材质):表面质感、纹理、反光特性\n"
            . "3. Color Palette(色彩):主色调、配色方案、光影色调\n"
            . "4. Lighting(光照):光源类型、方向、强度、氛围\n"
            . "5. Background(背景):环境描述、景深、虚实关系\n"
            . "6. Style(风格):写实/概念艺术/产品摄影/室内设计等\n"
            . "7. Composition(构图):镜头角度、景别、透视\n"
            . "8. Quality Tags(质量标签):masterpiece, best quality, highly detailed, professional lighting, sharp focus, 8k uhd\n\n"
            . "=== 中文图片描述 ===\n"
            . "在英文提示词之后,必须严格使用以下格式(一行一个):\n"
            . "图片说明:[20字以内的中文描述,说明图片展示什么内容]\n"
            . "替代文本:[10-15字中文,简洁描述图片主体用于SEO]\n\n"
            . "【示例参考】\n"
            . "英文部分:\n"
            . "modern minimalist living room interior, light oak wood flooring, off-white fabric sofa with textured cushions, natural sunlight streaming through large floor-to-ceiling windows, green potted plants in ceramic pots, warm neutral color palette with beige and cream tones, professional architectural photography, wide-angle lens, soft diffused lighting, cozy and inviting atmosphere, clean lines and uncluttered space, masterpiece, best quality, highly detailed, photorealistic, 8k uhd, sharp focus\n\n"
            . "中文部分:\n"
            . "图片说明:[现代简约客厅场景,浅色木地板搭配米白色布艺沙发]\n"
            . "替代文本:[简约风格客厅室内设计效果图]\n\n"
            . "【严格规则 - 必须遵守】\n"
            . "1. 先输出完整的英文提示词(不要带任何【】标记)\n"
            . "2. 然后换行,输出'图片说明:[中文描述]'(方括号内必须是纯中文)\n"
            . "3. 再换行,输出'替代文本:[中文描述]'(方括号内必须是纯中文)\n"
            . "4. 英文提示词必须包含质量标签:masterpiece, best quality, highly detailed, professional lighting\n"
            . "5. 主体描述要具体,避免模糊的'a room',要写'a spacious Scandinavian-style bedroom'\n"
            . "6. 光照描述要专业:natural sunlight, soft diffused lighting等\n"
            . "7. 风格要明确:photorealistic product photography / interior design render\n\n"
            . "用户输入:\n{$content}";
    }

    /**
     * 当 AI 未按格式输出中文元数据时,根据 prompt 内容自动生成
     */
    private function buildAutoChineseMetadata(string $prompt): array
    {
        // 从 prompt 中提取中文关键内容作为描述
        $desc = $prompt;
        // 去掉英文提示词部分(如果有)
        $desc = preg_replace('/^【[^】]+】.*$/um', '', $desc);
        $desc = preg_replace('/^[\[【].*?[\]】]/um', '', $desc);
        $desc = preg_replace('/[---]\s*/u', '', $desc);
        $desc = trim($desc);
        // 限制长度
        if (mb_strlen($desc, 'utf-8') > 30) {
            $desc = mb_substr($desc, 0, 28, 'utf-8') . '...';
        }
        if (mb_strlen($desc, 'utf-8') < 4) {
            $desc = '家居产品展示图';
        }
        // 替代文本:简短版
        $alt = mb_substr($desc, 0, 18, 'utf-8');
        return ['desc' => $desc, 'alt' => $alt];
    }

    /**
     * 根据操作类型返回明确的缓存 TTL(秒),避免依赖 prompt 文本推断
     */
    private function getCacheTtlForAction(string $action): int
    {
        return match ($action) {
            'generate', 'expand', 'rewrite'  => \ZuoAIPlus\Utils\Constants::CACHE_TTL_CONTENT_GENERATE,
            'summarize'                         => \ZuoAIPlus\Utils\Constants::CACHE_TTL_SUMMARIZE,
            'keyword'                          => \ZuoAIPlus\Utils\Constants::CACHE_TTL_KEYWORD,
            'slug'                             => \ZuoAIPlus\Utils\Constants::CACHE_TTL_SLUG,
            'title_optimize'                   => \ZuoAIPlus\Utils\Constants::CACHE_TTL_TITLE_OPTIMIZE,
            'featured_image', 'image'          => \ZuoAIPlus\Utils\Constants::CACHE_TTL_IMAGE,
            default                            => \ZuoAIPlus\Utils\Constants::CACHE_TTL_DEFAULT,
        };
    }
}
