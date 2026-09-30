# Commands

## Load the toolchain
Purpose: put php, composer, node, moodle-plugin-ci on PATH
Run from: any
Command:
    source ~/dev/mod_peerreview/scripts/env.sh
Expected output (summary): none
Actual output (phase 0): none
If it fails: check ~/.local/bin exists.

## Start the dev database
Purpose: MariaDB is a user process, not a service; start it after reboot
Run from: ~/dev/mod_peerreview
Command:
    scripts/db.sh start
Expected output (summary): `started` or `already running`
Actual output (phase 0): started
If it fails: read ~/.local/var/mariadb/err.log (port 3307 in use, or stale socket file).

## Check versions
Purpose: confirm toolchain
Run from: any (after env.sh)
Command:
    php -v; composer --version; node -v; moodle-plugin-ci --version
Expected output (summary): PHP 8.4.x, Composer 2.x, v22.x, 4.5.x
Actual output (phase 0): PHP 8.4.26, Composer 2.10.3, v22.23.3, Moodle Plugin CI 4.5.11
If it fails: env.sh not sourced.

## Install Moodle dependencies
Purpose: vendor/ (phpunit, behat) for Moodle core and moodle-plugin-ci
Run from: ~/test/moodle and ~/test/moodle-plugin-ci
Command:
    composer install --no-interaction
Expected output (summary): "Generating autoload files"
Actual output (phase 0): success in both
If it fails: missing PHP extension; check `php -m`.

## Install the Moodle site
Purpose: create dev site (one-off)
Run from: ~/test/moodle
Command:
    php admin/cli/install.php --lang=en --wwwroot=http://localhost:8000 --dataroot=$HOME/test/moodledata --dbtype=mariadb --dbhost=127.0.0.1 --dbport=3307 --dbname=moodle --dbuser=moodle --dbpass=moodle --fullname="PeerReview Dev" --shortname=dev --adminuser=admin --adminpass='Admin123!' --adminemail=wrwjpn@gmail.com --agree-license --non-interactive
Expected output (summary): "Installation completed successfully."
Actual output (phase 0): Installation completed successfully.
If it fails: DB not started, or config.php already exists.

## Plugin name collision check
Purpose: ensure `mod_peerreview` is free
Run from: any
Command:
    curl -s https://download.moodle.org/api/1.3/pluglist.php | grep -o '"component":"[^"]*peer[^"]*"' | sort -u
Expected output (summary): no `mod_peerreview`
Actual output (phase 0): "component":"mod_peerwork" (different plugin; name is free)
If it fails: network.

## Register the Moodle coding standard with phpcs (one-off)
Purpose: moodle-plugin-ci's phpcs failed with `the "moodle" coding standard is not installed`
Run from: ~/test/moodle-plugin-ci/vendor
Command:
    php squizlabs/php_codesniffer/bin/phpcs --config-set installed_paths "$(pwd)/moodlehq/moodle-cs,$(pwd)/phpcsstandards/phpcsextra,$(pwd)/phpcsstandards/phpcsutils"
Expected output (summary): `Config value "installed_paths" added successfully`; `phpcs -i` then lists moodle, moodle-extra
Actual output (phase 2): as expected
If it fails: composer install not run in moodle-plugin-ci.

## Install / upgrade the plugin on the dev site
Purpose: run install.xml and register capabilities
Run from: ~/test/moodle
Command:
    php admin/cli/upgrade.php --non-interactive
Expected output (summary): `-->mod_peerreview` then `++ Success ++`, ending "completed successfully"
Actual output (phase 2): `-->mod_peerreview ++ Success (0.55 seconds) ++`
If it fails: syntax error in install.xml/access.php (run `php -l`), or DB not started (`scripts/db.sh start`).

## Create the test course
Purpose: course PR101 with teacher1, student1..N and one peer review activity (cmid 5 on the current dev site)
Run from: ~/test/moodle/mod/peerreview
Command:
    source scripts/env.sh && php scripts/dev-create-test-course.php 6
Expected output (summary): `Created course 5, cmid 5, 6 students (password Test123!)`
Actual output (phase 2): as expected (after fixing `module` id, see learn.md)
If it fails: "Course PR101 already exists" — delete the course first (half-created courses remain after an error).

## Serve the dev site
Purpose: browse http://localhost:8000 (admin / Admin123!, teacher1 / Test123!, student1 / Test123!)
Run from: ~/test/moodle
Command:
    php -S localhost:8000 -t ~/test/moodle
Expected output (summary): server log lines per request; stop with Ctrl-C (or kill by PID)
Actual output (phase 2): pages 200 with no PHP warnings: view.php, index.php, course/modedit.php, grade/grading/manage.php (area peer, renamed to received in phase 4)
If it fails: port in use; `php` not on PATH (source scripts/env.sh).

## Code-quality checks
Purpose: the moodle-plugin-ci checks that apply at this stage
Run from: ~/test/moodle/mod/peerreview
Command:
    moodle-plugin-ci phplint . ; moodle-plugin-ci validate . ; moodle-plugin-ci phpcs --max-warnings 0 . ; moodle-plugin-ci phpdoc --max-warnings 0 . ; moodle-plugin-ci phpmd . ; moodle-plugin-ci phpcbf .
Expected output (summary): exit 0 each. (Run without `--moodle`; the plugin-ci finds Moodle from the plugin path.)
Actual output (phase 2): phplint/validate/phpcs/phpdoc exit 0; phpmd exit 0 but lists 8 violations (parameters that Moodle callback signatures force, unused until later phases)
If it fails: `phpcbf` fixes most phpcs formatting; Moodle CS rejects `@SuppressWarnings` tags, so do not use them.

## Build the PHPUnit environment (one-off, slow: ~10 minutes on a Raspberry Pi)
Purpose: install a separate Moodle test site (prefix phpu_) and create phpunit.xml
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php admin/tool/phpunit/cli/init.php
Expected output (summary): a long install log ending without errors; `phpunit.xml` exists in ~/test/moodle. Needs in config.php: `$CFG->phpunit_prefix = 'phpu_'; $CFG->phpunit_dataroot = '/home/bill/test/phpunit_dataroot';`
Actual output (phase 3): success after building the en_AU.UTF-8 locale (see learn.md)
If it fails: "Required locale 'en_AU.UTF-8' is not installed" → `localedef -i en_AU -f UTF-8 ~/.local/locale/en_AU.UTF-8` (env.sh sets LOCPATH).

## Run the plugin's PHPUnit tests
Purpose: run all mod_peerreview tests
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php vendor/bin/phpunit --testsuite mod_peerreview_testsuite
Expected output (summary): `OK (N tests, M assertions)`
Actual output (phase 3): OK (48 tests, 29927 assertions), ~25 s
If it fails: "No tests executed" → after adding new test files run `php admin/tool/phpunit/cli/util.php --buildconfig` first (and `--testsuite`, not a path, is required); after changing install.xml/version.php run `php admin/tool/phpunit/cli/init.php` again.

## Exercise the teacher pages without a browser
Purpose: log in and call allocate.php (preview, confirm, delete, CSV)
Run from: ~/test/moodle/mod/peerreview
Command:
    scripts/dev-web.sh login admin 'Admin123!' && scripts/dev-web.sh get "mod/peerreview/allocate.php?id=5"
Expected output (summary): `login 303` then the page HTML. Forms need the sesskey (from `"sesskey":"..."` in the page) and the hidden `_qf__mod_peerreview_form_allocate_form=1`.
Actual output (phase 3): overview, random preview/confirm, delete with started-review warning, CSV upload/preview/confirm and export all behaved as expected; a student got "Sorry, but you do not currently have permissions"
If it fails: server not running (`php -S localhost:8000 -t ~/test/moodle &`).

## Stress the random top-up (throwaway check)
Purpose: measure legality, minimum reviews and spread over 1500 messy scenarios
Run from: ~/test/moodle
Command:
    php <scratch>/stress.php   (script not kept; the same checks live in random_allocator_test::test_messy_top_ups_stay_legal_and_bounded)
Expected output (summary): illegal=0, short=0, over=0
Actual output (phase 3): illegal 0, short 0, over 0; dropped-pair warnings 2/1500; spread>1 in 22/1500 (always due to pre-existing manual pairs)
If it fails: n/a.

## Mustache lint (limited here)
Purpose: lint templates
Run from: ~/test/moodle/mod/peerreview
Command:
    moodle-plugin-ci mustache .
Expected output (summary): no warnings. Locally it prints "Problem calling HTML validator" because Java is not installed, so only the ESLint-style checks run.
Actual output (phase 3): HTML validator unavailable (no Java); GitHub Actions in Phase 9 will run it fully
If it fails: install a JRE (needs root, or extract a JRE deb into ~/.local/root).

## Choose the grading method of a dev activity
Purpose: give the test activity a rubric, a marking guide, or none (simple points and comment)
Run from: ~/test/moodle/mod/peerreview
Command:
    source scripts/env.sh && php scripts/dev-set-grading-method.php 5 rubric    # or guide, or none
Expected output (summary): `rubric set for cmid 5 (definition status ready)`
Actual output (phase 4): rubric, guide and none all worked; the review form rendered correctly for each
If it fails: "Call to undefined function file_postupdate_standard_editor" means filelib.php was not loaded (already required in the script).

## Try the review form over HTTP
Purpose: draft, submit, edit and close-window checks without a browser (students reach review.php by URL until Phase 5 adds the cards)
Run from: ~/test/moodle/mod/peerreview
Command:
    scripts/dev-web.sh login student1 'Test123!'
    scripts/dev-web.sh get "mod/peerreview/review.php?id=5&alloc=9"
Expected output (summary): the form with the rubric/guide (or score box). Post fields: `id`, `alloc`, `advancedgradinginstanceid`, `advancedgrading[criteria][CID][levelid|score|remark]`, `feedback_editor[text|format]`, `sesskey`, `_qf__mod_peerreview_form_review_form=1`, and `savedraft` or `submitreview`.
Actual output (phase 4): rubric draft with a half-filled form saved (status 1, no grade); incomplete submit refused; complete submit graded 88.89 (8/9); edit gave a new active instance (55.56) and archived the old one; after closing, page read-only and edits refused; teacher (admin) sees the review read-only; guide 15+5 of 30 gave 66.67; simple form accepted 72.5 and refused empty/150
If it fails: allocation ids belong to specific students (a student opening someone else's gets a permissions error); purge caches (`php admin/cli/purge_caches.php`) after changing lang strings.

## Install the Node tools for Grunt (one-off)
Purpose: build AMD JavaScript (`amd/build/*.min.js`) and run ESLint
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && npm ci --no-audit --no-fund
Expected output (summary): `added 1164 packages` (about a minute)
Actual output (phase 5): added 1164 packages in 45s
If it fails: wrong Node version (Moodle 5.0 needs 22.x; env.sh puts ~/.local/node first on PATH).

## Build the plugin's JavaScript
Purpose: turn amd/src/*.js into amd/build/*.min.js and lint it
Run from: ~/test/moodle/mod/peerreview
Command:
    source scripts/env.sh && npx grunt amd
Expected output (summary): `Running "eslint:amd"`, `Running "rollup:dist"`, `Done.`; amd/build/progress.min.js(.map) updated. Commit the build files.
Actual output (phase 5): Done, no lint errors
If it fails: run `npm ci` in ~/test/moodle first.

## Try the teacher pages, live progress and release over HTTP
Purpose: check view/report/drill-down/release/export and the web services without a browser
Run from: ~/test/moodle/mod/peerreview
Command:
    scripts/dev-web.sh login admin 'Admin123!'
    scripts/dev-web.sh get "mod/peerreview/report.php?id=5"
    scripts/dev-web.sh get "mod/peerreview/export.php?id=5&dataformat=csv"
    (web services: POST JSON to /lib/ajax/service.php?sesskey=SK&info=mod_peerreview_get_progress with body
     [{"index":0,"methodname":"mod_peerreview_get_progress","args":{"cmid":5,"groupid":0}}])
Expected output (summary): pages without PHP notices; CSV header Reviewer,Reviewee,Status,Grade,... plus one Score/Remark column pair per rubric or guide criterion
Actual output (phase 5): all worked; students refused by both web services; release/hide need a valid sesskey (a request without it changed nothing)
If it fails: after editing lang strings run `php admin/cli/purge_caches.php`; after adding web services bump version.php and run `php admin/cli/upgrade.php --non-interactive`, then re-run the PHPUnit init.

## Push grades, override and revert (dev site, over HTTP)
Purpose: check the gradebook path end to end
Run from: ~/test/moodle/mod/peerreview
Command:
    scripts/dev-web.sh login admin 'Admin123!'
    scripts/dev-web.sh post "mod/peerreview/action.php" id=5 action=pushgrades returnto=report sesskey=SK
    scripts/dev-web.sh post "mod/peerreview/report.php?id=5&user=UID" id=5 user=UID overridegrade=90 overridenote=why sesskey=SK _qf__mod_peerreview_form_override_form=1 submitbutton=Save
    scripts/dev-web.sh post "mod/peerreview/action.php" id=5 action=revertoverride user=UID returnto=report sesskey=SK
Expected output (summary): "Grades were sent to the gradebook." / "Override saved..." / "Override removed..."; `grade_grades` shows the peer mean, then 90, then the mean again; students with no counted review are NULL
Actual output (phase 6): student2 66.67 -> 90 -> 66.67; student1/3/6 NULL; participation student1 20/20; a student pushing got "Sorry, but you do not currently have permissions (Override received grades)"
If it fails: the SK (sesskey) must come from a page fetched in the same login session.

## Re-initialise PHPUnit after a version bump
Purpose: PHPUnit refuses to run when version.php changed ("initialised for different version")
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php admin/tool/phpunit/cli/init.php
Expected output (summary): ends with "PHPUnit test environment setup complete." after about 10 minutes
Actual output (phase 6): success (run in the background)
If it fails: see the phase 3 entry (locale) in this file.

## Phase 7 PHPUnit: privacy, backup/restore, events
Purpose: run the new tests (privacy provider, backup→restore round trip with and without user data, every event)
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php admin/tool/phpunit/cli/util.php --buildconfig
    php vendor/bin/phpunit --testsuite mod_peerreview_testsuite --filter 'provider_test|backup_restore_test|events_test'
    php vendor/bin/phpunit --testsuite mod_peerreview_testsuite
Expected output (summary): OK, no notices or deprecations
Actual output (phase 7): OK (24 tests, 180 assertions); whole suite OK (130 tests, 30351 assertions)
If it fails: "Unexpected debugging() call" names a missing lang string or a missing event mapping; add `--display-notices` to see it. `@covers` in a docblock shows up as a PHPUnit deprecation; use `#[CoversClass]`.

## Core privacy compliance check for the plugin
Purpose: core's check that the provider implements the right interfaces and every metadata string exists
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php vendor/bin/phpunit privacy/tests/privacy/provider_test.php --filter mod_peerreview
Expected output (summary): 4 tests OK
Actual output (phase 7): Tests: 4, Assertions: 64 (5 PHPUnit deprecations come from core's test file itself)
If it fails: a `privacy:metadata:*` string is missing from lang/en/peerreview.php.

## Backup and restore on the dev site (CLI)
Purpose: real backup of the test course with user data, restored as a new course, then compared
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php admin/cli/purge_caches.php
    php admin/cli/backup.php --courseid=5 --destination=/tmp/peerreview-backup
    php admin/cli/restore_backup.php --file=/tmp/peerreview-backup/backup-moodle2-course-5-....mbz --categoryid=1
    php mod/peerreview/scripts/dev-show-state.php
    mod/peerreview/scripts/dev-web.sh login admin 'Admin123!'
    mod/peerreview/scripts/dev-web.sh get "mod/peerreview/review.php?id=NEWCMID&alloc=NEWALLOCID"
Expected output (summary): "Backup completed." and "== Restored course ID: N =="; the restored activity has the same counts; a restored guide review shows the same scores and remarks as the original
Actual output (phase 7): restored course 6: allocs 12 submitted 3 overrides 0 gradinginst 3 method guide, same as course 5; review 13 (original) and 25 (restored) have identical text (15/20, 5/10, remarks); view, report, review and log pages had no debugging output
If it fails: "backup not supported" → FEATURE_BACKUP_MOODLE2 missing in peerreview_supports(); a restored grading instance with itemid 0 → the restore step did not set the grading_item_received mapping.

## Install chromedriver without root
Purpose: WebDriver for Behat's JavaScript scenarios, matching the system Chromium
Run from: any directory
Command:
    apt-get download chromium-driver
    dpkg-deb -x chromium-driver_*.deb ~/.local/root
    ~/.local/root/usr/bin/chromedriver --version
Expected output (summary): the version equals `chromium --version`
Actual output (phase 8): ChromeDriver 154.0.8037.57 (Chromium 1:154.0.8037.57-1~deb13u1+rpt1 is installed)
If it fails: after a Chromium upgrade the versions differ ("session not created: This version of ChromeDriver only supports..."); download and extract the matching chromium-driver again.

## Behat settings in config.php
Purpose: a separate Behat site and a headless Chromium profile that talks to chromedriver directly (no Selenium)
Run from: ~/test/moodle (edit config.php)
Command:
    $CFG->behat_prefix = 'bht_';
    $CFG->behat_dataroot = '/home/bill/test/behat_dataroot';
    $CFG->behat_wwwroot = 'http://127.0.0.1:8001';
    $CFG->behat_faildump_path = '/home/bill/test/behat_dataroot/faildumps';
    $CFG->behat_increasetimeout = 2;
    $CFG->behat_profiles = ['default' => ['browser' => 'chrome', 'wd_host' => 'http://127.0.0.1:9515',
        'capabilities' => ['extra_capabilities' => ['goog:chromeOptions' => ['binary' => '/usr/bin/chromium',
        'args' => ['headless=new', 'no-sandbox', 'disable-dev-shm-usage', 'disable-gpu', 'window-size=1366,768']]]]]];
Expected output (summary): `php -l config.php` reports no syntax errors
Actual output (phase 8): no syntax errors
If it fails: behat_wwwroot must differ from wwwroot; after changing any behat setting run `php admin/tool/behat/cli/util.php --enable`.

## Run Behat (scripts/behat.sh)
Purpose: start the services, install the Behat site once, run the plugin's features
Run from: ~/test/moodle/mod/peerreview
Command:
    source scripts/env.sh && scripts/behat.sh start
    scripts/behat.sh init          # once, and again after version.php or install.xml changes (~10 min on the Pi)
    scripts/behat.sh run           # add --format=pretty to see each step
    scripts/behat.sh stop
Expected output (summary): "Acceptance tests environment enabled on http://127.0.0.1:8001"; then "1 scenario (1 passed)"
Actual output (phase 8): init exit 0; run "1 scenario (1 passed) 63 steps (63 passed)" in about 2 minutes, 3 runs in a row
If it fails: read the HTML and PNG in ~/test/behat_dataroot/faildumps/<time>/; "not ready after 10 seconds" → raise behat_increasetimeout; "Could not open connection" → `scripts/behat.sh status`, then start.

## Full local CI (scripts/ci-local.sh)
Purpose: every moodle-plugin-ci check, in the same order as .github/workflows/ci.yml, with a pass/fail summary
Run from: ~/test/moodle/mod/peerreview
Command:
    source scripts/env.sh && scripts/db.sh start && scripts/behat.sh start
    scripts/ci-local.sh                 # or --no-phpunit / --no-behat
Expected output (summary): the summary lists PASS for phplint, phpmd, phpcs, phpdoc, validate, savepoints, mustache, grunt, phpunit, behat
Actual output (phase 9, first run): 8 PASS; mustache and grunt FAIL from the environment (no working Java; grunt could not restore read-only .git objects). Both were fixed (entries below). The final run is at the end of this file.
If it fails: do not edit files in the plugin during the run (grunt restores a backup of the plugin and deletes newer files). phpmd always exits 0 and lists advisory violations; see DESIGN.md section 15.

## Java for the mustache HTML validator (no root)
Purpose: `moodle-plugin-ci mustache` runs `java -jar vnu.jar` on every rendered template
Run from: any directory
Command:
    apt-get download openjdk-21-jre-headless && dpkg-deb -x openjdk-21-jre-headless_*.deb ~/.local/root
    J=~/.local/root/usr/lib/jvm/java-21-openjdk-arm64
    find $J -type l -lname '/etc/*' | while read -r l; do t=$(readlink "$l"); [ -e "$HOME/.local/root$t" ] && ln -sfn "$HOME/.local/root$t" "$l"; done
    ln -sf $J/bin/java ~/.local/bin/java
Expected output (summary): `java -version` prints openjdk 21; the mustache lint says "OK: Mustache rendered html succesfully" for each template
Actual output (phase 9): openjdk version "21.0.12.1"; all 7 templates OK
If it fails: "Error loading java.security file" → the /etc symlinks were not repointed; "Problem calling HTML validator" → java is not on PATH (`source scripts/env.sh`).

## PostgreSQL for PHPUnit (no root)
Purpose: run the plugin's PHPUnit tests on PostgreSQL as well as MariaDB
Run from: ~/test/moodle
Command:
    apt-get download postgresql-17 postgresql-client-17 libpq5 && for d in *.deb; do dpkg-deb -x $d ~/.local/root; done
    mod/peerreview/scripts/pg.sh init          # once; later: scripts/pg.sh start
    export PEERREVIEW_TEST_DB=pgsql            # config.php switches to PostgreSQL only in this shell
    php admin/tool/phpunit/cli/init.php
    php vendor/bin/phpunit --testsuite mod_peerreview_testsuite
Expected output (summary): the PHPUnit header shows "pgsql: 17.x"; OK
Actual output (phase 9): "Php: 8.4.26, pgsql: 17.11 (Debian 17.11-0+deb13u1)"; OK (130 tests, 30351 assertions) in 1:20
If it fails: "connection refused" → `scripts/pg.sh start`; tests on MariaDB again → open a new shell (unset PEERREVIEW_TEST_DB).

## Upgrade the dev site after the beta bump
Purpose: install version 2026093003 (MATURITY_BETA, release 0.9.0) on the dev site
Run from: ~/test/moodle
Command:
    source mod/peerreview/scripts/env.sh && php admin/cli/upgrade.php --non-interactive && php admin/cli/purge_caches.php
Expected output (summary): "-->mod_peerreview ++ 2026093003: Success" and "completed successfully"
Actual output (phase 9): as expected; "Command line upgrade from 5.0.10+ (Build: 20260928) (2025041410.01) to 5.0.10+ ... completed successfully."
If it fails: after the bump the PHPUnit and Behat sites must be initialised again (`php admin/tool/phpunit/cli/init.php`, `scripts/behat.sh init`).
