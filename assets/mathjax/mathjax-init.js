(function () {
  'use strict';

  var LOG = '[Babel Arcaea Code: MathJax]';

  function renderMathJax(root) {
    if (!window.MathJax || typeof window.MathJax.typesetPromise !== 'function') {
      return;
    }

    var scope = root && root.querySelectorAll ? root : document;
    var targets = Array.prototype.slice.call(
      scope.querySelectorAll('.bac-latex-block[data-bac-latex="mathjax"], .entry-content, .post-content, .article-content')
    );

    if (!targets.length) {
      targets = [document.body];
    }

    window.MathJax.typesetClear(targets);
    window.MathJax.typesetPromise(targets).catch(function (error) {
      console.warn(LOG, error);
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    renderMathJax(document);
  } else {
    document.addEventListener('DOMContentLoaded', function () { renderMathJax(document); });
  }

  document.addEventListener('pjax:complete', function () { renderMathJax(document); });
  document.addEventListener('pjax:end', function () { renderMathJax(document); });
})();
