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

// Get all the req files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_man_settings');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'edit_status') {edit_status();}
	elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'custom_statuses.php', 'NOTICE');}
	elseif ($action == 'new_status') {new_status();}
	elseif ($action == 'save_status') {save_status();}
	elseif ($action == 'remove_status') {remove_status();}
}

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
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

		// Show a link to banned_ips.php if user has permission to do so
		if ( hesk_checkPermission('can_ban_ips',0) )
		{
			echo '<li class=""><a title="' . $hesklang['banip'] . '" href="banned_ips.php">' . $hesklang['banip'] . '</a></li> ';
		}

		// Show a link to service_message.php if user has permission to do so
		if ( hesk_checkPermission('can_service_msg',0) )
		{
			echo '<li class=""><a title="' . $hesklang['sm_title'] . '" href="service_messages.php">' . $hesklang['sm_title'] . '</a></li> ';
		}

		// Show a link to email_templates.php if user has permission to do so
		if ( hesk_checkPermission('can_email_tpl',0) )
		{
			echo '<li class=""><a title="' . $hesklang['et_title'] . '" href="email_templates.php">' . $hesklang['et_title'] . '</a></li> ';
		}
		?>
        <li class=""><a title="<?php echo $hesklang['tab_4']; ?>" href="custom_fields.php"><?php echo $hesklang['tab_4']; ?></a></li>
		<li class="tabberactive"><a title="<?php echo $hesklang['statuses']; ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['statuses_intro']); ?>')"><?php echo $hesklang['statuses']; ?> [?]</a></li>
	</ul>

</div>
<!-- TABS -->

&nbsp;<br />

<?php
// Show a back link when editing
if ($action == 'edit_status')
{
	?>
	<span class="smaller"><a href="custom_statuses.php" class="smaller">&laquo; <?php echo $hesklang['statuses']; ?></a></span><br />&nbsp;
	<?php
}

/* This will handle error, success and notice messages */
hesk_handle_messages();

// Number of custom statuses
$hesk_settings['num_custom_statuses'] = count($hesk_settings['statuses']) - 6;

// Did we reach the custom statuses limit?
if ($hesk_settings['num_custom_statuses'] >= 100 && $action != 'edit_status')
{
    hesk_show_info($hesklang['status_limit']);
}
// Less than 100 custom statuses
else
{
?>

<script language="Javascript" type="text/javascript" src="<?php echo HESK_PATH; ?>inc/jscolor/jscolor.min.js"></script>
<script language="Javascript" type="text/javascript">
function hesk_preview(jscolor) {
    document.getElementById('color_preview').style.color = "#" + jscolor;
}
</script>

&nbsp;<br />

<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>

		<form action="custom_statuses.php" method="post" name="form1">

		<h3 align="center"><a name="new_status"></a><?php echo hesk_SESSION('edit_status') ? $hesklang['edit_status'] : $hesklang['new_status']; ?></h3>
		<br />

		<table border="0" width="100%">

		<tr>
		<td width="200" style="text-align:right;vertical-align:top;padding-top:5px;"><b><?php echo $hesklang['status']; ?>:</b></td>
		<td align="left">
		<?php
		$names = hesk_SESSION(array('new_status','names'));

		if ($hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1)
		{
			?>
			<table border="0">
			<?php
			foreach ($hesk_settings['languages'] as $lang => $info)
			{
				echo '
				<tr>
				<td>'.$lang.':</td>
				<td><input type="text" name="name['.$lang.']" size="30" value="'.(isset($names[$lang]) ? $names[$lang] : '').'" /></td>
				</tr>
				';
			}
			?>
			</table>
			<?php

		}
		else
		{
			?><input type="text" name="name[<?php echo $hesk_settings['language']; ?>]" size="30" value="<?php echo isset($names[$hesk_settings['language']]) ? $names[$hesk_settings['language']] : ''; ?>" /><?php
		}
		?>
		</td>
		</tr>
		<tr><td colspan="2">&nbsp;</td></tr>
		<tr>
		<td width="200" style="text-align:right"><b><?php echo $hesklang['color']; ?>:</b></td>
		<td align="left">
            <?php $color = hesk_validate_color_hex(hesk_SESSION(array('new_status','color'))); ?>
            <input type="text" class="jscolor {hash:true, uppercase:false, onFineChange:'hesk_preview(this)'}" style="border:none" name="color" size="10" value="<?php echo $color; ?>" />
            &nbsp; <span id="color_preview" style="color:<?php echo $color; ?>"><?php echo $hesklang['clr_view']; ?></span>
            </td>
		</tr>
		<tr><td colspan="2">&nbsp;</td></tr>
		<tr>
		<td width="200" style="text-align:right">&nbsp;</td>
		<td align="left">
            <b><?php echo $hesklang['ccc']; ?></b><br />
			<?php $can_customers_change = hesk_SESSION(array('new_status','can_customers_change'), 0); ?>
			<label><input type="radio" name="can_customers_change" value="0" <?php if ($can_customers_change == 0) {echo 'checked="checked"';} ?> /> <i><?php echo $hesklang['no']; ?></i></label> &nbsp;
			<label><input type="radio" name="can_customers_change" value="1" <?php if ($can_customers_change == 1) {echo 'checked="checked"';} ?> /> <i><?php echo $hesklang['yes']; ?></i></label>
		</td>
		</tr>
		</table>

		&nbsp;

		<p align="center">
		<?php echo isset($_SESSION['edit_status']) ? '<input type="hidden" name="a" value="save_status" /><input type="hidden" name="id" value="'.intval($_SESSION['new_status']['id']).'" />' : '<input type="hidden" name="a" value="new_status" />'; ?>
		<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
		<input type="submit" value="<?php echo $hesklang['status_save']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
		</p>
		</form>

	</td>
	<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
	<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
</table>

<p>&nbsp;</p>

<?php
}
// End less than 100 custom statuses

// List existing custom statuses
?>
<h3 style="padding-bottom:5px;">&raquo; <?php echo $hesklang['ex_status']; ?></h3>
&nbsp;

<div align="center">
<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
<tr>
<th class="admin_white"><b><i><?php echo $hesklang['id']; ?></i></b></th>
<th class="admin_white"><b><i><?php echo $hesklang['status']; ?></i></b></th>
<th class="admin_white"><b><i><?php echo $hesklang['csscl']; ?></i></b></th>
<th class="admin_white"><b><i><?php echo $hesklang['tickets']; ?></i></b></th>
<th class="admin_white"><b><i><?php echo $hesklang['cbc']; ?></i></b></th>
<th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo $hesklang['opt']; ?>&nbsp;</i></b></th>
</tr>
<tr>
<td class="admin_white" colspan="6" style="text-align:left;"><b><i>&raquo; <?php echo $hesklang['status_hesk']; ?></i></b></td></tr>
</tr>
<?php
// Number of tickets per status
$tickets_all = array();

$res = hesk_dbQuery('SELECT COUNT(*) AS `cnt`, `status` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` GROUP BY `status`');
while ($tmp = hesk_dbFetchAssoc($res))
{
	$tickets_all[$tmp['status']] = $tmp['cnt'];
}

$is_custom = false;

$i = 1;

foreach ($hesk_settings['statuses'] as $tmp_id => $status)
{
    $status['span'] = isset($status['class']) ? '<span class="' . $status['class'] . '">' : '<span style="color: ' . $status['color'] . '">';

    $status['color'] = isset($status['class']) ? $status['span'] . '.' . $status['class'] . '</span>' : $status['span'] . $status['color'] . '</span>';

    $status['tickets'] = isset($tickets_all[$tmp_id]) ? $tickets_all[$tmp_id] : 0;

    $status['can_customers_change'] = ! isset($status['can_customers_change']) ? '/' : ($status['can_customers_change'] == 1 ? $hesklang['yes'] : $hesklang['no']);

    if ( ! $is_custom && $tmp_id > 5)
    {
        $is_custom = true;
        echo '
        <tr>
        <td class="admin_white" colspan="6" style="text-align:left;"><b><i>&raquo; ' . $hesklang['status_custom'] . '</i></b></td></tr>
        </tr>
        ';
    }

    $color = 'admin_white'; //$i ? 'admin_white' : 'admin_gray';
    $tmp   = 'White'; //$i ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
    $i     = $i ? 0 : 1;
    ?>
    <tr>
    <td class="<?php echo $color; ?>" style="text-align:left;"><?php echo $tmp_id; ?></td>
    <td class="<?php echo $color; ?>" style="text-align:left;"><?php echo $status['span'] . $status['name']; ?></span></td>
    <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;"><?php echo $status['color']; ?></td>
    <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;"><a href="show_tickets.php?<?php echo 's'.$tmp_id.'=1'; ?>&amp;s_my=1&amp;s_ot=1&amp;s_un=1" alt="<?php echo $hesklang['list_tkt_status']; ?>" title="<?php echo $hesklang['list_tkt_status']; ?>"><?php echo $status['tickets']; ?></a></td>
    <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;"><?php echo $status['can_customers_change']; ?></td>
    <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;">
    <?php
    if ($is_custom)
    {
        ?>
        <a name="Edit <?php echo $status['name']; ?>" href="custom_statuses.php?a=edit_status&amp;id=<?php echo $tmp_id; ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo $hesklang['edit']; ?>" title="<?php echo $hesklang['edit']; ?>" <?php echo $style; ?> /></a>

        <?php
        if ($status['tickets'] > 0)
        {
            ?>
            <a onclick="alert('<?php echo hesk_makeJsString($hesklang['status_not_empty']); ?>');"><img src="../img/delete_off.png" width="16" height="16" alt="<?php echo $hesklang['status_not_empty']; ?>" title="<?php echo $hesklang['status_not_empty']; ?>" <?php echo $style; ?> /></a>&nbsp;</td>
            <?php
        }
        else
        {
            ?>
            <a name="Delete <?php echo $status['name']; ?>" href="custom_statuses.php?a=remove_status&amp;id=<?php echo $tmp_id; ?>&amp;token=<?php hesk_token_echo(); ?>" onclick="return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['del_status']); ?>');"><img src="../img/delete.png" width="16" height="16" alt="<?php echo $hesklang['delete']; ?>" title="<?php echo $hesklang['delete']; ?>" <?php echo $style; ?> /></a>&nbsp;</td>
            <?php
        }
    }
    else
    {
        echo '/';
    }
    ?>
    </tr>
    <?php
} // End foreach

// If no custom statuses, say so
if ($hesk_settings['num_custom_statuses'] == 0)
{
    ?>
    <tr>
    <td class="admin_white" colspan="6" style="text-align:left;"><b><i>&raquo; <?php echo $hesklang['status_custom']; ?></i></b></td></tr>
    </tr>
    <tr>
    <td class="admin_white" colspan="6" style="text-align:left;"><i><?php echo $hesklang['status_custom_none']; ?></i></td></tr>
    </tr>
    <?php
}
?>
</table>
</div>

<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>

<?php

hesk_cleanSessionVars( array('new_status', 'edit_status') );

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function save_status()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

	// Get custom status ID
	$id = intval( hesk_POST('id') ) or hesk_error($hesklang['status_e_id']);

	// Validate inputs
	if (($status = status_validate()) == false)
	{
		$_SESSION['edit_status'] = true;
		$_SESSION['new_status']['id'] = $id;

		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'custom_statuses.php');
	}

    // Remove # from color
    $color = str_replace('#', '', $status['color']);

	// Add custom status data into database
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` SET
	`name` = '".hesk_dbEscape($status['names'])."',
	`color` = '{$color}',
	`can_customers_change` = '{$status['can_customers_change']}'
	WHERE `id`={$id}");

	// Clear cache
	hesk_purge_cache('status');

	// Show success
	$_SESSION['statusord'] = $id;
	hesk_process_messages($hesklang['status_mdf'],'custom_statuses.php','SUCCESS');

} // End save_status()


function edit_status()
{
	global $hesk_settings, $hesklang;

	// Get custom status ID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['status_e_id']);

	// Get details from the database
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` WHERE `id`={$id} LIMIT 1");
	if ( hesk_dbNumRows($res) != 1 )
	{
		hesk_error($hesklang['status_not_found']);
	}
	$status = hesk_dbFetchAssoc($res);

	$status['names'] = json_decode($status['name'], true);
	unset($status['name']);

    $status['color'] = '#'.$status['color'];

	$_SESSION['new_status'] = $status;
	$_SESSION['edit_status'] = true;

} // End edit_status()


function update_status_order()
{
	global $hesk_settings, $hesklang;

	// Get list of current custom statuses
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` ORDER BY `order` ASC");

	// Update database
	$i = 10;
	while ( $status = hesk_dbFetchAssoc($res) )
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` SET `order`=".intval($i)." WHERE `id`='".intval($status['id'])."'");
		$i += 10;
	}

	return true;

} // END update_status_order()


function remove_status()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get ID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['status_e_id']);

    // Any tickets with this status?
    $res = hesk_dbQuery("SELECT COUNT(*) AS `cnt`, `status` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `status` = {$id}");
    if (hesk_dbResult($res) > 0)
    {
        hesk_process_messages($hesklang['status_not_empty'],'./custom_statuses.php');
    }

	// Reset the custom status
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` WHERE `id`={$id}");

	// Were we successful?
	if ( hesk_dbAffectedRows() == 1 )
	{
		// Update order
		update_status_order();

		// Clear cache
		hesk_purge_cache('status');

		// Delete custom status data from tickets
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `custom{$id}`=''");

		// Show success message
		hesk_process_messages($hesklang['status_deleted'],'./custom_statuses.php','SUCCESS');
	}
	else
	{
		hesk_process_messages($hesklang['status_not_found'],'./custom_statuses.php');
	}

} // End remove_status()


function status_validate()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	$hesk_error_buffer = array();

	// Get names
	$status['names'] = hesk_POST_array('name');

	// Make sure only valid names pass
	foreach ($status['names'] as $key => $name)
	{
		if ( ! isset($hesk_settings['languages'][$key]))
		{
			unset($status['names'][$key]);
		}
		else
		{
			$name = is_array($name) ? '' : hesk_input($name, 0, 0, HESK_SLASH);

			if (strlen($name) < 1)
			{
				unset($status['names'][$key]);
			}
			else
			{
				$status['names'][$key] = stripslashes($name);
			}
		}
	}

	// No name entered?
	if ( ! count($status['names']))
	{
		$hesk_error_buffer[] = $hesklang['err_status'];
	}

	// Color
	$status['color'] = hesk_validate_color_hex(hesk_POST('color'));

	// Can customers change it?
	$status['can_customers_change'] = hesk_POST('can_customers_change') ? 1 : 0;

	// Any errors?
	if (count($hesk_error_buffer))
	{
		$_SESSION['new_status'] = $status;
		return false;
	}

	$status['names'] = addslashes(json_encode($status['names']));

	return $status;
} // END status_validate()


function new_status()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

	// Validate inputs
	if (($status = status_validate()) == false)
	{
		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'custom_statuses.php');
	}

    // Did we reach status limit?
    if (count($hesk_settings['statuses']) >= 100)
    {
        hesk_process_messages($hesklang['status_limit'],'custom_statuses.php');
    }

    // Lowest available ID for custom statuses is 6
    $next_id = 6;

	// Any existing statuses?
    if (count($hesk_settings['statuses']) > 6)
    {
        // The lowest currently used ID
        $res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` ORDER BY `id` ASC LIMIT 1");
        $lowest_id = hesk_dbResult($res);

        if ($lowest_id > 6)
        {
            $next_id = 6;
        }
        else
        {
            // Minimum next ID
          	$res = hesk_dbQuery("
                  SELECT MIN(`t1`.`id` + 1) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` AS `t1`
                      LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` AS `t2`
                           ON `t1`.`id` + 1 = `t2`.`id`
                  WHERE `t2`.`id` IS NULL"
            );
            $next_id = hesk_dbResult($res);
        }
    }

    // Remove # from color
    $color = str_replace('#', '', $status['color']);

	// Insert custom status into database
	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` (`id`, `name`, `color`, `can_customers_change`, `order`) VALUES (".intval($next_id).", '".hesk_dbEscape($status['names'])."', '{$color}', '{$status['can_customers_change']}', 990)");

	// Update order
	update_status_order();

	// Clear cache
	hesk_purge_cache('status');

	// Show success
	hesk_process_messages($hesklang['status_added'],'custom_statuses.php','SUCCESS');

} // End new_status()


function hesk_validate_color_hex($hex, $def = '#000000')
{
    $hex = strtolower($hex);
    return preg_match('/^\#[a-f0-9]{6}$/', $hex) ? $hex : $def;
} // END hesk_validate_color_hex()


function hesk_get_text_color($bg_color)
{
    // Get RGB values
    list($r, $g, $b) = sscanf($bg_color, "#%02x%02x%02x");

    // Is Black a good text color?
    if (hesk_color_diff($r, $g, $b, 0, 0, 0) >= 500)
    {
        return '#000000';
    }

    // Use white instead
    return '#ffffff';
} // END hesk_get_text_color()


function hesk_color_diff($R1,$G1,$B1,$R2,$G2,$B2)
{
    return max($R1,$R2) - min($R1,$R2) +
           max($G1,$G2) - min($G1,$G2) +
           max($B1,$B2) - min($B1,$B2);
} // END hesk_color_diff()
