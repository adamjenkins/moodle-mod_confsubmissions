# mod_confsubmissions

Conference Submissions — a Moodle activity module for running a call for abstracts.

Part of the [Conference Tools](https://github.com/adamjenkins/moodle-conference-tools) suite:

- **mod_confsubmissions** (this plugin) — call for abstracts / submissions
- [mod_confprogram](https://github.com/adamjenkins/moodle-mod_confprogram) — reviewer vetting workflow + public program display
- [mod_confscheduler](https://github.com/adamjenkins/moodle-mod_confscheduler) — drag-and-drop block schedule / timetable
- [mod_confcheckin](https://github.com/adamjenkins/moodle-mod_confcheckin) — tickets, badges, QR check-in, certificates

## What it does

Add a "Conference Submissions" activity to a course to open a call for abstracts. Presenters submit a title, abstract and speaker information; organisers configure per-instance limits, optional fields, and submission tracks used later for review and scheduling.

- Configurable title/abstract character and word limits.
- Speaker field defaults to the logged-in user; co-presenters can be added by searching for a user enrolled in the course, or by typing a name/email manually, in an organiser-visible, explicitly re-orderable display order.
- Fully dynamic optional fields: organisers add as many as they like, each with a freely-chosen label and a type (short text, long text, dropdown, checkbox, date, number, or web address).
- Tracks for categorising submissions, each with an optional colour and icon (drawn from a curated, built-in set — never free text or an uploaded asset), used by downstream plugins for review assignment, scheduling, and coloured pill badges shown across the whole suite.
- Submission types (e.g. Lightning Talk, Workshop), each with a default presentation duration in minutes, organiser-managed on their own screen. A presenter chooses their submission's type, which `mod_confscheduler` then uses as the initial length of the block when the presentation is first scheduled (still freely resizable afterwards).
- Optional conference dates plus an "offer preferred dates" toggle: when enabled, a submitter sees one checkbox per conference day (all checked by default), which `mod_confscheduler`'s autoscheduler tries to honour when placing a presentation. Organisers can additionally disable specific days org-wide (`dates.php`) so regular submitters are never offered them — a submitter's checkbox for a disabled day still appears, but greyed out and unchecked, while an organiser (editingteacher/manager) still sees and can select every day.
- Submitters can withdraw their own submission (a reversible status change); managers and site admins can permanently delete any submission (editingteacher deliberately cannot).
- Notifications, sent via Moodle's own core notification system (email on by default): every real speaker on a submission is notified when it is made; every editingteacher in the course is notified when a submission is withdrawn. Templates for each are organiser-editable (`notifications.php`).
- Full course backup/restore support, and course reset (deletes all submissions and everything attached to one; instance configuration -- tracks, submission types, optional fields, notification templates -- survives a reset unchanged).
- A stable PHP API (`classes/api.php`) other conference-tools plugins build on.

## Architecture notes

- **Notification templates use a plain, fixed `[[name]]` placeholder delimiter**, not a sitewide-configurable admin setting like `mod_confcheckin`'s badge/ticket templates — those are reused PDF documents authored once and kept for the life of an instance, where a delimiter clash is a real, recurring risk; a notification template is a short, one-off email an organiser is unlikely to already have `[[ ]]`-shaped content in, so the extra admin setting wasn't judged worth its own maintenance burden here.
- **A manually-entered co-presenter (no `userid`) is never notified** — there is no Moodle account to message. This is the same rule `mod_confcheckin\local\eligibility` already uses for presenter-ticket eligibility.
- **`api::set_status()` only fires the withdrawal notification for the literal `'withdrawn'` status**, never for `mod_confprogram`'s own accept/reject decision-sync calls through the same method — safe because `VALID_STATUSES` has no `'waitlisted'` entry (waitlist is a `confprogram_decision`-only concept), so there's no overlap to worry about.
- **A per-instance notifications master switch** (`confsubmissions.notificationsenabled`, default on) overrides every per-type template: when off, `notifier` sends nothing at all for that instance, regardless of what's configured on notifications.php. Editable as a checkbox on that same screen.
- **Backup/restore defines the `confsubmissions_submission` id mapping `mod_confprogram`/`mod_confscheduler` both depend on** (user request, 2026-07-06): those sibling plugins' own restore steps resolve their tables' `submissionid` references via `get_mappingid('confsubmissions_submission', ...)` from their own `after_restore()` -- restore processing order across activities in the same course backup is not guaranteed until every activity's main structure step has completed, so this mapping must exist unconditionally by the time any sibling's `after_restore()` runs. Instance configuration (tracks, submission types, optional fields, notification templates) is backed up regardless of the "include user info" setting; only submissions (and everything attached to one) are gated on it, matching how core's own `mod_choice` treats its `choice_options` vs. `choice_answers`. Verified with a real `backup_controller`/`restore_controller` cycle in `tests/backup/restore_confsubmissions_test.php`, not just a unit test of the stepslib classes.
- **An `editingteacher`/`manager` holding the new `mod/confsubmissions:editany`
  capability can edit any submission on the instance** via `edit.php`,
  regardless of ownership and regardless of whether the call is currently
  open -- unlike the submitter's own edit access, which still requires both.
  `mod_confprogram`'s Decision report links into this same `edit.php` for
  exactly this purpose (see that plugin's own README).

## Requirements

- Moodle 5.2 (`2026042000`) or later.

## Installation

```
git clone https://github.com/adamjenkins/moodle-mod_confsubmissions.git mod/confsubmissions
php admin/cli/upgrade.php
```

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

## Author

Adam Jenkins <adam@wisecat.net>
