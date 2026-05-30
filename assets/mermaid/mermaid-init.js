(function () {
  'use strict';

  var LOG = '[Babel Arcaea Code]';
  var config = window.BAC_Config || {};
  var bootTimer = null;

  function asBool(value, fallback) {
    if (typeof value === 'boolean') return value;
    if (value === 1 || value === '1') return true;
    if (value === 0 || value === '0') return false;
    return fallback;
  }

  /* ════════════════════════════════════════════
   * Prism: PJAX-safe highlighting + Sakurairo <pre> fix
   * ════════════════════════════════════════════ */

  function preparePrism(root) {
    if (!asBool(config.prismEnabled, true)) return;
    var scope = root && root.querySelectorAll ? root : document;
    var lineNumbersEnabled = asBool(config.lineNumbers, true);

    function normalizeBarePre(pre) {
      if (
        pre.querySelector('code') ||
        pre.closest('.arcaea-mermaid-box') ||
        pre.closest('.arcaea-markmap-box') ||
        pre.classList.contains('arcaea-markmap-source')
      ) return null;

      var langClass = 'language-text';
      var cls = pre.className || '';
      var langMatch = cls.match(/(?:^|\s)(?:language-|lang-)([a-z0-9_+#.-]+)/i);
      if (langMatch) { langClass = 'language-' + langMatch[1].toLowerCase(); }

      var code = document.createElement('code');
      code.className = langClass;
      while (pre.firstChild) { code.appendChild(pre.firstChild); }
      pre.appendChild(code);
      return code;
    }

    scope.querySelectorAll('pre:not([data-bac-prism-normalized="1"])').forEach(function (pre) {
      pre.dataset.bacPrismNormalized = '1';
      normalizeBarePre(pre);
    });

    scope.querySelectorAll('pre code:not([data-bac-prism-ready="1"])').forEach(function (code) {
      var pre = code.closest('pre');
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

  /* ════════════════════════════════════════════
   * Mermaid: dynamic ESM import + Arcaea dark theme
   * ════════════════════════════════════════════ */

  async function loadMermaid() {
    if (window.mermaid) return window.mermaid;
    var url = (window.BAC_Mermaid && window.BAC_Mermaid.mermaidUrl)
      || '/wp-content/plugins/babel-arcaea-code/assets/mermaid/mermaid.esm.min.mjs';
    var mod = await import(url);
    window.mermaid = mod.default;
    return window.mermaid;
  }

  function normalizeMermaidSvg(el) {
    var svg = el.querySelector('svg');
    if (!svg) return;
    /* Remove fixed px dimensions; let viewBox + CSS control sizing.
     * max-width:100% + height:auto for responsive behaviour.
     * .arcaea-mermaid-box handles max-height + overflow for tall diagrams. */
    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.style.removeProperty('width');
    svg.style.removeProperty('height');
    svg.style.maxWidth = '100%';
    svg.style.height = 'auto';

    /* ── Crop viewBox to tight content bounds ──
     * svg.getBBox() includes invisible edgePaths → huge whitespace.
     * Instead, compute the union bounding box of all visible .node and
     * .cluster rect elements (the actual diagram content). */
    try {
      var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      var elems = svg.querySelectorAll('.node, .cluster');
      var found = false;

      elems.forEach(function (el) {
        try {
          var b = el.getBBox();
          if (b && b.width > 0 && b.height > 0) {
            found = true;
            if (b.x < minX) minX = b.x;
            if (b.y < minY) minY = b.y;
            if (b.x + b.width  > maxX) maxX = b.x + b.width;
            if (b.y + b.height > maxY) maxY = b.y + b.height;
          }
        } catch (_) {}
      });

      if (found) {
        var pad = 16;
        svg.setAttribute('viewBox',
          (minX - pad) + ' ' + (minY - pad) + ' ' +
          (maxX - minX + 2 * pad) + ' ' + (maxY - minY + 2 * pad));
      }
    } catch (_) { /* skip */ }
  }

  /* ── Fullscreen overlay ── */

  function createOverlay() {
    var overlay = document.getElementById('arcaea-mermaid-overlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'arcaea-mermaid-overlay';
    overlay.className = 'arcaea-mermaid-overlay';

    var content = document.createElement('div');
    content.className = 'arcaea-mermaid-overlay-content';
    overlay.appendChild(content);

    var close = document.createElement('button');
    close.className = 'arcaea-mermaid-overlay-close';
    close.innerHTML = '\u2715';
    close.setAttribute('aria-label', '关闭全屏');
    close.addEventListener('click', function () {
      overlay.classList.remove('active');
      content.innerHTML = '';
    });
    overlay.appendChild(close);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        overlay.classList.remove('active');
        content.innerHTML = '';
      }
    });

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
    var overlay = createOverlay();
    var content = overlay.querySelector('.arcaea-mermaid-overlay-content');
    var clone = svg.cloneNode(true);
    clone.removeAttribute('width');
    clone.removeAttribute('height');
    clone.style.maxWidth = '100%';
    clone.style.height = 'auto';
    content.innerHTML = '';
    content.appendChild(clone);
    overlay.classList.add('active');
  }

  function addFullscreenButton(box, svg) {
    if (box.querySelector('.arcaea-mermaid-fullscreen-btn')) return;
    var btn = document.createElement('button');
    btn.className = 'arcaea-mermaid-fullscreen-btn';
    btn.innerHTML = '\u26F6';
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
    var box = el.closest('.arcaea-mermaid-box') || el;
    box.classList.add('arcaea-mermaid-error');
    if (!box.querySelector('.arcaea-mermaid-error-message')) {
      var msg = document.createElement('div');
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
    var scope = root && root.querySelectorAll ? root : document;
    var diagrams = scope.querySelectorAll(
      '.mermaid.arcaea-mermaid-diagram:not([data-arcaea-rendered="1"]):not([data-bac-mermaid-rendering="1"]):not([data-bac-mermaid-error="1"])'
    );
    if (!diagrams.length) return;

    diagrams.forEach(function (el) { el.dataset.bacMermaidRendering = '1'; });

    var mermaid;
    try { mermaid = await loadMermaid(); }
    catch (e) {
      diagrams.forEach(function (el) { markMermaidError(el, e); });
      console.warn(LOG, 'Mermaid runtime load failed:', e);
      return;
    }

    mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'strict',
      theme: 'base',
      flowchart: {
        htmlLabels: false, useMaxWidth: true, curve: 'basis',
        padding: 6, nodeSpacing: 10, rankSpacing: 16, subGraphMargin: 10
      },
      sequence: {
        useMaxWidth: true, mirrorActors: false, rightAngles: false,
        diagramMarginX: 16, diagramMarginY: 16
      },
      themeVariables: {
        darkMode: true,
        background: 'transparent',
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
      diagrams.forEach(function (el) {
        var box = el.closest('.arcaea-mermaid-box');
        var svg = el.querySelector('svg');
        if (!svg) { markMermaidError(el, new Error('Mermaid did not produce SVG.')); return; }
        el.dataset.arcaeaRendered = '1';
        delete el.dataset.bacMermaidRendering;
        normalizeMermaidSvg(el);
        if (box) addFullscreenButton(box, svg);
      });
      console.log(LOG, 'Mermaid rendered:', diagrams.length);
    } catch (e) {
      diagrams.forEach(function (el) { markMermaidError(el, e); });
      console.warn(LOG, 'Mermaid render skipped:', e);
    }
  }

  /* ════════════════════════════════════════════
   * Image zoom (medium-zoom)
   * ════════════════════════════════════════════ */

  function initZoom(root) {
    if (!window.mediumZoom) return;
    var scope = root && root.querySelectorAll ? root : document;
    try {
      scope.querySelectorAll('.entry-content img, .post-content img, .arcaea-mermaid-box img')
        .forEach(function (img) {
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

  /* ════════════════════════════════════════════
   * Boot sequence
   * ════════════════════════════════════════════ */

  async function boot(root) {
    var scope = root && root.querySelectorAll ? root : document;
    preparePrism(scope);
    await renderMermaid(scope);
    initZoom(scope);
  }

  function scheduleBoot(root) {
    window.clearTimeout(bootTimer);
    bootTimer = window.setTimeout(function () {
      boot(root || document).catch(function (e) { console.warn(LOG, e); });
    }, 80);
  }

  /* ── Startup: DOMContentLoaded (full boot) ── */
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    scheduleBoot(document);
  } else {
    document.addEventListener('DOMContentLoaded', function () { scheduleBoot(document); });
  }

  /* ── PJAX: only re-scan Prism + zoom (skip Mermaid re-init) ── */
  document.addEventListener('pjax:complete', function () {
    preparePrism(document);
    initZoom(document);
  });
  document.addEventListener('pjax:end', function () {
    preparePrism(document);
    initZoom(document);
  });

})();
