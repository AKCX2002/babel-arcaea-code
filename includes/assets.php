<?php
/**
 * Babel Arcaea Code — Asset Enqueuing & Utility Functions
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Utility: Safe asset URL with existence check ── */

/**
 * Get the full URL for a plugin asset, verifying the file exists.
 * Returns null if the file is missing.
 *
 * @param string $relative Relative path from plugin root (e.g. 'assets/prism/prism.js').
 * @return string|null Full URL, or null if file not found.
 */
function bac_asset_url($relative) {
    $path = BAC_PLUGIN_DIR . ltrim($relative, '/');
    if (!file_exists($path)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Babel Arcaea Code] Missing asset: ' . $relative);
        }
        return null;
    }
    return BAC_PLUGIN_URL . ltrim($relative, '/');
}

/**
 * Enqueue a plugin stylesheet only when the target file exists.
 */
function bac_enqueue_style_asset($handle, $relative, $deps = [], $ver = BAC_VERSION) {
    $url = bac_asset_url($relative);
    if (!$url) return false;
    wp_enqueue_style($handle, $url, $deps, $ver);
    return true;
}

/**
 * Enqueue a plugin script only when the target file exists.
 */
function bac_enqueue_script_asset($handle, $relative, $deps = [], $ver = BAC_VERSION, $in_footer = true) {
    $url = bac_asset_url($relative);
    if (!$url) return false;
    wp_enqueue_script($handle, $url, $deps, $ver, $in_footer);
    return true;
}

/* ── Utility: Bulk deregister theme/plugin handles ── */

/**
 * Safely dequeue and deregister multiple script or style handles.
 * Checks if the handle is actually enqueued/registered first.
 *
 * @param string $type    'style' or 'script'.
 * @param array  $handles List of handle names.
 */
function bac_disable_handles($type, array $handles) {
    foreach ($handles as $handle) {
        if ($type === 'style') {
            if (wp_style_is($handle, 'enqueued')) {
                wp_dequeue_style($handle);
            }
            if (wp_style_is($handle, 'registered')) {
                wp_deregister_style($handle);
            }
        } elseif ($type === 'script') {
            if (wp_script_is($handle, 'enqueued')) {
                wp_dequeue_script($handle);
            }
            if (wp_script_is($handle, 'registered')) {
                wp_deregister_script($handle);
            }
        }
    }
}

/* ── Prism CSS ── */

/**
 * Enqueue Prism CSS based on current options.
 */
function bac_enqueue_prism_css() {
    $o = bac_options();
    $theme = in_array($o['prism_theme'] ?? '', ['arcaea_dark', 'arcaea_light'], true)
        ? $o['prism_theme']
        : 'arcaea_dark';
    $theme_file = $theme === 'arcaea_light' ? 'arcaea-light.css' : 'arcaea-dark.css';

    bac_enqueue_style_asset('bac-prism-base', 'assets/prism/prism.css', [], BAC_VERSION);
    bac_enqueue_style_asset('bac-prism-toolbar', 'assets/prism/prism-toolbar.css', ['bac-prism-base'], BAC_VERSION);
    bac_enqueue_style_asset('bac-prism-arcaea-common', 'assets/prism/themes/arcaea-common.css', ['bac-prism-toolbar'], BAC_VERSION);
    bac_enqueue_style_asset('bac-prism-arcaea-theme', 'assets/prism/themes/' . $theme_file, ['bac-prism-arcaea-common'], BAC_VERSION);

    // Soft-wrap for code blocks (white-space: pre-wrap).
    // Must depend on bac-prism-arcaea-theme so it loads AFTER theme CSS and can override overflow.
    bac_enqueue_style_asset('bac-prism-wrap', 'assets/css/bac-prism-wrap.css', ['bac-prism-arcaea-theme'], BAC_VERSION);

    if ($o['prism_line_numbers']) {
        bac_enqueue_style_asset('bac-prism-ln', 'assets/prism/prism-line-numbers.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
        bac_enqueue_style_asset('bac-prism-lh', 'assets/prism/prism-line-highlight.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
    }

    if ($o['prism_previewers']) {
        bac_enqueue_style_asset('bac-prism-previewers', 'assets/prism/prism-previewers.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
        bac_enqueue_style_asset('bac-prism-previewers-arcaea', 'assets/prism/prism-previewers-arcaea.css', ['bac-prism-previewers'], BAC_VERSION);
    }
}

/* ── Prism JS ── */

/**
 * Enqueue Prism JS with correct loading order.
 */
function bac_enqueue_prism_js() {
    $o = bac_options();

    if (!bac_enqueue_script_asset('bac-prism-core', 'assets/prism/prism.js', [], BAC_VERSION, true)) {
        return;
    }

    bac_enqueue_script_asset('bac-prism-toolbar', 'assets/prism/prism-toolbar.js', ['bac-prism-core'], BAC_VERSION, true);
    bac_enqueue_script_asset('bac-prism-lang', 'assets/prism/prism-show-language.js', ['bac-prism-toolbar'], BAC_VERSION, true);

    if ($o['prism_line_numbers']) {
        bac_enqueue_script_asset('bac-prism-ln', 'assets/prism/prism-line-numbers.js', ['bac-prism-core'], BAC_VERSION, true);
        bac_enqueue_script_asset('bac-prism-lh', 'assets/prism/prism-line-highlight.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    if ($o['prism_braces']) {
        bac_enqueue_script_asset('bac-prism-braces', 'assets/prism/prism-match-braces.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    bac_enqueue_script_asset('bac-prism-norm', 'assets/prism/prism-normalize-whitespace.js', ['bac-prism-core'], BAC_VERSION, true);
    bac_enqueue_script_asset('bac-prism-cmd', 'assets/prism/prism-command-line.js', ['bac-prism-core'], BAC_VERSION, true);
    bac_enqueue_script_asset('bac-prism-tree', 'assets/prism/prism-treeview.js', ['bac-prism-core'], BAC_VERSION, true);

    if ($o['prism_previewers']) {
        bac_enqueue_script_asset('bac-prism-previewers', 'assets/prism/prism-previewers.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    if ($o['prism_copy']) {
        bac_enqueue_script_asset('bac-prism-copy', 'assets/prism/prism-copy.js', ['bac-prism-toolbar'], BAC_VERSION, true);
    }

    if (bac_enqueue_script_asset('bac-prism-autoloader', 'assets/prism/prism-autoloader.js', ['bac-prism-core'], BAC_VERSION, true)) {
        wp_localize_script('bac-prism-autoloader', 'BAC_Prism', [
            'langPath' => esc_url_raw(BAC_PLUGIN_URL . 'assets/prism/components/'),
        ]);
        wp_add_inline_script(
            'bac-prism-autoloader',
            'if(window.Prism&&Prism.plugins&&Prism.plugins.autoloader&&window.BAC_Prism){Prism.plugins.autoloader.languages_path=BAC_Prism.langPath;}',
            'after'
        );
    }
}

/* ── Medium-zoom ── */

/**
 * Enqueue medium-zoom.
 */
function bac_enqueue_medium_zoom() {
    bac_enqueue_script_asset('bac-medium-zoom', 'assets/js/medium-zoom.min.js', [], '1.1.0', true);
}

/* ── Frontend init script (Prism re-scan, PJAX, image zoom) ── */

/**
 * Enqueue the frontend bootstrap script and its dependencies.
 */
function bac_enqueue_frontend_init() {
    $o = bac_options();

    if (!$o['mermaid_enabled'] && !$o['prism_enabled']) {
        return;
    }

    $deps = [];
    if (!empty($o['prism_enabled']) && wp_script_is('bac-prism-core', 'registered')) {
        $deps[] = 'bac-prism-core';
    }
    if (wp_script_is('bac-medium-zoom', 'registered')) {
        $deps[] = 'bac-medium-zoom';
    }

    if (!bac_enqueue_script_asset('bac-mermaid-init', 'assets/mermaid/mermaid-init.js', $deps, BAC_VERSION, true)) {
        return;
    }

    wp_localize_script('bac-mermaid-init', 'BAC_Config', [
        'lineNumbers'    => !empty($o['prism_line_numbers']),
        'prismEnabled'   => !empty($o['prism_enabled']),
        'mermaidEnabled' => !empty($o['mermaid_enabled']),
    ]);

    if ($o['mermaid_enabled']) {
        bac_enqueue_style_asset('bac-mermaid', 'assets/mermaid/mermaid.css', [], BAC_VERSION);
        wp_localize_script('bac-mermaid-init', 'BAC_Mermaid', [
            'mermaidUrl' => esc_url_raw(BAC_PLUGIN_URL . 'assets/mermaid/mermaid.esm.min.mjs'),
        ]);
    }
}

/* ── Markmap frontend assets ── */

/**
 * Enqueue Markmap client-side assets (when pre-render is off).
 */
function bac_enqueue_markmap_assets() {
    $o = bac_options();

    if (empty($o['markmap_enabled'])) {
        return;
    }

    bac_enqueue_style_asset('bac-markmap', 'assets/markmap/markmap.css', [], BAC_VERSION);

    // Pre-rendered mode: no client-side JS needed.
    if (!empty($o['markmap_prerender'])) {
        return;
    }

    if (($o['markmap_runtime'] ?? 'local') === 'cdn') {
        wp_enqueue_script('bac-markmap-d3', 'https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js', [], '7.9.0', true);
        wp_enqueue_script('bac-markmap-view', 'https://cdn.jsdelivr.net/npm/markmap-view@0.18.12/dist/browser/index.js', ['bac-markmap-d3'], '0.18.12', true);
        wp_enqueue_script('bac-markmap-lib', 'https://cdn.jsdelivr.net/npm/markmap-lib@0.18.12/dist/browser/index.js', ['bac-markmap-view'], '0.18.12', true);
        bac_enqueue_script_asset('bac-markmap-init', 'assets/markmap/markmap-init.js', ['bac-markmap-lib'], BAC_VERSION, true);
    } else {
        if (!bac_enqueue_script_asset('bac-markmap-d3', 'assets/markmap/vendor/d3.min.js', [], BAC_VERSION, true)) return;
        if (!bac_enqueue_script_asset('bac-markmap-view', 'assets/markmap/vendor/markmap-view.min.js', ['bac-markmap-d3'], BAC_VERSION, true)) return;
        if (!bac_enqueue_script_asset('bac-markmap-lib', 'assets/markmap/vendor/markmap-lib.min.js', ['bac-markmap-view'], BAC_VERSION, true)) return;
        bac_enqueue_script_asset('bac-markmap-init', 'assets/markmap/markmap-init.js', ['bac-markmap-lib'], BAC_VERSION, true);
    }
}

/* ── MathJax ── */

/**
 * Enqueue MathJax assets.
 */
function bac_enqueue_mathjax() {
    $o = bac_options();

    if (!$o['mathjax_enabled']) {
        return;
    }

    add_action('wp_head', function () {
        echo '<script>window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]]},svg:{fontCache:"global"},options:{ignoreHtmlClass:"no-mathjax"}};</script>';
    }, 0);
    bac_enqueue_script_asset('bac-mathjax', 'assets/mathjax/es5/tex-chtml.js', [], BAC_VERSION, true);
}

/* ── Main enqueue action ── */

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;

    $o = bac_options();
    if (!$o['enabled']) return;

    // Prism.
    if ($o['prism_enabled']) {
        bac_enqueue_prism_css();
        bac_enqueue_prism_js();
    }

    // Medium-zoom must be registered before the frontend init dependency list is built.
    bac_enqueue_medium_zoom();

    // Frontend init + Mermaid.
    bac_enqueue_frontend_init();

    // Markmap.
    bac_enqueue_markmap_assets();

    // MathJax (delayed hook).
    bac_enqueue_mathjax();
});
