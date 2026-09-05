"""Refresh supported dependency versions, failing before release on download errors."""
import json
from pathlib import Path
import re
import subprocess
import sys
from urllib.request import urlopen

root = Path(__file__).resolve().parents[1]
options = root / 'includes/class-bac-options.php'
source = options.read_text(encoding='utf-8')

for name, package in [('mermaid', 'mermaid'), ('prism', 'prismjs'), ('mathjax', 'mathjax'), ('katex', 'katex')]:
    current = re.search(r"'" + name + r"_version'\s*=>\s*'([^']+)'", source).group(1)
    version = current
    # The runtime uses the MathJax 3 es5 API. Major 4 is not compatible.
    if '--latest' in sys.argv or (name == 'mathjax' and not current.startswith('3.')):
        with urlopen(f'https://registry.npmjs.org/{package}', timeout=60) as response:
            metadata = json.load(response)
        candidates = [v for v in metadata['versions'] if re.fullmatch(r'\d+\.\d+\.\d+', v)]
        if name == 'mathjax':
            candidates = [v for v in candidates if v.startswith('3.')]
        version = max(candidates, key=lambda v: tuple(map(int, v.split('.'))))
    if name == 'mathjax':
        subprocess.run([sys.executable, 'scripts/sync-runtime.py', version, 'mathjax'], cwd=root, check=True)
    else:
        subprocess.run(['bash', f'download-{name}.sh', version], cwd=root, check=True)
    source = re.sub(r"('" + name + r"_version'\s*=>\s*')[^']+(')", lambda m: m[1] + version + m[2], source)
options.write_text(source, encoding='utf-8', newline='\n')
