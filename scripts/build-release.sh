#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"

if [[ -z "${VERSION}" ]]; then
  VERSION="$(grep -oP 'Version:\s*\K[0-9.]+' "$ROOT_DIR/babel-arcaea-code.php" | head -1)"
fi

if [[ -z "${VERSION}" ]]; then
  echo "Could not determine plugin version." >&2
  exit 1
fi

DIST_ROOT="${ROOT_DIR}/dist"
PKG_DIR="${DIST_ROOT}/babel-arcaea-code"
ZIP_PATH="${DIST_ROOT}/babel-arcaea-code-${VERSION}.zip"

rm -rf "${PKG_DIR}" "${ZIP_PATH}"
mkdir -p "${PKG_DIR}"

cp -r \
  "${ROOT_DIR}/babel-arcaea-code.php" \
  "${ROOT_DIR}/includes" \
  "${ROOT_DIR}/assets" \
  "${ROOT_DIR}/blocks" \
  "${ROOT_DIR}/bin" \
  "${ROOT_DIR}/lib" \
  "${ROOT_DIR}/scripts" \
  "${ROOT_DIR}/README.md" \
  "${ROOT_DIR}/CHANGELOG.md" \
  "${ROOT_DIR}/LICENSE" \
  "${ROOT_DIR}/package.json" \
  "${ROOT_DIR}/package-lock.json" \
  "${PKG_DIR}/"

test -f "${PKG_DIR}/blocks/mermaid/block.json"
test -f "${PKG_DIR}/assets/mathjax/mathjax-init.js"
test -f "${PKG_DIR}/assets/css/bac-latex.css"
test -f "${PKG_DIR}/includes/class-bac-markmap.php"
node "${ROOT_DIR}/scripts/validate-mermaid.mjs" "${PKG_DIR}/assets/mermaid" >&2

(
  cd "${DIST_ROOT}"
  python3 -m zipfile -c "$(basename "${ZIP_PATH}")" "babel-arcaea-code"
)

echo "${ZIP_PATH}"
