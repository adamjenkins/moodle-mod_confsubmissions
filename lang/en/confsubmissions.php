<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['abstract'] = 'Abstract';
$string['abstractlimit'] = 'Abstract length limit';
$string['abstractlimit_help'] = 'The maximum length of a submitted abstract. Set to 0 for no limit.';
$string['addfield'] = 'Add field';
$string['addspeaker'] = 'Add another speaker';
$string['addsubmissiontype'] = 'Add submission type';
$string['addtrack'] = 'Add track';
$string['allstatuses'] = 'All statuses';
$string['allsubmissions'] = 'All submissions';
$string['alltracks'] = 'All tracks';
$string['callnotopen'] = 'Submissions are not currently open.';
$string['conferenceend'] = 'Conference end';
$string['conferenceend_help'] = 'The date/time the conference ends. Optional -- only used to generate the day range for "Offer preferred dates" checkboxes, if that is enabled below; it does not restrict anything else in this activity.';
$string['conferencestart'] = 'Conference start';
$string['conferencestart_help'] = 'The date/time the conference starts. Optional -- only used to generate the day range for "Offer preferred dates" checkboxes, if that is enabled below; it does not restrict anything else in this activity.';
$string['confirmdeletefield'] = 'Are you sure you want to delete the field "{$a}"? Any answers submitters gave to it will be deleted too.';
$string['confirmdeletesubmission'] = 'Permanently delete the submission "{$a}"? This cannot be undone, and removes it completely (unlike Withdraw, which just changes its status).';
$string['confirmdeletesubmissiontype'] = 'Are you sure you want to delete the submission type "{$a}"? Any submissions using it will be left with no type assigned.';
$string['confirmdeletetrack'] = 'Are you sure you want to delete the track "{$a}"? Any submissions using it will be left with no track assigned.';
$string['confirmunwithdraw'] = 'Unwithdraw the submission "{$a}"? It will return to "Submitted" status.';
$string['confirmwithdraw'] = 'Withdraw the submission "{$a}"? You can contact the organiser if you need to resubmit it later.';
$string['confsubmissions:addinstance'] = 'Add a new Conference Submissions activity';
$string['confsubmissions:deleteany'] = 'Permanently delete any submission';
$string['confsubmissions:editany'] = 'Edit any submission';
$string['confsubmissions:manageform'] = 'Manage submission form settings (limits, open/close dates, disabled preferred days)';
$string['confsubmissions:managenotifications'] = 'Manage notification templates';
$string['confsubmissions:managetracks'] = 'Manage submission tracks, optional fields and submission types';
$string['confsubmissions:submit'] = 'Submit an abstract';
$string['confsubmissions:viewall'] = 'View all submissions';
$string['confsubmissions:viewown'] = 'View own submissions';
$string['disabledaycheckbox'] = 'Disable {$a}';
$string['disableddatereason'] = 'Reason (optional)';
$string['disableddatereasonfor'] = 'Reason for disabling {$a} (optional)';
$string['disableddatessaved'] = 'Disabled preferred days saved.';
$string['editfield'] = 'Edit field';
$string['editinganothersubmission'] = 'You are editing this submission on behalf of {$a}.';
$string['editsubmission'] = 'Edit submission';
$string['editsubmissiontype'] = 'Edit submission type';
$string['edittrack'] = 'Edit track';
$string['entermanually'] = 'Enter name/email manually';
$string['error:abstracttoolong'] = 'The abstract exceeds the {$a->limit} {$a->type} limit (currently {$a->count}).';
$string['error:closebeforeopen'] = 'The close date cannot be earlier than the open date.';
$string['error:conferenceendbeforestart'] = 'The conference end date must be after the conference start date.';
$string['error:fieldtypechangehasvalues'] = 'This field already has submitted answers, so its type can no longer be changed. Delete the field (which removes its answers) and add a new one instead.';
$string['error:invalidcolour'] = 'The colour must be a valid 6-digit hex colour (e.g. #3366cc), or left blank.';
$string['error:invalidduration'] = 'The duration must be a whole number of minutes greater than 0.';
$string['error:invalidfieldnumber'] = 'Please enter a number.';
$string['error:invalidfieldoption'] = 'That is not one of the available choices for this field.';
$string['error:invalidfieldoptions'] = 'A dropdown field needs at least one choice, one per line.';
$string['error:invalidfieldtype'] = 'That is not a recognised field type.';
$string['error:invalidfieldurl'] = 'Please enter a valid web address (e.g. https://example.com).';
$string['error:invalidicon'] = 'That icon is not one of the available choices.';
$string['error:invalidnotiftype'] = 'That is not a recognised notification type.';
$string['error:invalidstatus'] = 'That is not a recognised submission status.';
$string['error:invalidsubmissiontype'] = 'Please choose a submission type.';
$string['error:invalidtrack'] = 'That track is not available for this activity.';
$string['error:limitnegative'] = 'The limit cannot be negative.';
$string['error:needsspeaker'] = 'At least one speaker is required.';
$string['error:notowner'] = 'You do not have permission to edit this submission.';
$string['error:notwithdrawn'] = 'That submission is not currently withdrawn.';
$string['error:preferreddatesneedconferencedates'] = 'Set both conference start and end dates above before enabling this.';
$string['error:titletoolong'] = 'The title exceeds the {$a->limit} {$a->type} limit (currently {$a->count}).';
$string['error:toomanyspeakers'] = 'A submission cannot have more than {$a} speakers.';
$string['error:usernotenrolled'] = 'The selected user is not enrolled in this course.';
$string['fieldadded'] = 'Field added.';
$string['fielddeleted'] = 'Field deleted.';
$string['fieldlabel'] = 'Label';
$string['fieldlist'] = 'Fields';
$string['fieldoptions'] = 'Choices (one per line)';
$string['fieldoptions_help'] = 'The list of choices a presenter can pick from, one per line. Only used when the field type is Dropdown.';
$string['fieldrequired'] = 'Required';
$string['fieldtype'] = 'Field type';
$string['fieldtype_checkbox'] = 'Checkbox (yes/no)';
$string['fieldtype_date'] = 'Date';
$string['fieldtype_menu'] = 'Dropdown';
$string['fieldtype_number'] = 'Number';
$string['fieldtype_text'] = 'Short text';
$string['fieldtype_textarea'] = 'Long text';
$string['fieldtype_url'] = 'Web address';
$string['fieldupdated'] = 'Field updated.';
$string['lastmodified'] = 'Last modified';
$string['limittype_chars'] = 'characters';
$string['limittype_words'] = 'words';
$string['managedisableddates'] = 'Manage disabled preferred days';
$string['managedisableddates_help'] = 'Check any day you do not want offered to regular submitters as a preferred date. Optionally give a short reason (e.g. "Venue unavailable") -- it is shown in parentheses next to the greyed-out day on the submission form. A user with permission to manage this activity\'s form settings still sees and can select every day, disabled or not, on the submission form.';
$string['managefields'] = 'Manage fields';
$string['managenotifications'] = 'Manage notifications';
$string['managesubmissiontypes'] = 'Manage submission types';
$string['managetracks'] = 'Manage tracks';
$string['messageprovider:submissioncreated'] = 'A submission you are a speaker on has been made';
$string['messageprovider:submissionwithdrawn'] = 'A submission has been withdrawn';
$string['modulename'] = 'Conference Submissions';
$string['modulename_help'] = 'The Conference Submissions activity lets presenters submit a title, abstract, and speaker information (including co-presenters) to a conference track. Organisers configure per-instance title/abstract limits, an open/close window, optional fields, and tracks used later for review and scheduling.';
$string['modulenameplural'] = 'Conference Submissions';
$string['mysubmissions'] = 'My submissions';
$string['newsubmission'] = 'New submission';
$string['nodisableddatesconferencedates'] = 'Set both conference start and end dates in this activity\'s settings before managing disabled preferred days.';
$string['nofields'] = 'No fields have been added yet.';
$string['noinstances'] = 'There are no Conference Submissions activities in this course yet.';
$string['nosubmissionsfound'] = 'No submissions found.';
$string['nosubmissionsyet'] = 'You have not made any submissions yet.';
$string['nosubmissiontypes'] = 'No submission types have been added yet.';
$string['notifbody'] = 'Message';
$string['notifbody_help'] = 'The notification email body, sent via Moodle\'s own notification system (and by email by default). Use [[fullname]], [[submissiontitle]], [[coursename]] (and, for the withdrawal notification, [[submitterfullname]] in place of [[fullname]], since that notification goes to editingteachers, not the submitter).';
$string['notifdefaultbody:created'] = '<p>Hello [[fullname]],</p><p>Your submission "[[submissiontitle]]" has been received for [[coursename]].</p>';
$string['notifdefaultbody:withdrawn'] = '<p>Hello,</p><p>The submission "[[submissiontitle]]" by [[submitterfullname]] has been withdrawn from [[coursename]].</p>';
$string['notifdefaultsubject:created'] = 'Submission received: [[submissiontitle]]';
$string['notifdefaultsubject:withdrawn'] = 'Submission withdrawn: [[submissiontitle]]';
$string['notificationsenabled'] = 'Enable notifications';
$string['notificationsenabled_help'] = 'Master switch for this activity: when unchecked, no notification (submission made, submission withdrawn) is ever sent from this instance, regardless of the templates configured below.';
$string['notifplaceholders'] = 'Available placeholders: {$a}.';
$string['notifsubject'] = 'Subject';
$string['notifsubject_help'] = 'The notification email subject line. Same placeholders as the message body below.';
$string['notiftemplatesaved'] = 'Notification template saved.';
$string['notiftype:created'] = 'Submission made';
$string['notiftype:withdrawn'] = 'Submission withdrawn';
$string['notrack'] = 'No track';
$string['notracks'] = 'No tracks have been added yet.';
$string['offerpreferreddates'] = 'Offer preferred dates';
$string['offerpreferreddates_desc'] = 'Show submitters a set of preferred-date checkboxes';
$string['offerpreferreddates_help'] = 'When enabled, a submitter sees one checkbox per day in the conference date range above (all checked by default), so they can uncheck any day they cannot attend. mod_confscheduler\'s autoscheduler tries to honour these when placing a submission (it still shuffles time of day; only the day is preference-aware), and its edit-mode unscheduled-submissions panel hides a submission entirely when the currently-selected day is not one of its preferred days. Requires both conference dates above to be set.';
$string['pluginadministration'] = 'Conference Submissions administration';
$string['pluginname'] = 'Conference Submissions';
$string['preferreddates'] = 'Preferred dates';
$string['preferreddates_help'] = 'Uncheck any day you are not available to present. All days are checked by default. This only affects which day the autoscheduler tries to place your presentation on; you can still be manually rescheduled to any day by an organiser.';
$string['primaryspeaker'] = 'Primary speaker';
$string['privacy:metadata:confsubmissions_datepref'] = 'A submitter\'s preferred conference days for a submission.';
$string['privacy:metadata:confsubmissions_datepref:prefdate'] = 'A preferred conference day, as a timestamp of local midnight.';
$string['privacy:metadata:confsubmissions_fieldval'] = 'The submitter\'s answers to this activity\'s optional fields.';
$string['privacy:metadata:confsubmissions_fieldval:fieldid'] = 'The id of the optional field being answered.';
$string['privacy:metadata:confsubmissions_fieldval:value'] = 'The submitter\'s answer to the optional field.';
$string['privacy:metadata:confsubmissions_speaker'] = 'The speakers (primary presenter and co-presenters) attached to a submission.';
$string['privacy:metadata:confsubmissions_speaker:email'] = 'The email address of the speaker, when entered manually.';
$string['privacy:metadata:confsubmissions_speaker:name'] = 'The name of the speaker, when entered manually.';
$string['privacy:metadata:confsubmissions_speaker:role'] = 'The speaker\'s role on the submission (e.g. primary or co-presenter).';
$string['privacy:metadata:confsubmissions_speaker:userid'] = 'The ID of the speaker, when they are an enrolled Moodle user.';
$string['privacy:metadata:confsubmissions_submission'] = 'A presenter\'s submission to a call for abstracts, including its title, abstract text, and workflow status.';
$string['privacy:metadata:confsubmissions_submission:abstract'] = 'The abstract text of the submission.';
$string['privacy:metadata:confsubmissions_submission:status'] = 'The workflow status of the submission (e.g. submitted, accepted, rejected).';
$string['privacy:metadata:confsubmissions_submission:timecreated'] = 'The time the submission was created.';
$string['privacy:metadata:confsubmissions_submission:timemodified'] = 'The time the submission was last modified.';
$string['privacy:metadata:confsubmissions_submission:title'] = 'The title of the submission.';
$string['privacy:metadata:confsubmissions_submission:userid'] = 'The ID of the user who submitted the abstract.';
$string['removespeaker'] = 'Remove speaker {$a}';
$string['removesubmissions'] = 'Delete all submissions';
$string['selectuser'] = 'Select user';
$string['speakeremail'] = 'Email';
$string['speakername'] = 'Name';
$string['speakerno'] = 'Speaker {$a}';
$string['speakerposition'] = 'Drag to reorder this speaker';
$string['speakers'] = 'Speakers';
$string['status'] = 'Status';
$string['status_accepted'] = 'Accepted';
$string['status_rejected'] = 'Rejected';
$string['status_submitted'] = 'Submitted';
$string['status_withdrawn'] = 'Withdrawn';
$string['submissiondeleted'] = 'Submission deleted.';
$string['submissiondetails'] = 'Submission details';
$string['submissionsaved'] = 'Submission saved.';
$string['submissionsettings'] = 'Submission settings';
$string['submissiontype'] = 'Presentation type';
$string['submissiontypeadded'] = 'Submission type added.';
$string['submissiontypedeleted'] = 'Submission type deleted.';
$string['submissiontypeduration'] = 'Duration (minutes)';
$string['submissiontypeduration_help'] = 'The default presentation length, in minutes, for a submission of this type. mod_confscheduler uses this as the initial block length when a presentation of this type is first scheduled; the block can still be resized afterwards without affecting this setting.';
$string['submissiontypelist'] = 'Submission types';
$string['submissiontypename'] = 'Name';
$string['submissiontypeupdated'] = 'Submission type updated.';
$string['submissionunwithdrawn'] = 'Submission unwithdrawn.';
$string['submissionwithdrawn'] = 'Submission withdrawn.';
$string['submitted'] = 'Submitted';
$string['timeclose'] = 'Submissions close';
$string['timeclose_help'] = 'The date and time after which submissions can no longer be made. Leave disabled for no closing date.';
$string['timeopen'] = 'Submissions open';
$string['timeopen_help'] = 'The date and time from which submissions can be made. Leave disabled for no opening restriction.';
$string['title'] = 'Title';
$string['titlelimit'] = 'Title length limit';
$string['titlelimit_help'] = 'The maximum length of a submitted title. Set to 0 for no limit.';
$string['track'] = 'Track';
$string['trackadded'] = 'Track added.';
$string['trackcolour'] = 'Colour';
$string['trackcolour_help'] = 'A 6-digit hex colour code (e.g. #3366cc) used to theme this track in downstream displays. Leave blank for no colour.';
$string['trackdeleted'] = 'Track deleted.';
$string['trackicon'] = 'Icon';
$string['trackicon_book'] = 'Book';
$string['trackicon_camera'] = 'Camera (media)';
$string['trackicon_chart_bar'] = 'Bar chart';
$string['trackicon_code'] = 'Code';
$string['trackicon_comments'] = 'Discussion/comments';
$string['trackicon_flask'] = 'Flask (research)';
$string['trackicon_globe'] = 'Globe (global/international)';
$string['trackicon_graduation_cap'] = 'Graduation cap (education)';
$string['trackicon_laptop_code'] = 'Laptop with code';
$string['trackicon_leaf'] = 'Leaf (sustainability)';
$string['trackicon_lightbulb'] = 'Lightbulb (ideas)';
$string['trackicon_microphone'] = 'Microphone (talk)';
$string['trackicon_none'] = 'None';
$string['trackicon_paintbrush'] = 'Paintbrush (design)';
$string['trackicon_puzzle_piece'] = 'Puzzle piece (integration)';
$string['trackicon_rocket'] = 'Rocket (innovation)';
$string['trackicon_shield_halved'] = 'Shield (security)';
$string['trackicon_users'] = 'People';
$string['trackicon_wrench'] = 'Wrench (tools/ops)';
$string['tracklist'] = 'Existing tracks';
$string['trackname'] = 'Track name';
$string['trackupdated'] = 'Track updated.';
$string['unwithdraw'] = 'Unwithdraw';
$string['withdraw'] = 'Withdraw';
