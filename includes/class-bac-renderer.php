<?php
/**
 * Babel Arcaea Code — Unified Content Renderer (v1.6.0)
 *
 * Handles all the_content filtering: code-block normalisation,
 * Mermaid / Markmap / KaTeX block conversion, and shortcodes.
 *
 * Priority layout:
 *   0  — normalizeCodeBlocks (fix Sakurairo <pre> stripping) + KaTeX protect
 *   11 — Mermaid / Markmap conversion (after wpautop=10)
 *
 * @package Babel_Arcaea_Code
 * @since   1.5.0
 * @since   1.6.0  KaTeX filter & shortcode merged from legacy renderer-katex.php.
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Renderer {

    private array $opts;

    public function __construct() {
        $this->opts = Plugin::init()->options()->get();
        if (!$this->opts['enabled']) return;

        /* ── Priority 0: run before wpautop(10) ── */
        \add_filter('the_content', [$this, 'normalizeCodeBlocks'], 0);
        \add_filter('the_content', [$this, 'protectKatex'], 0);

        /* ── Priority 11: run after wpautop(10), strip injected <br/> ── */
        if ($this->opts['mermaid_enabled']) {
            \add_filter('the_content', [$this, 'filterMermaid'], 11);
            \add_shortcode('mermaid', [$this, 'shortcodeMermaid']);
        }
        if ($this->opts['markmap_enabled']) {
            \add_filter('the_content', [$this, 'filterMarkmap'], 11);
            \add_shortcode('markmap', [$this, 'shortcodeMarkmap']);
        }
        if ($this->opts['katex_enabled']) {
            \add_shortcode('katex', [$this, 'shortcodeKatex']);
        }
    }

    /* ════════════════════════════════════════════
     * Code-block normalization (Sakurairo fix)
     * ════════════════════════════════════════════ */

    /**
     * Wrap bare <pre> tags (stripped by Sakurairo) back into
     * <pre><code class="language-xxx"> form so Prism.js can highlight them.
     */
    public function normalizeCodeBlocks(string $content): string {
        // Pass 1: wrap bare <pre> (no <code> inside, stripped by Sakurairo)
        $pattern = '/<pre(\s[^>]*)?>\s*(?!\s*<)(.*?)\s*<\/pre>/si';

        $content = \preg_replace_callback($pattern, function ($m) {
            $attrs = $m[1] ?? '';
            $inner = $m[2] ?? '';

            $langClass = $this->extractLang($attrs);
            $inner = \html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<pre' . $attrs . '><code class="' . \esc_attr($langClass) . '">' . $inner . '</code></pre>';
        }, $content);

        // Pass 2: handle <pre><code> that lacks language-* class
        $content = \preg_replace_callback(
            '/<pre(\s[^>]*)?>\s*<code(?![^>]*\blanguage-)(\s[^>]*)>(.*?)<\/code>\s*<\/pre>/si',
            function ($m) {
                $preAttrs = $m[1] ?? '';
                $codeAttrs = $m[2] ?? '';
                $inner = $m[3] ?? '';

                $langClass = $this->extractLang($preAttrs);
                $inner = \html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return '<pre' . $preAttrs . '><code class="' . \esc_attr($langClass) . '"' . $codeAttrs . '>' . $inner . '</code></pre>';
            },
            $content
        );

        return $content;
    }

    /**
     * Extract language class from tag attributes.
     */
    private function extractLang(string $attrs): string {
        if (\preg_match('/class=["\']([^"\']*)["\']/i', $attrs, $cm)) {
            $classes = $cm[1];
            if (\preg_match('/(?:^|\s)(?:language-|lang-)([a-z0-9_+#.-]+)/i', $classes, $lm)) {
                return 'language-' . \strtolower($lm[1]);
            }
            if (\preg_match('/(?:^|\s)(dart|flutter|bash|sh|python|js|javascript|ts|typescript|html|css|json|yaml|xml|sql|php|ruby|rust|go|java|c|cpp|csharp|swift|kotlin|mermaid|markmap)(?:\s|$)/i', $classes, $lm)) {
                return 'language-' . \strtolower($lm[1]);
            }
        }
        return 'language-text';
    }

    /* ════════════════════════════════════════════
     * KaTeX content protection & shortcode
     * (originally renderer-katex.php)
     * ════════════════════════════════════════════ */

    /**
     * Wrap KaTeX display-math delimiters in <div> to prevent wpautop
     * from inserting <br/> and <p> tags inside math expressions.
     */
    public function protectKatex(string $content): string {
        if (empty($this->opts['katex_enabled'])) return $content;

        return \preg_replace_callback(
            '/\$\$([\s\S]*?)\$\$/',
            fn($m) => '<div class="katex-display">$$' . $m[1] . '$$</div>',
            $content
        );
    }

    /** [katex display=1]...[/katex] shortcode */
    public function shortcodeKatex(array $atts, ?string $content = null): string {
        $display = !empty($atts['display']);
        $content = \html_entity_decode(\trim((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$content) return '';

        if ($display) {
            return '<div class="katex-display">$$' . \esc_html($content) . '$$</div>';
        }
        return '<span class="katex-inline">$' . \esc_html($content) . '$</span>';
    }

    /* ════════════════════════════════════════════
     * Shared helpers
     * ════════════════════════════════════════════ */

    private static function pattern(array $classes): string {
        $n = \implode('|', \array_map(fn($c) => \preg_quote($c, '/'), $classes));
        return '/<pre[^>]*>\s*<code[^>]*class=([\"\'])(?=[^\"\']*\b(?:language-' . $n . '|lang-' . $n . '|' . $n . ')\b)[^\"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';
    }

    private static function clean(string $r): string {
        $code = \html_entity_decode(\trim($r), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code = \preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = \strip_tags($code);
        return \trim($code);
    }

    /* ════════════════════════════════════════════
     * Mermaid  — MerPress-style: <pre class="mermaid">
     *
     * Outputs a bare <pre class="mermaid"> containing the raw Mermaid
     * source text.  wpautop won't touch <pre> (block-level exemption).
     * The Mermaid JS initializes on load via mermaid.run() and replaces
     * the pre content with an SVG — same pattern MerPress uses.
     * ════════════════════════════════════════════ */

    public function filterMermaid(string $c): string {
        return \preg_replace_callback(
            self::pattern(['mermaid']),
            fn($m) => self::clean($m[2]) === ''
                ? $m[0]
                : '<div class="arcaea-mermaid-box"><pre class="mermaid">'
                    . \esc_html(self::clean($m[2]))
                    . '</pre></div>',
            $c
        );
    }

    public function shortcodeMermaid(array $a, ?string $c): string {
        $c = self::clean((string) $c);
        return $c === ''
            ? ''
            : '<div class="arcaea-mermaid-box"><pre class="mermaid">'
                . \esc_html($c)
                . '</pre></div>';
    }

    /* ════════════════════════════════════════════
     * Markmap
     * ════════════════════════════════════════════ */

    public function filterMarkmap(string $c): string {
        return \preg_replace_callback(
            self::pattern(['markmap']),
            function ($m) {
                $c = self::clean($m[2]);
                if ($c === '') return $m[0];
                if (
                    !empty($this->opts['markmap_prerender'])
                    && \function_exists('bac_markmap_render_svg')
                ) {
                    $sv = \bac_markmap_render_svg($c);
                    if ($sv) {
                        return '<div class="arcaea-markmap-box arcaea-markmap-prerendered">' . $sv . '</div>';
                    }
                }
                return '<div class="arcaea-markmap-box">'
                    . '<pre class="arcaea-markmap-source">' . \esc_html($c) . '</pre>'
                    . '<svg class="arcaea-markmap-diagram"></svg>'
                    . '</div>';
            },
            $c
        );
    }

    public function shortcodeMarkmap(array $a, ?string $c): string {
        $c = self::clean((string) $c);
        if ($c === '') return '';
        if (
            !empty($this->opts['markmap_prerender'])
            && \function_exists('bac_markmap_render_svg')
        ) {
            $sv = \bac_markmap_render_svg($c);
            if ($sv) return '<div class="arcaea-markmap-box arcaea-markmap-prerendered">' . $sv . '</div>';
        }
        return '<div class="arcaea-markmap-box">'
            . '<pre class="arcaea-markmap-source">' . \esc_html($c) . '</pre>'
            . '<svg class="arcaea-markmap-diagram"></svg>'
            . '</div>';
    }
}
