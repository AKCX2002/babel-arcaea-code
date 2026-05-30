<?php
/**
 * Babel Arcaea Code — Core Plugin Class
 * @package Babel_Arcaea_Code
 * @since   1.5.0
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Plugin {

    private static ?Plugin $instance = null;
    private Options $options;

    public static function init(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = new Options();
        $this->defineConstants();
        $this->registerHooks();
        $this->loadModules();
    }

    private function defineConstants(): void {
        if (!defined('BAC_VERSION')) define('BAC_VERSION', '1.5.0');
        if (!defined('BAC_PLUGIN_URL')) define('BAC_PLUGIN_URL', \plugin_dir_url(\dirname(__DIR__) . '/babel-arcaea-code.php'));
        if (!defined('BAC_PLUGIN_DIR')) define('BAC_PLUGIN_DIR', \plugin_dir_path(\dirname(__DIR__) . '/babel-arcaea-code.php'));
    }

    private function registerHooks(): void {
        \add_filter('plugin_action_links_' . \plugin_basename(\dirname(__DIR__) . '/babel-arcaea-code.php'), [ $this, 'addSettingsLink' ]);
        \add_action('plugins_loaded', [ $this, 'initUpdater' ]);
    }

    public function addSettingsLink(array $links): array {
        \array_unshift($links, '<a href="' . \esc_url(\admin_url('admin.php?page=bac-panel')) . '">设置</a>');
        return $links;
    }

    public function initUpdater(): void {
        $lib = \dirname(__DIR__) . '/lib/plugin-update-checker.php';
        if (!\file_exists($lib)) return;
        require_once $lib;
        $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
            'https://github.com/AKCX2002/babel-arcaea-code/', \dirname(__DIR__) . '/babel-arcaea-code.php', 'babel-arcaea-code'
        );
        $uc->getVcsApi()->enableReleaseAssets();
        if (\defined('BAC_ENABLE_GITHUB_TOKEN') && BAC_ENABLE_GITHUB_TOKEN) {
            $token = \getenv('GH_TOKEN') ?: \getenv('GITHUB_TOKEN');
            if ($token) { try { $uc->getVcsApi()->setAuthentication($token); } catch (\Exception $e) {} }
        }
    }

    private function loadModules(): void {
        $modules = ['includes/class-bac-options.php','includes/class-bac-detector.php','includes/class-bac-assets.php','includes/class-bac-renderer.php','includes/class-bac-compat.php','includes/class-bac-headers.php','includes/class-bac-health.php'];
        foreach ($modules as $m) {
            $p = \dirname(__DIR__) . '/' . $m;
            if (\file_exists($p)) require_once $p;
        }
        if (\is_admin()) {
            $a = \dirname(__DIR__) . '/includes/class-bac-admin.php';
            if (\file_exists($a)) require_once $a;
        }
    }

    public function options(): Options { return $this->options; }
}
