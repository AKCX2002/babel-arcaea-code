(function () {
  'use strict';
  var config = window.BAC_Config || {};
  function asBool(value, fallback) { return value === undefined ? fallback : value === true || value === 1 || value === '1'; }
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
  function boot() { initZoom(document); }
  document.addEventListener('bac:content-ready', boot);
})();
