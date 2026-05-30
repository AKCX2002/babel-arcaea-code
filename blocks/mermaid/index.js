/**
 * Babel Arcaea Code — Mermaid Gutenberg Block
 *
 * Editor: plain textarea for Mermaid source code entry.
 * Save: <pre class="mermaid">CONTENT</pre> — rendered on frontend
 *       by mermaid-init.js (mermaid.run() with Arcaea theme).
 *
 * Mirrors MerPress's save() format.  No build step, no JSX.
 *
 * @package Babel_Arcaea_Code
 */
(function (wp) {
  'use strict';

  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var PlainText = wp.blockEditor ? wp.blockEditor.PlainText : wp.components.TextareaControl;
  var __ = wp.i18n.__;

  function MermaidEdit(props) {
    var content = props.attributes.content || '';

    return el('div', { className: 'bac-mermaid-block' },
      el(PlainText, {
        __experimentalVersion: 2,
        label: __('Mermaid 图表代码', 'babel-arcaea-code'),
        value: content,
        onChange: function (v) { props.setAttributes({ content: v }); },
        rows: 8,
        placeholder: 'graph TD\n    A --> B\n    B --> C'
      })
    );
  }

  function MermaidSave(props) {
    return el('pre', { className: 'mermaid' }, props.attributes.content);
  }

  registerBlockType('bac/mermaid', {
    title: __('Mermaid 图表', 'babel-arcaea-code'),
    description: __('使用 Mermaid 语法创建流程图、时序图、状态图等。页面加载后自动渲染为 SVG。', 'babel-arcaea-code'),
    icon: 'editor-code',
    category: 'widgets',
    keywords: ['mermaid', 'chart', 'graph', 'diagram', 'flowchart', '图表'],
    attributes: {
      content: {
        type: 'string',
        source: 'text',
        selector: 'pre'
      }
    },
    edit: MermaidEdit,
    save: MermaidSave
  });
})(window.wp);
