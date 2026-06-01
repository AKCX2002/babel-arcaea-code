<?php
/**
 * Babel Arcaea Code — Core Plugin Class (v1.6.0)
 *
 * Single entry point for all modules.  No legacy procedural fallback.
 * Architecture mimics githuber-md's Githuber::init() + Module pattern.
 *
 * @package Babel_Arcaea_Code
 * @since   1.6.0
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

require_once __DIR__ . '/class-bac-options.php';

class Plugin {

    private static ?Plugin $instance = null;
    private Options $options;

    /* ── Singleton init ── */

    public static function init(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = new Options();
        $this->registerHooks();
        $this->loadModules();
    }

    /* ── Options accessor ── */

    public function options(): Options {
        return $this->options;
    }

    /* ── Plugin-level hooks ── */

    private function registerHooks(): void {
        \add_filter(
            'plugin_action_links_' . \plugin_basename(__DIR__ . '/../babel-arcaea-code.php'),
            [$this, 'addSettingsLink']
        );
        \add_action('plugins_loaded', [$this, 'initUpdater']);
    }

    public function addSettingsLink(array $links): array {
        \array_unshift(
            $links,
            '<a href="' . \esc_url(\admin_url('admin.php?page=bac-panel')) . '">设置</a>'
        );
        return $links;
    }

    public function initUpdater(): void {
        $lib = __DIR__ . '/../lib/plugin-update-checker.php';
        if (!\file_exists($lib)) return;
        require_once $lib;
        try {
            $pluginFile = __DIR__ . '/../babel-arcaea-code.php';
            $slug = 'babel-arcaea-code';
            $metadataUrl = 'https://raw.githubusercontent.com/AKCX2002/babel-arcaea-code/main/update-info.json';

            $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
                $metadataUrl,
                $pluginFile,
                $slug
            );
        } catch (\Throwable $e) {
            try {
                $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
                    'https://github.com/AKCX2002/babel-arcaea-code/',
                    $pluginFile,
                    $slug
                );
                $uc->setBranch('main');
                $uc->getVcsApi()->enableReleaseAssets('/\.zip($|[?&#])/i');

                if (\defined('BAC_ENABLE_GITHUB_TOKEN') && BAC_ENABLE_GITHUB_TOKEN) {
                    $token = \getenv('GH_TOKEN') ?: \getenv('GITHUB_TOKEN');
                    if ($token) {
                        $uc->setAuthentication($token);
                    }
                }
            } catch (\Throwable $fallbackError) {
                if (\defined('WP_DEBUG') && WP_DEBUG) {
                    \error_log('[Babel Arcaea Code] Updater init failed: ' . $e->getMessage());
                    \error_log('[Babel Arcaea Code] GitHub fallback init failed: ' . $fallbackError->getMessage());
                }
            }
        }
    }

    /* ── Module loader (one-time, no duplication) ── */

    private function loadModules(): void {
        $base = __DIR__;

        // CRITICAL: Set singleton BEFORE instantiating modules.
        // Detector/Assets/Renderer/Compat constructors all call
        // Plugin::init()->options()->get(), which would infinitely
        // recurse if self::$instance is still null.
        self::$instance = $this;

        // 1. Options (already required at top of file)
        // 2. Detector  — post-meta scanner (save_post hook)
        require_once $base . '/class-bac-detector.php';
        new Detector();

        // 3. Assets    — enqueues Prism / Mermaid / KaTeX / MathJax / Markmap
        require_once $base . '/class-bac-assets.php';
        new Assets();

        // 4. Renderer  — the_content filters for code blocks, Mermaid, Markmap, KaTeX
        require_once $base . '/class-bac-renderer.php';
        new Renderer();

        // 5. Markmap   — prerender helpers, cache warming, SVG sanitizer
        require_once $base . '/class-bac-markmap.php';
        new Markmap();

        // 6. Compat    — Sakurairo theme deconfliction (disable theme Prism, APlayer, LightGallery)
        require_once $base . '/class-bac-compat.php';
        new Compat();

        // 7. Headers   — security headers (X-Content-Type-Options, etc.)
        require_once $base . '/class-bac-headers.php';
        new Headers();

        // 8. Health    — system check (available in admin)
        require_once $base . '/class-bac-health.php';

        // 9. Blocks    — Gutenberg blocks (Mermaid, Markmap, etc.)
        require_once $base . '/class-bac-blocks.php';
        new Blocks();

        // 10. Admin     — settings page (admin only)
        if (\is_admin()) {
            require_once $base . '/class-bac-admin.php';
            new Admin();
        }
    }
}
