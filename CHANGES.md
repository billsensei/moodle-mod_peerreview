# Changes

## Unreleased

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
