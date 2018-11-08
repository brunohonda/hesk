<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../');
define('LOAD_TABS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/profile_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_man_users');

/* Possible user features */
$hesk_settings['features'] = array(
'can_view_tickets',		/* User can read tickets */
'can_reply_tickets',	/* User can reply to tickets */
'can_del_tickets',		/* User can delete tickets */
'can_edit_tickets',		/* User can edit tickets */
'can_merge_tickets',	/* User can merge tickets */
'can_resolve',			/* User can resolve tickets */
'can_submit_any_cat',	/* User can submit a ticket to any category/department */
'can_del_notes',		/* User can delete ticket notes posted by other staff members */
'can_change_cat',		/* User can move ticket to any category/department */
'can_change_own_cat',	/* User can move ticket to a category/department he/she has access to */
'can_man_kb',			/* User can manage knowledgebase articles and categories */
'can_man_users',		/* User can create and edit staff accounts */
'can_man_cat',			/* User can manage categories/departments */
'can_man_canned',		/* User can manage canned responses */
'can_man_ticket_tpl',	/* User can manage ticket templates */
'can_man_settings',		/* User can manage help desk settings */
'can_add_archive',		/* User can mark tickets as "Tagged" */
'can_assign_self',		/* User can assign tickets to himself/herself */
'can_assign_others',	/* User can assign tickets to other staff members */
'can_view_unassigned',	/* User can view unassigned tickets */
'can_view_ass_others',	/* User can view tickets that are assigned to other staff */
'can_view_ass_by',      /* User can view tickets he/she assigned to others */
'can_run_reports',		/* User can run reports and see statistics (only allowed categories and self) */
'can_run_reports_full', /* User can run reports and see statistics (unrestricted) */
'can_export',			/* User can export own tickets to Excel */
'can_view_online',		/* User can view what staff members are currently online */
'can_ban_emails',		/* User can ban email addresses */
'can_unban_emails',		/* User can delete email address bans. Also enables "can_ban_emails" */
'can_ban_ips',			/* User can ban IP addresses */
'can_unban_ips',		/* User can delete IP bans. Also enables "can_ban_ips" */
'can_privacy',  		/* User can use privacy tools (Anonymize tickets) */
'can_service_msg',		/* User can manage service messages shown in customer interface */
'can_email_tpl',		/* User can manage email templates */
);

/* Set default values */
$default_userdata = array(

	// Profile info
	'name' => '',
	'email' => '',
	'cleanpass' => '',
	'user' => '',
	'autoassign' => 'Y',

	// Signature
	'signature' => '',

	// Permissions
	'isadmin' => 1,
	'categories' => array('1'),
	'features' => array('can_view_tickets','can_reply_tickets','can_change_cat','can_assign_self','can_view_unassigned','can_view_online','can_resolve','can_submit_any_cat'),

	// Preferences
	'afterreply' => 0,

	// Defaults
	'autostart' => 1,
	'notify_customer_new' => 1,
	'notify_customer_reply' => 1,
	'show_suggested' => 1,
	'autoreload' => 0,

	// Notifications
	'notify_new_unassigned' => 1,
	'notify_new_my' => 1,
	'notify_reply_unassigned' => 1,
	'notify_reply_my' => 1,
	'notify_assigned' => 1,
	'notify_note' => 1,
	'notify_pm' => 1,
);

/* A list of all categories */
$hesk_settings['categories'] = array();
$res = hesk_dbQuery('SELECT `id`,`name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` ORDER BY `cat_order` ASC');
while ($row=hesk_dbFetchAssoc($res))
{
	if ( hesk_okCategory($row['id'], 0) )
    {
		$hesk_settings['categories'][$row['id']] = $row['name'];
    }
}

/* Non-admin users may not create users with more permissions than they have */
if ( ! $_SESSION['isadmin'])
{
	/* Can't create admin users */
    if ( isset($_POST['isadmin']) )
	{
    	unset($_POST['isadmin']);
	}

    /* Can only add features he/she has access to */
	$hesk_settings['features'] = array_intersect( explode(',', $_SESSION['heskprivileges']) , $hesk_settings['features']);

	/* Can user modify auto-assign setting? */
    if ($hesk_settings['autoassign'] && ( ! hesk_checkPermission('can_assign_self', 0) || ! hesk_checkPermission('can_assign_others', 0) ) )
    {
    	$hesk_settings['autoassign'] = 0;
    }
}

/* Use any set values, default otherwise */
foreach ($default_userdata as $k => $v)
{
	if ( ! isset($_SESSION['userdata'][$k]) )
    {
    	$_SESSION['userdata'][$k] = $v;
    }
}

$_SESSION['userdata'] = hesk_stripArray($_SESSION['userdata']);

/* What should we do? */
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'reset_form')
	{
		$_SESSION['edit_userdata'] = TRUE;
		header('Location: ./manage_users.php');
	}
	elseif ($action == 'edit')       {edit_user();}
	elseif ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_users.php', 'NOTICE');}
	elseif ($action == 'new')        {new_user();}
	elseif ($action == 'save')       {update_user();}
	elseif ($action == 'remove')     {remove();}
	elseif ($action == 'autoassign') {toggle_autoassign();}
    else 							 {hesk_error($hesklang['invalid_action']);}
}

else
{

/* If one came from the Edit page make sure we reset user values */

if (isset($_SESSION['save_userdata']))
{
	$_SESSION['userdata'] = $default_userdata;
    unset($_SESSION['save_userdata']);
}
if (isset($_SESSION['edit_userdata']))
{
	$_SESSION['userdata'] = $default_userdata;
    unset($_SESSION['edit_userdata']);
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

</td>
</tr>
<tr>
<td>

<script language="Javascript" type="text/javascript"><!--
function confirm_delete()
{
if (confirm('<?php echo addslashes($hesklang['sure_remove_user']); ?>')) {return true;}
else {return false;}
}
//-->
</script>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();

// If POP3 fetching is active, no user should have the same email address
if ($hesk_settings['pop3'] && hesk_validateEmail($hesk_settings['pop3_user'], 'ERR', 0))
{
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['pop3_user'])."'");

    if (hesk_dbNumRows($res) > 0)
    {
        while ($myuser = hesk_dbFetchAssoc($res))
        {
            if (compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges'])))
            {
                hesk_show_notice(sprintf($hesklang['pop3_warning'], $myuser['name'], $hesk_settings['pop3_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
                break;
            }
        }
    }
}

// If IMAP fetching is active, no user should have the same email address
if ($hesk_settings['imap'] && hesk_validateEmail($hesk_settings['imap_user'], 'ERR', 0))
{
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['imap_user'])."'");

    if (hesk_dbNumRows($res) > 0)
    {
        while ($myuser = hesk_dbFetchAssoc($res))
        {
            if (compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges'])))
            {
                hesk_show_notice(sprintf($hesklang['imap_warning'], $myuser['name'], $hesk_settings['imap_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
                break;
            }
        }
    }
}
?>

<h3 style="padding-bottom:5px"><?php echo $hesklang['manage_users']; ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['users_intro']); ?>')">?</a>]</h3>

&nbsp;<br />

<div align="center">
<table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
<tr>
<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['name']; ?></i></b></th>
<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['email']; ?></i></b></th>
<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['username']; ?></i></b></th>
<th class="admin_white" style="text-align:center;white-space:nowrap;width:1px;"><b><i><?php echo $hesklang['administrator']; ?></i></b></th>
<?php
/* Is user rating enabled? */
if ($hesk_settings['rating'])
{
	?>
	<th class="admin_white" style="text-align:center;white-space:nowrap;width:1px;"><b><i><?php echo $hesklang['rating']; ?></i></b></th>
	<?php
}
?>
<th class="admin_white" style="width:100px"><b><i>&nbsp;<?php echo $hesklang['opt']; ?>&nbsp;</i></b></th>
</tr>

<?php
$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'users` ORDER BY `name` ASC');

$i=1;
$cannot_manage = array();

while ($myuser = hesk_dbFetchAssoc($res))
{

	if ( ! compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges'])) )
    {
    	$cannot_manage[$myuser['id']] = array('name' => $myuser['name'], 'user' => $myuser['user'], 'email' => $myuser['email']);
        continue;
    }

    if ( isset($_SESSION['seluser']) && $myuser['id'] == $_SESSION['seluser'])
    {
		$color = 'admin_green';
		unset($_SESSION['seluser']);
	}
    else
    {
		$color = $i ? 'admin_white' : 'admin_gray';
    }

	$tmp   = $i ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
	$i	   = $i ? 0 : 1;

    /* User online? */
	if ($hesk_settings['online'])
	{
    	if (isset($hesk_settings['users_online'][$myuser['id']]))
        {
			$myuser['name'] = '<img src="../img/online_on.png" width="16" height="16" alt="'.$hesklang['online'].'" title="'.$hesklang['online'].'" style="vertical-align:text-bottom" /> ' . $myuser['name'];
        }
        else
        {
			$myuser['name'] = '<img src="../img/online_off.png" width="16" height="16" alt="'.$hesklang['offline'].'" title="'.$hesklang['offline'].'" style="vertical-align:text-bottom" /> ' . $myuser['name'];
        }
	}

	/* To edit yourself go to "Profile" page, not here. */
    if ($myuser['id'] == $_SESSION['id'])
    {
    	$edit_code = '<a name="Edit '.$myuser['user'].'" href="profile.php"><img src="../img/edit.png" width="16" height="16" alt="'.$hesklang['edit'].'" title="'.$hesklang['edit'].'" '.$style.' /></a>';
    }
    else
    {
    	$edit_code = '<a name="Edit '.$myuser['user'].'" href="manage_users.php?a=edit&amp;id='.$myuser['id'].'"><img src="../img/edit.png" width="16" height="16" alt="'.$hesklang['edit'].'" title="'.$hesklang['edit'].'" '.$style.' /></a>';
    }

    if ($myuser['isadmin'])
    {
    	$myuser['isadmin'] = '<font class="open">'.$hesklang['yes'].'</font>';
    }
    else
    {
    	$myuser['isadmin'] = '<font class="resolved">'.$hesklang['no'].'</font>';
    }

    /* Deleting user with ID 1 (default administrator) is not allowed */
    if ($myuser['id'] == 1)
    {
        $remove_code = ' <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
    }
    else
    {
        $remove_code = ' <a name="Delete '.$myuser['user'].'" href="manage_users.php?a=remove&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['remove'].'" title="'.$hesklang['remove'].'" '.$style.' /></a>';
    }

	/* Is auto assign enabled? */
	if ($hesk_settings['autoassign'])
    {
    	if ($myuser['autoassign'])
        {
			$autoassign_code = '<a name="Unassign '.$myuser['user'].'" href="manage_users.php?a=autoassign&amp;s=0&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/autoassign_on.png" width="16" height="16" alt="'.$hesklang['aaon'].'" title="'.$hesklang['aaon'].'" '.$style.' /></a>';
        }
        else
        {
			$autoassign_code = '<a name="Assign '.$myuser['user'].'" href="manage_users.php?a=autoassign&amp;s=1&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/autoassign_off.png" width="16" height="16" alt="'.$hesklang['aaoff'].'" title="'.$hesklang['aaoff'].'" '.$style.' /></a>';
        }
    }
    else
    {
		$autoassign_code = '';
    }

echo <<<EOC
<tr>
<td class="$color">$myuser[name]</td>
<td class="$color"><a href="mailto:$myuser[email]">$myuser[email]</a></td>
<td class="$color">$myuser[user]</td>
<td class="$color">$myuser[isadmin]</td>

EOC;

if ($hesk_settings['rating'])
{
	$alt = $myuser['rating'] ? sprintf($hesklang['rated'], sprintf("%01.1f", $myuser['rating']), ($myuser['ratingneg']+$myuser['ratingpos'])) : $hesklang['not_rated'];
	echo '<td class="'.$color.'" style="text-align:center; white-space:nowrap;"><img src="../img/star_'.(hesk_round_to_half($myuser['rating'])*10).'.png" width="85" height="16" alt="'.$alt.'" title="'.$alt.'" border="0" style="vertical-align:text-bottom" />&nbsp;</td>';
}

echo <<<EOC
<td class="$color" style="text-align:center">$autoassign_code $edit_code $remove_code</td>
</tr>

EOC;
} // End while
?>
</table>
</div>

<p>&nbsp;</p>

<h3><?php echo $hesklang['add_user']; ?></h3>

<p><?php echo $hesklang['req_marked_with']; ?> <font class="important">*</font><br />&nbsp;</p>

<script language="Javascript" type="text/javascript"><!--
var tabberOptions = {
	'cookie':"tabbernu",
	'onLoad': function(argsObj)
	{
		var t = argsObj.tabber;
		var i;
		if (t.id) {
		t.cookie = t.id + t.cookie;
	}

	i = parseInt(getCookie(t.cookie));
	if (isNaN(i)) { return; }
		t.tabShow(i);
	},

	'onClick':function(argsObj)
	{
		var c = argsObj.tabber.cookie;
		var i = argsObj.index;
		setCookie(c, i);
	}
};
//-->
</script>

<script language="Javascript" type="text/javascript" src="<?php echo HESK_PATH; ?>inc/tabs/tabber-minimized.js"></script>

<form name="form1" method="post" action="manage_users.php">
<?php hesk_profile_tab('userdata', false); ?>

<!-- Submit -->
<p align="center"><input type="hidden" name="a" value="new" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="submit" value="<?php echo $hesklang['create_user']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
|
<a href="manage_users.php?a=reset_form"><?php echo $hesklang['refi']; ?></a></p>
</form>

<p>&nbsp;</p>
<p>&nbsp;</p>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

} // End else


/*** START FUNCTIONS ***/


function compare_user_permissions($compare_id, $compare_isadmin, $compare_categories, $compare_features)
{
	global $hesk_settings;

    /* Comparing myself? */
    if ($compare_id == $_SESSION['id'])
    {
    	return true;
    }

    /* Admins have full access, no need to compare */
	if ($_SESSION['isadmin'])
    {
    	return true;
    }
    elseif ($compare_isadmin)
    {
    	return false;
    }

	/* Compare categories */
    foreach ($compare_categories as $catid)
    {
    	if ( ! array_key_exists($catid, $hesk_settings['categories']) )
        {
        	return false;
        }
    }

	/* Compare features */
    foreach ($compare_features as $feature)
    {
    	if ( ! in_array($feature, $hesk_settings['features']) )
        {
        	return false;
        }
    }

    return true;

} // END compare_user_permissions()


function edit_user()
{
	global $hesk_settings, $hesklang, $default_userdata;

	$id = intval( hesk_GET('id') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");

	/* To edit self fore using "Profile" page */
    if ($id == $_SESSION['id'])
    {
    	hesk_process_messages($hesklang['eyou'],'profile.php','NOTICE');
    }

    $_SESSION['edit_userdata'] = TRUE;

    if ( ! isset($_SESSION['save_userdata']))
    {
		$res = hesk_dbQuery("SELECT *,`heskprivileges` AS `features` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($id)."' LIMIT 1");
    	$_SESSION['userdata'] = hesk_dbFetchAssoc($res);

        /* Store original username for display until changes are saved successfully */
        $_SESSION['original_user'] = $_SESSION['userdata']['user'];

        /* A few variables need special attention... */
        if ($_SESSION['userdata']['isadmin'])
        {
	        $_SESSION['userdata']['features'] = $default_userdata['features'];
	        $_SESSION['userdata']['categories'] = $default_userdata['categories'];
        }
        else
        {
	        $_SESSION['userdata']['features'] = explode(',',$_SESSION['userdata']['features']);
	        $_SESSION['userdata']['categories'] = explode(',',$_SESSION['userdata']['categories']);
        }
        $_SESSION['userdata']['cleanpass'] = '';
    }

	/* Make sure we have permission to edit this user */
	if ( ! compare_user_permissions($id, $_SESSION['userdata']['isadmin'], $_SESSION['userdata']['categories'], $_SESSION['userdata']['features']) )
	{
		hesk_process_messages($hesklang['npea'],'manage_users.php');
	}

    /* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	/* Print main manage users page */
	require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
	?>

	</td>
	</tr>
	<tr>
	<td>

	<span class="smaller"><a href="manage_users.php" class="smaller"><?php echo $hesklang['manage_users']; ?></a> &gt; <?php echo $hesklang['editing_user'].' '.$_SESSION['original_user']; ?></span>

	<br />&nbsp;

	<?php
	/* This will handle error, success and notice messages */
	hesk_handle_messages();
	?>

	<h3><?php echo $hesklang['editing_user'].' '.$_SESSION['original_user']; ?></h3>

	<p><?php echo $hesklang['req_marked_with']; ?> <font class="important">*</font><br />&nbsp;</p>

	<script language="Javascript" type="text/javascript"><!--
	var tabberOptions = {
		'cookie':"tabbereu",
		'onLoad': function(argsObj)
		{
			var t = argsObj.tabber;
			var i;
			if (t.id) {
			t.cookie = t.id + t.cookie;
		}

		i = parseInt(getCookie(t.cookie));
		if (isNaN(i)) { return; }
			t.tabShow(i);
		},

		'onClick':function(argsObj)
		{
			var c = argsObj.tabber.cookie;
			var i = argsObj.index;
			setCookie(c, i);
		}
	};
	//-->
	</script>

	<script language="Javascript" type="text/javascript" src="<?php echo HESK_PATH; ?>inc/tabs/tabber-minimized.js"></script>

	<form name="form1" method="post" action="manage_users.php">
	<?php hesk_profile_tab('userdata', false); ?>

	<!-- Submit -->
	<p align="center"><input type="hidden" name="a" value="save" />
	<input type="hidden" name="userid" value="<?php echo $id; ?>" />
    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
	<input type="submit" value="<?php echo $hesklang['save_changes']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
    |
    <a href="manage_users.php"><?php echo $hesklang['dich']; ?></a></p>
	</form>

	<p>&nbsp;</p>
	<p>&nbsp;</p>

	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End edit_user()


function new_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$myuser = hesk_validateUserInfo();

    /* Categories and Features will be stored as a string */
    $myuser['categories'] = implode(',',$myuser['categories']);
    $myuser['features'] = implode(',',$myuser['features']);

    /* Check for duplicate usernames */
	$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($myuser['user'])."' LIMIT 1");
	if (hesk_dbNumRows($result) != 0)
	{
        hesk_process_messages($hesklang['duplicate_user'],'manage_users.php');
	}

    /* Admins will have access to all features and categories */
    if ($myuser['isadmin'])
    {
		$myuser['categories'] = '';
		$myuser['features'] = '';
    }

	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (
	`user`,
	`pass`,
	`isadmin`,
	`name`,
	`email`,
	`signature`,
	`categories`,
	`autoassign`,
	`heskprivileges`,
	`afterreply`,
	`autostart`,
	`autoreload`,
	`notify_customer_new`,
	`notify_customer_reply`,
	`show_suggested`,
	`notify_new_unassigned`,
	`notify_new_my`,
	`notify_reply_unassigned`,
	`notify_reply_my`,
	`notify_assigned`,
	`notify_pm`,
	`notify_note`
	) VALUES (
	'".hesk_dbEscape($myuser['user'])."',
	'".hesk_dbEscape($myuser['pass'])."',
	'".intval($myuser['isadmin'])."',
	'".hesk_dbEscape($myuser['name'])."',
	'".hesk_dbEscape($myuser['email'])."',
	'".hesk_dbEscape($myuser['signature'])."',
	'".hesk_dbEscape($myuser['categories'])."',
	'".intval($myuser['autoassign'])."',
	'".hesk_dbEscape($myuser['features'])."',
	'".($myuser['afterreply'])."' ,
	'".($myuser['autostart'])."' ,
	'".($myuser['autoreload'])."' ,
	'".($myuser['notify_customer_new'])."' ,
	'".($myuser['notify_customer_reply'])."' ,
	'".($myuser['show_suggested'])."' ,
	'".($myuser['notify_new_unassigned'])."' ,
	'".($myuser['notify_new_my'])."' ,
	'".($myuser['notify_reply_unassigned'])."' ,
	'".($myuser['notify_reply_my'])."' ,
	'".($myuser['notify_assigned'])."' ,
	'".($myuser['notify_pm'])."',
	'".($myuser['notify_note'])."'
	)" );

    $_SESSION['seluser'] = hesk_dbInsertID();

    unset($_SESSION['userdata']);

    hesk_process_messages(sprintf($hesklang['user_added_success'],$myuser['user'],$myuser['cleanpass']),'./manage_users.php','SUCCESS');
} // End new_user()


function update_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    $_SESSION['save_userdata'] = TRUE;

	$tmp = intval( hesk_POST('userid') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");

	/* To edit self fore using "Profile" page */
    if ($tmp == $_SESSION['id'])
    {
    	hesk_process_messages($hesklang['eyou'],'profile.php','NOTICE');
    }

    $_SERVER['PHP_SELF'] = './manage_users.php?a=edit&id='.$tmp;
	$myuser = hesk_validateUserInfo(0,$_SERVER['PHP_SELF']);
    $myuser['id'] = $tmp;

    /* Check for duplicate usernames */
	$res = hesk_dbQuery("SELECT `id`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($myuser['user'])."' LIMIT 1");
	if (hesk_dbNumRows($res) == 1)
	{
    	$tmp = hesk_dbFetchAssoc($res);

        /* Duplicate? */
        if ($tmp['id'] != $myuser['id'])
        {
        	hesk_process_messages($hesklang['duplicate_user'],$_SERVER['PHP_SELF']);
        }

		/* Do we have permission to edit this user? */
		if ( ! compare_user_permissions($tmp['id'], $tmp['isadmin'], explode(',', $tmp['categories']) , explode(',', $tmp['heskprivileges'])) )
		{
			hesk_process_messages($hesklang['npea'],'manage_users.php');
		}
	}

    /* Admins will have access to all features and categories */
    if ($myuser['isadmin'])
    {
		$myuser['categories'] = '';
		$myuser['features'] = '';
    }
	/* Not admin */
	else
    {
		/* Categories and Features will be stored as a string */
	    $myuser['categories'] = implode(',',$myuser['categories']);
	    $myuser['features'] = implode(',',$myuser['features']);

    	/* Unassign tickets from categories that the user had access before but doesn't anymore */
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".intval($myuser['id'])."' AND `category` NOT IN (".$myuser['categories'].")");
    }

	hesk_dbQuery(
    "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET
    `user`='".hesk_dbEscape($myuser['user'])."',
    `name`='".hesk_dbEscape($myuser['name'])."',
    `email`='".hesk_dbEscape($myuser['email'])."',
    `signature`='".hesk_dbEscape($myuser['signature'])."'," . ( isset($myuser['pass']) ? "`pass`='".hesk_dbEscape($myuser['pass'])."'," : '' ) . "
    `categories`='".hesk_dbEscape($myuser['categories'])."',
    `isadmin`='".intval($myuser['isadmin'])."',
    `autoassign`='".intval($myuser['autoassign'])."',
    `heskprivileges`='".hesk_dbEscape($myuser['features'])."',
	`afterreply`='".($myuser['afterreply'])."' ,
	`autostart`='".($myuser['autostart'])."' ,
	`autoreload`='".($myuser['autoreload'])."' ,
	`notify_customer_new`='".($myuser['notify_customer_new'])."' ,
	`notify_customer_reply`='".($myuser['notify_customer_reply'])."' ,
	`show_suggested`='".($myuser['show_suggested'])."' ,
	`notify_new_unassigned`='".($myuser['notify_new_unassigned'])."' ,
	`notify_new_my`='".($myuser['notify_new_my'])."' ,
	`notify_reply_unassigned`='".($myuser['notify_reply_unassigned'])."' ,
	`notify_reply_my`='".($myuser['notify_reply_my'])."' ,
	`notify_assigned`='".($myuser['notify_assigned'])."' ,
	`notify_pm`='".($myuser['notify_pm'])."',
	`notify_note`='".($myuser['notify_note'])."'
    WHERE `id`='".intval($myuser['id'])."'");

    unset($_SESSION['save_userdata']);
    unset($_SESSION['userdata']);

    hesk_process_messages( $hesklang['user_profile_updated_success'],$_SERVER['PHP_SELF'],'SUCCESS');
} // End update_profile()


function hesk_validateUserInfo($pass_required = 1, $redirect_to = './manage_users.php')
{
	global $hesk_settings, $hesklang;

    $hesk_error_buffer = '';

	$myuser['name']		  = hesk_input( hesk_POST('name') ) or $hesk_error_buffer .= '<li>' . $hesklang['enter_real_name'] . '</li>';
	$myuser['email']	  = hesk_validateEmail( hesk_POST('email'), 'ERR', 0) or $hesk_error_buffer .= '<li>' . $hesklang['enter_valid_email'] . '</li>';
	$myuser['user']		  = hesk_input( hesk_POST('user') ) or $hesk_error_buffer .= '<li>' . $hesklang['enter_username'] . '</li>';
	$myuser['isadmin']	  = empty($_POST['isadmin']) ? 0 : 1;
	$myuser['signature']  = hesk_input( hesk_POST('signature') );
    $myuser['autoassign'] = hesk_POST('autoassign') == 'Y' ? 1 : 0;

    /* If it's not admin at least one category and fature is required */
    $myuser['categories']	= array();
    $myuser['features']		= array();

    if ($myuser['isadmin']==0)
    {
    	if (empty($_POST['categories']) || ! is_array($_POST['categories']) )
        {
			$hesk_error_buffer .= '<li>' . $hesklang['asign_one_cat'] . '</li>';
        }
        else
        {
			foreach ($_POST['categories'] as $tmp)
			{
            	if (is_array($tmp))
                {
                	continue;
                }

				if ($tmp = intval($tmp))
				{
					$myuser['categories'][] = $tmp;
				}
			}
        }

    	if (empty($_POST['features']) || ! is_array($_POST['features']) )
        {
			$hesk_error_buffer .= '<li>' . $hesklang['asign_one_feat'] . '</li>';
        }
        else
        {
			foreach ($_POST['features'] as $tmp)
			{
				if (in_array($tmp,$hesk_settings['features']))
				{
					$myuser['features'][] = $tmp;
				}
			}
        }
	}

	if (hesk_mb_strlen($myuser['signature'])>1000)
    {
    	$hesk_error_buffer .= '<li>' . $hesklang['signature_long'] . '</li>';
    }

    /* Password */
	$myuser['cleanpass'] = '';

	$newpass = hesk_input( hesk_POST('newpass') );
	$passlen = strlen($newpass);

	if ($pass_required || $passlen > 0)
	{
        /* At least 5 chars? */
        if ($passlen < 5)
        {
        	$hesk_error_buffer .= '<li>' . $hesklang['password_not_valid'] . '</li>';
        }
        /* Check password confirmation */
        else
        {
        	$newpass2 = hesk_input( hesk_POST('newpass2') );

			if ($newpass != $newpass2)
			{
				$hesk_error_buffer .= '<li>' . $hesklang['passwords_not_same'] . '</li>';
			}
            else
            {
                $myuser['pass'] = hesk_Pass2Hash($newpass);
                $myuser['cleanpass'] = $newpass;
            }
        }
	}

    /* After reply */
    $myuser['afterreply'] = intval( hesk_POST('afterreply') );
    if ($myuser['afterreply'] != 1 && $myuser['afterreply'] != 2)
    {
    	$myuser['afterreply'] = 0;
    }

    // Defaults
    $myuser['autostart']				= isset($_POST['autostart']) ? 1 : 0;
    $myuser['notify_customer_new']		= isset($_POST['notify_customer_new']) ? 1 : 0;
    $myuser['notify_customer_reply']	= isset($_POST['notify_customer_reply']) ? 1 : 0;
    $myuser['show_suggested']			= isset($_POST['show_suggested']) ? 1 : 0;
    $myuser['autoreload']				= isset($_POST['autoreload']) ? 1 : 0;

    if ($myuser['autoreload'])
    {
        $myuser['autoreload'] = intval(hesk_POST('reload_time'));

        if (hesk_POST('secmin') == 'min')
        {
            $myuser['autoreload'] *= 60;
        }

        if ($myuser['autoreload'] < 0 || $myuser['autoreload'] > 65535)
        {
            $myuser['autoreload'] = 30;
        }
    }

    /* Notifications */
    $myuser['notify_new_unassigned']	= empty($_POST['notify_new_unassigned']) ? 0 : 1;
    $myuser['notify_new_my'] 			= empty($_POST['notify_new_my']) ? 0 : 1;
    $myuser['notify_reply_unassigned']	= empty($_POST['notify_reply_unassigned']) ? 0 : 1;
    $myuser['notify_reply_my']			= empty($_POST['notify_reply_my']) ? 0 : 1;
    $myuser['notify_assigned']			= empty($_POST['notify_assigned']) ? 0 : 1;
    $myuser['notify_note']				= empty($_POST['notify_note']) ? 0 : 1;
    $myuser['notify_pm']				= empty($_POST['notify_pm']) ? 0 : 1;

    /* Save entered info in session so we don't loose it in case of errors */
	$_SESSION['userdata'] = $myuser;

    /* Any errors */
    if (strlen($hesk_error_buffer))
    {
		if ($myuser['isadmin'])
		{
			// Preserve default staff data for the form
			global $default_userdata;
        	$_SESSION['userdata']['features'] = $default_userdata['features'];
        	$_SESSION['userdata']['categories'] = $default_userdata['categories'];
		}

    	$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    	hesk_process_messages($hesk_error_buffer,$redirect_to);
    }

	// "can_unban_emails" feature also enables "can_ban_emails"
	if ( in_array('can_unban_emails', $myuser['features']) && ! in_array('can_ban_emails', $myuser['features']) )
	{
    	$myuser['features'][] = 'can_ban_emails';
	}

	return $myuser;

} // End hesk_validateUserInfo()


function remove()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);

    /* You can't delete the default user */
	if ($myuser == 1)
    {
        hesk_process_messages($hesklang['cant_del_admin'],'./manage_users.php');
    }

    /* You can't delete your own account (the one you are logged in) */
	if ($myuser == $_SESSION['id'])
    {
        hesk_process_messages($hesklang['cant_del_own'],'./manage_users.php');
    }

    /* Un-assign all tickets for this user */
    $res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".intval($myuser)."'");

    /* Delete user info */
	$res = hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($myuser)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_users.php');
    }

	/* Delete any user reply drafts */
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`={$myuser}");

    hesk_process_messages($hesklang['sel_user_removed'],'./manage_users.php','SUCCESS');
} // End remove()


function toggle_autoassign()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);
    $_SESSION['seluser'] = $myuser;

    if ( intval( hesk_GET('s') ) )
    {
		$autoassign = 1;
        $tmp = $hesklang['uaaon'];
    }
    else
    {
        $autoassign = 0;
        $tmp = $hesklang['uaaoff'];
    }

	/* Update auto-assign settings */
	$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `autoassign`='{$autoassign}' WHERE `id`='".intval($myuser)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_users.php');
    }

    hesk_process_messages($tmp,'./manage_users.php','SUCCESS');
} // End toggle_autoassign()
?>
