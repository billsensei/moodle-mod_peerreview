# Moodle app checklist (real phone)

What to try in the official Moodle app, because the app pages (0.15.0 to 0.19.4) have only been tested with PHPUnit and over REST. Write down anything that looks wrong: the screen, what you did, what you expected. Screenshots help.

## Setup

1. On the computer: `source scripts/env.sh`, then `scripts/dev-site.sh start`. The phone must reach `http://192.168.1.4:8000` (same Wi-Fi). `wwwroot` in `config.php` must match that address.
2. *Site administration → Mobile app → Mobile settings*: **Enable web services for mobile devices** on.
3. Install the Moodle app (Android or iOS) and log in to the site address above. Use `http`, so expect a warning about an insecure connection.
4. Make one course with these activities, and at least 4 students plus 1 teacher (log in as a student on the phone, as the teacher in a browser):
   - A: simple points (grade 100), **Allow self-review** on.
   - B: a scale.
   - C: a rubric.
   - D: a marking guide with some frequently used comments.
5. Allocate reviews on the website (allocation page; use the **Self-assessment** tab for A so every student also reviews themselves).
6. After a pull-to-refresh, check the course page in the app shows the activities.

## Student (log in as a student)

For each of A, B, C, D:

- [ ] The activity opens in the app (no "open in browser" detour), the intro and dates show.
- [ ] The list of reviews to do shows each person with a status (New, Draft, Submitted) and a progress line ("0 of 3 reviews done").
- [ ] A review opens. The form fits the screen; the keyboard does not hide the field being typed in.
- [ ] A: enter points above the maximum, then a valid one. B: choose a scale item.
- [ ] C: choose one level for each criterion; the level text and points are readable. D: enter a score for each criterion (a score above the maximum is refused), and use **Insert frequently used comment**: it adds to the remark on a new line.
- [ ] **Save draft**, leave, come back: the draft is still there and the status says Draft (C and D only).
- [ ] **Submit** with a criterion left empty: a clear message, nothing submitted. Fill it in and submit: the card shows Submitted straight away.
- [ ] After the activity closes, or on a submitted review, it can be viewed but not changed.
- [ ] Before the open date or after the close date the page says so and has no Review button.
- [ ] Flight mode on, try to submit: the app shows an error and does not lose what you typed (offline drafts are not supported; only note what happens).

Feedback (the teacher releases it from the phone or the website):

- [ ] Before release: no grade or feedback is shown.
- [ ] After a pull-to-refresh: your grade, and each received review with the reviewer name (or "Anonymous reviewer" if the activity hides it) and the comment.
- [ ] A comment written in the browser with formatting shows readable text.
- [ ] **Self-assessment comparison** (activity A, and C or D for the per-criterion lines): heading, your score, your peers' score, and one sentence ("You rated yourself 30 points higher ..."). For C and D every criterion shows You / Peers (average) / Max. Check long criterion names wrap. A student with no self-review sees no comparison.
- [ ] Your own self-review appears in the list as "You (self-review)" and does not change the grade shown.

## Teacher (log in as the teacher)

- [ ] The page shows "N of M reviews done" and one row per student with reviews given and received, participation and grade. Students behind are easy to see.
- [ ] In a course with separate groups, a teacher who is not in all groups sees only their groups (compare with the website report).
- [ ] **Release feedback**, then **Hide feedback**: the state text changes at once, and the website agrees. Try it as a teacher without the *Release feedback* permission: the button is not offered.
- [ ] **Report** and **Allocate** open the website (browser or in-app browser) and work after logging in.
- [ ] A teacher who is not a student does not see review lists of their own.

## Other

- [ ] Language: switch the app to another language that has strings (or force one on the site) and check the page texts follow.
- [ ] Dark mode and a large font size: nothing cut off or unreadable.
- [ ] Tablet or landscape layout is usable.
- [ ] Open the same activity as a user without the *view* capability or not enrolled: a normal Moodle error, not a blank page.
- [ ] Note the app version and phone model in your report.

## If something fails

- Blank page or spinner: the app's debug log (*More → Settings → General → Debug*, or *More → Settings → About → Logs*) and `~/test/moodle-web.log` on the computer.
- Check the same action works in the website. If the website works and the app does not, it is an app page bug: report which review method and which step.
