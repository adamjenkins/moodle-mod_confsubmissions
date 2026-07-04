# Changelog

## Unreleased

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
