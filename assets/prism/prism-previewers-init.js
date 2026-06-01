(function () {
  'use strict';

  function markState(state) {
    document.documentElement.setAttribute('data-bac-prism-previewers', state);
  }

  function checkPreviewers() {
    var prism = window.Prism;
    var ready = !!(prism && prism.plugins && prism.plugins.Previewer);
    markState(ready ? 'ready' : 'missing');
    if (!ready) {
      console.warn('[Babel Arcaea Code] Prism Previewers unavailable: prism-previewers.js may not have initialized correctly.');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkPreviewers);
  } else {
    checkPreviewers();
  }

  document.addEventListener('pjax:complete', checkPreviewers);
  document.addEventListener('pjax:end', checkPreviewers);
})();
