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
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // AI 生成摘要
        register_rest_route($this->namespace, '/nav/ai-summary', [
            'methods'  => 'POST',
            'callback' => [$this, 'generateAiSummary'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
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
            'permission_callback' => fn() => current_user_can('manage_options'),
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
        
        // AI 生成 slug（拼音或英文）
        $slug = $this->generateSlug($name, $description);

        $data = [
            'success'     => true,
            'name'        => $name,
            'keywords'    => $keywords,
            'description' => $description,
            'logo'        => $logo,
            'screenshot'  => '',
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
}
