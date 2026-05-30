<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Options {
    public const DEFAULTS = [
        'enabled'=>1,'prism_enabled'=>1,'mermaid_enabled'=>1,'mathjax_enabled'=>0,'markmap_enabled'=>0,
        'katex_enabled'=>0,
        'markmap_runtime'=>'local','markmap_prerender'=>0,'mermaid_version'=>'11.15.0',
        'prism_version'=>'1.30.0','mathjax_version'=>'3.2.2','katex_version'=>'0.16.25','prism_line_numbers'=>1,
        'prism_copy'=>1,'prism_braces'=>1,'prism_previewers'=>1,'prism_theme'=>'arcaea_dark',
        'disable_sakurairo_prism'=>1,'aplayer_safe_patch'=>0,'suppress_lightgallery_warn'=>0,
    ];
    private const ALLOWED = ['markmap_runtime'=>['cdn','local'],'mermaid_version'=>['11.15.0'],'prism_theme'=>['arcaea_dark','arcaea_light']];
    private ?array $cache = null;

    public function defaults(): array { return self::DEFAULTS; }
    public function get(): array {
        if ($this->cache !== null) return $this->cache;
        $saved = \get_option('bac_options', []);
        $merged = self::DEFAULTS;
        if (\is_array($saved)) { foreach (self::DEFAULTS as $k => $d) { if (\array_key_exists($k, $saved)) $merged[$k] = $saved[$k]; } }
        return $this->cache = $merged;
    }
    public function getKey(string $key) { $opts = $this->get(); return $opts[$key] ?? self::DEFAULTS[$key] ?? null; }
    public function flush(): void { $this->cache = null; }

    public static function sanitize($in): array {
        $in = \is_array($in) ? $in : []; $out = [];
        foreach (['enabled','prism_enabled','mermaid_enabled','mathjax_enabled','markmap_enabled','katex_enabled','markmap_prerender','prism_line_numbers','prism_copy','prism_braces','prism_previewers','disable_sakurairo_prism','aplayer_safe_patch','suppress_lightgallery_warn'] as $f) { $out[$f] = empty($in[$f]) ? 0 : 1; }
        foreach (self::ALLOWED as $f => $vals) { $v = $in[$f] ?? ''; $out[$f] = \in_array($v, $vals, true) ? \sanitize_key($v) : self::DEFAULTS[$f]; }
        $out['prism_version'] = self::DEFAULTS['prism_version'];
        $out['mathjax_version'] = self::DEFAULTS['mathjax_version'];
        return $out;
    }

    public function registerSettings(): void {
        \register_setting('bac_settings_group', 'bac_options', ['type'=>'array','sanitize_callback'=>[self::class,'sanitize'],'default'=>self::DEFAULTS]);
    }
}

// Backward-compatible global wrappers
if (!\function_exists('bac_options')) { function bac_options(): array { return \BabelArcaeaCode\Plugin::init()->options()->get(); } }
if (!\function_exists('bac_sanitize_options')) { function bac_sanitize_options($in): array { return \BabelArcaeaCode\Options::sanitize($in); } }
