#!/usr/bin/env bash
# Purpose: log in to the dev site and make GET/POST requests with curl (for checking pages without a browser).
# Usage:   scripts/dev-web.sh login USER [PASS]    (without PASS: PEERREVIEW_PASS env var, else prompted)
#          scripts/dev-web.sh get PATH
#          scripts/dev-web.sh post PATH FIELD=VALUE...      (values are URL-encoded; repeat FIELD[]=v for arrays)
# Arguments: PATH is relative to the site root, e.g. "mod/peerreview/allocate.php?id=5".
# Environment: BASE (default http://localhost:8000), JAR (cookie file, default /tmp/peerreview-cookies).
# Exit codes: 0 ok, 1 bad usage. curl errors pass through.
# Example: scripts/dev-web.sh login admin 'Admin123!' && scripts/dev-web.sh get "mod/peerreview/allocate.php?id=5"
set -euo pipefail
BASE="${BASE:-http://localhost:8000}"
JAR="${JAR:-/tmp/peerreview-cookies}"
action="${1:-}"
case "$action" in
  login)
    [ $# -ge 2 ] && [ $# -le 3 ] || { echo "usage: $0 login USER [PASS]" >&2; exit 1; }
    pass="${3:-${PEERREVIEW_PASS:-}}"
    [ -n "$pass" ] || { read -r -s -p "Password: " pass; echo >&2; }
    passfile=$(mktemp)
    trap 'rm -f "$passfile"' EXIT
    printf '%s' "$pass" > "$passfile"  # Keeps the password out of the curl command line (visible in ps).
    rm -f "$JAR"
    token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
    curl -s -c "$JAR" -b "$JAR" -o /dev/null -w "login %{http_code}\n" \
      --data-urlencode "username=$2" --data-urlencode "password@$passfile" --data-urlencode "logintoken=$token" "$BASE/login/index.php"
    ;;
  get)
    [ $# -eq 2 ] || { echo "usage: $0 get PATH" >&2; exit 1; }
    curl -s -b "$JAR" -c "$JAR" -L "$BASE/$2"
    ;;
  post)
    [ $# -ge 2 ] || { echo "usage: $0 post PATH FIELD=VALUE..." >&2; exit 1; }
    path="$2"; shift 2; args=()
    for field in "$@"; do args+=(--data-urlencode "$field"); done
    curl -s -b "$JAR" -c "$JAR" -L "${args[@]}" "$BASE/$path"
    ;;
  *) echo "usage: $0 login|get|post ..." >&2; exit 1 ;;
esac
