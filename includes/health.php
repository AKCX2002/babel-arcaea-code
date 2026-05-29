<?php
/**
 * Babel Arcaea Code — Health Check Utilities
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/**
 * Gather system health information for the admin dashboard.
 *
 * Every call is defensively wrapped so a single failure doesn't crash
 * the entire admin page.
 *
 * @return array Associative array of health check items.
 */
function bac_health_check() {
    $health = [];

    // Prism core.
    $health['prism_core'] = function_exists('bac_asset_url') && @bac_asset_url('assets/prism/prism.js') ? 'found' : 'missing';

    // Mermaid ESM.
    $health['mermaid_esm'] = function_exists('bac_asset_url') && @bac_asset_url('assets/mermaid/mermaid.esm.min.mjs') ? 'found' : 'missing';

    // Mermaid chunks.
    $mermaid_chunks = defined('BAC_PLUGIN_DIR') ? @glob(BAC_PLUGIN_DIR . 'assets/mermaid/chunks/mermaid.esm.min/*.mjs') : false;
    $health['mermaid_chunks'] = (is_array($mermaid_chunks) && count($mermaid_chunks))
        ? count($mermaid_chunks) . ' files'
        : 'missing';

    // Markmap vendor.
    $health['markmap_vendor'] = function_exists('bac_asset_url') && @bac_asset_url('assets/markmap/vendor/d3.min.js') ? 'found' : 'missing';

    // Markmap render script.
    $render_script = defined('BAC_PLUGIN_DIR') ? BAC_PLUGIN_DIR . 'bin/markmap-render.js' : '';
    $health['markmap_render_script'] = ($render_script && file_exists($render_script)) ? 'found' : 'missing';

    // Node.js.
    if (function_exists('bac_find_node')) {
        $node = bac_find_node();
        if ($node) {
            $node_ver = function_exists('exec') ? @exec(escapeshellcmd($node) . ' --version') : '';
            $health['node'] = 'found ' . $node . ($node_ver ? ' ' . trim($node_ver) : '');
        } else {
            $health['node'] = 'not found';
        }
    } else {
        $health['node'] = 'checker unavailable';
    }

    // Cache directory.
    if (function_exists('bac_markmap_cache_dir')) {
        $cache_dir = @bac_markmap_cache_dir();
        $health['cache_dir'] = ($cache_dir && is_dir($cache_dir) && is_writable($cache_dir)) ? 'writable' : 'unavailable';
    } else {
        $health['cache_dir'] = 'checker unavailable';
    }

    // proc_open availability.
    $health['proc_open'] = function_exists('proc_open') && function_exists('proc_close')
        ? 'available'
        : 'disabled';

    // Sakurairo detection.
    $theme = wp_get_theme();
    $health['sakurairo'] = ($theme->get_template() === 'Sakurairo' || $theme->get('Name') === 'Sakurairo')
        ? 'detected'
        : 'not detected';

    return $health;
}

/**
 * Render the health check table HTML.
 */
function bac_health_check_table() {
    $health = bac_health_check();
    ?>
    <div class="bac-health-check-panel">
        <h3>系统健康检查</h3>
        <table class="widefat striped" style="width:auto;min-width:400px">
            <thead><tr><th>项目</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($health as $label => $status):
                $is_ok = (strpos($status, 'not found') === false)
                    && (strpos($status, 'missing') === false)
                    && (strpos($status, 'disabled') === false)
                    && (strpos($status, 'unavailable') === false);
                ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td style="color:<?php echo $is_ok ? '#4caf50' : '#ef5350'; ?>">
                        <?php echo esc_html($status); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="margin-top:8px">如果资源标记为 missing，请重新运行 CI 同步或检查插件文件完整性。</p>
    </div>
    <?php
}
