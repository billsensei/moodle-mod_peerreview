# Peer review (mod_peerreview)

A lightweight peer assessment activity for Moodle 5.0, 5.1 and 5.2, made for face-to-face and blended classes.

Students assess something that happened in the room — a presentation, a speaking task, a role-play, a contribution to group work. There are **no submissions and no phases**: the activity is simply open, optionally between an opening and a closing date. The teacher decides who reviews whom, students fill in a rubric or marking guide on their phones, the teacher watches progress live and releases the feedback when ready, and an aggregated grade goes to the gradebook.

Compared with the Workshop activity (`mod_workshop`), there is nothing to upload and nothing to switch between phases.

## Features

- **Allocation** of reviewers by the teacher: manual, random (N reviews per student, balanced, never yourself), all-to-all within groups, rotation (each student reviews the next one in a list), self-assessment (every student reviews themselves; the feedback page shows it next to the peers' grade, per criterion with a rubric or marking guide), or CSV import. Every automatic method shows a preview before anything is saved; reviews that were already started are never replaced silently.
- **Assessment forms**: Moodle's standard **rubric** or **marking guide** (advanced grading), or a simple points-and-comment form when no advanced method is set up. Students can save a draft and edit their review until the closing date.
- **Anonymity**: by default reviewees see "Anonymous" instead of the reviewer's name. Teachers always see who wrote what.
- **Live teacher report**: reviews given and received per student, received grade, participation; refreshes itself every 15 seconds; a button to remind students who still have reviews to do, and up to five optional automatic reminders, each any time from 1 hour to 52 weeks before the close date; the class average on each rubric or marking guide criterion; drill down to every review; export to CSV, Excel and other formats.
- **Feedback release**: students see the feedback they received only after the teacher releases it (one button, can be undone).
- **Grades**: the received grade (mean or median of the reviews, self-reviews excluded) and an optional participation grade (share of assigned reviews completed). A teacher can override a student's received grade with a note. Grades reach the gradebook when the teacher presses "Push grades to gradebook".
- **Completion**: "Student must submit all assigned reviews".
- **Groups and groupings**, course reset, backup and restore (with or without user data), privacy API (export and delete), event logging.
- **Phone first**: one-column cards on phones, large touch targets, no sideways scrolling (checked by an automated test at 425×750).
- **Moodle app** (unreleased, not yet tried in the real app): students see their reviews to do, submit reviews with the points or scale form, and read received feedback. Rubric and marking guide reviews, and all teacher tools, open in the browser.

## Requirements

| | |
|---|---|
| Moodle | 5.0, 5.1 or 5.2 (version 2025041400 or later). Not tested with 5.3+. |
| PHP | 8.2 or 8.3 for Moodle 5.0 and 5.1 (tested on both); Moodle 5.2 itself needs PHP 8.3 or later |
| Database | MariaDB or PostgreSQL (tested on MariaDB 11 and PostgreSQL 17). MySQL should work, since the plugin only uses Moodle's database API, but it is not tested. |

## Installation

Copy the plugin to `mod/peerreview` in your Moodle directory and run the upgrade. Step-by-step instructions, including the command line and expected output: [docs/ADMIN_INSTALL.md](docs/ADMIN_INSTALL.md).

## Documentation

- [Teacher quick start](docs/TEACHER_QUICKSTART.md): one page, from creating the activity to checking grades.
- [Student how-to](docs/STUDENT_HOWTO.md): half a page to project in class.
- [Admin install and upgrade](docs/ADMIN_INSTALL.md)
- [Design](docs/DESIGN.md) and [commands](docs/COMMANDS.md): for developers.
- [Changes](CHANGES.md) and [roadmap](ROADMAP.md).

## Screenshots (to take)

1. Student on a phone: "Reviews to do" cards with the progress bar.
2. Student on a phone: rubric review form.
3. Student on a phone: "Feedback I received" with "Anonymous" and the grade.
4. Teacher: activity page summary with the Report and Allocate buttons.
5. Teacher: random allocation preview (numbers per student, "Confirm and save").
6. Teacher: live report with the auto-refresh switch, "Release feedback" and "Push grades to gradebook".
7. Teacher: drill-down of one student with the override form.
8. Activity settings: anonymity, aggregation, grading method, participation grade.

## Development

Run every check locally with `scripts/ci-local.sh` (see [docs/COMMANDS.md](docs/COMMANDS.md)); GitHub Actions runs the same checks on PHP 8.2 and 8.3 with MariaDB and PostgreSQL (`.github/workflows/ci.yml`).

## License

2026 Bill <wrwjpn@gmail.com>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
