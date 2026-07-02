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
