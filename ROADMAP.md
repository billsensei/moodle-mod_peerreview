# Roadmap

Ideas and known limitations for versions after 0.17.0. Nothing here is promised.

## Known limitations in 0.17.0

- **Moodle app: no marking guides.** Marking guide reviews, the self-assessment comparison, the full report and the allocation open in the browser (students from 0.15.0, the teacher progress list and release switch from 0.16.0). Not tried in the real app yet.
- **No files** in comments (the overall comment editor accepts text only).

## Ideas

- Finish the phpmd clean-up. The allocation manager, the random allocator, the review service, the privacy export, the student view and the progress figures are split (see CHANGES.md, 0.14.0). What is left is advisory, 10 findings: `allocation\manager` (coupling 13, `plan()` complexity 13), `local\criterion_stats::get_statistics()` (at the limit), the boolean flag of `grade_range::format()`, and six findings on test classes with many methods or dependencies. The other 18 of the 28 findings are forced by Moodle's own signatures or naming (unused callback parameters, `get_other_mapping()`, `validate_defined_fields()`, long backup class names) and cannot be fixed.
- Moodle app: marking guide reviews in the app, offline drafts, and automatic refresh of the teacher's progress list.
- Weighting of reviews, or excluding outliers from the received grade.
- Grade calibration: compare each reviewer's marks with the teacher's.
