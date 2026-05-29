<?php
/**
 * Plugin Name: Babel Arcaea Code
 * Plugin URI: https://github.com/AKCX2002/babel-arcaea-code
 * Description: Unified Prism.js + Mermaid renderer. Local assets, no CDN. Replaces Sakurairo's built-in Prism.
 * Version: 1.0.0
 * Author: Babel36acl
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('BAC_VERSION', '1.0.0');
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/* ── Auto-updater (PUC) ── */
add_action('plugins_loaded', function () {
    $puc = BAC_PLUGIN_DIR . 'lib/plugin-update-checker.php';
    if (!file_exists($puc)) return;
    require_once $puc;
    $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
        'https://github.com/AKCX2002/babel-arcaea-code/', __FILE__, 'babel-arcaea-code');
    $uc->getVcsApi()->enableReleaseAssets();
    $token = getenv('GH_TOKEN') ?: getenv('GITHUB_TOKEN');
    if ($token) try { $uc->getVcsApi()->setAuthentication($token); } catch (\Exception $e) {}
});

/* ── Settings link ── */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . admin_url('options-general.php?page=babel-arcaea-code') . '">设置</a>');
    return $links;
});

/* ── Options ── */
function bac_defaults() {
    return [
        'enabled'           => 1,
        'prism_enabled'     => 1,
        'mermaid_enabled'   => 1,
        'mermaid_version'   => '11.15.0',
        'prism_line_numbers' => 1,
        'prism_copy'        => 1,
        'prism_theme'       => 'arcaea_dark',
        'disable_sakurairo_prism' => 1,
    ];
}

function bac_options() {
    return wp_parse_args(get_option('bac_options', []), bac_defaults());
}

add_action('admin_init', function () {
    register_setting('bac_settings_group', 'bac_options', function ($in) {
        $d = bac_defaults(); $out = [];
        $out['enabled'] = !empty($in['enabled']) ? 1 : 0;
        $out['prism_enabled'] = !empty($in['prism_enabled']) ? 1 : 0;
        $out['mermaid_enabled'] = !empty($in['mermaid_enabled']) ? 1 : 0;
        $out['mermaid_version'] = in_array($in['mermaid_version'] ?? '', ['11.15.0','11','10.9.6']) ? $in['mermaid_version'] : $d['mermaid_version'];
        $out['prism_line_numbers'] = !empty($in['prism_line_numbers']) ? 1 : 0;
        $out['prism_copy'] = !empty($in['prism_copy']) ? 1 : 0;
        $out['prism_theme'] = in_array($in['prism_theme'] ?? '', ['arcaea_dark','arcaea_light']) ? $in['prism_theme'] : $d['prism_theme'];
        $out['disable_sakurairo_prism'] = !empty($in['disable_sakurairo_prism']) ? 1 : 0;
        return $out;
    });
});

add_action('admin_menu', function () {
    add_options_page('Babel Arcaea Code', 'Arcaea Code', 'manage_options', 'babel-arcaea-code', function () {
        if (!current_user_can('manage_options')) return;
        $o = bac_options(); ?>
        <div class="wrap"><h1>Babel Arcaea Code</h1>
        <p>统一 Prism.js + Mermaid 渲染引擎。本地化资源，无 CDN 依赖。</p>
        <form method="post" action="options.php">
        <?php settings_fields('bac_settings_group'); ?>
        <table class="form-table">
            <tr><th>启用</th><td><label><input type="checkbox" name="bac_options[enabled]" value="1" <?php checked($o['enabled'],1); ?>> 总开关</label></td></tr>
            <tr><th>Prism.js</th><td><label><input type="checkbox" name="bac_options[prism_enabled]" value="1" <?php checked($o['prism_enabled'],1); ?>> 启用 Prism 代码高亮</label></td></tr>
            <tr><th>Mermaid</th><td><label><input type="checkbox" name="bac_options[mermaid_enabled]" value="1" <?php checked($o['mermaid_enabled'],1); ?>> 启用 Mermaid 图表</label></td></tr>
            <tr><th>Sakurairo Prism</th><td><label><input type="checkbox" name="bac_options[disable_sakurairo_prism]" value="1" <?php checked($o['disable_sakurairo_prism'],1); ?>> 禁用主题自带 Prism</label></td></tr>
            <tr><th>Prism 主题</th><td><select name="bac_options[prism_theme]">
                <option value="arcaea_dark" <?php selected($o['prism_theme'],'arcaea_dark'); ?>>Arcaea Dark</option>
                <option value="arcaea_light" <?php selected($o['prism_theme'],'arcaea_light'); ?>>Arcaea Light</option>
            </select></td></tr>
            <tr><th>行号</th><td><label><input type="checkbox" name="bac_options[prism_line_numbers]" value="1" <?php checked($o['prism_line_numbers'],1); ?>> 显示行号</label></td></tr>
            <tr><th>复制</th><td><label><input type="checkbox" name="bac_options[prism_copy]" value="1" <?php checked($o['prism_copy'],1); ?>> 代码块复制按钮</label></td></tr>
        </table>
        <?php submit_button(); ?>
        </form></div>
    <?php });
});

/* ── Disable Sakurairo's Prism ── */
add_action('wp_enqueue_scripts', function () {
    $o = bac_options();
    if (!$o['enabled'] || !$o['disable_sakurairo_prism']) return;
    wp_dequeue_style('prism-style');
    wp_dequeue_script('prism-script');
    wp_dequeue_style('prism-toolbar');
    wp_dequeue_script('prism-toolbar');
    wp_dequeue_style('prism-line-numbers');
    wp_dequeue_script('prism-line-numbers');
    wp_dequeue_style('prism-autoloader');
    wp_dequeue_script('prism-autoloader');
    // Sakurairo uses webpack-bundled names
    wp_dequeue_script('code-highlight');
    wp_dequeue_style('code-highlight');
}, 999);

/* ── Enqueue local Prism + Mermaid ── */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;
    $o = bac_options();
    if (!$o['enabled']) return;

    $base = BAC_PLUGIN_URL . 'assets/';

    // Prism CSS
    if ($o['prism_enabled']) {
        wp_enqueue_style('bac-prism', $base . 'prism/prism.css', [], BAC_VERSION);
        wp_enqueue_style('bac-prism-toolbar', $base . 'prism/prism-toolbar.css', [], BAC_VERSION);
        if ($o['prism_line_numbers']) {
            wp_enqueue_style('bac-prism-ln', $base . 'prism/prism-line-numbers.css', [], BAC_VERSION);
        }
    }

    // Prism JS (footer)
    if ($o['prism_enabled']) {
        wp_enqueue_script('bac-prism-core', $base . 'prism/prism.js', [], BAC_VERSION, true);
        wp_enqueue_script('bac-prism-autoloader', $base . 'prism/prism-autoloader.js', ['bac-prism-core'], BAC_VERSION, true);
        wp_enqueue_script('bac-prism-toolbar', $base . 'prism/prism-toolbar.js', ['bac-prism-core'], BAC_VERSION, true);
        if ($o['prism_copy']) {
            wp_enqueue_script('bac-prism-copy', $base . 'prism/prism-copy.js', ['bac-prism-toolbar'], BAC_VERSION, true);
        }
        if ($o['prism_line_numbers']) {
            wp_enqueue_script('bac-prism-ln', $base . 'prism/prism-line-numbers.js', ['bac-prism-core'], BAC_VERSION, true);
        }
        // Set autoloader language path
        wp_localize_script('bac-prism-autoloader', 'BAC_Prism', [
            'langPath' => $base . 'prism/components/',
        ]);
    }

    // Mermaid
    if ($o['mermaid_enabled']) {
        wp_enqueue_style('bac-mermaid', $base . 'mermaid/mermaid.css', [], BAC_VERSION);
        $mermaid_url = $base . 'mermaid/mermaid.esm.min.mjs';
        wp_enqueue_script('bac-mermaid-init', $base . 'mermaid/mermaid-init.js', [], BAC_VERSION, true);
        wp_localize_script('bac-mermaid-init', 'BAC_Mermaid', [
            'mermaidUrl' => $mermaid_url,
        ]);
    }
});

/* ── Mermaid PHP filter: convert code blocks in the_content ── */
add_filter('the_content', function ($content) {
    $o = bac_options();
    if (!$o['enabled'] || !$o['mermaid_enabled']) return $content;
    $pattern = '/<pre[^>]*>\s*<code[^>]*class="[^"]*language-mermaid[^"]*"[^>]*>(.*?)<\/code>\s*<\/pre>/si';
    return preg_replace_callback($pattern, function ($m) {
        $code = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$code) return $m[0];
        return '<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'
            . esc_html($code) . '</div></div>';
    }, $content);
}, 1);

/* ── Shortcode ── */
add_shortcode('mermaid', function ($atts, $content = null) {
    $content = html_entity_decode(trim((string)$content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!$content) return '';
    return '<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'
        . esc_html($content) . '</div></div>';
});

/* ── Autoloader path filter for Prism ── */
add_action('wp_footer', function () {
    if (!bac_options()['prism_enabled']) return;
    ?>
    <script>
    (function(){
    if(window.Prism&&Prism.plugins.autoloader){
    Prism.plugins.autoloader.languages_path=window.BAC_Prism?BAC_Prism.langPath:
    '<?php echo BAC_PLUGIN_URL; ?>assets/prism/components/';}})();
    </script>
    <?php
}, 1);
