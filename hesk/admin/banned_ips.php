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
hesk_checkPermission('can_ban_ips');
$can_unban = hesk_checkPermission('can_unban_ips', 0);

// Define required constants
define('LOAD_TABS',1);

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'banned_ips.php', 'NOTICE');}
	elseif ($action == 'ban')   {ban_ip();}
	elseif ($action == 'unban' && $can_unban) {unban_ip();}
	elseif ($action == 'unbantemp' && $can_unban) {unban_temp_ip();}
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
		<?php
		// Show a link to banned_emails.php if user has permission to do so
		if ( hesk_checkPermission('can_ban_emails',0) )
		{
			echo '<li class=""><a title="' . $hesklang['banemail'] . '" href="banned_emails.php">' . $hesklang['banemail'] . '</a></li> ';
		}
		?>
		<li class="tabberactive"><a title="<?php echo $hesklang['banip']; ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['banip_intro']); ?>')"><?php echo $hesklang['banip']; ?> [?]</a></li>
		<?php
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

<form action="banned_ips.php" method="post" name="form1">

<table border="0">
<tr>
<td valign="top">
<?php echo $hesklang['bananip']; ?>:
<input type="text" name="ip" size="30" maxlength="255" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="hidden" name="a" value="ban" />
<input type="submit" value="<?php echo $hesklang['savebanip']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
</td>
<td valign="top" style="padding-left:20px;"><i><?php echo $hesklang['banex']; ?></i><br />
<b>123.0.0.0</b><br />
<b>123.0.0.1 - 123.0.0.53</b><br />
<b>123.0.0.0/24</b><br />
<b>123.0.*.*</b></td>
</tr>
</table>

</form>

<p>&nbsp;</p>

<?php

// Get login failures
$res = hesk_dbQuery("SELECT `ip`, TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(`last_attempt`, INTERVAL ".intval($hesk_settings['attempt_banmin'])." MINUTE) ) AS `minutes` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` WHERE `number` >= ".intval($hesk_settings['attempt_limit'])." AND `last_attempt` > (NOW() -  INTERVAL ".intval($hesk_settings['attempt_banmin'])." MINUTE)");
$num = hesk_dbNumRows($res);

if ($num > 0)
{
	?>
	<h3 style="padding-bottom:5px;">&raquo; <?php echo $hesklang['iptemp']; ?></h3>
	<div>
	<table border="0" cellspacing="1" cellpadding="3" class="white" width="60%">
	<tr>
	<th class="admin_white" style="text-align:left" width="40%"><b><i><?php echo $hesklang['ip']; ?></i></b></th>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['m2e']; ?></i></b></th>
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
		$color = $i ? 'admin_white' : 'admin_gray';
		$tmp   = $i ? 'White' : 'Blue';
	    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
	    $i     = $i ? 0 : 1;

	    echo '
	    <tr>
	    <td class="'.$color.'" style="text-align:left">'.$ban['ip'].'</td>
	    <td class="'.$color.'" style="text-align:left">'.$ban['minutes'].'</td>
		';

		if ($can_unban)
		{
		echo '
        <td class="'.$color.'" style="text-align:center; white-space:nowrap;">
        <a href="banned_ips.php?a=ban&amp;ip='.urlencode($ban['ip']).'&amp;token='.hesk_token_echo(0).'"><img src="../img/ban.png" width="16" height="16" alt="'.$hesklang['ippermban'].'" title="'.$hesklang['ippermban'].'" '.$style.' /></a>
        <a href="banned_ips.php?a=unbantemp&amp;ip='.urlencode($ban['ip']).'&amp;token='.hesk_token_echo(0).'" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['delban'].'" title="'.$hesklang['delban'].'" '.$style.' /></a>&nbsp;</td>
		';
		}

	    echo '</tr>';
    } // End while

    ?>
	</table>
	</div>
	<p>&nbsp;</p>
    <?php
}

// Get banned ips from database
$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'banned_ips` ORDER BY `ip_from` ASC');
$num = hesk_dbNumRows($res);

if ($num < 1)
{
    echo '<p>'.$hesklang['no_banips'].'</p>';
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
	<h3 style="padding-bottom:5px;">&raquo; <?php echo $hesklang['ipperm']; ?></h3>
	<div align="center">
	<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
	<tr>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['ip']; ?></i></b></th>
	<th class="admin_white" style="text-align:left"><b><i><?php echo $hesklang['iprange']; ?></i></b></th>
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
		if (isset($_SESSION['ban_ip']['id']) && $ban['id'] == $_SESSION['ban_ip']['id'])
		{
			$color = 'admin_green';
			unset($_SESSION['ban_ip']['id']);
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
	    <td class="'.$color.'" style="text-align:left">'.$ban['ip_display'].'</td>
	    <td class="'.$color.'" style="text-align:left">'.( ($ban['ip_to'] == $ban['ip_from']) ? long2ip($ban['ip_to']) : long2ip($ban['ip_from']).' - '.long2ip($ban['ip_to']) ).'</td>
	    <td class="'.$color.'" style="text-align:left">'.(isset($admins[$ban['banned_by']]) ? $admins[$ban['banned_by']] : $hesklang['e_udel']).'</td>
	    <td class="'.$color.'" style="text-align:left">'.$ban['dt'].'</td>
		';

		if ($can_unban)
		{
		echo '
        <td class="'.$color.'" style="text-align:center; white-space:nowrap;">
        <a name="Unban '.$ban['ip_display'].'" href="banned_ips.php?a=unban&amp;id='.$ban['id'].'&amp;token='.hesk_token_echo(0).'" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['delban'].'" title="'.$hesklang['delban'].'" '.$style.' /></a>&nbsp;</td>
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

function ban_ip()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get the ip
	$ip = preg_replace('/[^0-9\.\-\/\*]/', '', hesk_REQUEST('ip') );
	$ip_display = str_replace('-', ' - ', $ip);

	// Nothing entered?
	if ( ! strlen($ip) )
	{
    	hesk_process_messages($hesklang['enterbanip'],'banned_ips.php');
	}

	// Convert asterisk to ranges
	if ( strpos($ip, '*') !== false )
	{
		$ip = str_replace('*', '0', $ip) . '-' . str_replace('*', '255', $ip);
	}

	$ip_regex = '(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])';

	// Is this a single IP address?
	if ( preg_match('/^'.$ip_regex.'$/', $ip) )
	{
    	$ip_from = ip2long($ip);
		$ip_to   = $ip_from;
	}
    // Is this an IP range?
	elseif ( preg_match('/^'.$ip_regex.'\-'.$ip_regex.'$/', $ip) )
	{
    	list($ip_from, $ip_to) = explode('-', $ip);
		$ip_from = ip2long($ip_from);
		$ip_to   = ip2long($ip_to);
	}
    // Is this an IP with CIDR?
	elseif ( preg_match('/^'.$ip_regex.'\/([0-9]{1,2})$/', $ip, $matches) && $matches[4] >= 0 && $matches[4] <= 32)
	{
    	list($ip_from, $ip_to) = hesk_cidr_to_range($ip);
	}
	// Not a valid input
	else
	{
    	hesk_process_messages($hesklang['validbanip'],'banned_ips.php');
	}

	// Make sure we have valid ranges
	if ($ip_from < 0)
	{
		$ip_from += 4294967296;
	}
	elseif ($ip_from > 4294967296)
	{
    	$ip_from = 4294967296;
	}
	if ($ip_to < 0)
	{
		$ip_to += 4294967296;
	}
	elseif ($ip_to > 4294967296)
	{
    	$ip_to = 4294967296;
	}

	// Make sure $ip_to is not lower that $ip_from
	if ($ip_to < $ip_from)
	{
		$tmp    = $ip_to;
    	$ip_to   = $ip_from;
		$ip_from = $tmp;
	}

	// Is this IP address already banned?
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_ips` WHERE {$ip_from} BETWEEN `ip_from` AND `ip_to` AND {$ip_to} BETWEEN `ip_from` AND `ip_to` LIMIT 1");
	if ( hesk_dbNumRows($res) == 1 )
	{
		$_SESSION['ban_ip']['id'] = hesk_dbResult($res);
		$hesklang['ipbanexists'] = ($ip_to == $ip_from) ? sprintf($hesklang['ipbanexists'], long2ip($ip_to) ) : sprintf($hesklang['iprbanexists'], long2ip($ip_from).' - '.long2ip($ip_to) );
    	hesk_process_messages($hesklang['ipbanexists'],'banned_ips.php','NOTICE');
	}

	// Delete any duplicate banned IP or ranges that are within the new banned range
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_ips` WHERE `ip_from` >= {$ip_from} AND `ip_to` <= {$ip_to}");

	// Delete temporary bans from logins table
	if ($ip_to == $ip_from)
	{
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` WHERE `ip`='".hesk_dbEscape($ip_display)."'");
	}

	// Redirect either to banned ips or ticket page from now on
	$redirect_to = ($trackingID = hesk_cleanID()) ? 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999) : 'banned_ips.php';

	// Insert the ip address into database
	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_ips` (`ip_from`,`ip_to`,`ip_display`,`banned_by`) VALUES ({$ip_from}, {$ip_to},'".hesk_dbEscape($ip_display)."','".intval($_SESSION['id'])."')");

	// Remember ip that got banned
	$_SESSION['ban_ip']['id'] = hesk_dbInsertID();

    // Generate success message
	$hesklang['ip_banned'] = ($ip_to == $ip_from) ? sprintf($hesklang['ip_banned'], long2ip($ip_to) ) : sprintf($hesklang['ip_rbanned'], long2ip($ip_from).' - '.long2ip($ip_to) );

	// Show success
    hesk_process_messages( sprintf($hesklang['ip_banned'], $ip) ,$redirect_to,'SUCCESS');

} // End ban_ip()


function unban_temp_ip()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get the ip
	$ip = preg_replace('/[^0-9\.\-\/\*]/', '', hesk_REQUEST('ip') );

	// Delete from bans
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` WHERE `ip`='" . hesk_dbEscape($ip) . "'");

	// Show success
    hesk_process_messages($hesklang['ip_tempun'],'banned_ips.php','SUCCESS');

} // End unban_temp_ip()


function unban_ip()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Delete from bans
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_ips` WHERE `id`=" . intval( hesk_GET('id') ) );

	// Redirect either to banned ips or ticket page from now on
	$redirect_to = ($trackingID = hesk_cleanID()) ? 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999) : 'banned_ips.php';

	// Show success
    hesk_process_messages($hesklang['ip_unbanned'],$redirect_to,'SUCCESS');

} // End unban_ip()


function hesk_cidr_to_range($cidr)
{
	$range = array();
	$cidr = explode('/', $cidr);
	$range[0] = (ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1])));
	$range[1] = (ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1;
	return $range;
} // END hesk_cidr_to_range()

?>
