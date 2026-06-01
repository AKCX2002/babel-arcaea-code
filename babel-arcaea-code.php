<?php
/**
 * Plugin Name: Babel Arcaea Code
 * Plugin URI: https://github.com/AKCX2002/babel-arcaea-code
 * Update URI: https://github.com/AKCX2002/babel-arcaea-code
 * Description: Unified Prism.js + Mermaid + MathJax + Markmap renderer. Local assets, no CDN by default. CI auto-syncs all assets. Replaces Sakurairo's built-in Prism.
 * Version: 1.6.18
 * Author: Babel36acl
 * License: GPL-2.0-or-later
 *
 * @package Babel_Arcaea_Code
 * @since   1.6.0  Single-entry architecture — legacies removed.
 */

defined('ABSPATH') || exit;

define('BAC_VERSION', '1.6.18');
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/* ── Core class loader (single source of truth) ── */
require_once __DIR__ . '/includes/class-bac-plugin.php';

/* ── Bootstrap ── */
\BabelArcaeaCode\Plugin::init();
