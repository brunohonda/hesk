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
require(HESK_PATH . 'inc/email_functions.inc.php');

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* List of staff */
$admins = array();
$res = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row=hesk_dbFetchAssoc($res))
{
	$admins[$row['id']]=$row['name'];
}

/* What folder are we in? */
$hesk_settings['mailtmp']['inbox']  = '<a href="mail.php"><img src="../img/inbox.png" width="16" height="16" alt="'.$hesklang['inbox'].'" title="'.$hesklang['inbox'].'" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php">'.$hesklang['inbox'].'</a>';
$hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox"><img src="../img/outbox.png" width="16" height="16" alt="'.$hesklang['outbox'].'" title="'.$hesklang['outbox'].'" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php?folder=outbox">'.$hesklang['outbox'].'</a>';
$hesk_settings['mailtmp']['new']    = '<a href="mail.php?a=new"><img src="../img/new_mail.png" width="16" height="16" alt="'.$hesklang['m_new'].'" title="'.$hesklang['m_new'].'" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php?a=new">'.$hesklang['m_new'].'</a>';

/* Get action */
if ( $action = hesk_REQUEST('a') )
{
	if ( defined('HESK_DEMO') && $action != 'new' && $action != 'read' )
	{
		hesk_process_messages($hesklang['ddemo'], 'mail.php', 'NOTICE');
	}
}

/* Sub-page specific settings */
if (isset($_GET['folder']) && hesk_GET('folder') == 'outbox')
{
	$hesk_settings['mailtmp']['this']   = 'from';
	$hesk_settings['mailtmp']['other']  = 'to';
	$hesk_settings['mailtmp']['m_from'] = $hesklang['m_to'];
	$hesk_settings['mailtmp']['outbox'] = '<b><img src="../img/outbox.png" width="16" height="16" alt="'.$hesklang['outbox'].'" title="'.$hesklang['outbox'].'" border="0" style="border:none;vertical-align:text-bottom" /> '.$hesklang['outbox'].'</b>';
    $hesk_settings['mailtmp']['folder'] = 'outbox';
}
elseif ($action == 'new')
{
	$hesk_settings['mailtmp']['new'] = '<b><img src="../img/new_mail.png" width="16" height="16" alt="'.$hesklang['m_new'].'" title="'.$hesklang['m_new'].'" border="0" style="border:none;vertical-align:text-bottom" /> '.$hesklang['m_new'].'</b>';
	$_SESSION['hide']['list'] = 1;

    /* Do we have a recipient selected? */
    if (!isset($_SESSION['mail']['to']) && isset($_GET['id']))
    {
    	$_SESSION['mail']['to'] = intval( hesk_GET('id') );
    }
}
else
{
	$hesk_settings['mailtmp']['this']   = 'to';
	$hesk_settings['mailtmp']['other']  = 'from';
	$hesk_settings['mailtmp']['m_from'] = $hesklang['m_from'];
    if ($action != 'read')
    {
		$hesk_settings['mailtmp']['inbox']  = '<b><img src="../img/inbox.png" width="16" height="16" alt="'.$hesklang['inbox'].'" title="'.$hesklang['inbox'].'" border="0" style="border:none;vertical-align:text-bottom" /> '.$hesklang['inbox'].'</b>';
        $hesk_settings['mailtmp']['folder'] = '';
    }
}

/* What should we do? */
switch ($action)
{
	case 'send':
    	mail_send();
        break;
    case 'mark_read':
    	mail_mark_read();
        break;
    case 'mark_unread':
    	mail_mark_unread();
        break;
    case 'delete':
    	mail_delete();
        break;
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

<script language="javascript" type="text/javascript"><!--
function confirm_delete()
{
	if (confirm('<?php echo addslashes($hesklang['delete_saved']); ?>')) {return true;}
	else {return false;}
}
//-->
</script>

<h3 align="center"><?php echo $hesklang['m_h']; ?></h3>

<?php
/* Print sub-navigation */
echo $hesk_settings['mailtmp']['inbox'] . ' | ' . $hesk_settings['mailtmp']['outbox'] . ' | ' . $hesk_settings['mailtmp']['new'] . '<br /><br />&nbsp;';

/* This will handle error, success and notice messages */
hesk_handle_messages();

/* Show a message? */
if ($action == 'read')
{
	show_message();
}

/* Hide list of messages? */
if (!isset($_SESSION['hide']['list']))
{
	mail_list_messages();
} // END hide list of messages

/* Show new message form */
show_new_form();

/* Clean unneeded session variables */
hesk_cleanSessionVars('hide');
hesk_cleanSessionVars('mail');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function mail_delete()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
        	/* If both correspondents deleted the mail remove it from database, otherwise mark as deleted by this user */
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `deletedby`='".intval($_SESSION['id'])."' WHERE `id`='".intval($id)."' AND (`to`='".intval($_SESSION['id'])."' OR `from`='".intval($_SESSION['id'])."') AND `deletedby`=0");

            if (hesk_dbAffectedRows() != 1)
            {
		        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `id`='".intval($id)."' AND (`to`='".intval($_SESSION['id'])."' OR `from`='".intval($_SESSION['id'])."') AND `deletedby`!=0");
            }
        }

		hesk_process_messages($hesklang['smdl'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_unread()


function mail_mark_unread()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='0' WHERE `id`='".intval($id)."' AND `to`='".intval($_SESSION['id'])."'");
        }

		hesk_process_messages($hesklang['smmu'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_unread()


function mail_mark_read()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='1' WHERE `id`='".intval($id)."' AND `to`='".intval($_SESSION['id'])."'");
        }

		hesk_process_messages($hesklang['smmr'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_read()


function mail_get_ids()
{
	global $hesk_settings, $hesklang;

	// Mail id as a query parameter?
	if ( $id = hesk_GET('id', false) )
	{
		return array($id);
	}
	// Mail id as a post array?
	elseif ( isset($_POST['id']) && is_array($_POST['id']) )
	{
		return array_map('intval', $_POST['id']);
	}
	// No valid ID parameter
	else
	{
		hesk_process_messages($hesklang['nms'],'NOREDIRECT','NOTICE');
		return false;
	}
    
} // END mail_get_ids()


function mail_send()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$hesk_error_buffer = '';

	/* Recipient */
	$_SESSION['mail']['to'] = intval( hesk_POST('to') );

	/* Valid recipient? */
    if (empty($_SESSION['mail']['to']))
    {
		$hesk_error_buffer .= '<li>' . $hesklang['m_rec'] . '</li>';
    }
	elseif ($_SESSION['mail']['to'] == $_SESSION['id'])
	{
		$hesk_error_buffer .= '<li>' . $hesklang['m_inr'] . '</li>';
	}
	else
	{
		$res = hesk_dbQuery("SELECT `name`,`email`,`notify_pm` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($_SESSION['mail']['to'])."' LIMIT 1");
		$num = hesk_dbNumRows($res);
		if (!$num)
		{
			$hesk_error_buffer .= '<li>' . $hesklang['m_inr'] . '</li>';
		}
        else
        {
        	$pm_recipient = hesk_dbFetchAssoc($res);
        }
	}

	/* Subject */
	$_SESSION['mail']['subject'] = hesk_input( hesk_POST('subject') ) or $hesk_error_buffer .= '<li>' . $hesklang['m_esu'] . '</li>';

	/* Message */
	$_SESSION['mail']['message'] = hesk_input( hesk_POST('message') ) or $hesk_error_buffer .= '<li>' . $hesklang['enter_message'] . '</li>';

	// Attach signature to the message?
	if ( ! empty($_POST['signature']))
	{
		$_SESSION['mail']['message'] .= "\n\n" . addslashes($_SESSION['signature']) . "\n";
	}

	/* Any errors? */
	if (strlen($hesk_error_buffer))
	{
    	$_SESSION['hide']['list'] = 1;
		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
	}
    else
    {
		$_SESSION['mail']['message'] = hesk_makeURL($_SESSION['mail']['message']);
		$_SESSION['mail']['message'] = nl2br($_SESSION['mail']['message']);
        
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` (`from`,`to`,`subject`,`message`,`dt`,`read`) VALUES ('".intval($_SESSION['id'])."','".intval($_SESSION['mail']['to'])."','".hesk_dbEscape($_SESSION['mail']['subject'])."','".hesk_dbEscape($_SESSION['mail']['message'])."',NOW(),'0')");

        /* Notify receiver via e-mail? */
        if (isset($pm_recipient) && $pm_recipient['notify_pm'])
        {
            $pm_id = hesk_dbInsertID();

            $pm = array(
				'name'		=> hesk_msgToPlain( addslashes($_SESSION['name']) ,1,1),
				'subject'	=> hesk_msgToPlain($_SESSION['mail']['subject'],1,1),
				'message'	=> hesk_msgToPlain($_SESSION['mail']['message'],1,1),
				'id'		=> $pm_id,
            );

			/* Format email subject and message for recipient */
			$subject = hesk_getEmailSubject('new_pm',$pm,0);
			$message = hesk_getEmailMessage('new_pm',$pm,1,0);

			/* Send e-mail */
			hesk_mail($pm_recipient['email'], $subject, $message);
        }

		unset($_SESSION['mail']);

		hesk_process_messages($hesklang['m_pms'],'./mail.php','SUCCESS');
    }
} // END mail_send()


function show_message()
{
	global $hesk_settings, $hesklang, $admins;

		$id = intval( hesk_GET('id') );

		/* Get the message details */
		$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `id`='".intval($id)."' AND `deletedby`!='".intval($_SESSION['id'])."' LIMIT 1");
		$num = hesk_dbNumRows($res);

	    if ($num)
	    {
	    	$pm = hesk_dbFetchAssoc($res);

	        /* Allowed to read the message? */
	        if ($pm['to'] == $_SESSION['id'])
	        {

			    if (!isset($_SESSION['mail']['subject']))
			    {
			    	$_SESSION['mail']['subject'] = $hesklang['m_re'] . ' ' . $pm['subject'];
			    }

			    if (!isset($_SESSION['mail']['to']))
			    {
			    	$_SESSION['mail']['to'] = $pm['from'];
			    }

	        }
	        elseif ($pm['from'] == $_SESSION['id'])
	        {

			    if (!isset($_SESSION['mail']['subject']))
			    {
			    	$_SESSION['mail']['subject'] = $hesklang['m_fwd'] . ' ' . $pm['subject'];
			    }

			    if (!isset($_SESSION['mail']['to']))
			    {
			    	$_SESSION['mail']['to'] = $pm['to'];
			    }

				$hesk_settings['mailtmp']['this']   = 'from';
				$hesk_settings['mailtmp']['other']  = 'to';
				$hesk_settings['mailtmp']['m_from'] = $hesklang['m_to'];
				$hesk_settings['mailtmp']['outbox'] = '<b>'.$hesklang['outbox'].'</b>';
				$hesk_settings['mailtmp']['inbox']  = '<a href="mail.php">'.$hesklang['inbox'].'</a>';
				$hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox">'.$hesklang['outbox'].'</a>';

	        }
	        else
	        {
	        	hesk_process_message($hesklang['m_ena'],'mail.php');
	        }

	        /* Mark as read */
	        if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read'])
	        {
				$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='1' WHERE `id`='".intval($id)."'");
	        }

	        $pm['name'] = isset($admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id='.$pm[$hesk_settings['mailtmp']['other']].'">'.$admins[$pm[$hesk_settings['mailtmp']['other']]].'</a>' : (($pm['from'] == 9999) ? '<a href="https://www.hesk.com" target="_blank">HESK.com</a>' : $hesklang['e_udel']);
	        $pm['dt'] = hesk_dateToString($pm['dt'],0,1,0,true);
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

			<table border="0" cellspacing="0" cellpadding="0" width="100%">
			<tr>
			<td valign="top">
				<table border="0">
				<tr>
					<td><b><?php echo $hesk_settings['mailtmp']['m_from']; ?></b></td>
					<td><?php echo $pm['name']; ?></td>
				</tr>
				<tr>
					<td><b><?php echo $hesklang['date']; ?></b></td>
					<td><?php echo $pm['dt']; ?></td>
				</tr>
				<tr>
					<td><b><?php echo $hesklang['m_sub']; ?></b></td>
					<td><?php echo $pm['subject']; ?></td>
				</tr>
				</table>
			</td>
			<td style="text-align:right; vertical-align:top;">

				<?php
				$folder = '&amp;folder=outbox';
				if ($pm['to'] == $_SESSION['id'])
				{
					echo '<a name= "MAU '.$pm['subject'].'" href="mail.php?a=mark_unread&amp;id='.$id.'&amp;token='.hesk_token_echo(0).'"><img src="../img/mail.png" width="16" height="16" alt="'.$hesklang['mau'].'" title="'.$hesklang['mau'].'" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a> ';
					$folder = '';
				}
				echo '<a name="Delete '.$pm['subject'].'" href="mail.php?a=delete&amp;id='.$id.'&amp;token='.hesk_token_echo(0).$folder.'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['delm']).'?\');"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['delm'].'" title="'.$hesklang['delm'].'" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a>';
				?>

			</td>
			</tr>
			</table>

		<hr />

		<p><?php echo $pm['message']; ?></p>

	    </td>
		<td class="roundcornersright">&nbsp;</td>
		</tr>
		<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
		</tr>
	</table>

	<br /><hr />


			<?php
	    } // END if $num

		$_SESSION['hide']['list'] = 1;

} // END show_message()


function mail_list_messages()
{
	global $hesk_settings, $hesklang, $admins;

    $href = 'mail.php';
    $query = '';
    if ($hesk_settings['mailtmp']['folder'] == 'outbox')
    {
    	$query .= 'folder=outbox&amp;';
    }
    $query .= 'page=';

	$maxresults = 30;

	$tmp  = intval( hesk_GET('page', 1) );
	$page = ($tmp > 1) ? $tmp : 1;

	/* List of private messages */
	$res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `".hesk_dbEscape($hesk_settings['mailtmp']['this'])."`='".intval($_SESSION['id'])."' AND `deletedby`!='".intval($_SESSION['id'])."'");
	$total = hesk_dbResult($res,0,0);

    if ($total > 0)
	{

		$pages = ceil($total/$maxresults) or $pages = 1;
		if ($page > $pages)
		{
			$page = $pages;
		}
		$limit_down = ($page * $maxresults) - $maxresults;

		$prev_page = ($page - 1 <= 0) ? 0 : $page - 1;
		$next_page = ($page + 1 > $pages) ? 0 : $page + 1;

		if ($pages > 1)
		{
			echo $hesklang['pg'] . ': ';

			/* List pages */
			if ($pages >= 7)
			{
				if ($page > 2)
				{
					echo '<a href="'.$href.'?'.$query.'1"><b>&laquo;</b></a> &nbsp; ';
				}

				if ($prev_page)
				{
					echo '<a href="'.$href.'?'.$query.$prev_page.'"><b>&lsaquo;</b></a> &nbsp; ';
				}
			}

			for ($i=1; $i<=$pages; $i++)
			{
				if ($i <= ($page+5) && $i >= ($page-5))
				{
					if ($i == $page)
					{
						echo ' <b>'.$i.'</b> ';
					}
					else
					{
						echo ' <a href="'.$href.'?'.$query.$i.'">'.$i.'</a> ';
					}
				}
			}

			if ($pages >= 7)
			{
				if ($next_page)
				{
					echo ' &nbsp; <a href="'.$href.'?'.$query.$next_page.'"><b>&rsaquo;</b></a> ';
				}

				if ($page < ($pages - 1))
				{
					echo ' &nbsp; <a href="'.$href.'?'.$query.$pages.'"><b>&raquo;</b></a>';
				}
			}

            echo '<br />&nbsp;';

		} // end PAGES > 1

		// Get messages from the database
		$res = hesk_dbQuery("SELECT `id`, `from`, `to`, `subject`, `dt`, `read` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `".hesk_dbEscape($hesk_settings['mailtmp']['this'])."`='".intval($_SESSION['id'])."' AND `deletedby`!='".intval($_SESSION['id'])."' ORDER BY `id` DESC LIMIT ".intval($limit_down)." , ".intval($maxresults)." ");
		?>

		<form action="mail.php<?php if ($hesk_settings['mailtmp']['folder'] == 'outbox') {echo '?folder=outbox';} ?>" name="form1" method="post">

		<div align="center">
		<table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
		<tr>
		<th class="admin_white" style="width:1px"><input type="checkbox" name="checkall" value="2" onclick="hesk_changeAll(this)" /></th>
		<th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo $hesklang['m_sub']; ?></th>
		<th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo $hesk_settings['mailtmp']['m_from']; ?></th>
		<th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo $hesklang['date']; ?></th>
		</tr>

		<?php
		$i = 0;
		while ($pm=hesk_dbFetchAssoc($res))
		{
			if ($i) {$color="admin_gray"; $i=0;}
			else {$color="admin_white"; $i=1;}

			$pm['subject'] = '<a href="mail.php?a=read&amp;id='.$pm['id'].'">'.$pm['subject'].'</a>';
		    if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read'])
		    {
		    	$pm['subject'] = '<b>'.$pm['subject'].'</b>';
		    }
			$pm['name'] = isset($admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id='.$pm[$hesk_settings['mailtmp']['other']].'">'.$admins[$pm[$hesk_settings['mailtmp']['other']]].'</a>' : (($pm['from'] == 9999) ? '<a href="https://www.hesk.com" target="_blank">HESK.com</a>' : $hesklang['e_udel']);
		    $pm['dt'] = hesk_dateToString($pm['dt'],0,0,0,true);

			echo <<<EOC
			<tr>
			<td class="$color" style="text-align:left; white-space:nowrap;"><input type="checkbox" id="$pm[id]" name="id[]" value="$pm[id]" />&nbsp;</td>
			<td class="$color">$pm[subject]</td>
			<td class="$color">$pm[name]</td>
			<td class="$color">$pm[dt]</td>
			</tr>

EOC;
		} // End while
		?>
		</table>
		</div>

		<p align="center"><select name="a">
		<?php
		if ($hesk_settings['mailtmp']['this'] == 'to')
		{
			?>
            <option value="mark_read" selected="selected"><?php echo $hesklang['mo1']; ?></option>
			<option value="mark_unread"><?php echo $hesklang['mo2']; ?></option>
			<?php
		}
		?>
		<option value="delete"><?php echo $hesklang['mo3']; ?></option>
		</select>
		<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
		<input type="submit" value="<?php echo $hesklang['execute']; ?>" onclick="Javascript:if (document.form1.a.value=='delete') return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['mo3']); ?>?');" class="orangebutton"  onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /></p>

		</form>

		<p>&nbsp;</p>
	    <?php

	} // END if total > 0
    else
    {
    	echo '<i>' . $hesklang['npm'] . '</i> <p>&nbsp;</p>';
    }

} // END mail_list_messages()


function show_new_form()
{
	global $hesk_settings, $hesklang, $admins;
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

		<form action="mail.php" method="post" name="form2">
		<h3 align="center"><?php echo $hesklang['new_mail']; ?></h3>

		<div align="center">
		<table border="0">
		<tr>
		<td>

	    	<table border="0">
	        <tr>
		        <td><b><?php echo $hesklang['m_to']; ?></b></td>
		        <td>
					<select name="to">
					<option value="" selected="selected"><?php echo $hesklang['select']; ?></option>
					<?php
					foreach ($admins as $k=>$v)
					{
						if ($k != $_SESSION['id'])
						{
							if (isset($_SESSION['mail']) && $k == $_SESSION['mail']['to'])
							{
								echo '<option value="'.$k.'" selected="selected">'.$v.'</option>';
							}
							else
							{
								echo '<option value="'.$k.'">'.$v.'</option>';
							}
						}
					}
					?>
					</select>
		        </td>
	        </tr>
	        <tr>
		        <td><b><?php echo $hesklang['m_sub']; ?></b></td>
		        <td>
				<input type="text" name="subject" size="40" maxlength="50"
				<?php
				if (isset($_SESSION['mail']['subject']))
				{
					echo ' value="'.stripslashes($_SESSION['mail']['subject']).'" ';
				}
				?>
				/>
	            </td>
	        </tr>
	        </table>

		<p><b><?php echo $hesklang['message']; ?>:</b><br />
		<textarea name="message" rows="15" cols="70"><?php
	    if (isset($_SESSION['mail']['message']))
	    {
	    	echo stripslashes($_SESSION['mail']['message']);
	    }
	    ?></textarea>
		</p>

		</td>
		</tr>
		</table>

		<label><input type="checkbox" name="signature" value="1" checked="checked" /> <?php echo $hesklang['attach_sign']; ?></label> (<a href="profile.php"><?php echo $hesklang['profile_settings']; ?></a>)<br />&nbsp;<br />

		</div>

		<p align="center">
	    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
	    <input type="hidden" name="a" value="send" />
	    <input type="submit" value="<?php echo $hesklang['m_send']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
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
    <?php
} // END show_new_form()
?>
