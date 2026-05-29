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
        return null;
    }
    return BAC_PLUGIN_URL . ltrim($relative, '/');
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
    $o   = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';
    $theme = in_array($o['prism_theme'] ?? '', ['arcaea_dark', 'arcaea_light'], true)
        ? $o['prism_theme']
        : 'arcaea_dark';
    $theme_file = $theme === 'arcaea_light' ? 'arcaea-light.css' : 'arcaea-dark.css';

    wp_enqueue_style('bac-prism-base', $base . 'prism/prism.css', [], BAC_VERSION);
    wp_enqueue_style('bac-prism-toolbar', $base . 'prism/prism-toolbar.css', ['bac-prism-base'], BAC_VERSION);
    wp_enqueue_style('bac-prism-arcaea-common', $base . 'prism/themes/arcaea-common.css', ['bac-prism-toolbar'], BAC_VERSION);
    wp_enqueue_style('bac-prism-arcaea-theme', $base . 'prism/themes/' . $theme_file, ['bac-prism-arcaea-common'], BAC_VERSION);

    if ($o['prism_line_numbers']) {
        wp_enqueue_style('bac-prism-ln', $base . 'prism/prism-line-numbers.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
        wp_enqueue_style('bac-prism-lh', $base . 'prism/prism-line-highlight.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
    }

    if ($o['prism_previewers']) {
        wp_enqueue_style('bac-prism-previewers', $base . 'prism/prism-previewers.css', ['bac-prism-arcaea-theme'], BAC_VERSION);
        wp_enqueue_style('bac-prism-previewers-arcaea', $base . 'prism/prism-previewers-arcaea.css', ['bac-prism-previewers'], BAC_VERSION);
    }
}

/* ── Prism JS ── */

/**
 * Enqueue Prism JS with correct loading order.
 */
function bac_enqueue_prism_js() {
    $o    = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';

    wp_enqueue_script('bac-prism-core', $base . 'prism/prism.js', [], BAC_VERSION, true);
    wp_enqueue_script('bac-prism-toolbar', $base . 'prism/prism-toolbar.js', ['bac-prism-core'], BAC_VERSION, true);
    wp_enqueue_script('bac-prism-lang', $base . 'prism/prism-show-language.js', ['bac-prism-toolbar'], BAC_VERSION, true);

    if ($o['prism_line_numbers']) {
        wp_enqueue_script('bac-prism-ln', $base . 'prism/prism-line-numbers.js', ['bac-prism-core'], BAC_VERSION, true);
        wp_enqueue_script('bac-prism-lh', $base . 'prism/prism-line-highlight.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    if ($o['prism_braces']) {
        wp_enqueue_script('bac-prism-braces', $base . 'prism/prism-match-braces.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    wp_enqueue_script('bac-prism-norm', $base . 'prism/prism-normalize-whitespace.js', ['bac-prism-core'], BAC_VERSION, true);
    wp_enqueue_script('bac-prism-cmd', $base . 'prism/prism-command-line.js', ['bac-prism-core'], BAC_VERSION, true);
    wp_enqueue_script('bac-prism-tree', $base . 'prism/prism-treeview.js', ['bac-prism-core'], BAC_VERSION, true);

    if ($o['prism_previewers']) {
        wp_enqueue_script('bac-prism-previewers', $base . 'prism/prism-previewers.js', ['bac-prism-core'], BAC_VERSION, true);
    }

    if ($o['prism_copy']) {
        wp_enqueue_script('bac-prism-copy', $base . 'prism/prism-copy.js', ['bac-prism-toolbar'], BAC_VERSION, true);
    }

    wp_enqueue_script('bac-prism-autoloader', $base . 'prism/prism-autoloader.js', ['bac-prism-core'], BAC_VERSION, true);
    wp_localize_script('bac-prism-autoloader', 'BAC_Prism', [
        'langPath' => esc_url_raw($base . 'prism/components/'),
    ]);
    wp_add_inline_script(
        'bac-prism-autoloader',
        'if(window.Prism&&Prism.plugins&&Prism.plugins.autoloader&&window.BAC_Prism){Prism.plugins.autoloader.languages_path=BAC_Prism.langPath;}',
        'after'
    );
}

/* ── Frontend init script (Prism re-scan, PJAX, image zoom) ── */

/**
 * Enqueue the frontend bootstrap script and its dependencies.
 */
function bac_enqueue_frontend_init() {
    $o    = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';

    // Mermaid bootstrap also owns Prism re-scan, PJAX re-init and image zoom.
    if ($o['mermaid_enabled'] || $o['prism_enabled']) {
        wp_enqueue_script('bac-mermaid-init', $base . 'mermaid/mermaid-init.js', [], BAC_VERSION, true);
        wp_localize_script('bac-mermaid-init', 'BAC_Config', [
            'lineNumbers' => !empty($o['prism_line_numbers']),
        ]);
    }

    if ($o['mermaid_enabled']) {
        wp_enqueue_style('bac-mermaid', $base . 'mermaid/mermaid.css', [], BAC_VERSION);
        wp_localize_script('bac-mermaid-init', 'BAC_Mermaid', [
            'mermaidUrl' => esc_url_raw($base . 'mermaid/mermaid.esm.min.mjs'),
        ]);
    }
}

/* ── Markmap frontend assets ── */

/**
 * Enqueue Markmap client-side assets (when pre-render is off).
 */
function bac_enqueue_markmap_assets() {
    $o    = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';

    if (empty($o['markmap_enabled'])) {
        return;
    }

    wp_enqueue_style('bac-markmap', $base . 'markmap/markmap.css', [], BAC_VERSION);

    // Pre-rendered mode: no client-side JS needed.
    if (!empty($o['markmap_prerender'])) {
        return;
    }

    if (($o['markmap_runtime'] ?? 'local') === 'cdn') {
        wp_enqueue_script('bac-markmap-d3', 'https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js', [], '7.9.0', true);
        wp_enqueue_script('bac-markmap-view', 'https://cdn.jsdelivr.net/npm/markmap-view@0.18.12/dist/browser/index.js', ['bac-markmap-d3'], '0.18.12', true);
        wp_enqueue_script('bac-markmap-lib', 'https://cdn.jsdelivr.net/npm/markmap-lib@0.18.12/dist/browser/index.js', ['bac-markmap-view'], '0.18.12', true);
        wp_enqueue_script('bac-markmap-init', $base . 'markmap/markmap-init.js', ['bac-markmap-lib'], BAC_VERSION, true);
    } else {
        wp_enqueue_script('bac-markmap-d3', $base . 'markmap/vendor/d3.min.js', [], BAC_VERSION, true);
        wp_enqueue_script('bac-markmap-view', $base . 'markmap/vendor/markmap-view.min.js', ['bac-markmap-d3'], BAC_VERSION, true);
        wp_enqueue_script('bac-markmap-lib', $base . 'markmap/vendor/markmap-lib.min.js', ['bac-markmap-view'], BAC_VERSION, true);
        wp_enqueue_script('bac-markmap-init', $base . 'markmap/markmap-init.js', ['bac-markmap-lib'], BAC_VERSION, true);
    }
}

/* ── Medium-zoom ── */

/**
 * Enqueue medium-zoom and ensure it's a dependency of the frontend init script.
 */
function bac_enqueue_medium_zoom() {
    $base = BAC_PLUGIN_URL . 'assets/';

    wp_enqueue_script('bac-medium-zoom', $base . 'js/medium-zoom.min.js', [], '1.1.0', true);

    add_filter('script_loader_tag', function ($tag, $handle) {
        if ($handle === 'bac-mermaid-init') {
            global $wp_scripts;
            $init = $wp_scripts->query('bac-mermaid-init');
            if ($init && !in_array('bac-medium-zoom', $init->deps, true)) {
                $init->deps[] = 'bac-medium-zoom';
            }
        }
        return $tag;
    }, 10, 2);
}

/* ── MathJax ── */

/**
 * Enqueue MathJax assets.
 */
function bac_enqueue_mathjax() {
    $o    = bac_options();
    $base = BAC_PLUGIN_URL . 'assets/';

    if (!$o['mathjax_enabled']) {
        return;
    }

    add_action('wp_head', function () {
        echo '<script>window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]]},svg:{fontCache:"global"},options:{ignoreHtmlClass:"no-mathjax"}};</script>';
    }, 0);
    wp_enqueue_script('bac-mathjax', $base . 'mathjax/es5/tex-chtml.js', [], BAC_VERSION, true);
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

    // Frontend init + Mermaid.
    bac_enqueue_frontend_init();

    // Markmap.
    bac_enqueue_markmap_assets();

    // Medium-zoom.
    bac_enqueue_medium_zoom();

    // MathJax (delayed hook).
    bac_enqueue_mathjax();
});
