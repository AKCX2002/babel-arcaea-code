<?php
/**
 * Plugin Name: Babel Arcaea Code
 * Plugin URI: https://github.com/AKCX2002/babel-arcaea-code
 * Description: Unified Prism.js + Mermaid + MathJax + Markmap renderer. Local assets, no CDN by default. CI auto-syncs all assets. Replaces Sakurairo's built-in Prism.
 * Version: 1.5.1
 * Author: Babel36acl
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('BAC_VERSION', '1.5.1');
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/* ── v1.5.0: Class-based core ── */
require_once __DIR__ . '/includes/class-bac-plugin.php';
require_once __DIR__ . '/includes/class-bac-options.php';
require_once __DIR__ . '/includes/class-bac-assets.php';
require_once __DIR__ . '/includes/class-bac-renderer.php';
require_once __DIR__ . '/includes/class-bac-compat.php';
require_once __DIR__ . '/includes/class-bac-headers.php';
require_once __DIR__ . '/includes/class-bac-health.php';

\BabelArcaeaCode\Plugin::init();
new \BabelArcaeaCode\Assets();
new \BabelArcaeaCode\Renderer();
new \BabelArcaeaCode\Compat();
new \BabelArcaeaCode\Headers();

if (is_admin()) {
    require_once __DIR__ . '/includes/class-bac-admin.php';
    new \BabelArcaeaCode\Admin();
}

/* ── Legacy procedural includes (backward compatibility) ── */
$bac_includes = ['includes/options.php','includes/assets.php','includes/headers.php','includes/renderer-mermaid.php','includes/renderer-markmap.php','includes/compat-sakurairo.php','includes/health.php'];
foreach ($bac_includes as $inc) {
    $path = BAC_PLUGIN_DIR . $inc;
    if (file_exists($path)) require_once $path;
}
if (is_admin()) { $a = BAC_PLUGIN_DIR . 'includes/admin.php'; if (file_exists($a)) require_once $a; }