<?php
/**
 * 导航网站数据模型
 */
namespace ZuoAIPlus\Models;

if (!defined('ABSPATH')) exit;

class NavigationSite
{
    const POST_TYPE = 'nav_site';
    const TAX_CAT   = 'nav_category';
    const TAX_TAG   = 'nav_tag';

    // ── 注册 CPT 和分类法 ────────────────────────────────────────────────
    public static function register(): void
    {
        // CPT
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'               => '导航网站',
                'singular_name'      => '导航网站',
                'add_new'            => '添加网站',
                'add_new_item'       => '添加新网站',
                'edit_item'          => '编辑网站',
                'new_item'           => '新网站',
                'view_item'          => '查看网站',
                'search_items'      => '搜索网站',
                'not_found'          => '未找到网站',
                'not_found_in_trash' => '回收站中未找到网站',
                'menu_name'          => '导航',
            ],
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'       => 25,
            'menu_icon'          => 'dashicons-book-alt',
            'capability_type'    => 'post',
            'has_archive'        => 'nav-sites',
            'rewrite'            => ['slug' => 'nav-sites'],
            'supports'           => ['title', 'author', 'thumbnail'],
            'show_in_rest'       => true,
            'template'           => [],
            'rest_base'          => 'nav-sites',
        ]);

        // 分类
        register_taxonomy(self::TAX_CAT, self::POST_TYPE, [
            'labels'       => [
                'name'              => '导航分类',
                'singular_name'     => '导航分类',
                'search_items'      => '搜索分类',
                'all_items'         => '所有分类',
                'edit_item'         => '编辑分类',
                'update_item'       => '更新分类',
                'add_new_item'      => '添加新分类',
                'new_item_name'     => '新分类名称',
                'menu_name'         => '导航分类',
            ],
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'nav-category'],
            'show_in_rest'       => true,
        ]);

        // 标签
        register_taxonomy(self::TAX_TAG, self::POST_TYPE, [
            'labels'       => [
                'name'              => '导航标签',
                'singular_name'     => '导航标签',
                'search_items'      => '搜索标签',
                'all_items'         => '所有标签',
                'edit_item'         => '编辑标签',
                'update_item'       => '更新标签',
                'add_new_item'      => '添加新标签',
                'new_item_name'     => '新标签名称',
                'menu_name'         => '导航标签',
            ],
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'nav-tag'],
            'show_in_rest'       => true,
        ]);
    }

    // ── 注册 Meta 字段 ────────────────────────────────────────────────────
    public static function registerMeta(): void
    {
        $fields = [
            'nav_url'         => 'string',
            'nav_name'        => 'string',
            'nav_keywords'    => 'string',
            'nav_description' => 'string',
            'nav_logo'        => 'string',
            'nav_screenshot'  => 'string',
            'nav_ai_summary'  => 'string',
            'nav_views'       => 'integer',
            'nav_status'      => 'string', // featured | normal
        ];

        foreach ($fields as $key => $type) {
            register_post_meta(self::POST_TYPE, $key, [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $type,
                'auth_callback' => function () { return true; }, // 允许所有人读取
            ]);
        }

        // 注册 REST API 字段，确保 meta 数据在响应中可用
        add_action('rest_api_init', [self::class, 'registerRestFields']);
    }

    /**
     * 注册 REST API 字段，暴露导航网站元数据
     */
    public static function registerRestFields(): void
    {
        register_rest_field(self::POST_TYPE, 'nav_meta', [
            'get_callback'    => function ($post) {
                return self::getMeta($post['id']);
            },
            'update_callback' => null,
            'schema'          => null,
        ]);
    }

    // ── 获取网站元数据 ────────────────────────────────────────────────────
    public static function getMeta(int $postId): array
    {
        return [
            'url'         => get_post_meta($postId, 'nav_url', true),
            'name'        => get_post_meta($postId, 'nav_name', true) ?: get_the_title($postId),
            'keywords'    => get_post_meta($postId, 'nav_keywords', true),
            'description' => get_post_meta($postId, 'nav_description', true),
            'logo'        => get_post_meta($postId, 'nav_logo', true),
            'screenshot'  => get_post_meta($postId, 'nav_screenshot', true),
            'ai_summary'  => get_post_meta($postId, 'nav_ai_summary', true),
            'views'       => (int) get_post_meta($postId, 'nav_views', true),
            'status'      => get_post_meta($postId, 'nav_status', true) ?: 'normal',
        ];
    }

    // ── 保存网站元数据 ────────────────────────────────────────────────────
    public static function saveMeta(int $postId, array $data): void
    {
        $fields = ['nav_url', 'nav_name', 'nav_keywords', 'nav_description',
                    'nav_logo', 'nav_screenshot', 'nav_ai_summary', 'nav_status'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($postId, $field, sanitize_text_field($data[$field]));
            }
        }
        // 标题同步
        if (!empty($data['nav_name'])) {
            wp_update_post(['ID' => $postId, 'post_title' => sanitize_text_field($data['nav_name'])]);
        }
    }

    // ── 列表查询 ──────────────────────────────────────────────────────────
    public static function query(array $args = []): \WP_Query
    {
        $defaults = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $args = array_merge($defaults, $args);
        return new \WP_Query($args);
    }
}
