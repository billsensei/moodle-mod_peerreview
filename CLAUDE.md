# CLAUDE.md — mod_peerreview

Moodle 5.0 activity plugin: lightweight in-class peer assessment. Spec: `~/test/prompt.md` (phased; stop after each phase for approval).

## Paths
- Plugin repo: **`~/test/moodle/mod/peerreview`** (the real directory; `~/dev/mod_peerreview` is a convenience symlink to it). It must NOT be the other way round: entry scripts do `require('../../config.php')`, which breaks when the plugin is only a symlink into dirroot. Never edit Moodle core.
- Moodle 5.0.10+ (version 2025041410.01, branch 500, classic layout, no /public): `~/test/moodle` (dirroot; `config.php` there)
- Moodledata: `~/test/moodledata`. Site wwwroot `http://localhost:8000` (serve with `php -S localhost:8000 -t ~/test/moodle`). Admin: admin / Admin123! (dev only).
- moodle-plugin-ci 4.5.11: `~/test/moodle-plugin-ci/bin/moodle-plugin-ci`
- momopda is NOT a CLI: `~/test/momopda/` is a prompt collection (AGENTS.md, .prompts/). Read `.prompts/plugins/mod.md`, `mod_patterns.md`, `core/*` and hand-write the skeleton.
- Error log for lessons: `~/test/learn.md` (log every error + fix).

## Toolchain (no root; installed under ~/.local)
- Every session: `source scripts/env.sh`, then `scripts/db.sh start`.
- PHP 8.4.26 (extracted .debs in ~/.local/root, wrapper ~/.local/bin/php), Composer 2.10.3, Node 22.23.3 (Moodle needs >=22.11 <23).
- DB: user-space MariaDB 11.8.6, 127.0.0.1:3307, db/user/pass `moodle`. PostgreSQL not installed (CI matrix covers it in Phase 9).
- Not available: docker, Selenium/chromedriver, Java (Behat needs these — decide in Phase 8).

## Conventions
- Component `mod_peerreview`, MATURITY_ALPHA until Phase 9, PHP 8.2+ syntax, XMLDB/DML only, AMD ES6 + Grunt, Mustache for all HTML.
- GPL v3 header on every PHP file; full PHPDoc; cite the core file modelled on in a comment.
- Every command run goes in `docs/COMMANDS.md`; helper scripts need header comments and `set -euo pipefail`.
- Small commits, one logical change each.
