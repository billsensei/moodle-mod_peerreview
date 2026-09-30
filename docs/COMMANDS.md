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
