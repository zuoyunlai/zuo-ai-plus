<?php
/**
 * 导航标签页 (taxonomy-nav_tag.php)
 * iowen风格卡片 + 网格布局
 */
if (!defined('ABSPATH')) exit;

get_header();

$term = get_queried_object();
$termId = $term->term_id;

$queryArgs = [
    'post_type'      => 'nav_site',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => max(1, get_query_var('paged')),
    'tax_query'      => [[
        'taxonomy' => 'nav_tag',
        'field'    => 'term_id',
        'terms'    => $termId,
    ]],
];
$query = new \WP_Query($queryArgs);
?>


<nav class="nav-breadcrumb">
    <a href="<?php echo esc_url(home_url()); ?>">首页</a>
    <span class="sep">/</span>
    <a href="<?php echo esc_url(get_post_type_archive_link('nav_site')); ?>">网址导航</a>
    <span class="sep">/</span>
    <span class="current"><?php echo esc_html($term->name); ?></span>
</nav>

<main class="nav-archive-wrap">
    <div class="nav-archive-header">
        <h1><?php echo esc_html($term->name); ?></h1>
        <p><?php echo esc_html($term->description ?: '共 ' . $query->found_posts . ' 个网站'); ?></p>
    </div>

    <?php if ($query->have_posts()): ?>
    <div class="nav-grid">
        <?php while ($query->have_posts()): $query->the_post();
            $meta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
            $url  = $meta['url'] ?: '#';
            $name = $meta['name'] ?: get_the_title();
            $desc = $meta['description'] ?: '';
            $logo = $meta['logo'] ?: '';
            $cats = get_the_terms(get_the_ID(), 'nav_category');
        ?>
        <article class="nav-card" data-post-id="<?php echo get_the_ID(); ?>">
            <?php if ($meta['status'] === 'featured'): ?>
            <div class="nav-card-badge">⭐</div>
            <?php endif; ?>
            
            <a href="<?php echo esc_url(get_permalink()); ?>" class="nav-card-main">
                <div class="nav-card-media">
                    <?php if ($logo): ?>
                    <div class="blur-bg" style="background-image:url('<?php echo esc_url($logo); ?>')"></div>
                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" class="nav-card-img" loading="lazy">
                    <?php else: ?>
                    <span class="nav-card-letter"><?php echo esc_html(mb_substr($name, 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-card-body">
                    <h3 class="nav-card-title"><b><?php echo esc_html($name); ?></b></h3>
                    <?php if ($desc): ?>
                    <div class="nav-card-desc"><?php echo esc_html($desc); ?></div>
                    <?php endif; ?>
                </div>
            </a>
            
            <div class="nav-card-footer">
                <div class="nav-card-tags">
                    <?php if ($cats && !is_wp_error($cats)): ?>
                        <?php foreach (array_slice($cats, 0, 1) as $cat): ?>
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="nav-card-tag">📁 <?php echo esc_html($cat->name); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="nav-card-togo" title="直达" onclick="recordNavClick(<?php echo get_the_ID(); ?>)">直达</a>
            </div>
        </article>
        <?php endwhile; ?>
    </div>

    <?php if ($query->max_num_pages > 1): ?>
    <div class="nav-pagination">
        <?php
        echo paginate_links([
            'total'   => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
        ]);
        ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="nav-empty"><p>该标签下暂无网站</p></div>
    <?php endif; ?>
</div>

<!-- SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "<?php echo esc_js($term->name); ?>",
    "url": "<?php echo esc_js(get_term_link($term)); ?>",
    "description": "<?php echo esc_js($term->description ?: ('标签：' . $term->name)); ?>",
    "inLanguage": "zh-CN",
    "isPartOf": {"@type": "WebSite", "name": "<?php bloginfo('name'); ?>", "url": "<?php echo esc_js(home_url()); ?>"}
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "首页", "item": "<?php echo esc_js(home_url()); ?>"},
            {"@type": "ListItem", "position": 2, "name": "网址导航", "item": "<?php echo esc_js(get_post_type_archive_link('nav_site')); ?>"},
            {"@type": "ListItem", "position": 3, "name": "<?php echo esc_js($term->name); ?>", "item": "<?php echo esc_js(get_term_link($term)); ?>"}
        ]
    }
}
</script>

</main>

<?php get_footer(); ?>


