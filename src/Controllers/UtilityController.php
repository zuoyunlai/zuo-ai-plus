<?php
/**
 * 工具控制器 - 处理翻译、别名、摘要、关键词、标签等工具接口
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class UtilityController extends BaseController
{
    // 语言映射表
    private const LANG_MAP = [
        'auto' => ['name' => '自动检测', 'zhipu' => 'auto', 'tongyi' => '自动检测', 'minimax' => 'auto', 'kimi' => 'auto', 'deepseek' => 'auto', 'custom' => 'auto'],
        'en'   => ['name' => '英文', 'zhipu' => 'English', 'tongyi' => '英文', 'minimax' => 'English', 'kimi' => 'English', 'deepseek' => 'English', 'custom' => 'English'],
        'zh'   => ['name' => '中文', 'zhipu' => 'Chinese', 'tongyi' => '中文', 'minimax' => 'Chinese', 'kimi' => 'Chinese', 'deepseek' => 'Chinese', 'custom' => 'Chinese'],
        'zt'   => ['name' => '繁体中文', 'zhipu' => 'Chinese', 'tongyi' => '中文', 'minimax' => 'Chinese', 'kimi' => 'Chinese', 'deepseek' => 'Chinese', 'custom' => 'Chinese'],
        'ja'   => ['name' => '日文', 'zhipu' => 'Japanese', 'tongyi' => '日文', 'minimax' => 'Japanese', 'kimi' => 'Japanese', 'deepseek' => 'Japanese', 'custom' => 'Japanese'],
        'ko'   => ['name' => '韩文', 'zhipu' => 'Korean', 'tongyi' => '韩文', 'minimax' => 'Korean', 'kimi' => 'Korean', 'deepseek' => 'Korean', 'custom' => 'Korean'],
        'fr'   => ['name' => '法文', 'zhipu' => 'French', 'tongyi' => '法文', 'minimax' => 'French', 'kimi' => 'French', 'deepseek' => 'French', 'custom' => 'French'],
        'de'   => ['name' => '德文', 'zhipu' => 'German', 'tongyi' => '德文', 'minimax' => 'German', 'kimi' => 'German', 'deepseek' => 'German', 'custom' => 'German'],
        'es'   => ['name' => '西班牙文', 'zhipu' => 'Spanish', 'tongyi' => '西班牙文', 'minimax' => 'Spanish', 'kimi' => 'Spanish', 'deepseek' => 'Spanish', 'custom' => 'Spanish'],
        'pt'   => ['name' => '葡萄牙文', 'zhipu' => 'Portuguese', 'tongyi' => '葡萄牙文', 'minimax' => 'Portuguese', 'kimi' => 'Portuguese', 'deepseek' => 'Portuguese', 'custom' => 'Portuguese'],
        'ru'   => ['name' => '俄文', 'zhipu' => 'Russian', 'tongyi' => '俄文', 'minimax' => 'Russian', 'kimi' => 'Russian', 'deepseek' => 'Russian', 'custom' => 'Russian'],
        'ar'   => ['name' => '阿拉伯文', 'zhipu' => 'Arabic', 'tongyi' => '阿拉伯文', 'minimax' => 'Arabic', 'kimi' => 'Arabic', 'deepseek' => 'Arabic', 'custom' => 'Arabic'],
        'it'   => ['name' => '意大利文', 'zhipu' => 'Italian', 'tongyi' => '意大利文', 'minimax' => 'Italian', 'kimi' => 'Italian', 'deepseek' => 'Italian', 'custom' => 'Italian'],
        'th'   => ['name' => '泰文', 'zhipu' => 'Thai', 'tongyi' => '泰文', 'minimax' => 'Thai', 'kimi' => 'Thai', 'deepseek' => 'Thai', 'custom' => 'Thai'],
        'vi'   => ['name' => '越南文', 'zhipu' => 'Vietnamese', 'tongyi' => '越南文', 'minimax' => 'Vietnamese', 'kimi' => 'Vietnamese', 'deepseek' => 'Vietnamese', 'custom' => 'Vietnamese'],
    ];

    public function registerRoutes(): void
    {
        // 翻译
        register_rest_route('ai-plus/v1', '/translate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleTranslate'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 别名生成
        register_rest_route('ai-plus/v1', '/slug', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSlug'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 摘要提取
        register_rest_route('ai-plus/v1', '/summarize', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSummarize'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 关键词提取
        register_rest_route('ai-plus/v1', '/keywords', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleKeywords'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 标签创建
        register_rest_route('ai-plus/v1', '/tags-create', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleTagsCreate'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 标签保存
        register_rest_route('ai-plus/v1', '/tags-save', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleTagsSave'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);

        // 特色图设置
        register_rest_route('ai-plus/v1', '/featured-image-set', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleFeaturedImageSet'],
            'permission_callback' => function () { return $this->canEdit(); },
        ]);
    }

    public function handleTranslate(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkRateLimit('translate', 20, 60)) { return $err; }
        $modelName  = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content    = sanitize_textarea_field($request->get_param('content'));
        $sourceLang = sanitize_text_field($request->get_param('source_lang') ?: 'auto');
        $targetLang = sanitize_text_field($request->get_param('target_lang') ?: 'zh');

        if (empty($content)) {
            return $this->error(__('内容不能为空', 'zuo-ai-plus'));
        }

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        try {
            $srcInfo = self::LANG_MAP[$sourceLang] ?? self::LANG_MAP['auto'];
            $tgtInfo = self::LANG_MAP[$targetLang] ?? self::LANG_MAP['zh'];

            $srcName = $srcInfo['name'];
            $tgtName = $tgtInfo['name'];

            $sys  = '你是一位专业翻译专家，只返回翻译结果，不添加任何解释、评论或额外内容。保持原文格式（Markdown/HTML）。';
            $user = $sourceLang === 'auto'
                ? "请将以下内容翻译成{$tgtName}，只返回翻译结果：\n{$content}"
                : "请将以下{$srcName}内容翻译成{$tgtName}，只返回翻译结果：\n{$content}";

            $msgs   = [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
            $result = $model->chat($msgs);

            $text = is_array($result) ? \ZuoAIPlus\Models\Model_Init::extractContent($result) : $result;
            return $this->success(['content' => $text]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function handleSlug(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkRateLimit('slug', 30, 60)) { return $err; }
        $title   = sanitize_text_field($request->get_param('title') ?: '');
        $content = sanitize_textarea_field($request->get_param('content') ?: '');

        if (empty($title) && empty($content)) {
            return $this->error(__('标题或内容不能为空', 'zuo-ai-plus'));
        }

        $text = $title ?: mb_substr($content, 0, 100);
        $slug = $this->generateSlug($text);
        $slug = $this->sanitizeSlug($slug);

        return $this->success(['slug' => $slug]);
    }

    public function handleSummarize(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkRateLimit('summarize', 20, 60)) { return $err; }
        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content   = sanitize_textarea_field($request->get_param('content') ?: '');

        if (empty($content)) {
            return $this->error(__('文章内容不能为空', 'zuo-ai-plus'));
        }

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        $prompt = $this->buildSummarizePrompt($content);

        try {
            $result = $model->completion($prompt, ['max_tokens' => 1500, 'temperature' => 0.5]);
            $text   = \ZuoAIPlus\Models\Model_Init::extractContent($result);
            return $this->success(['excerpt' => is_string($text) ? trim($text) : '']);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function handleKeywords(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = $this->checkRateLimit('keyword', 20, 60)) { return $err; }
        $modelName = sanitize_text_field($request->get_param('model') ?: $this->getDefaultModel());
        $content   = sanitize_textarea_field($request->get_param('content') ?: '');

        if (empty($content)) {
            return $this->error(__('文章内容不能为空', 'zuo-ai-plus'));
        }

        $model = $this->getModel($modelName);
        if (!$model) {
            return $this->error(__('未配置该模型或API Key', 'zuo-ai-plus'));
        }

        $prompt = $this->buildKeywordsPrompt($content);

        try {
            $result = $model->completion($prompt, ['max_tokens' => 800, 'temperature' => 0.3]);
            $text   = \ZuoAIPlus\Models\Model_Init::extractContent($result);
            $tags   = $this->parseTags($text);

            return $this->success(['tags' => $tags]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function handleTagsCreate(\WP_REST_Request $request): \WP_REST_Response
    {
        $name = sanitize_text_field($request->get_param('name') ?: '');
        if (empty($name)) {
            return $this->error(__('标签名称为空', 'zuo-ai-plus'));
        }

        $result = wp_insert_term($name, 'post_tag');
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success(['term_id' => $result['term_id'], 'name' => $name]);
    }

    public function handleTagsSave(\WP_REST_Request $request): \WP_REST_Response
    {
        $post_id  = intval($request->get_param('post_id') ?: 0);
        $tags_raw = $request->get_param('tags') ?: '';

        if (!$post_id) {
            return $this->error(__('无法获取文章ID', 'zuo-ai-plus'));
        }

        $term_ids = $this->resolveTagIds($request);

        $post = get_post($post_id);
        if (!$post) {
            return $this->error(sprintf(__('文章不存在 (ID=%d)', 'zuo-ai-plus'), $post_id));
        }

        if (empty($term_ids)) {
            // 标签全被过滤属于「正常不操作」，返回成功而非错误，前端不显示红色警告
            $saved = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
            return $this->success([
                'success'   => true,
                'skipped'   => true,
                'reason'    => 'AI生成的标签均不符合规范（过长/过短/含无效字符），已保留原标签',
                'tag_ids'   => [],
                'tag_names' => is_array($saved) ? array_slice($saved, 0, 4) : [],
            ]);
        }

        $result = wp_set_object_terms($post_id, $term_ids, 'post_tag');
        if (is_wp_error($result)) {
            return $this->error('标签保存失败：' . $result->get_error_message());
        }

        $saved_tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
        $saved_tags  = is_array($saved_tags) ? array_slice($saved_tags, 0, 4) : [];

        return $this->success([
            'success'   => true,
            'tag_ids'   => $term_ids,
            'tag_names' => $saved_tags,
        ]);
    }

    public function handleFeaturedImageSet(\WP_REST_Request $request): \WP_REST_Response
    {
        $imageUrl    = esc_url_raw($request->get_param('image_url') ?: '');
        $post_id     = intval($request->get_param('post_id') ?: 0);
        $post_title  = sanitize_text_field($request->get_param('post_title') ?: '');
        $post_content = sanitize_textarea_field($request->get_param('post_content') ?: '');
        $prompt      = sanitize_textarea_field($request->get_param('image_prompt') ?: '');
        $alt_text    = sanitize_text_field($request->get_param('alt_text') ?: '');
        $chinese_desc = sanitize_text_field($request->get_param('chinese_desc') ?: '');
        $chinese_alt  = sanitize_text_field($request->get_param('chinese_alt') ?: '');

        // DEBUG: 记录接收到的参数
        if (defined('WP_DEBUG') && WP_DEBUG) {
            /* @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log */
            error_log('[ZuoAI] featured-image-set params:');
            error_log('  chinese_desc: ' . ($chinese_desc ?: '(empty)'));
            error_log('  chinese_alt: ' . ($chinese_alt ?: '(empty)'));
            error_log('  post_title: ' . ($post_title ?: '(empty)'));
            error_log('  post_content len: ' . mb_strlen($post_content, 'utf-8'));
            /* @phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log */
        }

        // 智能备用：当 AI 未返回中文 metadata 时，从文章内容中提取关键词生成
        if (!$chinese_desc && !$chinese_alt) {
            $meta = $this->generateFallbackImageMeta($post_title, $post_content);
            $chinese_desc = $meta['desc'];
            $chinese_alt  = $meta['alt'];
        }

        // 优先使用中文元数据
        $final_title   = $chinese_desc ?: $post_title ?: 'AI生成图片';
        $final_alt     = $chinese_alt ?: '文章配图';
        $final_desc    = $chinese_desc ?: ('图片展示了' . ($post_title ?: '相关内容'));

        if (empty($imageUrl)) {
            return $this->error(__('图片URL不能为空', 'zuo-ai-plus'));
        }
        if (!$post_id) {
            return $this->error(__('无法获取文章ID', 'zuo-ai-plus'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp_file = sys_get_temp_dir() . '/feat_' . uniqid() . '.jpg';
        $response = wp_remote_request($imageUrl, [
            'method'   => 'GET',
            'timeout'  => 60,
            'stream'   => true,
            'filename' => $tmp_file,
        ]);

        if (is_wp_error($response)) {
            return $this->error('下载图片失败: ' . $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return $this->error(sprintf(__('下载图片失败: HTTP %d', 'zuo-ai-plus'), wp_remote_retrieve_response_code($response)));
        }

        // 用中文描述作为文件名（中文转拼音）
        $base_name = $final_title
            ? $this->titleToPinyin($final_title)
            : 'ai-image-' . $post_id;
        $base_name = preg_replace('/[^a-z0-9_-]/i', '-', $base_name);

        $att_id = media_handle_sideload([
            'name'     => $base_name . '-' . time() . '.jpg',
            'tmp_name' => $tmp_file,
        ], $post_id);

        if (is_wp_error($att_id)) {
            wp_delete_file($tmp_file);
            return $this->error('保存图片失败: ' . $att_id->get_error_message());
        }

        // 写入媒体库附件详情（中文元数据）
        $attachment = [
            'ID'           => $att_id,
            // 标题：优先使用中文描述
            'post_title'   => $final_title,
            // 说明文字（post_content）：中文图片描述
            'post_content' => $final_desc,
            // 摘要（post_excerpt）：中文替代文本
            'post_excerpt' => mb_substr($final_alt, 0, 100, 'utf-8'),
        ];
        wp_update_post($attachment);

        // 替代文本（alt）存在 postmeta 里（中文）
        if ($final_alt) {
            update_post_meta($att_id, '_wp_attachment_image_alt', mb_substr($final_alt, 0, 200, 'utf-8'));
        }

        // 自动设置文章特色图（在后端直接写库，最可靠）
        update_post_meta($post_id, '_thumbnail_id', $att_id);

        return $this->success([
            'success'       => true,
            'attachment_id' => $att_id,
            'url'           => wp_get_attachment_url($att_id),
            'title'         => $final_title,
            'alt'           => $final_alt,
            'description'   => $final_desc,
            'post_id'       => $post_id,
        ]);
    }

    // ============ 私有方法 ============

    /**
     * 特色图中文 metadata 智能备用
     * 当 AI 未返回中文元数据时，从文章标题和内容中提取关键词生成
     */
    private function generateFallbackImageMeta(string $postTitle, string $postContent): array
    {
        $desc = '';
        $alt  = '';

        // 从标题提取核心主题词
        if ($postTitle) {
            // 去掉常见前缀词
            $cleanTitle = preg_replace('/^(现代简约|极简|北欧|日式|中式|欧式|美式|工业风|轻奢|诧寂|混搭)\s*/u', '', $postTitle);
            $len = mb_strlen($cleanTitle, 'utf-8');
            if ($len <= 20) {
                $desc = $cleanTitle . '效果图';
                $alt  = mb_substr($cleanTitle, 0, 18, 'utf-8');
            } else {
                // 标题过长时，取前15字
                $desc = mb_substr($cleanTitle, 0, 15, 'utf-8') . '...';
                $alt  = mb_substr($cleanTitle, 0, 15, 'utf-8');
            }
        }

        // 如果内容中有明确主题词，补充到 alt 中
        if ($postContent && mb_strlen($postContent, 'utf-8') > 10) {
            // 提取内容中最先出现的3-6字主题词（名词）
            if (preg_match('/[\x{4e00}-\x{9fa5}]{3,8}(?:设计|风格|应用|装修|搭配|布局|空间|效果)/u', $postContent, $m)) {
                $keyword = $m[0];
                if ($alt && mb_strlen($alt, 'utf-8') < 12) {
                    $alt = $alt . '——' . $keyword;
                } else {
                    $alt = $keyword;
                }
            }
        }

        // 绝对兜底
        if (!$desc) $desc = '家居设计效果图';
        if (!$alt)  $alt  = mb_substr($desc, 0, 18, 'utf-8');

        return [
            'desc' => $desc,
            'alt'  => $alt,
        ];
    }

    /**
     * 专用 slug 生成：使用智谱 GLM（比 MiniMax 更遵循 prompt，不易复制 prompt 词汇）
     * 失败时使用标题拼音 fallback
     */
    private function generateSlug(string $title): string
    {
        $apiKeys = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
        $defaultModel = \get_option('ai_plus_default_model', 'minimax');
        $model = $this->createModelInstance($apiKeys, $defaultModel);

        if (!$model) {
            return $this->titleToPinyin($title);
        }

        try {
            $fallback = $model;
            $result = $fallback->completion(
                "Give me a short URL slug for this title, lowercase letters and hyphens only, no explanation. Title: {$title}",
                ['max_tokens' => 20, 'temperature' => 0.7]
            );
            $text = \ZuoAIPlus\Models\Model_Init::extractContent($result);
            $raw  = trim($text);
            // 取最后一行，再提取英文片段
            $lines   = array_filter(array_map('trim', explode("\n", $raw)), 'strlen');
            $lastLine = strtolower(end($lines) ?: $raw);
            $lastLine = preg_replace('/^[\d*.、：:\-\s]+/u', '', $lastLine);
            preg_match_all('/[a-z][a-z0-9\-]*/', $lastLine, $m);
            $valid = array_filter($m[0] ?? [], function($s) {
                $body = str_replace('-', '', $s);
                if (mb_strlen($body, 'utf-8') < 3) return false;
                if (mb_strlen($body, 'utf-8') > 20) return false;
                if (preg_match('/^(give|me|only|the|a|an|url|for|slug|title|lowercase|please|here|output|one|short|long)/i', $s)) return false;
                return true;
            });
            if (!empty($valid)) {
                usort($valid, fn($a, $b) => mb_strlen($b, 'utf-8') - mb_strlen($a, 'utf-8'));
                return $valid[0];
            }
            return $this->titleToPinyin($title);
        } catch (\Exception $e) {
            return $this->titleToPinyin($title);
        }
    }

    /**
     * 将中文标题转换为拼音 slug（取每个汉字拼音首字母，连字符分隔）
     */
    private function titleToPinyin(string $title): string
    {
        // 常用汉字 → 拼音映射（覆盖家具/设计词汇）
        static $map = [
            '铝'=>'lv','金'=>'jin','属'=>'shu','衣'=>'yi','柜'=>'gui','櫃'=>'gui',
            '卧'=>'wo','室'=>'shi','客'=>'ke','厅'=>'ting','餐'=>'can','桌'=>'zhuo',
            '书'=>'shu','房'=>'fang','玄'=>'xuan','关'=>'guan','阳'=>'yang','台'=>'tai',
            '儿'=>'er','童'=>'tong','卫'=>'wei','浴'=>'yu','门'=>'men','窗'=>'chuang',
            '楼'=>'lou','梯'=>'ti','天'=>'tian','花'=>'hua','地'=>'di','板'=>'ban',
            '砖'=>'zhuan','墙'=>'qiang','面'=>'mian',
            '设'=>'she','计'=>'ji','收'=>'shou','纳'=>'na','组'=>'zu','装'=>'zhuang',
            '连'=>'lian','接'=>'jie','受'=>'shou','力'=>'li','分'=>'fen','析'=>'xi',
            '框'=>'kuang','架'=>'jia','型'=>'xing','材'=>'cai','料'=>'liao',
            '实'=>'shi','木'=>'mu','定'=>'ding','制'=>'zhi','傢'=>'jia','具'=>'jv',
            '软'=>'ruan','智'=>'zhi','能'=>'neng','照'=>'zhao','明'=>'ming',
            '家'=>'jia','居'=>'ju','修'=>'xiu','生'=>'sheng','活'=>'huo',
            '美'=>'mei','学'=>'xue','空'=>'kong','间'=>'jian','流'=>'liu','线'=>'xian',
            '色'=>'se','彩'=>'cai','比'=>'bi','例'=>'li','平'=>'ping','衡'=>'heng',
            '对'=>'dui','称'=>'chen','功'=>'gong','时'=>'shi','尚'=>'shang',
            '北'=>'bei','欧'=>'ou','日'=>'ri','式'=>'shi','现'=>'xian','代'=>'dai',
            '轻'=>'qing','奢'=>'she','极'=>'ji','简'=>'jian','田'=>'tian','园'=>'yuan',
            '工'=>'gong','业'=>'ye','风'=>'feng','格'=>'ge','效'=>'xiao','果'=>'guo',
            '案'=>'an','指'=>'zhi','南'=>'nan','教'=>'jiao','程'=>'cheng',
            '评'=>'ping','测'=>'ce','较'=>'jiao','推'=>'tui','荐'=>'jian',
            '攻'=>'gong','略'=>'lve','选'=>'xuan','购'=>'gou',
            '宁'=>'ning','波'=>'bo','高'=>'gao','层'=>'ceng','住'=>'zhu','宅'=>'zhai',
            // 多字词优先
            '铝合'=>'lvhe','衣柜'=>'yigui','卧室'=>'woshi','客厅'=>'keting','餐厅'=>'canting',
            '书房'=>'shufang','玄关'=>'xuanguan','阳台'=>'yangtai','儿童'=>'ertong',
            '卫浴'=>'weiyu','门窗'=>'menchuang','楼梯'=>'louti','地板'=>'diban',
            '设计'=>'sheji','收纳'=>'shouna','组装'=>'zuzhuang','连接'=>'lianjie',
            '受力'=>'shouli','分析'=>'fenxi','框架'=>'kuangjia','连接件'=>'lianjiejian',
            '铝框'=>'lvkuang','铝型'=>'lvxing','型材'=>'xingcai','材料'=>'cailiao',
            '实木'=>'shimu','定制'=>'dingzhi','家具'=>'jiajv','软装'=>'ruanzhuang',
            '智能'=>'zhineng','照明'=>'zhaoming','家居'=>'jiajv','装修'=>'zhuangxiu',
            '生活'=>'shenghuo','美学'=>'meixue','空间'=>'kongjian','线条'=>'xiantiao',
            '色彩'=>'secai','比例'=>'bili','平衡'=>'pingheng','对称'=>'duichen',
            '功能'=>'gongneng','时尚'=>'shishang','北欧'=>'beiou','日式'=>'rishi',
            '现代'=>'xiandai','轻奢'=>'qingshe','极简'=>'jijian','田园'=>'tianyuan',
            '工业'=>'gongye','风格'=>'fengge','效果'=>'xiaoguo','案例'=>'anli',
            '指南'=>'zhinan','教程'=>'jiaocheng','测评'=>'ceping','比较'=>'bijiao',
            '推荐'=>'tuijian','攻略'=>'gonglue','选购'=>'xuango',
        ];

        $result = '';
        $titleLen = mb_strlen($title, 'utf-8');
        $i = 0;
        while ($i < $titleLen) {
            $char = mb_substr($title, $i, 1, 'utf-8');
            $matched = false;
            foreach ([3, 2] as $n) {
                if ($i + $n <= $titleLen) {
                    $word = mb_substr($title, $i, $n, 'utf-8');
                    if (isset($map[$word]) && $map[$word] !== '') {
                        $result .= $map[$word] . '-';
                        $i += $n;
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) {
                if (isset($map[$char])) {
                    $result .= $map[$char] . '-';
                }
                $i++;
            }
        }

        $result = trim($result, '-');
        // 限制总长度，最多6个拼音节
        if (mb_strlen($result, 'utf-8') > 20) {
            $parts = explode('-', $result);
            $result = implode('-', array_slice($parts, 0, 6));
        }
        return $result ?: 'slug';
    }

    private function buildSlugPrompt(string $text): string
    {
        $kb      = trim(get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        return "请根据以下文章标题或内容生成一个适合WordPress URL的英文或拼音别名（slug），只输出slug本身，不要任何说明，不要空格，不要特殊字符。{$kbBlock}\n{$text}";
    }

    private function buildSummarizePrompt(string $content): string
    {
        $kb      = trim(get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        return "请为以下文章内容生成一段50字以内的摘要，语言精炼准确，直接输出摘要不要其他说明。{$kbBlock}\n" . $content;
    }

    private function buildKeywordsPrompt(string $content): string
    {
        $kb      = trim(get_option('ai_plus_knowledge_base', ''));
        $kbBlock = $kb ? ("\n\n【背景知识】\n" . $kb . "\n") : '';
        return "请为以下文章提取3-5个SEO友好的关键词标签，用逗号分隔。\n规则：\n"
            . "- 每个标签必须是2-6个中文字的短词（如：「AI绘图」「工业设计」「衣柜设计」）\n"
            . "- 标签要简洁，是文章核心主题的单词或词组，不要完整句子\n"
            . "- 不要用品牌名、公司名或超长词组\n"
            . "- 只输出标签，用逗号分隔，不要编号、不要任何解释{$kbBlock}\n文章内容：\n" . $content;
    }

    private function tryFallbackSlug(string $prompt): string
    {
        $apiKeys = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
        $defaultModel = \get_option('ai_plus_default_model', 'minimax');
        $model = $this->createModelInstance($apiKeys, $defaultModel);

        if ($model) {
            $result = $model->completion($prompt, ['max_tokens' => 64, 'temperature' => 0.3]);
            return $result['content'] ?? '';
        }
        return '';
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $slug);
        return strtolower(trim($slug, '-'));
    }

    private function parseTags(string $text): array
    {
        // 直接用 mb_split 分割定界符，trim/preg_replace 均会破坏某些 UTF-8 字符
        $parts = mb_split('[,，、]', $text);
        if (!is_array($parts) || $parts === ['']) {
            return [];
        }
        $tags = array_filter(
            array_map(function ($tag) { return trim(wp_strip_all_tags($tag)); }, $parts),
            function ($tag) {
                $len = mb_strlen($tag, 'utf-8');
                return !empty($tag)
                    && $len >= 3
                    && $len <= 16  // SEO友好标签常见「铝合金衣柜」「小户型收纳」等13-16字词
                    && preg_match('/[\x{4e00}-\x{9fa5}]/u', $tag)
                    && !preg_match('/^[a-zA-Z0-9]+$/', $tag);
            }
        );
        return array_slice(array_values(array_unique($tags)), 0, 4);
    }

    private function resolveTagIds(\WP_REST_Request $request): array
    {
        $tag_ids   = $request->get_param('tag_ids');
        $tag_names = $request->get_param('tag_names');
        $tags_raw  = $request->get_param('tags');

        // 统一转换为标签名称数组（去重，最多4个）
        $tag_names = $this->normalizeTagNames($tag_names, $tags_raw);

        // 名称 → term_id 转换
        $term_ids = [];
        foreach ($tag_names as $name) {
            $name = sanitize_text_field(trim($name));
            if (empty($name)) continue;

            $term = get_term_by('name', $name, 'post_tag');
            if ($term) {
                $term_ids[] = intval($term->term_id);
            } else {
                $new = wp_insert_term($name, 'post_tag');
                if (!is_wp_error($new)) {
                    $term_ids[] = intval($new['term_id']);
                }
            }
        }

        if (!empty($tag_ids) && is_array($tag_ids)) {
            $term_ids = array_merge($term_ids, array_map('intval', $tag_ids));
        }

        return array_unique(array_filter($term_ids));
    }

    /**
     * 将 tag_names 和 tags_raw 统一规范化为标签名称数组
     */
    private function normalizeTagNames($tag_names, $tags_raw): array
    {
        if (!empty($tag_names) && is_array($tag_names)) {
            $parts = [];
            foreach ($tag_names as $raw) {
                $filtered = $this->parseTags(is_string($raw) ? $raw : '');
                $parts = array_merge($parts, $filtered);
            }
            return array_slice(array_unique(array_filter($parts)), 0, 4);
        }

        if (!empty($tags_raw)) {
            if (is_string($tags_raw)) {
                return array_slice(array_unique(array_filter($this->parseTags($tags_raw))), 0, 4);
            }
            $parts = [];
            foreach (array_filter((array) $tags_raw) as $raw) {
                $filtered = $this->parseTags(is_string($raw) ? $raw : '');
                $parts = array_merge($parts, $filtered);
            }
            return array_slice(array_unique(array_filter($parts)), 0, 4);
        }

        return [];
    }

    /**
     * 根据默认模型设置创建模型实例（统一模型选择逻辑）
     */
    private function createModelInstance(array $apiKeys, string $defaultModel): ?object
    {
        $modelConfig = $apiKeys[$defaultModel] ?? null;
        if (!$modelConfig || empty($modelConfig['api_key'])) {
            // 回退：尝试任何已配置的模型
            foreach (['minimax', 'zhipu', 'tongyi', 'deepseek', 'kimi'] as $fallback) {
                $cfg = $apiKeys[$fallback] ?? null;
                $key = is_array($cfg) ? ($cfg['api_key'] ?? '') : (is_string($cfg) ? $cfg : '');
                if ($key) {
                    $defaultModel = $fallback;
                    $modelConfig = is_array($cfg) ? $cfg : ['api_key' => $cfg];
                    break;
                }
            }
            if (empty($modelConfig['api_key'])) return null;
        }

        $apiKey  = is_array($modelConfig) ? ($modelConfig['api_key'] ?? '') : (is_string($modelConfig) ? $modelConfig : '');
        $modelId = is_array($modelConfig) ? ($modelConfig['model'] ?? '') : '';

        switch ($defaultModel) {
            case 'minimax': return new \ZuoAIPlus\Models\MiniMaxModel($apiKey, $modelId ?: 'MiniMax-M2.7-highspeed');
            case 'zhipu':   return new \ZuoAIPlus\Models\ZhipuModel($apiKey, $modelId ?: 'glm-4-flash');
            case 'tongyi':  return new \ZuoAIPlus\Models\TongyiModel($apiKey, $modelId ?: 'qwen-turbo');
            case 'deepseek': return new \ZuoAIPlus\Models\DeepSeekModel($apiKey, $modelId ?: 'deepseek-chat');
            case 'kimi':    return new \ZuoAIPlus\Models\KimiModel($apiKey, $modelId ?: 'kimi-k2.5');
            default:
                $baseUrl = is_array($modelConfig) ? ($modelConfig['base_url'] ?? '') : '';
                if (empty($baseUrl)) return null;
                return new \ZuoAIPlus\Models\CustomModel($apiKey, $modelId, $baseUrl);
        }
    }
}
