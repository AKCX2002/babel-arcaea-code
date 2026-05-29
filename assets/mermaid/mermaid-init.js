(function () {
  'use strict';
  const LOG = '[Babel Arcaea Code]';

  /* ── Prism: add line-numbers to all code blocks, detect unlabeled ── */
  function preparePrism(root) {
    root.querySelectorAll('pre code').forEach((code) => {
      const pre = code.closest('pre');
      if (!pre) return;
      if (!pre.classList.contains('line-numbers') && !pre.closest('.arcaea-mermaid-box')) {
        pre.classList.add('line-numbers');
      }
      if (!code.className.includes('language-')) {
        code.classList.add('language-text');
      }
    });
    if (window.Prism) Prism.highlightAll();
  }

  /* ── Mermaid ── */
  async function loadMermaid() {
    if (window.mermaid) return window.mermaid;
    var url = (window.BAC_Mermaid && window.BAC_Mermaid.mermaidUrl)
      || '/wp-content/plugins/babel-arcaea-code/assets/mermaid/mermaid.esm.min.mjs';
    const mod = await import(url);
    window.mermaid = mod.default;
    return window.mermaid;
  }

  function normalizeMermaidSvg(el) {
    const svg = el.querySelector('svg');
    if (!svg) return;

    svg.removeAttribute('width');
    svg.removeAttribute('height');

    svg.style.width = 'auto';
    svg.style.height = 'auto';
    svg.style.maxWidth = 'none';
    svg.style.maxHeight = 'none';

    try {
      const root = svg.querySelector('g.root') || svg.querySelector('g') || svg;
      const box = root.getBBox();

      if (
        Number.isFinite(box.x) &&
        Number.isFinite(box.y) &&
        Number.isFinite(box.width) &&
        Number.isFinite(box.height) &&
        box.width > 0 &&
        box.height > 0
      ) {
        const pad = 32;
        const x = box.x - pad;
        const y = box.y - pad;
        const width = box.width + pad * 2;
        const height = box.height + pad * 2;

        svg.setAttribute('viewBox', `${x} ${y} ${width} ${height}`);

        const readableWidth = Math.max(960, Math.min(width, 1600));
        svg.style.width = readableWidth + 'px';
        return;
      }
    } catch (e) {
      console.warn(LOG, 'Mermaid SVG bbox crop skipped:', e);
    }

    const viewBox = svg.getAttribute('viewBox');
    if (!viewBox) return;

    const parts = viewBox.trim().split(/\s+/).map(Number);
    const viewBoxWidth = parts[2];

    if (Number.isFinite(viewBoxWidth)) {
      const readableWidth = Math.max(960, Math.min(viewBoxWidth, 1600));
      svg.style.width = readableWidth + 'px';
    }
  }

  async function renderMermaid(root) {
    const diagrams = root.querySelectorAll(
      '.mermaid.arcaea-mermaid-diagram:not([data-arcaea-rendered="1"])'
    );
    if (!diagrams.length) return;

    const mermaid = await loadMermaid();
    mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'strict',
      theme: 'base',
      flowchart: {
        htmlLabels: false,
        useMaxWidth: false,
        curve: 'basis',
        padding: 8,
        nodeSpacing: 24,
        rankSpacing: 32
      },
      sequence: {
        useMaxWidth: false,
        mirrorActors: false,
        rightAngles: false,
        diagramMarginX: 16,
        diagramMarginY: 16
      },
      themeVariables: {
        darkMode: true, background: 'transparent',
        primaryColor: '#202a40', primaryTextColor: '#f2f8ff', primaryBorderColor: '#9fd2ff',
        lineColor: '#9fd2ff', secondaryColor: '#26334d', tertiaryColor: '#121827',
        textColor: '#f2f8ff', mainBkg: '#202a40', secondBkg: '#26334d',
        nodeBorder: '#9fd2ff', clusterBkg: 'rgba(32,42,64,0.92)', clusterBorder: '#8dc7ff',
        edgeLabelBackground: '#151d2c', titleColor: '#f2f8ff', labelTextColor: '#f2f8ff',
        actorBkg: '#202a40', actorBorder: '#9fd2ff', actorTextColor: '#f2f8ff',
        actorLineColor: '#8dc7ff', signalColor: '#f2f8ff', signalTextColor: '#f2f8ff',
        noteBkgColor: '#1c2638', noteTextColor: '#f2f8ff', noteBorderColor: '#9fd2ff',
        fontFamily: 'FiraCode Nerd Font, Fira Code, JetBrains Mono, Noto Sans SC, sans-serif',
        fontSize: '15px'
      }
    });
    await mermaid.run({ nodes: diagrams, suppressErrors: true });

    diagrams.forEach((el) => {
      el.dataset.arcaeaRendered = '1';
      normalizeMermaidSvg(el);
    });
    console.log(LOG, 'Mermaid rendered:', diagrams.length);
  }

  /* ── Image zoom ── */
  function initZoom(root) {
    if (!window.mediumZoom) return;
    try {
      mediumZoom(root.querySelectorAll('.entry-content img, .post-content img, .arcaea-mermaid-box img'), {
        margin: 24,
        background: 'rgba(10, 12, 18, 0.86)',
        scrollOffset: 40
      });
    } catch (e) { /* skip */ }
  }

  /* ── Full init ── */
  async function boot(root) {
    preparePrism(root);
    await renderMermaid(root);
    initZoom(root);
  }

  document.addEventListener('DOMContentLoaded', () => boot(document).catch(e => console.error(LOG, e)));
  window.addEventListener('load', () => boot(document).catch(e => console.error(LOG, e)));
  document.addEventListener('pjax:complete', () => boot(document).catch(e => console.error(LOG, e)));
  document.addEventListener('pjax:end', () => boot(document).catch(e => console.error(LOG, e)));
})();