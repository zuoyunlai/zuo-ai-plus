<?php
/**
 * 插件激活时创建数据库表
 */
namespace AI_Plus\Utils;

class Activator
{
    public static function activate(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // AI 对话记录表
        $table_chat = $wpdb->prefix . 'ai_plus_chat';
        $sql_chat = "CREATE TABLE $table_chat (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            role varchar(20) NOT NULL DEFAULT 'user',
            message longtext NOT NULL,
            response longtext,
            model varchar(32) NOT NULL,
            tokens int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // 提示词模板表
        $table_templates = $wpdb->prefix . 'ai_plus_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            prompt_template longtext NOT NULL,
            category varchar(64) DEFAULT 'general',
            is_favorite tinyint(1) DEFAULT 0,
            use_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 生成历史记录表
        $table_history = $wpdb->prefix . 'ai_plus_history';
        $sql_history = "CREATE TABLE $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            action_type varchar(32) NOT NULL,
            prompt longtext,
            result longtext,
            model varchar(32) NOT NULL,
            tokens int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY action_type (action_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_chat);
        dbDelta($sql_templates);
        dbDelta($sql_history);

        // 默认选项
        $defaults = [
            'ai_plus_enabled_models' => ['zhipu', 'tongyi', 'minimax', 'kimi'],
            'ai_plus_default_model' => 'zhipu',
            'ai_plus_api_keys' => [],
            'ai_plus_chat_enabled' => '1',
            'ai_plus_image_enabled' => '1',
            'ai_plus_seo_enabled' => '1',
        ];
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
