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
            'generate'       => ['max' => 5, 'window' => 60],    // 文章生成：每分钟5次
            'expand'         => ['max' => 10, 'window' => 60],   // 扩写：每分钟10次
            'rewrite'        => ['max' => 10, 'window' => 60],   // 改写：每分钟10次
            'summarize'      => ['max' => 20, 'window' => 60],   // 摘要：每分钟20次
            'keyword'        => ['max' => 20, 'window' => 60],   // 关键词：每分钟20次
            'slug'           => ['max' => 30, 'window' => 60],   // slug：每分钟30次
            'featured_image' => ['max' => 3, 'window' => 60],    // 特色图：每分钟3次
            'image'          => ['max' => 3, 'window' => 60],    // 文生图：每分钟3次
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
            return $this->error('未配置该模型或API Key');
        }

        $prompt    = $this->buildPrompt($action, $content, $extraPrompt);
        $maxTokens = $this->getMaxTokens($action);

        try {
            $result = $model->completion($prompt, ['max_tokens' => $maxTokens]);
            $text   = trim($result['content'] ?? $result['text'] ?? (is_string($result) ? $result : ''));

            // Markdown → HTML 转换
            if (in_array($action, ['generate', 'expand', 'rewrite']) && $text) {
                $text = \ZuoAIPlus\Utils\MarkdownConverter::convert($text);
            }

            // 特色图处理
            if (in_array($action, ['featured_image', 'image'])) {
                return $this->handleImageGeneration($text, $imgModel, $modelName, $modelInfo['img_model_id'] ?? '');
            }

            // 关键词/摘要/slug 在模型层已处理，AI 返回的 text 即最终结果
            if (in_array($action, ['generate', 'expand', 'rewrite'])) {
                $result['content'] = $text;
            }

            // 记录操作历史
            $this->logHistory($action, $userModel, $result);

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
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
            return $this->error('未配置该模型或API Key');
        }

        // 同 featured_image 一样生成带中文元数据的提示词，统一解析
        $rawPrompt = $this->buildImagePrompt($prompt);

        // 先让 AI 生成英文绘图提示词 + 中文元数据
        try {
            $metaResult = $model->completion($rawPrompt, ['max_tokens' => 1024]);
            $rawText = trim($metaResult['content'] ?? $metaResult['text'] ?? (is_string($metaResult) ? $metaResult : ''));
        } catch (\Exception $e) {
            return $this->error('生成提示词失败：' . $e->getMessage(), 500);
        }

        // 解析中文元数据（与 handleImageGeneration 相同）
        $chineseDesc   = '';
        $chineseAlt    = '';
        $englishPrompt = $rawText;

        // 尝试多种中文标记
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
            $englishPrompt = trim(mb_substr($rawText, 0, $posZh, 'utf-8'));
            $metaBlock = mb_substr($rawText, $posZh + mb_strlen($foundMarker, 'utf-8'), null, 'utf-8');

            // 尝试多种格式匹配图片说明
            $descPatterns = [
                '/图片说明[：:]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|替代文本|$)/u',
                '/说明[：:]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|$)/u',
                '/\[([^\]]{2,40})\]\s*(?:\n|替代文本|$)/u',
            ];
            foreach ($descPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $chineseDesc = trim($m[1]);
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chineseDesc)) {
                        break;
                    }
                }
            }

            // 尝试多种格式匹配替代文本
            $altPatterns = [
                '/替代文本[：:]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
                '/替代[：:]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
            ];
            foreach ($altPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $chineseAlt = trim($m[1]);
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chineseAlt)) {
                        break;
                    }
                }
            }
        }

        // 兜底：如果还没提取到中文描述，尝试从整个文本中提取（任何位置）
        if (!$chineseDesc) {
            if (preg_match('/图片说明[：:]\s*\[?([^\]\n\[]{2,40}?)\]?\s*(?:\n|$)/u', $rawText, $m)) {
                $candidate = trim($m[1]);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseDesc = $candidate;
                }
            }
        }

        // 兜底：如果还没提取到替代文本
        if (!$chineseAlt) {
            if (preg_match('/替代文本[：:]\s*\[?([^\]\n\[]{2,25}?)\]?\s*(?:\n|$)/u', $rawText, $m)) {
                $candidate = trim($m[1]);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseAlt = $candidate;
                }
            }
        }

        // 如果描述还没提取到，使用用户原始输入或自动生成
        if (!$chineseDesc) {
            if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $prompt)) {
                $chineseDesc = mb_substr($prompt, 0, 30, 'utf-8');
            } else {
                $autoMeta = $this->buildAutoChineseMetadata($rawText);
                $chineseDesc = $autoMeta['desc'];
            }
        }

        // 确保替代文本不为空
        if (!$chineseAlt && $chineseDesc) {
            $chineseAlt = mb_substr($chineseDesc, 0, 18, 'utf-8');
        }
        
        // DEBUG: 记录解析结果
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ZuoAI] handleImage rawText: ' . substr($rawText, 0, 500));
            error_log('[ZuoAI] handleImage parsed: desc=' . $chineseDesc . ', alt=' . $chineseAlt);
        }
        $englishPrompt = trim(preg_replace('/^【[^】]+】\s*/um', '', $englishPrompt));

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
        // Slug/关键词/改写优先使用智谱（MiniMax reasoning 会污染输出）
        // 但如果智谱未配置，回退到用户选择的模型
        if (in_array($action, ['slug', 'keyword', 'rewrite'], true)) {
            $zhipuKey = '';
            $apiKeys  = (array) get_option('ai_plus_api_keys', []);
            $zk = $apiKeys['zhipu'] ?? null;
            $zhipuKey = is_array($zk) ? ($zk['api_key'] ?? '') : (is_string($zk) ? $zk : '');
            if ($zhipuKey) {
                return [
                    'model' => new \ZuoAIPlus\Models\ZhipuModel($zhipuKey, 'glm-4-flash'),
                    'name'  => 'zhipu',
                ];
            }
            // 智谱未配置，使用用户选择的模型，但调整参数减少 reasoning 污染
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

        $imgApiKeys  = get_option('ai_plus_api_keys', []);
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
     * 特色图生成：解析中文元数据（图片说明/替代文本）+ 英文提示词，分别处理
     */
    private function handleImageGeneration(string $rawPrompt, $imgModel, string $modelName, string $imgModelId): \WP_REST_Response
    {
        if (empty(trim($rawPrompt))) {
            return $this->success([
                'error' => '未生成图片描述，请检查文章内容或换一个模型试试',
                'url'   => '',
            ]);
        }

        if (!$imgModel) {
            return $this->error('图片模型未配置，请在后台设置里配置特色图片模型');
        }

        // 解析中文图片元数据（来自 buildImagePrompt 的结构化输出）
        $chineseDesc   = '';
        $chineseAlt    = '';
        $englishPrompt = $rawPrompt;

        // 尝试多种中文标记
        $zhMarkers = ['【中文图片描述】', '中文图片描述', '图片描述', '图片说明'];
        $posZh = false;
        $foundMarker = '';
        foreach ($zhMarkers as $marker) {
            $pos = mb_strpos($rawPrompt, $marker, 0, 'utf-8');
            if ($pos !== false) {
                $posZh = $pos;
                $foundMarker = $marker;
                break;
            }
        }

        if ($posZh !== false) {
            // 有结构化输出：英文提示词在前，中文元数据在标记之后
            $englishPrompt = trim(mb_substr($rawPrompt, 0, $posZh, 'utf-8'));
            $metaBlock = mb_substr($rawPrompt, $posZh + mb_strlen($foundMarker, 'utf-8'), null, 'utf-8');

            // 尝试多种格式匹配图片说明
            $descPatterns = [
                '/图片说明[：:]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|替代文本|$)/u',
                '/说明[：:]\s*\[?([^\]\n]{2,40}?)\]?\s*(?:\n|$)/u',
                '/\[([^\]]{2,40})\]\s*(?:\n|替代文本|$)/u',
            ];
            foreach ($descPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $chineseDesc = trim($m[1]);
                    // 确保提取到的是中文（包含中文字符）
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chineseDesc)) {
                        break;
                    }
                }
            }

            // 尝试多种格式匹配替代文本
            $altPatterns = [
                '/替代文本[：:]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
                '/替代[：:]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/u',
                '/alt[：:]\s*\[?([^\]\n]{2,25}?)\]?\s*(?:\n|$)/ui',
            ];
            foreach ($altPatterns as $pattern) {
                if (preg_match($pattern, $metaBlock, $m)) {
                    $chineseAlt = trim($m[1]);
                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $chineseAlt)) {
                        break;
                    }
                }
            }
        }

        // 兜底：如果还没提取到中文描述，尝试从整个文本中提取（任何位置）
        if (!$chineseDesc) {
            // 尝试匹配 "图片说明：[...]" 格式
            if (preg_match('/图片说明[：:]\s*\[?([^\]\n\[]{2,40}?)\]?\s*(?:\n|$)/u', $rawPrompt, $m)) {
                $candidate = trim($m[1]);
                // 确保是中文
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseDesc = $candidate;
                }
            }
        }

        // 兜底：如果还没提取到替代文本，尝试从整个文本中提取
        if (!$chineseAlt) {
            if (preg_match('/替代文本[：:]\s*\[?([^\]\n\[]{2,25}?)\]?\s*(?:\n|$)/u', $rawPrompt, $m)) {
                $candidate = trim($m[1]);
                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $candidate)) {
                    $chineseAlt = $candidate;
                }
            }
        }

        // 如果描述还没提取到，使用用户原始输入或自动生成
        if (!$chineseDesc) {
            if (preg_match('/[\x{4e00}-\x{9fa5}]{4,}/u', $content, $m)) {
                $chineseDesc = mb_substr($m[0], 0, 30, 'utf-8');
            } else {
                $autoMeta = $this->buildAutoChineseMetadata($rawPrompt);
                $chineseDesc = $autoMeta['desc'];
            }
        }

        // 确保替代文本不为空
        if (!$chineseAlt && $chineseDesc) {
            $chineseAlt = mb_substr($chineseDesc, 0, 18, 'utf-8');
        }
        // 清理英文提示词前的标记文字
        $englishPrompt = trim(preg_replace('/^【[^】]+】\s*/um', '', $englishPrompt));

        // DEBUG: 记录 AI 原始输出和解析结果（仅调试模式）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ZuoAI] handleImageGeneration rawPrompt: ' . substr($rawPrompt, 0, 500));
            error_log('[ZuoAI] handleImageGeneration parsed englishPrompt: ' . substr($englishPrompt, 0, 200));
            error_log('[ZuoAI] handleImageGeneration chineseDesc: ' . $chineseDesc);
            error_log('[ZuoAI] handleImageGeneration chineseAlt: ' . $chineseAlt);
        }

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
            return $this->success([
                'content'       => $chineseDesc ?: $englishPrompt,
                'image_prompt' => $englishPrompt,
                'chinese_desc' => $chineseDesc,
                'chinese_alt'  => $chineseAlt,
                'url'          => '',
                'error'        => '图片生成失败：' . $imgErr->getMessage(),
                'model'        => $imgModelId ?: $modelName,
            ]);
        }

        return $this->success([
            'content'       => $chineseDesc ?: $englishPrompt,
            'chinese_desc' => $chineseDesc,
            'chinese_alt'  => $chineseAlt,
            'url'          => '',
        ]);
    }

    private function buildPrompt(string $action, string $content, string $extraPrompt): string
    {
        $kb      = trim(get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【产品/品牌背景知识】\n" . $kb . "\n") : '';
        $extra   = $extraPrompt ? ("\n\n【用户补充要求】" . $extraPrompt . "\n") : '';

        return match ($action) {
            // SEO 友好的文章生成 prompt
            'generate' => "你是一位专业家居领域编辑兼SEO内容专家。请根据标题撰写一篇SEO友好的文章。\n\n要求：\n"
                . "- 标题已在WordPress中单独填写，正文只需写正文内容，**绝对不要包含文章标题**\n"
                . "- 结构：引言 + 2-4个正文小节（每个小节用 ## 二级标题 开头），再加一段总结\n"
                . "- 字数800-1200字，语言专业但不生硬，贴近真实阅读体验\n"
                . "- WordPress规范：多段落自然叙述，少用列表，不用乱加HTML\n"
                . "- 不要写编号列表，多用自然段落叙述{$kbBlock}{$extra}\n文章标题：{$content}",

            'expand'  => "请仔细阅读用户提供的文章段落，在其后直接续写内容，增加更多细节、案例和论述。不要重复已有内容，不要写标题，直接在原文基础上续写2-3段。{$kbBlock}{$extra}\n{$content}",

            'rewrite' => "请将以下文章进行改写。要求：保持原意、换一种表达方式、提升可读性、结构更清晰。不要遗漏重要信息。{$kbBlock}{$extra}\n原文：\n{$content}",

            'summarize' => "请为以下内容生成100字以内的摘要，语言精炼：\n{$content}",

            'keyword' => $this->buildKeywordPrompt($content),

            'category' => "判断以下内容最适合的WordPress分类名称，只返回分类名称：\n{$content}",

            'slug' => "请根据标题生成一个简短的WordPress别名（slug），只输出英文或拼音slug，不要任何说明、不要引号、不要多余字符。标题：{$content}",

            'title_optimize' => $this->buildTitleOptimizePrompt($content),

            // 特色图：同时输出英文绘图提示词 + 中文图片元数据
            'featured_image', 'image' => $this->buildImagePrompt($content),

            default => "请根据以下内容续写文章正文。不要写标题（不要写 # 或 ## 标题），不要重复已有内容，直接写正文段落，用 - 或 1. 列表组织信息：\n{$content}",
        };
    }

    private function buildKeywordPrompt(string $content): string
    {
        return "你是一位SEO关键词专家。请为以下文章提取3-5个关键词标签。\n\n提取规则：\n"
            . "- 每个标签必须是2-6个中文字的完整词组（如：智能家居、工业设计、衣柜布局）\n"
            . "- 必须是文章核心主题词或细分领域词，能反映文章讨论的具体内容\n"
            . "- 不要单个汉字，不要太宽泛的词（如「技术」「方法」「产品」），不要完整句子\n"
            . "- 不要品牌名、公司名、日期或无意义词\n"
            . "- 只输出标签，用中文逗号「，」分隔，不要编号、不要任何解释\n\n文章内容：\n{$content}";
    }

    private function buildTitleOptimizePrompt(string $content): string
    {
        return "你是一位SEO标题专家。请根据用户给出的原始文章标题，生成一个完全不同的、SEO友好的最佳标题。\n\n要求：\n"
            . "- 新标题必须与原文主题高度相关，不能偏离原意\n"
            . "- 字数控制在30-60字（汉字），在搜索引擎中展示完整且有吸引力\n"
            . "- 在标题前面加入搜索意图词（如：指南/教程/方案/推荐/评测/对比/怎么/如何/哪个/2024等）以提升点击率\n"
            . "- 核心关键词尽量靠前放置\n"
            . "- 语言自然流畅，不要生硬堆砌关键词\n"
            . "- 不要使用特殊符号（如【】、『』等），用 - 或 | 分隔即可\n"
            . "- 直接输出新标题，不要任何解释、不要引号\n\n原始标题：\n{$content}";
    }

    /**
     * 特色图提示词构建：生成高质量英文绘图提示词 + 中文图片元数据
     * 输出格式：英文提示词（直接给绘图模型用）+ 【中文图片描述】模块（给 WordPress 元数据用）
     */
    private function buildImagePrompt(string $content): string
    {
        return "你是一位专业的AI绘图提示词工程师。请根据用户描述，生成一份高质量的英文AI绘图提示词。\n\n"
            . "=== 英文AI绘图提示词（80-150个英文单词）===\n"
            . "直接输出英文提示词，不要加【英文AI绘图提示词】标记，格式如下：\n"
            . "主体描述, 材质细节, 色彩方案, 光照条件, 背景环境, 摄影风格, 构图方式, masterpiece, best quality, highly detailed, professional lighting, sharp focus, 8k uhd\n\n"
            . "必须包含以下要素，用英文逗号分隔：\n"
            . "1. Subject（主体）：详细描述主体对象、姿态、位置\n"
            . "2. Material（材质）：表面质感、纹理、反光特性\n"
            . "3. Color Palette（色彩）：主色调、配色方案、光影色调\n"
            . "4. Lighting（光照）：光源类型、方向、强度、氛围\n"
            . "5. Background（背景）：环境描述、景深、虚实关系\n"
            . "6. Style（风格）：写实/概念艺术/产品摄影/室内设计等\n"
            . "7. Composition（构图）：镜头角度、景别、透视\n"
            . "8. Quality Tags（质量标签）：masterpiece, best quality, highly detailed, professional lighting, sharp focus, 8k uhd\n\n"
            . "=== 中文图片描述 ===\n"
            . "在英文提示词之后，必须严格使用以下格式（一行一个）：\n"
            . "图片说明：[20字以内的中文描述，说明图片展示什么内容]\n"
            . "替代文本：[10-15字中文，简洁描述图片主体用于SEO]\n\n"
            . "【示例参考】\n"
            . "英文部分：\n"
            . "modern minimalist living room interior, light oak wood flooring, off-white fabric sofa with textured cushions, natural sunlight streaming through large floor-to-ceiling windows, green potted plants in ceramic pots, warm neutral color palette with beige and cream tones, professional architectural photography, wide-angle lens, soft diffused lighting, cozy and inviting atmosphere, clean lines and uncluttered space, masterpiece, best quality, highly detailed, photorealistic, 8k uhd, sharp focus\n\n"
            . "中文部分：\n"
            . "图片说明：[现代简约客厅场景，浅色木地板搭配米白色布艺沙发]\n"
            . "替代文本：[简约风格客厅室内设计效果图]\n\n"
            . "【严格规则 - 必须遵守】\n"
            . "1. 先输出完整的英文提示词（不要带任何【】标记）\n"
            . "2. 然后换行，输出'图片说明：[中文描述]'（方括号内必须是纯中文）\n"
            . "3. 再换行，输出'替代文本：[中文描述]'（方括号内必须是纯中文）\n"
            . "4. 英文提示词必须包含质量标签：masterpiece, best quality, highly detailed, professional lighting\n"
            . "5. 主体描述要具体，避免模糊的'a room'，要写'a spacious Scandinavian-style bedroom'\n"
            . "6. 光照描述要专业：natural sunlight, soft diffused lighting等\n"
            . "7. 风格要明确：photorealistic product photography / interior design render\n\n"
            . "用户输入：\n{$content}";
    }

    /**
     * 当 AI 未按格式输出中文元数据时，根据 prompt 内容自动生成
     */
    private function buildAutoChineseMetadata(string $prompt): array
    {
        // 从 prompt 中提取中文关键内容作为描述
        $desc = $prompt;
        // 去掉英文提示词部分（如果有）
        $desc = preg_replace('/^【[^】]+】.*$/um', '', $desc);
        $desc = preg_replace('/^[\[【].*?[\]】]/um', '', $desc);
        $desc = preg_replace('/[-―-]\s*/u', '', $desc);
        $desc = trim($desc);
        // 限制长度
        if (mb_strlen($desc, 'utf-8') > 30) {
            $desc = mb_substr($desc, 0, 28, 'utf-8') . '...';
        }
        if (mb_strlen($desc, 'utf-8') < 4) {
            $desc = '家居产品展示图';
        }
        // 替代文本：简短版
        $alt = mb_substr($desc, 0, 18, 'utf-8');
        return ['desc' => $desc, 'alt' => $alt];
    }
}
