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
hesk_checkPermission('can_service_msg');

// Define required constants
define('LOAD_TABS',1);
define('WYSIWYG',1);

// Do we need to show the language options?
$hesk_settings['show_language'] = (count($hesk_settings['languages']) > 1);

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'edit_sm') {edit_sm();}
	elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'service_messages.php', 'NOTICE');}
	elseif ($action == 'new_sm') {new_sm();}
	elseif ($action == 'save_sm') {save_sm();}
	elseif ($action == 'order_sm') {order_sm();}
	elseif ($action == 'remove_sm') {remove_sm();}
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
		?>
		<li class="tabberactive"><a title="<?php echo $hesklang['sm_title']; ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['sm_intro']); ?>')"><?php echo $hesklang['sm_title']; ?> [?]</a></li>
		<?php
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

<?php
// Show a back link when editing
if ($action == 'edit_sm')
{
	?>
	<span class="smaller"><a href="service_messages.php" class="smaller">&laquo; <?php echo $hesklang['sm_title']; ?></a></span><br />&nbsp;
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

        <?php
        if ($hesk_settings['kb_wysiwyg'])
        {
	        ?>
			<script type="text/javascript">
			tinyMCE.init({
				mode : "exact",
				elements : "content",
				theme : "advanced",
                   convert_urls : false,
                   gecko_spellcheck: true,

				theme_advanced_buttons1 : "cut,copy,paste,|,undo,redo,|,formatselect,fontselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull",
				theme_advanced_buttons2 : "sub,sup,|,charmap,|,bullist,numlist,|,outdent,indent,insertdate,inserttime,preview,|,forecolor,backcolor,|,hr,removeformat,visualaid,|,link,unlink,anchor,image,cleanup,code",
				theme_advanced_buttons3 : "",

				theme_advanced_toolbar_location : "top",
				theme_advanced_toolbar_align : "left",
				theme_advanced_statusbar_location : "bottom",
				theme_advanced_resizing : true
			});
			</script>
	        <?php
        }
        ?>

	    <form action="service_messages.php" method="post" name="form1">

		<h3 align="center"><a name="new_article"></a><?php echo hesk_SESSION('edit_sm') ? $hesklang['edit_sm'] : $hesklang['new_sm']; ?></h3>
	    <br />

		<table border="0">
		<tr>
		<td valign="middle"><b><?php echo $hesklang['sm_style']; ?>:</b></td>
		<td>
			<div class="none" style="margin-right:10px;float:left"><label><input type="radio" name="style" value="0" <?php if (!isset($_SESSION['new_sm']['style']) || (isset($_SESSION['new_sm']['style']) && $_SESSION['new_sm']['style'] == 0) ) {echo 'checked="checked"';} ?> /> <b><?php echo $hesklang['sm_none']; ?></b></label></div>
			<div class="success" style="margin-right:10px;float:left"><label><input type="radio" name="style" value="1" <?php if (isset($_SESSION['new_sm']['style']) && $_SESSION['new_sm']['style'] == 1 ) {echo 'checked="checked"';} ?> /> <b><?php echo $hesklang['sm_success']; ?></b></label></div>
			<div class="info" style="margin-right:10px;float:left"><label><input type="radio" name="style" value="2" <?php if (isset($_SESSION['new_sm']['style']) && $_SESSION['new_sm']['style'] == 2) {echo 'checked="checked"';} ?> /> <b><?php echo $hesklang['sm_info']; ?></b></label></div>
			<div class="notice" style="margin-right:10px;float:left"><label><input type="radio" name="style" value="3" <?php if (isset($_SESSION['new_sm']['style']) && $_SESSION['new_sm']['style'] == 3) {echo 'checked="checked"';} ?> /> <b><?php echo $hesklang['sm_notice']; ?></b></label></div>
			<div class="error" style="float:left"><label><input type="radio" name="style" value="4" <?php if (isset($_SESSION['new_sm']['style']) && $_SESSION['new_sm']['style'] == 4) {echo 'checked="checked"';} ?> /> <b><?php echo $hesklang['sm_error']; ?></b></label></div>
		</td>
		</tr>
		<tr>
		<td cellspan="2">&nbsp;</td>
		</tr>
		<tr>
		<td valign="top"><b><?php echo $hesklang['sm_type']; ?>:</b></td>
		<td>
		<label><input type="radio" name="type" value="0" <?php if (!isset($_SESSION['new_sm']['type']) || (isset($_SESSION['new_sm']['type']) && $_SESSION['new_sm']['type'] == 0) ) {echo 'checked="checked"';} ?> /> <i><?php echo $hesklang['sm_published']; ?></i></label>
		&nbsp;|&nbsp;
		<label><input type="radio" name="type" value="1" <?php if (isset($_SESSION['new_sm']['type']) && $_SESSION['new_sm']['type'] == 1) {echo 'checked="checked"';} ?> /> <i><?php echo $hesklang['sm_draft']; ?></i></label><br />&nbsp;
		</td>
		</tr>
        <?php
        if ($hesk_settings['show_language'])
        {
            ?>
    		<tr>
    		<td valign="top"><b><?php echo $hesklang['lgs']; ?>:</b></td>
    		<td><select name="language" id="language">
            <option value=""><?php echo $hesklang['all']; ?></option>
            <?php
            foreach ($hesk_settings['languages'] as $lang => $v)
            {
                echo '<option '.(isset($_SESSION['new_sm']['language']) && $_SESSION['new_sm']['language'] == $lang ? 'selected="selected"' : '').'>'.$lang.'</option>';
            }
            ?>
            </select></td>
    		</tr>
    		<tr>
    		<td cellspan="2">&nbsp;</td>
    		</tr>
            <?php
        }
        ?>
		<tr>
		<td><b><?php echo $hesklang['sm_mtitle']; ?>:</b></td>
		<td><input type="text" name="title" size="70" maxlength="255" <?php if (isset($_SESSION['new_sm']['title'])) {echo 'value="'.$_SESSION['new_sm']['title'].'"';} ?> /></td>
		</tr>
		</table>

		<p>&nbsp;<br /><b><?php echo $hesklang['sm_msg']; ?>:</b></p>

		<p><textarea name="message" rows="25" cols="70" id="content"><?php if (isset($_SESSION['new_sm']['message'])) {echo $_SESSION['new_sm']['message'];} ?></textarea></p>

		<p align="center">
        <?php echo isset($_SESSION['edit_sm']) ? '<input type="hidden" name="a" value="save_sm" /><input type="hidden" name="id" value="'.intval($_SESSION['new_sm']['id']).'" />' : '<input type="hidden" name="a" value="new_sm" />'; ?>
        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
        <input type="submit" name="sm_save" value="<?php echo $hesklang['sm_save']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
		<input type="submit" name="sm_preview" value="<?php echo $hesklang['sm_preview']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
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

<p>&nbsp;</p>

<?php

// Get service messages from database
$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'service_messages` ORDER BY `order` ASC');
$num = hesk_dbNumRows($res);

if ($num < 1)
{
    echo '<p><i>'.$hesklang['no_sm'].'</i></p>';
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
	<h3 style="padding-bottom:5px;">&raquo; <?php echo $hesklang['ex_sm']; ?></h3>
	&nbsp;

	<div align="center">
	<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
	<tr>
	<th class="admin_white"><b><i><?php echo $hesklang['sm_mtitle']; ?></i></b></th>
    <?php
    if ($hesk_settings['show_language'])
    {
        ?>
        <th class="admin_white"><b><i><?php echo $hesklang['lgs']; ?></i></b></th>
        <?php
    }
    ?>
	<th class="admin_white"><b><i><?php echo $hesklang['sm_author']; ?></i></b></th>
	<th class="admin_white"><b><i><?php echo $hesklang['sm_type']; ?></i></b></th>
	<th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo $hesklang['opt']; ?>&nbsp;</i></b></th>
	</tr>
	<?php
	$i = 1;
	$j = 1;
	$k = 1;

    while ($sm=hesk_dbFetchAssoc($res))
    {
		switch ($sm['style'])
		{
        	case 1:
				$sm_style = "success";
				break;
        	case 2:
				$sm_style = "info";
				break;
        	case 3:
				$sm_style = "notice";
				break;
        	case 4:
				$sm_style = "error";
				break;
        	default:
				$sm_style = "none";
		}

		$type = $sm['type'] ? $hesklang['sm_draft']: $hesklang['sm_published'];

		$color = 'admin_white'; //$i ? 'admin_white' : 'admin_gray';
		$tmp   = 'White'; //$i ? 'White' : 'Blue';
	    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
	    $i     = $i ? 0 : 1;

		?>
		<tr>
			<td class="<?php echo $color; ?>" style="text-align:left; padding:5px;" width="50%">
				<div class="<?php echo $sm_style; ?>">
                <?php
                if ($sm_style != 'none')
                {
                ?>
				<img src="<?php echo HESK_PATH; ?>img/<?php echo $sm_style; ?>.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
                <?php
                }
                ?>
				<?php echo $sm['title']; ?></b>
				</div>
			</td>
            <?php
            if ($hesk_settings['show_language'])
            {
                ?>
                <td class="<?php echo $color; ?>" style="text-align:left; white-space:nowrap;"><?php echo strlen($sm['language']) ? $sm['language'] : $hesklang['all']; ?></td>
                <?php
            }
            ?>
			<td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;"><?php echo (isset($admins[$sm['author']]) ? $admins[$sm['author']] : $hesklang['e_udel']); ?></td>
			<td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;"><?php echo $type; ?></td>
			<td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;">
			<?php
			if ($num > 1)
			{
				if ($k == 1)
				{
					?>
					<img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />
					<a href="service_messages.php?a=order_sm&amp;id=<?php echo $sm['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"><img src="../img/move_down.png" width="16" height="16" alt="<?php echo $hesklang['move_dn']; ?>" title="<?php echo $hesklang['move_dn']; ?>" <?php echo $style; ?> /></a>
					<?php
				}
				elseif ($k == $num)
				{
					?>
					<a href="service_messages.php?a=order_sm&amp;id=<?php echo $sm['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"><img src="../img/move_up.png" width="16" height="16" alt="<?php echo $hesklang['move_up']; ?>" title="<?php echo $hesklang['move_up']; ?>" <?php echo $style; ?> /></a>
					<img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />
					<?php
				}
				else
				{
					?>
					<a href="service_messages.php?a=order_sm&amp;id=<?php echo $sm['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"><img src="../img/move_up.png" width="16" height="16" alt="<?php echo $hesklang['move_up']; ?>" title="<?php echo $hesklang['move_up']; ?>" <?php echo $style; ?> /></a>
					<a href="service_messages.php?a=order_sm&amp;id=<?php echo $sm['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"><img src="../img/move_down.png" width="16" height="16" alt="<?php echo $hesklang['move_dn']; ?>" title="<?php echo $hesklang['move_dn']; ?>" <?php echo $style; ?> /></a>
					<?php
				}
			}
			?>
			<a name="Edit <?php echo $sm['title']; ?>" href="service_messages.php?a=edit_sm&amp;id=<?php echo $sm['id']; ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo $hesklang['edit']; ?>" title="<?php echo $hesklang['edit']; ?>" <?php echo $style; ?> /></a>
			<a name="Delete <?php echo $sm['title']; ?>" href="service_messages.php?a=remove_sm&amp;id=<?php echo $sm['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" onclick="return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['del_sm']); ?>');"><img src="../img/delete.png" width="16" height="16" alt="<?php echo $hesklang['delete']; ?>" title="<?php echo $hesklang['delete']; ?>" <?php echo $style; ?> /></a>&nbsp;</td>
		</tr>
		<?php
		$j++;
		$k++;
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

hesk_cleanSessionVars( array('new_sm', 'preview_sm', 'edit_sm') );

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function save_sm()
{
	global $hesk_settings, $hesklang, $listBox;
    global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

    $hesk_error_buffer = array();

	// Get service messageID
	$id = intval( hesk_POST('id') ) or hesk_error($hesklang['sm_e_id']);

	$style = intval( hesk_POST('style', 0) );
	if ($style > 4 || $style < 0)
	{
    	$style = 0;
	}

    $type  = empty($_POST['type']) ? 0 : 1;
    $title = hesk_input( hesk_POST('title') ) or $hesk_error_buffer[] = $hesklang['sm_e_title'];
	$message = hesk_getHTML( hesk_POST('message') );

    // Clean the HTML code
    require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
    $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
    $message = $purifier->heskPurify($message);

    // Any errors?
    if (count($hesk_error_buffer))
    {
		$_SESSION['edit_sm'] = true;

		$_SESSION['new_sm'] = array(
		'id' => $id,
		'style' => $style,
		'type' => $type,
		'title' => $title,
		'message' => hesk_input( hesk_POST('message') ),
		);

		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

    	$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    	hesk_process_messages($hesk_error_buffer,'service_messages.php');
    }

	// Just preview the message?
	if ( isset($_POST['sm_preview']) )
	{
    	$_SESSION['preview_sm'] = true;
		$_SESSION['edit_sm'] = true;

		$_SESSION['new_sm'] = array(
		'id' => $id,
		'style' => $style,
		'type' => $type,
		'title' => $title,
		'message' => $message,
		);

		header('Location: service_messages.php');
		exit;
	}

	// Update the service message in the database
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` SET
	`author` = '".intval($_SESSION['id'])."',
	`title` = '".hesk_dbEscape($title)."',
	`message` = '".hesk_dbEscape($message)."',
	`style` = '{$style}',
	`type` = '{$type}'
	WHERE `id`={$id}");

    $_SESSION['smord'] = $id;
    hesk_process_messages($hesklang['sm_mdf'],'service_messages.php','SUCCESS');

} // End save_sm()


function edit_sm()
{
	global $hesk_settings, $hesklang;

	// Get service messageID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['sm_e_id']);

	// Get details from the database
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` WHERE `id`={$id} LIMIT 1");
	if ( hesk_dbNumRows($res) != 1 )
	{
    	hesk_error($hesklang['sm_not_found']);
	}
	$sm = hesk_dbFetchAssoc($res);

	$_SESSION['new_sm'] = $sm;
	$_SESSION['edit_sm'] = true;

} // End edit_sm()


function order_sm()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get ID and move parameters
	$id    = intval( hesk_GET('id') ) or hesk_error($hesklang['sm_e_id']);
	$move  = intval( hesk_GET('move') );
    $_SESSION['smord'] = $id;

	// Update article details
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` SET `order`=`order`+".intval($move)." WHERE `id`={$id}");

    // Update order of all service messages
    update_sm_order();

	// Finish
	header('Location: service_messages.php');
	exit();

} // End order_sm()


function update_sm_order()
{
	global $hesk_settings, $hesklang;

	// Get list of current service messages
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` ORDER BY `order` ASC");

	// Update database
	$i = 10;
	while ( $sm = hesk_dbFetchAssoc($res) )
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` SET `order`=".intval($i)." WHERE `id`='".intval($sm['id'])."'");
		$i += 10;
	}

	return true;

} // END update_sm_order()


function remove_sm()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get ID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['sm_e_id']);

	// Delete the service message
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` WHERE `id`={$id}");

	// Were we successful?
    if ( hesk_dbAffectedRows() == 1 )
	{
		hesk_process_messages($hesklang['sm_deleted'],'./service_messages.php','SUCCESS');
	}
	else
	{
		hesk_process_messages($hesklang['sm_not_found'],'./service_messages.php');
	}

} // End remove_sm()


function new_sm()
{
	global $hesk_settings, $hesklang, $listBox;
    global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

    $hesk_error_buffer = array();

	$style = intval( hesk_POST('style', 0) );
	if ($style > 4 || $style < 0)
	{
    	$style = 0;
	}

    $type  = empty($_POST['type']) ? 0 : 1;
    $language = hesk_input( hesk_POST('language') );
    if ( ! isset($hesk_settings['languages'][$language]))
    {
        $language = '';
    }
    $title = hesk_input( hesk_POST('title') ) or $hesk_error_buffer[] = $hesklang['sm_e_title'];
	$message = hesk_getHTML( hesk_POST('message') );

    // Clean the HTML code
    require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
    $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
    $message = $purifier->heskPurify($message);

    // Any errors?
    if (count($hesk_error_buffer))
    {
		$_SESSION['new_sm'] = array(
		'style' => $style,
		'type' => $type,
        'language' => $language,
		'title' => $title,
		'message' => hesk_input( hesk_POST('message') ),
		);

		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

    	$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    	hesk_process_messages($hesk_error_buffer,'service_messages.php');
    }

	// Just preview the message?
	if ( isset($_POST['sm_preview']) )
	{
    	$_SESSION['preview_sm'] = true;

		$_SESSION['new_sm'] = array(
		'style' => $style,
		'type' => $type,
        'language' => $language,
		'title' => $title,
		'message' => $message,
		);

		header('Location: service_messages.php');
		exit;
	}

	// Get the latest service message order
	$res = hesk_dbQuery("SELECT `order` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` ORDER BY `order` DESC LIMIT 1");
	$row = hesk_dbFetchRow($res);
	$my_order = intval($row[0]) + 10;

    // Insert service message into database
	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` (`author`,`title`,`message`,`language`,`style`,`type`,`order`) VALUES (
    '".intval($_SESSION['id'])."',
    '".hesk_dbEscape($title)."',
    '".hesk_dbEscape($message)."',
    ".(strlen($language) ? "'".hesk_dbEscape($language)."'" : 'NULL').",
    '{$style}',
    '{$type}',
    '{$my_order}'
    )");

    $_SESSION['smord'] = hesk_dbInsertID();
    hesk_process_messages($hesklang['sm_added'],'service_messages.php','SUCCESS');

} // End new_sm()

?>
