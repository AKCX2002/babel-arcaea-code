(function () {
  'use strict';

  const LOG = '[Babel Arcaea Code]';

  function decodeHtmlEntities(text) {
    if (!text || text.indexOf('&') === -1) return text || '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  }

  function normalizeMarkmapSource(text) {
    if (!text) return '';
    return decodeHtmlEntities(String(text))
      .replace(/\r\n?/g, '\n')
      .replace(/[\u200B-\u200D\u2060\uFEFF]/g, '')
      .replace(/\u00A0/g, ' ')
      .replace(/[\u2018\u2019]/g, '\'')
      .replace(/[\u201C\u201D]/g, '"')
      .trim();
  }

  function getMarkmapApi() {
    if (window.markmap && window.markmap.Markmap && window.markmap.Transformer) {
      return window.markmap;
    }

    if (window.Markmap && window.Transformer) {
      return {
        Markmap: window.Markmap,
        Transformer: window.Transformer,
      };
    }

    return null;
  }

  function countNodes(node) {
    if (!node) return 0;
    const children = Array.isArray(node.children) ? node.children : [];
    return 1 + children.reduce((sum, child) => sum + countNodes(child), 0);
  }

  function maxDepth(node, depth) {
    if (!node) return depth;
    const children = Array.isArray(node.children) ? node.children : [];
    if (!children.length) return depth;
    return children.reduce((max, child) => Math.max(max, maxDepth(child, depth + 1)), depth);
  }

  function estimateHeight(root) {
    const nodes = countNodes(root);
    const depth = maxDepth(root, 1);
    const raw = 180 + nodes * 18 + depth * 42;
    return Math.max(320, Math.min(raw, 920));
  }

  function setBoxMetrics(box, root) {
    const height = estimateHeight(root);
    box.style.setProperty('--bac-markmap-height', height + 'px');
    box.dataset.bacMarkmapHeight = String(height);
    return height;
  }

  function scheduleFit(box, delay) {
    if (!box || !box.__bacMarkmap || typeof box.__bacMarkmap.fit !== 'function') return;
    window.clearTimeout(box.__bacMarkmapFitTimer);
    box.__bacMarkmapFitTimer = window.setTimeout(function () {
      if (!box.isConnected || !box.__bacMarkmap) return;
      box.__bacMarkmap.fit().catch(error => console.warn(LOG, error));
    }, delay || 0);
  }

  function observeBox(box) {
    if (!box || box.__bacMarkmapObserversReady) return;
    box.__bacMarkmapObserversReady = true;

    if (typeof ResizeObserver !== 'undefined') {
      box.__bacMarkmapResizeObserver = new ResizeObserver(function () {
        scheduleFit(box, 40);
      });
      box.__bacMarkmapResizeObserver.observe(box);
    }

    if (typeof IntersectionObserver !== 'undefined') {
      box.__bacMarkmapIntersectionObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            scheduleFit(box, 20);
          }
        });
      }, { threshold: 0.05 });
      box.__bacMarkmapIntersectionObserver.observe(box);
    }
  }

  async function renderOne(box, signal) {
    if (!box || box.dataset.bacMarkmapReady === '1' || box.dataset.bacMarkmapRendering === '1') return;

    const source = box.querySelector('.arcaea-markmap-source');
    const svg = box.querySelector('svg.arcaea-markmap-diagram');
    if (!source || !svg) return;

    const markdown = normalizeMarkmapSource(source.textContent);
    if (!markdown) {
      box.classList.add('arcaea-markmap-empty');
      return;
    }
    source.textContent = markdown;

    const api = getMarkmapApi();
    if (!api) {
      box.classList.add('arcaea-markmap-error');
      box.dataset.bacMarkmapError = 'runtime-missing';
      if (!box.querySelector('.arcaea-markmap-error-message')) {
        const message = document.createElement('div');
        message.className = 'arcaea-markmap-error-message';
        message.textContent = 'Markmap runtime missing.';
        box.appendChild(message);
      }
      return;
    }

    box.dataset.bacMarkmapRendering = '1';

    try {
      const transformer = new api.Transformer();
      const result = transformer.transform(markdown);
      const root = result && result.root ? result.root : null;

      if (!root) throw new Error('Markmap transform produced empty root.');

      const estimatedHeight = setBoxMetrics(box, root);
      svg.innerHTML = '';

      const mm = api.Markmap.create(svg, {
        autoFit: false,
        duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 300,
        maxWidth: Math.min(Math.max(Math.floor((box.clientWidth || 960) * 0.42), 220), 420),
        colorFreezeLevel: 2,
        spacingHorizontal: 72,
        spacingVertical: 14,
        paddingX: 24,
      });

      box.__bacMarkmap = mm;
      await mm.setData(root);
      if (signal.aborted) return;
      box.__bacMarkmapHeight = estimatedHeight;
      box.dataset.bacMarkmapReady = '1';
      delete box.dataset.bacMarkmapRendering;
      observeBox(box);
      scheduleFit(box, 180);
    } catch (e) {
      delete box.dataset.bacMarkmapRendering;
      box.classList.add('arcaea-markmap-error');
      box.dataset.bacMarkmapError = e && e.message ? String(e.message) : 'render-error';
      if (!box.querySelector('.arcaea-markmap-error-message')) {
        const message = document.createElement('div');
        message.className = 'arcaea-markmap-error-message';
        message.textContent = 'Markmap render error: ' + (e && e.message ? e.message : 'unknown error');
        box.appendChild(message);
      }
      console.error(LOG, 'Markmap render error:', e);
    }
  }

  function initMarkmaps(root, signal) {
    const scope = root && root.querySelectorAll ? root : document;
    return Promise.all(Array.from(scope.querySelectorAll('.arcaea-markmap-box:not([data-bac-markmap-ready="1"])')).map(box => renderOne(box, signal)));
  }

  window.BAC_Lifecycle.register('markmap', async ({ root, signal, cleanup }) => {
    const boxes = Array.from(root.querySelectorAll('.arcaea-markmap-box'));
    cleanup(() => boxes.forEach(box => {
      window.clearTimeout(box.__bacMarkmapFitTimer);
      box.__bacMarkmapResizeObserver?.disconnect();
      box.__bacMarkmapIntersectionObserver?.disconnect();
      const svg = box.querySelector('svg');
      // Markmap.destroy removes handlers but does not cancel D3 transitions.
      window.d3.select(svg).interrupt().selectAll('*').interrupt();
      box.__bacMarkmap?.destroy();
      delete box.__bacMarkmap;
      delete box.__bacMarkmapObserversReady;
      delete box.dataset.bacMarkmapReady;
      delete box.dataset.bacMarkmapRendering;
    }));
    await initMarkmaps(root, signal);
    if (signal.aborted) return;
    window.addEventListener('resize', () => boxes.forEach(box => scheduleFit(box, 160)), { signal });
  });
})();
