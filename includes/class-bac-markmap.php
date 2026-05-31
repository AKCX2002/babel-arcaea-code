<?php
/**
 * Babel Arcaea Code — Markmap helper layer
 *
 * Provides cache helpers, CLI prerender, SVG sanitization and save-time
 * cache warming for Markmap diagrams.
 *
 * @package Babel_Arcaea_Code
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Markmap {

    public function __construct() {
        $opts = Plugin::init()->options()->get();
        if (empty($opts['enabled']) || empty($opts['markmap_enabled']) || empty($opts['markmap_prerender'])) {
            return;
        }

        \add_action('save_post', [$this, 'warmCacheOnSave'], 20, 3);
    }

    public function warmCacheOnSave(int $postId, \WP_Post $post, bool $update): void {
        unset($update);

        if (\wp_is_post_revision($postId) || \wp_is_post_autosave($postId)) return;
        if (!\in_array($post->post_type, ['post', 'page'], true)) return;

        $chunks = self::extractSources((string) $post->post_content);
        if (!$chunks) return;

        foreach ($chunks as $chunk) {
            \bac_markmap_render_svg($chunk);
        }
    }

    /**
     * @return string[]
     */
    public static function extractSources(string $content): array {
        $sources = [];

        $patterns = [
            '/<pre[^>]*>\s*<code[^>]*class=([\"\'])(?=[^\"\']*\b(?:language-markmap|lang-markmap|markmap|language-mindmap|lang-mindmap|mindmap)\b)[^\"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si',
            '/\[markmap[^\]]*\](.*?)\[\/markmap\]/si',
            '/\[mindmap[^\]]*\](.*?)\[\/mindmap\]/si',
        ];

        foreach ($patterns as $pattern) {
            \preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $raw = $match[2] ?? $match[1] ?? '';
                $clean = Renderer::cleanMark($raw);
                if ($clean !== '') {
                    $sources[$clean] = $clean;
                }
            }
        }

        return \array_values($sources);
    }
}

if (!\function_exists('bac_markmap_cache_dir')) {
    function bac_markmap_cache_dir(): string {
        $uploads = \wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return '';
        }

        $dir = \trailingslashit($uploads['basedir']) . 'bac-markmap-cache/';
        if (!\is_dir($dir)) {
            \wp_mkdir_p($dir);
        }

        return $dir;
    }
}

if (!\function_exists('bac_markmap_clear_cache')) {
    function bac_markmap_clear_cache(): void {
        $dir = \bac_markmap_cache_dir();
        if ($dir === '' || !\is_dir($dir)) return;

        $files = \glob($dir . '*.svg');
        if (!\is_array($files)) return;

        foreach ($files as $file) {
            @\unlink($file);
        }
    }
}

if (!\function_exists('bac_find_node')) {
    function bac_find_node(): string {
        $env = \getenv('BAC_NODE_BIN');
        if (\is_string($env) && $env !== '' && \is_executable($env)) {
            return $env;
        }

        $candidates = [
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
        ];

        foreach ($candidates as $candidate) {
            if (\is_executable($candidate)) {
                return $candidate;
            }
        }

        if (!\function_exists('exec')) {
            return '';
        }

        $bin = @\exec('command -v node 2>/dev/null');
        return \is_string($bin) ? \trim($bin) : '';
    }
}

if (!\function_exists('bac_sanitize_svg')) {
    function bac_sanitize_svg(string $svg): string {
        $svg = \preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $svg);
        $svg = \preg_replace('/\son[a-z]+\s*=\s*([\"\']).*?\1/i', '', $svg);
        $svg = \preg_replace('/\s(?:href|xlink:href)\s*=\s*([\"\'])javascript:.*?\1/i', '', $svg);
        return (string) $svg;
    }
}

if (!\function_exists('bac_markmap_render_svg')) {
    function bac_markmap_render_svg(string $markdown): string {
        $markdown = \trim(\html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($markdown === '') return '';

        $cacheDir = \bac_markmap_cache_dir();
        if ($cacheDir === '') return '';

        $hash = \sha1($markdown);
        $cacheFile = $cacheDir . $hash . '.svg';

        if (\is_file($cacheFile)) {
            $cached = (string) @\file_get_contents($cacheFile);
            return $cached !== '' ? $cached : '';
        }

        $node = \bac_find_node();
        $script = BAC_PLUGIN_DIR . 'bin/markmap-render.js';
        if ($node === '' || !\is_file($script) || !\function_exists('proc_open')) {
            return '';
        }

        $cmd = \escapeshellarg($node) . ' ' . \escapeshellarg($script);
        $cmd = \apply_filters('bac_markmap_render_cmd', $cmd);

        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @\proc_open($cmd, $desc, $pipes, BAC_PLUGIN_DIR);
        if (!\is_resource($proc)) {
            return '';
        }

        \fwrite($pipes[0], $markdown);
        \fclose($pipes[0]);

        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $code = \proc_close($proc);
        if ($code !== 0 || $stdout === '') {
            if (\defined('WP_DEBUG') && WP_DEBUG && $stderr !== '') {
                \error_log('[Babel Arcaea Code] markmap prerender failed: ' . \trim($stderr));
            }
            return '';
        }

        $svg = \bac_sanitize_svg($stdout);
        if ($svg !== '') {
            @\file_put_contents($cacheFile, $svg);
        }

        return $svg;
    }
}
