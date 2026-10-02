# Roadmap

Ideas and known limitations for versions after 0.11.0. Nothing here is promised.

## Known limitations in 0.11.0

- **Moodle 5.2 is tested on PHP 8.3 only** (in GitHub CI). Whether it also runs on PHP 8.2 is not checked.
- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).

## Ideas

- Split the largest classes flagged by phpmd (allocation manager, random allocator, review service) into smaller units.

- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Weighting of reviews, or excluding outliers from the received grade.
- Self-assessment compared with peer assessment on the student's feedback page.
- Automatic reminders (for example shortly before the close date) on top of the manual button.
- Grade calibration: compare each reviewer's marks with the teacher's.
