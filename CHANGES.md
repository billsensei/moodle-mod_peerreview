# Changes in the latest version

What changed or was added since the previous version of `mod_peerreview`. The full history is in [changelog.md](changelog.md).

## Unreleased

- Tests: the three tests that use data providers (`events_test`, `random_allocator_test`, `aggregator_test`) now also carry `@dataProvider` annotations, so they run on Moodle 4.5 (PHPUnit 9.6) as well as 5.x. The plugin still declares Moodle 5.0 as its minimum; on 4.5.15 (PHP 8.4, minimum version lowered by hand) the 215 PHPUnit tests and 17 Behat scenarios pass. Database: no change.
- Moodle 4.5: the minimum Moodle version is now **4.5** (`$plugin->requires` 2024100700, `supported` 405 to 503) and CI also runs on `MOODLE_405_STABLE`. Before, the site refused to install the plugin below Moodle 5.0. No plugin code changed for it. Database: no change.

## 0.19.9 (beta), 2026-10-20

- Phones: a **rubric with four levels and several criteria** was wider than a 425 px screen, so the remark boxes of the review form and the received-feedback page were cut off at the right edge. Below 576 px the rubric now stacks: the criterion name, its levels (up to four across, wrapping if needed) and then the remark box at full width. Only `styles.css` changed; no database or version change.
- Tests: new Behat scenario `phone_rubric.feature` (a four-level, three-criterion rubric must fit a 425 px phone, for the review form and the received feedback); it fails on 0.19.8 and passes on 0.19.9. Database: no change; the version number changed (run the usual upgrade).
