#!/bin/bash
# Download ALL Mermaid chunks from jsDelivr (complete clone).
# On version change, all files are new (different hash filenames).
# On same version, only downloads missing files (fast).
set -e
VER="$1"
[ -z "$VER" ] && echo "Usage: $0 <version>" && exit 1

BASE="https://cdn.jsdelivr.net/npm/mermaid@${VER}/dist"
DIR="assets/mermaid/chunks/mermaid.esm.min"
mkdir -p "$DIR"

echo "=== Mermaid $VER: main ==="
curl -sL "$BASE/mermaid.esm.min.mjs" -o assets/mermaid/mermaid.esm.min.mjs
echo "  mermaid.esm.min.mjs"

echo "=== Mermaid $VER: all chunk files ==="
curl -sL "https://data.jsdelivr.com/v1/packages/npm/mermaid@${VER}" | python3 -c "
import sys, json, os, urllib.request
d = json.load(sys.stdin)
BASE_URL = '$BASE'
OUT_DIR = '$DIR'
count = 0

# Also get .mjs.map files alongside .mjs
extensions = ['.mjs', '.mjs.map']
files_dl = set()
for f in d.get('files', []):
    name = f.get('name', '')
    for ext in extensions:
        if 'dist/chunks/mermaid.esm.min/' in name and name.endswith(ext):
            files_dl.add(name)

for full_path in sorted(files_dl):
    filename = full_path.split('/')[-1]
    url = BASE_URL + '/' + full_path.split('dist/')[1]
    out_path = os.path.join(OUT_DIR, filename)
    if not os.path.exists(out_path):
        urllib.request.urlretrieve(url, out_path)
        count += 1
        print(f'  + {filename}')

total_mjs = len([x for x in os.listdir(OUT_DIR) if x.endswith('.mjs')])
print(f'  Downloaded {count} new, {total_mjs} .mjs chunks total')
"

echo "=== Done. Mermaid $VER assets ready ==="
