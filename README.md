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
- Optional fields (presentation language, teaching context, sub-topic area) can be toggled on per instance.
- Tracks for categorising submissions, each with an optional colour and icon (drawn from a curated, built-in set — never free text or an uploaded asset), used by downstream plugins for review assignment, scheduling, and coloured pill badges shown across the whole suite.
- Submission types (e.g. Lightning Talk, Workshop), each with a default presentation duration in minutes, organiser-managed on their own screen. A presenter chooses their submission's type, which `mod_confscheduler` then uses as the initial length of the block when the presentation is first scheduled (still freely resizable afterwards).
- A stable PHP API (`classes/api.php`) other conference-tools plugins build on.

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
