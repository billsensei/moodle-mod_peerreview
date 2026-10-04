#!/usr/bin/env bash
# Purpose: start/stop/check the dev Moodle site (user-space MariaDB + PHP built-in web server on all interfaces).
# Usage:   scripts/dev-site.sh start|stop|status
# Arguments: one action.  Exit codes: 0 ok, 1 not running / failed / bad argument.
# Environment: PORT (default 8000), WORKERS (default 6; the single-threaded server stalls on the app's parallel requests).
# Note:    wwwroot in config.php must match the address clients use (the LAN address for the Moodle app).
# Example: scripts/dev-site.sh start
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../../.." && pwd)"
PORT="${PORT:-8000}"; WORKERS="${WORKERS:-6}"
PIDFILE="$HOME/.local/var/dev-site.pid"; LOG="$ROOT/../moodle-web.log"
export PATH="$HOME/.local/bin:$PATH"

web_running() { [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; }

case "${1:-}" in
  start)
    "$HERE/db.sh" start
    if web_running; then echo "web already running (pid $(cat "$PIDFILE"))"; else
      PHP_CLI_SERVER_WORKERS="$WORKERS" setsid nohup php -S "0.0.0.0:$PORT" -t "$ROOT" > "$LOG" 2>&1 < /dev/null &
      echo $! > "$PIDFILE"
      sleep 2
      web_running || { echo "web failed, see $LOG" >&2; exit 1; }
    fi
    url="$(grep -oE "wwwroot *= *'[^']+'" "$ROOT/config.php" | head -1 | sed "s/.*'\(.*\)'/\1/")"
    echo "site: $url ($(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/login/index.php"))" ;;
  stop)
    if web_running; then kill "$(cat "$PIDFILE")"; rm -f "$PIDFILE"; echo "web stopped"; else echo "web not running"; fi
    "$HERE/db.sh" stop || true ;;
  status)
    "$HERE/db.sh" status || true
    web_running && echo "web running (pid $(cat "$PIDFILE"))" || { echo "web not running"; exit 1; } ;;
  *) echo "usage: $0 start|stop|status" >&2; exit 1 ;;
esac
