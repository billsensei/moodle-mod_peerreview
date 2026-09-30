#!/usr/bin/env bash
# Purpose: run Moodle Behat for mod_peerreview without Selenium or Java: headless Chromium is driven directly by
#          chromedriver (W3C), and the Behat site is served by PHP's built-in web server with several workers.
# Usage:   scripts/behat.sh start            start the web server (127.0.0.1:8001) and chromedriver (127.0.0.1:9515)
#          scripts/behat.sh stop             stop both
#          scripts/behat.sh init             (re)install the Behat test site (bht_ tables, ~/test/behat_dataroot)
#          scripts/behat.sh run [ARGS...]    run the plugin's features; extra ARGS go to behat (e.g. --name="...")
#          scripts/behat.sh status           show whether the two services answer
# Arguments: see Usage. Needs config.php settings behat_prefix, behat_dataroot, behat_wwwroot, behat_profiles.
# Environment: MOODLE_DIR (default ~/test/moodle); LOGDIR (default ~/test/behat_dataroot/logs).
# Exit codes: 0 success (run: all scenarios passed), 1 bad usage or a service did not start, other: behat's exit code.
# Example: source scripts/env.sh && scripts/behat.sh start && scripts/behat.sh init && scripts/behat.sh run
set -euo pipefail

MOODLE_DIR="${MOODLE_DIR:-$HOME/test/moodle}"
LOGDIR="${LOGDIR:-$HOME/test/behat_dataroot/logs}"
CHROMEDRIVER="$HOME/.local/root/usr/bin/chromedriver"
WEB_PORT=8001
DRIVER_PORT=9515

# Wait until a URL answers (any HTTP status), at most about 20 seconds.
wait_for() {
    local url="$1" i
    for i in $(seq 1 40); do
        if curl -s -o /dev/null "$url"; then
            return 0
        fi
        sleep 0.5
    done
    echo "no answer from $url" >&2
    return 1
}

action="${1:-}"
case "$action" in
  start)
    mkdir -p "$LOGDIR"
    if ! curl -s -o /dev/null "http://127.0.0.1:$WEB_PORT/"; then
        # Several workers, otherwise one slow AJAX request blocks the page that is waiting for it.
        PHP_CLI_SERVER_WORKERS=4 nohup php -S "127.0.0.1:$WEB_PORT" -t "$MOODLE_DIR" > "$LOGDIR/webserver.log" 2>&1 &
    fi
    if ! curl -s -o /dev/null "http://127.0.0.1:$DRIVER_PORT/status"; then
        nohup "$CHROMEDRIVER" --port="$DRIVER_PORT" > "$LOGDIR/chromedriver.log" 2>&1 &
    fi
    wait_for "http://127.0.0.1:$WEB_PORT/" && wait_for "http://127.0.0.1:$DRIVER_PORT/status" || exit 1
    echo "web server :$WEB_PORT and chromedriver :$DRIVER_PORT are up"
    ;;
  stop)
    pkill -f "php -S 127.0.0.1:$WEB_PORT" || true
    pkill -f "chromedriver --port=$DRIVER_PORT" || true
    echo "stopped"
    ;;
  status)
    curl -s -o /dev/null -w "web server :$WEB_PORT -> %{http_code}\n" "http://127.0.0.1:$WEB_PORT/" || echo "web server down"
    curl -s "http://127.0.0.1:$DRIVER_PORT/status" | head -c 200 || echo "chromedriver down"
    echo
    ;;
  init)
    cd "$MOODLE_DIR"
    php admin/tool/behat/cli/init.php
    ;;
  run)
    shift
    cd "$MOODLE_DIR"
    config="$HOME/test/behat_dataroot/behatrun/behat/behat.yml"   # written by "init"
    set +e
    vendor/bin/behat --config "$config" --tags=@mod_peerreview --format=progress "$@"
    status=$?
    set -e
    exit "$status"
    ;;
  *)
    sed -n '2,11p' "$0" >&2
    exit 1
    ;;
esac
