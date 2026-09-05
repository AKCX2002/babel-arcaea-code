/* Rendered content owns module requirements, including after PJAX replacement. */
(function () {
  'use strict';
  const manifest = window.BAC_Assets;
  if (!manifest || window.BAC_ContentLoader) return;
  window.BAC_ContentLoader = true;
  const pending = new Map();
  function inline(source) {
    const script = document.createElement('script');
    script.textContent = source;
    document.head.appendChild(script);
    script.remove();
  }
  function load(kind, handle) {
    const key = kind + ':' + handle;
    if (pending.has(key)) return pending.get(key);
    const asset = manifest[kind][handle];
    // Dependencies already enqueued outside the manifest remain WordPress-owned.
    if (!asset) return Promise.resolve();
    const promise = Promise.all(asset.deps.map(dep => load(kind, dep))).then(() => new Promise((resolve, reject) => {
      asset.before.forEach(inline);
      const element = document.createElement(kind === 'scripts' ? 'script' : 'link');
      element.id = handle + (kind === 'scripts' ? '-js' : '-css');
      element.onload = () => { asset.after.forEach(inline); resolve(); };
      element.onerror = () => { element.remove(); reject(new Error('Cannot load ' + asset.src)); };
      if (kind === 'scripts') { element.src = asset.src; element.async = false; }
      else { element.rel = 'stylesheet'; element.href = asset.src; }
      document.head.appendChild(element);
    }));
    pending.set(key, promise);
    return promise;
  }
  async function boot() {
    const content = document.querySelector('.entry-content, .post-content');
    if (!content) return;
    const required = {
      prism: Array.from(content.querySelectorAll('pre')).some(pre => !pre.closest('.arcaea-mermaid-box, .arcaea-markmap-box') && !pre.matches('.mermaid, .arcaea-markmap-source')),
      mermaid: !!content.querySelector('.mermaid, .arcaea-mermaid-box'),
      markmap: !!content.querySelector('.markmap, .arcaea-markmap-box, .arcaea-markmap-source'),
      zoom: !!content.querySelector('img'),
      math: !!content.querySelector('.katex, .bac-latex-block, .math, .mathjax') || /\$|\\[([]/.test(content.textContent),
    };
    try {
      const results = await Promise.allSettled(Object.entries(required).filter(([name, needed]) => needed && manifest.groups[name]).map(async ([name]) => {
        const group = manifest.groups[name];
        await Promise.all(group.styles.map(handle => load('styles', handle)));
        // Registration order is significant for Prism plugins as well as deps.
        for (const handle of group.scripts) await load('scripts', handle);
      }));
      results.filter(result => result.status === 'rejected').forEach(result => console.warn('[Babel Arcaea Code]', result.reason));
      if (content.isConnected) document.dispatchEvent(new Event('bac:content-ready'));
    } catch (error) { console.warn('[Babel Arcaea Code]', error); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  document.addEventListener('pjax:complete', boot);
  document.addEventListener('pjax:end', boot);
})();
