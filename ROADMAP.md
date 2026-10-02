# Roadmap

Ideas and known limitations for versions after 0.11.0. Nothing here is promised.

## Known limitations in 0.11.0

- **Only Moodle 5.0 is tested.** Moodle 5.1 moved the web root to `/public`; the plugin should work there, but it is not tested.
- **No Moodle app support yet.** In the app the activity opens in the phone's browser; students can also use the browser directly.
- **No files** in comments (the overall comment editor accepts text only).

## Ideas

- Split the largest classes flagged by phpmd (allocation manager, random allocator, review service) into smaller units.

- Native Moodle app support (`db/mobile.php`) for reviewing without a browser.
- Confirm the GitHub CI results for Moodle 5.1 and 5.2 (both are in the matrix; 5.2 on PHP 8.3 only, an assumption to check), then list 5.2 in `$plugin->supported` and the README.
- Weighting of reviews, or excluding outliers from the received grade.
- Self-assessment compared with peer assessment on the student's feedback page.
- Automatic reminders (for example shortly before the close date) on top of the manual button.
- Grade calibration: compare each reviewer's marks with the teacher's.
