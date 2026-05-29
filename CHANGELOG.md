# Changelog

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
