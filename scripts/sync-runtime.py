"""Install one complete npm Mermaid distribution; never mix release chunks."""
import base64
import hashlib
import io
import json
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]


def download(url):
    with urlopen(url, timeout=60) as response:
        return response.read()


def main(version, package_name='mermaid'):
    if package_name not in ('mermaid', 'mathjax'):
        raise ValueError('Unsupported runtime package')
    if not re.fullmatch(r"\d+\.\d+\.\d+", version):
        raise ValueError("Expected a stable Mermaid version")
    metadata = json.loads(download(f"https://registry.npmjs.org/{package_name}/{version}"))
    archive = download(metadata["dist"]["tarball"])
    algorithm, digest = metadata["dist"]["integrity"].split("-", 1)
    if base64.b64encode(hashlib.new(algorithm, archive).digest()).decode() != digest:
        raise ValueError("npm archive integrity mismatch")
    target = (ROOT / 'assets' / package_name).resolve()
    if target.parent != (ROOT / "assets").resolve() or target.is_symlink():
        raise ValueError("Unexpected Mermaid target")
    with tempfile.TemporaryDirectory(prefix=f'.{package_name}-stage-', dir=ROOT) as temporary:
        stage = Path(temporary)
        with tarfile.open(fileobj=io.BytesIO(archive), mode="r:gz") as package:
            for member in package.getmembers():
                prefix = 'package/dist/' if package_name == 'mermaid' else 'package/'
                if not member.name.startswith(prefix) or not member.isfile():
                    continue
                relative = member.name[len(prefix):]
                selected = (relative == 'mermaid.esm.min.mjs' or relative.startswith('chunks/mermaid.esm.min/')) if package_name == 'mermaid' else relative.startswith('es5/')
                if not selected:
                    continue
                destination = (stage / relative).resolve()
                if not destination.is_relative_to(stage.resolve()):
                    raise ValueError("Archive path escapes staging directory")
                destination.parent.mkdir(parents=True, exist_ok=True)
                with package.extractfile(member) as source, destination.open("wb") as output:
                    shutil.copyfileobj(source, output)
        if package_name == 'mermaid':
            subprocess.run(["node", str(ROOT / "scripts/validate-mermaid.mjs"), str(stage)], check=True)
        else:
            subprocess.run(['node', '--check', str(stage / 'es5/tex-chtml.js')], check=True)
            if not (stage / 'es5/output/chtml/fonts/woff-v2').is_dir():
                raise ValueError('MathJax fonts are missing')
        names = ['chunks', 'mermaid.esm.min.mjs'] if package_name == 'mermaid' else ['es5']
        moved, installed = [], []
        try:
            for name in names:
                original = target / name
                if original.exists():
                    original.rename(stage / ("old-" + name))
                    moved.append(name)
                (stage / name).rename(original)
                installed.append(name)
        except Exception:
            for name in reversed(installed):
                (target / name).rename(stage / ("failed-" + name))
            for name in reversed(moved):
                (stage / ("old-" + name)).rename(target / name)
            raise
    print(f"{package_name} {version}: complete distribution installed")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2] if len(sys.argv) > 2 else 'mermaid')
