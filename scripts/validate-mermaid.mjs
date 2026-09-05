import { readFile, readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(process.argv[2] || fileURLToPath(new URL('../assets/mermaid/', import.meta.url)));
const entry = path.join(root, 'mermaid.esm.min.mjs');
const modules = [entry];
for (const item of await readdir(path.join(root, 'chunks/mermaid.esm.min'))) {
  if (item.endsWith('.mjs')) modules.push(path.join(root, 'chunks/mermaid.esm.min', item));
}
if (modules.length < 2) throw new Error('Mermaid chunks are missing');
let references = 0;
for (const file of modules) {
  const source = await readFile(file, 'utf8');
  if (/^\s*</.test(source)) throw new Error(`HTML instead of JavaScript: ${file}`);
  // Include lazy diagram imports; importing the entry alone cannot check them.
  for (const match of source.matchAll(/(?:\bfrom\s*|\bimport\s*(?:\(\s*)?)["'](\.[^"']+)["']/g)) {
    const dependency = path.resolve(path.dirname(file), match[1]);
    if (!dependency.startsWith(root + path.sep)) throw new Error(`External path: ${match[1]}`);
    if (!(await stat(dependency).catch(() => null))?.isFile()) {
      throw new Error(`Missing Mermaid dependency: ${path.relative(root, file)} -> ${match[1]}`);
    }
    references++;
  }
}
const mermaid = await import(pathToFileURL(entry).href);
if (typeof mermaid.default?.initialize !== 'function' || typeof mermaid.default?.parse !== 'function') {
  throw new Error('Mermaid ESM API is incomplete');
}
console.log(`Mermaid verified: ${modules.length} modules, ${references} relative imports`);
