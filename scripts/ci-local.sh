#!/usr/bin/env bash
# Purpose: run the full moodle-plugin-ci suite on this plugin locally, in the same order as the GitHub workflow,
#          and print a pass/fail summary. Every check runs even when an earlier one fails.
# Usage:   scripts/ci-local.sh [--no-phpunit] [--no-behat]
# Arguments: --no-phpunit  skip PHPUnit (about 3 minutes on the Pi)
#            --no-behat    skip Behat (needs `scripts/behat.sh start` and an initialised Behat site)
# Environment: needs `source scripts/env.sh` first (moodle-plugin-ci, php, node on PATH), and the database running
#              (`scripts/db.sh start`). PHPUnit needs an initialised PHPUnit site (see docs/COMMANDS.md).
# Warning: do not create or edit files in the plugin while this runs. The grunt check copies the plugin away and
#          mirrors it back afterwards, deleting files that did not exist when it started.
# Exit codes: 0 every check passed, 1 at least one check failed, 2 bad usage.
# Example: source scripts/env.sh && scripts/ci-local.sh --no-behat
set -euo pipefail

plugindir="$(cd "$(dirname "$0")/.." && pwd)"
runphpunit=1
runbehat=1
for arg in "$@"; do
    case "$arg" in
      --no-phpunit) runphpunit=0 ;;
      --no-behat) runbehat=0 ;;
      *) sed -n '2,11p' "$0" >&2; exit 2 ;;
    esac
done

checks=(
    "phplint"
    "phpmd"
    "phpcs --max-warnings 0"
    "phpdoc --max-warnings 0"
    "validate"
    "savepoints"
    "mustache"
    "grunt --max-lint-warnings 0"
)
[ "$runphpunit" = 1 ] && checks+=("phpunit --fail-on-warning")
[ "$runbehat" = 1 ] && checks+=("behat --profile default --auto-rerun 0")

cd "$plugindir"
summary=()
failed=0
for check in "${checks[@]}"; do
    echo "=================== moodle-plugin-ci $check"
    if [[ "$check" == grunt* && -d .git/objects ]]; then
        # grunt restores its backup over the plugin, and git's object files are read-only (restore fails otherwise).
        chmod -R u+w .git/objects
    fi
    set +e
    # shellcheck disable=SC2086 # the check string carries its own options.
    moodle-plugin-ci $check .
    status=$?
    set -e
    if [ "$status" -eq 0 ]; then
        summary+=("PASS  $check")
    else
        summary+=("FAIL  $check (exit $status)")
        failed=1
    fi
done

echo "=================== summary"
printf '%s\n' "${summary[@]}"
exit "$failed"
