# Changelog

## 1.3.0

### Added

- **Mermaid 响应式渲染架构重写**：Markdown → Mermaid 代码块 → Mermaid.js → SVG → 玻璃拟态容器 → 自动缩放 → 超宽滚动 → 全屏预览。
- Mermaid 玻璃拟态容器：`backdrop-filter: blur(16px)` + 发光边框 + 发光阴影，适配 Arcaea 暗色风格。
- Mermaid 悬停微动效：`hover` 时 `scale(1.01)` + 增强光晕。
- Mermaid 全屏预览功能：悬停显示「查看大图」按钮，点击弹出全屏覆层，支持 ESC / 点击背景 / ✕ 按钮关闭。
- Mermaid 自定义滚动条美化：半透明蓝光滚动条。
- 响应式适配：平板（768px）和小手机（420px）断点优化。

### Changed

- **CSS 架构重构**：移除 `min-width: 960px` 强制宽度的旧方案，改用 `min-width: fit-content` + wrapper `overflow-x: auto` 的新方案。小图自动居中，大图自然触发横向滚动。
- **JS 简化**：移除 `getResponsiveMermaidWidth` / `applyMermaidSvgWidth` 等旧逻辑，`normalizeMermaidSvg` 仅保留 viewBox 裁剪。
- 版本号从 1.2.0 升至 1.3.0。

## 1.2.0

### Added

- Markmap CLI server-side pre-render engine (`bin/markmap-render.js`).
- Markmap pre-rendered SVG cache in `wp-content/uploads/bac-markmap-cache/`.
- Settings toggle for server-side pre-render mode ("Markmap 服务端预渲染").
- Front-end JS dependencies (d3, markmap-view, markmap-lib) are skipped when pre-render is enabled.
- Pre-rendered SVG styling in `markmap.css`.
- CI validation for CLI renderer in validate.yml.

### Changed

- Version bumped from 1.1.1 to 1.2.0.

## 1.1.0

### Added

- Added optional APlayer safe patch toggle.
- Added optional LightGallery warning suppression toggle.
- Added safer Mermaid error UI state.
- Added PJAX boot debounce.
- Added GitHub Actions validation workflow.
- Added privacy audit script.

### Changed

- Markmap runtime now defaults to local assets.
- Markmap CDN dependencies are version-pinned.
- Prism autoloader path setup is now inline.
- Sakurairo Prism assets are both dequeued and deregistered.
- Version bumped from 1.0.26 to 1.1.0.

### Security

- GitHub token authentication is opt-in via `BAC_ENABLE_GITHUB_TOKEN`.
- Removed default environment token reads for public repository updates.

### Fixed

- Mermaid shortcode now respects plugin settings.
- Mermaid and Markmap code block regex supports single and double quoted class attributes.
- Mermaid runtime load failures no longer break the page.

---

## 1.0.26

### Fixed

- Improved Mermaid render error handling and boot debounce.
- Prevented duplicate Mermaid re-rendering.

## 1.0.25

### Changed

- Markmap Adapter support with `language-markmap` code blocks and `[markmap]` shortcode.
- Bumped Prism components to latest.
- Refined Arcaea Dark theme.

## 1.0.24

### Added

- Local Markmap runtime support (local assets preferred over CDN).
- CI workflow to sync Markmap vendor assets.

### Changed

- Markmap runtime default changed from CDN to local.

## 1.0.23

### Added

- Initial Markmap mindmap support.

### Fixed

- Plugin update checker authentication hardening.

## 1.0.19

### Added

- Theme system support.
- Arcaea Light theme placeholder.

## 1.0.0

### Added

- Initial release.
- Prism.js with Arcaea Dark theme.
- Mermaid diagram support.
- MathJax LaTeX support.
- Medium-zoom image viewer.
- Sakurairo theme compatibility patches.
- Plugin Update Checker integration.
