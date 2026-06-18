#!/usr/bin/env bash
#
# Local lint + security check, run by hand before committing/pushing.
# Usage: composer check   (or: bash scripts/check.sh)

set -uo pipefail
cd "$(dirname "$0")/.."
fail=0

echo "==> phpcs (PSR-12)"
vendor/bin/phpcs || fail=1
echo

echo "==> phpstan (level 5)"
vendor/bin/phpstan analyse || fail=1
echo

echo "==> composer audit (known CVEs in dependencies)"
composer audit || fail=1
echo

echo "==> semgrep (SAST: php + security-audit rulesets)"
if command -v semgrep >/dev/null 2>&1; then
    semgrep --config p/php --config p/security-audit \
        public src includes templates || fail=1
else
    echo "semgrep not installed — skipping. Install with: pip3 install semgrep  (or: brew install semgrep)"
fi
echo

if [ "$fail" -ne 0 ]; then
    echo "One or more checks failed."
else
    echo "All checks passed."
fi

exit $fail
