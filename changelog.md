# Changelog

## Unreleased

- User feedback (2026-07-05): "submitters should have the ability to 'Withdraw' a
  submission. Managers and site Admins should also be able to delete submissions
  (editingteacher should not have this capability)." Added a `withdrawn` status
  (`api::VALID_STATUSES`) with a "Withdraw" link on a submitter's own "my
  submissions" row (reversible-in-spirit: a status change, via the existing
  `set_status()`, not a deletion) and a separate, genuinely destructive
  `api::delete_submission()` (removes the submission, its speakers, and its
  optional-field answers) gated by a new `mod/confsubmissions:deleteany`
  capability, deliberately given only to the `manager` archetype -- site admins
  always bypass capability checks regardless of archetype config, so no
  explicit admin entry was needed, and `editingteacher` was deliberately left
  out. Live-verified via Moodle's "Login as": an editingteacher-only user sees
  no Delete link at all; a manager-only user does, and both the confirm-first
  Withdraw and Delete flows were exercised end-to-end (including confirming the
  DB record and its speakers are actually gone after a Delete). Known
  limitation, documented in `RELATIONS.md`: neither `mod_confprogram` nor
  `mod_confscheduler` currently reacts to a withdrawal in any way.
- User feedback (2026-07-05): "the Speakers/Speaker1 sections are still a mess ...
  Speakers should be one section containing all the speaker settings ... Speaker 1,
  Speaker 2 and so on should NOT be separate sections. Speaker order should be
  settable by drag and drop." The submission form's repeating speaker rows no longer
  each get their own collapsible formslib 'header' (which is what made every
  "Speaker N" its own section, leaving the outer "Speakers" section looking empty);
  a new `amd/src/speaker_order.js` re-parents each row's already-rendered fields into
  one card per speaker, all inside the single "Speakers" section, and drives
  `core/sortable_list` for drag-and-drop reordering of co-presenters (the primary
  presenter, row 0, stays pinned first and non-draggable, unchanged from before). The
  previously-visible "Display order" dropdown is now a hidden field the drag handler
  writes to directly.

  Investigating this also surfaced a real, separate bug: adding two or more
  co-presenter rows showed the *same* phantom "Admin User" (or, after a second
  page reload, a literal "0") pre-selected on every new row, even though none had
  actually been chosen. Root cause: the autocomplete's seed-options array had no
  entry representing "nothing selected" for a fresh row, so the underlying
  `<select>`'s native default-to-first-option behaviour (and, once `PARAM_INT`
  round-tripped a blank submission to a literal `0`, the widget's raw-value
  fallback display) both surfaced a value nobody chose. Fixed by seeding the
  options array with an explicit `0 => ''` entry.
- Added a Japanese (`lang/ja/confsubmissions.php`) language pack, translating every
  string in `lang/en/confsubmissions.php` (verified live: every key present in both,
  no extras or omissions on either side).
- **Bug fix** (user feedback, 2026-07-05): a submission's `status` column never
  changed after creation — no code anywhere wrote `accepted`/`rejected` into it, so a
  submitter's own "my submissions" view always showed "Submitted" even long after
  `mod_confprogram` recorded a decision. New `classes/api.php::set_status()` (validates
  against a new `VALID_STATUSES` allow-list) is what `mod_confprogram` now calls — but
  only once a decision is no longer Display-phase-embargoed, never at the moment it's
  first recorded during Review phase, since this status is shown directly to the
  submitter and the whole point of that embargo is to keep decisions invisible until
  an organiser explicitly switches phase. See `mod_confprogram`'s own changelog for the
  full fix.
- Initial scaffold: activity settings stub, schema (submissions, speakers,
  tracks, optional fields), capabilities, full privacy provider, and a
  `classes/api.php` integration surface for downstream conference-tools
  plugins.
- Activity settings: optional-field toggles (presentation language, teaching
  context, sub-topic area).
- Track management screen (`tracks.php`), linked from the activity
  navigation for `mod/confsubmissions:managetracks` holders.
- Submission form (`classes/form/submission_form.php`, `edit.php`): title,
  abstract, track, enabled optional fields, and a repeating speaker group
  (existing enrolled user via AJAX autocomplete, or manual name/email entry).
  Title/abstract limits are enforced server-side and shown live via an AMD
  character/word counter.
- Read-only submission detail view (`submission.php`).
- Real "My submissions" / "All submissions" listings on `view.php`, with
  track/status filtering.
- `mod_confsubmissions_search_course_users` AJAX external function backing
  the speaker-picker autocomplete.
- Security fixes from review: reject speaker `userid`s that aren't enrolled
  in the course (previously trusted unchecked, allowing identity
  impersonation via a crafted request), reject `trackid`s that don't belong
  to the current instance, cap submissions at 10 speakers, re-check
  `mod/confsubmissions:submit` when editing an existing submission (not just
  ownership), and scope/align the read-only detail view's track lookup and
  open-window check with the edit form's.
- Revision round 1 (user feedback, 2026-07-03):
  - **Track colour + icon**: new nullable `confsubmissions_track.colour`
    (hex, matching `mod_confscheduler`'s room-colour convention) and `.icon`
    (a curated Font Awesome key from a fixed allow-list — never free text or
    an uploaded asset, an XSS/file-safety risk) columns. `tracks.php` gained
    full edit support (previously add/delete only) and a colour swatch +
    icon column in the track list. `classes/api.php::get_tracks()`'s return
    shape now includes both fields, a cross-plugin contract `mod_confprogram`
    and `mod_confscheduler` both already consume.
  - **Speaker-form bug fixes**, each with a genuine root-cause fix, not a
    surface patch:
    - The Speakers section rendered empty on a new submission because
      formslib collapses every repeated header past the first two by
      default — fixed with an explicit `setExpanded()` per row.
    - The primary speaker's autocomplete showed a raw userid instead of a
      resolved name because that element only renders a name for a
      pre-selected value when a matching choice exists in its options array
      — fixed by seeding it with the current user's (and, when editing,
      every existing speaker's) resolved name.
    - A co-presenter's "manual entry" mode was forgotten on reload because
      `repeat_elements()`'s own default for the mode checkbox was applied
      *before*, and so always won over, `set_data()`'s attempt to restore
      the actually-saved value — fixed by removing that erroneous default.
    - Speaker display order is now explicitly specifiable via a "Display
      order" selector per co-presenter row (the primary speaker is always
      first and not reorderable); `extract_speakers()` stably sorts
      co-presenters by the submitted position before handing them to the
      existing, unmodified `sync_speakers()`, which already persisted
      `sortorder` from array order.
  - 26/26 PHPUnit passing (was 22), phpcs/moodlecheck clean. Verified live
    as a genuine non-admin enrolled user: the Speakers section now renders
    correctly on a new submission, a manually-entered co-presenter's mode
    and details survive a save-then-reload round trip, and the primary
    speaker's resolved name displays correctly throughout.
- Revision round 1, follow-up (user feedback, 2026-07-04): **configurable
  submission types with durations**. New `confsubmissions_submissiontype`
  table (name + `durationminutes`, organiser-managed) and a nullable
  `confsubmissions_submission.submissiontypeid` FK.
  - `submissiontypes.php` (new, gated on the existing
    `mod/confsubmissions:managetracks` capability -- no new capability was
    needed for what is the same kind of instance-configuration screen as
    `tracks.php`) gives organisers full CRUD, following that same screen's
    IDOR-scoping pattern.
  - The submission form now shows a required "Presentation type" select --
    but only once the instance has at least one type configured, mirroring
    how `trackid` degrades gracefully when an instance has no tracks. An
    instance with none configured yet is never blocked from accepting
    submissions on a choice that does not exist.
  - `mod_confscheduler` consumes each submission's type duration as the
    initial block length when it is first scheduled (both by dragging it
    out of the unscheduled panel and via the autoscheduler), falling back
    to a fixed default for a submission with no type. See that plugin's own
    changelog for the corresponding removal of its now-meaningless
    "default duration" autoscheduler setting.
  - 30/30 PHPUnit passing (was 26), phpcs/moodlecheck clean. Verified live:
    the type selector is required once types exist and rejects a type
    belonging to a different instance; a real submission's chosen type
    correctly persisted end to end into `mod_confscheduler`'s unscheduled
    panel and the resulting scheduled block's duration.
- Revision round 1, follow-up (user feedback, 2026-07-04): **dynamic,
  organiser-defined optional fields**, replacing the fixed, closed set of
  three checkboxes (language/teaching context/sub-topic) that previously
  lived directly in `mod_form.php`. Schema migrated (`2026070206`):
  `confsubmissions_field` gained `name`/`type`/`options`/`required` and
  dropped `fieldname`/`enabled`; `confsubmissions_fieldval` switched from a
  `fieldname` string key to a `fieldid` FK, since a field's machine identity
  is now its row id, not a fixed vocabulary string. A real `db/upgrade.php`
  migration backfills `name`/`type` from the old fixed fieldname for any
  pre-existing row (deleting one that was never enabled outright, since it
  was never shown to a presenter and carries no data) and re-keys existing
  answers from fieldname to fieldid.
  - `fields.php` (new, gated on the existing `mod/confsubmissions:managetracks`
    capability, matching `tracks.php`/`submissiontypes.php`) gives organisers
    full CRUD: each field gets a freely-chosen display label and one of seven
    types -- short text, long text, dropdown, checkbox, date, number, web
    address -- inspired by (a deliberately smaller subset of) `mod_data`'s
    field type system, scoped to what a submission form realistically needs.
    A dropdown field also takes a newline-separated choice list.
  - The submission form (`classes/form/submission_form.php`) renders each
    field with the moodleform element matching its own type, named
    `field_<id>` (not `field_<name>`, since a field's name is now freely
    organiser-chosen and editable later). Each field's `required` flag is
    enforced server-side explicitly in `validation()`, not merely via the
    client-only `addRule()` also attached -- formslib's own server-side
    validation cycle skips a rule registered with the `'client'` context, so
    relying on it alone would let a required field be silently bypassed by a
    JS-disabled or crafted request. A caught-in-review PHPUnit failure during
    this work confirmed the gap before it shipped.
  - 35/35 PHPUnit passing (was 30), phpcs/moodlecheck clean. Verified live
    end to end for all three non-trivial types: a required text field
    blocks submission until answered; a dropdown offers exactly its
    configured choices and rejects a tampered one server-side; a checkbox
    persists as an explicit `'0'`/`'1'` (never the empty string used
    elsewhere for "no answer", since an unanswered checkbox would otherwise
    be indistinguishable from one explicitly answered "no"); and the
    read-only submission detail view renders each type correctly (a
    checkbox as Yes/No, a date formatted via `userdate()`, everything else
    as plain text).
