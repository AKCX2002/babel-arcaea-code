(function () {
  'use strict';
  window.BAC_Lifecycle.register('math', async ({ root, signal, cleanup }) => {
    const math = window.MathJax;
    await math.startup.promise;
    if (signal.aborted) return;
    cleanup(() => math.typesetClear([root]));
    try { await math.typesetPromise([root]); }
    finally { if (signal.aborted) math.typesetClear([root]); }
  });
})();
