<?php
/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');
if (!auth_isvalid()) {
	auth_block();
}
switch (_OP_) {
	case 'list':
		$content = _dialog() . '<h2>' . _('الإرسال من ملف') . '</h2><p />';
		if (auth_isadmin()) {
			$info_format = _('destination number, message, username');
		} else {
			$info_format = _('الرقم; نص الرسالة; اسم المرسل');
		}
		$content .= "
			<table class=ps_table>
				<tbody>
					<tr>
						<td>
							<form action=\"index.php?app=main&inc=feature_sendfromfile&op=upload_confirm\" enctype=\"multipart/form-data\" method=\"post\">
							" . _CSRF_FORM_ . "
							<p>" . _('الرجاء اختيار ملف CSV') . "</p>
							<p><input type=\"file\" name=\"fncsv\"></p>
							<p class=help-block>" . _('صيغة ملف CSV') . " : " . $info_format . "</p>
							<p><input type=checkbox name=fncsv_dup value=1 checked> " . _('منع التكرار') . "</p>
							<p><input type=\"submit\" value=\"" . _('تحميل') . "\" class=\"button\"></p>
							</form>
						</td>
					</tr>
				</tbody>
			</table>";
		_p($content);
		break;
	case 'upload_confirm':
		$filename = $_FILES['fncsv']['name'];
		$fn = $_FILES['fncsv']['tmp_name'];
		$fs = (int) $_FILES['fncsv']['size'];
		$nodups = ($_REQUEST['fncsv_dup'] ? TRUE : FALSE);
		$all_numbers = array();
		$valid = 0;
		$invalid = 0;
		$item_valid = array();
		$item_invalid = array();

		if (($fs == filesize($fn)) && file_exists($fn)) {
			if (($fd = fopen($fn, 'r')) !== FALSE) {
				$sid = md5(uniqid('SID', true));
				$continue = true;
				while ((($data = fgetcsv($fd, $fs, ';')) !== FALSE) && $continue) {
					$dup = false;
					$sms_to = trim($data[0]);
					$sms_msg = trim($data[1]);
					$sms_sender = trim($data[2]);
					if (auth_isadmin()) {
						if ($sms_username = trim($data[3])) {
							if ($uid = user_username2uid($sms_username)) {
								$data[3] = $sms_username;
							} else {
								$sms_username = $user_config['username'];
								$uid = $user_config['uid'];
								$data[3] = $sms_username;
							}
						} else {
							$sms_username = $user_config['username'];
							$uid = $user_config['uid'];
							$data[3] = $sms_username;
						}
					} else {
						$sms_username = $user_config['username'];
						$uid = $user_config['uid'];
						$data[3] = $sms_username;
					}
					if ($nodups) {
						if (in_array($sms_to, $all_numbers)) $dup = true;
					}
					if ($sms_to && $sms_msg && $uid && !$dup) {
						$all_numbers[] = $sms_to;
						$db_query = "INSERT INTO " . _DB_PREF_ . "_featureSendfromfile (uid,sid,sms_datetime,sms_to,sms_msg,sms_sender,sms_username) ";
						$db_query .= "VALUES ('$uid','$sid','" . core_get_datetime() . "','$sms_to','" . addslashes($sms_msg) . "','$sms_sender','$sms_username')";
						if ($db_result = dba_insert_id($db_query)) {
							$item_valid[$valid] = $data;
							$valid++;
						} else {
							$item_invalid[$invalid] = $data;
							$invalid++;
						}
					} else if ($sms_to || $sms_msg) {
						$item_invalid[$invalid] = $data;
						$invalid++;
					}
					$num_of_rows = $valid + $invalid;
					if ($num_of_rows >= $sendfromfile_row_limit) {
						$continue = false;
					}
				}
			}
		} else {
			$_SESSION['dialog']['danger'][] = _('إدخالات خاطئة');
			header("Location: " . _u('index.php?app=main&inc=feature_sendfromfile&op=list'));
			exit();
			break;
		}

		$content = '<h2>' . _('إرسال من ملف') . '</h2><p />';
		$content .= '<h3>' . _('تأكيد') . '</h3><p />';
		$content .= _('الملف المحمل') . ': ' . $filename . '<p />';

		if ($valid) {
			$content .= _('العثور على إدخالات صحيحة') . ' (' . _('الإدخالات الصحيحة') . ': ' . $valid . ' ' . _('من') . ' ' . $num_of_rows . ')<p />';
		  $content .= '<h4>' . _('الإدخالات الصحيحة') . '</h4>'; $content .= " <div class=table-responsive> <table class=playsms-table-list> <thead><tr> <th width=20%>" . _('الرقم') . "</th> <th width=40%>" . _('الرسالة') . "</th> <th width=20%>" . _('اسم المرسل') . "</th> <th width=20%>" . _('اسم المستخدم') . "</th> </tr></thead> <tbody>"; $j = 0; foreach ($item_valid as $item) { if ($item[0] && $item[1] && $item[2]) { $content .= " <tr> <td>" . $item[0] . "</td> <td>" . $item[1] . "</td> <td>" . $item[2] . "</td> <td>" . $item[3] . "</td></tr>"; } } $content .= "</tbody></table></div>";

		}

		if ($invalid) {
			$content .= '<p /><br />';
			$content .= _('العثور على إدخالات خاطئة') . ' (' . _('الإدخالات الخاطئة') . ': ' . $invalid . ' ' . _('من') . ' ' . $num_of_rows . ')<p />';
			$content .= '<h4>' . _('Invalid entries') . '</h4>';
			$content .= "
				<div class=table-responsive>
				<table class=playsms-table-list>
				<thead><tr>
					<th width='10%'>" . _('الرقم') . "</th>
					<th width='50%'>" . _('الرسالة') . "</th>
					<th width='20%'>" . _('المرسل') . "</th>
					<th width='20%'>" . _('المستخدم') . "</th>
				</tr></thead>";
			$j = 0;
			foreach ($item_invalid as $item) {
				if ($item[0] && $item[1] && $item[2]) {
					$content .= "
						<tr>
							<td>" . $item[0] . "</td>
							<td>" . $item[1] . "</td>
							<td>" . $item[2] . "</td>
							<td>" . $item[3] . "</td>
						</tr>";
				}
			}
			$content .= "</tbody></table></div>";
		}

		$content .= '<h4>' . _('اختيارك') . '</h4><p />';
		$content .= "<form action=\"index.php?app=main&inc=feature_sendfromfile&op=upload_cancel\" method=\"post\">";
		$content .= _CSRF_FORM_ . "<input type=hidden name=sid value='" . $sid . "'>";
		$content .= "<input type=\"submit\" value=\"" . _('الإلغاء') . "\" class=\"button\"></p>";
		$content .= "</form>";
		$content .= "<form action=\"index.php?app=main&inc=feature_sendfromfile&op=upload_process\" method=\"post\">";
		$content .= _CSRF_FORM_ . "<input type=hidden name=sid value='" . $sid . "'>";
		$content .= "<input type=\"submit\" value=\"" . _('الإرسال للإدخالات الصحيحة') . "\" class=\"button\"></p>";
		$content .= "</form>";

		_p($content);
		break;

	case 'upload_cancel':
		if ($sid = $_REQUEST['sid']) {
			$db_query = "DELETE FROM " . _DB_PREF_ . "_featureSendfromfile WHERE sid='$sid'";
			dba_query($db_query);
			$_SESSION['dialog']['danger'][] = _('تم الالغاء');
		} else {
			$_SESSION['dialog']['danger'][] = _('رقم الجلسة غير صحيح');
		}
		header("Location: " . _u('index.php?app=main&inc=feature_sendfromfile&op=list'));
		exit();
		break;

	case 'upload_process':
		@set_time_limit(0);
		if ($sid = $_REQUEST['sid']) {
			$data = array();
			$db_query = "SELECT * FROM " . _DB_PREF_ . "_featureSendfromfile WHERE sid='$sid'";
			$db_result = dba_query($db_query);
			while ($db_row = dba_fetch_array($db_result)) {
				$c_sms_to = $db_row['sms_to'];
				$c_username = $db_row['sms_username'];
				$c_sms_msg = $db_row['sms_msg'];
				$c_sms_sender = $db_row['sms_sender'];
				$c_hash = md5($c_username . $c_sms_msg);
				if ($c_sms_to && $c_username && $c_sms_msg) {
					$data[$c_hash]['username'] = $c_username;
					$data[$c_hash]['message'] = $c_sms_msg;
					$data[$c_hash]['sms_to'][] = $c_sms_to;
					$data[$c_hash]['sms_sender'] = $c_sms_sender;
				}
			}
			foreach ($data as $hash => $item) {
				$username = $item['username'];
				$message = $item['message'];
				$sms_to = $item['sms_to'];
				$sms_sender = $item['sms_sender'];
				_log('hash:' . $hash . ' u:' . $username . ' m:[' . $message . '] to_count:' . count($sms_to), 3, 'sendfromfile upload_process');
				if ($username && $message && count($sms_to)) {
					$type = 'text';
					$unicode = core_detect_unicode($message);
					$message = addslashes($message);
					list($ok, $to, $smslog_id, $queue) = sendsms_helper($username, $sms_to, $message, $type, $unicode, '', '', '', $sms_sender);
				}
			}
			$db_query = "DELETE FROM " . _DB_PREF_ . "_featureSendfromfile WHERE sid='$sid'";
			dba_query($db_query);
			$_SESSION['dialog']['info'][] = _('تم الإرسال للإدخالات الصحيحة');
		} else {
			$_SESSION['dialog']['danger'][] = _('رقم الجلسة غير صحيح');
		}
		header("Location: " . _u('index.php?app=main&inc=feature_sendfromfile&op=list'));
		exit();
		break;
}
