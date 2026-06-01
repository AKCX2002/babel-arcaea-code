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
        \add_filter('the_content', [$this, 'filterLatexBlocks'], 11);

        /* ── Priority 11: run after wpautop(10), strip injected <br/> ── */
        if ($this->opts['mermaid_enabled']) {
            \add_filter('the_content', [$this, 'filterMermaid'], 11);
            \add_filter('the_content', [$this, 'wrapBareMermaidPre'], 12);
            \add_shortcode('mermaid', [$this, 'shortcodeMermaid']);
        }
        if ($this->opts['markmap_enabled']) {
            \add_filter('the_content', [$this, 'filterMarkmap'], 11);
            \add_shortcode('markmap', [$this, 'shortcodeMarkmap']);
            \add_shortcode('mindmap', [$this, 'shortcodeMarkmap']);
        }
        if ($this->opts['katex_enabled']) {
            \add_shortcode('katex', [$this, 'shortcodeKatex']);
        }
        if (!empty($this->opts['latex_enabled'])) {
            \add_shortcode('latex', [$this, 'shortcodeLatex']);
        }
    }

    /* ════════════════════════════════════════════
     * Code-block normalization (Sakurairo fix)
     * ════════════════════════════════════════════ */

    /**
     * Wrap bare <pre> tags (stripped by Sakurairo) back into
     * <pre><code class="language-xxx"> form so Prism.js can highlight them.
     *
     * Security:
     * - Code payload is always re-escaped before output.
     * - Original tag attributes are filtered instead of being reused verbatim.
     */
    public function normalizeCodeBlocks(string $content): string {
        // Pass 1: wrap bare <pre> (no <code> inside, stripped by Sakurairo)
        $pattern = '/<pre(\s[^>]*)?>\s*(?!\s*<)(.*?)\s*<\/pre>/si';

        $content = \preg_replace_callback($pattern, function ($m) {
            $attrs = $m[1] ?? '';
            $inner = $m[2] ?? '';

            $langClass = $this->extractLangClass($attrs);
            $inner = \html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeAttrs = $this->sanitizeTagAttrs($attrs);

            return '<pre' . $safeAttrs . '><code class="' . \esc_attr($langClass) . '">'
                . \esc_html($inner)
                . '</code></pre>';
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
            if (\preg_match('/(?:^|\s)(dart|flutter|bash|sh|python|js|javascript|ts|typescript|html|css|json|yaml|xml|sql|php|ruby|rust|go|java|c|cpp|csharp|swift|kotlin|mermaid|markmap|mindmap|latex|katex|mathjax|tex)(?:\s|$)/i', $classes, $lm)) {
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
        if (empty($this->opts['latex_enabled'])) return $content;

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

    public function shortcodeLatex(array $atts, ?string $content = null): string {
        $content = self::clean((string) $content);
        if ($content === '') return '';
        $display = !isset($atts['display']) || !empty($atts['display']);
        return $this->buildLatexBlock($content, $display);
    }

    /* ════════════════════════════════════════════
     * Shared helpers
     * ════════════════════════════════════════════ */

    private static function pattern(array $classes): string {
        $n = \implode('|', \array_map(fn($c) => \preg_quote($c, '/'), $classes));
        return '/<pre[^>]*>\s*<code[^>]*class=([\"\'])(?=[^\"\']*\b(?:language-' . $n . '|lang-' . $n . '|' . $n . ')\b)[^\"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';
    }

    private function extractLangClass(string $attrs): string {
        $langClass = 'language-text';
        if (!\preg_match('/class=[\"\']([^\"\']*)[\"\']/i', $attrs, $cm)) return $langClass;

        $classes = $cm[1];
        if (\preg_match('/(?:^|\s)(?:language-|lang-)([a-z0-9_+#.-]+)/i', $classes, $lm)) {
            return 'language-' . \strtolower($lm[1]);
        }

        if (\preg_match('/(?:^|\s)(dart|flutter|bash|sh|python|js|javascript|ts|typescript|html|css|json|yaml|xml|sql|php|ruby|rust|go|java|c|cpp|csharp|swift|kotlin|mermaid|markmap)(?:\s|$)/i', $classes, $lm)) {
            return 'language-' . \strtolower($lm[1]);
        }

        return $langClass;
    }

    private function sanitizeTagAttrs(string $attrs): string {
        if ($attrs === '') return '';

        $safe = '';

        if (\preg_match('/\bclass=(["\'])(.*?)\1/i', $attrs, $m)) {
            $classes = \preg_split('/\s+/', $m[2]) ?: [];
            $classes = \array_values(\array_filter(\array_map('\sanitize_html_class', $classes)));
            if ($classes !== []) {
                $safe .= ' class="' . \esc_attr(\implode(' ', $classes)) . '"';
            }
        }

        if (\preg_match('/\bid=(["\'])(.*?)\1/i', $attrs, $m)) {
            $safe .= ' id="' . \esc_attr(\sanitize_html_class($m[2])) . '"';
        }

        if (\preg_match_all('/\b((?:data|aria)-[a-z0-9_-]+)=(["\'])(.*?)\2/i', $attrs, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $safe .= ' ' . \esc_attr($m[1]) . '="' . \esc_attr($m[3]) . '"';
            }
        }

        return $safe;
    }

    private static function clean(string $r): string {
        $code = \html_entity_decode(\trim($r), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code = \preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = \strip_tags($code);
        return \trim($code);
    }

    public static function cleanMark(string $r): string {
        return self::clean($r);
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

    /**
     * Wrap bare <pre class="mermaid"> blocks so MerPress frontend output
     * inherits the same Sakurairo/Arcaea shell as BAC-rendered diagrams.
     */
    public function wrapBareMermaidPre(string $content): string {
        return \preg_replace_callback(
            '/(?<!arcaea-mermaid-box">)\s*(<pre(?=[^>]*\bclass=(["\'])[^"\']*\bmermaid\b[^"\']*\2)[^>]*>[\s\S]*?<\/pre>)/i',
            static function ($m) {
                $pre = $m[1] ?? '';
                if ($pre === '' || \stripos($pre, 'data-bac-mermaid-shell=') !== false) {
                    return $m[0];
                }
                $pre = \preg_replace('/<pre\b/i', '<pre data-bac-mermaid-shell="1"', $pre, 1);
                return '<div class="arcaea-mermaid-box">' . $pre . '</div>';
            },
            $content
        );
    }

    public function filterLatexBlocks(string $content): string {
        if (empty($this->opts['latex_enabled'])) return $content;

        return \preg_replace_callback(
            self::pattern(['katex', 'latex', 'mathjax', 'tex']),
            function ($m) {
                $code = self::clean($m[2]);
                if ($code === '') return $m[0];
                return $this->buildLatexBlock($code, true);
            },
            $content
        );
    }

    private function buildLatexBlock(string $content, bool $display): string {
        $renderer = !empty($this->opts['mathjax_enabled']) ? 'mathjax' : 'katex';
        $attrs = ' class="bac-latex-block bac-latex-' . \esc_attr($renderer) . '" data-bac-latex="' . \esc_attr($renderer) . '" data-display="' . ($display ? '1' : '0') . '"';

        if ($renderer === 'mathjax') {
            $body = $display ? '$$' . \esc_html($content) . '$$' : '\\(' . \esc_html($content) . '\\)';
            return '<div' . $attrs . '>' . $body . '</div>';
        }

        return '<div' . $attrs . '><code class="bac-latex-source">' . \esc_html($content) . '</code></div>';
    }

    /* ════════════════════════════════════════════
     * Markmap
     * ════════════════════════════════════════════ */

    public function filterMarkmap(string $c): string {
        return \preg_replace_callback(
            self::pattern(['markmap', 'mindmap']),
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
