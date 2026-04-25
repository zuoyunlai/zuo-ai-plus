<?php
/**
 * 导航主页 (page-nav.php)
 * 放在WordPress页面里作为导航首页，可添加到主菜单
 * 模板名称：导航主页
 */
if (!defined('ABSPATH')) exit;

/**
 * Template Name: 导航主页
 * Description: 网址导航首页模板，请新建页面并选择此模板
 */

get_header();

$cats = get_terms([
    'taxonomy'   => 'nav_category',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);

// 注册外部 CSS/JS 资源
wp_enqueue_style('zuo-nav-css', AI_PLUS_PLUGIN_URL . 'Assets/css/nav.css', [], AI_PLUS_VERSION);
wp_enqueue_script('zuo-nav-js', AI_PLUS_PLUGIN_URL . 'Assets/js/nav.js', [], AI_PLUS_VERSION, true);
wp_localize_script('zuo-nav-js', 'zuoNav', [
    'clickUrl'   => esc_url(rest_url('ai-plus/v1/nav/click')),
    'restBase'   => esc_url(rest_url('wp/v2/nav-sites')),
    'archiveUrl' => esc_url(get_post_type_archive_link('nav_site')),
    'ratingUrl'  => '',
    'rateUrl'    => '',
    'weightUrl'  => '',
    'postId'     => 0,
    'siteDomain' => '',
]);

$queryArgs = [
    'post_type'      => 'nav_site',
    'post_status'    => 'publish',
    'posts_per_page' => 100,
    'orderby'        => ['meta_value' => 'DESC', 'date' => 'DESC'],
    'meta_key'       => 'nav_views',
];
$query = new \WP_Query($queryArgs);

// 按分类聚合
$sitesByCat = [];
$featured = [];
// 预加载 post meta 和 term cache，避免 N+1 查询
update_meta_cache('post', wp_list_pluck($query->posts, 'ID'));
update_object_term_cache(wp_list_pluck($query->posts, 'ID'), 'nav_site');
while ($query->have_posts()) {
    $query->the_post();
    $meta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
    $postCats = get_the_terms(get_the_ID(), 'nav_category');
    $isFeatured = ($meta['status'] === 'featured');

    if ($isFeatured) {
        $featured[] = ['post' => get_post(), 'meta' => $meta];
        continue;
    }

    if ($postCats && !is_wp_error($postCats)) {
        foreach ($postCats as $cat) {
            $sitesByCat[$cat->term_id]['term'] = $cat;
            $sitesByCat[$cat->term_id]['sites'][] = ['post' => get_post(), 'meta' => $meta];
        }
    } else {
        $sitesByCat[0]['term'] = (object)['term_id' => 0, 'name' => '未分类'];
        $sitesByCat[0]['sites'][] = ['post' => get_post(), 'meta' => $meta];
    }
}
wp_reset_postdata();
?>


<nav class="nav-breadcrumb">
    <a href="<?php echo esc_url(home_url()); ?>">首页</a>
    <span class="sep">/</span>
    <span class="current">网址导航</span>
</nav>

<main class="nav-home-wrap">
    <!-- 搜索 -->
    <div class="nav-home-search" style="position:relative;">
        <input type="search" id="nav-search-input" placeholder="🔍 搜索网站名称或关键词... (按 / 快速搜索)" autocomplete="off">
        <div class="nav-search-suggestions" id="search-suggestions"></div>
    </div>
    <div class="nav-search-empty" id="nav-search-empty">未找到匹配结果</div>

    <!-- Tab 切换 -->
    <div class="nav-tabs" style="display:flex;gap:8px;margin-bottom:24px;justify-content:center;flex-wrap:wrap;">
        <button type="button" class="nav-tab-btn active" data-tab="all" onclick="switchTab('all')">全部网站</button>
        <button type="button" class="nav-tab-btn" data-tab="favorites" onclick="switchTab('favorites')">❤️ 我的收藏</button>
        <button type="button" class="nav-tab-btn" data-tab="history" onclick="switchTab('history')">🕐 最近访问</button>
        <button type="button" class="nav-tab-btn" onclick="showDataManager()">⚙️ 数据管理</button>
    </div>
    

    <!-- 分类快捷入口 -->
    <div class="nav-cat-scroll">
        <div class="nav-cat-list">
            <span class="nav-cat-btn active" data-cat="all">全部<span class="nav-cat-count"><?php echo esc_html($query->found_posts); ?></span></span>
            <?php foreach ($cats as $cat): ?>
            <span class="nav-cat-btn" data-cat="<?php echo esc_attr($cat->term_id); ?>">
                <?php echo esc_html($cat->name); ?><span class="nav-cat-count"><?php echo esc_html($cat->count); ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 热门网站 -->
    <?php
    $popularQuery = new \WP_Query([
        'post_type'      => 'nav_site',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'meta_key'       => 'nav_views',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ]);
    if ($popularQuery->have_posts()):
        update_meta_cache('post', wp_list_pluck($popularQuery->posts, 'ID'));
        update_object_term_cache(wp_list_pluck($popularQuery->posts, 'ID'), 'nav_site');
    ?>
    <div class="nav-popular-section">
        <h3 class="nav-popular-title">🔥 热门网站</h3>
        <div class="nav-popular-grid">
          <?php while ($popularQuery->have_posts()): $popularQuery->the_post();
              $pmeta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
              $plogo = $pmeta['logo'];
              $pname = $pmeta['name'] ?: get_the_title();
              $pdesc = $pmeta['description'];
              $pviews = (int) get_post_meta(get_the_ID(), 'nav_views', true);
          ?>
          <a href="<?php echo esc_url(get_permalink()); ?>" class="nav-popular-item" onclick="recordNavClick(<?php echo esc_js(get_the_ID()); ?>)">
            <div class="nav-popular-logo">
              <?php if ($plogo): ?>
              <img src="<?php echo esc_url($plogo); ?>" alt="<?php echo esc_attr($pname); ?>" loading="lazy">
              <?php else: ?>
              <span class="nav-popular-letter"><?php echo esc_html(mb_substr($pname, 0, 1, 'UTF-8')); ?></span>
              <?php endif; ?>
            </div>
            <div class="nav-popular-info">
              <span class="nav-popular-name"><?php echo esc_html($pname); ?></span>
              <?php if ($pdesc): ?>
              <span class="nav-popular-desc"><?php echo esc_html(mb_substr($pdesc, 0, 30, 'UTF-8')); ?></span>
              <?php endif; ?>
            </div>
            <span class="nav-popular-clicks"><?php echo esc_html($pviews); ?>次浏览</span>
          </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 推荐网站 -->
    <?php if (!empty($featured)): ?>
    <div class="nav-featured-section" id="section-featured">
        <h2 class="nav-featured-title">⭐ 推荐网站</h2>
        <div class="nav-featured-grid">
            <?php foreach ($featured as $item):
                $p = $item['post'];
                $m = $item['meta'];
                $url = $m['url'] ?: '#';
                $name = $m['name'] ?: $p->post_title;
                $desc = $m['description'] ?: '';
            ?>
            <a href="<?php echo esc_url($url); ?>" class="nav-featured-card" target="_blank" rel="noopener" data-keywords="<?php echo esc_attr($m['keywords'] ?? ''); ?>" data-name="<?php echo esc_attr($name); ?>" data-id="<?php echo esc_attr($p->ID); ?>">
                <div class="nav-featured-badge">⭐ 推荐</div>
                <div class="nav-card-actions" onclick="event.preventDefault();">
                    <button class="nav-card-action-btn favorite" onclick="quickFavorite(<?php echo intval($p->ID); ?>, '<?php echo esc_js($name); ?>', '<?php echo esc_js($url); ?>', '<?php echo esc_js($m['logo'] ?? ''); ?>', this)" title="收藏">🤍</button>
                    <button class="nav-card-action-btn" onclick="openShare('<?php echo esc_js($name); ?>', '<?php echo esc_js($url); ?>')" title="分享">📤</button>
                </div>
                <div class="nav-featured-logo">
                    <?php if ($m['logo']): ?>
                        <img src="<?php echo esc_url($m['logo']); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
                    <?php else: ?>
                        <span class="logo-letter"><?php echo esc_html(mb_substr($name, 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-featured-info">
                    <div class="nav-featured-name"><?php echo esc_html($name); ?></div>
                    <?php if ($desc): ?><div class="nav-featured-desc"><?php echo esc_html($desc); ?></div><?php endif; ?>
                    <div class="nav-featured-url"><?php echo esc_html(wp_parse_url($url, PHP_URL_HOST)); ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 分类下的网站 -->
    <?php foreach ($sitesByCat as $catId => $group):
        $term = $group['term'];
        $sites = $group['sites'];
    ?>
    <div class="nav-cat-block" data-cat="<?php echo esc_attr($term->term_id); ?>">
        <h2 class="nav-cat-block-title">
            <?php if ($term->term_id): ?>
                <a href="<?php echo esc_url(get_term_link($term)); ?>">📂 <?php echo esc_html($term->name); ?></a>
                <span style="font-weight:400;color:var(--nav-text-muted);font-size:14px;margin-left:8px;">(<?php echo count($sites); ?>)</span>
            <?php else: ?>
                📂 未分类
            <?php endif; ?>
        </h2>
        <div class="nav-site-grid">
            <?php foreach ($sites as $item):
                $p = $item['post'];
                $m = $item['meta'];
                $url = $m['url'] ?: '#';
                $name = $m['name'] ?: $p->post_title;
                $desc = $m['description'] ?: '';
            ?>
            <a href="<?php echo esc_url($url); ?>" class="nav-site-card"
               target="_blank" rel="noopener"
               data-cat="<?php echo esc_attr($term->term_id); ?>"
               data-keywords="<?php echo esc_attr($m['keywords'] ?? ''); ?>"
               data-name="<?php echo esc_attr($name); ?>">
                <div class="nav-site-logo-wrap">
                    <?php if ($m['logo']): ?>
                        <img src="<?php echo esc_url($m["logo"]); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
                    <?php else: ?>
                        <span class="nav-site-logo-letter"><?php echo esc_html(mb_substr($name, 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-site-name"><?php echo esc_html($name); ?></div>
                <?php if ($desc): ?><div class="nav-site-desc"><?php echo esc_html($desc); ?></div><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($featured) && empty($sitesByCat)): ?>
    <div class="nav-empty-state">
        <p>暂无导航网站</p>
        <p>请在后台添加网站后刷新页面</p>
    </div>
    <?php endif; ?>
</div>



<!-- Toast 容器 -->
<div class="nav-toast-container" id="toastContainer"></div>

<!-- 数据管理弹窗 -->
<div class="nav-share-overlay" id="dataManagerOverlay" onclick="closeDataManager(event)">
    <div class="nav-share-modal" onclick="event.stopPropagation()" style="max-width: 400px;">
        <div class="nav-share-title">数据管理</div>
        <div style="text-align: left; margin-bottom: 20px;">
            <p style="margin: 0 0 15px; color: var(--zhuoer-color-text-muted, #5f6368); font-size: 0.875rem;">
                导出收藏和访问记录，或从文件导入。
            </p>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button class="nav-share-close" onclick="exportData()" style="flex: 1;">📥 导出数据</button>
                <button class="nav-share-close" onclick="document.getElementById('importFile').click()" style="flex: 1;">📤 导入数据</button>
            </div>
            <input type="file" id="importFile" accept=".json" style="display: none;" onchange="importData(this)">
            <button class="nav-share-close" onclick="clearAllData()" style="width: 100%; color: var(--nav-fav-active-color);">🗑️ 清空所有数据</button>
        </div>
        <button class="nav-share-close" onclick="closeDataManager()">关闭</button>
    </div>
</div>

<!-- 返回顶部 -->
<button class="nav-back-to-top" id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="返回顶部">↑</button>


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



<!-- PWA 支持 -->


</main>

<!-- Schema.org 结构化数据 -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "网址导航",
    "url": "<?php echo esc_js(get_permalink()); ?>",
    "description": "精选优质网站导航，按分类整理推荐",
    "inLanguage": "zh-CN",
    "isPartOf": {"@type": "WebSite", "name": "<?php bloginfo('name'); ?>", "url": "<?php echo esc_js(home_url()); ?>"},
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "首页", "item": "<?php echo esc_js(home_url()); ?>"},
            {"@type": "ListItem", "position": 2, "name": "网址导航", "item": "<?php echo esc_js(get_permalink()); ?>"}
        ]
    }
}
</script>

<?php get_footer(); ?>
