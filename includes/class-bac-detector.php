<?php
/**
 * Babel Arcaea Code — Content Detector
 *
 * Scans post content at save time to determine which JS rendering
 * modules are needed. Stores post meta for conditional frontend loading.
 *
 * Pattern: githuber-md's detect_code_languages() + ModuleAbstract::is_module_should_be_loaded().
 * No editor capability — frontend-only.
 *
 * @package Babel_Arcaea_Code
 * @since   1.5.3
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Detector {

    /** Post meta keys. */
    public const META_PRISM       = '_bac_needs_prism';
    public const META_MERMAID     = '_bac_needs_mermaid';
    public const META_MATHJAX     = '_bac_needs_mathjax';
    public const META_KATEX       = '_bac_needs_katex';
    public const META_MARKMAP     = '_bac_needs_markmap';
    public const META_FLOWCHART   = '_bac_needs_flowchart';
    public const META_SEQUENCE    = '_bac_needs_sequence';

    /** Cached post ID for frontend queries. */
    private static int $frontPostId = 0;

    /** Global toggle flags (set from Options). */
    private bool $prismEnabled;
    private bool $mermaidEnabled;
    private bool $mathjaxEnabled;
    private bool $katexEnabled;
    private bool $markmapEnabled;

    public function __construct() {
        $opts = Plugin::init()->options()->get();
        $this->prismEnabled   = !empty($opts['prism_enabled']);
        $this->mermaidEnabled = !empty($opts['mermaid_enabled']);
        $this->mathjaxEnabled = !empty($opts['mathjax_enabled']);
        $this->katexEnabled   = !empty($opts['katex_enabled']);
        $this->markmapEnabled = !empty($opts['markmap_enabled']);

        if ($opts['enabled']) {
            \add_action('save_post', [$this, 'scanOnSave'], 10, 3);
        }
    }

    /* ─────────────────────────────────────────────
     * Save-time scanning
     * ───────────────────────────────────────────── */

    /**
     * Scan post content on save and store module requirements as post meta.
     *
     * @param int      $postId Post ID.
     * @param \WP_Post $post   Post object.
     * @param bool     $update Whether this is an update.
     */
    public function scanOnSave(int $postId, \WP_Post $post, bool $update): void {
        if (\wp_is_post_revision($postId) || \wp_is_post_autosave($postId)) return;
        if (!\in_array($post->post_type, ['post', 'page'], true)) return;

        $content = $post->post_content;

        // ── Prism: any <pre><code or ```fenced code block ──
        $needsPrism = $this->prismEnabled && (
            \strpos($content, '<pre') !== false ||
            \strpos($content, '<code') !== false ||
            \preg_match('/^```/m', $content)
        );

        // ── Mermaid: class="mermaid" or class="language-mermaid" or ```mermaid ──
        $needsMermaid = $this->mermaidEnabled && (
            \stripos($content, 'mermaid') !== false
        );

        // ── Markmap: class="markmap" or language-markmap ──
        $needsMarkmap = $this->markmapEnabled && (
            \stripos($content, 'markmap') !== false
        );

        // ── MathJax: $...$ or $$...$$ delimiters ──
        $needsMathJax = $this->mathjaxEnabled && (
            \preg_match('/\$\$[\s\S]*?\$\$/', $content) ||
            \preg_match('/(?<!\$)\$(?!\$)[^$\n]+\$(?!\$)/', $content) ||
            \preg_match('/\\\\[\(\[]/', $content)
        );

        // ── KaTeX: same delimiters (but KaTeX is alternative to MathJax) ──
        $needsKatex = $this->katexEnabled && (
            \preg_match('/\$\$[\s\S]*?\$\$/', $content) ||
            \preg_match('/(?<!\$)\$(?!\$)[^$\n]+\$(?!\$)/', $content) ||
            \preg_match('/\\\\[\(\[]/', $content)
        );

        // ── Store meta ──
        $this->updateMeta($postId, self::META_PRISM, $needsPrism);
        $this->updateMeta($postId, self::META_MERMAID, $needsMermaid);
        $this->updateMeta($postId, self::META_MARKMAP, $needsMarkmap);
        $this->updateMeta($postId, self::META_MATHJAX, $needsMathJax);
        $this->updateMeta($postId, self::META_KATEX, $needsKatex);
    }

    /** @param int|bool $value */
    private function updateMeta(int $postId, string $key, $value): void {
        $value = $value ? '1' : '0';
        if (\get_post_meta($postId, $key, true) !== $value) {
            \update_post_meta($postId, $key, $value);
        }
    }

    /* ─────────────────────────────────────────────
     * Frontend: conditional loading check
     * ───────────────────────────────────────────── */

    /**
     * Check if a module should be loaded for the current post.
     * Mirrors githuber-md's ModuleAbstract::is_module_should_be_loaded().
     *
     * @param string $metaKey Post meta key (one of Detector::META_* constants).
     * @return bool True if module is needed.
     */
    public static function needsModule(string $metaKey): bool {
        if (empty(self::$frontPostId)) {
            self::$frontPostId = self::getCurrentPostId();
        }

        if (empty(self::$frontPostId)) {
            // Can't determine — load everything (safe fallback).
            return true;
        }

        $meta = \get_post_meta(self::$frontPostId, $metaKey, true);
        // Not yet scanned (legacy post) — load to be safe.
        if ($meta === '') return true;
        return ($meta === '1');
    }

    /** Get current post ID on the frontend. */
    private static function getCurrentPostId(): int {
        if (\is_singular()) {
            $id = \get_queried_object_id();
            return $id ?: 0;
        }
        return 0;
    }

    /* ─────────────────────────────────────────────
     * Bulk scan: run for existing posts (CLI / admin trigger)
     * ───────────────────────────────────────────── */

    /**
     * Scan all existing posts for module requirements.
     * Useful for initial migration or recalculation.
     */
    public static function scanAll(): int {
        $posts = \get_posts([
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        $detector = new self();
        $count = 0;

        foreach ($posts as $post) {
            $detector->scanOnSave($post->ID, $post, true);
            $count++;
        }

        return $count;
    }
}
