#!/usr/bin/env bash
# Purpose: init/start/stop/check a user-space PostgreSQL 17 (port 5433) used to run PHPUnit on PostgreSQL.
#          config.php switches to it when PEERREVIEW_TEST_DB=pgsql is set. Binaries come from the extracted
#          postgresql-17 .deb in ~/.local/root.
# Usage:   scripts/pg.sh init|start|stop|status
# Arguments: one action. init creates the cluster (once), the role "moodle" (password "moodle") and database "moodle".
# Exit codes: 0 ok, 1 not running / failed / bad argument.
# Example: scripts/pg.sh init && scripts/pg.sh start
set -euo pipefail
R="$HOME/.local/root"; B="$R/usr/lib/postgresql/17/bin"; D="$HOME/.local/var/postgresql"
export LD_LIBRARY_PATH="$R/usr/lib/aarch64-linux-gnu"
ready() { "$B/pg_isready" -h 127.0.0.1 -p 5433 >/dev/null 2>&1; }
case "${1:-}" in
  init)
    [ -d "$D/data" ] && { echo "already initialised"; exit 0; }
    mkdir -p "$D"
    "$B/initdb" -D "$D/data" -U postgres --auth=trust --encoding=UTF8 --locale=C.UTF-8 > "$D/initdb.log"
    "$0" start
    "$B/psql" -h 127.0.0.1 -p 5433 -U postgres -qc "CREATE ROLE moodle LOGIN PASSWORD 'moodle' CREATEDB"
    "$B/psql" -h 127.0.0.1 -p 5433 -U postgres -qc "CREATE DATABASE moodle OWNER moodle ENCODING 'UTF8'"
    echo "initialised" ;;
  start)
    if ready; then echo "already running"; exit 0; fi
    "$B/pg_ctl" -D "$D/data" -l "$D/server.log" -w \
      -o "-p 5433 -k $D -c listen_addresses=127.0.0.1" start >/dev/null
    ready && echo started || { echo "failed, see $D/server.log" >&2; exit 1; } ;;
  stop) "$B/pg_ctl" -D "$D/data" -m fast stop >/dev/null && echo stopped ;;
  status) ready && echo running || { echo "not running"; exit 1; } ;;
  *) echo "usage: $0 init|start|stop|status" >&2; exit 1 ;;
esac
