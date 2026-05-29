#!/bin/bash
# Download ALL Mermaid chunks from jsDelivr (complete clone)
set -e
LAT="$1"
[ -z "$LAT" ] && echo "Usage: $0 <version>" && exit 1

BASE="https://cdn.jsdelivr.net/npm/mermaid@${LAT}/dist"
DIR="assets/mermaid/chunks/mermaid.esm.min"
mkdir -p "$DIR"

echo "Downloading mermaid.esm.min.mjs..."
curl -sL "$BASE/mermaid.esm.min.mjs" -o assets/mermaid/mermaid.esm.min.mjs

echo "Downloading ALL chunk files from jsDelivr API..."
curl -sL "https://data.jsdelivr.com/v1/packages/npm/mermaid@${LAT}" | python3 -c "
import sys, json, os, urllib.request
d = json.load(sys.stdin)
CHUNK_DIR = 'dist/chunks/mermaid.esm.min/'
BASE_URL = '$BASE'
OUT_DIR = '$DIR'

files = [f['name'] for f in d.get('files', [])
         if CHUNK_DIR in f.get('name', '') and f['name'].endswith('.mjs')]
print(f'Total chunks in package: {len(files)}')

for full_path in files:
    filename = full_path.split('/')[-1]
    url = BASE_URL + '/' + full_path.split('dist/')[1]
    out_path = os.path.join(OUT_DIR, filename)
    urllib.request.urlretrieve(url, out_path)
    print(f'  {filename}')

print(f'Done: {len(files)} chunks')
"

echo "Complete: $(ls \"$DIR\"/*.mjs 2>/dev/null | wc -l) chunks total"
