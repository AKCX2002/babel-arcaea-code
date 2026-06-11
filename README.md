# Babel Arcaea Code

统一 Prism.js + Mermaid + MathJax + Markmap 渲染引擎。本地化资源优先，适配 Sakurairo WordPress 技术博客。

## 功能

- **Prism.js** 本地化（核心 + 20 语言 + toolbar + copy + line-numbers + autoloader）
- **Mermaid** 本地化（ESM 模块，版本锁定 11.15.0）
- **MathJax** 本地化加载（按后台开关启用）
- **Markmap Adapter**：Markdown 思维导图渲染层，支持 `language-markmap` 代码块和 `[markmap]` shortcode
- **PHP filter** 在 `the_content` 中提前替换 mermaid / markmap 代码块，避免 Prism 误高亮图表源码
- **前台兼容层**：兼容 `WP Githuber MD` 的 `language-mermaid` 代码块与 `MerPress` 的 `pre.mermaid` 区块输出
- **特殊字符清洗**：前台渲染前自动解码 HTML entities、NBSP、零宽字符和常见弯引号，减少 Mermaid/Markmap 因排版字符导致的解析失败
- **PJAX 支持**（Sakurairo）
- **禁用 Sakurairo 自带 Prism**（可配置）
- **GitHub 自动更新**（PUC library）
- **WordPress Abilities / MCP**：按分组注册 `bac/*` abilities，可通过 `wordpress/mcp-adapter` 的默认 server 发现和执行

## 安装

```bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/AKCX2002/babel-arcaea-code.git
```

后台 → 插件 → 启用 → 设置 → Arcaea Code。

## MCP / Abilities

插件会在 WordPress Abilities API 可用时自动注册 `bac/*` abilities，不新增后台开关。

当前分组：

- `bac/content-types-list`
- `bac/posts-*` 与 `bac/pages-*`
- `bac/content-by-slug`
- `bac/taxonomies-list`、`bac/terms-*`、`bac/content-terms-*`
- `bac/media-*`
- `bac/users-*`
- `bac/comments-*`
- `bac/plugins-*`

说明：

- 所有 ability 都通过 `meta.mcp.public = true` 暴露给 `mcp-adapter` 默认 server
- `public` 只表示可被 MCP 发现；执行仍按当前 WordPress 用户的对象级 capability 校验
- 正文写入只接受原始 HTML / Gutenberg blocks，不做 Markdown 转换
- 首版只服务当前站点，不实现 InstaWP 风格的 multi-site / site-management

## 设置

| 选项 | 推荐值 |
|------|--------|
| 总开关 | 开 |
| Prism.js | 开 |
| Mermaid | 开 |
| MathJax | 按需开启 |
| Markmap | 按需开启 |
| Markmap Runtime | CDN 调试模式 / 正式站本地资源模式 |
| 禁用 Sakurairo Prism | 开 |
| 行号 | 开 |
| 复制按钮 | 开 |
| Prism 主题 | Arcaea Dark |

## Markmap / MindMap 路线

Mermaid 的 `mindmap` 更适合轻量静态图；如果目标是接近 XMind 的交互式 Markdown 思维导图，推荐使用 Markmap。

推荐路线：

```text
Markdown
↓
Markmap
↓
Interactive MindMap / SVG / HTML
↓
WordPress
```

当前 v1.0.23 提供 render-only Markmap 支持：

- `language-markmap` 代码块
- `[markmap]...[/markmap]` shortcode
- Arcaea Dark / 玻璃拟态样式
- PJAX-safe 初始化
- resize 后自动 fit

大型知识树建议后续使用：

```text
Markmap CLI
↓
HTML / SVG
↓
iframe
↓
WordPress
```

这样可以减少 Sakurairo + PJAX + APlayer + Mermaid + Markmap 多运行时之间的冲突。

### 代码块用法

````markdown
```markmap
# Babel Arcaea Code

## Prism
- Toolbar
- Copy Button
- Line Numbers

## Mermaid
- Flowchart
- Sequence
- State Diagram

## Markmap
- Markdown MindMap
- Foldable nodes
- SVG render
```
````

### Shortcode 用法

```text
[markmap]
# Blog Render Layer

## Prism.js
- Code highlight
- Previewers

## Mermaid
- Diagrams

## Markmap
- MindMap
[/markmap]
```

## 支持的语言

c, cpp, bash, python, json, yaml, cmake, makefile, dart,
javascript, typescript, rust, go, php, markdown, css, diff,
ini, toml, xml (+ autoloader 按需加载其他语言)

## WordPress 推荐配置

### WP Githuber MD

```
- 关闭内置 Prism / Highlight.js
- 关闭内置 Mermaid 渲染
- MathJax 可只保留语法输出，脚本由本插件加载
```

前台文章内容仍可继续使用 ` ```mermaid ` / ` ```markmap ` 代码块，Babel Arcaea Code 会负责网页端渲染与 Sakurairo 风格覆盖。

### Sakurairo

```
- 禁用主题自带 Prism（插件默认已勾选）
- 如使用 medium-zoom，可关闭 LightGallery
- APlayer 修复开关仅在报错时开启
```

### Markmap

```
- 正式站点推荐 local 模式
- CDN 仅用于调试
- local 模式必须存在 assets/markmap/vendor/ 运行时文件
- 预渲染模式（推荐）：在插件设置中启用"服务端预渲染"
  - 需要服务器安装 Node.js
  - SVG 缓存于 wp-content/uploads/bac-markmap-cache/
  - 前端无需加载 d3 / markmap-view / markmap-lib JS
  - 大幅提升 SEO 和 PJAX 稳定性
```

### MerPress

```
- 编辑器块可继续使用
- 前台 Mermaid 盒子样式由本插件统一包裹为 Arcaea / Sakurairo 风格
- Sakurairo PJAX 场景下由本插件负责再次扫描并重渲染
```

## 发布包检查

发布前确认以下文件完整：

```
babel-arcaea-code.php
assets/prism/prism.js
assets/prism/components/
assets/mermaid/mermaid.esm.min.mjs
assets/mermaid/chunks/
assets/markmap/vendor/d3.min.js
assets/markmap/vendor/markmap-view.min.js
assets/markmap/vendor/markmap-lib.min.js
assets/markmap/vendor/versions.json
assets/mathjax/es5/tex-chtml.js
assets/js/medium-zoom.min.js
lib/plugin-update-checker.php
```

## License

GPL-2.0-or-later
