# Babel Arcaea Code

统一 Prism.js + Mermaid + MathJax + Markmap 渲染引擎。本地化资源优先，适配 Sakurairo WordPress 技术博客。

## 功能

- **Prism.js** 本地化（核心 + 20 语言 + toolbar + copy + line-numbers + autoloader）
- **Mermaid** 本地化（ESM 模块，版本锁定 11.15.0）
- **MathJax** 本地化加载（按后台开关启用）
- **Markmap Adapter**：Markdown 思维导图渲染层，支持 `language-markmap` 代码块和 `[markmap]` shortcode
- **PHP filter** 在 `the_content` 中提前替换 mermaid / markmap 代码块，避免 Prism 误高亮图表源码
- **PJAX 支持**（Sakurairo）
- **禁用 Sakurairo 自带 Prism**（可配置）
- **GitHub 自动更新**（PUC library）

## 安装

```bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/AKCX2002/babel-arcaea-code.git
```

后台 → 插件 → 启用 → 设置 → Arcaea Code。

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
