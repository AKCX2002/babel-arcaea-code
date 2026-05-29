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
 * @return array Associative array of health check items.
 */
function bac_health_check() {
    $health = [];

    // Prism core.
    $health['prism_core'] = bac_asset_url('assets/prism/prism.js') ? 'found' : 'missing';

    // Mermaid ESM.
    $health['mermaid_esm'] = bac_asset_url('assets/mermaid/mermaid.esm.min.mjs') ? 'found' : 'missing';

    // Mermaid chunks.
    $mermaid_chunks = glob(BAC_PLUGIN_DIR . 'assets/mermaid/chunks/mermaid.esm.min/*.mjs');
    $health['mermaid_chunks'] = count($mermaid_chunks) ? count($mermaid_chunks) . ' files' : 'missing';

    // Markmap vendor.
    $health['markmap_vendor'] = bac_asset_url('assets/markmap/vendor/d3.min.js') ? 'found' : 'missing';

    // Markmap render script.
    $render_script = BAC_PLUGIN_DIR . 'bin/markmap-render.js';
    $health['markmap_render_script'] = file_exists($render_script) ? 'found' : 'missing';

    // Node.js.
    $node = bac_find_node();
    if ($node) {
        $node_ver = @exec(escapeshellcmd($node) . ' --version');
        $health['node'] = 'found ' . $node . ($node_ver ? ' ' . $node_ver : '');
    } else {
        $health['node'] = 'not found';
    }

    // Cache directory.
    $cache_dir = bac_markmap_cache_dir();
    $health['cache_dir'] = (is_dir($cache_dir) && is_writable($cache_dir)) ? 'writable' : 'unavailable';

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
    <div style="background:rgba(15,24,42,0.12);padding:16px 20px;margin:16px 0;border-radius:8px;border:1px solid rgba(230,238,255,0.15);">
        <h3 style="margin-top:0">系统健康检查</h3>
        <table class="widefat striped" style="width:auto;min-width:400px">
            <thead><tr><th>项目</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($health as $label => $status): ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td style="color:<?php echo (strpos($status, 'not found') === false && strpos($status, 'missing') === false && strpos($status, 'disabled') === false && strpos($status, 'unavailable') === false) ? '#4caf50' : '#ef5350'; ?>">
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
