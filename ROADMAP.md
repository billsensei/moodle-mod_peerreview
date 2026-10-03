# Roadmap

Ideas and known limitations for versions after 0.13.0. Nothing here is promised.

## Known limitations in 0.13.0

- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).
- **Automatic reminders**: one reminder per activity and close date.

## Ideas

- Finish the phpmd clean-up. The allocation manager, the random allocator, the review service, the privacy export, the student view and the progress figures are split (see CHANGES.md, Unreleased). What is left is advisory, 10 findings: `allocation\manager` (coupling 13, `plan()` complexity 13), `local\criterion_stats::get_statistics()` (at the limit), the boolean flag of `grade_range::format()`, and six findings on test classes with many methods or dependencies. The other 18 of the 28 findings are forced by Moodle's own signatures or naming (unused callback parameters, `get_other_mapping()`, `validate_defined_fields()`, long backup class names) and cannot be fixed.
- Several automatic reminders per activity (for example a week and a day before the close date). Needs a place to remember which of them were sent, so a new table or field, an upgrade step, and backup and restore changes.
- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Weighting of reviews, or excluding outliers from the received grade.
- Grade calibration: compare each reviewer's marks with the teacher's.
