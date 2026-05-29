(function () {
  'use strict';

  const LOG = '[Babel Arcaea Code]';
  const config = window.BAC_Config || {};

  function asBool(value, fallback) {
    if (typeof value === 'boolean') return value;
    if (value === 1 || value === '1') return true;
    if (value === 0 || value === '0') return false;
    return fallback;
  }

  /* ── Prism: PJAX-safe highlighting ── */
  function preparePrism(root) {
    const lineNumbersEnabled = asBool(config.lineNumbers, true);

    root.querySelectorAll('pre code:not([data-bac-prism-ready="1"])').forEach((code) => {
      const pre = code.closest('pre');
      if (!pre || pre.closest('.arcaea-mermaid-box')) return;

      if (lineNumbersEnabled && !pre.classList.contains('line-numbers')) {
        pre.classList.add('line-numbers');
      }

      if (!/\blanguage-/.test(code.className)) {
        code.classList.add('language-text');
      }

      code.dataset.bacPrismReady = '1';

      if (window.Prism && typeof Prism.highlightElement === 'function') {
        Prism.highlightElement(code);
      }
    });
  }

  /* ── Mermaid ── */
  async function loadMermaid() {
    if (window.mermaid) return window.mermaid;

    const url = (window.BAC_Mermaid && window.BAC_Mermaid.mermaidUrl)
      || '/wp-content/plugins/babel-arcaea-code/assets/mermaid/mermaid.esm.min.mjs';

    const mod = await import(url);
    window.mermaid = mod.default;
    return window.mermaid;
  }

  function getResponsiveMermaidWidth(rawWidth) {
    if (!Number.isFinite(rawWidth) || rawWidth <= 0) return 960;

    if (rawWidth <= 720) {
      return Math.min(Math.round(rawWidth * 1.25), 720);
    }

    return Math.max(960, Math.min(Math.round(rawWidth), 1600));
  }

  function applyMermaidSvgWidth(svg, rawWidth) {
    const readableWidth = getResponsiveMermaidWidth(rawWidth);
    svg.style.setProperty('--bac-mermaid-width', readableWidth + 'px');
    svg.style.width = 'var(--bac-mermaid-width)';
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
        applyMermaidSvgWidth(svg, width);
        return;
      }
    } catch (e) {
      console.warn(LOG, 'Mermaid SVG bbox crop skipped:', e);
    }

    const viewBox = svg.getAttribute('viewBox');
    if (!viewBox) return;

    const parts = viewBox.trim().split(/\s+/).map(Number);
    const viewBoxWidth = parts[2];
    applyMermaidSvgWidth(svg, viewBoxWidth);
  }

  async function renderMermaid(root) {
    const diagrams = root.querySelectorAll(
      '.mermaid.arcaea-mermaid-diagram:not([data-arcaea-rendered="1"]):not([data-bac-mermaid-rendering="1"])'
    );
    if (!diagrams.length) return;

    diagrams.forEach((el) => {
      el.dataset.bacMermaidRendering = '1';
    });

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
        darkMode: true,
        background: 'transparent',
        primaryColor: '#202a40',
        primaryTextColor: '#f2f8ff',
        primaryBorderColor: '#9fd2ff',
        lineColor: '#9fd2ff',
        secondaryColor: '#26334d',
        tertiaryColor: '#121827',
        textColor: '#f2f8ff',
        mainBkg: '#202a40',
        secondBkg: '#26334d',
        nodeBorder: '#9fd2ff',
        clusterBkg: 'rgba(32,42,64,0.92)',
        clusterBorder: '#8dc7ff',
        edgeLabelBackground: '#151d2c',
        titleColor: '#f2f8ff',
        labelTextColor: '#f2f8ff',
        actorBkg: '#202a40',
        actorBorder: '#9fd2ff',
        actorTextColor: '#f2f8ff',
        actorLineColor: '#8dc7ff',
        signalColor: '#f2f8ff',
        signalTextColor: '#f2f8ff',
        noteBkgColor: '#1c2638',
        noteTextColor: '#f2f8ff',
        noteBorderColor: '#9fd2ff',
        fontFamily: 'FiraCode Nerd Font, Fira Code, JetBrains Mono, Noto Sans SC, sans-serif',
        fontSize: '15px'
      }
    });

    try {
      await mermaid.run({ nodes: diagrams, suppressErrors: true });
      diagrams.forEach((el) => {
        el.dataset.arcaeaRendered = '1';
        delete el.dataset.bacMermaidRendering;
        normalizeMermaidSvg(el);
      });
      console.log(LOG, 'Mermaid rendered:', diagrams.length);
    } catch (e) {
      diagrams.forEach((el) => {
        delete el.dataset.bacMermaidRendering;
      });
      throw e;
    }
  }

  /* ── Image zoom ── */
  function initZoom(root) {
    if (!window.mediumZoom) return;

    try {
      root.querySelectorAll('.entry-content img, .post-content img, .arcaea-mermaid-box img')
        .forEach((img) => {
          if (img.dataset.bacZoomReady === '1') return;
          mediumZoom(img, {
            margin: 24,
            background: 'rgba(10, 12, 18, 0.86)',
            scrollOffset: 40
          });
          img.dataset.bacZoomReady = '1';
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
