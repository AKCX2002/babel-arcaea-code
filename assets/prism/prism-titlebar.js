(function () {
  'use strict';

  var EXTENSIONS = {
    bash: 'terminal',
    shell: 'terminal',
    sh: 'terminal',
    zsh: 'terminal',
    console: 'terminal',
    c: 'main.c',
    cpp: 'main.cpp',
    csharp: 'Program.cs',
    cmake: 'CMakeLists.txt',
    css: 'styles.css',
    diff: 'patch.diff',
    go: 'main.go',
    html: 'index.html',
    javascript: 'main.js',
    js: 'main.js',
    json: 'config.json',
    jsx: 'App.jsx',
    makefile: 'Makefile',
    markdown: 'README.md',
    md: 'README.md',
    php: 'index.php',
    python: 'main.py',
    py: 'main.py',
    rust: 'main.rs',
    sql: 'query.sql',
    text: 'snippet.txt',
    toml: 'config.toml',
    ts: 'main.ts',
    tsx: 'App.tsx',
    typescript: 'main.ts',
    xml: 'config.xml',
    yaml: 'config.yaml',
    yml: 'config.yaml'
  };

  function getLanguage(pre) {
    var code = pre.querySelector('code[class*="language-"]');
    var className = (code ? code.className : pre.className) || '';
    var match = className.match(/language-([a-z0-9_+#.-]+)/i);
    return match ? match[1].toLowerCase() : 'text';
  }

  function getContainer(pre) {
    return pre.parentElement && pre.parentElement.classList.contains('code-toolbar')
      ? pre.parentElement
      : pre;
  }

  function getShell(container) {
    var shell = container.parentElement;
    if (shell && shell.classList.contains('bac-code-shell')) return shell;

    shell = document.createElement('div');
    shell.className = 'bac-code-shell';
    container.parentNode.insertBefore(shell, container);
    shell.appendChild(container);
    return shell;
  }

  function getDisplayName(pre, language) {
    var dataName = pre.getAttribute('data-filename') || pre.getAttribute('data-label');
    if (dataName) return dataName;
    return EXTENSIONS[language] || ('snippet.' + language.replace(/[^a-z0-9]+/g, '-'));
  }

  function createTitlebar(name) {
    var titlebar = document.createElement('div');
    titlebar.className = 'bac-code-titlebar';

    var dots = document.createElement('div');
    dots.className = 'bac-code-titlebar-dots';
    dots.setAttribute('aria-hidden', 'true');
    dots.innerHTML = '<span></span><span></span><span></span>';

    var label = document.createElement('div');
    label.className = 'bac-code-titlebar-name';
    label.textContent = name;

    titlebar.appendChild(dots);
    titlebar.appendChild(label);
    return titlebar;
  }

  function decorateCodeBlocks(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('pre[class*="language-"]:not(.mermaid):not(.arcaea-markmap-source)').forEach(function (pre) {
      if (pre.closest('.arcaea-mermaid-box') || pre.closest('.arcaea-markmap-box')) return;

      var container = getContainer(pre);
      var shell = getShell(container);
      if (shell.querySelector('.bac-code-titlebar')) return;

      var language = getLanguage(pre);
      var titlebar = createTitlebar(getDisplayName(pre, language));
      shell.insertBefore(titlebar, container);
      shell.dataset.bacCodeLanguage = language;
    });
  }

  function schedule(root) {
    window.requestAnimationFrame(function () {
      decorateCodeBlocks(root || document);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { schedule(document); });
  } else {
    schedule(document);
  }

  document.addEventListener('pjax:complete', function () { schedule(document); });
  document.addEventListener('pjax:end', function () { schedule(document); });
})();
