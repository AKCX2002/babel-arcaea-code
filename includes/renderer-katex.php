<?php
/**
 * Babel Arcaea Code — KaTeX Renderer
 *
 * Server-side normalization of KaTeX math blocks and asset enqueuing.
 * KaTeX runs client-side via auto-render; this file handles the PHP side:
 *   - Content normalization (protect math from wpautop)
 *   - Shortcode support
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── KaTeX content normalization (priority 0, before wpautop) ── */

/**
 * Protect KaTeX delimiters from wpautop mangling.
 * wpautop adds <br/> and <p> tags that break KaTeX rendering.
 * This wraps display math in <div> and inline math in <span> to
 * prevent wpautop from inserting breaks inside math expressions.
 */
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['katex_enabled'])) return $content;

    // Protect display math: $$ ... $$ → <div class="katex-display">$$ ... $$</div>
    $content = preg_replace_callback(
        '/\$\$([\s\S]*?)\$\$/',
        function ($m) {
            return '<div class="katex-display">$$' . $m[1] . '$$</div>';
        },
        $content
    );

    return $content;
}, 0);

/* ── KaTeX shortcode ── */

add_shortcode('katex', function ($atts, $content = null) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['katex_enabled'])) return '';

    $display = !empty($atts['display']);
    $content = html_entity_decode(trim((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!$content) return '';

    if ($display) {
        return '<div class="katex-display">$$' . esc_html($content) . '$$</div>';
    }
    return '<span class="katex-inline">$' . esc_html($content) . '$</span>';
});
