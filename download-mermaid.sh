#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
python3 scripts/sync-runtime.py "${1:?Usage: download-mermaid.sh VERSION}"
