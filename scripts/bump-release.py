"""Keep release metadata in sync; called only after release validation."""
import json
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
plugin = root / 'babel-arcaea-code.php'
source = plugin.read_text(encoding='utf-8')
old = re.search(r'Version:\s*(\d+\.\d+\.\d+)', source).group(1)
major, minor, patch = map(int, old.split('.'))
version = f'{major}.{minor}.{patch + 1}'
source = source.replace(f'Version: {old}', f'Version: {version}').replace(f"define('BAC_VERSION', '{old}')", f"define('BAC_VERSION', '{version}')")
plugin.write_text(source, encoding='utf-8', newline='\n')
for name in ['package.json', 'package-lock.json', 'update-info.json']:
    file = root / name
    data = json.loads(file.read_text(encoding='utf-8'))
    data['version'] = version
    if name == 'package-lock.json':
        data['packages']['']['version'] = version
    if name == 'update-info.json':
        data['download_url'] = f'https://github.com/AKCX2002/babel-arcaea-code/releases/download/v{version}/babel-arcaea-code-{version}.zip'
        data['sections']['changelog'] = '<p>See CHANGELOG.md and the release notes.</p>'
    file.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(version)
