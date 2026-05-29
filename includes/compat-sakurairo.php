<?php
/**
 * Babel Arcaea Code — Sakurairo / Theme Compatibility
 *
 * Disables Sakurairo's built-in Prism, provides APlayer safe patch,
 * and suppresses LightGallery license warnings.
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

/* ── Disable Sakurairo's Prism ── */

add_action('wp_enqueue_scripts', function () {
    $o = bac_options();
    if (!$o['enabled'] || !$o['disable_sakurairo_prism']) return;

    $styles  = ['prism-style', 'prism-toolbar', 'prism-line-numbers', 'prism-autoloader', 'code-highlight'];
    $scripts = ['prism-script', 'prism-toolbar', 'prism-line-numbers', 'prism-autoloader', 'code-highlight'];

    bac_disable_handles('style', $styles);
    bac_disable_handles('script', $scripts);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $detected = false;
        foreach (array_merge($styles, $scripts) as $h) {
            if (wp_style_is($h) || wp_script_is($h)) {
                $detected = true;
                break;
            }
        }
        if ($detected) {
            error_log('[Babel Arcaea Code] Disabled Sakurairo Prism styles/scripts.');
        }
    }
}, 999);

/* ── APlayer safe patch ── */

/**
 * Output the APlayer safe-patch script that skips init when the container
 * DOM element is missing. Only fires when the option is enabled.
 */
function bac_aplayer_safe_patch() {
    if (empty(bac_options()['aplayer_safe_patch'])) return;
    ?><script>
(function(){
var _A=window.APlayer;
Object.defineProperty(window,'APlayer',{get:function(){return _A;},set:function(v){
if(v&&v.prototype&&v.prototype.init){
var O=v.prototype.init;v.prototype.init=function(){
if(this.options&&typeof this.options.container==='string'&&!document.querySelector(this.options.container))return;
return O.apply(this,arguments);};}
_A=v;},configurable:true});
if(_A)window.APlayer=_A;})();
</script><?php
}

add_action('wp_head', 'bac_aplayer_safe_patch', 0);

/* ── LightGallery warning suppression ── */

/**
 * Output script that suppresses LightGallery license key warnings.
 * This is a debug-only temporary measure; not recommended for production.
 */
function bac_suppress_lightgallery_warn() {
    if (empty(bac_options()['suppress_lightgallery_warn'])) return;
    ?><script>
(function(){var cw=console.warn;console.warn=function(){var m=Array.prototype.join.call(arguments,' ');
if(m.indexOf('license key')>=0||m.indexOf('LightGallery')>=0)return;return cw.apply(console,arguments);};})();
</script><?php
}

add_action('wp_head', 'bac_suppress_lightgallery_warn', 999);
