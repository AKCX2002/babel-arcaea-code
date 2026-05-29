<?php
/**
 * Babel Arcaea Code — Options & Settings API
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/**
 * Return default option values.
 *
 * @return array
 */
function bac_defaults() {
    return [
        'enabled'                     => 1,
        'prism_enabled'               => 1,
        'mermaid_enabled'             => 1,
        'mathjax_enabled'             => 0,
        'markmap_enabled'             => 0,
        'markmap_runtime'             => 'local',
        'markmap_prerender'           => 0,
        'mermaid_version'             => '11.15.0',
        'prism_version'               => '1.30.0',
        'mathjax_version'             => '3.2.2',
        'prism_line_numbers'          => 1,
        'prism_copy'                  => 1,
        'prism_braces'                => 1,
        'prism_previewers'            => 1,
        'prism_theme'                 => 'arcaea_dark',
        'disable_sakurairo_prism'     => 1,
        'aplayer_safe_patch'          => 0,
        'suppress_lightgallery_warn'  => 0,
    ];
}

/**
 * Get merged plugin options (saved + defaults).
 *
 * @return array
 */
function bac_options() {
    return wp_parse_args(get_option('bac_options', []), bac_defaults());
}

/**
 * Sanitize and validate all plugin options.
 *
 * @param mixed $in Raw input array.
 * @return array Sanitized options.
 */
function bac_sanitize_options($in) {
    $in = is_array($in) ? $in : [];
    $d = bac_defaults();
    $out = [];

    $out['enabled']                 = !empty($in['enabled']) ? 1 : 0;
    $out['prism_enabled']           = !empty($in['prism_enabled']) ? 1 : 0;
    $out['mermaid_enabled']         = !empty($in['mermaid_enabled']) ? 1 : 0;
    $out['mathjax_enabled']         = !empty($in['mathjax_enabled']) ? 1 : 0;
    $out['markmap_enabled']         = !empty($in['markmap_enabled']) ? 1 : 0;
    $out['markmap_runtime']         = in_array($in['markmap_runtime'] ?? '', ['cdn', 'local'], true)
        ? sanitize_key($in['markmap_runtime'])
        : $d['markmap_runtime'];
    $out['markmap_prerender']       = !empty($in['markmap_prerender']) ? 1 : 0;

    $out['mermaid_version'] = in_array($in['mermaid_version'] ?? '', ['11.15.0'], true)
        ? sanitize_text_field($in['mermaid_version'])
        : $d['mermaid_version'];
    $out['prism_version']   = $d['prism_version'];
    $out['mathjax_version'] = $d['mathjax_version'];

    $out['prism_line_numbers']      = !empty($in['prism_line_numbers']) ? 1 : 0;
    $out['prism_copy']              = !empty($in['prism_copy']) ? 1 : 0;
    $out['prism_braces']            = !empty($in['prism_braces']) ? 1 : 0;
    $out['prism_previewers']        = !empty($in['prism_previewers']) ? 1 : 0;
    $out['prism_theme']             = in_array($in['prism_theme'] ?? '', ['arcaea_dark', 'arcaea_light'], true)
        ? sanitize_key($in['prism_theme'])
        : $d['prism_theme'];
    $out['disable_sakurairo_prism'] = !empty($in['disable_sakurairo_prism']) ? 1 : 0;
    $out['aplayer_safe_patch']      = !empty($in['aplayer_safe_patch']) ? 1 : 0;
    $out['suppress_lightgallery_warn'] = !empty($in['suppress_lightgallery_warn']) ? 1 : 0;

    return $out;
}

// Register settings.
add_action('admin_init', function () {
    register_setting('bac_settings_group', 'bac_options', [
        'type'              => 'array',
        'sanitize_callback' => 'bac_sanitize_options',
        'default'           => bac_defaults(),
    ]);
});
