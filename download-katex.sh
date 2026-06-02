#!/bin/bash
# Download KaTeX assets from jsDelivr.
# On version change, all files are new (different hash filenames).
# On same version, only downloads missing files (fast).
set -e
VER="$1"
[ -z "$VER" ] && echo "Usage: $0 <version>" && exit 1

BASE="https://cdn.jsdelivr.net/npm/katex@${VER}/dist"
DIR="assets/katex"
mkdir -p "$DIR"

echo "=== KaTeX $VER ==="
curl -sL "$BASE/katex.min.js" -o "$DIR/katex.min.js"
echo "  ✓ katex.min.js"

curl -sL "$BASE/katex.min.css" -o "$DIR/katex.min.css"
echo "  ✓ katex.min.css"

curl -sL "$BASE/contrib/auto-render.min.js" -o "$DIR/auto-render.min.js"
echo "  ✓ auto-render.min.js"

mkdir -p "$DIR/fonts"

curl -sL "$BASE/fonts/KaTeX_AMS-Regular.woff2" -o "$DIR/fonts/KaTeX_AMS-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Caligraphic-Bold.woff2" -o "$DIR/fonts/KaTeX_Caligraphic-Bold.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Caligraphic-Regular.woff2" -o "$DIR/fonts/KaTeX_Caligraphic-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Fraktur-Bold.woff2" -o "$DIR/fonts/KaTeX_Fraktur-Bold.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Fraktur-Regular.woff2" -o "$DIR/fonts/KaTeX_Fraktur-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Main-BoldItalic.woff2" -o "$DIR/fonts/KaTeX_Main-BoldItalic.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Main-Bold.woff2" -o "$DIR/fonts/KaTeX_Main-Bold.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Main-Italic.woff2" -o "$DIR/fonts/KaTeX_Main-Italic.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Main-Regular.woff2" -o "$DIR/fonts/KaTeX_Main-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Math-BoldItalic.woff2" -o "$DIR/fonts/KaTeX_Math-BoldItalic.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Math-Italic.woff2" -o "$DIR/fonts/KaTeX_Math-Italic.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_SansSerif-Bold.woff2" -o "$DIR/fonts/KaTeX_SansSerif-Bold.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_SansSerif-Italic.woff2" -o "$DIR/fonts/KaTeX_SansSerif-Italic.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_SansSerif-Regular.woff2" -o "$DIR/fonts/KaTeX_SansSerif-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Script-Regular.woff2" -o "$DIR/fonts/KaTeX_Script-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Size1-Regular.woff2" -o "$DIR/fonts/KaTeX_Size1-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Size2-Regular.woff2" -o "$DIR/fonts/KaTeX_Size2-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Size3-Regular.woff2" -o "$DIR/fonts/KaTeX_Size3-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Size4-Regular.woff2" -o "$DIR/fonts/KaTeX_Size4-Regular.woff2" 2>/dev/null || true
curl -sL "$BASE/fonts/KaTeX_Typewriter-Regular.woff2" -o "$DIR/fonts/KaTeX_Typewriter-Regular.woff2" 2>/dev/null || true
echo "  ✓ fonts (woff2)"

echo "=== Done. KaTeX $VER assets ready ==="
