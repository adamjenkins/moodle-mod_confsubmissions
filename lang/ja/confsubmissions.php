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
 * Language strings for mod_confsubmissions (Japanese).
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['abstract'] = 'アブストラクト';
$string['abstractlimit'] = 'アブストラクトの文字数制限';
$string['abstractlimit_help'] = '提出できるアブストラクトの最大文字数（または語数）です。0 にすると制限なしになります。';
$string['addfield'] = '項目を追加';
$string['addspeaker'] = '発表者を追加';
$string['addsubmissiontype'] = '発表タイプを追加';
$string['addtrack'] = 'トラックを追加';
$string['allstatuses'] = 'すべてのステータス';
$string['allsubmissions'] = 'すべての応募';
$string['alltracks'] = 'すべてのトラック';
$string['callnotopen'] = '現在、応募の受付は行っていません。';
$string['conferenceend'] = '開催終了日時';
$string['conferenceend_help'] = '開催イベントの終了日時です。任意項目です——下の「Offer preferred dates（希望日程を提示する）」を有効にした場合の日程チェックボックスの範囲を生成する目的でのみ使用され、このアクティビティの他の動作を制限するものではありません。';
$string['conferencestart'] = '開催開始日時';
$string['conferencestart_help'] = '開催イベントの開始日時です。任意項目です——下の「Offer preferred dates（希望日程を提示する）」を有効にした場合の日程チェックボックスの範囲を生成する目的でのみ使用され、このアクティビティの他の動作を制限するものではありません。';
$string['confirmdeletefield'] = '項目「{$a}」を削除しますか？ この項目に対する回答もすべて削除されます。';
$string['confirmdeletesubmission'] = '応募「{$a}」を完全に削除しますか？ この操作は取り消せず、応募を完全に削除します（ステータスを変更するだけの「Withdraw（取り下げ）」とは異なります）。';
$string['confirmdeletesubmissiontype'] = '発表タイプ「{$a}」を削除しますか？ このタイプを使用している応募はタイプ未設定になります。';
$string['confirmdeletetrack'] = 'トラック「{$a}」を削除しますか？ このトラックを使用している応募はトラック未設定になります。';
$string['confirmwithdraw'] = '応募「{$a}」を取り下げますか？ 後で再提出したい場合は主催者にご連絡ください。';
$string['confsubmissions:addinstance'] = 'Conference Submissions アクティビティを新規追加する';
$string['confsubmissions:deleteany'] = '任意の応募を完全に削除する';
$string['confsubmissions:manageform'] = '応募フォームの設定（文字数制限、受付開始・終了日時）を管理する';
$string['confsubmissions:managenotifications'] = '通知テンプレートを管理する';
$string['confsubmissions:managetracks'] = '応募トラックを管理する';
$string['confsubmissions:submit'] = 'アブストラクトを応募する';
$string['confsubmissions:viewall'] = 'すべての応募を閲覧する';
$string['confsubmissions:viewown'] = '自分の応募を閲覧する';
$string['disableddatessaved'] = '無効化した希望日程を保存しました。';
$string['editfield'] = '項目を編集';
$string['editsubmission'] = '応募を編集';
$string['editsubmissiontype'] = '発表タイプを編集';
$string['edittrack'] = 'トラックを編集';
$string['entermanually'] = '名前・メールアドレスを手動で入力';
$string['error:abstracttoolong'] = 'アブストラクトが{$a->limit}{$a->type}の上限を超えています（現在{$a->count}）。';
$string['error:closebeforeopen'] = '受付終了日を受付開始日より前の日付にすることはできません。';
$string['error:conferenceendbeforestart'] = '開催終了日時は開催開始日時より後にしてください。';
$string['error:invalidcolour'] = '色は有効な6桁の16進数カラーコード（例: #3366cc）にするか、空欄にしてください。';
$string['error:invalidduration'] = '所要時間は0より大きい整数（分）で入力してください。';
$string['error:invalidfieldnumber'] = '数値を入力してください。';
$string['error:invalidfieldoption'] = 'この項目で選択できる選択肢ではありません。';
$string['error:invalidfieldoptions'] = 'ドロップダウン項目には、1行につき1つずつ、少なくとも1つの選択肢が必要です。';
$string['error:invalidfieldtype'] = '認識できない項目タイプです。';
$string['error:invalidfieldurl'] = '有効なWebアドレスを入力してください（例: https://example.com）。';
$string['error:invalidicon'] = 'そのアイコンは選択可能な候補にありません。';
$string['error:invalidnotiftype'] = '認識できない通知種別です。';
$string['error:invalidstatus'] = '認識できない応募ステータスです。';
$string['error:invalidsubmissiontype'] = '発表タイプを選択してください。';
$string['error:invalidtrack'] = 'そのトラックはこのアクティビティでは利用できません。';
$string['error:limitnegative'] = '上限に負の数は指定できません。';
$string['error:needsspeaker'] = '少なくとも1名の発表者が必要です。';
$string['error:notowner'] = 'この応募を編集する権限がありません。';
$string['error:preferreddatesneedconferencedates'] = 'これを有効にする前に、上の開催開始日時・終了日時の両方を設定してください。';
$string['error:titletoolong'] = 'タイトルが{$a->limit}{$a->type}の上限を超えています（現在{$a->count}）。';
$string['error:toomanyspeakers'] = '1件の応募につき発表者は{$a}名までです。';
$string['error:usernotenrolled'] = '選択されたユーザーはこのコースに登録されていません。';
$string['fieldadded'] = '項目を追加しました。';
$string['fielddeleted'] = '項目を削除しました。';
$string['fieldlabel'] = 'ラベル';
$string['fieldlist'] = '項目一覧';
$string['fieldoptions'] = '選択肢（1行に1つ）';
$string['fieldoptions_help'] = '発表者が選べる選択肢の一覧です。1行に1つずつ入力してください。項目タイプが「ドロップダウン」の場合のみ使用されます。';
$string['fieldrequired'] = '必須';
$string['fieldtype'] = '項目タイプ';
$string['fieldtype_checkbox'] = 'チェックボックス（はい/いいえ）';
$string['fieldtype_date'] = '日付';
$string['fieldtype_menu'] = 'ドロップダウン';
$string['fieldtype_number'] = '数値';
$string['fieldtype_text'] = '短いテキスト';
$string['fieldtype_textarea'] = '長いテキスト';
$string['fieldtype_url'] = 'Webアドレス';
$string['fieldupdated'] = '項目を更新しました。';
$string['lastmodified'] = '最終更新日時';
$string['limittype_chars'] = '文字';
$string['limittype_words'] = '語';
$string['managedisableddates'] = '無効にする希望日程の管理';
$string['managedisableddates_help'] = '一般の応募者に希望日程として提示したくない日にチェックを入れてください。このアクティビティのフォーム設定を管理する権限を持つユーザーには、無効化の有無にかかわらず、応募フォーム上ですべての日が表示され、選択できます。';
$string['managefields'] = '項目の管理';
$string['managenotifications'] = '通知の管理';
$string['managesubmissiontypes'] = '発表タイプの管理';
$string['managetracks'] = 'トラックの管理';
$string['messageprovider:submissioncreated'] = 'あなたが発表者に含まれる応募が提出されたとき';
$string['messageprovider:submissionwithdrawn'] = '応募が取り下げられたとき';
$string['modulename'] = 'Conference Submissions';
$string['modulename_help'] = 'Conference Submissions アクティビティは、発表者がタイトル・アブストラクト・発表者情報（共同発表者を含む）をトラックに応募するためのものです。主催者はインスタンスごとに、タイトル・アブストラクトの文字数制限、受付開始・終了期間、任意項目、そして後の査読・スケジューリングで使用するトラックを設定できます。';
$string['modulenameplural'] = 'Conference Submissions';
$string['mysubmissions'] = '自分の応募';
$string['newsubmission'] = '新規応募';
$string['nodisableddatesconferencedates'] = '無効にする希望日程を管理する前に、このアクティビティの設定で開催開始日時・終了日時の両方を設定してください。';
$string['nofields'] = 'まだ項目が追加されていません。';
$string['noinstances'] = 'このコースにはまだ Conference Submissions アクティビティがありません。';
$string['nosubmissionsfound'] = '応募が見つかりません。';
$string['nosubmissionsyet'] = 'まだ応募していません。';
$string['nosubmissiontypes'] = 'まだ発表タイプが追加されていません。';
$string['notifbody'] = '本文';
$string['notifbody_help'] = '通知メールの本文です。Moodle 自体の通知システム（既定でメール送信も行われます）を通じて送信されます。[[fullname]]、[[submissiontitle]]、[[coursename]] を使用できます（取り下げ通知では、editingteacher 宛てとなるため [[fullname]] の代わりに [[submitterfullname]] を使用します）。';
$string['notifdefaultbody:created'] = '<p>[[fullname]] 様</p><p>[[coursename]] にて、あなたの応募「[[submissiontitle]]」を受け付けました。</p>';
$string['notifdefaultbody:withdrawn'] = '<p>ご担当者様</p><p>[[coursename]] にて、[[submitterfullname]] による応募「[[submissiontitle]]」が取り下げられました。</p>';
$string['notifdefaultsubject:created'] = '応募を受け付けました: [[submissiontitle]]';
$string['notifdefaultsubject:withdrawn'] = '応募が取り下げられました: [[submissiontitle]]';
$string['notificationsenabled'] = '通知を有効にする';
$string['notificationsenabled_help'] = 'このアクティビティのマスタースイッチです。チェックを外すと、下記のテンプレート設定にかかわらず、このインスタンスから通知（応募受付・応募取り下げ）が一切送信されなくなります。';
$string['notifplaceholders'] = '利用可能なプレースホルダー: {$a}。';
$string['notifsubject'] = '件名';
$string['notifsubject_help'] = '通知メールの件名です。下の本文と同じプレースホルダーが使用できます。';
$string['notiftemplatesaved'] = '通知テンプレートを保存しました。';
$string['notiftype:created'] = '応募が提出されたとき';
$string['notiftype:withdrawn'] = '応募が取り下げられたとき';
$string['notrack'] = 'トラックなし';
$string['notracks'] = 'まだトラックが追加されていません。';
$string['offerpreferreddates'] = '希望日程を提示する';
$string['offerpreferreddates_desc'] = '応募者に希望日程のチェックボックスを表示する';
$string['offerpreferreddates_help'] = '有効にすると、応募者には上の開催期間内の日ごとに1つずつチェックボックスが表示されます（デフォルトはすべてチェック済み）。応募者は参加できない日のチェックを外せます。mod_confscheduler の自動スケジューラーは、応募を配置する際にこれらの希望をできる限り尊重しようとします（時間帯は引き続きシャッフルされ、日付のみが希望を考慮した対象です）。また、編集モードの未配置応募パネルは、現在選択されている日がその応募の希望日程に含まれない場合、その応募を完全に非表示にします。有効にするには上の開催開始日時・終了日時の両方が設定されている必要があります。';
$string['pluginadministration'] = 'Conference Submissions の管理';
$string['pluginname'] = 'Conference Submissions';
$string['preferreddates'] = '希望日程';
$string['preferreddates_help'] = '発表できない日のチェックを外してください。デフォルトではすべての日にチェックが入っています。これは自動スケジューラーがどの日に発表を配置しようとするかにのみ影響します。主催者による手動での再スケジュールでは、どの日にも変更され得ます。';
$string['primaryspeaker'] = '主発表者';
$string['privacy:metadata:confsubmissions_datepref'] = '応募に対する応募者の希望日程。';
$string['privacy:metadata:confsubmissions_datepref:prefdate'] = '希望する開催日（現地時間の午前0時のタイムスタンプ）。';
$string['privacy:metadata:confsubmissions_fieldval'] = 'このアクティビティの任意項目に対する応募者の回答。';
$string['privacy:metadata:confsubmissions_fieldval:fieldid'] = '回答対象の任意項目のID。';
$string['privacy:metadata:confsubmissions_fieldval:value'] = '任意項目に対する応募者の回答内容。';
$string['privacy:metadata:confsubmissions_speaker'] = '応募に紐づく発表者（主発表者および共同発表者）。';
$string['privacy:metadata:confsubmissions_speaker:email'] = '手動で入力された場合の発表者のメールアドレス。';
$string['privacy:metadata:confsubmissions_speaker:name'] = '手動で入力された場合の発表者の氏名。';
$string['privacy:metadata:confsubmissions_speaker:role'] = '応募における発表者の役割（主発表者/共同発表者など）。';
$string['privacy:metadata:confsubmissions_speaker:userid'] = '登録済みの Moodle ユーザーである場合の発表者のID。';
$string['privacy:metadata:confsubmissions_submission'] = 'タイトル・アブストラクト本文・ワークフローステータスを含む、発表者からの応募。';
$string['privacy:metadata:confsubmissions_submission:abstract'] = '応募のアブストラクト本文。';
$string['privacy:metadata:confsubmissions_submission:status'] = '応募のワークフローステータス（応募済み・採択・却下など）。';
$string['privacy:metadata:confsubmissions_submission:timecreated'] = '応募が作成された日時。';
$string['privacy:metadata:confsubmissions_submission:timemodified'] = '応募が最後に更新された日時。';
$string['privacy:metadata:confsubmissions_submission:title'] = '応募のタイトル。';
$string['privacy:metadata:confsubmissions_submission:userid'] = 'アブストラクトを応募したユーザーのID。';
$string['removespeaker'] = '発表者{$a}を削除';
$string['selectuser'] = 'ユーザーを選択';
$string['speakeremail'] = 'メールアドレス';
$string['speakername'] = '氏名';
$string['speakerno'] = '発表者{$a}';
$string['speakerposition'] = 'ドラッグしてこの発表者の順序を変更';
$string['speakers'] = '発表者';
$string['status'] = 'ステータス';
$string['status_accepted'] = '採択';
$string['status_rejected'] = '却下';
$string['status_submitted'] = '応募済み';
$string['status_withdrawn'] = '取り下げ済み';
$string['submissiondeleted'] = '応募を削除しました。';
$string['submissiondetails'] = '応募内容';
$string['submissionsaved'] = '応募を保存しました。';
$string['submissionsettings'] = '応募設定';
$string['submissiontype'] = '発表タイプ';
$string['submissiontypeadded'] = '発表タイプを追加しました。';
$string['submissiontypedeleted'] = '発表タイプを削除しました。';
$string['submissiontypeduration'] = '所要時間（分）';
$string['submissiontypeduration_help'] = 'この発表タイプの標準的な発表時間（分）です。mod_confscheduler では、このタイプの発表を最初にスケジュールする際の初期のブロック長として使用されます。ブロックは後からサイズ変更でき、この設定自体には影響しません。';
$string['submissiontypelist'] = '発表タイプ一覧';
$string['submissiontypename'] = '名前';
$string['submissiontypeupdated'] = '発表タイプを更新しました。';
$string['submissionwithdrawn'] = '応募を取り下げました。';
$string['submitted'] = '応募済み';
$string['timeclose'] = '応募締切';
$string['timeclose_help'] = 'これ以降は応募できなくなる日時です。締切を設けない場合は無効のままにしてください。';
$string['timeopen'] = '応募開始';
$string['timeopen_help'] = 'これ以降に応募が可能になる日時です。開始制限を設けない場合は無効のままにしてください。';
$string['title'] = 'タイトル';
$string['titlelimit'] = 'タイトルの文字数制限';
$string['titlelimit_help'] = '提出できるタイトルの最大文字数です。0 にすると制限なしになります。';
$string['track'] = 'トラック';
$string['trackadded'] = 'トラックを追加しました。';
$string['trackcolour'] = '色';
$string['trackcolour_help'] = '6桁の16進数カラーコード（例: #3366cc）で、関連画面でこのトラックを色分け表示する際に使われます。空欄にすると色は付きません。';
$string['trackdeleted'] = 'トラックを削除しました。';
$string['trackicon'] = 'アイコン';
$string['trackicon_book'] = '本';
$string['trackicon_camera'] = 'カメラ（メディア）';
$string['trackicon_chart_bar'] = '棒グラフ';
$string['trackicon_code'] = 'コード';
$string['trackicon_comments'] = 'ディスカッション/コメント';
$string['trackicon_flask'] = 'フラスコ（研究）';
$string['trackicon_globe'] = '地球儀（グローバル/国際）';
$string['trackicon_graduation_cap'] = '角帽（教育）';
$string['trackicon_laptop_code'] = 'コード付きノートPC';
$string['trackicon_leaf'] = '葉（サステナビリティ）';
$string['trackicon_lightbulb'] = '電球（アイデア）';
$string['trackicon_microphone'] = 'マイク（講演）';
$string['trackicon_none'] = 'なし';
$string['trackicon_paintbrush'] = '筆（デザイン）';
$string['trackicon_puzzle_piece'] = 'パズルピース（連携）';
$string['trackicon_rocket'] = 'ロケット（イノベーション）';
$string['trackicon_shield_halved'] = '盾（セキュリティ）';
$string['trackicon_users'] = '人（複数）';
$string['trackicon_wrench'] = 'レンチ（ツール/運用）';
$string['tracklist'] = '既存のトラック';
$string['trackname'] = 'トラック名';
$string['trackupdated'] = 'トラックを更新しました。';
$string['withdraw'] = '取り下げ';
