#!/bin/bash
# Download all Mermaid chunks from jsDelivr
set -e
LAT="$1"
BASE="https://cdn.jsdelivr.net/npm/mermaid@${LAT}/dist"
DIR="assets/mermaid/chunks/mermaid.esm.min"
mkdir -p "$DIR"
curl -sL "$BASE/mermaid.esm.min.mjs" -o assets/mermaid/mermaid.esm.min.mjs
# Fetch complete file list from jsDelivr API and download all .mjs chunk files
curl -sL "https://data.jsdelivr.com/v1/packages/npm/mermaid@${LAT}" | \
  python3 -c "
import sys,json,os,urllib.request
d=json.load(sys.stdin)
chunks=[f['name'] for f in d['files'] if f['name'].startswith('dist/chunks/mermaid.esm.min/') and f['name'].endswith('.mjs')]
os.chdir('$DIR')
for c in chunks:
    fn=c.split('/')[-1]
    if not os.path.exists(fn):
        url='$BASE/'+c.split('dist/')[1]
        urllib.request.urlretrieve(url,fn)
        print('+',fn)
"
echo "Mermaid $LAT: $(ls "$DIR"/*.mjs 2>/dev/null | wc -l) chunks total"
