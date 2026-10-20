# Changes in the latest version

What changed or was added since the previous version of `mod_peerreview`. The full history is in [changelog.md](changelog.md).

## 0.19.9 (beta), 2026-10-20

- Phones: a **rubric with four levels and several criteria** was wider than a 425 px screen, so the remark boxes of the review form and the received-feedback page were cut off at the right edge. Below 576 px the rubric now stacks: the criterion name, its levels (up to four across, wrapping if needed) and then the remark box at full width. Only `styles.css` changed; no database or version change.
- Tests: new Behat scenario `phone_rubric.feature` (a four-level, three-criterion rubric must fit a 425 px phone, for the review form and the received feedback); it fails on 0.19.8 and passes on 0.19.9. Database: no change; the version number changed (run the usual upgrade).
