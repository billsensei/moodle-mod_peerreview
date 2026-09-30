#!/usr/bin/env bash
# Purpose: start/stop/check the user-space MariaDB used by the dev Moodle site (port 3307).
# Usage:   scripts/db.sh start|stop|status
# Arguments: one action.  Exit codes: 0 ok, 1 not running / bad argument.
# Example: scripts/db.sh start
set -euo pipefail
R="$HOME/.local/root"; D="$HOME/.local/var/mariadb"
export LD_LIBRARY_PATH="$R/usr/lib/aarch64-linux-gnu"
cli() { "$R/usr/bin/mariadb-admin" --no-defaults -S "$D/mysqld.sock" -uroot "$@"; }
case "${1:-}" in
  start)
    if cli ping >/dev/null 2>&1; then echo "already running"; exit 0; fi
    nohup "$R/usr/sbin/mariadbd" --no-defaults --basedir="$R/usr" --datadir="$D/data" \
      --socket="$D/mysqld.sock" --port=3307 --bind-address=127.0.0.1 \
      --lc-messages-dir="$R/usr/share/mariadb" --character-set-server=utf8mb4 \
      --collation-server=utf8mb4_unicode_ci > "$D/err.log" 2>&1 &
    for _ in $(seq 20); do sleep 1; cli ping >/dev/null 2>&1 && { echo started; exit 0; }; done
    echo "failed, see $D/err.log" >&2; exit 1 ;;
  stop) cli shutdown && echo stopped ;;
  status) cli ping >/dev/null 2>&1 && echo running || { echo "not running"; exit 1; } ;;
  *) echo "usage: $0 start|stop|status" >&2; exit 1 ;;
esac
