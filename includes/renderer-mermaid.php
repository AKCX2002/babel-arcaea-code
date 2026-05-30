<?php
/**
 * Babel Arcaea Code — Mermaid Renderer
 *
 * Converts Mermaid code blocks and shortcodes into rendered diagram containers.
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Code block normalization (priority 0, before wpautop) ── */

/**
 * Normalize bare <pre> tags (without <code> child) into <pre><code class="language-xxx"> format.
 * Sakurairo theme strips <code> from <pre>; this filter adds it back.
 * Runs at priority 0, before wpautop(10) and Mermaid filter(11).
 */
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled']) return $content;

    // Match <pre> whose direct content does NOT start with an HTML tag
    // (i.e., bare <pre>text</pre> without <code> or any other child element).
    $pattern = '/<pre(\s[^>]*)?>\s*(?!\s*<)(.*?)\s*<\/pre>/si';

    return preg_replace_callback($pattern, function ($m) {
        $attrs = $m[1] ?? '';
        $inner = $m[2] ?? '';

        // Extract language from <pre> class if present.
        $langClass = 'language-text';
        if (preg_match('/class=["\']([^"\']*)["\']/i', $attrs, $cm)) {
            $classes = $cm[1];
            if (preg_match('/(?:^|\s)(?:language-|lang-)([a-z0-9_+#.-]+)/i', $classes, $lm)) {
                $langClass = 'language-' . strtolower($lm[1]);
            } elseif (preg_match('/(?:^|\s)(dart|flutter|bash|sh|python|js|javascript|ts|typescript|html|css|json|yaml|xml|sql|php|ruby|rust|go|java|c|cpp|csharp|swift|kotlin|mermaid|markmap)(?:\s|$)/i', $classes, $lm)) {
                $langClass = 'language-' . strtolower($lm[1]);
            }
        }

        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<pre' . $attrs . '><code class="' . esc_attr($langClass) . '">' . $inner . '</code></pre>';
    }, $content);
}, 0);

/* ── Mermaid PHP filter: convert code blocks in the_content ── */

/**
 * Convert Mermaid code blocks in post content to render-ready containers.
 * Runs at priority 11 (after wpautop) to strip <br /> inserted by wpautop.
 */
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || !$o['mermaid_enabled']) return $content;

    // Match language-mermaid, lang-mermaid, or bare mermaid class.
    // Pattern 1: <pre><code class="...language-mermaid...">
    // Pattern 2: <pre class="...mermaid..."> (bare, without <code> — fallback if normalize fails)
    $patterns = [
        '/<pre[^>]*>\s*<code[^>]*class=(["\'])(?=[^"\']*\b(?:language-mermaid|lang-mermaid|mermaid)\b)[^"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si',
        '/<pre[^>]*class=(["\'])(?=[^"\']*\b(?:language-mermaid|lang-mermaid|mermaid)\b)[^"\']*\1[^>]*>(.*?)<\/pre>/si',
    ];

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function ($m) {
            // Pattern 1 returns m[2] as code content, Pattern 2 returns m[2] as pre content
            $code = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!$code) return $m[0];

            // Strip <br /> that wpautop inserted inside the code block.
            $code = preg_replace('/<br\s*\/?>/i', "\n", $code);
            $code = strip_tags($code);
            if (!$code) return $m[0];

            return '<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'
                . esc_html($code)
                . '</div></div>';
        }, $content);
    }

    return $content;
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
