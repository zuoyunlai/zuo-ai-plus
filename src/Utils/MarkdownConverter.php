<?php
/**
 * Markdown → HTML 转换器（专为 WordPress / Gutenberg 设计）
 * 转换顺序：先分块 → 块级元素转换 → 列表单独处理
 */
namespace ZuoAIPlus\Utils;

if (!defined('ABSPATH')) exit;

class MarkdownConverter
{
    public static function convert(string $markdown): string
    {
        $md = $markdown;

        // 0. 预转换：处理整块代码（不换行）
        $md = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $lang = $m[1] ? ' language="' . esc_attr($m[1]) . '"' : '';
            return '<pre class="wp-block-code"><code' . $lang . '>' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</code></pre>';
        }, $md);

        // 0b. 行内代码 `code`
        $md = preg_replace('/`([^`]+)`/', '<code>$1</code>', $md);

        // 1. 粗体斜体先行（全局）
        $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
        $md = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $md);
        $md = preg_replace('/(?<!\*)\*([^\*\n]+)\*(?!\*)/', '<em>$1</em>', $md);
        $md = preg_replace('/(?<!_)_(?!_)([^_\n]+)_(?!_)/', '<em>$1</em>', $md);

        // 2. 链接和图片（全局）
        $md = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $md);
        $md = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $md);

        // 3. 按 \n\n 分割成块
        $md = preg_replace('/\n{3,}/', "\n\n", $md);
        $blocks = preg_split('/\n\n+/', $md);
        $out = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;

            // 行首检测
            $first_line = explode("\n", $block)[0];

            // 标题
            if (preg_match('/^#{1,6} /', $first_line)) {
                $block = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $block);
                $block = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $block);
                $block = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $block);
                $block = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $block);
                $out[] = $block;
                continue;
            }

            // 水平线
            if (trim($block) === '---' || trim($block) === '***' || trim($block) === '___') {
                $out[] = '<hr>';
                continue;
            }

            // 引用块（> 开头）
            if (preg_match('/^>/', $first_line)) {
                $block = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $block);
                $block = preg_replace('/<\/blockquote>\n<blockquote>/', "\n", $block);
                $out[] = $block;
                continue;
            }

            // 代码块（<pre> 包裹的）
            if (preg_match('/^<pre/', $first_line)) {
                $out[] = $block;
                continue;
            }

            // 表格
            if (preg_match('/^\|/', $first_line)) {
                $out[] = self::convertTableBlock($block);
                continue;
            }

            // 无序列表（- 或 * 开头）
            if (preg_match('/^[\-\*] /', $first_line)) {
                $out[] = self::convertListBlock($block, 'ul');
                continue;
            }

            // 有序列表
            if (preg_match('/^\d+\. /', $first_line)) {
                $out[] = self::convertListBlock($block, 'ol');
                continue;
            }

            // 普通段落
            $out[] = '<p>' . str_replace("\n", '<br>', $block) . '</p>';
        }

        return implode("\n", $out);
    }

    /**
     * 转换列表块
     */
    private static function convertListBlock(string $block, string $type): string
    {
        $lines = explode("\n", $block);
        $tag = $type === 'ol' ? 'ol' : 'ul';
        $html = "<{$tag}>";
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // 去掉列表前缀 (- * 1. 2. 等)
            $content = preg_replace('/^[\-\*] (.+)$/', '$1', $line);
            $content = preg_replace('/^\d+\. (.+)$/', '$1', $content);
            $html .= '<li>' . $content . '</li>';
        }
        $html .= "</{$tag}>";
        return $html;
    }

    /**
     * 转换表格块
     */
    private static function convertTableBlock(string $block): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $block)));
        if (count($lines) < 2) return $block;

        // 第二行是对齐行，跳过
        $header_line = $lines[0];
        $align_line = isset($lines[1]) && preg_match('/^[\|\-:]+$/', str_replace('|', '', $lines[1])) ? $lines[1] : '';

        $header_cells = array_map('trim', array_filter(explode('|', trim($header_line, '|'))));

        $aligns = [];
        if ($align_line) {
            foreach (explode('|', trim($align_line, '|')) as $cell) {
                $cell = trim($cell);
                if (strpos($cell, '---:') !== false) $aligns[] = ' style="text-align:right"';
                elseif (strpos($cell, ':---') !== false) $aligns[] = ' style="text-align:left"';
                elseif (strpos($cell, ':') !== false) $aligns[] = ' style="text-align:center"';
                else $aligns[] = '';
            }
        }

        $html = '<table class="wp-block-table"><thead><tr>';
        foreach ($header_cells as $i => $cell) {
            $align = $aligns[$i] ?? '';
            $html .= "<th{$align}>{$cell}</th>";
        }
        $html .= '</tr></thead><tbody>';

        for ($i = 2; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^[\|\-:]+$/', str_replace('|', '', $line))) continue;
            $cells = array_map('trim', explode('|', trim($line, '|')));
            $html .= '<tr>';
            foreach ($cells as $j => $cell) {
                $align = $aligns[$j] ?? '';
                $html .= "<td{$align}>{$cell}</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }
}
