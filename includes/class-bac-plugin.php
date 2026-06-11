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
    private $updateChecker = null;

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
        $pluginFile = \realpath(__DIR__ . '/../babel-arcaea-code.php') ?: (__DIR__ . '/../babel-arcaea-code.php');
        \add_filter(
            'plugin_action_links_' . \plugin_basename($pluginFile),
            [$this, 'addSettingsLink']
        );
        \add_action('plugins_loaded', [$this, 'initUpdater']);
        \add_action('admin_post_bac_force_update_check', [$this, 'forceUpdateCheck']);
        \add_action('admin_notices', [$this, 'showUpdateCheckNotice']);
    }

    public function addSettingsLink(array $links): array {
        $checkUrl = \wp_nonce_url(
            \admin_url('admin-post.php?action=bac_force_update_check'),
            'bac_force_update_check'
        );
        \array_unshift(
            $links,
            '<a href="' . \esc_url(\admin_url('admin.php?page=bac-panel')) . '">设置</a>',
            '<a href="' . \esc_url($checkUrl) . '">检查更新</a>'
        );
        return $links;
    }

    public function forceUpdateCheck(): void {
        if (!\current_user_can('update_plugins')) {
            \wp_die(\esc_html__('You do not have permission to update plugins.', 'babel-arcaea-code'));
        }
        \check_admin_referer('bac_force_update_check');

        \delete_site_option('external_updates-babel-arcaea-code');
        \delete_site_transient('update_plugins');

        if ($this->updateChecker !== null) {
            try {
                $this->updateChecker->checkForUpdates();
            } catch (\Throwable $e) {
                \error_log('[Babel Arcaea Code] Manual update check failed: ' . $e->getMessage());
            }
        }

        \wp_safe_redirect(\admin_url('plugins.php?bac_update_check=1'));
        exit;
    }

    public function showUpdateCheckNotice(): void {
        if (!\is_admin() || empty($_GET['bac_update_check'])) {
            return;
        }
        echo '<div class="notice notice-success is-dismissible"><p>'
            . \esc_html__('Babel Arcaea Code update check has been refreshed.', 'babel-arcaea-code')
            . '</p></div>';
    }

    public function initUpdater(): void {
        $lib = __DIR__ . '/../lib/plugin-update-checker.php';
        if (!\file_exists($lib)) return;
        require_once $lib;

        $pluginFile = \realpath(__DIR__ . '/../babel-arcaea-code.php') ?: (__DIR__ . '/../babel-arcaea-code.php');
        $slug = 'babel-arcaea-code';
        $githubUrl = 'https://github.com/AKCX2002/babel-arcaea-code/';
        $metadataUrl = 'https://raw.githubusercontent.com/AKCX2002/babel-arcaea-code/main/update-info.json';

        $uc = null;
        try {
            $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
                $githubUrl,
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
        } catch (\Throwable $e) {
            try {
                $uc = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
                    $metadataUrl,
                    $pluginFile,
                    $slug
                );
            } catch (\Throwable $fallbackError) {
                \error_log('[Babel Arcaea Code] Updater init failed: ' . $e->getMessage());
                \error_log('[Babel Arcaea Code] Metadata fallback init failed: ' . $fallbackError->getMessage());
                // Don't return — still register the cron safety net below.
            }
        }

        // ── Crash recovery: detect and fix stale/corrupted Puc state ──
        // After a WordPress crash or fatal error the Puc WP-Cron job may have
        // failed to run, leaving "external_updates-{slug}" in a stale state
        // that reports "no update" even when a newer version exists.
        //
        // Strategy: if the stored lastCheck is older than 13 h (Puc default
        // is 12 h), or the state option is missing entirely, trigger an
        // immediate on-demand update check so the next page load reflects
        // the real remote version.
        if ($uc !== null) {
            $this->updateChecker = $uc;
            $stateOption = 'external_updates-' . $slug;
            $savedState  = \get_site_option($stateOption);
            $lastCheck   = 0;
            if (is_object($savedState) && isset($savedState->lastCheck)) {
                $lastCheck = (int) $savedState->lastCheck;
            }
            $age = $lastCheck > 0 ? (time() - $lastCheck) : PHP_INT_MAX;

            if ($age > 13 * HOUR_IN_SECONDS) {
                // Force an immediate check — this writes fresh data into
                // the Puc state option so WordPress sees the update on the
                // very next page load instead of waiting for cron.
                try {
                    $uc->checkForUpdates();
                    if (\defined('WP_DEBUG') && WP_DEBUG) {
                        \error_log(sprintf(
                            '[Babel Arcaea Code] Crash recovery: forced update check (state age=%ds)',
                            $age
                        ));
                    }
                } catch (\Throwable $checkErr) {
                    \error_log('[Babel Arcaea Code] Forced update check failed: ' . $checkErr->getMessage());
                }
                // Also clear the core transient so WP re-evaluates immediately.
                \delete_site_transient('update_plugins');
            }
        }

        // ── WP-Cron safety net ──
        // If WP-Cron is disabled or broken (common after server crashes),
        // register an hourly hook that clears the update transient so the
        // next admin page load triggers a fresh Puc check via the
        // site_transient_update_plugins filter.
        $cronHook = 'bac_hourly_update_probe';
        if (!\wp_next_scheduled($cronHook) && !\wp_doing_cron()) {
            \wp_schedule_event(time(), 'hourly', $cronHook);
        }
        \add_action($cronHook, function () {
            \delete_site_transient('update_plugins');
        });
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

        // 10. Abilities — WordPress Abilities API + MCP adapter exposure
        require_once $base . '/class-bac-abilities.php';
        new Abilities();

        // 11. Admin     — settings page (admin only)
        if (\is_admin()) {
            require_once $base . '/class-bac-admin.php';
            new Admin();
        }
    }
}
