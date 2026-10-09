# Peer review: install and upgrade (site administrators)

## Requirements

- Moodle **5.0** (version 2025041400 or later). This release is tested with 5.0 only.
- PHP 8.2 or 8.3 (Moodle 5.2 needs PHP 8.3 or later) and a database Moodle 5.0 supports (tested: MariaDB 11, PostgreSQL 17).
- The Moodle cron must run for the optional automatic reminder before the close date (scheduled task *Send automatic peer review reminders*, hourly).
- For the **Moodle app**: *Site administration → Mobile app → Mobile settings* must have **Enable web services for mobile devices** on (it is off by default on a new site). The plugin adds the activity page to the app by itself; nothing else needs setting up. Students get the app page for the simple points or scale form; rubric and marking guide reviews open in the browser.
- Command-line access to the server is recommended; the web installer works too.

## Before you start

- Take a backup of the Moodle database and the Moodle code directory.
- Consider turning on maintenance mode for upgrades on a busy site:
  `php admin/cli/maintenance.php --enable` (and `--disable` afterwards).

## Install

1. Put the plugin code in `mod/peerreview` inside the Moodle code directory (the directory that contains `config.php`). Either
   - unzip the release so that the file `mod/peerreview/version.php` exists, or
   - clone the repository: `git clone <repository URL> mod/peerreview`.

   Make sure the web server user can read the files.

2. Run the upgrade from the Moodle directory, as the web server user (for example `sudo -u www-data`):

   ```
   php admin/cli/upgrade.php --non-interactive
   ```

   Expected output (the version numbers depend on your site):

   ```
   -->mod_peerreview
   ++ Success (0.21 seconds) ++
   ...
   Command line upgrade from 5.0.10+ (Build: 20260928) (2025041410.01) to 5.0.10+ (Build: 20260928) (2025041410.01) completed successfully.
   ```

   **Without command-line access:** log in as an administrator and go to *Site administration → Notifications*, check that "Peer review" is listed as "To be installed", and press **Upgrade Moodle database now**.

3. Purge the caches:

   ```
   php admin/cli/purge_caches.php
   ```

   No output means success.

4. Check: *Site administration → Plugins → Plugins overview* lists **Peer review** (mod_peerreview) with version 2026101000 and release 0.19.0.

## Upgrade to a newer release

1. Back up (see above).
2. Replace the contents of `mod/peerreview` with the new release (or `git pull` in that directory). Do not keep old files next to the new ones.
3. Run `php admin/cli/upgrade.php --non-interactive` and `php admin/cli/purge_caches.php` as above. The output lists `-->mod_peerreview` and the new version, for example:

   ```
   -->mod_peerreview
   ++ 2026093003: Success (0.07 seconds) ++
   ++ Success (0.21 seconds) ++
   ```

## Permissions

Default capabilities ("Teachers" means both Teacher and Non-editing teacher; change them under *Site administration → Users → Permissions → Define roles*, or per course/activity):

| Capability | Default roles | What it allows |
|---|---|---|
| mod/peerreview:addinstance | Manager, Editing teacher | Add the activity to a course |
| mod/peerreview:view | Guest, Student, Teachers, Manager | Open the activity |
| mod/peerreview:review | Student | Write reviews |
| mod/peerreview:allocate | Manager, Teachers | Decide who reviews whom |
| mod/peerreview:viewallreviews | Manager, Teachers | See every review and **reviewer names** |
| mod/peerreview:releasefeedback | Manager, Teachers | Release or hide feedback |
| mod/peerreview:overridegrade | Manager, Editing teacher | Override grades, push grades to the gradebook |
| mod/peerreview:export | Manager, Editing teacher | Download all reviews |

## Privacy, backup, logs

- The plugin implements the Privacy API: data requests in *Site administration → Users → Privacy and policies* include reviews given and received (reviewer names hidden when the activity is anonymous), grade overrides and the rubric/guide data.
- Course and activity backup and restore work with or without user data.
- Every action (allocation, review, feedback release, grade override) is logged.

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| "Peer review" is not listed after copying the files | The folder is not called `mod/peerreview`, or it contains an extra folder level. `mod/peerreview/version.php` must exist. |
| Upgrade stops: "requires Moodle 2025041400" | The site is older than Moodle 5.0. Upgrade Moodle first. |
| Strings show as `[[something]]` or old text | Purge the caches. |
| Students see no "Reviews to do" | The teacher has not allocated reviewers yet, or the students lack `mod/peerreview:review`. |
| Grades do not appear in the gradebook | Grades are sent only when a teacher presses **Push grades to gradebook** on the report page. |
