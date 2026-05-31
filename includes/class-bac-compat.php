<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Compat {
    private array $opts;
    public function __construct() { $this->opts = Plugin::init()->options()->get(); if(empty($this->opts['enabled']))return; if(!empty($this->opts['disable_sakurairo_prism']))\add_action('wp_enqueue_scripts',[$this,'disablePrism'],999); if(!empty($this->opts['disable_legacy_plugin_assets']))\add_action('wp_enqueue_scripts',[$this,'disableLegacyPluginAssets'],999); if(!empty($this->opts['aplayer_safe_patch']))\add_action('wp_head',[$this,'aplayerPatch'],0); if(!empty($this->opts['suppress_lightgallery_warn']))\add_action('wp_head',[$this,'lgSuppress'],999); }

    public function disablePrism(): void {
        $s=['style','script']; $h=['prism-','code-highlight','highlight-','highlightjs-','sakurairo-prism','sakura-prism'];
        foreach($s as $t){ $fn=$t==='style'?'wp_dequeue_style':'wp_dequeue_script'; $fn2=$t==='style'?'wp_deregister_style':'wp_deregister_script'; $is=$t==='style'?'wp_style_is':'wp_script_is'; foreach($h as $p){if($is($p,'enqueued'))$fn($p); if($is($p,'registered'))$fn2($p);} }
    }
    public function disableLegacyPluginAssets(): void {
        $this->removeLegacy('script');
        $this->removeLegacy('style');
    }
    private function removeLegacy(string $type): void {
        global $wp_scripts, $wp_styles;
        $obj = $type === 'script' ? $wp_scripts : $wp_styles;
        if (!$obj || empty($obj->registered) || !\is_array($obj->registered)) return;
        $dequeue = $type === 'script' ? 'wp_dequeue_script' : 'wp_dequeue_style';
        $deregister = $type === 'script' ? 'wp_deregister_script' : 'wp_deregister_style';
        foreach ($obj->registered as $handle => $item) {
            $src = isset($item->src) ? (string) $item->src : '';
            if ($src === '') continue;
            if (\strpos($src, '/githuber-md/') === false && \strpos($src, '/merpress/') === false) continue;
            $dequeue($handle);
            $deregister($handle);
        }
    }
    public function aplayerPatch(): void { ?><script>(function(){var A=window.APlayer;Object.defineProperty(window,'APlayer',{get:function(){return A},set:function(v){if(v&&v.prototype&&v.prototype.init){var O=v.prototype.init;v.prototype.init=function(){if(this.options&&typeof this.options.container==='string'&&!document.querySelector(this.options.container))return;return O.apply(this,arguments)};}A=v},configurable:true});if(A)window.APlayer=A})();</script><?php }
    public function lgSuppress(): void { ?><script>(function(){var cw=console.warn;console.warn=function(){var m=Array.prototype.join.call(arguments,' ');if(m.indexOf('license key')>=0||m.indexOf('LightGallery')>=0)return;return cw.apply(console,arguments)}})();</script><?php }
}
