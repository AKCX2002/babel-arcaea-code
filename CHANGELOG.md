# Changelog

## 1.6.1

### Changed

- 前台 Mermaid 渲染现在会在 PJAX 完成后重新完整扫描，兼容 Sakurairo 异步切页场景。
- `MerPress` 输出的裸 `pre.mermaid` 块会自动包裹进 BAC 的 Arcaea 玻璃容器，前台样式与全屏行为统一。
- `Mermaid` / `Markmap` 前台渲染前增加源文本清洗：自动解码 HTML entities、去除零宽字符与 NBSP，并回退常见弯引号。

### Fixed

- 修复 `MerPress` / `WP Githuber MD` / Sakurairo 组合下，前台图表在 PJAX 页面中不重渲染的问题。
- 改善复制或编辑器转码后 `&lt;`、`&gt;`、`&amp;`、弯引号等特殊字符导致的 Mermaid/Markmap 解析失败。

## 1.4.4

### Fixed

- **Release ZIP missing `includes/` directory**: The `Build zip` step in `release.yml` only copied `babel-arcaea-code.php assets lib` but missed the entire `includes/` directory containing all module files (`options.php`, `assets.php`, `admin.php`, `renderer-mermaid.php`, `renderer-markmap.php`, `compat-sakurairo.php`, `health.php`). After the v1.4 modular refactoring, none of the plugin functions were available — `bac_options()` undefined, frontend enqueue hooks missing, admin page inaccessible. Added `includes bin` to the zip command.
- **Settings link broken**: Plugin action link "设置" pointed to `options-general.php?page=babel-arcaea-code` which no longer exists after the admin page was migrated to a standalone top-level menu. Fixed to point to `admin.php?page=bac-panel`.
- **WAF 403 on admin page**: Server WAF/mod_security was blocking all admin URLs containing `babel-arcaea-code`. After discovering that even the shortened `bac` slug was problematic (possible slug conflict), changed to unique slugs: top-level `bac-panel`, Settings submenu `bac-options`. Also fixed `admin_enqueue_scripts` hook to check the new slugs instead of the old `babel-arcaea-code`.

## 1.4.3

### Fixed

- **JS dependency chain**: `bac-mermaid-init` now declares explicit dependencies on `bac-prism-core` and `bac-medium-zoom` via `wp_script_is('...', 'registered')` guard — fixes script loading order where Prism/medium-zoom could load after the init script.
- **Removed broken `script_loader_tag` filter**: Previous dependency injection fired too late (post dependency resolution) and was silently ineffective.
- **Safe asset enqueue helpers**: New `bac_enqueue_style_asset()` and `bac_enqueue_script_asset()` verify file existence before enqueuing, with `WP_DEBUG` error logging for missing files. All enqueue calls migrated to these helpers.
- **Admin page resilience**: Added `add_menu_page` as standalone top-level entry (avoids hosts/WAFs that 403 on `options-general.php`), secondary `babel-arcaea-code-settings` fallback slug, and `function_exists('bac_health_check_table')` guard.
- **Health check defense**: Added `function_exists` guards for `bac_asset_url`, `bac_find_node`, `bac_markmap_cache_dir`; "missing" status now correctly shows red; node.js probe caches `exec()` availability.
- **Sakurairo Prism handle coverage**: Expanded disable handle list to include `highlight-style`, `highlightjs-style`, `sakurairo-prism`, `sakura-prism` variants.
- **Mermaid init JS**: Added `prismEnabled`/`mermaidEnabled` config guards; skip Prism scanning inside `.arcaea-markmap-box`/`.arcaea-markmap-source`; null-check SVG before adding fullscreen button.

### Changed

- **BAC_Config localized object** now includes `prismEnabled` and `mermaidEnabled` booleans alongside existing `lineNumbers`.
- **Enqueue order**: `bac-medium-zoom` now registered before `bac-mermaid-init` in the main action for stable dependency ordering.
- **`bac_find_node()`**: Short-circuits when `exec()` is in `disable_functions`, falls back to `is_executable()` for absolute paths.

## 1.4.0

### Added

- **Modular directory structure**: Monolithic `babel-arcaea-code.php` split into dedicated modules under `includes/`:
  - `includes/options.php` — Defaults, option getter, sanitize callback, Settings API registration
  - `includes/assets.php` — All asset enqueuing (Prism CSS/JS, Mermaid, Markmap, medium-zoom, MathJax)
  - `includes/renderer-mermaid.php` — Mermaid `the_content` filter and `[mermaid]` shortcode
  - `includes/renderer-markmap.php` — Markmap `the_content` filter, `[markmap]` shortcode, CLI render engine, `save_post` pre-render hook, utility functions (`bac_find_node()`, `bac_sanitize_svg()`)
  - `includes/compat-sakurairo.php` — Sakurairo Prism disable, APlayer safe patch, LightGallery warning suppression
  - `includes/health.php` — `bac_health_check()` and `bac_health_check_table()` for admin dashboard
  - `includes/admin.php` — Admin settings page with health check integration
- **`assets/css/bac-prism-wrap.css`**: Dedicated CSS file for Prism code block soft-wrapping (`white-space: pre-wrap`, `word-break: break-word`, `overflow-wrap: anywhere`), compatible with Line Numbers plugin.

### Changed

- **Entry file (`babel-arcaea-code.php`) reduced** from ~800 lines to ~80 lines — thin loader that defines constants and loads modules via `require_once`.
- **Admin page** now uses a named callback `bac_admin_page_render()` instead of anonymous closure, making it testable.
- **Health check** extracted to `includes/health.php` with `bac_health_check()` (returns array) and `bac_health_check_table()` (renders HTML).
- **All functions remain available globally** — no namespace change, no breaking API changes.
- Version bumped from 1.3.1 to 1.4.0.

### Fixed

- **Prism code block overflow**: Added automatic soft-wrapping via `pre-wrap` so long one-line code blocks wrap instead of creating horizontal scrollbars. See `assets/css/bac-prism-wrap.css`.

---

## 1.5.0

### Added

- **v1.5.0: `save_post` pre-render hook** (`bac_markmap_prerender_on_save()`): Shifts Markmap SVG rendering from front-end (`the_content`) to post-save time. Extracts all `language-markmap`/`lang-markmap`/`markmap` code blocks and `[markmap]` shortcodes, deduplicates by content hash, and pre-renders them as cached SVGs during save.
- **Graceful degradation**: If `save_post` cache is missing (e.g. post was saved before upgrade), `the_content` filter falls back to on-demand pre-render, then to client-side rendering if Node.js is unavailable.

### Changed

- **Markmap pre-render pipeline upgraded**:
  1. `save_post` → extract blocks → `bac_markmap_render_svg()` → cache
  2. `the_content` → read cache → served as `<div class="arcaea-markmap-prerendered">`
  3. Cache miss → immediate render → cache → serve
  4. Render failure → client-side `<pre>` + `<svg>` fallback
- **`bac_markmap_render_svg()`**: Now used by both `save_post` and `the_content` paths (previously only `the_content`).

---

## 1.3.1

### Added

- **`bac_find_node()`**: Robust Node.js binary locator probing common paths (`/usr/bin/node`, `/usr/local/bin/node`, `/opt/homebrew/bin/node`, `BAC_NODE_BIN` env).
- **`bac_sanitize_svg()`**: Lightweight SVG sanitizer that strips `<script>` blocks, `on*` event handlers, and `javascript:` URIs to prevent XSS vectors in pre-rendered markmap SVGs.
- **`bac_asset_url()`**: Safe asset URL resolver with file existence check — returns `null` if file is missing, enabling graceful degradation.
- **`bac_disable_handles()`**: Bulk handle dequeue/deregister helper that checks whether handles are actually enqueued/registered before acting.
- **Health check section in admin page**: Displays status of Prism core, Mermaid ESM, Markmap vendor, Node.js binary, render script, cache directory writability, `proc_open` availability, and Sakurairo theme detection.

### Changed

- **Mermaid code block regex** now also matches `lang-mermaid` and bare `mermaid` class names (previously only `language-mermaid`).
- **Markmap code block regex** now also matches `lang-markmap` and bare `markmap` class names (previously only `language-markmap`).
- **`bac_markmap_render_svg()`**:
  - Now uses `bac_find_node()` instead of inline fallback logic.
  - Applies `bac_sanitize_svg()` to cached and newly rendered SVGs.
  - Sets stdout/stderr to non-blocking mode.
  - Implements polling read with a 15-second safety timeout.
  - Logs failures to `error_log()` when `WP_DEBUG` is enabled.
  - Added `bac_markmap_render_cmd` filter for custom command wrapping (e.g. `timeout(15)`).
- **Sanitize callback extracted**: Moved anonymous `register_setting` callback to named function `bac_sanitize_options()` for better testability and reuse.
- **Medium-zoom dependency**: Now added to `bac-mermaid-init` deps via `script_loader_tag` filter instead of mutating `$wp_scripts` directly.
- **Sakurairo Prism disable**: Now uses `bac_disable_handles()` helper with debug logging.
- **Admin page descriptions**: Improved APlayer safe patch and LightGallery warning suppression descriptions with clearer risk warnings.
- Version bumped from 1.3.0 to 1.3.1.

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
