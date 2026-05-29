#!/usr/bin/env node
/**
 * Babel Arcaea Code — Markmap CLI SVG Renderer
 *
 * Reads markdown mindmap content from stdin and outputs an inline SVG
 * with Arcaea Dark theming. Used by the WordPress plugin to pre-render
 * markmap diagrams server-side, eliminating client-side JS dependencies.
 *
 * Usage:
 *   echo "# Title\n## Child" | node bin/markmap-render.js > output.svg
 *   cat content.md | node bin/markmap-render.js --theme arcaea-light
 *
 * Options:
 *   --theme     arcaea-dark (default) | arcaea-light
 *   --padding   padding around diagram (default: 40)
 *   --help      show usage
 */

const { Transformer } = require('markmap-lib');

// ── Parse CLI args ──
const args = process.argv.slice(2);
const flags = {};
for (let i = 0; i < args.length; i++) {
    if (args[i] === '--help') {
        console.log(require('fs').readFileSync(__filename, 'utf8').split('\n').slice(0, 18).join('\n'));
        process.exit(0);
    }
    if (args[i].startsWith('--')) {
        const key = args[i].slice(2);
        flags[key] = args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : true;
        if (flags[key] !== true) i++;
    }
}

const theme = flags.theme || 'arcaea-dark';
const padding = parseInt(flags.padding, 10) || 40;

// ── Theme colors ──
const THEMES = {
    'arcaea-dark': {
        bg: 'rgba(15,24,42,0.32)',
        border: 'rgba(230,238,255,0.28)',
        text: '#eef4ff',
        textDim: 'rgba(238,244,255,0.7)',
        nodeBg: 'rgba(30,50,80,0.5)',
        nodeBorder: 'rgba(160,220,255,0.45)',
        edge: 'rgba(160,220,255,0.55)',
        rootBg: 'rgba(40,70,120,0.35)',
        rootBorder: 'rgba(160,220,255,0.65)',
    },
    'arcaea-light': {
        bg: 'rgba(245,240,235,0.8)',
        border: 'rgba(180,160,140,0.35)',
        text: '#2c2420',
        textDim: 'rgba(44,36,32,0.6)',
        nodeBg: 'rgba(255,255,255,0.7)',
        nodeBorder: 'rgba(160,140,120,0.4)',
        edge: 'rgba(140,120,100,0.5)',
        rootBg: 'rgba(255,255,255,0.85)',
        rootBorder: 'rgba(160,140,120,0.55)',
    },
};

const C = THEMES[theme] || THEMES['arcaea-dark'];

// ── Layout constants ──
const NODE_MIN_W = 100;
const NODE_H = 36;
const NODE_PAD_X = 14;
const H_GAP = 50;   // horizontal gap between parent and child
const V_GAP = 14;    // vertical gap between siblings
const LEVEL_GAP = 16; // extra vertical padding per level for nested children

// ── Layout calculation ──
function calcLayout(node, depth) {
    const children = (node.children || []).filter(c => c.content);
    const childLayouts = children.map(c => calcLayout(c, depth + 1));
    const nodeH = NODE_H;

    // Estimate text width (monospace approximation: ~8.5px per char)
    const textWidth = Math.max(NODE_MIN_W, node.content.length * 8.5 + NODE_PAD_X * 2 + 10);
    const nodeW = Math.min(textWidth, 360); // cap width

    return {
        node,
        content: node.content,
        width: nodeW,
        height: nodeH,
        children: childLayouts,
        totalH: Math.max(nodeH, childLayouts.length > 0
            ? childLayouts.reduce((s, cl) => s + cl.totalH, 0) + (childLayouts.length - 1) * V_GAP
            : 0),
    };
}

function assignPositions(layout, x, y) {
  layout.x = x;
  layout.y = y - layout.height / 2;

  if (layout.children.length === 0) return;

  const childX = x + layout.width + H_GAP;
  const totalChildrenH = layout.children.reduce((s, cl) => s + cl.totalH, 0) + (layout.children.length - 1) * V_GAP;
  let childY = y - totalChildrenH / 2;

  for (const cl of layout.children) {
    assignPositions(cl, childX, childY + cl.totalH / 2);
        childY += cl.totalH + V_GAP;
    }
}

// ── SVG rendering ──
function escapeXml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function renderSvg(layout) {
    // Find bounds
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    function walk(l) {
        const r = l.x + l.width;
        const b = l.y + l.height;
        if (l.x < minX) minX = l.x;
        if (l.y < minY) minY = l.y;
        if (r > maxX) maxX = r;
        if (b > maxY) maxY = b;
        l.children.forEach(walk);
    }
    walk(layout);

    const w = maxX - minX + padding * 2;
    const h = maxY - minY + padding * 2;
    const ox = padding - minX;
    const oy = padding - minY;

    const lines = [];
    const nodes = [];

function collect(l, px, py, pw) {
    const cx = l.x + ox;
    const cy = l.y + oy;
    const cw = l.width;
    const ch = l.height;

    // Edge line from parent to this node
    if (px !== null && py !== null) {
      const parentRight = px + pw;
      const childLeft = cx;
      const parentCY = py;
      const childCY = cy + ch / 2;
      lines.push(`    <path d="M${parentRight},${parentCY} L${childLeft},${parentCY} L${childLeft},${childCY}" fill="none" stroke="${C.edge}" stroke-width="1.8" stroke-linecap="round"/>`);
    }

    const isRoot = px === null;
    const bg = isRoot ? C.rootBg : C.nodeBg;
    const border = isRoot ? C.rootBorder : C.nodeBorder;
    const rc = 8;

    nodes.push(`    <rect x="${cx}" y="${cy}" width="${cw}" height="${ch}" rx="${rc}" ry="${rc}" fill="${bg}" stroke="${border}" stroke-width="1.5"/>`);
    nodes.push(`    <text x="${cx + cw / 2}" y="${cy + ch / 2 + 1}" text-anchor="middle" dominant-baseline="central" fill="${C.text}" font-family="FiraCode Nerd Font,Fira Code,JetBrains Mono,Noto Sans SC,Microsoft YaHei,sans-serif" font-size="13" font-weight="${isRoot ? 600 : 500}">${escapeXml(l.content)}</text>`);

    l.children.forEach(cl => collect(cl, cx, cy + ch / 2, cw));
    }

    collect(layout, null, null);

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="100%" height="100%" class="arcaea-markmap-diagram" style="background:${C.bg};border:1px solid ${C.border};border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,0.18),inset 0 1px 0 rgba(255,255,255,0.08);">
  <g>
${lines.join('\n')}
${nodes.join('\n')}
  </g>
</svg>`;
}

// ── Main ──
let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => input += chunk);
process.stdin.on('end', () => {
    const md = input.trim();
    if (!md) {
        console.error('markmap-render: empty input');
        process.exit(1);
    }

    try {
        const transformer = new Transformer();
        const { root } = transformer.transform(md);
        const layout = calcLayout(root, 0);

        // Center root vertically
        layout.height = NODE_H;
        assignPositions(layout, 0, layout.totalH / 2, 0);

        const svg = renderSvg(layout);
        console.log(svg);
    } catch (err) {
        console.error('markmap-render error:', err.message);
        process.exit(1);
    }
});
