<?php
defined('_SECURE_') or die('Forbidden');

// arabian loaded page/plugin
/*
 * $themes_config['arabian']['main'] = array( 'arabian_inc' => 'feature_report', 'arabian_route' => 'user', 'arabian_op' => '', );
 */

// override common action icons
/*
 * $themes_config['arabian']['icon'] = array( 'edit' => "<span class='playsms-icon glyphicon glyphicon-cog' alt='"._('Edit')."' title='"._('Edit')."'></span>", 'delete' => "<span class='playsms-icon glyphicon glyphicon-trash' alt='"._('Delete')."' title='"._('Delete')."'></span>", );
 */

// override menus
/*
$themes_config['arabian']['menu'] = array(
	$core_config['menutab']['my_account'] => array(
		array(
			'index.php?app=main&inc=feature_report&route=user_inbox&op=user_inbox',
			_('Inbox'),
			1 
		),
		array(
			'index.php?app=main&inc=core_sendsms&op=sendsms',
			_('Compose message'),
			2 
		),
		array(
			'index.php?app=main&inc=core_user&route=user_pref&op=user_pref',
			_('Preferences'),
			4 
		) 
	),
	$core_config['menutab']['settings'] => array(),
	$core_config['menutab']['features'] => array(),
	$core_config['menutab']['reports'] => array(
		array(
			'index.php?app=main&inc=feature_report&route=user_outgoing&op=user_outgoing',
			_('My sent messages'),
			3 
		),
		array(
			'index.php?app=main&inc=feature_report&route=credit&op=credit_list',
			_('My credit transactions'),
			1 
		) 
	) 
);
*/
