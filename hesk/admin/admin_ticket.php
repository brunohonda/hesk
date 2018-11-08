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
hesk_checkPermission('can_view_tickets');
$can_del_notes		 = hesk_checkPermission('can_del_notes',0);
$can_reply			 = hesk_checkPermission('can_reply_tickets',0);
$can_delete			 = hesk_checkPermission('can_del_tickets',0);
$can_edit			 = hesk_checkPermission('can_edit_tickets',0);
$can_archive		 = hesk_checkPermission('can_add_archive',0);
$can_assign_self	 = hesk_checkPermission('can_assign_self',0);
$can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
$can_change_cat		 = hesk_checkPermission('can_change_cat',0);
$can_change_own_cat  = hesk_checkPermission('can_change_own_cat',0);
$can_ban_emails		 = hesk_checkPermission('can_ban_emails', 0);
$can_unban_emails	 = hesk_checkPermission('can_unban_emails', 0);
$can_ban_ips		 = hesk_checkPermission('can_ban_ips', 0);
$can_unban_ips		 = hesk_checkPermission('can_unban_ips', 0);
$can_resolve		 = hesk_checkPermission('can_resolve', 0);
$can_view_ass_by     = hesk_checkPermission('can_view_ass_by', 0);
$can_privacy		 = hesk_checkPermission('can_privacy',0);
$can_export          = hesk_checkPermission('can_export',0);

// Get ticket ID
$trackingID = hesk_cleanID() or print_form();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

$_SERVER['PHP_SELF'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);

/* We will need timer function */
define('TIMER',1);

/* Get ticket info */
$res = hesk_dbQuery("SELECT `t1`.* , `t2`.name AS `repliername` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t1` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `t2` ON `t1`.`replierid` = `t2`.`id` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");

/* Ticket found? */
if (hesk_dbNumRows($res) != 1)
{
	/* Ticket not found, perhaps it was merged with another ticket? */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `merged` LIKE '%#".hesk_dbEscape($trackingID)."#%' LIMIT 1");

	if (hesk_dbNumRows($res) == 1)
	{
    	/* OK, found in a merged ticket. Get info */
     	$ticket = hesk_dbFetchAssoc($res);
        hesk_process_messages( sprintf($hesklang['tme'], $trackingID, $ticket['trackid']) ,'NOREDIRECT','NOTICE');
        $trackingID = $ticket['trackid'];
	}
    else
    {
    	/* Nothing found, error out */
	    hesk_process_messages($hesklang['ticket_not_found'],'NOREDIRECT');
	    print_form();
    }
}
else
{
	/* We have a match, get ticket info */
	$ticket = hesk_dbFetchAssoc($res);
}

/* Permission to view this ticket? */
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! hesk_checkPermission('can_view_ass_others',0))
{
    // Maybe this user is allowed to view tickets he/she assigned?
    if ( ! $can_view_ass_by || $ticket['assignedby'] != $_SESSION['id'])
    {
        hesk_error($hesklang['ycvtao']);
    }
}

if (!$ticket['owner'] && ! $can_view_unassigned)
{
	hesk_error($hesklang['ycovtay']);
}

/* Set last replier name */
if ($ticket['lastreplier'])
{
	if (empty($ticket['repliername']))
	{
		$ticket['repliername'] = $hesklang['staff'];
	}
}
else
{
	$ticket['repliername'] = $ticket['name'];
}

/* Get category name and ID */
$result = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($ticket['category'])."' LIMIT 1");

/* If this category has been deleted use the default category with ID 1 */
if (hesk_dbNumRows($result) != 1)
{
	$result = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='1' LIMIT 1");
}

$category = hesk_dbFetchAssoc($result);

/* Is this user allowed to view tickets inside this category? */
hesk_okCategory($category['id']);

/* Delete post action */
if (isset($_GET['delete_post']) && $can_delete && hesk_token_check())
{
	$n = intval( hesk_GET('delete_post') );
    if ($n)
    {
		/* Get last reply ID, we'll need it later */
		$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` DESC LIMIT 1");
        $last_reply_id = hesk_dbResult($res,0,0);

		// Was this post submitted by staff and does it have any attachments?
		$res = hesk_dbQuery("SELECT `dt`, `staffid`, `attachments` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`='".intval($n)."' AND `replyto`='".intval($ticket['id'])."' LIMIT 1");
		$reply = hesk_dbFetchAssoc($res);

		// If the reply was by a staff member update the appropriate columns
		if ( $reply['staffid'] )
		{
			// Is this the only staff reply? Delete "firstreply" and "firstreplyby" columns
			if ($ticket['staffreplies'] <= 1)
			{
				$staffreplies_sql = ' , `firstreply`=NULL, `firstreplyby`=NULL, `staffreplies`=0 ';
			}
			// Are we deleting the first staff reply? Update "firstreply" and "firstreplyby" columns
			elseif ($reply['dt'] == $ticket['firstreply'] && $reply['staffid'] == $ticket['firstreplyby'])
			{
				// Get the new first reply info
				$res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' AND `id`!='".intval($n)."' AND `staffid`!=0 ORDER BY `id` ASC LIMIT 1");

				// Did we find the new first reply?
				if ( hesk_dbNumRows($res) )
				{
					$firstreply = hesk_dbFetchAssoc($res);
					$staffreplies_sql = " , `firstreply`='".hesk_dbEscape($firstreply['dt'])."', `firstreplyby`='".hesk_dbEscape($firstreply['staffid'])."', `staffreplies`=`staffreplies`-1 ";
				}
				// The count must have been wrong, update it
				else
				{
					$staffreplies_sql = ' , `firstreply`=NULL, `firstreplyby`=NULL, `staffreplies`=0 ';
				}
			}
			// OK, this is not the first and not the only staff reply, just reduce number
			else
			{
            	$staffreplies_sql = ' , `staffreplies`=`staffreplies`-1 ';
			}
		}
		else
		{
			$staffreplies_sql = '';
		}

		/* Delete any attachments to this post */
		if ( strlen($reply['attachments']) )
		{
        	$hesk_settings['server_path'] = dirname(dirname(__FILE__));

			/* List of attachments */
			$att=explode(',',substr($reply['attachments'], 0, -1));
			foreach ($att as $myatt)
			{
				list($att_id, $att_name) = explode('#', $myatt);

				/* Delete attachment files */
				$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."' LIMIT 1");
				if (hesk_dbNumRows($res) && $file = hesk_dbFetchAssoc($res))
				{
					hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
				}

				/* Delete attachments info from the database */
				hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."'");
			}
		}

		/* Delete this reply */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`='".intval($n)."' AND `replyto`='".intval($ticket['id'])."'");

        /* Reply wasn't deleted */
        if (hesk_dbAffectedRows() != 1)
        {
			hesk_process_messages($hesklang['repl1'],$_SERVER['PHP_SELF']);
        }
        else
        {
			$closed_sql = '';

			/* Reply deleted. Need to update status and last replier? */
			$res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` DESC LIMIT 1");
			if (hesk_dbNumRows($res))
			{
				$replier_id = hesk_dbResult($res,0,1);
                $last_replier = $replier_id ? 1 : 0;

				/* Change status? */
                $status_sql = '';
				if ($last_reply_id == $n)
				{
					$status = $ticket['locked'] ? 3 : ($last_replier ? 2 : 1);
                    $status_sql = " , `status`='".intval($status)."' ";

					// Update closedat and closedby columns as required
					if ($status == 3)
					{
						$closed_sql = " , `closedat`=NOW(), `closedby`=".intval($_SESSION['id'])." ";
					}
				}

				hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `lastreplier`='{$last_replier}', `replierid`='".intval($replier_id)."', `replies`=`replies`-1 $status_sql $closed_sql $staffreplies_sql WHERE `id`='".intval($ticket['id'])."'");
			}
			else
			{
				// Update status, closedat and closedby columns as required
				if ($ticket['locked'])
				{
					$status = 3;
					$closed_sql = " , `closedat`=NOW(), `closedby`=".intval($_SESSION['id'])." ";
				}
				else
				{
                	$status = 0;
					$closed_sql = " , `closedat`=NULL, `closedby`=NULL ";
				}

				hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `lastreplier`='0', `status`='$status', `replies`=0 $staffreplies_sql WHERE `id`='".intval($ticket['id'])."'");
			}

			hesk_process_messages($hesklang['repl'],$_SERVER['PHP_SELF'],'SUCCESS');
        }
    }
    else
    {
    	hesk_process_messages($hesklang['repl0'],$_SERVER['PHP_SELF']);
    }
}

/* Delete notes action */
if (isset($_GET['delnote']) && hesk_token_check())
{
	$n = intval( hesk_GET('delnote') );
    if ($n)
    {
		// Get note info
		$res = hesk_dbQuery("SELECT `who`, `attachments` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `id`={$n}");

		if ( hesk_dbNumRows($res) )
		{
			$note = hesk_dbFetchAssoc($res);

			// Permission to delete note?
			if ($can_del_notes || $note['who'] == $_SESSION['id'])
			{
				// Delete note
				hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `id`='".intval($n)."'");

			    // Delete attachments
				if ( strlen($note['attachments']) )
				{
					$hesk_settings['server_path'] = dirname(dirname(__FILE__));

		            $attachments = array();

					$att=explode(',',substr($note['attachments'], 0, -1));
					foreach ($att as $myatt)
					{
						list($att_id, $att_name) = explode('#', $myatt);
						$attachments[] = intval($att_id);
					}

					if ( count($attachments) )
					{
						$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` IN (".implode(',', $attachments).") ");
						while ($file = hesk_dbFetchAssoc($res))
						{
							hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
						}
						hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` IN (".implode(',', $attachments).") ");
					}
				}
			}
		}
	}

    header('Location: admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    exit();
}

/* Add a note action */
if (isset($_POST['notemsg']) && hesk_token_check('POST'))
{
	// Error buffer
	$hesk_error_buffer = array();

	// Get message
	$msg = hesk_input( hesk_POST('notemsg') );

	// Get attachments
	if ($hesk_settings['attachments']['use'])
	{
		require(HESK_PATH . 'inc/posting_functions.inc.php');
		require(HESK_PATH . 'inc/attachments.inc.php');
		$attachments = array();
		for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
		{
			$att = hesk_uploadFile($i);
			if ($att !== false && !empty($att))
			{
				$attachments[$i] = $att;
			}
		}
	}
	$myattachments='';

	// We need message and/or attachments to accept note
	if ( count($attachments) || strlen($msg) || count($hesk_error_buffer) )
	{
		// Any errors?
		if ( count($hesk_error_buffer) != 0 )
		{
			$_SESSION['note_message'] = hesk_POST('notemsg');

			// Remove any successfully uploaded attachments
			if ($hesk_settings['attachments']['use'])
			{
				hesk_removeAttachments($attachments);
			}

			$tmp = '';
			foreach ($hesk_error_buffer as $error)
			{
				$tmp .= "<li>$error</li>\n";
			}
			$hesk_error_buffer = $tmp;

			$hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
			hesk_process_messages($hesk_error_buffer,'admin_ticket.php?track='.$ticket['trackid'].'&Refresh='.rand(10000,99999));
		}

		// Process attachments
		if ($hesk_settings['attachments']['use'] && ! empty($attachments) )
		{
			foreach ($attachments as $myatt)
			{
				hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`,`type`) VALUES ('".hesk_dbEscape($trackingID)."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."', '1')");
				$myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
			}
		}

		// Add note to database
		$msg = nl2br(hesk_makeURL($msg));
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`) VALUES ('".intval($ticket['id'])."','".intval($_SESSION['id'])."',NOW(),'".hesk_dbEscape($msg)."','".hesk_dbEscape($myattachments)."')");

        /* Notify assigned staff that a note has been added if needed */
        if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'])
        {
			$res = hesk_dbQuery("SELECT `email`, `notify_note` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($ticket['owner'])."' LIMIT 1");

			if (hesk_dbNumRows($res) == 1)
			{
				$owner = hesk_dbFetchAssoc($res);

				// 1. Generate the array with ticket info that can be used in emails
				$info = array(
				'email'			=> $ticket['email'],
				'category'		=> $ticket['category'],
				'priority'		=> $ticket['priority'],
				'owner'			=> $ticket['owner'],
				'trackid'		=> $ticket['trackid'],
				'status'		=> $ticket['status'],
				'name'			=> $_SESSION['name'],
				'subject'		=> $ticket['subject'],
				'message'		=> stripslashes($msg),
				'dt'			=> hesk_date($ticket['dt'], true),
				'lastchange'	=> hesk_date($ticket['lastchange'], true),
				'attachments'	=> $myattachments,
				'id'			=> $ticket['id'],
                'time_worked'   => $ticket['time_worked'],
                'last_reply_by' => $ticket['repliername'],
				);

				// 2. Add custom fields to the array
				foreach ($hesk_settings['custom_fields'] as $k => $v)
				{
					$info[$k] = $v['use'] ? $ticket[$k] : '';
				}

				// 3. Make sure all values are properly formatted for email
				$ticket = hesk_ticketToPlain($info, 1, 0);

				/* Get email functions */
				require(HESK_PATH . 'inc/email_functions.inc.php');

				/* Format email subject and message for staff */
				$subject = hesk_getEmailSubject('new_note',$ticket);
				$message = hesk_getEmailMessage('new_note',$ticket,1);

				/* Send email to staff */
				hesk_mail($owner['email'], $subject, $message);
			}
        }
	}

	header('Location: admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
	exit();
}

/* Update time worked */
if ($hesk_settings['time_worked'] && ($can_reply || $can_edit) && isset($_POST['h']) && isset($_POST['m']) && isset($_POST['s']) && hesk_token_check('POST'))
{
	$h = intval( hesk_POST('h') );
	$m = intval( hesk_POST('m') );
	$s = intval( hesk_POST('s') );

	/* Get time worked in proper format */
    $time_worked = hesk_getTime($h . ':' . $m . ':' . $s);

	/* Update database */
    $revision = sprintf($hesklang['thist14'],hesk_date(),$time_worked,$_SESSION['name'].' ('.$_SESSION['user'].')');
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `time_worked`='" . hesk_dbEscape($time_worked) . "', `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `trackid`='" . hesk_dbEscape($trackingID) . "'");

	/* Show ticket */
	hesk_process_messages($hesklang['twu'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

/* Delete attachment action */
if (isset($_GET['delatt']) && hesk_token_check())
{
	if ( ! $can_delete || ! $can_edit)
    {
		hesk_process_messages($hesklang['no_permission'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    }

	$att_id = intval( hesk_GET('delatt') ) or hesk_error($hesklang['inv_att_id']);

	$reply = intval( hesk_GET('reply', 0) );
	if ($reply < 1)
	{
		$reply = 0;
	}

	$note = intval( hesk_GET('note', 0) );
	if ($note < 1)
	{
		$note = 0;
	}

	/* Get attachment info */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."' LIMIT 1");
	if (hesk_dbNumRows($res) != 1)
	{
		hesk_process_messages($hesklang['id_not_valid'].' (att_id)','admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
	}
	$att = hesk_dbFetchAssoc($res);

	/* Is ticket ID valid for this attachment? */
	if ($att['ticket_id'] != $trackingID)
	{
		hesk_process_messages($hesklang['trackID_not_found'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
	}

	/* Delete file from server */
	hesk_unlink(HESK_PATH.$hesk_settings['attach_dir'].'/'.$att['saved_name']);

	/* Delete attachment from database */
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."'");

	/* Update ticket or reply in the database */
    $revision = sprintf($hesklang['thist12'],hesk_date(),$att['real_name'],$_SESSION['name'].' ('.$_SESSION['user'].')');
	if ($reply)
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",','') WHERE `id`='".intval($reply)."'");
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`='".intval($ticket['id'])."'");
	}
	elseif ($note)
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",','') WHERE `id`={$note}");
	}
	else
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",',''), `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`='".intval($ticket['id'])."'");
	}

	hesk_process_messages($hesklang['kb_att_rem'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* List of categories */
if ($can_change_cat)
{
    $result = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
}
else
{
    $result = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".hesk_myCategories('id')." ORDER BY `cat_order` ASC");
}
$categories_options='';
while ($row=hesk_dbFetchAssoc($result))
{
    if ($row['id'] == $ticket['category']) {continue;}
    $categories_options.='<option value="'.$row['id'].'">'.$row['name'].'</option>';
}

/* List of users */
$admins = array();
$result = hesk_dbQuery("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row=hesk_dbFetchAssoc($result))
{
	/* Is this an administrator? */
	if ($row['isadmin'])
    {
	    $admins[$row['id']]=$row['name'];
	    continue;
    }

	/* Not admin, is user allowed to view tickets? */
	if (strpos($row['heskprivileges'], 'can_view_tickets') !== false)
	{
		/* Is user allowed to access this category? */
		$cat=substr($row['categories'], 0);
		$row['categories']=explode(',',$cat);
		if (in_array($ticket['category'],$row['categories']))
		{
			$admins[$row['id']]=$row['name'];
			continue;
		}
	}
}

/* Get replies */
if ($ticket['replies'])
{
	$reply = '';
	$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
}
else
{
	$reply = false;
}

// Demo mode
if ( defined('HESK_DEMO') )
{
	$ticket['email'] = 'hidden@demo.com';
	$ticket['ip']	 = '127.0.0.1';
}

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

</td>
</tr>
<tr>
<td>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();

// Prepare special custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'] && hesk_is_custom_field_in_category($k, $ticket['category']) )
	{
		switch ($v['type'])
		{
			case 'date':
				$ticket[$k] = hesk_custom_date_display_format($ticket[$k], $v['value']['date_format']);
				break;
		}
	}
}

/* Do we need or have any canned responses? */
$can_options = hesk_printCanned();
?>

<h3 style="padding-bottom:5px"> &nbsp;

<?php
if ($ticket['archive'])
{
	echo '<img src="../img/tag.png" width="16" height="16" alt="'.$hesklang['archived'].'" title="'.$hesklang['archived'].'"  border="0" style="vertical-align:text-bottom" /> ';
}
if ($ticket['locked'])
{
    echo '<img src="../img/lock.png" width="16" height="16" alt="'.$hesklang['loc'].' - '.$hesklang['isloc'].'" title="'.$hesklang['loc'].' - '.$hesklang['isloc'].'" border="0" style="vertical-align:text-bottom" /> ';
}
echo $ticket['subject'];
?></h3>


<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
	<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornerstop"></td>
	<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
</tr>
<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>
    <!-- START TICKET HEAD -->

	<table border="0" cellspacing="1" cellpadding="1" width="100%">
	<?php

	$tmp = '';
	if ($hesk_settings['sequential'])
	{
    	$tmp = ' ('.$hesklang['seqid'].': '.$ticket['id'].')';
	}

	echo '

	<tr>
	<td>'.$hesklang['trackID'].': </td>
	<td id="trackid">'.$trackingID.' '.$tmp.'</td>
    <td style="text-align:right; padding-bottom:12px" rowspan="2">'.hesk_getAdminButtons().'</td>
	</tr>

	<tr>
	<td>'.$hesklang['created_on'].': </td>
	<td>'.hesk_date($ticket['dt'], true).'</td>
	</tr>

	<tr>
	<td>'.$hesklang['ticket_status'].': </td>
	<td>';

		$random=rand(10000,99999);

        if ($ticket['status'] == 3)
        {
            $status_action = ' [<a href="change_status.php?track='.$trackingID.'&amp;s=1&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">'.$hesklang['open_action'].'</a>] ';
        }
        elseif ($can_resolve)
        {
            $status_action = ' [<a href="change_status.php?track='.$trackingID.'&amp;s=3&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">'.$hesklang['close_action'].'</a>] ';
        }
        else
        {
            $status_action = '';
        }

        echo hesk_get_admin_ticket_status($ticket['status'], $status_action);

	echo '
    </td>
    <td style="text-align:right">
    	<form style="margin-bottom:0;" action="change_status.php" method="post">
    	<i>'.$hesklang['chngstatus'].'</i>

        <span style="white-space:nowrap;">
        <select name="s">
	    <option value="-1" selected="selected">'.$hesklang['select'].'</option>
        ' . hesk_get_status_select($ticket['status']) . '
        </select>

	    <input type="submit" id="chgstatus" value="'.$hesklang['go'].'" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" /><input type="hidden" name="track" value="'.$trackingID.'" />
        <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
        </span>

        </form>
    </td>
	</tr>

	<tr>
	<td>'.$hesklang['last_update'].': </td>
	<td>'.hesk_date($ticket['lastchange'], true).'</td>
    <td>&nbsp;</td>
	</tr>

	<tr>
	<td>'.$hesklang['category'].': </td>
    <td>'.$category['name'].'</td>
	<td style="text-align:right">
        ';

        if (strlen($categories_options) && ($can_change_cat || $can_change_own_cat))
        {
        echo '

    	<form style="margin-bottom:0;" action="move_category.php" method="post">
    	<i>'.$hesklang['move_to_catgory'].'</i>

        <span style="white-space:nowrap;">
        <select name="category">
	    <option value="-1" selected="selected">'.$hesklang['select'].'</option>
        '.$categories_options.'
        </select>

	    <input type="submit" id="chgcategory" value="'.$hesklang['go'].'" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" /><input type="hidden" name="track" value="'.$trackingID.'" />
        <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
        </span>

        </form>
        ';
        }

        echo '
	</td>
	</tr>

	<tr>
	<td>'.$hesklang['replies'].': </td>
	<td>'.$ticket['replies'].'</td>
    <td>&nbsp;</td>
	</tr>

	<tr>
	<td>'.$hesklang['priority'].': </td>
    <td>';

        $options = array(
        	0 => '<option value="0">'.$hesklang['critical'].'</option>',
        	1 => '<option value="1">'.$hesklang['high'].'</option>',
            2 => '<option value="2">'.$hesklang['medium'].'</option>',
            3 => '<option value="3">'.$hesklang['low'].'</option>'
        );

        switch ($ticket['priority'])
        {
        	case 0:
            	echo '<font class="critical">'.$hesklang['critical'].'</font>';
                unset($options[0]);
                break;
        	case 1:
            	echo '<font class="important">'.$hesklang['high'].'</font>';
                unset($options[1]);
                break;
        	case 2:
            	echo '<font class="medium">'.$hesklang['medium'].'</font>';
                unset($options[2]);
                break;
        	default:
            	echo $hesklang['low'];
                unset($options[3]);
        }

	echo '
    </td>
	<td style="text-align:right">
    	<form style="margin-bottom:0;" action="priority.php" method="post">
        <i>'.$hesklang['change_priority'].'</i>

        <span style="white-space:nowrap;">
        <select name="priority">
        <option value="-1" selected="selected">'.$hesklang['select'].'</option>
        ';
        echo implode('',$options);
        echo '
        </select>

        <input type="submit" id="chgpriority" value="'.$hesklang['go'].'" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" /><input type="hidden" name="track" value="'.$trackingID.'" />
        <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
        </span>

        </form>
    </td>
	</tr>

	<tr>
	<td>'.$hesklang['last_replier'].': </td>
	<td>'.$ticket['repliername'].'</td>
    <td>&nbsp;</td>
	</tr>
    ';
	?>

	<tr>
	<td><?php echo $hesklang['owner']; ?>: </td>
	<td>
        <?php
        echo isset($admins[$ticket['owner']]) ? '<b>'.$admins[$ticket['owner']].'</b>' :
        	 ($can_assign_self ? '<b>'.$hesklang['unas'].'</b>'.' [<a href="assign_owner.php?track='.$trackingID.'&amp;owner='.$_SESSION['id'].'&amp;token='.hesk_token_echo(0).'&amp;unassigned=1">'.$hesklang['asss'].'</a>]' : '<b>'.$hesklang['unas'].'</b>');
        ?>
	</td>
    <td style="text-align:right">
    	<form style="margin-bottom:0;" action="assign_owner.php" method="post">
		<?php
        if (hesk_checkPermission('can_assign_others',0))
        {
			?>
			<i><?php echo $hesklang['asst']; ?></i>

            <span style="white-space:nowrap;">
            <select name="owner">
			<option value="" selected="selected"><?php echo $hesklang['select']; ?></option>
			<?php
            if ($ticket['owner'])
            {
            	echo '<option value="-1"> &gt; '.$hesklang['unas'].' &lt; </option>';
            }

			foreach ($admins as $k=>$v)
			{
				if ($k != $ticket['owner'])
				{
					echo '<option value="'.$k.'">'.$v.'</option>';
				}
			}
			?>
			</select>
			<input type="submit" id="chgassignowner" value="<?php echo $hesklang['go']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
			<input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
			<input type="hidden" name="token" value="<?php echo hesk_token_echo(0); ?>" />
            <?php
            if ( ! $ticket['owner'])
            {
                echo '<input type="hidden" name="unassigned" value="1" />';
            }
            ?>
            </span>
			<?php
        }
        ?>
        </form>
    </td>
	</tr>
	<?php
	if ($hesk_settings['time_worked'])
	{
	?>
	<tr>
	<td valign="top"><?php echo $hesklang['ts']; ?>:</td>

    <?php
    if ($can_reply || $can_edit)
    {
    ?>
	<td><a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifytime')"><?php echo $ticket['time_worked']; ?></a>

        <?php $t = hesk_getHHMMSS($ticket['time_worked']); ?>

		<div id="modifytime" style="display:none">
			<br />

			<form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;">
			<table class="white">
			<tr>
				<td class="admin_gray"><?php echo $hesklang['hh']; ?>:</td>
				<td class="admin_gray"><input type="text" id="hours" name="h" value="<?php echo $t[0]; ?>" size="3" /></td>
			</tr>
			<tr>
				<td class="admin_gray"><?php echo $hesklang['mm']; ?>:</td>
				<td class="admin_gray"><input type="text" id="minutes" name="m" value="<?php echo $t[1]; ?>" size="3" /></td>
			</tr>
			<tr>
				<td class="admin_gray"><?php echo $hesklang['ss']; ?>:</td>
				<td class="admin_gray"><input type="text" id="seconds" name="s" value="<?php echo $t[2]; ?>" size="3" /></td>
			</tr>
			</table>

            <br />

			<input type="submit" value="<?php echo $hesklang['save']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
            |
            <a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifytime')"><?php echo $hesklang['cancel']; ?></a>
            <input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
			<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
			</form>
		</div>

    </td>
    <?php
    }
    else
    {
    	echo '<td>' . $ticket['time_worked'] . '</td>';
    }
    ?>
    <td>&nbsp;</td>
	</tr>
	<?php
	} // End if time_worked
	?>

	</table>

    <br />

	<table border="0" width="100%" cellspacing="0" cellpadding="2">
	<tr>
	<td><b><i><?php echo $hesklang['notes']; ?>:</i></b>

    <?php
    if ($can_reply)
    {
    ?>
    &nbsp; <a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('notesform')"><?php echo $hesklang['addnote']; ?></a>
    <?php
    }
    ?>
		<div id="notesform" style="display:<?php echo isset($_SESSION['note_message']) ? 'block' : 'none'; ?>">
	    <form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;" enctype="multipart/form-data">
	    <textarea name="notemsg" rows="6" cols="60"><?php echo isset($_SESSION['note_message']) ? stripslashes(hesk_input($_SESSION['note_message'])) : ''; ?></textarea>
		<?php
		// attachments
		if ($hesk_settings['attachments']['use'])
		{
			echo '<br />';
			for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
			{
				echo '<input type="file" name="attachment['.$i.']" size="50" /><br />';
			}
			echo '<br />';
		}
		?>

	    <input type="submit" value="<?php echo $hesklang['s']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /><input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
        <i><?php echo $hesklang['nhid']; ?></i>
        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
		<br />&nbsp;
        </form>
	    </div>
    </td>
	<td>&nbsp;</td>
	</tr>

	<?php
	$res = hesk_dbQuery("SELECT t1.*, t2.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` AS t1 LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='".intval($ticket['id'])."' ORDER BY t1.`id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
	while ($note = hesk_dbFetchAssoc($res))
	{
	?>
    <tr>
    <td>
		<table border="0" width="100%" cellspacing="0" cellpadding="3">
		<tr>
	    <td class="notes"><i><?php echo $hesklang['noteby']; ?> <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b></i> - <?php echo hesk_date($note['dt'], true); ?><br /><img src="../img/blank.gif" border="0" width="5" height="5" alt="" /><br />
		<?php
		// Message
		echo $note['message'];

		// Attachments
		if ( $hesk_settings['attachments']['use'] && strlen($note['attachments']) )
		{
        	echo strlen($note['message']) ? '<br /><br />' : '';

			$att = explode(',', substr($note['attachments'], 0, -1) );
			$num = count($att);
			foreach ($att as $myatt)
			{
				list($att_id, $att_name) = explode('#', $myatt);

				// Can edit and delete note (attachments)?
				if ($can_del_notes || $note['who'] == $_SESSION['id'])
				{
					// If this is the last attachment and no message, show "delete ticket" link
					if ($num == 1 && strlen($note['message']) == 0)
					{
						echo '<a href="admin_ticket.php?delnote='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['dela'].'" title="'.$hesklang['dela'].'" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a> ';
					}
					// Show "delete attachment" link
					else
					{
						echo '<a href="admin_ticket.php?delatt='.$att_id.'&amp;note='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['dela'].'" title="'.$hesklang['dela'].'" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a> ';
					}
				}

				echo '
				<a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'"><img src="../img/clip.png" width="16" height="16" alt="'.$hesklang['dnl'].' '.$att_name.'" title="'.$hesklang['dnl'].' '.$att_name.'" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a>
				<a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">'.$att_name.'</a><br />
				';
			}
		}
		?>
		</td>
	    </tr>
        </table>
    </td>
    <?php
    if ($can_del_notes || $note['who'] == $_SESSION['id'])
    {
	?>
		<td width="1" valign="top" style="white-space: nowrap;">
		<a href="edit_note.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;note=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>"><img
		src="../img/edit.png" alt="<?php echo $hesklang['ednote']; ?>" title="<?php echo $hesklang['ednote']; ?>" width="16" height="16" class="optionWhiteOFF" onmouseover="this.className='optionWhiteON'" onmouseout="this.className='optionWhiteOFF'" /></a>
		<a href="admin_ticket.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;delnote=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" onclick="return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['delnote']).'?'; ?>');"><img
		src="../img/delete.png" alt="<?php echo $hesklang['delnote']; ?>" title="<?php echo $hesklang['delnote']; ?>" width="16" height="16" class="optionWhiteOFF" onmouseover="this.className='optionWhiteON'" onmouseout="this.className='optionWhiteOFF'" /></a>
		</td>
	<?php
    }
    else
    {
    	echo '<td width="1" valign="top">&nbsp;</td>';
    }
	?>
    </tr>
    <?php
	}
    ?>

    </table>

    <!-- END TICKET HEAD -->
	</td>
	<td class="roundcornersright">&nbsp;</td>
</tr>
<tr>
	<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
</tr>
</table>

<br />

<?php
/* Reply form on top? */
if ($can_reply && $hesk_settings['reply_top'] == 1)
{
	hesk_printReplyForm();
    echo '<br />';
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
    <!-- START TICKET REPLIES -->

		<table border="0" cellspacing="1" cellpadding="1" width="100%">

        <?php
		if ($hesk_settings['new_top'])
        {
        	$i = hesk_printTicketReplies() ? 0 : 1;
        }
        else
        {
        	$i = 1;
        }

        /* Make sure original message is in correct color if newest are on top */
        $color = $i ? 'class="ticketalt"' : 'class="ticketrow"';
		?>

		<tr>
		<td <?php echo $color; ?>>

			<table border="0" cellspacing="0" cellpadding="0" width="100%">
			<tr>
			<td valign="top">

			    <table border="0" cellspacing="1">
			    <tr>
			    <td><?php echo $hesklang['date']; ?>:</td>
			    <td><?php echo hesk_date($ticket['dt'], true); ?></td>
			    </tr>
			    <tr>
			    <td><?php echo $hesklang['name']; ?>:</td>
			    <td><?php echo $ticket['name']; ?></td>
			    </tr>
                <?php
                if ($ticket['email'] != '')
                {
                ?>
			    <tr>
			    <td><?php echo $hesklang['email']; ?>:</td>
			    <td><?php
				if ($can_ban_emails)
				{
					if ( $email_id = hesk_isBannedEmail($ticket['email']) )
					{
						if ($can_unban_emails)
						{
							echo '<a href="banned_emails.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($email_id).'&amp;token='.hesk_token_echo(0).'"><img src="../img/banned.png" width="16" height="16" alt="'.$hesklang['eisban'].' '.$hesklang['click_unban'].'" title="'.$hesklang['eisban'].' '.$hesklang['click_unban'].'" /></a> ';
						}
						else
						{
                        	echo '<img src="../img/banned.png" width="16" height="16" alt="'.$hesklang['eisban'].'" title="'.$hesklang['eisban'].'" /> ';
						}
					}
					else
					{
						echo '<a href="banned_emails.php?a=ban&amp;track='.$trackingID.'&amp;email='.urlencode($ticket['email']).'&amp;token='.hesk_token_echo(0).'"><img src="../img/ban.png" width="16" height="16" alt="'.$hesklang['savebanemail'].'" title="'.$hesklang['savebanemail'].'" /></a> ';
					}
				}
				?><a href="mailto:<?php echo $ticket['email']; ?>"><?php echo $ticket['email']; ?></a></td>
			    </tr>
                <?php
                }
                ?>
			    <tr>
			    <td><?php echo $hesklang['ip']; ?>:</td>
			    <td><?php

				// Format IP for lookup
				if ($ticket['ip'] == '' || $ticket['ip'] == 'Unknown' || $ticket['ip'] == $hesklang['unknown'])
				{
					echo $hesklang['unknown'];
				}
				else
				{
					if ($can_ban_ips)
					{
						if ( $ip_id = hesk_isBannedIP($ticket['ip']) )
						{
							if ($can_unban_ips)
							{
								echo '<a href="banned_ips.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($ip_id).'&amp;token='.hesk_token_echo(0).'"><img src="../img/banned.png" width="16" height="16" alt="'.$hesklang['ipisban'].' '.$hesklang['click_unban'].'" title="'.$hesklang['ipisban'].' '.$hesklang['click_unban'].'" /></a> ';
							}
							else
							{
	                        	echo '<img src="../img/banned.png" width="16" height="16" alt="'.$hesklang['ipisban'].'" title="'.$hesklang['ipisban'].'" /> ';
							}
						}
						else
						{
							echo '<a href="banned_ips.php?a=ban&amp;track='.$trackingID.'&amp;ip='.urlencode($ticket['ip']).'&amp;token='.hesk_token_echo(0).'"><img src="../img/ban.png" width="16" height="16" alt="'.$hesklang['savebanip'].'" title="'.$hesklang['savebanip'].'" /></a> ';
						}
					}

					echo '<a href="../ip_whois.php?ip=' . urlencode($ticket['ip']) . '">' . $ticket['ip'] . '</a>';
				}
				?></td>
			    </tr>
			    </table>

			</td>
			<td style="text-align:right; vertical-align:top;">
            <?php echo hesk_getAdminButtons(0, $i); ?>
            </td>
			</tr>
			</table>

			<?php
			/* custom fields before message */
			$print_table = 0;
			$myclass = ' class="tickettd"';

			foreach ($hesk_settings['custom_fields'] as $k=>$v)
			{
				if ($v['use'] && $v['place']==0 && hesk_is_custom_field_in_category($k, $ticket['category']) )
			    {
			    	if ($print_table == 0)
			        {
			        	echo '<table border="0" cellspacing="1" cellpadding="2">';
			        	$print_table = 1;
			        }

					switch ($v['type'])
					{
						case 'email':
							$ticket[$k] = '<a href="mailto:'.$ticket[$k].'">'.$ticket[$k].'</a>';
							break;
					}

			        echo '
					<tr>
					<td valign="top" '.$myclass.'>'.$v['name:'].'</td>
					<td valign="top" '.$myclass.'>'.$ticket[$k].'</td>
					</tr>
			        ';
			    }
			}
			if ($print_table)
			{
				echo '</table>';
			}

            if ($ticket['message'] != '')
            {
			?>

			<p><b><?php echo $hesklang['message']; ?>:</b></p>
			<p><?php echo $ticket['message']; ?><br />&nbsp;</p>

			<?php
            }
            
			/* custom fields after message */
			$print_table = 0;

			foreach ($hesk_settings['custom_fields'] as $k=>$v)
			{
				if ($v['use'] && $v['place'] && hesk_is_custom_field_in_category($k, $ticket['category']) )
			    {
			    	if ($print_table == 0)
			        {
			        	echo '<table border="0" cellspacing="1" cellpadding="2">';
			        	$print_table = 1;
			        }

					switch ($v['type'])
					{
						case 'email':
							$ticket[$k] = '<a href="mailto:'.$ticket[$k].'">'.$ticket[$k].'</a>';
							break;
					}

			        echo '
					<tr>
					<td valign="top" '.$myclass.'>'.$v['name:'].'</td>
					<td valign="top" '.$myclass.'>'.$ticket[$k].'</td>
					</tr>
			        ';
			    }
			}
			if ($print_table)
			{
				echo '</table>';
			}

            /* Print attachments */
            hesk_listAttachments($ticket['attachments'], 0 , $i);

			// Show suggested KB articles
            if ($hesk_settings['kb_enable'] && $hesk_settings['kb_recommendanswers'] && strlen($ticket['articles']) )
			{
				$suggested = array();
				$suggested_list = '';

				// Get article info from the database
				$articles = hesk_dbQuery("SELECT `id`,`subject` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` WHERE `id` IN (".preg_replace('/[^0-9\,]/', '', $ticket['articles']).")");
				while ($article=hesk_dbFetchAssoc($articles))
				{
					$suggested[$article['id']] = '<a href="../knowledgebase.php?article='.$article['id'].'">'.$article['subject'].'</a><br />';
				}

				// Loop through the IDs to preserve the order they were suggested in
				$articles = explode(',', $ticket['articles']);
				foreach ($articles as $article)
				{
                	if ( isset($suggested[$article]) )
					{
                    	$suggested_list .= $suggested[$article];
					}
				}

				// Finally print suggested articles
				if ( strlen($suggested_list) )
				{
					$suggested_list = '<hr /><i>'.$hesklang['taws'].'</i><br />' . $suggested_list . '&nbsp;';
					echo $_SESSION['show_suggested'] ? $suggested_list : '<a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay(\'suggested_articles\')">'.$hesklang['sska'].'</a><span id="suggested_articles" style="display:none">'.$suggested_list.'</span>';
				}
			}
			?>

		</td>
		</tr>

        <?php
		if ( ! $hesk_settings['new_top'])
        {
        	hesk_printTicketReplies();
        }
		?>

        </table>

    <!-- END TICKET REPLIES -->
	</td>
	<td class="roundcornersright">&nbsp;</td>
</tr>
<tr>
	<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
</tr>
</table>

<br />

<?php
/* Reply form on bottom? */
if ($can_reply && ! $hesk_settings['reply_top'])
{
	hesk_printReplyForm();
}

/* Display ticket history */
if (strlen($ticket['history']))
{
?>

<p>&nbsp;</p>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>

    	<h3><?php echo $hesklang['thist']; ?></h3>

		<ul><?php echo $ticket['history']; ?></ul>

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
}

/* Clear unneeded session variables */
hesk_cleanSessionVars('ticket_message');
hesk_cleanSessionVars('time_worked');
hesk_cleanSessionVars('note_message');

require_once(HESK_PATH . 'inc/footer.inc.php');


/*** START FUNCTIONS ***/


function hesk_listAttachments($attachments='', $reply=0, $white=1)
{
	global $hesk_settings, $hesklang, $trackingID, $can_edit, $can_delete;

	/* Attachments disabled or not available */
	if ( ! $hesk_settings['attachments']['use'] || ! strlen($attachments) )
    {
    	return false;
    }

    /* Style and mousover/mousout */
    $tmp = $white ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';

	/* List attachments */
	echo '<p><b>'.$hesklang['attachments'].':</b><br />';
	$att=explode(',',substr($attachments, 0, -1));
	foreach ($att as $myatt)
	{
		list($att_id, $att_name) = explode('#', $myatt);

        /* Can edit and delete tickets? */
        if ($can_edit && $can_delete)
        {
        	echo '<a href="admin_ticket.php?delatt='.$att_id.'&amp;reply='.$reply.'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['dela'].'" title="'.$hesklang['dela'].'" '.$style.' /></a> ';
        }

		echo '
		<a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'"><img src="../img/clip.png" width="16" height="16" alt="'.$hesklang['dnl'].' '.$att_name.'" title="'.$hesklang['dnl'].' '.$att_name.'" '.$style.' /></a>
		<a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">'.$att_name.'</a><br />
        ';
	}
	echo '</p>';

    return true;
} // End hesk_listAttachments()


function hesk_getAdminButtons($reply=0,$white=1)
{
	global $hesk_settings, $hesklang, $ticket, $reply, $trackingID, $can_edit, $can_archive, $can_delete, $can_resolve, $can_privacy, $can_export;

	$buttons = array();

	// Print ticket button
    $buttons[] = '<a href="../print.php?track='.$trackingID.'" title="'.$hesklang['printer_friendly'].'"><img src="../img/print.png" width="16" height="16" alt="'.$hesklang['printer_friendly'].'" /> '.$hesklang['btn_print'].'</a>';

	// Edit
	if ($can_edit)
	{
    	$tmp = $reply ? '&amp;reply='.$reply['id'] : '';
		$buttons[] = '<a id="editticket" href="edit_post.php?track='.$trackingID.$tmp.'" title="'.$hesklang['edtt'].'"><img src="../img/edit.png" width="16" height="16" alt="'.$hesklang['edtt'].'" /> '.$hesklang['btn_edit'].'</a>';
	}

    // Lock ticket button
	if ( ! $reply && $can_resolve)
	{
		if ($ticket['locked'])
		{
			$des = $hesklang['tul'] . ' - ' . $hesklang['isloc'];
            $buttons['more'][] = '<a id="unlock" href="lock.php?track='.$trackingID.'&amp;locked=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$des.'"><img src="../img/unlock.png" width="16" height="16" alt="'.$des.'" /> '.$hesklang['btn_unlock'].'</a>';
		}
		else
		{
			$des = $hesklang['tlo'] . ' - ' . $hesklang['isloc'];
            $buttons['more'][] = '<a id="lock" href="lock.php?track='.$trackingID.'&amp;locked=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$des.'"><img src="../img/lock.png" width="16" height="16" alt="'.$des.'" /> '.$hesklang['btn_lock'].'</a>';
		}
	}

	// Tag ticket button
	if ( ! $reply && $can_archive)
	{
		if ($ticket['archive'])
		{
        	$buttons['more'][] = '<a id="untag" href="archive.php?track='.$trackingID.'&amp;archived=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['remove_archive'].'"><img src="../img/tag.png" width="16" height="16" alt="'.$hesklang['remove_archive'].'" /> '.$hesklang['btn_untag'].'</a>';
		}
		else
		{
        	$buttons['more'][] = '<a id="tag" href="archive.php?track='.$trackingID.'&amp;archived=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['add_archive'].'"><img src="../img/tag_off.png" width="16" height="16" alt="'.$hesklang['add_archive'].'" /> '.$hesklang['btn_tag'].'</a>';
		}
	}

	// Resend email notification button
	$buttons['more'][] = '<a id="resendemail" href="resend_notification.php?track='.$trackingID.'&amp;reply='.(isset($reply['id']) ? intval($reply['id']) : 0).'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['btn_resend'].'"><img src="../img/email.png" width="16" height="16" alt="'.$hesklang['btn_resend'].'" /> '.$hesklang['btn_resend'].'</a>';

	// Import to knowledgebase button
	if ( ! $reply && $hesk_settings['kb_enable'] && hesk_checkPermission('can_man_kb',0))
	{
		$buttons['more'][] = '<a id="addtoknow" href="manage_knowledgebase.php?a=import_article&amp;track='.$trackingID.'" title="'.$hesklang['import_kb'].'"><img src="../img/import_kb.png" width="16" height="16" alt="'.$hesklang['import_kb'].'" /> '.$hesklang['btn_import_kb'].'</a>';
	}

    // Export ticket
    if ( ! $reply && $can_export)
    {
        $buttons['more'][] = '<a id="exportticket" href="export_ticket.php?track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['btn_export'].'"><img src="../img/export.png" width="16" height="16" alt="'.$hesklang['btn_export'].'" /> '.$hesklang['btn_export'].'</a>';
    }

    // Anonymize ticket
    if ( ! $reply && $can_privacy)
    {
		$buttons['more'][] = '<a id="anonymizeticket" href="anonymize_ticket.php?track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['confirm_anony']).'?\\n\\n'.hesk_makeJsString($hesklang['privacy_anon_info']).'\');" title="'.$hesklang['confirm_anony'].'"><img src="../img/anonymize.png" width="16" height="16" alt="'.$hesklang['confirm_anony'].'" /> '.$hesklang['btn_anony'].'</a>';
    }

	// Delete ticket or reply
	if ($can_delete)
	{
		if ($reply)
		{
			$url = 'admin_ticket.php';
			$tmp = 'delete_post='.$reply['id'];
			$img = 'delete.png';
			$txt = $hesklang['btn_delr'];
		}
		else
		{
			$url = 'delete_tickets.php';
			$tmp = 'delete_ticket=1';
			$img = 'delete.png';
			$txt = $hesklang['btn_delt'];
		}
		$buttons['more'][] = '<a id="deleteticket" href="'.$url.'?track='.$trackingID.'&amp;'.$tmp.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($txt).'?\');" title="'.$txt.'"><img src="../img/'.$img.'" width="16" height="16" alt="'.$txt.'" /> '.$txt.'</a>';
	}

    // Format and return the HTML for buttons
    $button_code = '<ul id="hesk_nav">';

    foreach ($buttons as $button)
    {
        $button_code .= '<li>';

        if (is_array($button))
        {
            $button_code .= '<a href="#" title="'.$hesklang['moret'].'"><img src="../img/menu.png" width="16" height="16" alt="'.$hesklang['moret'].'" /> '.$hesklang['btn_more'].'</a>';
            $button_code .= '<ul>';

            foreach ($button as $sub_button)
            {
                $button_code .= '<li>'.$sub_button.'</li>';
            }

            $button_code .= '</ul>';
        }
        else
        {
            $button_code .= $button;
        }

        $button_code .= '</li>';
    }

    $button_code .= '</ul>';

    return $button_code;

} // END hesk_getAdminButtons()


function print_form()
{
	global $hesk_settings, $hesklang;
    global $trackingID;

	/* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	/* Print admin navigation */
	require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
	?>

	</td>
	</tr>
	<tr>
	<td>

    &nbsp;<br />

	<?php
	/* This will handle error, success and notice messages */
	hesk_handle_messages();
	?>

	<div align="center">
	<table border="0" cellspacing="0" cellpadding="0" width="50%">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>

	        <form action="admin_ticket.php" method="get">

	        <table width="100%" border="0" cellspacing="0" cellpadding="0">
	        <tr>
	                <td width="1"><img src="../img/existingticket.png" alt="" width="60" height="60" /></td>
	                <td>
	                <p><b><?php echo $hesklang['view_existing']; ?></a></b></p>
	                </td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td>&nbsp;</td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td>
	                <?php echo $hesklang['ticket_trackID']; ?>: <br /><input type="text" name="track" maxlength="20" size="35" value="<?php echo $trackingID; ?>" /><br />&nbsp;
	                </td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td><input type="submit" value="<?php echo $hesklang['view_ticket']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /><input type="hidden" name="Refresh" value="<?php echo rand(10000,99999); ?>"></td>
	        </tr>
	        </table>

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
	</div>

	<p>&nbsp;</p>
	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End print_form()


function hesk_printTicketReplies() {
	global $hesklang, $hesk_settings, $result, $reply;

	$i = $hesk_settings['new_top'] ? 0 : 1;

	if ($reply === false)
	{
		return $i;
	}

	while ($reply = hesk_dbFetchAssoc($result))
	{
		if ($i) {$color = 'class="ticketrow"'; $i=0;}
		else {$color = 'class="ticketalt"'; $i=1;}

		$reply['dt'] = hesk_date($reply['dt'], true);
		?>
		<tr>
			<td <?php echo $color; ?>>

				<table border="0" cellspacing="0" cellpadding="0" width="100%">
					<tr>
						<td valign="top">
							<table border="0" cellspacing="1">
								<tr>
									<td><?php echo $hesklang['date']; ?>:</td>
									<td><?php echo $reply['dt']; ?></td>
								</tr>
								<tr>
									<td><?php echo $hesklang['name']; ?>:</td>
									<td><?php echo $reply['name']; ?></td>
								</tr>
							</table>
						</td>
						<td style="text-align:right; vertical-align:top;">
							<?php echo hesk_getAdminButtons(1,$i); ?>
						</td>
					</tr>
				</table>

			<p><b><?php echo $hesklang['message']; ?>:</b></p>
			<p><?php echo $reply['message']; ?></p>

			<?php

            /* Attachments */
			hesk_listAttachments($reply['attachments'],$reply['id'],$i);

            /* Staff rating */
			if ($hesk_settings['rating'] && $reply['staffid'])
			{
				if ($reply['rating']==1)
				{
					echo '<p class="rate">'.$hesklang['rnh'].'</p>';
				}
				elseif ($reply['rating']==5)
				{
					echo '<p class="rate">'.$hesklang['rh'].'</p>';
				}
			}

            /* Show "unread reply" message? */
			if ($reply['staffid'] && ! $reply['read'])
            {
            	echo '<p class="rate">'.$hesklang['unread'].'</p>';
            }

			?>
	        </td>
        </tr>
        <?php
	}

    return $i;

} // End hesk_printTicketReplies()


function hesk_printReplyForm() {
	global $hesklang, $hesk_settings, $ticket, $admins, $can_options, $options, $can_assign_self, $can_resolve;

    // Force assigning a ticket before allowing to reply?
    if ($hesk_settings['require_owner'] && ! $ticket['owner'])
    {
        hesk_show_notice($hesklang['atbr'].($can_assign_self ? '<br /><br /><a href="assign_owner.php?track='.$ticket['trackid'].'&amp;owner='.$_SESSION['id'].'&amp;token='.hesk_token_echo(0).'&amp;unassigned=1">'.$hesklang['attm'].'</a>' : ''), $hesklang['owneed']);
        return '';
    }
?>
<!-- START REPLY FORM -->

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
	<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornerstop"></td>
	<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
</tr>
<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>

	<h3 align="center"><?php echo $hesklang['add_reply']; ?></h3>

	<form method="post" action="admin_reply_ticket.php" enctype="multipart/form-data" name="form1" onsubmit="javascript:force_stop();return true;">

    <br />

    <?php

    /* Ticket assigned to someone else? */
    if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && isset($admins[$ticket['owner']]) )
    {
    	hesk_show_notice($hesklang['nyt'] . ' ' . $admins[$ticket['owner']]);
    }

    /* Ticket locked? */
    if ($ticket['locked'])
    {
    	hesk_show_notice($hesklang['tislock']);
    }

	// Track time worked?
	if ($hesk_settings['time_worked'])
	{
    ?>

    <div align="center">
    <table class="white" style="min-width:600px;">
    <tr>
    	<td colspan="2">
	    &raquo; <?php echo $hesklang['ts']; ?>
		<input type="text" name="time_worked" id="time_worked" size="10" value="<?php echo ( isset($_SESSION['time_worked']) ? hesk_getTime($_SESSION['time_worked']) : '00:00:00'); ?>" />
		<input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="ss()" id="startb" value="<?php echo $hesklang['start']; ?>" />
		<input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="r()" value="<?php echo $hesklang['reset']; ?>" />
        <br />&nbsp;
        </td>
    </tr>
    </table>
    </div>

    <?php
	}

    /* Do we have any canned responses? */
    if (strlen($can_options))
    {
    ?>
    <div align="center">
    <table class="white" style="min-width:600px;">
    <tr>
    	<td class="admin_gray" colspan="2"><b>&raquo; <?php echo $hesklang['saved_replies']; ?></b></td>
    </tr>
    <tr>
    	<td class="admin_gray">
	    <label><input type="radio" name="mode" id="modeadd" value="1" checked="checked" /> <?php echo $hesklang['madd']; ?></label><br />
        <label><input type="radio" name="mode" id="moderep" value="0" /> <?php echo $hesklang['mrep']; ?></label>
        </td>
        <td class="admin_gray">
	    <?php echo $hesklang['select_saved']; ?>:<br />
	    <select name="saved_replies" onchange="setMessage(this.value)">
		<option value="0"> - <?php echo $hesklang['select_empty']; ?> - </option>
		<?php echo $can_options; ?>
		</select>
        </td>
    </tr>
    </table>
    </div>
    <?php
    }
    ?>

	<p align="center"><?php echo $hesklang['message']; ?>: <font class="important">*</font><br />
	<span id="HeskMsg"><textarea name="message" id="message" rows="12" cols="72"><?php

	// Do we have any message stored in session?
	if ( isset($_SESSION['ticket_message']) )
	{
		echo stripslashes( hesk_input( $_SESSION['ticket_message'] ) );
	}
	// Perhaps a message stored in reply drafts?
	else
	{
		$res = hesk_dbQuery("SELECT `message` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".intval($_SESSION['id'])." AND `ticket`=".intval($ticket['id'])." LIMIT 1");
		if (hesk_dbNumRows($res) == 1)
		{
			echo hesk_dbResult($res);
		}
	}

	?></textarea></span></p>

	<?php
	/* attachments */
	if ($hesk_settings['attachments']['use'])
    {
	?>
		<p align="center">
		<?php
		echo $hesklang['attachments'] . ' (<a href="Javascript:void(0)" onclick="Javascript:hesk_window(\'../file_limits.php\',250,500);return false;">' . $hesklang['ful'] . '</a>):<br />';
		for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
		{
			echo '<input type="file" name="attachment['.$i.']" size="50" /><br />';
		}
		?>
		</p>
	<?php
	}
	?>

	<div align="center">
	<center>
	<table>
	<tr>
	<td>
	<?php
    if ($ticket['owner'] != $_SESSION['id'] && $can_assign_self)
    {
		if (empty($ticket['owner']))
		{
			echo '<label><input type="checkbox" name="assign_self" value="1" checked="checked" /> <b>'.$hesklang['asss2'].'</b></label><br />';
		}
		else
		{
			echo '<label><input type="checkbox" name="assign_self" value="1" /> '.$hesklang['asss2'].'</label><br />';
		}
    }
	?>
	<label><input type="checkbox" name="set_priority" value="1" /> <?php echo $hesklang['change_priority']; ?> </label>
	<select id="replypriority" name="priority">
	<?php echo implode('',$options); ?>
	</select><br />
	<label><input type="checkbox" name="signature" value="1" checked="checked" /> <?php echo $hesklang['attach_sign']; ?></label>
	(<a href="profile.php"><?php echo $hesklang['profile_settings']; ?></a>)<br />
    <label><input type="checkbox" name="no_notify" value="1" <?php echo $_SESSION['notify_customer_reply'] ? '' : 'checked="checked"'; ?> /> <?php echo $hesklang['dsen']; ?></label>
	</td>
	</tr>
	</table>
	</center>
	</div>

	<p align="center">
    <input type="hidden" name="orig_id" value="<?php echo $ticket['id']; ?>" />
    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
    <input type="submit" value="    <?php echo $hesklang['submit_reply']; ?>    " class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
	&nbsp;
    <input type="submit" name="save_reply" value="<?php echo $hesklang['sacl']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
	</p>

	<?php
	// If ticket is not locked, show additional submit options
	if ( ! $ticket['locked'])
	{
		?>
		<p>&nbsp;</p>

		<p align="center">
		<input type="submit" name="submit_as_customer" value="<?php echo $hesklang['sasc']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />

        <?php
        if ($can_resolve)
        {
        ?>
		<input type="submit" name="submit_as_resolved" value="<?php echo $hesklang['submit_as'] . ' ' . $hesklang['closed']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
        <?php
        }
        ?>
		<input type="submit" name="submit_as_in_progress" value="<?php echo $hesklang['submit_as'] . ' ' . $hesklang['in_progress']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
		<input type="submit" name="submit_as_on_hold" value="<?php echo $hesklang['submit_as'] . ' ' . $hesklang['on_hold']; ?>" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
		</p>
		<?php
	}
	?>

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

<!-- END REPLY FORM -->
<?php
} // End hesk_printReplyForm()


function hesk_printCanned()
{
	global $hesklang, $hesk_settings, $can_reply, $ticket, $admins;

	/* Can user reply to tickets? */
	if ( ! $can_reply)
    {
    	return '';
    }

	/* Get canned replies from the database */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."std_replies` ORDER BY `reply_order` ASC");

	/* If no canned replies return empty */
    if ( ! hesk_dbNumRows($res) )
    {
    	return '';
    }

	/* We do have some replies, print the required Javascript and select field options */
	$can_options = '';
	?>
	<script language="javascript" type="text/javascript"><!--
    // -->
    var myMsgTxt = new Array();
	myMsgTxt[0]='';

	<?php
	while ($mysaved = hesk_dbFetchRow($res))
	{
	    $can_options .= '<option value="' . $mysaved[0] . '">' . $mysaved[1]. "</option>\n";
	    echo 'myMsgTxt['.$mysaved[0].']=\''.str_replace("\r\n","\\r\\n' + \r\n'", addslashes($mysaved[2]))."';\n";
	}

	?>

	function setMessage(msgid)
    {
		var myMsg=myMsgTxt[msgid];

        if (myMsg == '')
        {
        	if (document.form1.mode[1].checked)
            {
				document.getElementById('message').value = '';
            }
            return true;
        }

		myMsg = myMsg.replace(/%%HESK_ID%%/g, '<?php echo hesk_jsString($ticket['id']); ?>');
		myMsg = myMsg.replace(/%%HESK_TRACKID%%/g, '<?php echo hesk_jsString($ticket['trackid']); ?>');
		myMsg = myMsg.replace(/%%HESK_TRACK_ID%%/g, '<?php echo hesk_jsString($ticket['trackid']); ?>');
		myMsg = myMsg.replace(/%%HESK_NAME%%/g, '<?php echo hesk_jsString($ticket['name']); ?>');
        myMsg = myMsg.replace(/%%HESK_FIRST_NAME%%/g, '<?php echo hesk_jsString(hesk_full_name_to_first_name($ticket['name'])); ?>');
		myMsg = myMsg.replace(/%%HESK_EMAIL%%/g, '<?php echo hesk_jsString($ticket['email']); ?>');
		myMsg = myMsg.replace(/%%HESK_OWNER%%/g, '<?php echo hesk_jsString( isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : ''); ?>');

		<?php
        for ($i=1; $i<=50; $i++)
		{
        	echo 'myMsg = myMsg.replace(/%%HESK_custom'.$i.'%%/g, \''.hesk_jsString($ticket['custom'.$i]).'\');';
		}
		?>

	    if (document.getElementById)
        {
			if (document.getElementById('moderep').checked)
            {
				document.getElementById('HeskMsg').innerHTML='<textarea name="message" id="message" rows="12" cols="72">'+myMsg+'</textarea>';
            }
            else
            {
            	var oldMsg = document.getElementById('message').value;
		        document.getElementById('HeskMsg').innerHTML='<textarea name="message" id="message" rows="12" cols="72">'+oldMsg+myMsg+'</textarea>';
            }
	    }
        else
        {
			if (document.form1.mode[0].checked)
            {
				document.form1.message.value=myMsg;
            }
            else
            {
            	var oldMsg = document.form1.message.value;
		        document.form1.message.value=oldMsg+myMsg;
            }
	    }

	}
	//-->
	</script>
    <?php

    /* Return options for select box */
    return $can_options;

} // End hesk_printCanned()
?>
