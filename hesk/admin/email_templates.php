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
hesk_checkPermission('can_email_tpl');

// Define required constants
define('LOAD_TABS',1);

// Get valid email templates
require(HESK_PATH . 'inc/email_functions.inc.php');
$emails = array_keys(hesk_validEmails());

// Which language are we editing?
if ($hesk_settings['can_sel_lang'])
{
	$hesk_settings['edit_language'] = hesk_REQUEST('edit_language');
	if ( ! isset($hesk_settings['languages'][$hesk_settings['edit_language']]) )
	{
		$hesk_settings['edit_language'] = $hesk_settings['language'];
	}
}
else
{
	$hesk_settings['edit_language'] = $hesk_settings['language'];
}

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'edit') {}
	elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'email_templates.php', 'NOTICE');}
	elseif ($action == 'save') {save_et();}
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
		?>
		<li class="tabberactive"><a title="<?php echo $hesklang['et_title']; ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['et_intro']); ?>')"><?php echo $hesklang['et_title']; ?> [?]</a></li>
		<?php
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

<?php
// Show a back link when editing
if ($action == 'edit')
{
	?>
	<span class="smaller"><a href="email_templates.php?edit_language=<?php echo urlencode($hesk_settings['edit_language']); ?>" class="smaller">&laquo; <?php echo $hesklang['et_title']; ?></a></span><br />&nbsp;
	<?php
}

/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

&nbsp;<br />

<?php
if ( isset($_SESSION['new_sm']) && ! isset($_SESSION['edit_sm']) )
{
	$_SESSION['new_sm'] = hesk_stripArray($_SESSION['new_sm']);
}

if ( isset($_SESSION['preview_sm']) )
{
	hesk_service_message($_SESSION['new_sm']);
}

// EDIT
if ($action == 'edit')
{
	// Get email ID
	$email = hesk_GET('id');

	// Get file path
	$eml_file = et_file_path($email);

	// Make sure the file exists and is writable
	if ( ! file_exists($eml_file))
	{
   		hesk_error($hesklang['et_fm']);
	}
	elseif ( ! is_writable($eml_file))
	{
		hesk_error($hesklang['et_fw']);
	}

	// Start the edit form
	?>

	<table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
			<td class="roundcornerstop"></td>
			<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
		</tr>
		<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>

		<div align="center">
		<table border="0">
		<tr>
		<td>

		<form method="get" action="email_templates.php" style="margin:0;padding:0;border:0;white-space:nowrap;">
		<?php
		echo $hesklang['efile'] . ': ';
		?>
		<select name="id" onchange="this.form.submit()">
		<?php
		foreach ($emails as $email_tmp)
		{
			$eml_file_tmp = et_file_path($email_tmp);

			if ( ! file_exists($eml_file_tmp))
            {
            	echo '<option disabled="disabled">' . $hesklang['desc_'.$email_tmp].' --- ' . $hesklang['no_exists'] . '</option>';
			}
			elseif ( ! is_writable($eml_file_tmp))
            {
            	echo '<option disabled="disabled">' . $hesklang['desc_'.$email_tmp].' --- ' . $hesklang['not_writable'] . '</option>';
			}
			else
			{
				if ($email_tmp == $email)
				{
					echo '<option value="'.$email_tmp.'" selected="selected">' . $hesklang['desc_'.$email_tmp].'</option>';
				}
				else
				{
					echo '<option value="'.$email_tmp.'">' . $hesklang['desc_'.$email_tmp].'</option>';
				}
			}
		}
		?>
		</select>
		<input type="hidden" name="a" value="edit" />
		<input type="hidden" name="edit_language" value="<?php echo hesk_htmlspecialchars($hesk_settings['edit_language']); ?>" />
		</form>
		&nbsp;

		<?php
		if ($hesk_settings['can_sel_lang'])
		{
			?>
			<form method="get" action="email_templates.php" style="margin:0;padding:0;border:0;white-space:nowrap;">
			<?php echo $hesklang['lgs'] . ': '; ?>
			<select name="edit_language" onchange="this.form.submit()">
			<?php
			foreach ($hesk_settings['languages'] as $lang => $info)
			{
				if ($lang == $hesk_settings['edit_language'])
				{
					echo '<option value="'.$lang.'" selected="selected">'.$lang.'</option>';
				}
				else
				{
					echo '<option value="'.$lang.'">'.$lang.'</option>';
				}
			}
			?>
			</select>
			<input type="hidden" name="a" value="edit" />
			<input type="hidden" name="id" value="<?php echo $email; ?>" />
			</form>
			&nbsp;
			<?php
		}
		?>

		<form action="email_templates.php" method="post" name="form1">
		<?php echo $hesklang['source'] . ': ' . substr($eml_file, 2); ?><br /><span id="HeskMsg"><textarea name="msg" rows="15" cols="70"><?php echo hesk_htmlspecialchars(file_get_contents($eml_file)); ?></textarea></span><br />

		<?php echo $hesklang['insert_special']; ?>:<br />
		<?php
		if ($email == 'forgot_ticket_id')
		{
		?>
		<a href="javascript:void(0)" title="%%NAME%%" onclick="hesk_insertTag('NAME')"><?php echo $hesklang['name']; ?></a> |
		<a href="javascript:void(0)" title="%%FIRST_NAME%%" onclick="hesk_insertTag('FIRST_NAME')"><?php echo $hesklang['fname']; ?></a> |
		<a href="javascript:void(0)" title="%%NUM%%" onclick="hesk_insertTag('NUM')"><?php echo $hesklang['et_num']; ?></a> |
		<a href="javascript:void(0)" title="%%LIST_TICKETS%%" onclick="hesk_insertTag('LIST_TICKETS')"><?php echo $hesklang['et_list']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_TITLE%%" onclick="hesk_insertTag('SITE_TITLE')"><?php echo $hesklang['wbst_title']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_URL%%" onclick="hesk_insertTag('SITE_URL')"><?php echo $hesklang['wbst_url']; ?></a>
		<?php
		}
		elseif ($email == 'new_pm')
		{
		?>
		<a href="javascript:void(0)" title="%%NAME%%" onclick="hesk_insertTag('NAME')"><?php echo $hesklang['name']; ?></a> |
		<a href="javascript:void(0)" title="%%FIRST_NAME%%" onclick="hesk_insertTag('FIRST_NAME')"><?php echo $hesklang['fname']; ?></a> |
		<a href="javascript:void(0)" title="%%SUBJECT%%" onclick="hesk_insertTag('SUBJECT')"><?php echo $hesklang['subject']; ?></a> |
		<a href="javascript:void(0)" title="%%MESSAGE%%" onclick="hesk_insertTag('MESSAGE')"><?php echo $hesklang['message']; ?></a> |
		<a href="javascript:void(0)" title="%%TRACK_URL%%" onclick="hesk_insertTag('TRACK_URL')"><?php echo $hesklang['pm_url']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_TITLE%%" onclick="hesk_insertTag('SITE_TITLE')"><?php echo $hesklang['wbst_title']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_URL%%" onclick="hesk_insertTag('SITE_URL')"><?php echo $hesklang['wbst_url']; ?></a>
		<?php
		}
		else
		{
		?>
		<a href="javascript:void(0)" title="%%NAME%%" onclick="hesk_insertTag('NAME')"><?php echo $hesklang['name']; ?></a> |
		<a href="javascript:void(0)" title="%%FIRST_NAME%%" onclick="hesk_insertTag('FIRST_NAME')"><?php echo $hesklang['fname']; ?></a> |
		<a href="javascript:void(0)" title="%%EMAIL%%" onclick="hesk_insertTag('EMAIL')"><?php echo $hesklang['email']; ?></a> |
		<a href="javascript:void(0)" title="%%CATEGORY%%" onclick="hesk_insertTag('CATEGORY')"><?php echo $hesklang['category']; ?></a> |
		<a href="javascript:void(0)" title="%%PRIORITY%%" onclick="hesk_insertTag('PRIORITY')"><?php echo $hesklang['priority']; ?></a> |
		<a href="javascript:void(0)" title="%%STATUS%%" onclick="hesk_insertTag('STATUS')"><?php echo $hesklang['status']; ?></a> |
		<a href="javascript:void(0)" title="%%SUBJECT%%" onclick="hesk_insertTag('SUBJECT')"><?php echo $hesklang['subject']; ?></a> |
		<a href="javascript:void(0)" title="%%MESSAGE%%" onclick="hesk_insertTag('MESSAGE')"><?php echo $hesklang['message']; ?></a><br />

		<a href="javascript:void(0)" title="%%CREATED%%" onclick="hesk_insertTag('CREATED')"><?php echo $hesklang['created_on']; ?></a> |
		<a href="javascript:void(0)" title="%%UPDATED%%" onclick="hesk_insertTag('UPDATED')"><?php echo $hesklang['updated_on']; ?></a> |
		<a href="javascript:void(0)" title="%%OWNER%%" onclick="hesk_insertTag('OWNER')"><?php echo $hesklang['owner']; ?></a> |
		<a href="javascript:void(0)" title="%%LAST_REPLY_BY%%" onclick="hesk_insertTag('LAST_REPLY_BY')"><?php echo $hesklang['last_replier']; ?></a> |
		<a href="javascript:void(0)" title="%%TIME_WORKED%%" onclick="hesk_insertTag('TIME_WORKED')"><?php echo $hesklang['ts']; ?></a><br />

		<a href="javascript:void(0)" title="%%TRACK_ID%%" onclick="hesk_insertTag('TRACK_ID')"><?php echo $hesklang['trackID']; ?></a> |
		<a href="javascript:void(0)" title="%%ID%%" onclick="hesk_insertTag('ID')"><?php echo $hesklang['seqid']; ?></a> |
		<a href="javascript:void(0)" title="%%TRACK_URL%%" onclick="hesk_insertTag('TRACK_URL')"><?php echo $hesklang['ticket_url']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_TITLE%%" onclick="hesk_insertTag('SITE_TITLE')"><?php echo $hesklang['wbst_title']; ?></a> |
		<a href="javascript:void(0)" title="%%SITE_URL%%" onclick="hesk_insertTag('SITE_URL')"><?php echo $hesklang['wbst_url']; ?></a><br />
		<?php
			$i=1;
			foreach ($hesk_settings['custom_fields'] as $k=>$v)
			{
				if ($v['use'])
				{
                	if ($i != 1)
					{
                    	echo ' | ';
					}

					echo '<a href="javascript:void(0)" title="%%'.strtoupper($k).'%%" onclick="hesk_insertTag(\''.strtoupper($k).'\')">'.$v['name'].'</a>';

					if ($i == 5)
					{
                    	$i = 0;
						echo '<br />';
					}

					$i++;
				}
			}
		}
		?>

		<p>&nbsp;</p>

		<p align="center">
	    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
		<input type="hidden" name="a" value="save" />
		<input type="hidden" name="edit_language" value="<?php echo hesk_htmlspecialchars($hesk_settings['edit_language']); ?>" />
		<input type="hidden" name="id" value="<?php echo $email; ?>" />
	    <input type="submit" value="<?php echo $hesklang['et_save']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
	    </p>
		</form>

		</td>
		</tr>
		</table>
		</div>

	    </td>
		<td class="roundcornersright">&nbsp;</td>
		</tr>
		<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
		</tr>
	</table>
	<?php
} // END EDIT, START LIST
else
{
	?>
	<form method="get" action="email_templates.php" style="margin:0;padding:0;border:0;white-space:nowrap;">
	<h3>&raquo; <?php echo $hesklang['et_title']; ?>
	<?php
	if ($hesk_settings['can_sel_lang'])
	{
		?>
		<select name="edit_language" onchange="this.form.submit()">
		<?php
		foreach ($hesk_settings['languages'] as $lang => $info)
		{
			if ($lang == $hesk_settings['edit_language'])
			{
				echo '<option value="'.$lang.'" selected="selected">'.$lang.'</option>';
			}
			else
			{
				echo '<option value="'.$lang.'">'.$lang.'</option>';
			}
		}
		?>
		</select>
		<?php
	}
	?>
	</h3>
	</form>
	&nbsp;

	<div align="center">
	<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
	<tr>
	<th class="admin_white"><b><i><?php echo $hesklang['file']; ?></i></b></th>
	<th class="admin_white"><b><i><?php echo $hesklang['rdesc']; ?></i></b></th>
	<th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo $hesklang['edit']; ?>&nbsp;</i></b></th>
	</tr>
	<?php

	$all_files = true;
	$all_writable = true;

	foreach ($emails as $email)
	{
		$eml_file = et_file_path($email);
		?>
		<tr>
		<td class="admin_white"><?php echo $email; ?>.txt</td>
		<td class="admin_white"><?php echo $hesklang['desc_'.$email]; ?></td>
		<td class="admin_white" style="text-align:center; white-space:nowrap;">
		<?php
		if ( ! file_exists($eml_file))
		{
			$all_files = false;
			echo '<span style="color:red">'.$hesklang['no_exists'].'</span>';
		}
		elseif ( ! is_writable($eml_file))
		{
			$all_writable = false;
			echo '<span style="color:red">'.$hesklang['not_writable'].'</span>';
		}
		else
		{
			?>
			<a name="Edit <?php echo $email; ?>" href="email_templates.php?a=edit&amp;id=<?php echo $email; ?>&amp;edit_language=<?php echo urlencode($hesk_settings['edit_language']); ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo $hesklang['edit']; ?>" title="<?php echo $hesklang['edit']; ?>" class="optionWhiteOFF" onmouseover="this.className='optionWhiteON'" onmouseout="this.className='optionWhiteOFF'" /></a>
			<?php
		}
		?>
		</td>
		</tr>
		<?php
	}

	?>
	</table>
	</div>

	<p>&nbsp;</p>

	<?php
	// Any template missing?
	if ( ! $all_files)
	{
		hesk_show_error(sprintf($hesklang['etfm'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder']));
	}

	// Any template not writable?
	if ( ! $all_writable)
	{
		hesk_show_error(sprintf($hesklang['etfw'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder']));
	}
	?>

	<p>&nbsp;</p>
	<p>&nbsp;</p>

	<?php
} // END LIST

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function save_et()
{
	global $hesk_settings, $hesklang, $emails;

	// A security check
	# hesk_token_check('POST');

	// Get email ID
	$email = hesk_POST('id');

	// Get file path
	$eml_file = et_file_path($email);

	// Make sure the file exists and is writable
	if ( ! file_exists($eml_file))
	{
   		hesk_error($hesklang['et_fm']);
	}
	elseif ( ! is_writable($eml_file))
	{
		hesk_error($hesklang['et_fw']);
	}

	// Get message
	$message = trim(hesk_POST('msg'));

	// Do we need to remove backslashes from the message?
	if ( ! HESK_SLASH)
	{
    	$message = stripslashes($message);
	}

	// We won't accept an empty message
	if ( ! strlen($message))
	{
		hesk_process_messages($hesklang['et_empty'],'email_templates.php?a=edit&id=' . $email . '&edit_language='.$hesk_settings['edit_language']);
	}

	// Save to the file
	file_put_contents($eml_file, $message);

	// Show success
    $_SESSION['et_id'] = $email;
    hesk_process_messages($hesklang['et_saved'],'email_templates.php?edit_language='.$hesk_settings['edit_language'],'SUCCESS');
} // End save_et()


function et_file_path($id)
{
	global $hesk_settings, $hesklang, $emails;

	if ( ! in_array($id, $emails))
	{
    	hesk_error($hesklang['inve']);
	}

	return HESK_PATH . 'language/' . $hesk_settings['languages'][$hesk_settings['edit_language']]['folder'] . '/emails/' . $id . '.txt';
} // END et_file_path()

?>
