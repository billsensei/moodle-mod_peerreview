# mod_peerreview — Design (Phase 1)

Status: **draft for approval**. No plugin code exists yet. Target: Moodle 5.0.10+ (`$branch = 500`, version 2025041410.01, classic layout, no `/public`), PHP 8.2+, MariaDB and PostgreSQL via XMLDB/DML only.

Core files this design was checked against (all under `~/test/moodle`):
`mod/assign/classes/grades/gradeitems.php`, `mod/workshop/classes/grades/gradeitems.php`, `grade/classes/component_gradeitems.php`, `grade/grading/lib.php`, `mod/assign/locallib.php` (`get_grading_instance`, ~L7847; `submit_and_get_grade`, ~L8697), `mod/assign/classes/completion/custom_completion.php`, `course/moodleform_mod.php`.

## 0. Deviations from prompt.md (need your OK)

| # | Prompt says | Moodle 5.0 reality | Proposal |
|---|---|---|---|
| D1 | Implement `peerreview_grading_areas_list()` | `grading_manager::available_areas()` only calls that callback when the component has no `advancedgrading_mapping`, and then emits a `DEBUG_DEVELOPER` deprecation notice. | Do **not** write the callback. `classes/grades/gradeitems.php` implements `itemnumber_mapping`, `fieldname_mapping` and `advancedgrading_mapping`; lang string `gradeitem:peer`. |
| D2 | "Each allocation row is the grading itemid" with area `peer` while grade items are 0=received, 1=participation | Fine: `get_advancedgrading_itemnames()` is independent of `get_itemname_mapping_for_component()`. Area name is **not** a gradebook item. | Mapping `[0=>'received', 1=>'participation']`; advanced-grading names `['peer']`. Keeps the completion and mod forms from seeing a phantom third grade item. |
| D3 | Grade "the standard modgrade element" | modgrade also allows scales. Scales do not work with mean/median of rubric points. | Points only in v1 (`grade > 0`); the form restricts the type to points. |
| D4 | Grade item 1 is "participation" | mod form fields come from `fieldname_mapping`. | Field names: item 0 → `grade`, item 1 → `gradeparticipation`. |

## 1. Final DB schema (XMLDB)

### `peerreview`
| Field | Type | Notes |
|---|---|---|
| id | int(10) PK | |
| course | int(10) NN | index |
| name | char(255) NN | |
| intro / introformat | text / int(4) | |
| grade | int(10) NN default 100 | max of received grade, points only (D3) |
| gradeparticipation | int(10) NN default 0 | 0 = no participation item |
| aggregation | int(4) NN default 0 | 0 mean, 1 median |
| anonymous | int(2) NN default 1 | 1 = reviewer shown as "Anonymous" to reviewee |
| allowselfreview | int(2) NN default 0 | |
| feedbackreleased | int(2) NN default 0 | teacher toggle, no settings edit |
| timeopen / timeclose | int(10) NN default 0 | 0 = no limit |
| completionallreviews | int(2) NN default 0 | |
| timecreated / timemodified | int(10) NN | |

### `peerreview_alloc` (one row = one review = one advanced-grading `itemid`)
| Field | Type | Notes |
|---|---|---|
| id | int(10) PK | |
| peerreviewid | int(10) NN | FK → peerreview |
| reviewerid / revieweeid | int(10) NN | FK → user |
| groupid | int(10) NN default 0 | group context at allocation time; informational |
| status | int(2) NN default 0 | 0 new, 1 draft, 2 submitted |
| grade | number(10,5) NULL | normalised to 0..`peerreview.grade`; set only on submit |
| feedback / feedbackformat | text NULL / int(4) | used by the no-rubric fallback; `FORMAT_HTML`, no file area (v1) |
| allocatedby | int(10) NN | |
| timecreated / timemodified | int(10) NN | |
| timesubmitted | int(10) NULL | **added to the draft** for reporting/export |

Indexes: unique `(peerreviewid, reviewerid, revieweeid)`; `reviewerid`; `revieweeid`; `(peerreviewid, status)`.

### `peerreview_override`
`id, peerreviewid, userid, grade number(10,5) NN, note text NULL, overriddenby, timemodified`. Unique `(peerreviewid, userid)`.

No database foreign keys beyond XMLDB key declarations (Moodle convention). Rows of deleted users are handled by the privacy provider; see section 7 for enrolment removal.

## 2. Capability matrix

| Capability | Type / risk | manager | editingteacher | teacher (non-editing) | student | guest |
|---|---|---|---|---|---|---|
| `mod/peerreview:addinstance` | write, `RISK_XSS` | ✔ | ✔ | | | |
| `mod/peerreview:view` | read | ✔ | ✔ | ✔ | ✔ | ✔ |
| `mod/peerreview:review` | write | | | | ✔ | |
| `mod/peerreview:allocate` | write | ✔ | ✔ | ✔ | | |
| `mod/peerreview:viewallreviews` | read, `RISK_PERSONAL`; also reveals reviewer identity | ✔ | ✔ | ✔ | | |
| `mod/peerreview:overridegrade` | write | ✔ | ✔ | | | |
| `mod/peerreview:releasefeedback` | write | ✔ | ✔ | ✔ | | |
| `mod/peerreview:export` | read, `RISK_PERSONAL` | ✔ | ✔ | | | |

Rules: a **reviewer** is a user who has `review` and holds a `$CFG->gradebookroles` role. A **reviewee** is any user enrolled with a gradebook role. Groups: `groups_get_activity_groupmode` is honoured; users lacking `moodle/site:accessallgroups` only see their own groups in the teacher pages. Every entry point calls `require_login($course, false, $cm)` then `require_capability`; every state-changing request calls `require_sesskey()`.

## 3. Page map

| Page | Who | Purpose |
|---|---|---|
| `index.php` | any | course-level list (`course_module_instance_list_viewed`) |
| `view.php?id=cmid` | view | student: "Reviews to do" cards, "Feedback I received" (if released). Teacher: summary, link to report/allocate, release toggle |
| `review.php?id=cmid&alloc=ID` | review (own alloc) or viewallreviews (read-only) | review form; draft/submit; rubric/guide/fallback |
| `allocate.php?id=cmid` | allocate | method tabs, preview, confirm; pair table with remove |
| `allocate_csv.php?id=cmid` | allocate | CSV import (with preview) and export |
| `report.php?id=cmid` | viewallreviews | overview table, live refresh, drill-down, override, push grades, release/hide |
| `export.php?id=cmid&format=` | export | dataformat download |
| `action.php` (POST only) | varies | release/hide, push grades, delete pair; sesskey; redirects |
| Web services | see below | polling and toggle |

Web services (`db/services.php`, AJAX-enabled): `mod_peerreview_get_progress` (cmid → per-student counts; needs `viewallreviews`), `mod_peerreview_set_feedback_release` (needs `releasefeedback`). Live refresh = AMD module `mod_peerreview/progress` polling every 15 s, on/off stored in a user preference.

Output: renderer `classes/output/renderer.php` + renderables in `classes/output/`, templates in `templates/` (mobile-first cards, ≥44 px targets, verified at 375 px).

Events (`classes/event/`): `course_module_viewed`, `course_module_instance_list_viewed`, `allocation_created`, `allocation_deleted`, `review_submitted`, `review_updated`, `feedback_released`, `grade_overridden`.

## 4. Allocation algorithms

Definitions: `S` = eligible students in a pool (enrolled, gradebook role, active, in the selected group if group mode), `n = |S|`. Existing pairs are never removed by an automatic method. All methods show a **preview** (pairs, per-student given/received counts, min/max spread) and write only on confirm, inside one transaction.

### 4.1 Manual
Form: one reviewer, many reviewees → insert missing pairs. Self pair only if `allowselfreview`. Duplicates ignored (unique index). Remove = per-pair delete with confirmation (§4.6).

### 4.2 Random, N reviews per student
Pools: if group mode is on and "allow cross-group" is unticked, one pool per group (users in several groups go to the lowest group id; users in none form a pool of their own); otherwise one pool. Per pool:

```
effectiveN = min(N, n-1)            # warn in preview if reduced
if n < 2: skip pool, warn
if no existing pairs among S:       # fresh case  → exact
    order  = random_shuffle(S)                       # positions 0..n-1
    offsets = random_sample({1..n-1}, effectiveN)    # distinct
    for i in 0..n-1, for d in offsets:
        add(reviewer=order[i], reviewee=order[(i+d) mod n])
else:                               # top-up case (late joiners, re-runs)
    given[r], recv[s] = counts from existing pairs
    demand[r] = max(0, effectiveN - given[r])
    repeat rounds until all demand met or no candidate:
        for r in shuffle(reviewers with demand>0):
            cand = {s in S : s != r, pair(r,s) absent}
            pick s in cand with minimal recv[s] (ties random); add; recv[s]++; demand[r]--
    repair: while exists (r→a),(r'→b) new pairs with recv[a]-recv[b] >= 2
            and (r→b),(r'→a) both legal: swap targets
```

**Balance proof (fresh case).** Fix an offset `d ∈ {1..n-1}`. The map `i ↦ (i+d) mod n` is a bijection on `Z_n`, so each position is reviewed exactly once per offset. With `effectiveN` distinct offsets each student **receives exactly `effectiveN`** reviews and gives exactly `effectiveN`. `d ≠ 0 (mod n)` ⇒ never self. Distinct offsets ⇒ no duplicate pair. ∎ (Stronger than the spec's N or N±1.)

**Top-up case** carries no closed-form proof: greedy-min-received plus swap repair is a heuristic. The preview always shows the real spread, and the PHPUnit tests assert spread ≤ 1 on fixtures (late joiner, uneven groups, prior manual pairs). If you want a hard guarantee I can replace it with a min-cost flow, at extra complexity; my recommendation is to keep the heuristic for v1.

Self-review: random never assigns self-pairs; self-review is only creatable in manual mode when `allowselfreview` is on.

### 4.3 Within group, all-to-all
For every group (or the selected one): all ordered pairs `(a,b)`, `a≠b`, both in the group. Requires groups to exist; students in no group are listed as skipped. Rows = `g(g-1)` per group; preview warns above 12 members.

### 4.4 Rotation
Input: an ordered list (default: alphabetical by last name; the teacher can reorder with up/down or paste a username list) and shift `K`.
```
require 1 <= K mod n <= n-1
for i in 0..n-1: add(reviewer=list[i], reviewee=list[(i+K) mod n])
```
Each shift is a bijection, so every student gives one and receives one; running again with another K adds more.

### 4.5 CSV
Columns `reviewer,reviewee` (username or email, header row required). Import shows a preview with per-row errors (unknown user, not enrolled, self-pair not allowed, duplicate) and only imports valid rows on confirm. Export uses the same shape plus `status`.

### 4.6 Removing and regenerating
- Deleting a pair with `status = 0`: single confirmation. With `status ≥ 1`: a warning page stating "this deletes a saved/submitted review" and requires an explicit confirm; the grading instance for that itemid is deleted through the grading controller.
- "Replace" on automatic methods: deletes only `status = 0` pairs in scope; if any draft/submitted pair is in scope, a confirmation lists the count and the default is to **keep** them.
- Nothing deletes a submitted review without the confirmation page.

## 5. Grading data flow

### 5.1 Setup
`peerreview_supports(FEATURE_ADVANCED_GRADING)` → true; `gradeitems` supplies area `peer` (D1/D2). The teacher defines a rubric or marking guide on `grade/grading/manage.php`; area is `get_grading_manager($context, 'mod_peerreview', 'peer')`. Modelled on `mod_assign` (`locallib.php` `get_grading_instance`).

### 5.2 Review form (`review.php`)
```
alloc = fetch(id), check alloc.reviewerid == $USER->id (or viewallreviews for read-only)
gm = get_grading_manager($context, 'mod_peerreview', 'peer')
method = gm->get_active_method()
if method and controller->is_form_available():
    instance = controller->get_or_create_instance($instanceid, $USER->id, alloc.id)   # raterid=reviewer, itemid=alloc.id
    controller->set_grade_range(make_grades_menu($pr->grade), true)
    form adds 'advancedgrading' element via the instance
else:
    fallback: number field 0..grade + comment editor
```
On **Submit**: `grade = instance->submit_and_get_grade($data->advancedgrading, alloc.id)`; store `alloc.grade = grade`, `status = 2`, `timesubmitted`; fire `review_submitted` (first time) or `review_updated`; update completion. Fallback path stores the number and comment directly.
On **Save draft**: form validation of the grading element is skipped; `status = 1`; `alloc.grade` stays NULL. **Spike in Phase 4:** confirm that `gradingform_rubric`/`guide` instances accept a partial `update()`; if not, drafts store only the fallback comment plus a note and the rubric is saved on submit.
Edit after submit allowed until `timeclose`; the same instance is updated and `review_updated` fires. Rubric changed after reviews exist → core marks instances `NEEDUPDATE`; the form shows core's warning and the grade is recomputed on resubmit (same behaviour as assign).
Max grade change in settings: stored `alloc.grade` and overrides are rescaled proportionally in `peerreview_update_instance`.

### 5.3 Aggregation and gradebook
```
received(u) = aggregate( alloc.grade for alloc where revieweeid = u and status = 2 and reviewerid != u )
grade item 0 = override(u).grade if exists else received(u); no reviews and no override → no grade (null, not 0)
grade item 1 = 100% * done/assigned as reviewer, scaled to gradeparticipation; assigned = 0 → null
```
Mean = arithmetic; median = mean of the two middle values for even counts.
Grades are pushed **only** through `peerreview_update_grades()` (called by the "Push grades to gradebook" button and by reset/regrade), not on each review submit. Reason: the feedback release toggle would otherwise be bypassed because students see gradebook grades independently. This is D5 below.

Override lives in `peerreview_override`, never in `peerreview_alloc`; deleting the row reverts.

### 5.4 Completion
`classes/completion/custom_completion.php` extends `\core_completion\activity_custom_completion`, rule `completionallreviews`. Complete when assigned ≥ 1 and submitted = assigned. (A vacuous "0 of 0" is **not** complete, otherwise students complete before allocation.) Completion state is re-evaluated on allocation add/remove and on submit.

## 6. Anonymity
Enforced server-side in the output classes: the "received feedback" renderable never receives `reviewerid` when `anonymous = 1` and the viewer lacks `viewallreviews`. Web services and the export follow the same rule.

## 7. Privacy, backup, reset (for Phases 6–7)
- Privacy provider: metadata for all three tables and `core_grading`; export/delete for both roles (reviewer rows and reviewee rows); userlist support; grading instances found via `core_grading\privacy\provider`.
- Backup/restore moodle2: structure `peerreview` → `allocations` (userinfo) → `overrides` (userinfo), plus grading areas/definitions, modelled on `mod/assign/backup/moodle2/` and `mod/workshop/backup/moodle2/`. Restore remaps user ids and alloc ids (the grading `itemid`).
- Reset: delete allocations/overrides and the associated grading instances; optional "reset grades".
- Enrol removal: allocations for the user are kept (history) but hidden from reports and excluded from aggregation while the user is not enrolled.

## 8. Decisions and open questions

Please confirm or change each; my default is what I will build.

| # | Question | Default |
|---|---|---|
| D5 | Gradebook visibility vs feedback release | Grades reach the gradebook only via the manual "Push grades" button. |
| D6 | Are self-reviews (manual, allowed by setting) in the received grade? | Stored and shown, **excluded** from the aggregate. |
| D7 | Points-only grade (no scales) | Yes (D3). |
| D8 | Random top-up balance | Heuristic with tests (see §4.2), not min-cost flow. |
| D9 | Students in several groups (random per-group pools) | Lowest group id wins. |
| D10 | `requires` value | `2025041400` (branch-date release for 5.0), verified against core in Phase 2. |

## 9. Implementation notes (Phase 3)

Where the built allocation code differs from the plan above:

- **Eligible students** are active enrolments holding `mod/peerreview:review` (both as reviewers and reviewees), not "any gradebook role".
- **One page**: CSV import/export and delete live in `allocate.php` (`method=csv|csvexport|delete`) instead of a separate `allocate_csv.php`.
- **"Replace" never deletes started reviews.** It removes only not-started pairs in scope and reports how many started ones were kept. Started reviews are removed only through the delete page with an explicit warning and a check that the count of started reviews did not change since the page was shown.
- **Reproducible preview**: the preview picks a seed; the confirm request carries it, so the saved result equals the preview.
- **Top-up algorithm** (section 4.2) is implemented as slot matching: one give-slot per missing review of each reviewer, one receive-slot per missing review of each reviewee, padded with the least-loaded students until the lists are equal and legal, matched at random, conflicts repaired by swapping targets. A stress test (1,500 scenarios) found no illegal pair, nobody left below N and nobody above `max(N+1, what they already had)`; in ~1.5% of scenarios manual pairs left the spread above 1 and in ~0.1% a pair could not be placed (reported as a warning).
- **Advanced grading instances** of deleted allocations are removed in Phase 4, when reviews can first exist.
