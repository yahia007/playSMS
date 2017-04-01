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
//require_once 'lib/Excel/reader.php';

$uid = $user_config['uid'];

switch (_OP_) {
	case "list":
		$content .= "
			<h2>" . _('Phonebook') . "</h2>
			<h3>" . _('Import') . "</h3>
						<ul class='nav nav-tabs nav-justified' id='playsms-tab'>
		<li class=active><a href='#tabs-csv' data-toggle=tab>تحميل ملف</a></li>
		<li><a href='#tabs-paste' data-toggle=tab>لصق من اكسل</a></li>
			</ul>
		<div class=tab-content>
			<div id='tabs-csv' class='tab-pane fade in active'>
			<table class=ps_table>
				<tbody>
					<tr>
						<td>
							<form action=\"index.php?app=main&inc=feature_phonebook&route=import&op=import\" enctype=\"multipart/form-data\" method=POST>
							" . _CSRF_FORM_ . "
							<p>" . _('Please select CSV file for phonebook entries') . "</p>
							<p><input type=\"file\" name=\"fnpb\"></p>
							<p class=text-info>" . _('CSV file format') . " : " . _('Name') . ", " . _('Mobile') . ", " . _('Email') . ", " . _('Group code') . ", " . _('Tags') . "</p>
							<p><input type=\"submit\" value=\"" . _('Import') . "\" class=\"button\"></p>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
			<div id='tabs-paste' class='tab-pane fade'>
			<p>ألصق البيانات هنا:</p>
			<form action=\"index.php?app=main&inc=feature_phonebook&route=import&op=paste\" method=POST>
			" . _CSRF_FORM_ . "
    		<textarea placeholder=\"الاسم | الجوال | البريد الالكتروني | المجموعة | التسميات\" name=\"excel_data\" style=\"width:100%;height:350px;\"></textarea><br>
    		<p><input type=\"submit\" value=\"" . _('Import') . "\" class=\"button\"></p>
    		</form>
			</div>
			</div>
			" . _back('index.php?app=main&inc=feature_phonebook&op=phonebook_list');
		if ($err = TRUE) {
			_p(_dialog());
		}
		_p($content);
		break;
	case "import":

		$fnpb = $_FILES['fnpb'];
		$fnpb_tmpname = $_FILES['fnpb']['tmp_name'];
		$content = "
			<h2>" . _('Phonebook') . "</h2>
			<h3>" . _('Import confirmation') . "</h3>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead><tr>
				<th width=\"5%\">*</th>
				<th width=\"20%\">" . _('Name') . "</th>
				<th width=\"20%\">" . _('Mobile') . "</th>
				<th width=\"25%\">" . _('Email') . "</th>
				<th width=\"15%\">" . _('Group code') . "</th>
				<th width=\"15%\">" . _('Tags') . "</th>
			</tr></thead><tbody>";
		function getFileDelimiter($file, $checkLines = 2){
        $file = new SplFileObject($file);
        $delimiters = array(
          ',',
          '\t',
          ';',
          '|',
          ':'
        );
        $results = array();
        for($i = 0; $i <= $checkLines; $i++){
            $line = $file->fgets();
            foreach ($delimiters as $delimiter){
                $regExp = '/['.$delimiter.']/';
                $fields = preg_split($regExp, $line);
                if(count($fields) > 1){
                    if(!empty($results[$delimiter])){
                        $results[$delimiter]++;
                    } else {
                        $results[$delimiter] = 1;
                    }   
                }
            }
        }
        $results = array_keys($results, max($results));
        return $results[0];
    }
		if (file_exists($fnpb_tmpname)) {
			$session_import = 'phonebook_' . _PID_;
			unset($_SESSION['tmp'][$session_import]);
			ini_set('auto_detect_line_endings', TRUE);

			if (($fp = fopen($fnpb_tmpname, "r")) !== FALSE) {
				$encode =mb_detect_encoding($fnpb_tmpname, "auto");
				$i = 0;
				$delimiter = getFileDelimiter($fnpb_tmpname);

				while ($c_contact = fgetcsv($fp, 1000, $delimiter, '"', '\\')) {
					if ($i > $phonebook_row_limit) {
						break;
					}
					if ($i > 0) {
						$contacts[$i] = $c_contact;
					}
					$i++;
				}
				$i = 0;
				foreach ($contacts as $contact) {
					$c_gid = phonebook_groupcode2id($uid, $contact[3]);
					if (!$c_gid) {
						$contact[3] = '';
					}
					$contact[1] = sendsms_getvalidnumber($contact[1]);
					$contact[4] = phonebook_tags_clean($contact[4]);
					if ($contact[0] && $contact[1]) {
						$i++;
						
					//$coded = iconv(mb_detect_encoding($fnpb_tmpname, mb_detect_order(), true), "UTF-8", $fnpb_tmpname);
					 	$coded = iconv("WINDOWS-1256",'UTF-8',$contact[0]."\0");

					//$coded = iconv($encode,'UTF-8',$contact[0]."\0");
 
					  
						$content .= "
							<tr>
							<td>$i.</td>
							<td>$coded</td>
							<td>$contact[1]</td>
							<td>$contact[2]</td>
							<td>$contact[3]</td>
							<td>$contact[4]</td>
							</tr>";
						$k = $i - 1;
						$_SESSION['tmp'][$session_import][$k] = $contact;
					}
				}
			}
			ini_set('auto_detect_line_endings', FALSE);
			$content .= "
				</tbody></table>
				</div>
				<p>" . _('Import above phonebook entries ?') . "</p>
				<form action=\"index.php?app=main&inc=feature_phonebook&route=import&op=import_yes\" method=POST>
				" . _CSRF_FORM_ . "
				<input type=\"hidden\" name=\"number_of_row\" value=\"$j\">
				<input type=\"hidden\" name=\"session_import\" value=\"" . $session_import . "\">
				<p><input type=\"submit\" class=\"button\" value=\"" . _('Import') . "\"></p>
				</form>
				" . _back('index.php?app=main&inc=feature_phonebook&route=import&op=list');
			_p($content);
		} else {
			$_SESSION['dialog']['info'][] = _('Fail to upload CSV file for phonebook');
			header("Location: " . _u('index.php?app=main&inc=feature_phonebook&route=import&op=list'));
			exit();
		}
		break; 

	case "paste":
		$content = "
			<h2>" . _('Phonebook') . "</h2>
			<h3>" . _('Import confirmation') . "</h3>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead><tr>
				<th width=\"5%\">*</th>
				<th width=\"20%\">" . _('Name') . "</th>
				<th width=\"20%\">" . _('Mobile') . "</th>
				<th width=\"25%\">" . _('Email') . "</th>
				<th width=\"15%\">" . _('Group code') . "</th>
				<th width=\"15%\">" . _('Tags') . "</th>
			</tr></thead><tbody>";
		if (!empty($_POST['excel_data'])) {

			$pasted = $_POST['excel_data'];
			$remove = "\n";
    		$split = explode($remove, $pasted);
			$contacts[] = null;
    		$tab = "\t";
		foreach ($split as $string){
       			 $row = explode($tab, $string);
        		 array_push($contacts,$row);
    	}			
    	$i = 0;
    	
			foreach ($contacts as $contact) {
					$c_gid = phonebook_groupcode2id($uid, $contact[3]);
					if (!$c_gid) {
						$contact[3] = '';
					}
					$contact[1] = sendsms_getvalidnumber($contact[1]);
					$contact[4] = phonebook_tags_clean($contact[4]);
					if ($contact[0] && $contact[1]) {
						$i++;
					$content .= "
							<tr>
							<td>$i.</td>
							<td>$contact[0]</td>
							<td>$contact[1]</td>
							<td>$contact[2]</td>
							<td>$contact[3]</td>
							<td>$contact[4]</td>
							</tr>";
						$k = $i - 1;
						$_SESSION['tmp'][$session_import][$k] = $contact;
					}
    			}
    			ini_set('auto_detect_line_endings', FALSE);
			$content .= "
				</tbody></table>
				</div>
				<p>" . _('Import above phonebook entries ?') . "</p>
				<form action=\"index.php?app=main&inc=feature_phonebook&route=import&op=import_yes\" method=POST>
				" . _CSRF_FORM_ . "
				<input type=\"hidden\" name=\"number_of_row\" value=\"$j\">
				<input type=\"hidden\" name=\"session_import\" value=\"" . $session_import . "\">
				<p><input type=\"submit\" class=\"button\" value=\"" . _('Import') . "\"></p>
				</form>
				" . _back('index.php?app=main&inc=feature_phonebook&route=import&op=list');
			_p($content);
		
		}else {
			$_SESSION['dialog']['info'][] = _('Fail to upload CSV file for phonebook');
			header("Location: " . _u('index.php?app=main&inc=feature_phonebook&route=import&op=list'));
			exit();
		}
		break;

	case "import_yes":
		set_time_limit(600);
		$num = $_POST['number_of_row'];
		$session_import = $_POST['session_import'];
		$data = $_SESSION['tmp'][$session_import];
		// $i = 0;
		foreach ($data as $d) {
			$name = trim($d[0]);
			$mobile = trim($d[1]);
			$email = trim($d[2]);
			if ($group_code = trim($d[3])) {
				$gpid = phonebook_groupcode2id($uid, $group_code);
			}
			$tags = phonebook_tags_clean($d[4]);
			if ($name && $mobile) {
				if ($c_pid = phonebook_number2id($uid, $mobile)) {
					$save_to_group = TRUE;
				} else {
					$items = array(
						'uid' => $uid,
						'name' => $name,
						'mobile' => sendsms_getvalidnumber($mobile),
						'email' => $email,
						'tags' => $tags 
					);
					if ($c_pid = dba_add(_DB_PREF_ . '_featurePhonebook', $items)) {
						$save_to_group = TRUE;
					} else {
						logger_print('fail to add contact pid:' . $c_pid . ' m:' . $mobile . ' n:' . $name . ' e:' . $email . ' tags:[' . $tags . ']', 3, 'phonebook_add');
					}
				}
				if ($save_to_group && $gpid) {
					$items = array(
						'gpid' => $gpid,
						'pid' => $c_pid 
					);
					if (dba_isavail(_DB_PREF_ . '_featurePhonebook_group_contacts', $items, 'AND')) {
						if (dba_add(_DB_PREF_ . '_featurePhonebook_group_contacts', $items)) {
							logger_print('contact added to group gpid:' . $gpid . ' pid:' . $c_pid . ' m:' . $mobile . ' n:' . $name . ' e:' . $email, 3, 'phonebook_add');
						} else {
							logger_print('contact added but fail to save in group gpid:' . $gpid . ' pid:' . $c_pid . ' m:' . $mobile . ' n:' . $name . ' e:' . $email, 3, 'phonebook_add');
						}
					}
				}
				// $i++;
				// logger_print("no:".$i." gpid:".$gpid." uid:".$uid." name:".$name." mobile:".$mobile." email:".$email, 3, "phonebook import");
			}
			unset($gpid);
		}
		$_SESSION['dialog']['info'][] = _('Contacts have been imported');
		header("Location: " . _u('index.php?app=main&inc=feature_phonebook&route=import&op=list'));
		exit();
		break;
}
