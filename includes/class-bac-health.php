<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Health {
    public static function check(): array { return ['prism_core'=>self::a('assets/prism/prism.js'),'mermaid_esm'=>self::a('assets/mermaid/mermaid.esm.min.mjs'),'mermaid_chunks'=>self::chunks(),'markmap_vendor'=>self::a('assets/markmap/vendor/d3.min.js'),'markmap_render_script'=>(\file_exists(BAC_PLUGIN_DIR.'bin/markmap-render.js')?'found':'missing'),'node'=>self::node(),'cache_dir'=>self::cache(),'proc_open'=>(\function_exists('proc_open')&&\function_exists('proc_close')?'available':'disabled'),'sakurairo'=>self::theme()]; }
    public static function renderTable(): void { $h=self::check(); ?><div class="bac-health-check-panel"><h3>系统健康检查</h3><table class="widefat striped" style="width:auto;min-width:400px"><thead><tr><th>项目</th><th>状态</th></tr></thead><tbody><?php foreach($h as $l=>$s): $ok=\strpos($s,'not found')===false&&\strpos($s,'missing')===false&&\strpos($s,'disabled')===false&&\strpos($s,'unavailable')===false; ?><tr><td><?php echo \esc_html($l) ?></td><td style="color:<?php echo $ok?'#4caf50':'#ef5350' ?>"><?php echo \esc_html($s) ?></td></tr><?php endforeach; ?></tbody></table><p class="description" style="margin-top:8px">如果资源标记为 missing，请重新运行 CI 同步或检查插件文件完整性。</p></div><?php }
    private static function a(string $r): string { return \file_exists(BAC_PLUGIN_DIR.\ltrim($r,'/'))?'found':'missing'; }
    private static function chunks(): string { $g=\defined('BAC_PLUGIN_DIR')?\glob(BAC_PLUGIN_DIR.'assets/mermaid/chunks/mermaid.esm.min/*.mjs'):false; return(\is_array($g)&&\count($g)>0)?\count($g).' files':'missing'; }
    private static function node(): string { if(!\function_exists('bac_find_node'))return'checker unavailable'; $n=\bac_find_node(); if(!$n)return'not found'; $v=\function_exists('exec')?@\exec(\escapeshellcmd($n).' --version'):''; return'found '.$n.($v?' '.\trim($v):''); }
    private static function cache(): string { if(!\function_exists('bac_markmap_cache_dir'))return'checker unavailable'; $d=@\bac_markmap_cache_dir(); return($d&&\is_dir($d)&&\is_writable($d))?'writable':'unavailable'; }
    private static function theme(): string { $t=\wp_get_theme(); return($t->get_template()==='Sakurairo'||$t->get('Name')==='Sakurairo')?'detected':'not detected'; }
}
