/**
 * Babel Arcaea Code — Mermaid Editor Bridge
 *
 * Dynamically imports Mermaid ESM into the Gutenberg editor context
 * so block previews can call window.mermaid.parse() and render().
 *
 * Must load before the block's editorScript (index.js).
 *
 * @package Babel_Arcaea_Code
 */
(function () {
  'use strict';

  var url = (window.BAC_Mermaid_Editor && window.BAC_Mermaid_Editor.mermaidUrl)
    || '/wp-content/plugins/babel-arcaea-code/assets/mermaid/mermaid.esm.min.mjs';

  import(url).then(function (mod) {
    window.mermaid = mod.default;
    console.log('[BAC] Mermaid loaded in editor');
  }).catch(function (e) {
    console.warn('[BAC] Mermaid editor load failed:', e);
  });
})();
