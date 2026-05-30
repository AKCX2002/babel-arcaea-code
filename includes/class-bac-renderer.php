<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Renderer {
    private array $opts;

    public function __construct() {
        $this->opts = Plugin::init()->options()->get();
        if (!$this->opts['enabled']) return;
        // Priority 11: AFTER wpautop(10). Strip <br/> injected by wpautop.
        // Per sakurairo-arcaea-blog-skill/references/prism-mermaid-conflict.md
        if ($this->opts['mermaid_enabled']) { \add_filter('the_content',[$this,'filterMermaid'],11); \add_shortcode('mermaid',[$this,'shortcodeMermaid']); }
        if ($this->opts['markmap_enabled']) { \add_filter('the_content',[$this,'filterMarkmap'],11); \add_shortcode('markmap',[$this,'shortcodeMarkmap']); }
    }

    private static function pattern(array $classes): string {
        $n = \implode('|',\array_map(fn($c)=>preg_quote($c,'/'),$classes));
        return '/<pre[^>]*>\s*<code[^>]*class=(["\'])(?=[^"\']*\b(?:language-'.$n.'|lang-'.$n.'|'.$n.')\b)[^"\']*\1[^>]*>(.*?)<\/code>\s*<\/pre>/si';
    }

    private static function clean(string $r): string {
        $code = \html_entity_decode(\trim($r), ENT_QUOTES|ENT_HTML5, 'UTF-8');
        // Strip <br/> injected by wpautop (priority 10) before our filter (priority 11)
        $code = \preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = \strip_tags($code);
        return \trim($code);
    }

    public function filterMermaid(string $c): string {
        return \preg_replace_callback(self::pattern(['mermaid']),fn($m)=>self::clean($m[2])===''?$m[0]:'<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'.\esc_html(self::clean($m[2])).'</div></div>',$c);
    }

    public function shortcodeMermaid(array $a, ?string $c): string { $c=self::clean((string)$c); return $c===''?'':'<div class="arcaea-mermaid-box"><div class="mermaid arcaea-mermaid-diagram">'.\esc_html($c).'</div></div>'; }

    public function filterMarkmap(string $c): string {
        return \preg_replace_callback(self::pattern(['markmap']),function($m){ $c=self::clean($m[2]); if($c==='')return $m[0];
            if(!empty($this->opts['markmap_prerender'])&&\function_exists('bac_markmap_render_svg')){$sv=\bac_markmap_render_svg($c); if($sv)return'<div class="arcaea-markmap-box arcaea-markmap-prerendered">'.$sv.'</div>';}
            return'<div class="arcaea-markmap-box"><pre class="arcaea-markmap-source">'.\esc_html($c).'</pre><svg class="arcaea-markmap-diagram"></svg></div>';
        },$c);
    }

    public function shortcodeMarkmap(array $a, ?string $c): string { $c=self::clean((string)$c); if($c==='')return'';
        if(!empty($this->opts['markmap_prerender'])&&\function_exists('bac_markmap_render_svg')){$sv=\bac_markmap_render_svg($c); if($sv)return'<div class="arcaea-markmap-box arcaea-markmap-prerendered">'.$sv.'</div>';}
        return'<div class="arcaea-markmap-box"><pre class="arcaea-markmap-source">'.\esc_html($c).'</pre><svg class="arcaea-markmap-diagram"></svg></div>';
    }
}
