# Changes in the latest version

What changed or was added since the previous version of `mod_peerreview`. The full history is in [changelog.md](changelog.md).

## 0.19.8 (beta), 2026-10-17

- Hardening (found in a security review, low severity, no change in behaviour): the web service functions **`mod_peerreview_set_feedback_release`** and **`mod_peerreview_save_review`** now check the `releasefeedback` and `review` permissions in their own code, as the other functions already did. Before, the same checks happened one level deeper (in `feedback::set_released()` and `service::require_can_edit()`), so nobody gained or lost access.
- Privacy (found in a privacy audit): the privacy metadata now also lists the **group** of an allocation and the **reminder notifications** sent through the messaging subsystem. Nothing is stored differently; only the description of the stored data was incomplete.
- Hardening (found in a security review, low severity): the **allocation CSV export** now neutralises usernames that start with `=`, `+`, `-` or `@`, like the review export already did, so a spreadsheet cannot read them as a formula. Such a username gets an apostrophe in front, so it will not match when that file is imported again. The permission **Allocate reviews** (`mod/peerreview:allocate`) is now marked as a data-loss risk in the role editor, because it can delete started reviews. Run the usual upgrade for the permission flag to take effect.
- Tests: 215 PHPUnit tests and 16 Behat scenarios pass on Moodle 5.0 (GitHub CI covers 5.1 to 5.3). Database: no change; the version number changed (run the usual upgrade).
