# Roadmap

Ideas and known limitations for versions after 0.13.0. Nothing here is promised.

## Known limitations in 0.13.0

- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).
- **Automatic reminders**: the lead time is one of four fixed choices (1, 2, 3 days, 1 week).

## Ideas

- Finish the phpmd clean-up. The allocation manager, the random allocator and the review service are split (see CHANGES.md, Unreleased). What is left is advisory: `allocation\manager` (coupling 13, `plan()` complexity 13), `privacy\provider::export_user_data()`, `output\student_view::export_for_template()`, `local\progress::get_rows()`, `local\criterion_stats::get_statistics()` (at the limit), the boolean flag of `grade_range::format()`, and a few test classes with many methods. More than half of the 34 findings (18) are forced by Moodle's own signatures or naming (unused callback parameters, `get_other_mapping()`, `validate_defined_fields()`, long backup class names) and cannot be fixed.
- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Weighting of reviews, or excluding outliers from the received grade.
- Grade calibration: compare each reviewer's marks with the teacher's.
