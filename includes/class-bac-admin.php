<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Admin {
    private array $opts;
    public function __construct() { $this->opts = Plugin::init()->options()->get(); \add_action('admin_menu',[$this,'menu']); \add_action('admin_init',[$this,'registerSettings']); \add_action('admin_enqueue_scripts',[$this,'css']); \add_action('update_option_bac_options',[$this,'onUpdate'],10,2); }

    public function css(string $hook): void { if(\strpos($hook,'bac-panel')===false&&\strpos($hook,'bac-options')===false)return; \wp_enqueue_style('bac-admin',BAC_PLUGIN_URL.'assets/css/bac-admin.css',[],BAC_VERSION); }
    public function menu(): void { \add_menu_page('Babel Arcaea Code','Arcaea Code','manage_options','bac-panel',[$this,'render'],'dashicons-editor-code',81); \add_options_page('Babel Arcaea Code','Arcaea Code','manage_options','bac-options',[$this,'render']); }
    public function registerSettings(): void { Plugin::init()->options()->registerSettings(); }
    public function onUpdate($o,$n): void { Plugin::init()->options()->flush(); if(\function_exists('bac_markmap_clear_cache'))\bac_markmap_clear_cache(); }

    public function render(): void { if(!\current_user_can('manage_options'))return; $theme=\wp_get_theme(); ?>
<div class="wrap"><h1>Babel Arcaea Code</h1><p><?php \_e('统一 Prism.js + Mermaid + MathJax + Markmap 渲染引擎。本地化资源优先。','babel-arcaea-code') ?></p>
<?php if(\function_exists('\BabelArcaeaCode\Health::renderTable')){ \ob_start(); Health::renderTable(); echo \ob_get_clean(); } ?>
<form method="post" action="options.php"><?php \settings_fields('bac_settings_group') ?>
<table class="form-table">
<tr><th>启用</th><td><?php $this->cb('enabled','总开关') ?></td></tr>
<tr><th>Prism.js</th><td><?php $this->cb('prism_enabled','启用 Prism 代码高亮') ?></td></tr>
<tr><th>Mermaid</th><td><?php $this->cb('mermaid_enabled','启用 Mermaid 图表') ?><p class="description">版本 <?php echo \esc_html($this->opts['mermaid_version']) ?>。支持 <code>language-mermaid</code> 和 <code>[mermaid]</code> 短代码。</p></td></tr>
<tr><th>MathJax</th><td><?php $this->cb('mathjax_enabled','启用 MathJax 数学公式') ?></td></tr>
<tr><th>Markmap</th><td><?php $this->cb('markmap_enabled','启用 Markmap 思维导图') ?></td></tr>
<tr><th>Markmap Runtime</th><td><?php $this->sel('markmap_runtime',['local'=>'本地资源模式','cdn'=>'CDN 调试模式']) ?></td></tr>
<tr><th>预渲染</th><td><?php $this->cb('markmap_prerender','启用 CLI 预渲染（需 Node.js）') ?></td></tr>
<tr><th>Sakurairo Prism</th><td><?php $this->cb('disable_sakurairo_prism','禁用主题自带 Prism') ?><p class="description">当前主题：<?php echo \esc_html($theme->get('Name')?:'未知') ?></p></td></tr>
<tr><th>APlayer</th><td><?php $this->cb('aplayer_safe_patch','容器缺失时跳过初始化') ?></td></tr>
<tr><th>LightGallery</th><td><?php $this->cb('suppress_lightgallery_warn','抑制 license warning（⚠调试用）') ?></td></tr>
<tr><th>Prism 主题</th><td><?php $this->sel('prism_theme',['arcaea_dark'=>'Arcaea Dark','arcaea_light'=>'Arcaea Light']) ?></td></tr>
<tr><th>行号</th><td><?php $this->cb('prism_line_numbers','显示行号') ?></td></tr>
<tr><th>复制</th><td><?php $this->cb('prism_copy','代码块复制按钮') ?></td></tr>
<tr><th>括号匹配</th><td><?php $this->cb('prism_braces','Prism Match Braces') ?></td></tr>
<tr><th>Previewers</th><td><?php $this->cb('prism_previewers','颜色/渐变/时间实时预览') ?></td></tr>
</table><?php \submit_button() ?></form></div><?php }

    private function cb(string $k, string $l): void { $id='bac-opt-'.$k; printf('<label for="%s"><input type="checkbox" id="%s" name="bac_options[%s]" value="1" %s> %s</label>',\esc_attr($id),\esc_attr($id),\esc_attr($k),\checked($this->opts[$k]??0,1,false),\esc_html($l)); }
    private function sel(string $k, array $o): void { $id='bac-opt-'.$k; echo'<select id="'.\esc_attr($id).'" name="bac_options['.\esc_attr($k).']">'; foreach($o as $v=>$l)printf('<option value="%s" %s>%s</option>',\esc_attr($v),\selected(($this->opts[$k]??'')===$v,true,false),\esc_html($l)); echo'</select>'; }
}
