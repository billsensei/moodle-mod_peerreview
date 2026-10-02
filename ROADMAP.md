# Roadmap

Ideas and known limitations for versions after 0.12.0. Nothing here is promised.

## Known limitations in 0.12.0

- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).
- **Automatic reminders**: the lead time is one of four fixed choices (1, 2, 3 days, 1 week).

## Ideas

- Split the largest classes flagged by phpmd (allocation manager, random allocator, review service) into smaller units.

- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Weighting of reviews, or excluding outliers from the received grade.
- Self-assessment compared with peer assessment on the student's feedback page.
- Grade calibration: compare each reviewer's marks with the teacher's.
