(function () {
  'use strict';

  const LOG = '[Babel Arcaea Code]';

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

  function renderOne(box) {
    if (!box || box.dataset.bacMarkmapReady === '1' || box.dataset.bacMarkmapRendering === '1') return;

    const source = box.querySelector('.arcaea-markmap-source');
    const svg = box.querySelector('svg.arcaea-markmap-diagram');
    if (!source || !svg) return;

    const markdown = source.textContent.trim();
    if (!markdown) {
      box.classList.add('arcaea-markmap-empty');
      return;
    }

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

      svg.innerHTML = '';

      const mm = api.Markmap.create(svg, {
        autoFit: true,
        duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 300,
        maxWidth: 320,
        colorFreezeLevel: 2,
        spacingHorizontal: 80,
        spacingVertical: 10,
        paddingX: 16,
      }, root);

      box.__bacMarkmap = mm;
      box.dataset.bacMarkmapReady = '1';
      delete box.dataset.bacMarkmapRendering;
    } catch (e) {
      delete box.dataset.bacMarkmapRendering;
      box.classList.add('arcaea-markmap-error');
      console.error(LOG, 'Markmap render error:', e);
    }
  }

  function initMarkmaps(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.arcaea-markmap-box:not([data-bac-markmap-ready="1"])')
      .forEach(renderOne);
  }

  function fitAll() {
    document.querySelectorAll('.arcaea-markmap-box[data-bac-markmap-ready="1"]').forEach(function (box) {
      if (box.__bacMarkmap && typeof box.__bacMarkmap.fit === 'function') {
        box.__bacMarkmap.fit();
      }
    });
  }

  function boot() {
    initMarkmaps(document);
  }

  document.addEventListener('DOMContentLoaded', boot);
  window.addEventListener('load', function () {
    boot();
    setTimeout(fitAll, 120);
  });
  document.addEventListener('pjax:complete', boot);
  document.addEventListener('pjax:end', boot);
  window.addEventListener('resize', function () {
    window.clearTimeout(window.__bacMarkmapResizeTimer);
    window.__bacMarkmapResizeTimer = window.setTimeout(fitAll, 160);
  });
})();
