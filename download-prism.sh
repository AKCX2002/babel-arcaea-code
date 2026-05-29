#!/bin/bash
# Download ALL Prism.js plugins + languages from jsDelivr (complete clone).
# On version change, all files are new (different hash filenames).
# On same version, only downloads missing files (fast).
set -e
VER="$1"
[ -z "$VER" ] && echo "Usage: $0 <version>" && exit 1

BASE="https://cdn.jsdelivr.net/npm/prismjs@${VER}"
OUT="assets/prism"
mkdir -p "$OUT/components"

echo "=== Prism.js $VER: core ==="
curl -sL "$BASE/prism.min.js" -o "$OUT/prism.js"
curl -sL "$BASE/themes/prism.min.css" -o "$OUT/prism.css"
echo "  prism.js + prism.css"

echo "=== Prism.js $VER: all plugins ==="
curl -sL "https://data.jsdelivr.com/v1/packages/npm/prismjs@${VER}" | python3 -c "
import sys, json, os, urllib.request
d = json.load(sys.stdin)
BASE_URL = '$BASE'
OUT_DIR = '$OUT'
count = 0

def walk(files, prefix=''):
    global count
    for f in files:
        name = prefix + '/' + f['name'] if prefix else f['name']
        if f['type'] == 'directory':
            walk(f.get('files',[]), name)
        elif '/plugins/' in name and (name.endswith('.min.js') or name.endswith('.min.css')):
            basename = name.split('/')[-1].replace('.min', '')
            url = BASE_URL + '/' + name
            out_path = os.path.join(OUT_DIR, basename)
            if not os.path.exists(out_path):
                urllib.request.urlretrieve(url, out_path)
                count += 1
                print(f'  + {basename}')
walk(d.get('files',[]))
print(f'  Downloaded {count} new, total plugins: {len([x for x in os.listdir(OUT_DIR) if x.endswith(\".js\") and x != \"prism.js\"])} JS')
" 2>&1

echo "=== Prism.js $VER: all languages ==="
curl -sL "https://data.jsdelivr.com/v1/packages/npm/prismjs@${VER}" | python3 -c "
import sys, json, os, urllib.request
d = json.load(sys.stdin)
BASE_URL = '$BASE'
OUT_DIR = '$OUT/components'
count = 0

def walk(files, prefix=''):
    global count
    for f in files:
        name = prefix + '/' + f['name'] if prefix else f['name']
        if f['type'] == 'directory':
            walk(f.get('files',[]), name)
        elif name.startswith('components/prism-') and name.endswith('.min.js') and 'prism-core' not in name:
            basename = name.split('/')[-1].replace('.min', '')
            url = BASE_URL + '/' + name
            out_path = os.path.join(OUT_DIR, basename)
            if not os.path.exists(out_path):
                urllib.request.urlretrieve(url, out_path)
                count += 1
walk(d.get('files',[]))
total = len([x for x in os.listdir(OUT_DIR) if x.endswith('.js')])
print(f'  Downloaded {count} new, {total} languages total')
" 2>&1

echo "=== Done. Prism $VER assets ready ==="
