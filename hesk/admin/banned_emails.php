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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_ban_emails');
$can_unban = hesk_checkPermission('can_unban_emails', 0);

// Define required constants
define('LOAD_TABS',1);

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'banned_emails.php', 'NOTICE');}
	elseif ($action == 'ban')   {ban_email();}
	elseif ($action == 'unban' && $can_unban) {unban_email();}
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

<!-- TABS -->
<div id="tab1" class="tabberlive" style="margin-top:0px">

	<ul class="tabbernav">
		<li class="tabberactive"><a title="<?php echo $hesklang['banemail']; ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['banemail_intro']); ?>')"><?php echo $hesklang['banemail']; ?> [?]</a></li>
		<?php
		// Show a link to banned_ips.php if user has permission to do so
		if ( hesk_checkPermission('can_ban_ips',0) )
		{
			echo '<li class=""><a title="' . $hesklang['banip'] . '" href="banned_ips.php">' . $hesklang['banip'] . '</a></li> ';
		}

		// Show a link to status_message.php if user has permission to do so
		if ( hesk_checkPermission('can_service_msg',0) )
		{
			echo '<li class=""><a title="' . $hesklang['sm_title'] . '" href="service_messages.php">' . $hesklang['sm_title'] . '</a></li> ';
		}

		// Show a link to email_templates.php if user has permission to do so
		if ( hesk_checkPermission('can_email_tpl',0) )
		{
			echo '<li class=""><a title="' . $hesklang['et_title'] . '" href="email_templates.php">' . $hesklang['et_title'] . '</a></li> ';
		}

		// Show a link to custom_fields.php if user has permission to do so
		if ( hesk_checkPermission('can_man_settings',0) )
		{
			echo '<li class=""><a title="' . $hesklang['tab_4'] . '" href="custom_fields.php">' . $hesklang['tab_4'] . '</a></li> ';
			echo '<li class=""><a title="' . $hesklang['statuses'] . '" href="custom_statuses.php">' . $hesklang['statuses'] . '</a></li> ';
		}
		?>
	</ul>

</div>
<!-- TABS -->

&nbsp;<br />

<script language="javascript" type="text/javascript"><!--
function confirm_delete()
{
if (confirm('<?php echo hesk_makeJsString($hesklang['delban_confirm']); ?>')) {return true;}
else {return false;}
}
//-->
</script>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

&nbsp;<br />

<form action="banned_emails.php" method="post" name="form1">

<table border="0">
<tr>
<td valign="top">
<?php echo $hesklang['bananemail']; ?>:
<input type="text" name="email" size="30" maxlength="255" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="hidden" name="a" value="ban" />
<input type="submit" value="<?php echo $hesklang['savebanemail']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
</td>
<td valign="top" style="padding-left:20px;"><i><?php echo $hesklang['banex']; ?></i><br />
<b>john@example.com</b><br />
<b>@example.com</b></td>
</tr>
</table>

</form>

<p>&nbsp;</p>

<?php

// Get banned emails from database
$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'banned_emails` ORDER BY `email` ASC');
$num = hesk_dbNumRows($res);

if ($num < 1)
{
    echo '<p>'.$hesklang['no_banemails'].'</p>';
}
else
{
	// List of staff
	if ( ! isset($admins) )
	{
		$admins = array();
		$res2 = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users`");
		while ($row=hesk_dbFetchAssoc($res2))
		{
			$admins[$row['id']]=$row['name'];
		}
	}

	?>
	<h3 style="padding-bottom:5px;">&raquo; <?php echo $hesklang['eperm']; ?></h3>
	<div align="center">
	<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
	<tr>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['email']; ?></i></b></th>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['banby']; ?></i></b></th>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['date']; ?></i></b></th>
	<?php
	if ($can_unban)
	{
	?>
	<th class="admin_white" style="width:80px"><b><i>&nbsp;<?php echo $hesklang['opt']; ?>&nbsp;</i></b></th>
	<?php
	}
	?>
	</tr>
	<?php
	$i = 1;

    while ($ban=hesk_dbFetchAssoc($res))
    {
		if (isset($_SESSION['ban_email']['id']) && $ban['id'] == $_SESSION['ban_email']['id'])
		{
			$color = 'admin_green';
			unset($_SESSION['ban_email']['id']);
		}
		else
		{
			$color = $i ? 'admin_white' : 'admin_gray';
		}
        
		$tmp   = $i ? 'White' : 'Blue';
	    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
	    $i     = $i ? 0 : 1;

	    echo '
	    <tr>
	    <td class="'.$color.'" style="text-align:left">'.$ban['email'].'</td>
	    <td class="'.$color.'" style="text-align:left">'.(isset($admins[$ban['banned_by']]) ? $admins[$ban['banned_by']] : $hesklang['e_udel']).'</td>
	    <td class="'.$color.'" style="text-align:left">'.$ban['dt'].'</td>
		';

		if ($can_unban)
		{
		echo '
        <td class="'.$color.'" style="text-align:center; white-space:nowrap;">
        <a name="Unban '.$ban['email'].'" href="banned_emails.php?a=unban&amp;id='.$ban['id'].'&amp;token='.hesk_token_echo(0).'" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['delban'].'" title="'.$hesklang['delban'].'" '.$style.' /></a>&nbsp;</td>
		';
		}

	    echo '</tr>';
    } // End while

    ?>
	</table>
	</div>
    <?php
}

?>

<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/

function ban_email()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get the email
	$email = hesk_emailCleanup( strtolower( hesk_input( hesk_REQUEST('email') ) ) );

	// Nothing entered?
	if ( ! strlen($email) )
	{
    	hesk_process_messages($hesklang['enterbanemail'],'banned_emails.php');
	}

	// Only allow one email to be entered
	$email = ($index = strpos($email, ',')) ? substr($email, 0,  $index) : $email;
	$email = ($index = strpos($email, ';')) ? substr($email, 0,  $index) : $email;

	// Validate email address
	$hesk_settings['multi_eml'] = 0;

	if ( ! hesk_validateEmail($email, '', 0) && ! verify_email_domain($email) )
	{
		hesk_process_messages($hesklang['validbanemail'],'banned_emails.php');
	}

	// Redirect either to banned emails or ticket page from now on
	$redirect_to = ($trackingID = hesk_cleanID()) ? 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999) : 'banned_emails.php';

	// Prevent duplicate rows
	if ( $_SESSION['ban_email']['id'] = hesk_isBannedEmail($email) )
	{
    	hesk_process_messages( sprintf($hesklang['emailbanexists'], $email) ,$redirect_to,'NOTICE');
	}

	// Insert the email address into database
	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_emails` (`email`,`banned_by`) VALUES ('".hesk_dbEscape($email)."','".intval($_SESSION['id'])."')");

	// Remember email that got banned
	$_SESSION['ban_email']['id'] = hesk_dbInsertID();

	// Show success
    hesk_process_messages( sprintf($hesklang['email_banned'], $email) ,$redirect_to,'SUCCESS');

} // End ban_email()


function unban_email()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Delete from bans
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_emails` WHERE `id`=" . intval( hesk_GET('id') ) );

	// Redirect either to banned emails or ticket page from now on
	$redirect_to = ($trackingID = hesk_cleanID()) ? 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999) : 'banned_emails.php';

	// Show success
    hesk_process_messages($hesklang['email_unbanned'],$redirect_to,'SUCCESS');

} // End unban_email()


function verify_email_domain($domain)
{
    // Does it start with an @?
	$atIndex = strrpos($domain, "@");
	if ($atIndex !== 0)
	{
		return false;
	}

	// Get the domain and domain length
	$domain = substr($domain, 1);
	$domainLen = strlen($domain);

    // Check domain part length
	if ($domainLen < 1 || $domainLen > 254)
	{
		return false;
	}

    // Check domain part characters
	if ( ! preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain) )
	{
		return false;
	}

	// Domain part mustn't have two consecutive dots
	if ( strpos($domain, '..') !== false )
	{
		return false;
	}

	// All OK
	return true;

} // END verify_email_domain()

?>
