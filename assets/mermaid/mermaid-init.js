(function () {
  'use strict';

  const LOG = '[Babel Arcaea Code]';
  const config = window.BAC_Config || {};
  let bootTimer = null;

  function asBool(value, fallback) {
    if (typeof value === 'boolean') return value;
    if (value === 1 || value === '1') return true;
    if (value === 0 || value === '0') return false;
    return fallback;
  }

  /* ── Prism: PJAX-safe highlighting ── */
  function preparePrism(root) {
    if (!asBool(config.prismEnabled, true)) return;

    const scope = root && root.querySelectorAll ? root : document;
    const lineNumbersEnabled = asBool(config.lineNumbers, true);

    scope.querySelectorAll('pre code:not([data-bac-prism-ready="1"])').forEach((code) => {
      const pre = code.closest('pre');
      if (
        !pre ||
        pre.closest('.arcaea-mermaid-box') ||
        pre.closest('.arcaea-markmap-box') ||
        pre.classList.contains('arcaea-markmap-source')
      ) return;

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

  /* ── 规范化 SVG：移除固定宽高，设 viewBox 确保 CSS 响应式生效 ── */
  function normalizeMermaidSvg(el) {
    const svg = el.querySelector('svg');
    if (!svg) return;

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.style.removeProperty('width');
    svg.style.removeProperty('height');
    svg.style.removeProperty('max-width');
    svg.style.removeProperty('max-height');

    /* 用 viewBox 精确裁剪，去掉 Mermaid 自带的额外留白 */
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
        svg.setAttribute('viewBox',
          `${box.x - pad} ${box.y - pad} ${box.width + pad * 2} ${box.height + pad * 2}`
        );
        return;
      }
    } catch (e) {
      console.warn(LOG, 'Mermaid SVG bbox crop skipped:', e);
    }

    /* fallback: 保留已有 viewBox */
    const viewBox = svg.getAttribute('viewBox');
    if (!viewBox) {
      /* 极端 fallback：给一个默认 viewBox */
      svg.setAttribute('viewBox', '0 0 960 600');
    }
  }

  /* ── 全屏预览 ── */
  function createOverlay() {
    /* 复用已创建的 overlay */
    let overlay = document.getElementById('arcaea-mermaid-overlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'arcaea-mermaid-overlay';
    overlay.className = 'arcaea-mermaid-overlay';

    const content = document.createElement('div');
    content.className = 'arcaea-mermaid-overlay-content';
    overlay.appendChild(content);

    const close = document.createElement('button');
    close.className = 'arcaea-mermaid-overlay-close';
    close.innerHTML = '✕';
    close.setAttribute('aria-label', '关闭全屏');
    close.addEventListener('click', function () {
      overlay.classList.remove('active');
      content.innerHTML = '';
    });
    overlay.appendChild(close);

    /* 点击背景关闭 */
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        overlay.classList.remove('active');
        content.innerHTML = '';
      }
    });

    /* ESC 关闭 */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('active')) {
        overlay.classList.remove('active');
        content.innerHTML = '';
      }
    });

    document.body.appendChild(overlay);
    return overlay;
  }

  function openFullscreen(svg) {
    const overlay = createOverlay();
    const content = overlay.querySelector('.arcaea-mermaid-overlay-content');

    /* 克隆 SVG，保留所有样式 */
    const clone = svg.cloneNode(true);
    content.innerHTML = '';
    content.appendChild(clone);

    overlay.classList.add('active');
  }

  function addFullscreenButton(box, svg) {
    /* 已有按钮则不重复添加 */
    if (box.querySelector('.arcaea-mermaid-fullscreen-btn')) return;

    const btn = document.createElement('button');
    btn.className = 'arcaea-mermaid-fullscreen-btn';
    btn.innerHTML = '⛶';
    btn.setAttribute('aria-label', '查看大图');
    btn.setAttribute('title', '查看大图');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      openFullscreen(svg);
    });

    box.appendChild(btn);
  }

  function markMermaidError(el, error) {
    delete el.dataset.bacMermaidRendering;
    el.dataset.bacMermaidError = '1';

    const box = el.closest('.arcaea-mermaid-box') || el;
    box.classList.add('arcaea-mermaid-error');

    if (!box.querySelector('.arcaea-mermaid-error-message')) {
      const msg = document.createElement('div');
      msg.className = 'arcaea-mermaid-error-message';
      msg.textContent = 'Mermaid 渲染失败，请检查图表语法。';
      box.appendChild(msg);
    }

    if (error) {
      box.dataset.bacMermaidErrorMessage = String(error && error.message ? error.message : error).slice(0, 300);
    }
  }

  async function renderMermaid(root) {
    if (!asBool(config.mermaidEnabled, true)) return;

    const scope = root && root.querySelectorAll ? root : document;
    const diagrams = scope.querySelectorAll(
      '.mermaid.arcaea-mermaid-diagram:not([data-arcaea-rendered="1"]):not([data-bac-mermaid-rendering="1"]):not([data-bac-mermaid-error="1"])'
    );
    if (!diagrams.length) return;

    diagrams.forEach((el) => {
      el.dataset.bacMermaidRendering = '1';
    });

    let mermaid;
    try {
      mermaid = await loadMermaid();
    } catch (e) {
      diagrams.forEach((el) => markMermaidError(el, e));
      console.warn(LOG, 'Mermaid runtime load failed:', e);
      return;
    }

    /* ── Mermaid 初始化：Arcaea 暗色主题 ── */
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
        const box = el.closest('.arcaea-mermaid-box');
        const svg = el.querySelector('svg');

        if (!svg) {
          markMermaidError(el, new Error('Mermaid did not produce SVG.'));
          return;
        }

        el.dataset.arcaeaRendered = '1';
        delete el.dataset.bacMermaidRendering;
        normalizeMermaidSvg(el);

        if (box) {
          addFullscreenButton(box, svg);
        }
      });
      console.log(LOG, 'Mermaid rendered:', diagrams.length);
    } catch (e) {
      diagrams.forEach((el) => markMermaidError(el, e));
      console.warn(LOG, 'Mermaid render skipped:', e);
    }
  }

  /* ── Image zoom ── */
  function initZoom(root) {
    if (!window.mediumZoom) return;

    const scope = root && root.querySelectorAll ? root : document;

    try {
      scope.querySelectorAll('.entry-content img, .post-content img, .arcaea-mermaid-box img')
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
    const scope = root && root.querySelectorAll ? root : document;
    preparePrism(scope);
    await renderMermaid(scope);
    initZoom(scope);
  }

  function scheduleBoot(root) {
    window.clearTimeout(bootTimer);
    bootTimer = window.setTimeout(() => {
      boot(root || document).catch((e) => console.warn(LOG, e));
    }, 80);
  }

  document.addEventListener('DOMContentLoaded', () => scheduleBoot(document));
  window.addEventListener('load', () => scheduleBoot(document));
  document.addEventListener('pjax:complete', () => scheduleBoot(document));
  document.addEventListener('pjax:end', () => scheduleBoot(document));
})();