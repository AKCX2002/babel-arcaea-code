<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Assets {
    private array $opts;
    private const PRISM_CORE = 'bac-prism-core';

    public function __construct() { $this->opts = Plugin::init()->options()->get(); \add_action('wp_enqueue_scripts', [$this,'enqueueAll']); }

    public function enqueueAll(): void {
        if (\is_admin() || empty($this->opts['enabled'])) return;
        if ($this->opts['prism_enabled']) { $this->enqueuePrismCss(); $this->enqueuePrismJs(); }
        $this->enqueueMediumZoom(); $this->enqueueFrontendInit(); $this->enqueueMarkmap(); $this->enqueueMathJax();
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
        }
    }

    private function enqueueMediumZoom(): void { $this->script('assets/js/medium-zoom.min.js','bac-medium-zoom',[],'1.1.0'); }

    private function enqueueFrontendInit(): void {
        if (!$this->opts['mermaid_enabled'] && !$this->opts['prism_enabled']) return;
        $deps = [];
        if ($this->opts['prism_enabled'] && \wp_script_is(self::PRISM_CORE,'registered')) $deps[] = self::PRISM_CORE;
        if (\wp_script_is('bac-medium-zoom','registered')) $deps[] = 'bac-medium-zoom';
        if (!$this->script('assets/mermaid/mermaid-init.js','bac-mermaid-init',$deps)) return;
        \wp_localize_script('bac-mermaid-init','BAC_Config',['lineNumbers'=>!empty($this->opts['prism_line_numbers']),'prismEnabled'=>!empty($this->opts['prism_enabled']),'mermaidEnabled'=>!empty($this->opts['mermaid_enabled'])]);
        if ($this->opts['mermaid_enabled']) { $this->style('assets/mermaid/mermaid.css','bac-mermaid'); \wp_localize_script('bac-mermaid-init','BAC_Mermaid',['mermaidUrl'=>\esc_url(BAC_PLUGIN_URL.'assets/mermaid/mermaid.esm.min.mjs')]); }
    }

    private function enqueueMarkmap(): void {
        if (empty($this->opts['markmap_enabled'])) return;
        $this->style('assets/markmap/markmap.css','bac-markmap');
        if (!empty($this->opts['markmap_prerender'])) return;
        $r = $this->opts['markmap_runtime']??'local'; $v='assets/markmap/vendor/';
        if ($r==='cdn') { \wp_enqueue_script('bac-markmap-d3','https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js',[],'7.9.0',true); \wp_enqueue_script('bac-markmap-view','https://cdn.jsdelivr.net/npm/markmap-view@0.18.12/dist/browser/index.js',['bac-markmap-d3'],'0.18.12',true); \wp_enqueue_script('bac-markmap-lib','https://cdn.jsdelivr.net/npm/markmap-lib@0.18.12/dist/browser/index.js',['bac-markmap-view'],'0.18.12',true); $this->script('assets/markmap/markmap-init.js','bac-markmap-init',['bac-markmap-lib']); }
        else { if (!$this->script($v.'d3.min.js','bac-markmap-d3')) return; if (!$this->script($v.'markmap-view.min.js','bac-markmap-view',['bac-markmap-d3'])) return; if (!$this->script($v.'markmap-lib.min.js','bac-markmap-lib',['bac-markmap-view'])) return; $this->script('assets/markmap/markmap-init.js','bac-markmap-init',['bac-markmap-lib']); }
    }

    private function enqueueMathJax(): void { if (!$this->opts['mathjax_enabled']) return; \add_action('wp_head',fn()=>print('<script>window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]]},svg:{fontCache:"global"},options:{ignoreHtmlClass:"no-mathjax"}}</script>'),0); $this->script('assets/mathjax/es5/tex-chtml.js','bac-mathjax'); }

    private function script(string $rel, string $h, array $d=[], string $v=BAC_VERSION): bool { $p=BAC_PLUGIN_DIR.\ltrim($rel,'/'); if(!\file_exists($p))return false; \wp_enqueue_script($h,BAC_PLUGIN_URL.\ltrim($rel,'/'),$d,$v,true); return true; }
    private function style(string $rel, string $h, array $d=[], string $v=BAC_VERSION): bool { $p=BAC_PLUGIN_DIR.\ltrim($rel,'/'); if(!\file_exists($p))return false; \wp_enqueue_style($h,BAC_PLUGIN_URL.\ltrim($rel,'/'),$d,$v); return true; }
}
