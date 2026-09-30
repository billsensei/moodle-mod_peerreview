#!/usr/bin/env bash
# Purpose: put the no-root toolchain on PATH (php, composer, node, moodle-plugin-ci, mariadb).
# Usage:   source scripts/env.sh
# Arguments: none.  Exit codes: 0 always (meant to be sourced).
# Example: source ~/dev/mod_peerreview/scripts/env.sh && php -v
export PATH="$HOME/.local/bin:$HOME/test/moodle-plugin-ci/bin:$PATH"
export MOODLE_DIR="$HOME/test/moodle"
export LD_LIBRARY_PATH="$HOME/.local/root/usr/lib/aarch64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
