# Changes

## Unreleased

- Internal only, nothing changes for teachers or students: the largest classes were split into smaller ones. The allocation manager gave up its CSV import and export (`allocation\csv`), its deleting (`allocation\deleter`), the order for the rotation method (`allocation\rotation_order`) and the "in scope" set of the automatic methods (`allocation\pool_scope`). The random allocator gave up the matching of missing reviews (`allocation\slot_matcher`). The review service gave up the grading controller and instance lookups (`review\grading_access`) and the clean-up of draft rubrics and guides (`review\draft_normaliser`). The public methods of the manager and the service are unchanged. For the same seed the random allocator gives exactly the same proposals as before (checked on 1,960 recorded cases), and the review, feedback, report and export pages came out identical on a test site.

## 0.13.0 (beta), 2026-10-03

- Self-assessment: the allocation page has a new **Self-assessment** tab that gives every student a review of themselves (needs **Allow self-review** in the activity settings; students who already have one are left alone). When feedback is released, a student with a submitted self-review and at least one submitted peer review sees their self-assessment next to their peers' grade, with how many points higher or lower they rated themselves (for a scale only whether it is the same item), and, for a rubric or marking guide, their score and their peers' average on each criterion. Self-reviews still never count in the received grade.
- Fixed: the **Average by criterion** table on the report counted self-reviews. It now leaves them out, like the grades do.
## 0.12.1 (beta), 2026-10-02

- Reminders (the button on the report and the automatic one) are now written in each student's own language instead of the language of whoever or whatever sends them. Strings missing from a language pack fall back to English.

## 0.12.0 (beta), 2026-10-02

- Automatic reminder: the new activity setting **Remind students before the close date** (off by default; 1, 2 or 3 days or 1 week) makes Moodle send one message to each student who still has reviews left when that time before the close date is reached. It needs a close date and the Moodle cron. It is sent once for each close date, from the no-reply user, only while the activity is open and visible; changing the close date or the lead time arms it again. The manual button on the report works as before. New scheduled task *Send automatic peer review reminders* (hourly), database fields `reminderlead` and `remindersentfor` (upgrade step), and `reminderlead` is part of backup and restore (the sent state is not).
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
