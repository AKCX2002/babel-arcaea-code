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

  function decodeHtmlEntities(text) {
    if (!text || text.indexOf('&') === -1) return text || '';
    var textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  }

  function normalizeDiagramSource(text) {
    if (!text) return '';
    return decodeHtmlEntities(String(text))
      .replace(/\r\n?/g, '\n')
      .replace(/[\u200B-\u200D\u2060\uFEFF]/g, '')
      .replace(/\u00A0/g, ' ')
      .replace(/[\u2018\u2019]/g, '\'')
      .replace(/[\u201C\u201D]/g, '"')
      .trim();
  }

  function extractSubgraphAnchors(lines) {
    var anchors = {};
    var stack = [];

    function ensureAnchor(id) {
      if (!anchors[id]) {
        anchors[id] = { first: '', last: '' };
      }
      return anchors[id];
    }

    function recordNode(id) {
      if (!id || !stack.length) return;
      stack.forEach(function (subgraphId) {
        var anchor = ensureAnchor(subgraphId);
        if (!anchor.first) anchor.first = id;
        anchor.last = id;
      });
    }

    lines.forEach(function (line) {
      var subgraphMatch = line.match(/^\s*subgraph\s+([A-Za-z][\w:./-]*)(?=(?:\s|[\[(\{"']))/);
      if (subgraphMatch) {
        stack.push(subgraphMatch[1]);
        ensureAnchor(subgraphMatch[1]);
        return;
      }

      if (/^\s*end\s*$/.test(line)) {
        if (stack.length) stack.pop();
        return;
      }

      if (!stack.length) return;
      if (/^\s*(?:style|classDef|class|click|linkStyle|accTitle|accDescr|section|direction)\b/.test(line)) {
        return;
      }

      var nodePattern = /\b([A-Za-z][\w:./-]*)\s*(?=(?:\[[^\]]*\]|\([^)]+\)|\{[^}]+\}|\"[^\"]+\"|'[^']+'))/g;
      var match;
      while ((match = nodePattern.exec(line))) {
        recordNode(match[1]);
      }
    });

    return anchors;
  }

  function rewriteSubgraphEdgeLine(line, anchors) {
    var connectorPattern = /(\s*(?:<[-.=]+>|[-.=]+(?:>|x|o))(?:\|[^|\n]*\|)?\s*)/g;
    var parts = line.split(connectorPattern).filter(function (part) { return part !== ''; });
    if (parts.length < 3 || parts.length % 2 === 0) return null;

    var indentMatch = parts[0].match(/^\s*/);
    var indent = indentMatch ? indentMatch[0] : '';
    var rewritten = [];

    function resolveToken(raw, role) {
      var trimmed = raw.trim();
      var anchor = anchors[trimmed];
      if (!anchor) return trimmed;
      if (role === 'source') return anchor.last || anchor.first || trimmed;
      return anchor.first || anchor.last || trimmed;
    }

    for (var i = 0; i < parts.length - 2; i += 2) {
      var leftRaw = parts[i];
      var connector = parts[i + 1].trim();
      var rightRaw = parts[i + 2];
      var leftTrimmed = leftRaw.trim();
      var rightTrimmed = rightRaw.trim();
      var left = resolveToken(leftRaw, 'source');
      var right = resolveToken(rightRaw, 'target');

      if (left === leftTrimmed && right === rightTrimmed) continue;
      rewritten.push(indent + left + ' ' + connector + ' ' + right);
    }

    return rewritten.length ? rewritten.join('\n') : null;
  }

  function escapeRegex(text) {
    return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function applyMermaidCompatibilityMode(text, mode) {
    if (!text) return text;
    mode = mode === 'off' || mode === 'force' ? mode : 'auto';
    if (mode === 'off') return text;

    var lines = text.split('\n');
    var header = lines.find(function (line) { return line.trim(); }) || '';
    if (!/^\s*(?:flowchart|graph)\b/i.test(header)) return text;

    var anchors = extractSubgraphAnchors(lines);
    var subgraphIds = Object.keys(anchors).filter(function (id) {
      return anchors[id] && anchors[id].first && anchors[id].last;
    });
    if (!subgraphIds.length) return text;

    if (mode === 'auto') {
      var shouldCompat = lines.some(function (line) {
        if (
          !line ||
          /^\s*(?:subgraph|end|style|classDef|class|click|linkStyle|accTitle|accDescr|section|direction)\b/.test(line) ||
          !/(?:<[-.=]+>|[-.=]+(?:>|x|o))/.test(line)
        ) {
          return false;
        }
        return subgraphIds.some(function (id) {
          return new RegExp('(^|[^\\w:./-])' + escapeRegex(id) + '([^\\w:./-]|$)').test(line);
        });
      });
      if (!shouldCompat) return text;
    }

    var changed = false;
    var rewrittenLines = lines.map(function (line) {
      if (
        !line ||
        /^\s*(?:subgraph|end|style|classDef|class|click|linkStyle|accTitle|accDescr|section|direction)\b/.test(line) ||
        !/(?:<[-.=]+>|[-.=]+(?:>|x|o))/.test(line)
      ) {
        return line;
      }

      var hasSubgraphRef = subgraphIds.some(function (id) {
        return new RegExp('(^|[^\\w:./-])' + escapeRegex(id) + '([^\\w:./-]|$)').test(line);
      });
      if (!hasSubgraphRef) return line;

      var rewritten = rewriteSubgraphEdgeLine(line, anchors);
      if (!rewritten) return line;
      changed = true;
      return rewritten;
    });

    if (!changed) return text;
    return rewrittenLines.join('\n');
  }

  function prepareMermaidContainers(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('pre.mermaid:not([data-bac-mermaid-shell="1"])').forEach(function (pre) {
      if (pre.closest('.arcaea-mermaid-box')) {
        pre.dataset.bacMermaidShell = '1';
        return;
      }
      var box = document.createElement('div');
      box.className = 'arcaea-mermaid-box';
      pre.parentNode.insertBefore(box, pre);
      box.appendChild(pre);
      pre.dataset.bacMermaidShell = '1';
    });
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
        pre.classList.contains('arcaea-markmap-source') ||
        pre.classList.contains('mermaid')
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

  function markMermaidError(el, error) {
    delete el.dataset.bacMermaidRendering;
    el.dataset.bacMermaidError = '1';
    var box = el.closest('.arcaea-mermaid-box') || el;
    box.classList.add('arcaea-mermaid-error');
    if (!box.querySelector('.arcaea-mermaid-error-message')) {
      var msg = document.createElement('div');
      msg.className = 'arcaea-mermaid-error-message';
      var errText = 'Mermaid 语法错误';
      if (error && error.message) {
        errText += ': ' + String(error.message).slice(0, 200);
      } else if (error && error.str) {
        errText += ': ' + String(error.str).slice(0, 200);
      }
      msg.textContent = errText;
      box.appendChild(msg);
    }
    if (error && error.message) {
      box.dataset.bacMermaidErrorMessage = String(error.message).slice(0, 300);
    }
  }

  function parseViewBoxWidth(svg) {
    if (!svg) return 0;
    var viewBox = svg.getAttribute('viewBox');
    if (!viewBox) return 0;
    var parts = viewBox.trim().split(/\s+/);
    if (parts.length !== 4) return 0;
    var width = Number(parts[2]);
    return Number.isFinite(width) && width > 0 ? width : 0;
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function applyResponsiveSvgSize(svg, box) {
    if (!svg) return;
    var intrinsicWidth = parseViewBoxWidth(svg);
    var host = box || svg.closest('.arcaea-mermaid-box') || svg.parentElement;
    var hostWidth = host ? Math.max(0, host.clientWidth - 32) : 0;
    var fitsContainer = intrinsicWidth > 0 && hostWidth > 0 && intrinsicWidth <= hostWidth * 1.08;
    var readableScrollThreshold = hostWidth > 0 ? hostWidth * 1.9 : 0;
    var shouldPreferScrollableScale = intrinsicWidth > 0 && hostWidth > 0 && intrinsicWidth > readableScrollThreshold;
    var targetScrollableWidth = hostWidth > 0
      ? clamp(hostWidth * 1.75, hostWidth + 120, hostWidth * 2.4)
      : 0;

    svg.removeAttribute('width');
    svg.removeAttribute('height');
    if (fitsContainer) {
      svg.style.width = intrinsicWidth + 'px';
      svg.style.maxWidth = '100%';
      if (host) host.dataset.bacMermaidScaleMode = 'natural';
    } else if (shouldPreferScrollableScale && targetScrollableWidth > 0) {
      svg.style.width = targetScrollableWidth + 'px';
      svg.style.maxWidth = 'none';
      if (host) host.dataset.bacMermaidScaleMode = 'scroll';
    } else {
      svg.style.width = '100%';
      svg.style.maxWidth = '100%';
      if (host) host.dataset.bacMermaidScaleMode = 'fit';
    }
    svg.style.minWidth = '0';
    svg.style.height = 'auto';
  }

  function ensureFullscreenOverlay() {
    var overlay = document.querySelector('.arcaea-mermaid-overlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'arcaea-mermaid-overlay';
    overlay.innerHTML =
      '<button type="button" class="arcaea-mermaid-overlay-close" aria-label="关闭全屏预览">×</button>' +
      '<div class="arcaea-mermaid-overlay-content" role="dialog" aria-modal="true"></div>';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (event) {
      if (
        event.target === overlay ||
        event.target.closest('.arcaea-mermaid-overlay-close')
      ) {
        closeFullscreenOverlay();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && overlay.classList.contains('active')) {
        closeFullscreenOverlay();
      }
    });

    return overlay;
  }

  function closeFullscreenOverlay() {
    var overlay = document.querySelector('.arcaea-mermaid-overlay');
    if (!overlay) return;
    overlay.classList.remove('active');
    var content = overlay.querySelector('.arcaea-mermaid-overlay-content');
    if (content) content.innerHTML = '';
    document.documentElement.classList.remove('bac-mermaid-overlay-open');
    document.body.classList.remove('bac-mermaid-overlay-open');
  }

  function openMermaidFullscreen(box) {
    if (!box) return;
    var svg = box.querySelector('pre.mermaid > svg');
    if (!svg) return;

    var overlay = ensureFullscreenOverlay();
    var content = overlay.querySelector('.arcaea-mermaid-overlay-content');
    if (!content) return;

    var clone = svg.cloneNode(true);
    clone.style.width = '';
    clone.style.maxWidth = 'none';
    clone.style.minWidth = '0';
    clone.style.height = 'auto';
    content.innerHTML = '';
    content.appendChild(clone);

    overlay.classList.add('active');
    document.documentElement.classList.add('bac-mermaid-overlay-open');
    document.body.classList.add('bac-mermaid-overlay-open');
  }

  function ensureFullscreenTrigger(box) {
    if (!box || box.dataset.bacMermaidFullscreenReady === '1') return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'arcaea-mermaid-fullscreen-btn';
    button.setAttribute('aria-label', '全屏查看 Mermaid 图表');
    button.textContent = '⤢';
    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openMermaidFullscreen(box);
    });
    box.appendChild(button);

    box.addEventListener('click', function (event) {
      if (
        event.target.closest('.arcaea-mermaid-fullscreen-btn') ||
        event.target.closest('.arcaea-mermaid-error-message')
      ) {
        return;
      }
      if (event.target.closest('svg') || event.target.closest('pre.mermaid')) {
        openMermaidFullscreen(box);
      }
    });

    box.dataset.bacMermaidFullscreenReady = '1';
  }

  function refreshMermaidLayouts(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.arcaea-mermaid-box').forEach(function (box) {
      var svg = box.querySelector('pre.mermaid > svg');
      if (!svg) return;
      applyResponsiveSvgSize(svg, box);
    });
  }

  function enhanceRenderedMermaidBoxes(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.arcaea-mermaid-box').forEach(function (box) {
      var svg = box.querySelector('pre.mermaid > svg');
      if (!svg) return;
      applyResponsiveSvgSize(svg, box);
      ensureFullscreenTrigger(box);
    });
  }

  /* ════════════════════════════════════════════
   * Mermaid — MerPress-style: bare mermaid.run()
   *
   * Strategy: let Mermaid own the SVG lifecycle.  We only:
   *   1. Provide Arcaea dark theme via mermaid.initialize()
   *   2. Call mermaid.run({ nodes }) targeting unrendered .mermaid elements
   *   3. Add a fullscreen button to each rendered diagram
   *   4. Crop viewBox to visible content (tighten from computed bboxes)
   * ════════════════════════════════════════════ */

  async function renderMermaid(root) {
    if (!asBool(config.mermaidEnabled, true)) return;
    var scope = root && root.querySelectorAll ? root : document;
    prepareMermaidContainers(scope);
    // Target bare <pre class="mermaid"> elements (MerPress-style output)
    var diagrams = scope.querySelectorAll(
      '.mermaid:not([data-arcaea-rendered="1"]):not([data-bac-mermaid-rendering="1"]):not([data-bac-mermaid-error="1"])'
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
      // MerPress-style: pre-validate with mermaid.parse() so errors
      // are caught before rendering.  This gives the user a clear
      // "syntax error" message instead of silent failure.
      var validDiagrams = [];
      for (var i = 0; i < diagrams.length; i++) {
        try {
          var normalized = normalizeDiagramSource(diagrams[i].textContent);
          if (!normalized) {
            delete diagrams[i].dataset.bacMermaidRendering;
            continue;
          }
          var compatible = applyMermaidCompatibilityMode(normalized, config.mermaidCompatMode);
          diagrams[i].textContent = compatible;
          if (compatible !== normalized) {
            diagrams[i].dataset.bacMermaidCompat = '1';
          } else {
            delete diagrams[i].dataset.bacMermaidCompat;
          }
          await mermaid.parse(compatible);
          validDiagrams.push(diagrams[i]);
        } catch (parseErr) {
          markMermaidError(diagrams[i], parseErr);
        }
      }
      if (!validDiagrams.length) return;

      await mermaid.run({ nodes: validDiagrams, suppressErrors: true });
      validDiagrams.forEach(function (el) {
        var svg = el.querySelector('svg');
        if (!svg) { markMermaidError(el, new Error('Mermaid did not produce SVG.')); return; }
        el.dataset.arcaeaRendered = '1';
        delete el.dataset.bacMermaidRendering;
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        /* ── Crop viewBox to visible content ──
         * Mermaid stateDiagram/flowchart can produce huge viewBox values
         * (2000+px) because invisible edgePaths extend the bounding box.
         * Compute tight viewBox from visible .node, .cluster, .statediagram-*
         * and .note elements — the actual diagram content. */
        try {
          var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
          var found = false;
          svg.querySelectorAll(
            '.node, .cluster, .statediagram-state, .statediagram-cluster, .statediagram-note, .note-cluster'
          ).forEach(function (n) {
            try {
              var b = n.getBBox();
              if (b && b.width > 0 && b.height > 0) {
                found = true;
                if (b.x < minX) minX = b.x;
                if (b.y < minY) minY = b.y;
                if (b.x + b.width > maxX) maxX = b.x + b.width;
                if (b.y + b.height > maxY) maxY = b.y + b.height;
              }
            } catch (_) { }
          });
          if (found) {
            var pad = 12;
            svg.setAttribute('viewBox',
              (minX - pad) + ' ' + (minY - pad) + ' ' +
              (maxX - minX + 2 * pad) + ' ' + (maxY - minY + 2 * pad));
          }
        } catch (_) { }

        var box = el.closest('.arcaea-mermaid-box');
        applyResponsiveSvgSize(svg, box);
        if (box) {
          ensureFullscreenTrigger(box);
          box.dispatchEvent(new CustomEvent('bac:mermaid-rendered', {
            bubbles: true,
            detail: { box: box, svg: svg }
          }));
        }
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
    enhanceRenderedMermaidBoxes(scope);
    initZoom(scope);
  }

  function scheduleBoot(root) {
    window.clearTimeout(bootTimer);
    bootTimer = window.setTimeout(function () {
      boot(root || document).catch(function (e) { console.warn(LOG, e); });
    }, 80);
  }

  window.addEventListener('resize', function () {
    window.requestAnimationFrame(function () {
      enhanceRenderedMermaidBoxes(document);
    });
  });

  /* ── Startup: DOMContentLoaded (full boot) ── */
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    scheduleBoot(document);
  } else {
    document.addEventListener('DOMContentLoaded', function () { scheduleBoot(document); });
  }

  /* ── PJAX: only re-scan Prism + zoom (skip Mermaid re-init) ── */
  document.addEventListener('pjax:complete', function () {
    scheduleBoot(document);
  });
  document.addEventListener('pjax:end', function () {
    scheduleBoot(document);
  });

})();
