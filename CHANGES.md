# Changes

## Unreleased

- Docs: Moodle 5.2 needs PHP 8.3 or later (checked in CI; Moodle 5.2's own dependencies require it). No code change.

## 0.11.1 (beta), 2026-10-02

- Fixed: 0.11.0 could not be installed. Its `version.php` declared `$plugin->supported = [500, 501, 502]`, but Moodle expects a range of two values (`[500, 502]`), so the site install stopped with "Incorrect syntax in plugin supported declaration". Use 0.11.1 instead of 0.11.0.
- Moodle 5.2 is now listed as supported (tested in GitHub CI on PHP 8.3 with PostgreSQL and MariaDB), as well as 5.0 and 5.1.

## 0.11.0 (beta), 2026-10-02

- Report: **Average by criterion** table under the student list when the activity uses a rubric or marking guide. It shows, for each criterion, how many submitted reviews scored it, the average score and the highest possible score, for the students in the current group view. Scores are in the points of the rubric or guide.

## 0.10.0 (beta), 2026-10-02

- Reminders: on the **Report** page, **Remind students with reviews to do** sends a message (popup, email or the Moodle app, following each student's notification settings) to every student in the current group view who has submitted fewer reviews than assigned. Teachers press the button themselves; nothing is sent automatically. It works only while the activity is open and needs the capability to allocate reviews.

- Changing the maximum grade (points to points) now rescales stored review grades and teacher overrides proportionally, and refreshes grades already in the gradebook. Before, old values stayed as they were and could exceed the new maximum.

- The received grade can be a scale (for example Poor / Fair / Good / Excellent), not only points. Reviewers pick a scale item (or use a rubric or marking guide, which map onto the scale); the mean or median is rounded to the nearest item for the gradebook; reports and the student page show item names. The participation grade stays points only. Once reviews exist the grade cannot be switched between points and a scale.

- A submitted review is now shown with the grading method it was written in (rubric, marking guide) after the teacher switches methods, with a note saying so.

## 0.9.1 (beta), 2026-10-01

- Added the GPL v3 `LICENSE` file to the plugin root.

## 0.9.0 (beta), 2026-09-30

First beta, for Moodle 5.0.

- Activity without submissions or phases, optionally between an open and a close date.
- Allocation: manual, random (N per student, balanced, no self-review, inside groups by default), all-to-all within groups, rotation, CSV import and export; preview before saving; reviews already started are never replaced silently.
- Review form with rubric, marking guide or simple points and comment; drafts; editing until the close date.
- Student page for phones: "Reviews to do" cards with progress, "Feedback I received" after release, anonymous reviewers by default.
- Teacher report: reviews given and received, received grade, participation; auto-refresh every 15 seconds (web service); drill-down; export in any Moodle data format; release/hide feedback.
- Grades: received grade (mean or median, self-reviews excluded) and optional participation grade; teacher override with a note; "Push grades to gradebook".
- Completion rule "Student must submit all assigned reviews"; course reset; groups and groupings.
- Privacy API (export and delete, including rubric and guide data), backup and restore with or without user data, events for every action.
- Tests: 130 PHPUnit tests, one Behat scenario covering the whole flow on a phone-sized screen; CI on PHP 8.2/8.3 with MariaDB and PostgreSQL.

## 0.1.0–0.3.0 (alpha), 2026-09-30

Development versions (phases 2–8), not for production.
