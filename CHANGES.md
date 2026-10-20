# Changes in the latest version

What changed or was added since the previous version of `mod_peerreview`. The full history is in [changelog.md](changelog.md).

## 0.20.0 (beta), 2026-10-20

- **Moodle 4.5 is now supported** (4.5, 5.0, 5.1, 5.2 and 5.3): the minimum Moodle version is **4.5** (`$plugin->requires` 2024100700, `supported` 405 to 503). Before, the site refused to install the plugin below Moodle 5.0. No plugin code changed for it; CI now also runs on `MOODLE_405_STABLE`, and the unit tests were made readable for PHPUnit 9.6 (Moodle 4.5) as well as 10+/11 (see below). Tested with PHPUnit and Behat on Moodle 4.5.15 (on PHP 8.4, which Moodle 4.5 does not officially support; GitHub CI uses PHP 8.2 and 8.3). Not tried by hand on a 4.5 site.
- Tests: the three tests that use data providers (`events_test`, `random_allocator_test`, `aggregator_test`) now also carry `@dataProvider` annotations, so they run on PHPUnit 9.6 (Moodle 4.5) as well as 10+ (5.x).
- Tests: every test class also carries `@covers` annotations next to its `#[CoversClass]` / `#[CoversFunction]` attributes, because the coding-standard check on Moodle 4.5 (PHPUnit 9.6) does not read the attributes.
- Database: no change; the version number changed (run the usual upgrade).
