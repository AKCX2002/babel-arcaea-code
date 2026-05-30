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

  /* ── 规范化 SVG：裁剪 viewBox 留白，设置响应式尺寸策略 ──
   *
   * 策略：
   *   1. viewBox 紧贴内容裁剪（去除 Mermaid 多余留白）
   *   2. SVG 自身 max-width:100% 确保窄屏自动缩放
   *   3. 不设固定 px 宽高 → 自然宽高比由 viewBox 维持
   *   4. 高图由父容器 .arcaea-mermaid-box 的 max-height + overflow 控制
   */
  function normalizeMermaidSvg(el) {
    const svg = el.querySelector('svg');
    if (!svg) return;

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.style.removeProperty('width');
    svg.style.removeProperty('height');

    /* 精确裁剪 viewBox：
     * 分别取 g.clusters / g.nodes / g.edgeLabels 的 bbox 合并，
     * 显式跳过 g.edgePaths（其 bbox 含空 path 虚高，width≈0 但 height 极大）。
     * 只计入有实际内容的组（width > 2 && height > 2）。 */
    try {
      const contentGroups = [
        svg.querySelector('g.clusters'),
        svg.querySelector('g.nodes'),
        svg.querySelector('g.edgeLabels'),
      ].filter(Boolean);

      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      let found = false;

      for (const g of contentGroups) {
        try {
          const bbox = g.getBBox();
          if (bbox.width > 2 && bbox.height > 2) {
            if (bbox.x < minX) minX = bbox.x;
            if (bbox.y < minY) minY = bbox.y;
            const r = bbox.x + bbox.width;
            const b = bbox.y + bbox.height;
            if (r > maxX) maxX = r;
            if (b > maxY) maxY = b;
            found = true;
          }
        } catch (e) { /* skip */ }
      }
        svg.style.maxWidth = '100%';
        svg.style.height = 'auto';
        return;
      }
    } catch (e) {
      console.warn(LOG, 'Mermaid SVG bbox crop skipped:', e);
    }

    /* fallback */
    const viewBox = svg.getAttribute('viewBox');
    if (!viewBox) {
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
        padding: 6,
        nodeSpacing: 10,
        rankSpacing: 16,
        subGraphMargin: 10
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
        /* 对齐 Arcaea 设计 Token 色彩体系 */
        primaryColor: '#0f182a',
        primaryTextColor: '#eef4ff',
        primaryBorderColor: '#a0dcff',
        lineColor: '#a0dcff',
        secondaryColor: '#1a2540',
        tertiaryColor: '#0b1426',
        textColor: '#eef4ff',
        mainBkg: '#0f182a',
        secondBkg: '#1a2540',
        nodeBorder: '#a0dcff',
        clusterBkg: 'rgba(15,24,42,0.55)',
        clusterBorder: '#8ab0ff',
        edgeLabelBackground: '#0f182a',
        titleColor: '#eef4ff',
        labelTextColor: '#eef4ff',
        actorBkg: '#0f182a',
        actorBorder: '#a0dcff',
        actorTextColor: '#eef4ff',
        actorLineColor: '#a0dcff',
        signalColor: '#eef4ff',
        signalTextColor: '#eef4ff',
        noteBkgColor: '#111d33',
        noteTextColor: '#eef4ff',
        noteBorderColor: '#a0dcff',
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

  /* ── 统一启动策略 ──
   * 只在 DOMContentLoaded 执行一次完整启动。
   * PJAX 事件只触发 Prism 重扫 + Zoom 重绑（不再重复渲染 Mermaid），
   * 避免重复的 DOM 查询和 Mermaid 初始化。
   */
  document.addEventListener('DOMContentLoaded', () => scheduleBoot(document));

  /* PJAX 导航后：仅重扫 Prism 和 zoom，不重跑 Mermaid */
  document.addEventListener('pjax:complete', () => {
    const scope = document;
    preparePrism(scope);
    initZoom(scope);
  });
  document.addEventListener('pjax:end', () => {
    const scope = document;
    preparePrism(scope);
    initZoom(scope);
  });
})();