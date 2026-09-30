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
Actual output (phase 2): pages 200 with no PHP warnings: view.php, index.php, course/modedit.php, grade/grading/manage.php (area peer)
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
