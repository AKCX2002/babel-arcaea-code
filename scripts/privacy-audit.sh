#!/usr/bin/env bash
# ============================================================
# Babel Arcaea Code — Privacy Audit Script
#
# Scans the repository for potential secrets, credentials,
# and personally identifiable information (PII) that should
# not be committed.
#
# Usage:
#   bash scripts/privacy-audit.sh
#
# Exit code:
#   0 — no suspicious patterns found
#   1 — suspicious patterns detected (output listed)
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color
HITS=0

scan_pattern() {
    local pattern="$1"
    local label="$2"

    # Use grep to find matches, excluding .git directory
    results=$(grep -RInE "$pattern" . \
        --exclude-dir=.git \
        --exclude-dir=node_modules \
        --exclude-dir=vendor \
        --exclude-dir=lib \
        --exclude-dir=.github \
        --exclude-dir=assets/prism/components \
        --exclude="privacy-audit.sh" \
        --exclude="CHANGELOG.md" \
        --exclude="*.svg" \
        --exclude="*.min.js" \
        --exclude="*.min.mjs" \
        --exclude="*.css" \
        2>/dev/null || true)

    if [ -n "$results" ]; then
        echo -e "${RED}[!] ${label}${NC}"
        echo "$results"
        echo ""
        HITS=$((HITS + 1))
    fi
}

echo "============================================"
echo " Babel Arcaea Code — Privacy Audit"
echo " Scanning for secrets and PII..."
echo "============================================"
echo ""

scan_pattern "(GH_TOKEN|GITHUB_TOKEN)" "GitHub Token (plaintext)"
# CHANGELOG.md and code references to token env vars are documentation, not secrets.
scan_pattern "(password|passwd|secret)" "Password / Secret literal"
scan_pattern "(api[_-]?key|api_key|apikey)" "API Key literal"
scan_pattern "(bearer|authorization)" "Authorization / Bearer token"
scan_pattern "(BEGIN (RSA|OPENSSH|PRIVATE) KEY)" "Private SSH/RSA key"
scan_pattern "[a-zA-Z0-9._%+-]+@(gmail|outlook|qq|163)\.com" "Personal email address"
scan_pattern "C:\\\\(Users|Windows|Program)" "Windows absolute path"
scan_pattern "/home/[^/]+/" "Home directory path"

echo "============================================"
if [ "$HITS" -eq 0 ]; then
    echo -e "${GREEN}✓ No suspicious patterns detected.${NC}"
    exit 0
else
    echo -e "${RED}✗ Found ${HITS} category(ies) of potential issues above.${NC}"
    echo "  Review each match and remove if it contains real secrets or PII."
    exit 1
fi
