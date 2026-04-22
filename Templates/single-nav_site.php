<?php
/**
 * 导航网站详情页 (single-nav_site.php)
 * zhuoer 风格
 */
if (!defined('ABSPATH')) exit;

get_header();

$meta  = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
$name  = $meta['name'] ?: get_the_title();
$url   = $meta['url'] ?: '#';
$desc  = $meta['description'] ?: '';
$kw    = $meta['keywords'] ?: '';
$logo  = $meta['logo'] ?: '';
// 优先使用特色图，否则用 meta 截图
$thumbId = get_post_thumbnail_id(get_the_ID());
$sshot = $thumbId ? wp_get_attachment_url($thumbId) : ($meta['screenshot'] ?: '');
$sshotAlt = $thumbId ? get_post_meta($thumbId, '_wp_attachment_image_alt', true) : (esc_attr($name) . ' 网站截图');
$aiSum = $meta['ai_summary'] ?: '';
$tags  = get_the_terms(get_the_ID(), 'nav_tag');
$cats  = get_the_terms(get_the_ID(), 'nav_category');

// 浏览量
$views = (int) $meta['views'] + 1;
update_post_meta(get_the_ID(), 'nav_views', $views);

// 评分数据（取真实值，无评分则不输出 AggregateRating）
$ratings = get_post_meta(get_the_ID(), 'nav_ratings', true);
$ratingCount = is_array($ratings) ? intval($ratings['count'] ?? 0) : 0;
$ratingAvg   = is_array($ratings) ? floatval($ratings['avg'] ?? 0) : 0;

// 构建面包屑 Schema 数据
$bcItems = [
    ['name' => '首页', 'url' => home_url()],
    ['name' => '网址导航', 'url' => get_post_type_archive_link('nav_site')],
];
if ($cats && !is_wp_error($cats) && !empty($cats)) {
    $firstCat = $cats[0];
    if ($firstCat->parent) {
        $grandparent = get_term($firstCat->parent, 'nav_category');
        if ($grandparent && !is_wp_error($grandparent)) {
            $bcItems[] = ['name' => $grandparent->name, 'url' => get_term_link($grandparent)];
        }
    }
    $bcItems[] = ['name' => $firstCat->name, 'url' => get_term_link($firstCat)];
}
$bcItems[] = ['name' => $name, 'url' => get_permalink()];
?>

<nav class="nav-breadcrumb">
    <?php foreach ($bcItems as $i => $item): ?>
    <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
    <?php if ($i === count($bcItems) - 1): ?>
    <span class="current"><?php echo esc_html($item['name']); ?></span>
    <?php else: ?>
    <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
    <?php endif; ?>
    <?php endforeach; ?>
</nav>

<main class="nav-single-wrap">
    <!-- 头部区域 -->
    <div class="nav-single-header">
        <div class="nav-single-header-inner">
            <div class="nav-single-logo">
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
                <?php else: ?>
                    <div class="nav-single-logo-letter"><?php echo esc_html(mb_substr($name, 0, 1, 'UTF-8')); ?></div>
                <?php endif; ?>
            </div>

            <div class="nav-single-main">
                <h1 class="nav-single-name">
                    <?php echo esc_html($name); ?>
                    <?php if ($meta['status'] === 'featured'): ?>
                    <span class="nav-single-badge">⭐ 推荐</span>
                    <?php endif; ?>
                </h1>
                <div class="seo-weight-row" id="seo-weight-content">
                    <span style="color:var(--zhuoer-color-text-muted, #5f6368);font-size:0.8125rem;">加载中...</span>
                </div>

            </div>

            <!-- 右侧按钮 -->
            <div class="nav-single-actions">
                <a href="<?php echo esc_url($url); ?>" class="nav-btn nav-btn-primary" target="_blank" rel="noopener" onclick="recordDetailClick(<?php echo get_the_ID(); ?>, '<?php echo esc_js($name); ?>', '<?php echo esc_js(get_permalink()); ?>', '<?php echo esc_js($logo); ?>')">访问网站</a>
                <button type="button" class="nav-btn nav-btn-secondary nav-btn-icon" id="favBtn" onclick="toggleFavorite(<?php echo get_the_ID(); ?>)" title="收藏">
                    <span id="favIcon">🤍</span> <span id="favText">收藏</span>
                </button>
                <button class="nav-btn nav-btn-secondary nav-btn-icon" onclick="showQrCode('<?php echo esc_js($url); ?>')" title="手机访问">📱</button>
                <button class="nav-btn nav-btn-secondary nav-btn-icon" onclick="openShare('<?php echo esc_js($name); ?>', '<?php echo esc_js(get_permalink()); ?>')" title="分享">🔗</button>
            </div>
        </div>

        <!-- 分类与标签行 - 在logo下方 -->
        <?php if (($cats && !is_wp_error($cats)) || ($tags && !is_wp_error($tags))): ?>
        <div class="nav-single-meta-row">
            <?php if ($cats && !is_wp_error($cats)): ?>
            <div class="nav-single-meta-group">
                <?php foreach ($cats as $cat): ?>
                    <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="nav-single-meta-tag cat-tag">
                        <span>📁</span> <?php echo esc_html($cat->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($tags && !is_wp_error($tags)): ?>
            <div class="nav-single-meta-group">
                <?php foreach ($tags as $tag): ?>
                    <a href="<?php echo esc_url(get_term_link($tag)); ?>" class="nav-single-meta-tag">
                        <?php echo esc_html($tag->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 1. 网站截图 -->
    <div class="nav-screenshot-section">
        <?php if ($sshot): ?>
            <img src="<?php echo esc_url($sshot); ?>" alt="<?php echo esc_attr($sshotAlt); ?>" loading="lazy">
        <?php else: ?>
            <img src="https://s0.wp.com/mshots/v1/<?php echo urlencode($url); ?>?w=1080&h=600" alt="截图"
                 onerror="this.src='https://image.thum.io/get/width/1080/crop/600/<?php echo esc_url($url); ?>'">
        <?php endif; ?>
    </div>

    <!-- 2. 网站描述 -->
    <?php if ($desc): ?>
    <div class="nav-info-block">
        <h3>网站描述</h3>
        <div class="content"><?php echo \ZuoAIPlus\Utils\MarkdownConverter::convert($desc); ?></div>
    </div>
    <?php endif; ?>

    <!-- 3. 网站简介 -->
    <?php if ($aiSum): ?>
    <div class="nav-article">
        <h3>网站简介</h3>
        <?php
        $text = $aiSum;
        // 智能分段
        $text = preg_replace('/([：:])\s*(?=\d+[\.\、]\s)/u', "$1\n\n", $text);
        $text = preg_replace('/([：:])\s*(?=[一二三四五六七八九十]+[\、\.]\s)/u', "$1\n\n", $text);
        $breakPatterns = [
            '/。\s*(?=\d+[\.\、]\s)/u',
            '/。\s*(?=[一二三四五六七八九十]+[\、\.]\s)/u',
            '/。\s*(?=总之|综上|此外|另外|同时|然而|但是|而且|因此|所以|其次|再次|最后|首先|不仅如此|总的来说|总而言之)/u',
            '/。\s*(?=[^\s]{1,8}[：:])/u',
            '/[！!]\s*(?=\d+[\.\、]\s)/u',
            '/[！!]\s*(?=[^\s]{1,8}[：:])/u',
        ];
        foreach ($breakPatterns as $pattern) {
            $text = preg_replace($pattern, "\n\n", $text);
        }
        $text = preg_replace('/(?<!\n)\s*(\*\*[^*]+：?\*\*)/', "\n\n$1", $text);
        echo preg_replace('/<\/ol>\s*<ol>/', '', \ZuoAIPlus\Utils\MarkdownConverter::convert($text));
        ?>
    </div>
    <?php endif; ?>

    <!-- 4. 网站关键词 -->
    <?php if ($kw): ?>
    <div class="nav-info-block">
        <h3>网站关键词</h3>
        <div class="nav-keywords">
            <?php foreach (explode(',', $kw) as $k): ?>
                <?php $k = trim($k); if ($k): ?>
                <span class="nav-keyword"><?php echo esc_html($k); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 评分系统 -->
    <div class="nav-rating-section" id="rating-section">
        <div class="nav-rating-header">
            <div class="nav-rating-score" id="rating-score">0.0</div>
            <div class="nav-rating-info">
                <div class="nav-rating-stars" id="rating-stars-display">
                    <span class="star empty">★</span><span class="star empty">★</span><span class="star empty">★</span><span class="star empty">★</span><span class="star empty">★</span>
                </div>
                <div class="nav-rating-count" id="rating-count">0 人评分</div>
            </div>
        </div>
        <div class="nav-rating-action" id="rating-action">
            <div class="nav-rating-label">点击星星评分：</div>
            <div class="nav-rating-input" id="rating-input">
                <button class="star-btn" data-rating="1" onclick="submitRating(1)">★</button>
                <button class="star-btn" data-rating="2" onclick="submitRating(2)">★</button>
                <button class="star-btn" data-rating="3" onclick="submitRating(3)">★</button>
                <button class="star-btn" data-rating="4" onclick="submitRating(4)">★</button>
                <button class="star-btn" data-rating="5" onclick="submitRating(5)">★</button>
            </div>
            <div class="nav-rating-message" id="rating-message"></div>
        </div>
    </div>

    <!-- 相关推荐 -->
    <?php
    // 获取相关网站（同分类）
    $relatedQuery = null;
    if ($cats && !is_wp_error($cats) && !empty($cats)) {
        $catIds = wp_list_pluck($cats, 'term_id');
        $relatedQuery = new \WP_Query([
            'post_type'      => 'nav_site',
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            'post__not_in'   => [get_the_ID()],
            'tax_query'      => [[
                'taxonomy' => 'nav_category',
                'field'    => 'term_id',
                'terms'    => $catIds,
            ]],
            'orderby'        => 'rand',
        ]);
    }
    // 如果没有同分类的，获取同标签的
    if (!$relatedQuery || !$relatedQuery->have_posts()) {
        if ($tags && !is_wp_error($tags) && !empty($tags)) {
            $tagIds = wp_list_pluck($tags, 'term_id');
            $relatedQuery = new \WP_Query([
                'post_type'      => 'nav_site',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'post__not_in'   => [get_the_ID()],
                'tax_query'      => [[
                    'taxonomy' => 'nav_tag',
                    'field'    => 'term_id',
                    'terms'    => $tagIds,
                ]],
                'orderby'        => 'rand',
            ]);
        }
    }
    ?>
    <?php if ($relatedQuery && $relatedQuery->have_posts()): ?>
    <div class="nav-related-section">
        <h3 class="nav-related-title">相关网站</h3>
        <div class="nav-related-grid">
            <?php while ($relatedQuery->have_posts()): $relatedQuery->the_post(); ?>
                <?php
                $relatedMeta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
                $relatedUrl = $relatedMeta['url'] ?: '#';
                $relatedName = $relatedMeta['name'] ?: get_the_title();
                ?>
                <a href="<?php echo esc_url(get_permalink()); ?>" class="nav-related-card">
                    <div class="nav-related-logo">
                        <?php if ($relatedMeta['logo']): ?>
                            <img src="<?php echo esc_url($relatedMeta['logo']); ?>" alt="<?php echo esc_attr($relatedName); ?>" loading="lazy">
                        <?php else: ?>
                            <span class="nav-related-letter"><?php echo esc_html(mb_substr($relatedName, 0, 1, 'UTF-8')); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="nav-related-info">
                        <div class="nav-related-name"><?php echo esc_html($relatedName); ?></div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; wp_reset_postdata(); ?>

    <!-- 底部 -->
    <div class="nav-footer-bar">
        <div>浏览 <?php echo intval($views); ?> 次</div>
    </div>
</main>

<!-- 二维码弹窗 -->
<div class="qr-overlay" id="qrOverlay" onclick="closeQrCode(event)">
    <div class="qr-modal">
        <h4>扫码访问</h4>
        <img id="qrImage" src="" alt="二维码">
        <p>用手机扫描二维码访问网站</p>
    </div>
</div>

<!-- Schema.org 结构化数据 -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "<?php echo esc_js($name); ?>",
    "url": "<?php echo esc_js(get_permalink()); ?>",
    "description": "<?php echo esc_js($desc ?: $aiSum); ?>",
    "inLanguage": "zh-CN",
    "isPartOf": {
        "@type": "WebSite",
        "name": "<?php bloginfo('name'); ?>",
        "url": "<?php echo esc_js(home_url()); ?>"
    },
    <?php if ($logo): ?>
    "image": "<?php echo esc_js($logo); ?>",
    <?php endif; ?>
    <?php if ($ratingCount > 0): ?>
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<?php echo esc_js(number_format($ratingAvg, 1)); ?>",
        "ratingCount": "<?php echo intval($ratingCount); ?>",
        "bestRating": "5",
        "worstRating": "1"
    },
    <?php endif; ?>
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            <?php foreach ($bcItems as $i => $item): ?>
            {
                "@type": "ListItem",
                "position": <?php echo $i + 1; ?>,
                "name": "<?php echo esc_js($item['name']); ?>",
                "item": "<?php echo esc_js($item['url']); ?>"
            }<?php echo ($i < count($bcItems) - 1) ? ',' : ''; ?>
            <?php endforeach; ?>
        ]
    }
}
</script>

<!-- 分享弹窗 -->
<div class="nav-share-overlay" id="shareOverlay" onclick="closeShare(event)">
    <div class="nav-share-modal" onclick="event.stopPropagation()">
        <div class="nav-share-title">分享网站</div>
        <div class="nav-share-buttons">
            <button class="nav-share-btn" onclick="shareTo('weixin')">
                <span class="nav-share-icon">💬</span>
                <span class="nav-share-label">微信</span>
            </button>
            <button class="nav-share-btn" onclick="shareTo('weibo')">
                <span class="nav-share-icon">🔴</span>
                <span class="nav-share-label">微博</span>
            </button>
            <button class="nav-share-btn" onclick="shareTo('qq')">
                <span class="nav-share-icon">🐧</span>
                <span class="nav-share-label">QQ</span>
            </button>
            <button class="nav-share-btn" onclick="copyLink()">
                <span class="nav-share-icon">📋</span>
                <span class="nav-share-label">复制链接</span>
            </button>
        </div>
        <button class="nav-share-close" onclick="closeShare()">关闭</button>
    </div>
</div>





<?php get_footer(); ?>


