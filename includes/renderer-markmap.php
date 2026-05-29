<?php
/**
 * Babel Arcaea Code — Markmap Renderer
 *
 * Converts Markmap code blocks and shortcodes into mindmap diagrams.
 * Supports:
 *   - Client-side rendering (default, JS runtime in browser)
 *   - Server-side pre-rendering (SVG via Node.js CLI, cached on save)
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Utility: Node.js locator ── */

/**
 * Locate a working Node.js binary by probing common paths.
 *
 * @return string|null Absolute path to node, or null.
 */
function bac_find_node() {
    $candidates = [
        getenv('BAC_NODE_BIN'),
        '/usr/bin/node',
        '/usr/local/bin/node',
        '/opt/homebrew/bin/node',
        'node',
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        $cmd  = escapeshellcmd($candidate) . ' --version';
        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);

        if ($code === 0 && !empty($output[0])) {
            return $candidate;
        }
    }

    return null;
}

/* ── Utility: SVG sanitizer ── */

/**
 * Lightweight SVG sanitization to strip XSS vectors.
 *
 * @param string $svg Raw SVG markup.
 * @return string Sanitized SVG markup.
 */
function bac_sanitize_svg($svg) {
    if (!is_string($svg) || stripos($svg, '<svg') === false) {
        return '';
    }

    // Remove <script>...</script> blocks.
    $svg = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $svg);

    // Remove on* event handler attributes.
    $svg = preg_replace('/\son\w+="[^"]*"/i', '', $svg);
    $svg = preg_replace("/\son\w+='[^']*'/i", '', $svg);

    // Remove javascript: pseudo-protocol.
    $svg = preg_replace('/javascript\s*:/i', '', $svg);

    return $svg;
}

/* ── Cache directory ── */

/**
 * Get the cache directory path for pre-rendered SVGs.
 * Uses WordPress uploads directory to persist across plugin updates.
 *
 * @return string Absolute path to cache directory.
 */
function bac_markmap_cache_dir() {
    $upload_dir = wp_upload_dir();
    $dir = $upload_dir['basedir'] . '/bac-markmap-cache';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    return $dir;
}

/* ── Core render engine ── */

/**
 * Render markmap content to SVG using Node.js CLI.
 * Caches results by content hash for fast subsequent loads.
 *
 * @param string $content Markdown markmap content.
 * @return string|null SVG markup, or null on failure.
 */
function bac_markmap_render_svg($content) {
    $cache_dir  = bac_markmap_cache_dir();
    $hash       = md5($content);
    $cache_file = $cache_dir . '/' . $hash . '.svg';

    // Serve from cache if available.
    if (file_exists($cache_file)) {
        $svg = file_get_contents($cache_file);
        if ($svg !== false && strpos($svg, '<svg') !== false) {
            return bac_sanitize_svg($svg);
        }
    }

    // Locate the Node.js render script.
    $render_script = BAC_PLUGIN_DIR . 'bin/markmap-render.js';
    if (!file_exists($render_script)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Babel Arcaea Code] Markmap render script not found: ' . $render_script);
        }
        return null;
    }

    // Locate node executable.
    $node = bac_find_node();
    if (!$node) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Babel Arcaea Code] No working Node.js binary found.');
        }
        return null;
    }

    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $cmd = escapeshellcmd($node) . ' ' . escapeshellarg($render_script) . ' --theme arcaea-dark';
    /** Filter the proc_open command for Markmap pre-rendering. */
    $cmd = apply_filters('bac_markmap_render_cmd', $cmd);

    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Babel Arcaea Code] proc_open failed for Markmap render.');
        }
        return null;
    }

    // Non-blocking streams.
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    fwrite($pipes[0], $content);
    fclose($pipes[0]);

    // Polling read with safety timeout.
    $svg      = '';
    $err      = '';
    $timeout  = 15;
    $elapsed  = 0;
    $interval = 100000; // 0.1s

    while ($elapsed < $timeout) {
        $r = stream_get_contents($pipes[1]);
        if ($r !== false) { $svg .= $r; }
        $e = stream_get_contents($pipes[2]);
        if ($e !== false) { $err .= $e; }

        if (!is_resource($proc)) break;

        $status = @proc_get_status($proc);
        if ($status !== false && !$status['running']) {
            $r = stream_get_contents($pipes[1]);
            if ($r !== false) { $svg .= $r; }
            $e = stream_get_contents($pipes[2]);
            if ($e !== false) { $err .= $e; }
            break;
        }

        usleep($interval);
        $elapsed += $interval / 1000000;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($proc);

    if ($exit_code !== 0 || !$svg || strpos($svg, '<svg') === false) {
        if (defined('WP_DEBUG') && WP_DEBUG && $err) {
            error_log('[Babel Arcaea Code] Markmap render stderr: ' . $err);
        }
        return null;
    }

    $svg = bac_sanitize_svg($svg);
    if (!$svg) return null;

    file_put_contents($cache_file, $svg);
    return $svg;
}

/* ── Cache management ── */

/**
 * Clear all cached pre-rendered SVGs.
 */
function bac_markmap_clear_cache() {
    $cache_dir = bac_markmap_cache_dir();
    if (!is_dir($cache_dir)) return;
    foreach (glob($cache_dir . '/*.svg') as $f) {
        @unlink($f);
    }
}

add_action('update_option_bac_options', function () {
    bac_markmap_clear_cache();
});

/* ── v1.5.0: save_post pre-render ── */

/**
 * Extract all markmap content blocks from a post and pre-render them as SVGs.
 * This shifts the rendering cost from front-end (the_content) to post-save time.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function bac_markmap_prerender_on_save($post_id, $post, $update) {
    // Only process when pre-render is enabled.
    $o = bac_options();
    if (!$o['enabled'] || empty($o['markmap_enabled']) || empty($o['markmap_prerender'])) {
        return;
    }

    // Only for published posts/pages.
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_status !== 'publish') {
        return;
    }

    $content = $post->post_content;
    if (!$content) return;

    // Check user capability.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Extract markmap content from code blocks.
    $blocks = [];
    $pattern = '/<pre[^>]*>\s*<code[^>]*class=(["\'])(?=[^"\']*\b(?:language-markmap|lang-markmap|markmap)\b)[^"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';
    preg_match_all($pattern, $content, $code_matches, PREG_SET_ORDER);
    foreach ($code_matches as $m) {
        $code = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($code) {
            $blocks[] = $code;
        }
    }

    // Extract markmap content from shortcodes.
    $shortcode_pattern = '/\[markmap\](.*?)\[\/markmap\]/si';
    preg_match_all($shortcode_pattern, $content, $sc_matches, PREG_SET_ORDER);
    foreach ($sc_matches as $m) {
        $code = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($code) {
            $blocks[] = $code;
        }
    }

    // Deduplicate and render.
    $seen = [];
    foreach ($blocks as $block) {
        $hash = md5($block);
        if (isset($seen[$hash])) continue;
        $seen[$hash] = true;

        bac_markmap_render_svg($block);
    }
}

add_action('save_post', 'bac_markmap_prerender_on_save', 10, 3);

/* ── the_content filter: serve pre-rendered SVG or client-side fallback ── */
// Priority 11 to match original behavior — run after wpautop.
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['markmap_enabled'])) return $content;

    // Match language-markmap, lang-markmap, or bare markmap class.
    // Priority 11 ensures we run after wpautop so we can strip its <br /> tags.
// priority 11
    $pattern = '/<pre[^>]*>\s*<code[^>]*class=(["\'])(?=[^"\']*\b(?:language-markmap|lang-markmap|markmap)\b)[^"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';

    return preg_replace_callback($pattern, function ($m) {
        $code = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$code) return $m[0];

        $code = preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = strip_tags($code);

        $o = bac_options();

        // v1.5.0: Try cache first (pre-rendered on save_post).
        if (!empty($o['markmap_prerender'])) {
            $cached = bac_markmap_render_svg($code);
            if ($cached) {
                return '<div class="arcaea-markmap-box arcaea-markmap-prerendered">'
                    . $cached
                    . '</div>';
            }
            // Cache miss — fall through to client-side render.
        }

        return '<div class="arcaea-markmap-box">'
            . '<pre class="arcaea-markmap-source">' . esc_html($code) . '</pre>'
            . '<svg class="arcaea-markmap-diagram"></svg>'
            . '</div>';
    }, $content);
}, 11);

/* ── Markmap shortcode ── */

add_shortcode('markmap', function ($atts, $content = null) {
    $o = bac_options();
    if (!$o['enabled'] || empty($o['markmap_enabled'])) return '';

    $content = html_entity_decode(trim((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = strip_tags($content);
    if (!$content) return '';

    // v1.5.0: Use cached SVG if available.
    if (!empty($o['markmap_prerender'])) {
        $cached = bac_markmap_render_svg($content);
        if ($cached) {
            return '<div class="arcaea-markmap-box arcaea-markmap-prerendered">'
                . $cached
                . '</div>';
        }
    }

    return '<div class="arcaea-markmap-box">'
        . '<pre class="arcaea-markmap-source">' . esc_html($content) . '</pre>'
        . '<svg class="arcaea-markmap-diagram"></svg>'
        . '</div>';
});
