<?php
/**
 * Babel Arcaea Code — Mermaid Renderer
 *
 * Converts Mermaid code blocks and shortcodes into rendered diagram containers.
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Mermaid PHP filter: convert code blocks in the_content ── */

/**
 * Convert Mermaid code blocks in post content to render-ready containers.
 * Runs at priority 11 (after wpautop) to strip <br /> inserted by wpautop.
 */
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || !$o['mermaid_enabled']) return $content;

    // Match language-mermaid, lang-mermaid, or bare mermaid class.
    $pattern = '/<pre[^>]*>\s*<code[^>]*class=(["\'])(?=[^"\']*\b(?:language-mermaid|lang-mermaid|mermaid)\b)[^"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';

    return preg_replace_callback($pattern, function ($m) {
        $code = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$code) return $m[0];

        // Strip <br /> that wpautop inserted inside the code block.
        $code = preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = strip_tags($code);

        return '<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'
            . esc_html($code)
            . '</div></div>';
    }, $content);
}, 11);

/* ── Mermaid shortcode ── */

add_shortcode('mermaid', function ($atts, $content = null) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['mermaid_enabled'])) return '';

    $content = html_entity_decode(trim((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = strip_tags($content);
    if (!$content) return '';

    return '<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'
        . esc_html($content)
        . '</div></div>';
});
