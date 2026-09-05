<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Assets {
    private array $opts;
    private const PRISM_CORE = 'bac-prism-core';

    public function __construct() { $this->opts = Plugin::init()->options()->get(); \add_action('wp_enqueue_scripts', [$this,'enqueueAll']); }

    /**
     * Conditional enqueue: only load modules detected as needed for current post.
     * Pattern: githuber-md's Githuber::init() + ModuleAbstract::is_module_should_be_loaded().
     *
     * WordPress registers module URLs/dependencies; the content loader chooses
     * the rendered page requirements on initial load and PJAX navigation.
     */
    public function enqueueAll(): void {
        if (\is_admin() || empty($this->opts['enabled'])) return;

        $this->enqueueReadingEnhancements();

        // Register enabled modules once. The shared loader selects them from
        // rendered content on both initial load and subsequent PJAX navigation.
        $scriptsBefore = \wp_scripts()->queue;
        $stylesBefore = \wp_styles()->queue;
        $groups = [];
        $capture = function (string $name, callable $enqueue) use (&$groups): void {
            $scripts = \wp_scripts()->queue;
            $styles = \wp_styles()->queue;
            $enqueue();
            $groups[$name] = [
                'scripts' => \array_values(\array_diff(\wp_scripts()->queue, $scripts)),
                'styles' => \array_values(\array_diff(\wp_styles()->queue, $styles)),
            ];
        };
        if ($this->opts['prism_enabled']) {
            $capture('prism', function (): void {
            $this->enqueuePrismCss(); $this->enqueuePrismJs(); $this->enqueuePrismEnhancements();
            $this->script('assets/prism/prism-init.js', 'bac-prism-init', ['bac-prism-titlebar']);
            });
        }
        $capture('zoom', function (): void {
            $this->enqueueMediumZoom();
            $this->script('assets/js/image-zoom-init.js', 'bac-image-zoom-init', ['bac-medium-zoom']);
        });
        if ($this->opts['mermaid_enabled']) {
            $capture('mermaid', function (): void { $this->enqueueFrontendInit(); $this->enqueueMermaidEnhancements(); });
        }
        if ($this->opts['markmap_enabled']) {
            $capture('markmap', function (): void { $this->enqueueMarkmap(); });
        }
        if (!empty($this->opts['mathjax_enabled'])) {
            $capture('math', function (): void { $this->enqueueMathJax(); });
        }
        if (!empty($this->opts['katex_enabled'])) {
            $capture('math', function (): void { $this->enqueueKatex(); });
        }
        $manifest = ['groups' => $groups, 'scripts' => [], 'styles' => []];
        foreach (['scripts' => $scriptsBefore, 'styles' => $stylesBefore] as $kind => $before) {
            $registry = $kind === 'scripts' ? \wp_scripts() : \wp_styles();
            foreach (\array_diff($registry->queue, $before) as $handle) {
                $item = $registry->registered[$handle];
                $manifest[$kind][$handle] = [
                    'src' => $item->ver ? \add_query_arg('ver', $item->ver, $item->src) : $item->src,
                    'deps' => $item->deps,
                    'before' => \array_merge(isset($item->extra['data']) ? [$item->extra['data']] : [], $item->extra['before'] ?? []),
                    'after' => $item->extra['after'] ?? [],
                ];
                $kind === 'scripts' ? \wp_dequeue_script($handle) : \wp_dequeue_style($handle);
            }
        }
        $this->script('assets/js/content-loader.js', 'bac-content-loader');
        \wp_localize_script('bac-content-loader', 'BAC_Config', [
            'lineNumbers' => !empty($this->opts['prism_line_numbers']),
            'prismEnabled' => !empty($this->opts['prism_enabled']),
            'mermaidEnabled' => !empty($this->opts['mermaid_enabled']),
            'katexEnabled' => !empty($this->opts['katex_enabled']),
            'mermaidCompatMode' => $this->opts['mermaid_compat_mode'] ?? 'auto',
        ]);
        \wp_localize_script('bac-content-loader', 'BAC_Assets', $manifest);
    }

    private function enqueuePrismCss(): void {
        $theme = \in_array($this->opts['prism_theme']??'',['arcaea_dark','arcaea_light'],true) ? $this->opts['prism_theme'] : 'arcaea_dark';
        $d = 'assets/prism/';
        $this->style($d.'prism.css', 'bac-prism-base');
        $this->style($d.'prism-toolbar.css', 'bac-prism-toolbar', ['bac-prism-base']);
        $this->style($d.'themes/arcaea-common.css', 'bac-prism-arcaea-common', ['bac-prism-toolbar']);
        $this->style($d.'themes/'.($theme==='arcaea_light'?'arcaea-light.css':'arcaea-dark.css'), 'bac-prism-arcaea-theme', ['bac-prism-arcaea-common']);
        $this->style('assets/css/bac-prism-wrap.css', 'bac-prism-wrap', ['bac-prism-arcaea-theme']);
        if ($this->opts['prism_line_numbers']) { $this->style($d.'prism-line-numbers.css','bac-prism-ln',['bac-prism-arcaea-theme']); $this->style($d.'prism-line-highlight.css','bac-prism-lh',['bac-prism-arcaea-theme']); }
        if ($this->opts['prism_previewers']) { $this->style($d.'prism-previewers.css','bac-prism-previewers',['bac-prism-arcaea-theme']); $this->style($d.'prism-previewers-arcaea.css','bac-prism-previewers-arcaea',['bac-prism-previewers']); }
    }

    private function enqueuePrismJs(): void {
        if (!$this->script('assets/prism/prism.js', self::PRISM_CORE)) return;
        $this->script('assets/prism/prism-toolbar.js','bac-prism-toolbar',[self::PRISM_CORE]);
        $this->script('assets/prism/prism-show-language.js','bac-prism-lang',['bac-prism-toolbar']);
        $this->script('assets/prism/prism-normalize-whitespace.js','bac-prism-norm',[self::PRISM_CORE]);
        $this->script('assets/prism/prism-command-line.js','bac-prism-cmd',[self::PRISM_CORE]);
        $this->script('assets/prism/prism-treeview.js','bac-prism-tree',[self::PRISM_CORE]);
        if ($this->opts['prism_line_numbers']) { $this->script('assets/prism/prism-line-numbers.js','bac-prism-ln',[self::PRISM_CORE]); $this->script('assets/prism/prism-line-highlight.js','bac-prism-lh',[self::PRISM_CORE]); }
        if ($this->opts['prism_braces']) $this->script('assets/prism/prism-match-braces.js','bac-prism-braces',[self::PRISM_CORE]);
        if ($this->opts['prism_previewers']) $this->script('assets/prism/prism-previewers.js','bac-prism-previewers',[self::PRISM_CORE]);
        if ($this->opts['prism_copy']) $this->script('assets/prism/prism-copy.js','bac-prism-copy',['bac-prism-toolbar']);
        if ($this->script('assets/prism/prism-autoloader.js','bac-prism-autoloader',[self::PRISM_CORE])) {
            \wp_localize_script('bac-prism-autoloader','BAC_Prism',['langPath'=>\esc_url(BAC_PLUGIN_URL.'assets/prism/components/')]);
            \wp_add_inline_script('bac-prism-autoloader',
                'if(window.Prism&&Prism.plugins&&Prism.plugins.autoloader&&window.BAC_Prism){Prism.plugins.autoloader.languages_path=BAC_Prism.langPath;Prism.plugins.autoloader.use_minified=false;}',
                'after');
        }
    }

    private function enqueuePrismEnhancements(): void {
        $this->style('assets/prism/prism-titlebar.css', 'bac-prism-titlebar', ['bac-prism-wrap']);
        $this->style('assets/prism/prism-fold.css', 'bac-prism-fold', ['bac-prism-titlebar']);
        $deps = [self::PRISM_CORE];
        if (\wp_script_is('bac-prism-toolbar', 'registered')) $deps[] = 'bac-prism-toolbar';
        $this->script('assets/prism/prism-titlebar.js', 'bac-prism-titlebar', $deps);
        $this->script('assets/prism/prism-fold.js', 'bac-prism-fold', ['bac-prism-titlebar']);
        if ($this->opts['prism_previewers']) {
            $this->script('assets/prism/prism-previewers-init.js', 'bac-prism-previewers-init', ['bac-prism-previewers']);
        }
    }

    private function enqueueMediumZoom(): void { $this->script('assets/js/medium-zoom.min.js','bac-medium-zoom',[],'1.1.0'); }

    private function enqueueFrontendInit(): void {
        if (!$this->opts['mermaid_enabled']) return;
        $deps = [];
        if (!$this->script('assets/mermaid/mermaid-init.js','bac-mermaid-init',$deps)) return;
        if ($this->opts['mermaid_enabled']) { $this->style('assets/mermaid/mermaid.css','bac-mermaid'); \wp_localize_script('bac-mermaid-init','BAC_Mermaid',['mermaidUrl'=>\esc_url(BAC_PLUGIN_URL.'assets/mermaid/mermaid.esm.min.mjs')]); }
    }

    private function enqueueMermaidEnhancements(): void {
        $this->style('assets/mermaid/mermaid-enhance.css', 'bac-mermaid-enhance', ['bac-mermaid']);
        $this->script('assets/mermaid/mermaid-enhance.js', 'bac-mermaid-enhance', ['bac-mermaid-init']);
    }

    private function enqueueMarkmap(): void {
        if (empty($this->opts['markmap_enabled'])) return;
        $this->style('assets/markmap/markmap.css','bac-markmap');
        if (!empty($this->opts['markmap_prerender'])) return;
        $r = $this->opts['markmap_runtime']??'local'; $v='assets/markmap/vendor/';
        if ($r==='cdn') { \wp_enqueue_script('bac-markmap-d3','https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js',[],'7.9.0',true); \wp_enqueue_script('bac-markmap-view','https://cdn.jsdelivr.net/npm/markmap-view@0.18.12/dist/browser/index.js',['bac-markmap-d3'],'0.18.12',true); \wp_enqueue_script('bac-markmap-lib','https://cdn.jsdelivr.net/npm/markmap-lib@0.18.12/dist/browser/index.js',['bac-markmap-view'],'0.18.12',true); $this->script('assets/markmap/markmap-init.js','bac-markmap-init',['bac-markmap-lib']); }
        else { if (!$this->script($v.'d3.min.js','bac-markmap-d3')) return; if (!$this->script($v.'markmap-view.min.js','bac-markmap-view',['bac-markmap-d3'])) return; if (!$this->script($v.'markmap-lib.min.js','bac-markmap-lib',['bac-markmap-view'])) return; $this->script('assets/markmap/markmap-init.js','bac-markmap-init',['bac-markmap-lib']); }
    }

    private function enqueueMathJax(): void {
        if (empty($this->opts['mathjax_enabled'])) return;
        $mathConfig = 'window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]],displayMath:[["$$","$$"],["\\\\[","\\\\]"]]},options:{ignoreHtmlClass:"no-mathjax"}};';
        $this->style('assets/css/bac-latex.css', 'bac-latex');
        if (!$this->script('assets/mathjax/es5/tex-chtml.js','bac-mathjax')) return;
        \wp_add_inline_script('bac-mathjax', $mathConfig, 'before');
        $this->script('assets/mathjax/mathjax-init.js','bac-mathjax-init',['bac-mathjax']);
    }

    /**
     * Enqueue KaTeX assets (CSS + JS + auto-render + init).
     * KaTeX is a fast math rendering library — alternative to MathJax.
     * Reference: githuber-md/src/Modules/KaTeX.php
     */
    private function enqueueKatex(): void {
        if (empty($this->opts['katex_enabled'])) return;
        $v = $this->opts['katex_version'] ?? Options::DEFAULTS['katex_version'];
        $d = 'assets/katex/';

        // CSS
        $this->style($d . 'katex.min.css', 'bac-katex', [], $v);
        $this->style('assets/css/bac-latex.css', 'bac-latex', ['bac-katex']);

        // JS: core + auto-render + init
        if (!$this->script($d . 'katex.min.js', 'bac-katex-js', [], $v)) return;
        $this->script($d . 'auto-render.min.js', 'bac-katex-autorender', ['bac-katex-js'], $v);
        $this->script($d . 'katex-init.js', 'bac-katex-init', ['bac-katex-autorender']);
    }

    private function enqueueReadingEnhancements(): void {
        $this->style('assets/reading/reading-progress.css', 'bac-reading-progress');
        $this->style('assets/reading/content-enhance.css', 'bac-content-enhance');
        $this->style('assets/reading/arcaea-article-content.css', 'bac-arcaea-article-content', ['bac-content-enhance']);
        $this->script('assets/reading/reading-progress.js', 'bac-reading-progress');
    }

    private function script(string $rel, string $h, array $d=[], ?string $v=null): bool { $p=BAC_PLUGIN_DIR.\ltrim($rel,'/'); if(!\file_exists($p))return false; \wp_enqueue_script($h,BAC_PLUGIN_URL.\ltrim($rel,'/'),$d,$v??(string)\filemtime($p),true); return true; }
    private function style(string $rel, string $h, array $d=[], ?string $v=null): bool { $p=BAC_PLUGIN_DIR.\ltrim($rel,'/'); if(!\file_exists($p))return false; \wp_enqueue_style($h,BAC_PLUGIN_URL.\ltrim($rel,'/'),$d,$v??(string)\filemtime($p)); return true; }
}
