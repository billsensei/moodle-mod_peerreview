# Roadmap

Ideas and known limitations for versions after 0.9.0. Nothing here is promised.

## Known limitations in 0.9.0

- **Switching the grading method after reviews were submitted.** The old reviews keep their stored grade and their rubric or guide data (it is exported and backed up), but the review page shows the form of the *current* method, so the old filling is not displayed. Planned fix: show each review with the method it was written in.
- **Scales are not supported**, only points (a mean or median of scale items is not meaningful).
- **Only Moodle 5.0 is tested.** Moodle 5.1 moved the web root to `/public`; the plugin should work there, but it is not tested.
- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).

## Ideas

- Split the largest classes flagged by phpmd (allocation manager, random allocator, review service) into smaller units.

- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Moodle 5.1 and 5.2 in the CI matrix.
- Per-criterion statistics in the report (for example, the class average on "Delivery").
- Weighting of reviews, or excluding outliers from the received grade.
- Self-assessment compared with peer assessment on the student's feedback page.
- Reminders for students who have not finished their reviews (message API).
- Grade calibration: compare each reviewer's marks with the teacher's.
