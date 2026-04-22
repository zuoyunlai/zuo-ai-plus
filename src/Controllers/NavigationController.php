<?php
/**
 * 导航网站 REST API 控制器
 * 提供 AI 自动抓取网页信息功能
 */
namespace ZuoAIPlus\Controllers;

if (!defined('ABSPATH')) exit;

class NavigationController extends BaseController
{
    protected $namespace = 'ai-plus/v1';

    public function registerRoutes(): void
    {
        // 抓取网页信息（curl + AI 总结）
        register_rest_route($this->namespace, '/nav/fetch', [
            'methods'  => 'POST',
            'callback' => [$this, 'fetchUrl'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // AI 生成摘要
        register_rest_route($this->namespace, '/nav/ai-summary', [
            'methods'  => 'POST',
            'callback' => [$this, 'generateAiSummary'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // SEO 权重查询（公开访问，结果缓存）
        register_rest_route($this->namespace, '/nav/weight', [
            'methods'  => 'GET',
            'callback' => [$this, 'getSeoWeight'],
            'permission_callback' => '__return_true',
        ]);

        // 点击统计（公开访问）
        register_rest_route($this->namespace, '/nav/click', [
            'methods'  => 'POST',
            'callback' => [$this, 'recordClick'],
            'permission_callback' => '__return_true',
        ]);

        // 获取热门网站（公开访问）
        register_rest_route($this->namespace, '/nav/popular', [
            'methods'  => 'GET',
            'callback' => [$this, 'getPopularSites'],
            'permission_callback' => '__return_true',
        ]);

        // 检查网站状态（公开访问）
        register_rest_route($this->namespace, '/nav/check-status', [
            'methods'  => 'GET',
            'callback' => [$this, 'checkSiteStatus'],
            'permission_callback' => '__return_true',
        ]);

        // 批量检查网站状态（需管理员权限）
        register_rest_route($this->namespace, '/nav/bulk-check-status', [
            'methods'  => 'POST',
            'callback' => [$this, 'bulkCheckStatus'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);

        // 获取网站列表（分页）
        register_rest_route($this->namespace, '/nav/sites', [
            'methods'  => 'GET',
            'callback' => [$this, 'getSites'],
            'permission_callback' => '__return_true',
        ]);

        // 提交评分
        register_rest_route($this->namespace, '/nav/rate', [
            'methods'  => 'POST',
            'callback' => [$this, 'submitRating'],
            'permission_callback' => '__return_true',
        ]);

        // 获取评分
        register_rest_route($this->namespace, '/nav/rating', [
            'methods'  => 'GET',
            'callback' => [$this, 'getRating'],
            'permission_callback' => '__return_true',
        ]);

        // 下载远程图片到媒体库
        register_rest_route($this->namespace, '/nav/download-image', [
            'methods'  => 'POST',
            'callback' => [$this, 'downloadImage'],
            'permission_callback' => function() { return current_user_can('upload_files'); },
        ]);

        // 使用 Chrome Headless 截取网站截图
        register_rest_route($this->namespace, '/nav/screenshot', [
            'methods'  => 'POST',
            'callback' => [$this, 'takeScreenshot'],
            'permission_callback' => function() { return current_user_can('upload_files'); },
        ]);

        // AI 生成导航标签
        register_rest_route($this->namespace, '/nav/ai-tags', [
            'methods'  => 'POST',
            'callback' => [$this, 'generateAiTags'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/fetch
     * Body: { url: "https://..." }
     * 返回: { name, keywords, description, logo, screenshot }
     */
    public function fetchUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = esc_url_raw($request->get_param('url'));
        if (!$url) {
            return $this->error('URL不能为空');
        }

        $result = $this->curlFetch($url);
        if (!$result['success']) {
            return $this->error($result['message'] ?? '抓取失败');
        }
        unset($result['success']);
        return $this->success($result);
    }

    /**
     * POST /ai-plus/v1/nav/ai-summary
     * Body: { url, name, description }
     * 用 AI 生成一句话摘要
     */
    public function generateAiSummary(\WP_REST_Request $request): \WP_REST_Response
    {
        $url         = esc_url_raw($request->get_param('url'));
        $name        = sanitize_text_field($request->get_param('name'));
        $description = sanitize_text_field($request->get_param('description'));

        // 使用新的长内容生成函数（300-500字）
        $content = $this->generateAiContent($name, $url, $description);
        return $this->success(['content' => $content]);
    }

    private function curlFetch(string $url): array
    {
        $response = wp_remote_get($url, [
            'timeout'   => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            'sslverify' => false,
            'headers'  => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => '抓取失败: ' . $response->get_error_message()];
        }

        $html    = wp_remote_retrieve_body($response);
        $httpCode = wp_remote_retrieve_response_code($response);

        if (!$html || $httpCode !== 200) {
            $friendlyMsg = match ($httpCode) {
                403 => '目标网站拒绝访问（403），可能禁止抓取',
                404 => '目标网页不存在（404），请检查网址是否正确',
                500, 502, 503 => '目标服务器暂时不可用（{$httpCode}），请稍后重试',
                default => "HTTP {$httpCode}",
            };
            return ['success' => false, 'message' => $friendlyMsg];
        }

        // 编码处理 - 优先从 HTTP 响应头获取
        $encoding = '';
        $contentType = wp_remote_retrieve_header($response, 'content-type');
        if ($contentType && preg_match('/charset=([^\s;]+)/i', $contentType, $m)) {
            $encoding = trim($m[1], '"\'');
        }
        // 从 HTML meta 标签获取（备用）
        if (!$encoding) {
            if (preg_match('/<meta[^>]*charset=["\']?([^"\'\s>]+)/i', $html, $m)) {
                $encoding = str_replace(['"', "'"], '', $m[1]);
            } elseif (preg_match('/<meta[^>]*charset=([^\s>]+)/i', $html, $m)) {
                $encoding = trim($m[1]);
            }
        }
        // 转换编码
        if ($encoding && strcasecmp($encoding, 'utf-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $encoding);
            if ($converted !== false) {
                $html = $converted;
            }
        }

        $name        = $this->extractTitle($html);
        $description = $this->extractMeta($html, 'description');
        $keywords    = $this->extractMeta($html, 'keywords');
        $logo        = $this->extractLogo($html, $url);
        $screenshot  = $this->extractScreenshot($html, $url);

        // AI 生成 slug（拼音或英文）
        $slug = $this->generateSlug($name, $description);

        $data = [
            'success'     => true,
            'name'        => $name,
            'keywords'    => $keywords,
            'description' => $description,
            'logo'        => $logo,
            'screenshot'  => $screenshot,
            'slug'        => $slug,
        ];

        // 清理 HTML 实体
        foreach (['name', 'keywords', 'description'] as $k) {
            $data[$k] = html_entity_decode(trim($data[$k]), ENT_QUOTES, 'UTF-8');
        }

        return $data;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim($m[1]);
        }
        // og:title
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function extractMeta(string $html, string $name): string
    {
        // 标准 meta
        $pattern = '/<meta[^>]+(?:name|property)=["\']' . $name . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';
        if (preg_match($pattern, $html, $m)) {
            return trim($m[1]);
        }
        // 反序
        $pattern2 = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:name|property)=["\']' . $name . '["\'][^>]*>/i';
        if (preg_match($pattern2, $html, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function extractLogo(string $html, string $url): string
    {
        $base = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

        // og:image
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $this->makeAbsolute(trim($m[1]), $base);
        }
        // link icon
        $iconPatterns = [
            '/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:shortcut )?icon["\']/i',
            '/<link[^>]+rel=["\']apple-touch-icon["\'][^>]+href=["\']([^"\']+)["\']/i',
        ];
        foreach ($iconPatterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return $this->makeAbsolute(trim($m[1]), $base);
            }
        }
        return '';
    }

    /**
     * 提取网站截图 URL
     * 优先使用 og:image（网站首页快照），否则用 thum.io 在线截图服务
     */
    private function extractScreenshot(string $html, string $url): string
    {
        $base = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

        // 1. 优先使用 og:image（网站首页快照）
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $img = $this->makeAbsolute(trim($m[1]), $base);
            // 过滤掉明显的 logo/favicon URL
            if ($img && !preg_match('/(logo|favicon|icon|apple-touch|avatar|brand)/i', $img)) {
                return $img;
            }
        }

        // 2. 用本地 Chrome Headless 截图，保存到 uploads 目录
        $localUrl = $this->takeLocalScreenshot($url);
        if ($localUrl) {
            return $localUrl;
        }

        // 3. fallback: thum.io（加 allowJPG 提高成功率）
        $host = parse_url($url, PHP_URL_HOST);
        return 'https://image.thum.io/allowJPG/wait/3/getwidth/800/' . $host;
    }

    /**
     * 用本地 Chrome Headless 截图并保存到 WordPress uploads
     */
    private function takeLocalScreenshot(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return '';

        $uploadDir = wp_upload_dir();
        $navDir = $uploadDir['basedir'] . '/nav-screenshots';
        if (!is_dir($navDir)) {
            mkdir($navDir, 0755, true);
        }

        $filename = sanitize_file_name($host . '.png');
        $filepath = $navDir . '/' . $filename;

        // 已存在且不超过7天，直接返回
        if (file_exists($filepath) && (time() - filemtime($filepath)) < 7 * DAY_IN_SECONDS) {
            return $uploadDir['baseurl'] . '/nav-screenshots/' . $filename;
        }

        // 用 Chrome Headless 截图
        $chrome = '/usr/bin/google-chrome';
        if (!file_exists($chrome)) return '';

        $cmd = escapeshellcmd($chrome)
            . ' --headless --disable-gpu --no-sandbox'
            . ' --screenshot=' . escapeshellarg($filepath)
            . ' --window-size=1280,800'
            . ' --default-background-color=0xFFFFFFFF'
            . ' ' . escapeshellarg($url)
            . ' 2>/dev/null';

        exec($cmd, $output, $retcode);

        // Chrome 的 --screenshot 默认保存为 screenshot.png，需要检查
        // 如果原文件没生成，检查当前目录的 screenshot.png
        if (!file_exists($filepath)) {
            $fallback = ABSPATH . 'screenshot.png';
            if (file_exists($fallback)) {
                rename($fallback, $filepath);
            }
        }

        if (!file_exists($filepath) || filesize($filepath) < 1000) {
            @unlink($filepath);
            return ''; // 截图失败
        }

        return $uploadDir['baseurl'] . '/nav-screenshots/' . $filename;
    }

    private function makeAbsolute(string $url, string $base): string
    {
        if (!$url) return '';
        if (strpos($url, 'http') === 0) return $url;
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (strpos($url, '/') === 0) return $base . $url;
        return $base . '/' . $url;
    }

    // AI 生成 slug
    private function generateSlug(string $name, string $description): string
    {
        if (!$name) return '';
        
        $prompt = "请为网站「{$name}」生成一个简短易懂的英文或拼音 slug（不超过30个字符，只能包含字母、数字、连字符）。
网站描述：{$description}

直接返回 slug，不要其他内容。例如：baidu-search, toutiao-news, amazon-shop"
        ;
        
        $result = $this->callAi($prompt);
        $result = sanitize_title($result);
        
        // 确保不为空
        return $result ?: sanitize_title($name);
    }

    private function callAi(string $prompt): string
    {
        // 获取智谱 API Key
        $apiKeys = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
        $zk = $apiKeys['zhipu'] ?? null;
        $zhipuKey = is_array($zk) ? ($zk['api_key'] ?? '') : (is_string($zk) ? $zk : '');
        
        if (!$zhipuKey) {
            // 尝试其他模型
            $deepseekKey = $apiKeys['deepseek']['api_key'] ?? '';
            if ($deepseekKey) {
                $model = new \ZuoAIPlus\Models\DeepSeekModel($deepseekKey);
            } else {
                return '';
            }
        } else {
            $model = new \ZuoAIPlus\Models\ZhipuModel($zhipuKey, 'glm-4-flash');
        }

        try {
            $resp = $model->chat([
                ['role' => 'user', 'content' => $prompt]
            ]);
            return trim($resp['content'] ?? '');
        } catch (\Throwable $e) {
            // 记录 AI 调用错误
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Plus Nav - AI call failed: ' . $e->getMessage());
            }
            return '';
        }
    }

    // AI 生成简介（300-500字）
    private function generateAiContent(string $name, string $url, string $description): string
    {
        if (!$name) return '';
        
        $prompt = "你是一个网站内容分析师。请根据以下信息，写一篇300-500字的网站详细介绍：
网站名称：{$name}
网址：{$url}
网站描述：{$description}

要求：
1. 300-500字（中文字符）
2. 介绍网站的主要功能、特点、优势
3. 语言通顺，结构清晰
4. 必须分段输出，每段之间用空行分隔
5. 编号列表项每项独占一行
6. 直接返回文章，不要开头结尾的任何说明文字"
        ;
        
        $result = $this->callAi($prompt);
        return trim($result);
    }

    /**
     * GET /ai-plus/v1/nav/weight?domain=example.com
     * 代理 5118 权重查询，缓存 24 小时
     */
    public function getSeoWeight(\WP_REST_Request $request): \WP_REST_Response
    {
        $domain = sanitize_text_field($request->get_param('domain') ?? '');
        if (!$domain) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 domain 参数'], 400);
        }

        // 从 URL 提取域名
        if (preg_match('#https?://([^/]+)#i', $domain, $m)) {
            $domain = $m[1];
        }

        $apiKey = get_option('ai_plus_5118_apikey', '');
        if (!$apiKey) {
            return new \WP_REST_Response(['success' => false, 'message' => '未配置 5118 API Key'], 400);
        }

        // 缓存 24 小时
        $cacheKey = 'ai_plus_nav_weight_' . md5($domain);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return new \WP_REST_Response(['success' => true, 'data' => $cached, 'cached' => true]);
        }

        // 调用 5118 API
        $response = wp_remote_post('https://apis.5118.com/weight', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'body' => 'url=' . urlencode($domain),
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response(['success' => false, 'message' => '请求失败: ' . $response->get_error_message()], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body || ($body['errcode'] ?? '') !== '0') {
            $msg = $body['errmsg'] ?? '未知错误';
            return new \WP_REST_Response(['success' => false, 'message' => '5118: ' . $msg], 500);
        }

        // 解析权重数据
        $result = $body['data']['result'] ?? [];
        $weights = [];
        $map = [
            'BaiduPCWeight'    => 'baidu',
            'BaiduMobileWeight'=> 'baidu_m',
            'HaoSouWeight'     => '360',
            'SMWeight'         => 'sogou',
            'TouTiaoWeight'    => 'toutiao',
        ];

        foreach ($result as $item) {
            $type = $item['type'] ?? '';
            $w = intval($item['weight'] ?? 0);
            if (isset($map[$type])) {
                $weights[$map[$type]] = $w;
            }
        }

        $output = ['weights' => $weights, 'raw' => $result];

        // 缓存结果
        set_transient($cacheKey, $output, DAY_IN_SECONDS);

        return new \WP_REST_Response(['success' => true, 'data' => $output, 'cached' => false]);
    }

    /**
     * POST /ai-plus/v1/nav/click
     * 记录网站点击
     */
    public function recordClick(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = intval($request->get_param('post_id'));
        if (!$postId) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 post_id'], 400);
        }

        // 速率限制：同一IP对同一文章每分钟最多1次点击
        $clientIp = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rateKey = 'nav_rate_' . md5($clientIp . '_' . $postId);
        if (get_transient($rateKey)) {
            return new \WP_REST_Response(['success' => true, 'clicks' => (int) get_post_meta($postId, 'nav_clicks', true), 'throttled' => true], 200);
        }
        set_transient($rateKey, 1, 60); // 60秒冷却

        // 获取当前点击数
        $clicks = (int) get_post_meta($postId, 'nav_clicks', true);
        $clicks++;
        update_post_meta($postId, 'nav_clicks', $clicks);

        // 记录点击日志（用于分析）
        $today = date('Y-m-d');
        $clickLog = get_post_meta($postId, 'nav_click_log', true);
        if (!is_array($clickLog)) {
            $clickLog = [];
        }
        $clickLog[$today] = ($clickLog[$today] ?? 0) + 1;
        update_post_meta($postId, 'nav_click_log', $clickLog);

        return new \WP_REST_Response(['success' => true, 'clicks' => $clicks]);
    }

    /**
     * GET /ai-plus/v1/nav/popular
     * 获取热门网站
     */
    public function getPopularSites(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = intval($request->get_param('limit') ?? 10);
        $cacheKey = 'ai_plus_nav_popular_' . $limit;
        
        // 缓存 1 小时
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return new \WP_REST_Response(['success' => true, 'data' => $cached, 'cached' => true]);
        }

        $sites = [];
        $query = new \WP_Query([
            'post_type'      => 'nav_site',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => 'nav_clicks',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);

        // 预加载所有 post meta，避免 N+1 查询
        if ($query->have_posts()) {
            update_postmeta_cache($query->posts);
            update_object_term_cache($query->posts, 'nav_site');
        }

        while ($query->have_posts()) {
            $query->the_post();
            $postId = get_the_ID();
            // 直接使用 get_post_meta 单字段查询（已缓存）
            $sites[] = [
                'id'     => $postId,
                'title'  => get_the_title(),
                'url'    => get_post_meta($postId, 'nav_url', true),
                'logo'   => get_post_meta($postId, 'nav_logo', true),
                'desc'   => get_post_meta($postId, 'nav_description', true),
                'clicks' => (int) get_post_meta($postId, 'nav_clicks', true),
                'link'   => get_permalink(),
            ];
        }
        wp_reset_postdata();

        // 如果没有点击数据，返回最新添加的
        if (empty($sites)) {
            $query = new \WP_Query([
                'post_type'      => 'nav_site',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            while ($query->have_posts()) {
                $query->the_post();
                $meta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
                $sites[] = [
                    'id'     => get_the_ID(),
                    'title'  => get_the_title(),
                    'url'    => $meta['url'],
                    'logo'   => $meta['logo'],
                    'desc'   => $meta['description'],
                    'clicks' => 0,
                    'link'   => get_permalink(),
                ];
            }
            wp_reset_postdata();
        }

        set_transient($cacheKey, $sites, HOUR_IN_SECONDS);

        // 同时清理旧缓存键（兼容性）
        delete_transient('nav_popular_sites_' . $limit);

        return new \WP_REST_Response(['success' => true, 'data' => $sites, 'cached' => false]);
    }

    /**
     * GET /ai-plus/v1/nav/check-status
     * 检查网站状态
     */
    public function checkSiteStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = intval($request->get_param('post_id'));
        if (!$postId) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 post_id'], 400);
        }

        $url = get_post_meta($postId, 'nav_url', true);
        if (!$url) {
            return new \WP_REST_Response(['success' => false, 'message' => '无网址'], 400);
        }

        // 检查缓存
        $cacheKey = 'ai_plus_nav_status_' . $postId;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return new \WP_REST_Response(['success' => true, 'data' => $cached, 'cached' => true]);
        }

        // 检测网站状态
        $response = wp_remote_head($url, [
            'timeout'   => 10,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $status = [
            'url'       => $url,
            'checked_at' => date('Y-m-d H:i:s'),
            'is_online' => false,
            'http_code' => 0,
            'message'   => '',
        ];

        if (is_wp_error($response)) {
            $status['message'] = $response->get_error_message();
        } else {
            $httpCode = wp_remote_retrieve_response_code($response);
            $status['http_code'] = $httpCode;
            $status['is_online'] = ($httpCode >= 200 && $httpCode < 400);
            $status['message'] = $status['is_online'] ? '正常' : "HTTP $httpCode";
        }

        // 保存状态
        update_post_meta($postId, 'nav_status_check', $status);
        set_transient($cacheKey, $status, 6 * HOUR_IN_SECONDS); // 缓存6小时

        // 同时清理旧缓存键（兼容性）
        delete_transient('nav_status_' . $postId);

        return new \WP_REST_Response(['success' => true, 'data' => $status, 'cached' => false]);
    }

    /**
     * GET /ai-plus/v1/nav/sites
     * 分页获取网站列表
     */
    public function getSites(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = intval($request->get_param('page') ?? 1);
        $perPage = intval($request->get_param('per_page') ?? 20);
        $category = intval($request->get_param('category') ?? 0);
        
        $perPage = min($perPage, 50); // 最多50个
        
        $args = [
            'post_type'      => 'nav_site',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'nav_clicks',
            'order'          => 'DESC',
        ];
        
        if ($category > 0) {
            $args['tax_query'] = [[
                'taxonomy' => 'nav_category',
                'field'    => 'term_id',
                'terms'    => $category,
            ]];
        }
        
        $query = new \WP_Query($args);
        $sites = [];
        
        while ($query->have_posts()) {
            $query->the_post();
            $meta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
            $sites[] = [
                'id'       => get_the_ID(),
                'title'    => get_the_title(),
                'url'      => $meta['url'],
                'logo'     => $meta['logo'],
                'desc'     => $meta['description'],
                'keywords' => $meta['keywords'],
                'clicks'   => (int) get_post_meta(get_the_ID(), 'nav_clicks', true),
                'link'     => get_permalink(),
            ];
        }
        wp_reset_postdata();
        
        return new \WP_REST_Response([
            'success'      => true,
            'data'         => $sites,
            'total'        => $query->found_posts,
            'total_pages'  => $query->max_num_pages,
            'current_page' => $page,
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/rate
     * 提交评分
     */
    public function submitRating(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = intval($request->get_param('post_id'));
        $rating = intval($request->get_param('rating'));
        $visitorId = sanitize_text_field($request->get_param('visitor_id') ?? '');

        // 获取客户端 IP 和 User-Agent 用于防刷
        $ip = $this->getClientIp();
        $ua = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // 生成更可靠的标识（IP + UA + visitorId）
        $uniqueId = md5($ip . '|' . $ua . '|' . $visitorId);

        if (!$postId || $rating < 1 || $rating > 5) {
            return new \WP_REST_Response(['success' => false, 'message' => '参数错误'], 400);
        }

        // 检查是否已评分（使用更可靠的标识：IP + UA + visitorId）
        $ratedKey = 'nav_rated_' . $uniqueId;
        $alreadyRated = get_post_meta($postId, $ratedKey, true);
        if ($alreadyRated) {
            return new \WP_REST_Response(['success' => false, 'message' => '您已评分'], 403);
        }

        // 检查 IP 评分频率限制（每 IP 每小时最多评分 5 次不同网站）
        $ipKey = 'ai_plus_nav_rate_limit_' . md5($ip);
        $ipCount = get_transient($ipKey);
        if ($ipCount === false) {
            $ipCount = 0;
        }
        if ($ipCount >= 5) {
            return new \WP_REST_Response(['success' => false, 'message' => '评分过于频繁，请稍后再试'], 429);
        }

        // 获取当前评分数据
        $ratings = get_post_meta($postId, 'nav_ratings', true);
        if (!is_array($ratings)) {
            $ratings = ['count' => 0, 'total' => 0, 'avg' => 0];
        }

        // 更新评分
        $ratings['count']++;
        $ratings['total'] += $rating;
        $ratings['avg'] = round($ratings['total'] / $ratings['count'], 1);

        // 保存
        update_post_meta($postId, 'nav_ratings', $ratings);
        update_post_meta($postId, $ratedKey, $rating);

        // 更新 IP 评分计数（1小时过期）
        set_transient($ipKey, $ipCount + 1, HOUR_IN_SECONDS);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $ratings,
        ]);
    }

    /**
     * 获取客户端真实 IP
     */
    private function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * GET /ai-plus/v1/nav/rating
     * 获取评分
     */
    public function getRating(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = intval($request->get_param('post_id'));
        $visitorId = sanitize_text_field($request->get_param('visitor_id') ?? '');

        if (!$postId) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 post_id'], 400);
        }

        $ratings = get_post_meta($postId, 'nav_ratings', true);
        if (!is_array($ratings)) {
            $ratings = ['count' => 0, 'total' => 0, 'avg' => 0];
        }

        // 检查当前用户是否已评分
        $ratedKey = 'nav_rated_' . md5($visitorId);
        $userRating = get_post_meta($postId, $ratedKey, true);

        return new \WP_REST_Response([
            'success'     => true,
            'data'        => $ratings,
            'user_rated'  => !empty($userRating),
            'user_rating' => intval($userRating),
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/bulk-check-status
     * 批量检查网站状态（管理员）
     */
    public function bulkCheckStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response(['success' => false, 'message' => '无权访问'], 403);
        }

        $limit = intval($request->get_param('limit') ?? 10);
        $limit = min($limit, 50); // 最多50个

        // 获取需要检查的网站（超过6小时未检查的）
        $sites = get_posts([
            'post_type'      => 'nav_site',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'nav_status_check',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'nav_status_check',
                    'value'   => time() - 6 * HOUR_IN_SECONDS,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        $results = [];
        foreach ($sites as $site) {
            $url = get_post_meta($site->ID, 'nav_url', true);
            if (!$url) continue;

            $response = wp_remote_head($url, [
                'timeout'   => 10,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            $isOnline = false;
            $httpCode = 0;

            if (!is_wp_error($response)) {
                $httpCode = wp_remote_retrieve_response_code($response);
                $isOnline = ($httpCode >= 200 && $httpCode < 400);
            }

            $status = [
                'url'        => $url,
                'checked_at' => date('Y-m-d H:i:s'),
                'is_online'  => $isOnline,
                'http_code'  => $httpCode,
                'message'    => $isOnline ? '正常' : (is_wp_error($response) ? $response->get_error_message() : "HTTP $httpCode"),
            ];

            update_post_meta($site->ID, 'nav_status_check', $status);
            set_transient('nav_status_' . $site->ID, $status, 6 * HOUR_IN_SECONDS);

            $results[] = [
                'id'       => $site->ID,
                'title'    => $site->post_title,
                'status'   => $status,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'checked' => count($results),
            'data'    => $results,
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/download-image
     * Body: { image_url: "https://...", filename?: "xxx.png" }
     * 下载远程图片到媒体库，返回 attachment_id 和 URL
     */
    public function downloadImage(\WP_REST_Request $request): \WP_REST_Response
    {
        // 加载 WordPress admin 文件（REST API 上下文不会自动加载）
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $imageUrl = esc_url_raw($request->get_param('image_url'));
        if (!$imageUrl) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少图片地址'], 400);
        }

        // 下载到临时目录
        $tmp = download_url($imageUrl, 30);
        if (is_wp_error($tmp)) {
            return new \WP_REST_Response(['success' => false, 'message' => '下载失败：' . $tmp->get_error_message()], 500);
        }

        $filename = sanitize_file_name($request->get_param('filename') ?: basename(parse_url($imageUrl, PHP_URL_PATH)));
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $filename)) {
            $ext = 'png';
            $filename .= '.' . $ext;
        }

        $fileArr = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        // 插入媒体库
        $attId = media_handle_sideload($fileArr, 0, '', [
            'post_status' => 'inherit',
        ]);

        if (is_wp_error($attId)) {
            @unlink($tmp);
            return new \WP_REST_Response(['success' => false, 'message' => '保存失败：' . $attId->get_error_message()], 500);
        }

        $attachment = wp_get_attachment_image_src($attId, 'full');
        $imageUrl2  = $attachment ? $attachment[0] : wp_get_attachment_url($attId);

        return new \WP_REST_Response([
            'success'       => true,
            'attachment_id' => $attId,
            'url'          => $imageUrl2,
            'filename'      => $filename,
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/screenshot
     * Body: { url: "https://..." }
     * 使用 Chrome Headless 截取网站截图，保存到媒体库
     */
    public function takeScreenshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = esc_url_raw($request->get_param('url'));
        if (!$url) {
            return new \WP_REST_Response(['success' => false, 'message' => 'URL无效'], 400);
        }

        $chrome = '/usr/bin/google-chrome';
        if (!file_exists($chrome)) {
            $chrome = 'google-chrome';
        }

        // 创建目录
        $upload_dir = wp_upload_dir();
        $shot_dir = $upload_dir['basedir'] . '/nav-screenshots';
        if (!file_exists($shot_dir)) {
            wp_mkdir_p($shot_dir);
        }

        $hash = md5($url . time());
        $filename = 'screenshot-' . $hash . '.png';
        $filepath = $shot_dir . '/' . $filename;
        $tmp_file = '/tmp/chrome-shot-' . $hash . '.png';
        $user_data_dir = '/tmp/chrome-ud-' . $hash;

        // Chrome Headless 截图命令
        $cmd = sprintf(
            '%s --headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage ' .
            '--single-process --screenshot=%s --window-size=1280,800 ' .
            '--user-data-dir=%s %s 2>/dev/null',
            escapeshellcmd($chrome),
            escapeshellarg($tmp_file),
            escapeshellarg($user_data_dir),
            escapeshellarg($url)
        );

        @exec('rm -rf ' . escapeshellarg($user_data_dir));
        $output = [];
        $return_code = 0;
        exec($cmd, $output, $return_code);
        @exec('rm -rf ' . escapeshellarg($user_data_dir));

        if ($return_code !== 0 || !file_exists($tmp_file)) {
            return new \WP_REST_Response(['success' => false, 'message' => '截图失败，Chrome返回码: ' . $return_code], 500);
        }

        if (!rename($tmp_file, $filepath)) {
            @unlink($tmp_file);
            return new \WP_REST_Response(['success' => false, 'message' => '文件保存失败'], 500);
        }

        // 注册为 WordPress 附件
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $att_id = wp_insert_attachment([
            'post_title'     => '网站截图 - ' . parse_url($url, PHP_URL_HOST),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image/png',
        ], $filepath);

        if (!is_wp_error($att_id) && $att_id > 0) {
            wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $filepath));
        }

        $file_url = $att_id > 0 ? wp_get_attachment_url($att_id) : $upload_dir['baseurl'] . '/nav-screenshots/' . $filename;

        return new \WP_REST_Response([
            'success'       => true,
            'file_url'      => $file_url,
            'attachment_id' => $att_id > 0 ? (int) $att_id : null,
            'width'        => 1280,
            'height'       => 800,
        ]);
    }

    /**
     * POST /ai-plus/v1/nav/ai-tags
     * Body: { post_id, name, url?, description? }
     * 调用 AI 根据网站名称/URL/描述生成导航标签，并直接写入 post
     */
    public function generateAiTags(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $name   = sanitize_text_field($request->get_param('name') ?? '');
        $url    = esc_url_raw($request->get_param('url') ?? '');
        $desc   = sanitize_text_field($request->get_param('description') ?? '');

        if (!$name) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少网站名称'], 400);
        }

        // 有 post_id 时验证文章类型，没有时仅返回标签不写入
        $hasPost = false;
        if ($postId) {
            $post = get_post($postId);
            if ($post && $post->post_type === 'nav_site') {
                $hasPost = true;
            }
        }

        $prompt = "你是一个标签生成工具。根据网站信息直接输出3-8个标签，用逗号分隔，不要任何解释、编号、前缀或思考过程。\n"
            . "网站名称：{$name}\n"
            . "网站URL：{$url}\n"
            . "网站描述：{$desc}\n"
            . "输出示例：AI工具,在线写作,办公效率\n"
            . "直接输出标签：";

        $chatMessages = [
            ['role' => 'system', 'content' => '你是一个专业的网站标签生成助手，根据网站信息生成简洁准确的标签列表，直接输出标签列表，不要任何解释。'],
            ['role' => 'user', 'content' => $prompt],
        ];

        // 使用插件现有的加密 API Key + Model 类
        $apiKeys = \ZuoAIPlus\Utils\Crypto::decryptApiKeys((array)\get_option('ai_plus_api_keys', []));
        $defaultModel = \get_option('ai_plus_default_model', 'minimax');
        $modelConfig = $apiKeys[$defaultModel] ?? null;
        if (!$modelConfig || empty($modelConfig['api_key'])) {
            return new \WP_REST_Response(['success' => false, 'message' => '未配置 AI API Key'], 400);
        }

        // 根据默认模型选择对应的 Model 类
        $modelInstance = null;
        switch ($defaultModel) {
            case 'minimax':
                $modelInstance = new \ZuoAIPlus\Models\MiniMaxModel($modelConfig['api_key'], $modelConfig['model'] ?? 'MiniMax-M2.7-highspeed');
                break;
            case 'zhipu':
                $modelInstance = new \ZuoAIPlus\Models\ZhipuModel($modelConfig['api_key'], $modelConfig['model'] ?? 'glm-4-flash');
                break;
            case 'deepseek':
                $modelInstance = new \ZuoAIPlus\Models\DeepSeekModel($modelConfig['api_key'], $modelConfig['model'] ?? 'deepseek-chat');
                break;
            case 'kimi':
                $modelInstance = new \ZuoAIPlus\Models\KimiModel($modelConfig['api_key'], $modelConfig['model'] ?? 'kimi-k2.5');
                break;
            default:
                // custom 或其他：使用 DeepSeekModel（兼容 OpenAI 格式）
                $baseUrl = $modelConfig['base_url'] ?? 'https://api.deepseek.com/v1';
                $modelInstance = new \ZuoAIPlus\Models\CustomModel($modelConfig['api_key'], $modelConfig['model'] ?? 'deepseek-chat', $baseUrl);
                break;
        }

        if (!$modelInstance) {
            return new \WP_REST_Response(['success' => false, 'message' => '无法初始化 AI 模型'], 500);
        }

        try {
            $result = $modelInstance->chat($chatMessages, ['max_tokens' => 1024]);
            $content = $result['content'] ?? '';
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => 'AI 请求失败：' . $e->getMessage()], 500);
        }

        $content = trim($content);
        $content = trim(preg_replace('/^```(?:\w+)?\s*/', '', $content));
        $content = trim(preg_replace('/\s*```$/', '', $content));
        // 去掉 AI 可能返回的前缀说明（如"标签："、"可能的标签："等）
        $content = preg_replace('/^[^\n]*标签[：:][^\n]*\n/u', '', $content);
        $content = preg_replace('/^.*?(?:根据|以下|这些|提取|生成|需要|可能的)[^\n]*\n/u', '', $content);
        // 只取第一行（AI 有时返回多行解释+标签列表，第一行纯标签最可靠）
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        // 找到最长的一行（通常是完整标签列表）
        $bestLine = '';
        foreach ($lines as $line) {
            if (mb_strlen($line, 'utf-8') > mb_strlen($bestLine, 'utf-8') && preg_match('/[、，,]/u', $line)) {
                $bestLine = $line;
            }
        }
        $content = $bestLine ?: ($lines[0] ?? $content);

        if (!$content) {
            return new \WP_REST_Response(['success' => false, 'message' => 'AI 未返回内容'], 500);
        }

        $tagsRaw = preg_replace('/[、，,\s]+/u', ',', $content);
        // 去掉编号前缀（1. 2. 等）
        $tagsRaw = preg_replace('/\d+[.、)）]\s*/u', ',', $tagsRaw);
        $tags = array_filter(array_map('trim', explode(',', $tagsRaw)), function ($t) {
            $t = trim($t);
            // 过滤：太短(<2字)、太长(>8字)、纯数字、含标点/解释性词
            if (mb_strlen($t, 'utf-8') < 2 || mb_strlen($t, 'utf-8') > 8) return false;
            if (preg_match('/^\d+$/', $t)) return false;
            if (preg_match('/[.。！？：:；;、，,\n\r]/u', $t)) return false;
            if (preg_match('/^(提供|根据|需要|可能|具体|涵盖|以下|这些|核心|功能|类型|维度|平台|行业|工具|辅助)$/u', $t)) return false;
            return true;
        });
        $tags = array_unique(array_values($tags));

        if (empty($tags)) {
            return new \WP_REST_Response(['success' => false, 'message' => '未能解析出有效标签'], 500);
        }

        $termIds = [];
        foreach ($tags as $tagName) {
            $term = get_term_by('name', $tagName, 'nav_tag');
            if ($term) {
                $termIds[] = (int) $term->term_id;
            } else {
                $new = wp_insert_term($tagName, 'nav_tag');
                if (!is_wp_error($new)) {
                    $termIds[] = (int) $new['term_id'];
                }
            }
        }

        if ($hasPost) {
            wp_set_object_terms($postId, $termIds, 'nav_tag');
        }

        return new \WP_REST_Response([
            'success'   => true,
            'tags'      => $tags,
            'term_ids'  => $termIds,
            'saved'     => $hasPost,
        ]);
    }
}