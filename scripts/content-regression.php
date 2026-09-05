<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Exercise the real renderer and WordPress asset registrations without a database.
define('ABSPATH', __DIR__);
define('BAC_PLUGIN_DIR', dirname(__DIR__) . '/');
define('BAC_PLUGIN_URL', '/');
function is_admin() { return false; }
function add_action(...$args) {}
function add_query_arg($key, $value, $url) { return $url . '?' . $key . '=' . rawurlencode($value); }
function esc_url($value) { return $value; }
function wp_scripts() { static $r; return $r ??= (object)['queue' => [], 'registered' => []]; }
function wp_styles() { static $r; return $r ??= (object)['queue' => [], 'registered' => []]; }
function enqueue($r, $h, $src, $deps, $ver) {
    $r->registered[$h] = (object)['src' => $src, 'deps' => $deps, 'ver' => $ver, 'extra' => []];
    if (!in_array($h, $r->queue, true)) $r->queue[] = $h;
}
function wp_enqueue_script($h, $src, $deps = [], $ver = null, $footer = false) { enqueue(wp_scripts(), $h, $src, $deps, $ver); }
function wp_enqueue_style($h, $src, $deps = [], $ver = null) { enqueue(wp_styles(), $h, $src, $deps, $ver); }
function wp_dequeue_script($h) { wp_scripts()->queue = array_values(array_diff(wp_scripts()->queue, [$h])); }
function wp_dequeue_style($h) { wp_styles()->queue = array_values(array_diff(wp_styles()->queue, [$h])); }
function wp_script_is($h, $state) { return isset(wp_scripts()->registered[$h]); }
function wp_localize_script($h, $name, $data) {
    $GLOBALS['localized'][$name] = $data;
    wp_scripts()->registered[$h]->extra['data'] = 'var ' . $name . '=' . json_encode($data) . ';';
}
function wp_add_inline_script($h, $source, $position = 'after') { wp_scripts()->registered[$h]->extra[$position][] = $source; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
require BAC_PLUGIN_DIR . 'includes/class-bac-options.php';
require BAC_PLUGIN_DIR . 'includes/class-bac-assets.php';
require BAC_PLUGIN_DIR . 'includes/class-bac-renderer.php';

$reflection = new ReflectionClass(BabelArcaeaCode\Assets::class);
$assets = $reflection->newInstanceWithoutConstructor();
$property = $reflection->getProperty('opts');
$options = BabelArcaeaCode\Options::DEFAULTS;
$options['katex_enabled'] = !in_array('--mathjax', $argv, true);
$options['mathjax_enabled'] = in_array('--mathjax', $argv, true);
$options['markmap_enabled'] = 1;
$options['markmap_prerender'] = in_array('--prerender', $argv, true);
$property->setValue($assets, $options);
$assets->enqueueAll();
check(wp_scripts()->queue === ['bac-reading-progress', 'bac-content-loader'], 'Archive must not enqueue render engines');
$manifest = $GLOBALS['localized']['BAC_Assets'];
check(isset($manifest['groups']['prism'], $manifest['groups']['mermaid'], $manifest['groups']['math']), 'Missing lazy modules');
check(isset($manifest['scripts']['bac-markmap-init']), 'Prerender failures must retain client runtime registration');
check(!in_array('bac-prism-core', $manifest['scripts']['bac-mermaid-init']['deps'], true), 'Mermaid must not depend on Prism');
foreach (['scripts', 'styles'] as $kind) {
    $registry = $kind === 'scripts' ? wp_scripts() : wp_styles();
    foreach ($manifest[$kind] as $item) foreach ($item['deps'] as $dependency) {
        check(isset($registry->registered[$dependency]), 'Unregistered dependency: ' . $dependency);
    }
}
$renderer = (new ReflectionClass(BabelArcaeaCode\Renderer::class))->newInstanceWithoutConstructor();
foreach (['<blockquote><p>[!NOTE]<br><strong>Body</strong></p></blockquote>', '<blockquote><p>[!IMPORTANT]</p><p>Body</p></blockquote>'] as $input) {
    $output = $renderer->renderCallouts($input);
    check(!str_contains($output, '[!'), 'Alert marker remains');
    check(substr_count($output, '<p') === substr_count($output, '</p>'), 'Unbalanced alert paragraphs');
    check($renderer->renderCallouts($output) === $output, 'Alert conversion is not idempotent');
}
$ordinary = '<blockquote><p>Ordinary quote</p></blockquote><pre>[!NOTE]</pre>';
check($renderer->renderCallouts($ordinary) === $ordinary, 'Changed ordinary quote/code');
if (in_array('--manifest', $argv, true)) {
    echo json_encode(['assets' => $manifest, 'config' => $GLOBALS['localized']['BAC_Config']], JSON_UNESCAPED_UNICODE);
} else {
    echo "Content regressions passed: lazy asset graph and callout conversion\n";
}
