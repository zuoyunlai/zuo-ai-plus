<?php
/**
 * 导航网站列表页 (archive-nav_site.php)
 * 两栏布局：左侧分类/收藏/历史，右侧网站列表
 */
if (!defined('ABSPATH')) exit;

get_header();

// 使用模型方法获取分类层级树（自动累加子分类计数）
$catTree = \ZuoAIPlus\Models\NavigationSite::getCatTree();
$activeCat = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$queryArgs = [
    'post_type'      => 'nav_site',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => max(1, get_query_var('paged')),
    'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
];
if ($activeCat) {
    $queryArgs['tax_query'] = [['taxonomy' => 'nav_category', 'field' => 'term_id', 'terms' => $activeCat]];
}
$query = new \WP_Query($queryArgs);
?>


<nav class="nav-breadcrumb">
    <a href="<?php echo esc_url(home_url()); ?>">首页</a>
    <span class="sep">/</span>
    <span class="current">网址导航</span>
</nav>

<div class="nav-archive-wrap">
  <div class="nav-layout">
    <!-- 左侧边栏 -->
    <aside class="nav-sidebar">
      <!-- 快速入口 -->
      <div class="nav-sidebar-section">
        <h3 class="nav-sidebar-title">🚀 快速入口</h3>
        <div class="nav-quick-links">
          <button type="button" class="nav-quick-link active" data-view="all" onclick="switchView('all')">
            <span class="nav-quick-icon">🏠</span>
            <span>全部网站</span>
            <span class="nav-quick-count"><?php echo wp_count_posts('nav_site')->publish; ?></span>
          </button>
          <button type="button" class="nav-quick-link" data-view="favorites" onclick="switchView('favorites')">
            <span class="nav-quick-icon">❤️</span>
            <span>我的收藏</span>
            <span class="nav-quick-count" id="fav-count"></span>
          </button>
          <button type="button" class="nav-quick-link" data-view="history" onclick="switchView('history')">
            <span class="nav-quick-icon">🕐</span>
            <span>最近访问</span>
            <span class="nav-quick-count" id="history-count"></span>
          </button>
        </div>
      </div>

      <!-- 分类导航 -->
      <?php if (!empty($catTree)): ?>
      <div class="nav-sidebar-section">
        <h3 class="nav-sidebar-title">📁 分类导航</h3>
        <ul class="nav-cat-list" id="nav-cat-tree">
          <?php foreach ($catTree as $parent): ?>
          <?php $hasChildren = !empty($parent['children']); ?>
          <li class="cat-item<?php echo $hasChildren ? ' cat-has-children' : ''; ?><?php echo ($activeCat && $activeCat == $parent['term']->term_id) ? ' cat-active' : ''; ?>">
            <?php if ($hasChildren): ?>
            <div class="cat-parent-row" onclick="toggleCatChildren(this)">
              <span class="cat-toggle-icon">▸</span>
              <a href="<?php echo esc_url(get_term_link($parent['term'])); ?>" class="cat-name"><?php echo esc_html($parent['term']->name); ?></a>
              <span class="cat-count"><?php echo intval($parent['accumulated_count']); ?></span>
            </div>
            <ul class="cat-children" style="display:none">
              <?php foreach ($parent['children'] as $child): ?>
              <li class="cat-child-item<?php echo ($activeCat && $activeCat == $child['term']->term_id) ? ' cat-active' : ''; ?>">
                <a href="<?php echo esc_url(get_term_link($child['term'])); ?>">
                  <span class="cat-child-name"><?php echo esc_html($child['term']->name); ?></span>
                  <span class="cat-count"><?php echo intval($child['term']->count); ?></span>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <a href="<?php echo esc_url(get_term_link($parent['term'])); ?>" class="cat-name">
              <span><?php echo esc_html($parent['term']->name); ?></span>
              <span class="cat-count"><?php echo intval($parent['term']->count); ?></span>
            </a>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </aside>

    <!-- 右侧主内容 -->
    <main class="nav-main">
      <div class="nav-main-header">
        <h1 id="main-title">全部网站</h1>
        <p class="nav-main-count" id="site-count-display">共 <?php echo intval(wp_count_posts('nav_site')->publish); ?> 个站点</p>
      </div>

      <!-- 全部网站 -->
      <div id="view-all" class="nav-view-section">
      <?php if ($query->have_posts()): ?>
      <div class="nav-grid">
        <?php while ($query->have_posts()): $query->the_post();
            $meta = \ZuoAIPlus\Models\NavigationSite::getMeta(get_the_ID());
            $logo   = $meta['logo'];
            $url    = $meta['url'] ?: '#';
            $desc   = $meta['description'] ?: wp_strip_all_tags(get_the_content());
            $name   = $meta['name'] ?: get_the_title();
            $tags   = get_the_terms(get_the_ID(), 'nav_tag');
            $cats   = get_the_terms(get_the_ID(), 'nav_category');
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
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="nav-card-tag tag-cat"><?php echo esc_html($cat->name); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($tags && !is_wp_error($tags)): ?>
                        <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                        <a href="<?php echo esc_url(get_term_link($tag)); ?>" class="nav-card-tag"><?php echo esc_html($tag->name); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="nav-card-togo" title="直达" onclick="recordNavClick(<?php echo get_the_ID(); ?>)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="7" y1="17" x2="17" y2="7"></line>
                        <polyline points="7 7 17 7 17 17"></polyline>
                    </svg>
                </a>
            </div>
        </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>

      <?php if ($query->max_num_pages > 1): ?>
      <div class="nav-pagination">
        <?php
        echo paginate_links([
            'total'   => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
            'format'  => '?paged=%#%',
        ]);
        ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="nav-empty">暂无导航网站</div>
      <?php endif; ?>
      </div><!-- /view-all -->

      <!-- 我的收藏 -->
      <div id="view-favorites" class="nav-view-section" style="display:none;">
        <div class="nav-grid" id="favorites-list"></div>
      </div>

      <!-- 最近访问 -->
      <div id="view-history" class="nav-view-section" style="display:none;">
        <div class="nav-grid" id="history-list"></div>
      </div>
    </main>
  </div>
</div>

<?php get_footer(); ?>


