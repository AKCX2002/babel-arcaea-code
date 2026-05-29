<?php
/**
 * Plugin Name: Babel Arcaea Code
 * Plugin URI: https://github.com/AKCX2002/babel-arcaea-code
 * Description: Unified Prism.js + Mermaid + MathJax + Markmap renderer. Local assets, no CDN by default. CI auto-syncs all assets. Replaces Sakurairo's built-in Prism.
 * Version: 1.4.12
 * Author: Babel36acl
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('BAC_VERSION', '1.4.12');
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/* ── Auto-updater (PUC) ── */
add_action('plugins_loaded', function () {
    $puc = BAC_PLUGIN_DIR . 'lib/plugin-update-checker.php';
    if (!file_exists($puc)) return;

    require_once $puc;

    $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
        'https://github.com/AKCX2002/babel-arcaea-code/',
        __FILE__,
        'babel-arcaea-code'
    );
    $uc->getVcsApi()->enableReleaseAssets();

    if (defined('BAC_ENABLE_GITHUB_TOKEN') && BAC_ENABLE_GITHUB_TOKEN) {
        $token = getenv('GH_TOKEN') ?: getenv('GITHUB_TOKEN');

        if ($token) {
            try {
                $uc->getVcsApi()->setAuthentication($token);
            } catch (\Exception $e) {
                // Keep plugin boot safe even if PUC auth fails.
            }
        }
    }
});

/* ── Settings link ── */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift(
        $links,
        '<a href="' . esc_url(admin_url('admin.php?page=bac-panel')) . '">设置</a>'
    );
    return $links;
});

/* ── Module loader ── */
$bac_includes = [
    'includes/options.php',
    'includes/assets.php',
    'includes/headers.php',
    'includes/renderer-mermaid.php',
    'includes/renderer-markmap.php',
    'includes/compat-sakurairo.php',
    'includes/health.php',
];

foreach ($bac_includes as $bac_inc) {
    $bac_path = BAC_PLUGIN_DIR . $bac_inc;
    if (file_exists($bac_path)) {
        require_once $bac_path;
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Babel Arcaea Code] Missing include: ' . $bac_path);
    }
}

// Admin page (only in admin context).
if (is_admin()) {
    $bac_admin = BAC_PLUGIN_DIR . 'includes/admin.php';
    if (file_exists($bac_admin)) {
        require_once $bac_admin;
    }
}