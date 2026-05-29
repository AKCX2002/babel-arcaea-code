<?php
/**
 * Babel Arcaea Code — Admin Settings Page
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Enqueue admin styles ── */
add_action('admin_enqueue_scripts', function ($hook) {
    // Only load on our plugin pages (top-level or settings submenu).
    if (strpos($hook, 'bac-panel') === false && strpos($hook, 'bac-options') === false) {
        return;
    }
    $css = BAC_PLUGIN_URL . 'assets/css/bac-admin.css';
    $ver = defined('BAC_VERSION') ? BAC_VERSION : '1.0.0';
    wp_enqueue_style('bac-admin', $css, [], $ver);
});

add_action('admin_menu', function () {
    // Primary standalone entry using 'bac-panel' slug — short enough to
    // avoid WAF false positives, unique enough to avoid slug conflicts.
    add_menu_page(
        'Babel Arcaea Code',
        'Arcaea Code',
        'manage_options',
        'bac-panel',
        'bac_admin_page_render',
        'dashicons-editor-code',
        81
    );

    // Secondary Settings entry with a different slug to avoid duplicate slug
    // conflicts and provide a fallback route.
    add_options_page(
        'Babel Arcaea Code',
        'Arcaea Code',
        'manage_options',
        'bac-options',
        'bac_admin_page_render'
    );
});

/**
 * Render the plugin settings page.
 */
function bac_admin_page_render() {
    if (!current_user_can('manage_options')) return;

    $o     = bac_options();
    $theme = wp_get_theme();
    ?>
    <div class="wrap"><h1>Babel Arcaea Code</h1>
    <p>统一 Prism.js + Mermaid + MathJax + Markmap 渲染引擎。本地化资源优先，CDN 仅用于 Markmap 调试模式。</p>

    <?php
    // Wrap health check in output buffering so any PHP warnings from
    // file/exec checks don't leak into the page markup.
    if (function_exists('bac_health_check_table')) {
        ob_start();
        bac_health_check_table();
        $health_html = ob_get_clean();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $health_html;
    } else {
        echo '<div class="notice notice-warning"><p>Babel Arcaea Code 健康检查模块未加载，但设置页仍可使用。</p></div>';
    }
    ?>

    <form method="post" action="options.php">
    <?php settings_fields('bac_settings_group'); ?>
    <table class="form-table">
        <tr><th>启用</th><td><label for="bac-opt-enabled"><input type="checkbox" id="bac-opt-enabled" name="bac_options[enabled]" value="1" <?php checked($o['enabled'], 1); ?>> 总开关</label></td></tr>

        <tr><th>Prism.js</th><td><label for="bac-opt-prism-enabled"><input type="checkbox" id="bac-opt-prism-enabled" name="bac_options[prism_enabled]" value="1" <?php checked($o['prism_enabled'], 1); ?>> 启用 Prism 代码高亮</label></td></tr>

        <tr><th>Mermaid</th><td>
            <label for="bac-opt-mermaid-enabled"><input type="checkbox" id="bac-opt-mermaid-enabled" name="bac_options[mermaid_enabled]" value="1" <?php checked($o['mermaid_enabled'], 1); ?>> 启用 Mermaid 图表</label>
            <p class="description">当前本地锁定 Mermaid <?php echo esc_html($o['mermaid_version']); ?>。支持 <code>language-mermaid</code>、<code>lang-mermaid</code>、<code>mermaid</code> class 和 <code>[mermaid]</code> 短代码。</p>
        </td></tr>

        <tr><th>MathJax</th><td>
            <label for="bac-opt-mathjax-enabled"><input type="checkbox" id="bac-opt-mathjax-enabled" name="bac_options[mathjax_enabled]" value="1" <?php checked($o['mathjax_enabled'], 1); ?>> 启用 MathJax 数学公式</label>
            <p class="description">需先在 Githuber MD 设置中开启 MathJax，插件负责本地化加载。</p>
        </td></tr>

        <tr><th>Markmap</th><td>
            <label for="bac-opt-markmap-enabled"><input type="checkbox" id="bac-opt-markmap-enabled" name="bac_options[markmap_enabled]" value="1" <?php checked($o['markmap_enabled'], 1); ?>> 启用 Markmap 思维导图</label>
            <p class="description">支持 <code>language-markmap</code>、<code>lang-markmap</code>、<code>markmap</code> class 和 <code>[markmap]...[/markmap]</code>。大型图建议使用预渲染模式。</p>
        </td></tr>

        <tr><th>Markmap Runtime</th><td>
            <select id="bac-opt-markmap-runtime" name="bac_options[markmap_runtime]">
                <option value="local" <?php selected($o['markmap_runtime'], 'local'); ?>>本地资源模式</option>
                <option value="cdn" <?php selected($o['markmap_runtime'], 'cdn'); ?>>CDN 调试模式</option>
            </select>
            <p class="description">正式站点推荐本地资源；CDN 仅建议临时调试。</p>
        </td></tr>

        <tr><th>Markmap 服务端预渲染</th><td>
            <label for="bac-opt-markmap-prerender"><input type="checkbox" id="bac-opt-markmap-prerender" name="bac_options[markmap_prerender]" value="1" <?php checked($o['markmap_prerender'], 1); ?>> 启用 CLI 预渲染</label>
            <p class="description"><strong>v1.5.0+</strong>：保存文章时自动预渲染 Markmap 为 SVG 并缓存，前台只读缓存，无需加载 JS 运行时。大幅提升 SEO 和 PJAX 稳定性。需要 Node.js 且 <code>proc_open</code> 可用。缓存缺失时自动降级为客户端渲染。</p>
        </td></tr>

        <tr><th>Sakurairo Prism</th><td>
            <label for="bac-opt-disable-sakurairo-prism"><input type="checkbox" id="bac-opt-disable-sakurairo-prism" name="bac_options[disable_sakurairo_prism]" value="1" <?php checked($o['disable_sakurairo_prism'], 1); ?>> 禁用主题自带 Prism</label>
            <p class="description">当前主题：<?php echo esc_html($theme->get('Name') ?: '未知'); ?></p>
        </td></tr>

        <tr><th>Sakurairo APlayer 兼容</th><td>
            <label for="bac-opt-aplayer-safe-patch"><input type="checkbox" id="bac-opt-aplayer-safe-patch" name="bac_options[aplayer_safe_patch]" value="1" <?php checked($o['aplayer_safe_patch'], 1); ?>> APlayer 容器缺失时跳过初始化</label>
            <p class="description">仅当控制台出现 APlayer <code>container missing</code> / <code>init failed</code> 错误时启用。不建议正常情况下开启。</p>
        </td></tr>

        <tr><th>LightGallery 警告抑制</th><td>
            <label for="bac-opt-suppress-lightgallery-warn"><input type="checkbox" id="bac-opt-suppress-lightgallery-warn" name="bac_options[suppress_lightgallery_warn]" value="1" <?php checked($o['suppress_lightgallery_warn'], 1); ?>> 抑制 LightGallery license warning</label>
            <p class="description">⚠️ 调试专用临时措施。会全局覆盖 <code>console.warn</code>，可能隐藏真实 warning。不建议长期启用。</p>
        </td></tr>

        <tr><th>Prism 主题</th><td>
            <select id="bac-opt-prism-theme" name="bac_options[prism_theme]">
                <option value="arcaea_dark" <?php selected($o['prism_theme'], 'arcaea_dark'); ?>>Arcaea Dark</option>
                <option value="arcaea_light" <?php selected($o['prism_theme'], 'arcaea_light'); ?>>Arcaea Light</option>
            </select>
            <p class="description">Sakurairo 默认建议使用 Arcaea Dark。</p>
        </td></tr>

        <tr><th>行号</th><td><label for="bac-opt-prism-line-numbers"><input type="checkbox" id="bac-opt-prism-line-numbers" name="bac_options[prism_line_numbers]" value="1" <?php checked($o['prism_line_numbers'], 1); ?>> 显示行号</label></td></tr>

        <tr><th>复制</th><td><label for="bac-opt-prism-copy"><input type="checkbox" id="bac-opt-prism-copy" name="bac_options[prism_copy]" value="1" <?php checked($o['prism_copy'], 1); ?>> 代码块复制按钮</label></td></tr>

        <tr><th>括号匹配</th><td><label for="bac-opt-prism-braces"><input type="checkbox" id="bac-opt-prism-braces" name="bac_options[prism_braces]" value="1" <?php checked($o['prism_braces'], 1); ?>> Prism Match Braces</label></td></tr>

        <tr><th>Previewers</th><td><label for="bac-opt-prism-previewers"><input type="checkbox" id="bac-opt-prism-previewers" name="bac_options[prism_previewers]" value="1" <?php checked($o['prism_previewers'], 1); ?>> Prism Previewers（颜色/渐变/角度/时间/缓动实时预览）</label></td></tr>
    </table>

    <hr style="border-color:rgba(230,238,255,0.20);margin:24px 0">

    <h3 style="color:rgba(238,244,255,0.85);font-weight:400">已安装插件</h3>
    <p style="color:rgba(238,244,255,0.55);font-size:13px">
    Toolbar · Show Language · Copy · Line Numbers · Line Highlight · Match Braces ·<br>
    Normalize Whitespace · Command Line · Treeview · Previewers · Autoloader · Markmap Adapter
    </p>
    <p style="color:rgba(238,244,255,0.40);font-size:12px">
    Prism 语言组件: <?php
        $langs = glob(BAC_PLUGIN_DIR . 'assets/prism/components/prism-*.js');
        echo esc_html(count($langs) ?: '—');
    ?> 种 · CI 自动同步 ✓
    </p>

    <?php submit_button(); ?>
    </form></div>
    <?php
}
