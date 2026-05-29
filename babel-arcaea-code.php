<?php
/* ── 省略之前的头部定义和设置 ── */

function bac_defaults() {
    return [
        'enabled' => 1,
        'prism_enabled' => 1,
        'mermaid_enabled' => 1,
        'mathjax_enabled' => 0,
        'prism_version' => '1.30.0',
        'mermaid_version' => '11.15.0',
        'mathjax_version' => '3.2.2',
        'prism_line_numbers' => 1,
        'prism_copy' => 1,
        'prism_braces' => 1,
        'prism_previewers' => 1,
        'prism_theme' => 'arcaea_dark',
        'disable_sakurairo_prism' => 1,
        'markmap_enabled' => 1,
        'markmap_runtime' => 'assets/markmap/markmap-init.js',
    ];
}

add_action('wp_enqueue_scripts', function () {
    $o = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';
    if (!empty($o['markmap_enabled'])) {
        wp_enqueue_style('bac-markmap', $base . 'markmap/markmap.css', [], BAC_VERSION);
        wp_enqueue_script('bac-markmap-init', $base . 'markmap/markmap-init.js', [], BAC_VERSION, true);
    }
});

add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['markmap_enabled'])) return $content;
    $pattern = '/<pre[^>]*>\s*<code[^>]*class="[^"\\']*language-markmap[^"\\']*"[^>]*>(.*?)<\\/code>\s*<\\/pre>/si';
    return preg_replace_callback($pattern, function ($m) {
        $code = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$code) return $m[0];
        $code = preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = strip_tags($code);
        return '<div class="arcaea-markmap-box"><pre class="arcaea-markmap-source">'.esc_html($code).'</pre><svg class="arcaea-markmap-diagram"></svg></div>';
    }, $content);
});

add_shortcode('markmap', function ($atts, $content = null) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['markmap_enabled'])) return '';
    $content = html_entity_decode(trim((string)$content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = strip_tags($content);
    if (!$content) return '';
    return '<div class="arcaea-markmap-box"><pre class="arcaea-markmap-source">'.esc_html($content).'</pre><svg class="arcaea-markmap-diagram"></svg></div>';
});