<?php
defined('_SECURE_') or die('Forbidden');

function clickatell_hook_getsmsstatus($gpid = 0, $uid = "", $smslog_id = "", $p_datetime = "", $p_update = "") {
	global $plugin_config;
	list($c_sms_credit, $c_sms_status) = clickatell_getsmsstatus($smslog_id);
	// pending
	$p_status = 0;
	if ($c_sms_status) {
		$p_status = $c_sms_status;
	}
	dlr($smslog_id, $uid, $p_status);
}

function clickatell_hook_playsmsd() {

	// fetch every 60 seconds
	if (!core_playsmsd_timer(60)) {
		return;
	}

	// force to check p_status=1 (sent) as getsmsstatus only check for p_status=0 (pending)
	// $db_query = "SELECT * FROM "._DB_PREF_."_tblSMSOutgoing WHERE p_status=0 OR p_status=1";
	$db_query = "SELECT * FROM " . _DB_PREF_ . "_tblSMSOutgoing WHERE p_status='1' AND p_gateway='clickatell'";
	$db_result = dba_query($db_query);
	while ($db_row = dba_fetch_array($db_result)) {
		$uid = $db_row['uid'];
		$smslog_id = $db_row['smslog_id'];
		$p_datetime = $db_row['p_datetime'];
		$p_update = $db_row['p_update'];
		$gpid = $db_row['p_gpid'];
		core_hook('clickatell', 'getsmsstatus', array(
			$gpid,
			$uid,
			$smslog_id,
			$p_datetime,
			$p_update 
		));
	}
}

function clickatell_hook_sendsms($smsc, $sms_sender, $sms_footer, $sms_to, $sms_msg, $uid = '', $gpid = 0, $smslog_id = 0, $sms_type = 'text', $unicode = 0) {
	global $plugin_config;
	
	_log("enter smsc:" . $smsc . " smslog_id:" . $smslog_id . " uid:" . $uid . " to:" . $sms_to, 3, "clickatell_hook_sendsms");
	
	// override plugin gateway configuration by smsc configuration
	$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);
	
	$sms_sender = stripslashes($sms_sender);
	if ($plugin_config['clickatell']['module_sender']) {
		$sms_sender = $plugin_config['clickatell']['module_sender'];
	}
	
	$sms_footer = stripslashes($sms_footer);
	$sms_msg = stripslashes($sms_msg);
	$sms_from = $sms_sender;
	if ($sms_footer) {
		$sms_msg = $sms_msg . $sms_footer;
	}
	switch ($sms_type) {
		case "flash" :
			$sms_type = "SMS_FLASH";
			break;
		case "logo" :
			$sms_type = "SMS_NOKIA_OLOGO";
			break;
		case "picture" :
			$sms_type = "SMS_NOKIA_PICTURE";
			break;
		case "ringtone" :
		case "rtttl" :
			$sms_type = "SMS_NOKIA_RTTTL";
			break;
		case "text" :
		default :
			$sms_type = "SMS_TEXT";
	}
	
	// Automatically setting the unicode flag if necessary
	if (!$unicode) {
		$unicode = core_detect_unicode($sms_msg);
	}
	
	if ($unicode) {
		if (function_exists('mb_convert_encoding')) {
			$sms_msg = mb_convert_encoding($sms_msg, "UCS-2BE", "auto");
		}
		$sms_msg = core_str2hex($sms_msg);
		$unicode = 1;
	}
	
	// fixme anton - if sms_from is not set in gateway_number and global number, we cannot pass it to clickatell
	$set_sms_from = ($sms_from == $sms_sender ? '' : "&from=" . urlencode($sms_from));
	
	$query_string = "sendmsg?api_id=" . $plugin_config['clickatell']['api_id'] . "&user=" . $plugin_config['clickatell']['username'] . "&password=" . $plugin_config['clickatell']['password'] . "&to=" . urlencode($sms_to) . "&msg_type=$sms_type&text=" . urlencode($sms_msg) . "&unicode=" . $unicode . $set_sms_from;
	$url = $plugin_config['clickatell']['send_url'] . "/" . $query_string;
	
	if ($additional_param = $plugin_config['clickatell']['additional_param']) {
		$additional_param = "&" . $additional_param;
	} else {
		$additional_param = "&deliv_ack=1&callback=3";
	}
	$url .= $additional_param;
	$url = str_replace("&&", "&", $url);
	
	logger_print("url:" . $url, 3, "clickatell outgoing");
	$fd = @implode('', file($url));
	$ok = false;
	// failed
	$p_status = 2;
	if ($fd) {
		$response = explode(":", $fd);
		$err_code = trim($response[1]);
		if ((strtoupper($response[0]) == "ID")) {
			if ($apimsgid = trim($response[1])) {
				clickatell_setsmsapimsgid($smslog_id, $apimsgid);
				list($c_sms_credit, $c_sms_status) = clickatell_getsmsstatus($smslog_id);
				// pending
				$p_status = 0;
				if ($c_sms_status) {
					$p_status = $c_sms_status;
				}
			} else {
				// sent
				$p_status = 1;
			}
			logger_print("smslog_id:" . $smslog_id . " charge:" . $c_sms_credit . " sms_status:" . $p_status . " response:" . $response[0] . " " . $response[1], 2, "clickatell outgoing");
		} else {
			// even when the response is not what we expected we still print it out for debug purposes
			$fd = str_replace("\n", " ", $fd);
			$fd = str_replace("\r", " ", $fd);
			logger_print("smslog_id:" . $smslog_id . " response:" . $fd, 2, "clickatell outgoing");
		}
		$ok = true;
	}
	dlr($smslog_id, $uid, $p_status);
	return $ok;
}

function clickatell_getsmsstatus($smslog_id) {
	global $plugin_config;
	$c_sms_status = 0;
	$c_sms_credit = 0;
	$db_query = "SELECT apimsgid FROM " . _DB_PREF_ . "_gatewayClickatell_apidata WHERE smslog_id='$smslog_id'";
	$db_result = dba_query($db_query);
	$db_row = dba_fetch_array($db_result);
	if ($apimsgid = $db_row['apimsgid']) {
		$query_string = "getmsgcharge?api_id=" . $plugin_config['clickatell']['api_id'] . "&user=" . $plugin_config['clickatell']['username'] . "&password=" . $plugin_config['clickatell']['password'] . "&apimsgid=$apimsgid";
		$url = $plugin_config['clickatell']['send_url'] . "/" . $query_string;
		logger_print("smslog_id:" . $smslog_id . " apimsgid:" . $apimsgid . " url:" . $url, 3, "clickatell getsmsstatus");
		$fd = @implode('', file($url));
		if ($fd) {
			$response = explode(" ", $fd);
			$err_code = trim($response[1]);
			$credit = 0;
			if ((strtoupper(trim($response[2])) == "CHARGE:")) {
				$credit = intval(trim($response[3]));
			}
			$c_sms_credit = $credit;
			if ((strtoupper(trim($response[4])) == "STATUS:")) {
				$status = trim($response[5]);
				switch ($status) {
					case "001" :
					case "002" :
					case "011" :
						$c_sms_status = 0;
						break; // pending
					case "003" :
					case "008" :
						$c_sms_status = 1;
						break; // sent
					case "005" :
					case "006" :
					case "007" :
					case "009" :
					case "010" :
					case "012" :
						$c_sms_status = 2;
						break; // failed
					case "004" :
						$c_sms_status = 3;
						break; // delivered
				}
			}
			logger_print("smslog_id:" . $smslog_id . " apimsgid:" . $apimsgid . " charge:" . $credit . " status:" . $status . " sms_status:" . $c_sms_status, 2, "clickatell getsmsstatus");
		}
	}
	return array(
		$c_sms_credit,
		$c_sms_status 
	);
}

function clickatell_setsmsapimsgid($smslog_id, $apimsgid) {
	if ($smslog_id && $apimsgid) {
		$db_query = "INSERT INTO " . _DB_PREF_ . "_gatewayClickatell_apidata (smslog_id,apimsgid) VALUES ('$smslog_id','$apimsgid')";
		$db_result = dba_query($db_query);
	}
}

function clickatell_hook_call($requests) {
	// please note that we must globalize these 2 variables
	global $core_config, $plugin_config;
	$called_from_hook_call = true;
	$access = $requests['access'];
	if ($access == 'callback') {
		$fn = $core_config['apps_path']['plug'] . '/gateway/clickatell/callback.php';
		logger_print("start load:" . $fn, 2, "clickatell call");
		include $fn;
		logger_print("end load callback", 2, "clickatell call");
	}
}
