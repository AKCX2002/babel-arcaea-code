#!/bin/bash
set -euo pipefail

echo "=== Mermaid import smoke ==="
node --input-type=module <<'EOF'
const mermaid = await import('file://' + process.cwd() + '/assets/mermaid/mermaid.esm.min.mjs');
if (typeof mermaid.default?.initialize !== 'function' || typeof mermaid.default?.parse !== 'function') {
  throw new Error('Mermaid ESM API is incomplete');
}
console.log('  ✓ Mermaid ESM import OK');
EOF

echo "=== Prism highlight smoke ==="
node <<'EOF'
global.Prism = { manual: true };
require(process.cwd() + '/assets/prism/prism.js');
require(process.cwd() + '/assets/prism/components/prism-json.js');
const html = Prism.highlight('{"a":1}', Prism.languages.json, 'json');
if (!html.includes('token')) {
  throw new Error('Prism highlight output missing token markup');
}
console.log('  ✓ Prism highlight OK');
EOF

echo "=== KaTeX render smoke ==="
node <<'EOF'
const katex = require(process.cwd() + '/assets/katex/katex.min.js');
const html = katex.renderToString('\\frac{1}{2}');
if (!html.includes('katex')) {
  throw new Error('KaTeX render output missing expected markup');
}
console.log('  ✓ KaTeX render OK');
EOF
