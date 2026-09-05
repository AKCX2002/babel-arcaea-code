import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, extname } from 'node:path';
import { chromium } from 'playwright';

const root = resolve(import.meta.dirname, '..');
const article = `<div class="entry-content">
<pre class="language-javascript"><code class="language-javascript">const answer = 42;</code></pre>
<div class="arcaea-mermaid-box"><pre class="mermaid">graph TD\n A[Start] --> B[Finish]</pre></div>
<div class="arcaea-markmap-box"><pre class="arcaea-markmap-source"># Root\n## Child</pre><svg class="arcaea-markmap-diagram"></svg></div>
<p>Formula: \\(x^2+1\\)</p>
<img width="40" height="40" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Crect width='40' height='40' fill='blue'/%3E%3C/svg%3E">
</div>`;
const manifests = new Map(['katex', 'mathjax'].map(mode => [mode, JSON.parse(execFileSync(
  process.env.PHP_BINARY || 'php', ['scripts/content-regression.php', '--manifest', ...(mode === 'mathjax' ? ['--mathjax'] : [])],
  { cwd: root, encoding: 'utf8' }
))]));
const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/') {
      const { assets, config } = manifests.get(url.searchParams.get('mode') || 'katex');
      res.setHeader('Content-Type', 'text/html');
      res.end(`<!doctype html><meta charset="utf-8"><link rel="icon" href="data:,"><style>.entry-content{max-width:800px}svg{max-width:100%}</style>
<button id="article">Article</button><button id="home">Home</button><main></main>
<script>var BAC_Assets=${JSON.stringify(assets)}, BAC_Config=${JSON.stringify(config)};
window.readyCount=0;document.addEventListener('bac:content-ready',()=>readyCount++);
const article=${JSON.stringify(article)};
document.querySelector('#article').onclick=()=>{document.querySelector('main').innerHTML=article;document.dispatchEvent(new Event('pjax:complete'));document.dispatchEvent(new Event('pjax:end'));};
document.querySelector('#home').onclick=()=>{window.oldContent=document.querySelector('.entry-content');document.querySelector('main').innerHTML='Home';document.dispatchEvent(new Event('pjax:complete'));};
if(new URL(location.href).searchParams.has('initial')) document.querySelector('main').innerHTML=article;
</script><script src="/assets/js/content-loader.js"></script>`);
      return;
    }
    const file = resolve(root, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(root + '/') && !file.startsWith(root + '\\')) throw new Error('Invalid path');
    const mime = { '.js': 'text/javascript', '.mjs': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' };
    res.setHeader('Content-Type', mime[extname(file)] || 'application/octet-stream');
    res.end(await readFile(file));
  } catch { res.writeHead(404); res.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch(process.env.BROWSER_CHANNEL ? { channel: process.env.BROWSER_CHANNEL } : {});
try {
  for (const mode of manifests.keys()) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' || message.type() === 'warning') errors.push(message.text()); });
    await page.goto(`${base}/?mode=${mode}&initial=1`);
    for (let round = 1; round <= 3; round++) {
      await page.waitForFunction(n => window.readyCount === n, round).catch(async error => { console.error(mode, round, errors, await page.evaluate(() => ({ready: window.readyCount, html: document.querySelector("main").innerHTML.slice(0,300)}))); throw error; });
      assert.equal(await page.locator('pre.mermaid svg').count(), 1);
      assert.ok(await page.locator('code .token').count() > 0);
      assert.ok(await page.locator('.arcaea-markmap-diagram g').count() > 0);
      assert.ok(await page.locator(mode === 'katex' ? '.katex' : 'mjx-container').count() > 0);
      assert.equal(await page.locator('.bac-code-shell .bac-code-shell').count(), 0);
      await page.setViewportSize({ width: round % 2 ? 390 : 1280, height: 844 });
      await page.locator('.arcaea-mermaid-toolbar button').first().click();
      assert.equal(await page.locator('.arcaea-mermaid-overlay.active').count(), 1);
      await page.keyboard.press('Escape');
      await page.locator('.entry-content img').click();
      await page.waitForSelector('.medium-zoom-overlay');
      // Navigation can happen while an overlay is open, without a pointer click.
      await page.evaluate(() => document.querySelector('#home').click());
      await page.waitForFunction(() => !document.querySelector('.medium-zoom-overlay'));
      assert.equal(await page.locator('.arcaea-mermaid-overlay.active').count(), 0);
      assert.equal(await page.evaluate(() => !!window.oldContent.querySelector('.arcaea-markmap-box').__bacMarkmap), false);
      assert.equal(await page.evaluate(() => window.oldContent.querySelector('img').classList.contains('medium-zoom-image')), false);
      if (round < 3) await page.locator('#article').click();
    }
    const sources = await page.locator('script[src]').evaluateAll(nodes => nodes.map(node => node.src));
    assert.equal(sources.length, new Set(sources).size, 'Repeated asset requests');
    assert.deepEqual(errors, []);
    await page.close();
    console.log(`${mode}: initial render, repeated PJAX events, 3 visits, resize, overlays and instance cleanup passed`);
  }
  const page = await browser.newPage();
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  await page.route('**/mermaid-init.js*', async route => { await gate; await route.continue(); });
  await page.goto(base);
  await page.locator('#article').click();
  await page.locator('#home').click();
  release();
  await page.locator('#article').click();
  await page.waitForFunction(() => window.readyCount === 1);
  assert.equal(await page.locator('pre.mermaid svg').count(), 1);
  console.log('Navigation during delayed asset loading passed');
} finally {
  await browser.close();
  server.close();
}
