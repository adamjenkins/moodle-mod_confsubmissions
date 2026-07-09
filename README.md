# mod_confsubmissions

**Conference Submissions** — a Moodle activity for running a call for abstracts.

*Documentation: English (this file) · [日本語](README.ja.md)*

Part of the [Conference Tools](https://github.com/adamjenkins/moodle-conference-tools) suite:

- **mod_confsubmissions** (this plugin) — call for abstracts
- [mod_confprogram](https://github.com/adamjenkins/moodle-mod_confprogram) — reviewer vetting + public program
- [mod_confscheduler](https://github.com/adamjenkins/moodle-mod_confscheduler) — drag-and-drop block schedule
- [mod_confcheckin](https://github.com/adamjenkins/moodle-mod_confcheckin) — tickets, badges, QR check-in

## What it does

Add a **Conference Submissions** activity to open a call for abstracts. Presenters submit a title, abstract and speakers; organisers configure what is collected and how it is categorised.

- **Title & abstract limits** — by character or word count, enforced live as you type and again server-side.
- **Speakers** — the primary speaker defaults to the submitter; co-presenters are added by picking an enrolled user or typing a name/email, and reordered by drag.
- **Custom fields** — add any number of optional fields (short or long text, dropdown, checkbox, date, number, or URL), each freely labelled and optionally required. A field's type locks once any submission has answered it (stored answers only make sense under the type they were captured with — delete the field and add a new one to change type).
- **Tracks & submission types** — tracks (with an optional colour and icon) categorise submissions for review and scheduling; submission types (e.g. Lightning Talk) each carry a default duration the scheduler reuses.
- **Preferred dates** — optionally offer one checkbox per conference day; the scheduler's autoscheduler honours these. Specific days can be disabled for regular submitters, with an optional short reason shown in parentheses next to the greyed-out day.
- **Withdraw & delete** — submitters can withdraw their own submission (reversible); managers can permanently delete any. An editing teacher with *Edit any submission* can edit any submission, including its track, even after the call closes.
- **Notifications** — speakers are emailed when a submission is made and organisers when one is withdrawn; templates are editable and can be switched off per activity.
- **Backup/restore & course reset** — fully supported. Configuration (tracks, types, fields, templates) survives a reset; submissions do not.

Other Conference Tools plugins integrate through this plugin's `classes/api.php`.

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
