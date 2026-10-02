# CLAUDE.md — mod_peerreview

Moodle activity plugin: lightweight in-class peer assessment. Original spec: `~/test/prompt.md` (phased; all phases 0-9 are done). Now maintained as a beta: release 0.13.0, `MATURITY_BETA`, `requires` 5.0, `supported = [500, 502]` (5.0 to 5.2). See `CHANGES.md` for what each release did and `ROADMAP.md` for ideas (nothing promised).

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
- DB: user-space MariaDB 11.8.6, 127.0.0.1:3307, db/user/pass `moodle`.
- PostgreSQL 17.11 (user space, `scripts/pg.sh init|start|stop`), 127.0.0.1:5433, db/user/pass `moodle`; used only for PHPUnit: `export PEERREVIEW_TEST_DB=pgsql` switches config.php (own phpunit_dataroot_pgsql).
- Java 21 (OpenJDK headless, ~/.local/bin/java) for the mustache lint's HTML validator.
- Full local CI: `scripts/ci-local.sh` (do not edit plugin files while it runs: the grunt step restores a backup of the plugin).
- Behat: headless Chromium (system /usr/bin/chromium) driven directly by chromedriver 154 (~/.local/root/usr/bin, extracted from the chromium-driver .deb), no Selenium/Java. Site: 127.0.0.1:8001, prefix `bht_`, dataroot ~/test/behat_dataroot, fail dumps in its `faildumps/`. Use `scripts/behat.sh start|init|run|stop`. Not available: docker.

## Conventions
- Component `mod_peerreview`, MATURITY_BETA, PHP 8.2+ syntax, XMLDB/DML only, AMD ES6 + Grunt, Mustache for all HTML.
- The local dev site is Moodle 5.0 only; 5.1 and 5.2 are tested in GitHub CI.
- GPL v3 header on every PHP file; full PHPDoc; cite the core file modelled on in a comment.
- Every command run goes in `docs/COMMANDS.md`; helper scripts need header comments and `set -euo pipefail`.
- Small commits, one logical change each.

## Releasing and CI
- Remote: https://github.com/billsensei/moodle-mod_peerreview (HTTPS). `gh` is installed and logged in (token has `workflow` scope, needed to push changes under `.github/workflows/`).
- GitHub CI (`.github/workflows/ci.yml`): Moodle 5.0, 5.1, 5.2 x PHP 8.2/8.3 x MariaDB/PostgreSQL. 5.2 runs on PHP 8.3 only (its composer.lock needs 8.3). Check runs with `gh run list` / `gh run view <id>`.
- After ANY `version.php` change, re-init both test sites before `scripts/ci-local.sh`: `php admin/tool/phpunit/cli/init.php`, then `mod/peerreview/scripts/behat.sh start && ... init` (run the helper scripts from `mod/peerreview`, they are not in the Moodle root). Run `source scripts/env.sh` first or every check fails with exit 127.
- `$plugin->supported` is a range of exactly two values `[min, max]`, not a list.
- Release steps: update `CHANGES.md` and `version.php`, `scripts/ci-local.sh` (all 10 PASS), commit, push, **wait for GitHub CI to pass**, then tag `vX.Y.Z` and `gh release create` as a pre-release with a zip built by `git archive --format=zip --prefix=peerreview/ -o ~/test/mod_peerreview_X.Y.Z.zip vX.Y.Z`. Never tag or publish before CI is green (0.11.0 shipped broken that way and was withdrawn).
- Before acting on an old `learn.md` entry, check the code and `git log -S`: the log can lag behind the repo.
