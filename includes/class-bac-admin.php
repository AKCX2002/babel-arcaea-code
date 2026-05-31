<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Admin {
    private array $opts;
    public function __construct() { $this->opts = Plugin::init()->options()->get(); \add_action('admin_menu',[$this,'menu']); \add_action('admin_init',[$this,'registerSettings']); \add_action('admin_enqueue_scripts',[$this,'css']); \add_action('update_option_bac_options',[$this,'onUpdate'],10,2); \add_action('admin_post_bac_rescan_posts',[$this,'rescanPosts']); }

    public function css(string $hook): void { if(\strpos($hook,'bac-panel')===false&&\strpos($hook,'bac-options')===false)return; \wp_enqueue_style('bac-admin',BAC_PLUGIN_URL.'assets/css/bac-admin.css',[],BAC_VERSION); }
    public function menu(): void { \add_menu_page('Babel Arcaea Code','Arcaea Code','manage_options','bac-panel',[$this,'render'],'dashicons-editor-code',81); \add_options_page('Babel Arcaea Code','Arcaea Code','manage_options','bac-options',[$this,'render']); }
    public function registerSettings(): void { Plugin::init()->options()->registerSettings(); }
    public function onUpdate($o,$n): void { Plugin::init()->options()->flush(); if(\function_exists('bac_markmap_clear_cache'))\bac_markmap_clear_cache(); }
    public function rescanPosts(): void {
        if (!\current_user_can('manage_options')) \wp_die('Forbidden');
        \check_admin_referer('bac_rescan_posts');
        $count = Detector::scanAll();
        \wp_safe_redirect(\add_query_arg(['page'=>'bac-panel','bac_rescanned'=>$count], \admin_url('admin.php')));
        exit;
    }

    public function render(): void { if(!\current_user_can('manage_options'))return; $theme=\wp_get_theme(); ?>
<div class="wrap">
<h1>Babel Arcaea Code</h1>
<p><?php \_e('统一 Prism.js + Mermaid + LaTeX + Markmap 渲染引擎。本地化资源优先。','babel-arcaea-code') ?></p>
<?php if(isset($_GET['bac_rescanned'])): ?><div class="notice notice-success is-dismissible"><p><?php echo \esc_html(sprintf('已重扫 %d 篇文章的模块标记。', (int) $_GET['bac_rescanned'])) ?></p></div><?php endif; ?>
<?php if(\function_exists('\BabelArcaeaCode\Health::renderTable')){ \ob_start(); Health::renderTable(); echo \ob_get_clean(); } ?>
<form method="post" action="options.php">
<?php \settings_fields('bac_settings_group') ?>
<table class="form-table">
<tr><th>启用</th><td><?php $this->cb('enabled','总开关') ?></td></tr>
<tr><th>Prism.js</th><td><?php $this->cb('prism_enabled','启用 Prism 代码高亮') ?></td></tr>
<tr><th>Mermaid</th><td><?php $this->cb('mermaid_enabled','启用 Mermaid 图表') ?> <?php $this->sel('mermaid_compat_mode',['off'=>'关闭','auto'=>'自动（推荐）','force'=>'强制开启']) ?><p class="description">版本 <?php echo \esc_html($this->opts['mermaid_version']) ?>。支持 <code>language-mermaid</code>、MerPress 前台输出和 <code>[mermaid]</code> 短代码。</p><p class="description">兼容模式会在前台渲染前预处理 <code>subgraph -&gt; subgraph</code> 连线，绕开 Sakurairo 正文样式对 Mermaid HTML label 的布局干扰。</p></td></tr>
<tr><th>LaTeX</th><td><?php $this->cb('latex_enabled','启用数学公式渲染') ?> <?php $this->sel('latex_renderer',['katex'=>'KaTeX（推荐）','mathjax'=>'MathJax']) ?><p class="description">兼容 <code>```katex</code> / <code>```latex</code> / <code>```mathjax</code> 代码块与行内公式。</p></td></tr>
<tr><th>Markmap</th><td><?php $this->cb('markmap_enabled','启用 Markmap 思维导图') ?><p class="description">兼容 <code>language-markmap</code>、<code>language-mindmap</code>、<code>[markmap]</code> 和 <code>[mindmap]</code>。</p></td></tr>
<tr><th>Markmap Runtime</th><td><?php $this->sel('markmap_runtime',['local'=>'本地资源模式','cdn'=>'CDN 调试模式']) ?></td></tr>
<tr><th>预渲染</th><td><?php $this->cb('markmap_prerender','启用 CLI 预渲染（需 Node.js）') ?></td></tr>
<tr><th>Sakurairo Prism</th><td><?php $this->cb('disable_sakurairo_prism','禁用主题自带 Prism') ?><p class="description">当前主题：<?php echo \esc_html($theme->get('Name')?:'未知') ?></p></td></tr>
<tr><th>旧插件资源</th><td><?php $this->cb('disable_legacy_plugin_assets','接管 Githuber MD / MerPress 前台 JS 与样式') ?><p class="description">旧插件只保留内容格式，前台渲染由 BAC 统一接管，避免老版本 Mermaid / MathJax / Prism 冲突。</p></td></tr>
<tr><th>APlayer</th><td><?php $this->cb('aplayer_safe_patch','容器缺失时跳过初始化') ?></td></tr>
<tr><th>LightGallery</th><td><?php $this->cb('suppress_lightgallery_warn','抑制 license warning（⚠调试用）') ?></td></tr>
<tr><th>Prism 主题</th><td><?php $this->sel('prism_theme',['arcaea_dark'=>'Arcaea Dark','arcaea_light'=>'Arcaea Light']) ?></td></tr>
<tr><th>行号</th><td><?php $this->cb('prism_line_numbers','显示行号') ?></td></tr>
<tr><th>复制</th><td><?php $this->cb('prism_copy','代码块复制按钮') ?></td></tr>
<tr><th>括号匹配</th><td><?php $this->cb('prism_braces','Prism Match Braces') ?></td></tr>
<tr><th>Previewers</th><td><?php $this->cb('prism_previewers','颜色/渐变/时间实时预览') ?></td></tr>
</table>
<?php \submit_button() ?>
</form>
<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')) ?>" style="margin-top:16px">
<?php \wp_nonce_field('bac_rescan_posts') ?>
<input type="hidden" name="action" value="bac_rescan_posts">
<?php \submit_button('重扫已有文章模块标记','secondary','submit',false) ?>
<p class="description">启用新渲染能力后，建议执行一次重扫，让条件加载的脚本标记与现有文章同步。</p>
</form>
</div><?php }

    private function cb(string $k, string $l): void { $id='bac-opt-'.$k; printf('<label for="%s"><input type="checkbox" id="%s" name="bac_options[%s]" value="1" %s> %s</label>',\esc_attr($id),\esc_attr($id),\esc_attr($k),\checked($this->opts[$k]??0,1,false),\esc_html($l)); }
    private function sel(string $k, array $o): void { $id='bac-opt-'.$k; echo'<select id="'.\esc_attr($id).'" name="bac_options['.\esc_attr($k).']">'; foreach($o as $v=>$l)printf('<option value="%s" %s>%s</option>',\esc_attr($v),\selected(($this->opts[$k]??'')===$v,true,false),\esc_html($l)); echo'</select>'; }
}
