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
define('CALENDAR',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
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
$can_assign_others   = hesk_checkPermission('can_assign_others',0);
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
$can_due_date        = hesk_checkPermission('can_due_date',0);
$can_man_customers   = hesk_checkPermission('can_man_customers',0);
$can_link_tickets    = hesk_checkPermission('can_link_tickets',0);

// Get ticket ID
$trackingID = hesk_cleanID() or print_form();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

$_SERVER['PHP_SELF'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);

// We will need some extra functions
if ($hesk_settings['time_worked']) {
    define('TIMER',1);
}
define('BACK2TOP',1);
define('ATTACHMENTS',1);
if ($hesk_settings['time_display']) {
    define('TIMEAGO',1);
}
if ($hesk_settings['staff_ticket_formatting'] == 2) {
    define('WYSIWYG',1);
    define('STYLE_CODE',1);
}

/* Get ticket info */
$res = hesk_dbQuery("SELECT `t1`.* , `t2`.name AS `repliername` 
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t1` 
    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `t2` 
        ON `t1`.`replierid` = `t2`.`id` 
    WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");


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
$ticket['is_bookmark'] = hesk_isTicketBookmarked($ticket['id'], $_SESSION['id']);
$ticket['collaborators'] = hesk_getTicketsCollaboratorIDs($ticket['id']);
$ticket['am_I_collaborator'] = in_array($_SESSION['id'], $ticket['collaborators']);
$customers = hesk_get_customers_for_ticket($ticket['id']);
$found_requester = false;
$requester = [];
$followers = [];
foreach ($customers as $customer) {
    if ($customer['customer_type'] === 'REQUESTER') {
        $found_requester = true;
        $requester = $customer;
    } elseif ($customer['customer_type'] === 'FOLLOWER') {
        $followers[] = $customer;
    }
}
if (!$found_requester) {
    $requester = [
        'name' => $hesklang['anon_name'],
        'email' => $hesklang['anon_email']
    ];
}
// TODO REMOVE
$customer_emails = '';
foreach ($customers as $customer) {
    $customer_emails = $customer_emails === '' ? $customer['email'] : $customer_emails.';'.$customer['email'];
}

// Has this ticket been anonymized?
$ticket['anonymized'] = empty($customers) &&
    $ticket['subject'] == $hesklang['anon_subject'] &&
    $ticket['message'] == $hesklang['anon_message'] &&
    $ticket['message_html'] == $hesklang['anon_message'] &&
    $ticket['ip'] == $hesklang['anon_IP'];

/* Permission to view this ticket? */
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! hesk_checkPermission('can_view_ass_others',0) && ! $ticket['am_I_collaborator'])
{
    // Maybe this user is allowed to view tickets he/she assigned or is collaborator?
    if ( ! $can_view_ass_by || $ticket['assignedby'] != $_SESSION['id'])
    {
        hesk_error($hesklang['ycvtao']);
    }
}

if (!$ticket['owner'] && ! $can_view_unassigned && ! $ticket['am_I_collaborator'])
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
    $ticket['repliername'] = $hesklang['anon_name'];

    if ($ticket['replies'] > 0) {
        $replier_name_rs = hesk_dbQuery("SELECT `name` 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `id` = (
            SELECT `customer_id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies`
            WHERE `replyto` = ".intval($ticket['id'])."
            ORDER BY `id` DESC
            LIMIT 1
        )");
        if ($row = hesk_dbFetchAssoc($replier_name_rs)) {
            $ticket['repliername'] = $row['name'];
        }
    } else {
        $requester_name_rs = hesk_dbQuery("SELECT `name` 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `id` = (
            SELECT `customer_id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
            WHERE `ticket_id` = ".intval($ticket['id'])."
                AND `customer_type` = 'REQUESTER'
            LIMIT 1
        )");
        if ($row = hesk_dbFetchAssoc($requester_name_rs)) {
            $ticket['repliername'] = $row['name'];
        }
    }

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

				hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `lastreplier`='0', `replierid`=NULL, `status`='$status', `replies`=0 $staffreplies_sql WHERE `id`='".intval($ticket['id'])."'");
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
    $use_legacy_attachments = hesk_POST('use-legacy-attachments', 0);
	if ($hesk_settings['attachments']['use'])
	{
		require(HESK_PATH . 'inc/posting_functions.inc.php');
		require(HESK_PATH . 'inc/attachments.inc.php');
		$attachments = array();

        if ($use_legacy_attachments) {
            for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                $att = hesk_uploadFile($i);
                if ($att !== false && !empty($att)) {
                    $attachments[$i] = $att;
                }
            }
        } else {
            // The user used the new drag-and-drop system.
            $temp_attachment_names = hesk_POST_array('attachments');
            foreach ($temp_attachment_names as $temp_attachment_name) {
                $temp_attachment = hesk_getTemporaryAttachment($temp_attachment_name);

                if ($temp_attachment !== null) {
                    $attachments[] = $temp_attachment;
                }
            }
        }
	}
	$myattachments='';

	// We need message and/or attachments to accept note
	if ( (!empty($attachments) && count($attachments)) || strlen($msg) || count($hesk_error_buffer) )
	{
		// Any errors?
		if ( count($hesk_error_buffer) != 0 )
		{
			$_SESSION['note_message'] = hesk_POST('notemsg');

			// Remove any successfully uploaded attachments
			if ($hesk_settings['attachments']['use'])
			{
                if ($use_legacy_attachments) {
                    hesk_removeAttachments($attachments);
                } else {
                    $_SESSION['note_attachments'] = $attachments;
                }
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
            if (!$use_legacy_attachments) {
                $attachments = hesk_migrateTempAttachments($attachments, $trackingID);
            }

			foreach ($attachments as $myatt)
			{
				hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`,`type`) VALUES ('".hesk_dbEscape($trackingID)."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."', '1')");
				$myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
			}
		}

		// Add note to database
		$msg = nl2br(hesk_makeURL($msg));
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`) VALUES ('".intval($ticket['id'])."','".intval($_SESSION['id'])."',NOW(),'".hesk_dbEscape($msg)."','".hesk_dbEscape($myattachments)."')");

        // Update time worked
        if ($hesk_settings['time_worked'] && ($time_worked = hesk_getTime(hesk_POST('time_worked_notes'))) && $time_worked != '00:00:00')
        {
            $parts = explode(':', $ticket['time_worked']);
            $seconds = ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];

            $parts = explode(':', $time_worked);
            $seconds += ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];

            require(HESK_PATH . 'inc/reporting_functions.inc.php');
            $ticket['time_worked'] = hesk_SecondsToHHMMSS($seconds);

            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `time_worked` = ADDTIME(`time_worked`,'" . hesk_dbEscape($time_worked) . "') WHERE `trackid`='" . hesk_dbEscape($trackingID) . "'");
        }

        // Notify staff (owner and collaborators) of a new note
        if (($ticket['owner'] && $ticket['owner'] != $_SESSION['id']) || count($ticket['collaborators']))
        {
            $sql_note = "SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE ";
            if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id']) {
                $sql_note .= " (`id`=".intval($ticket['owner'])." AND `notify_note`='1') ";
            } else {
                $sql_note .= " 1 ";
            }

            if (count($ticket['collaborators'])) {
                $sql_note .= " OR (`notify_collaborator_note`='1' AND `id` IN (".implode(",", $ticket['collaborators'])."))";
            }

            $res = hesk_dbQuery($sql_note);

			if (hesk_dbNumRows($res) > 0)
			{
				// 1. Generate the array with ticket info that can be used in emails
				$info = array(
				'email'			=> $customer_emails,
				'category'		=> $ticket['category'],
				'priority'		=> $ticket['priority'],
				'owner'			=> $ticket['owner'],
                'collaborators' => $ticket['collaborators'],
				'trackid'		=> $ticket['trackid'],
				'status'		=> $ticket['status'],
				'name'			=> $_SESSION['name'],
				'subject'		=> $ticket['subject'],
				'message'		=> stripslashes($msg),
				'dt'			=> hesk_date($ticket['dt'], true),
				'lastchange'	=> hesk_date($ticket['lastchange'], true),
				'attachments'	=> $myattachments,
                'due_date'      => hesk_format_due_date($ticket['due_date']),
				'id'			=> $ticket['id'],
                'time_worked'   => $ticket['time_worked'],
                'last_reply_by' => $ticket['repliername'],
				);

				// 2. Add custom fields to the array
				foreach ($hesk_settings['custom_fields'] as $k => $v)
				{
					$info[$k] = $v['use'] ? $ticket[$k] : '';
				}

                // 3. Add HTML message to the array
                $info['message_html'] = $info['message'];

                // 4. Make sure all values are properly formatted for email
				$ticket = hesk_ticketToPlain($info, 1, 0);

                // 5. Send notification(s)
				require(HESK_PATH . 'inc/email_functions.inc.php');
                hesk_notifyAssignedStaff(false, 'new_note', 'notify_note', 'notify_collaborator_note', array($_SESSION['id']));
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
    $revision = sprintf($hesklang['thist14'],hesk_date(),$time_worked,addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `time_worked`='" . hesk_dbEscape($time_worked) . "', `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `trackid`='" . hesk_dbEscape($trackingID) . "'");

	/* Show ticket */
	hesk_process_messages($hesklang['twu'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

/* Update due date */
if (isset($_POST['action']) && $_POST['action'] == 'due_date' && hesk_token_check('POST')) {

    // Check permission
    if ( ! $can_due_date) {
        hesk_process_messages($hesklang['can_due_date_e'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'ERROR');
    }

    $new_due_date = hesk_POST('new-due-date');
    $sql_overdue_email = '';

    if ($new_due_date == '') {
        $formatted_date = false;
        $revision = sprintf($hesklang['thist20'], hesk_date(), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
    } else {
        $date = hesk_datepicker_get_date($new_due_date);
        if ($date === false) {
            hesk_process_messages($hesklang['invalid_due_date'], 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
        }

        $formatted_date = $date->format('Y-m-d');
        $revision = sprintf($hesklang['thist19'], hesk_date(), $formatted_date, addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

        // If this is a future date, we'll reset the
        $current_date = new DateTime();
        if ($date > $current_date)
        {
            $sql_overdue_email = '`overdue_email_sent`=0,';
        }
    }

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `due_date` = " . ($formatted_date === false ? 'NULL' : "'".hesk_dbEscape($formatted_date)."'") . ", {$sql_overdue_email} `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `trackid`='" . hesk_dbEscape($trackingID) . "' AND (`due_date` IS " . ($formatted_date === false ? 'NOT NULL' : "NULL OR `due_date` != '".hesk_dbEscape($formatted_date)."'") . ")");

    /* Show ticket */
    hesk_process_messages($hesklang['due_date_updated'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
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
    $revision = sprintf($hesklang['thist12'],hesk_date(),$att['real_name'],addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
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

// Add to bookmarks
if (isset($_GET['bm_add']) && hesk_token_check()) {
    if ($_GET['bm_add'] == 1 && empty($ticket['is_bookmark'])) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` (`user_id`, `ticket_id`) VALUES (".intval($_SESSION['id']).", {$ticket['id']})");
        hesk_process_messages($hesklang['bookmarks_added'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
    } elseif (! empty($ticket['is_bookmark'])) {
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` WHERE `ticket_id`={$ticket['id']} AND `user_id`=".intval($_SESSION['id']));
        hesk_process_messages($hesklang['bookmarks_removed'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
    }
}

// Link Ticket
if (isset($_POST['action_type']) && $_POST['action_type'] == 'linked_ticket' && hesk_token_check('POST')) {
    $json_data = [];
    $ticket_track_id = trim(hesk_POST('ticket_track_id'));

    //Tracking ID Required
    if ($ticket_track_id == "") {
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['link_ticket_required_error'].'</div>';
        $json_data['redirect'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);
        echo json_encode($json_data);
        exit;
    }
    //Check for ticket itself linking
    if ($ticket_track_id == $ticket['trackid']) {
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['link_ticket_itself_error'].'</div>';
        $json_data['redirect'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);
        echo json_encode($json_data);
        exit;
    }

    // Check permission
    if ( ! $can_link_tickets) {
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['can_link_tickets_e'].'</div>';
        $json_data['redirect'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);
        echo json_encode($json_data);
        exit;
    }
    // Fetch the ticket data from table
    $res_ticket = hesk_dbQuery("SELECT `id`,`trackid`,`u_email` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
     WHERE `trackid` = '".$ticket_track_id."'");
    $get_ticket_data = hesk_dbFetchAssoc($res_ticket);

    //Check for ticket data
    if (!empty($get_ticket_data)) {
        //Check for linked data in table
        $q = "SELECT `id`,`ticket_id1`,`ticket_id2` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets`
        WHERE ((`ticket_id1` = ".$get_ticket_data['id']." AND `ticket_id2` = ".$ticket['id'].") OR (`ticket_id1` = ".$ticket['id']." AND `ticket_id2` = ".$get_ticket_data['id']."))";

        $res_linked = hesk_dbQuery($q);

        $check_ticket_data = hesk_dbFetchAssoc($res_linked);
        //Check for already linked ticket for same user/customer
        if (!empty($check_ticket_data)) {
            $json_data['status'] = 'ERROR';
            $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['already_linked_error'].'</div>';
            $json_data['redirect'] = '';
            echo json_encode($json_data);
            exit;
        } else {
            // Insert ticket relation into database
            $q = "INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` (`ticket_id1`, `ticket_id2`, `dt_created`) VALUES ('".hesk_dbEscape($ticket['id'])."', '".hesk_dbEscape($get_ticket_data['id'])."',NOW())";
            hesk_dbQuery($q);
            //Update insert history log
            $link_ticket_log = sprintf($hesklang['link_history'], hesk_date(), $ticket_track_id, addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');;
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($link_ticket_log)."') WHERE `id`='".intval($ticket['id'])."'");

            //Get Linked Ticket Html View
            $linked_html = getLinkedHtml($customers, $ticket, $can_link_tickets);

            // Get ticket history log
            $q = hesk_dbQuery("SELECT `history` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` `tickets` WHERE `id`='".intval($ticket['id'])."'");
            $ticket_history = hesk_dbFetchAssoc($q);

            $history_pieces = explode('</li>', $ticket_history['history'], -1);
            $history_html = getTicketHistory($history_pieces);

            $json_data['status'] = 'SUCCESS';
            $json_data['message'] = '<div class="notification green"><b>'.$hesklang['success'].': </b>'.$hesklang['link_ticket_success'].'</div>';
            $json_data['redirect'] = '';
            $json_data['linked_html'] = $linked_html;
            $json_data['history_html'] = $history_html;
            echo json_encode($json_data);
            exit;
        }
    } else {
        //Ticket Not Found
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['ticket_not_found'].'</div>';
        $json_data['redirect'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);
        echo json_encode($json_data);
        exit;
    }
}

// Unlink Ticket
if (isset($_POST['action_type']) && $_POST['action_type'] == 'unlink_ticket') {

    // Check permission
    if ( ! $can_link_tickets) {
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['can_link_tickets_e'].'</div>';
        $json_data['redirect'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);
        echo json_encode($json_data);
        exit;
    }

    $json_data = [];
    $ticket1 = hesk_POST('ticket1');
    $ticket2 = hesk_POST('ticket2');
    $trackid = hesk_POST('trackid');

    $res_linked = hesk_dbQuery("SELECT `id`,`ticket_id1`,`ticket_id2` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets`
        WHERE ((`ticket_id1` = ".$ticket1 ." AND `ticket_id2` = ".$ticket2.") OR (`ticket_id1` = ".$ticket2." AND `ticket_id2` = ".$ticket1."))");

    $check_ticket_data = hesk_dbFetchAssoc($res_linked);

    if (!empty($check_ticket_data)) {

        $id = $check_ticket_data['id'];
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE `id`={$id}");

        if ( hesk_dbAffectedRows() == 1 ){
            //Update delete history log
            $delete_link = sprintf($hesklang['unlink_history'], hesk_date(), $trackid ,addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');;
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($delete_link)."') WHERE `id`='".intval($ticket['id'])."'");

            $linked_html = getLinkedHtml($customers, $ticket, $can_link_tickets);
            // Get ticket history log
            $q = hesk_dbQuery("SELECT `history` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` `tickets` WHERE `id`='".intval($ticket['id'])."'");
            $ticket_history = hesk_dbFetchAssoc($q);

            $history_pieces = explode('</li>', $ticket_history['history'], -1);
            $history_html = getTicketHistory($history_pieces);

            $json_data['status'] = 'SUCCESS';
            $json_data['message'] = '<div class="notification green"><b>'.$hesklang['success'].': </b>'.$hesklang['unlink_success'].'</div>';
            $json_data['redirect'] = '';
            $json_data['linked_html'] = $linked_html;
            $json_data['history_html'] = $history_html;
            echo json_encode($json_data);
            exit;
        } else {
            $json_data['status'] = 'ERROR';
            $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['unlink_error'].'</div>';
            $json_data['redirect'] = '';
            echo json_encode($json_data);
            exit;
        }

    } else {
        $json_data['status'] = 'ERROR';
        $json_data['message'] = '<div class="notification red"><b>'.$hesklang['error'].': </b>'.$hesklang['unlink_error'].'</div>';
        $json_data['redirect'] = '';
        echo json_encode($json_data);
        exit;
    }

}

// Collaborator
if (isset($_GET['collaborator']) && hesk_token_check()) {
    if ($_GET['collaborator'] == 1 && empty($ticket['am_I_collaborator'])) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` (`user_id`, `ticket_id`) VALUES (".intval($_SESSION['id']).", {$ticket['id']})");

        $revision = sprintf($hesklang['thist24'], hesk_date(), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `id`=" . intval($ticket['id']));

        hesk_process_messages($hesklang['collaborating'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
    } elseif ($_GET['collaborator'] == 0 && ! empty($ticket['am_I_collaborator'])) {
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `ticket_id`={$ticket['id']} AND `user_id`=".intval($_SESSION['id']));

        $revision = sprintf($hesklang['thist25'], hesk_date(), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `id`=" . intval($ticket['id']));

        hesk_process_messages($hesklang['not_collaborating'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
    }
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
    $categories_options.='<option value="'.$row['id'].'" '.($row['id'] == $ticket['category'] ? 'selected' : '').'>'.$row['name'].'</option>';
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
	$result = hesk_dbQuery("SELECT `replies`.*, `customers`.`name` AS `customer_name`, `users`.`name` AS `staff_name`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customers`
            ON `customers`.`id` = `replies`.`customer_id`
        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `users`
            ON `users`.`id` = `replies`.`staffid` 
        WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
}
else
{
	$reply = false;
}

// Demo mode
if ( defined('HESK_DEMO') )
{
    foreach ($customers as $customer) {
        $customer['email'] = 'hidden@demo.com';
    }
	$ticket['ip']	 = '127.0.0.1';
}

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
hesk_handle_messages();

// Prepare special custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'] && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category'])) )
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

$options = [];
foreach ($hesk_settings['priorities'] as $key => $value) {
    $data_style ='border-top-color:'.$value['color'].';border-left-color:'.$value['color'].';border-bottom-color:'.$value['color'].';';
    $options[$value['id']] = '<option value="'.$value['id'].'" '.($ticket['priority'] == $value['id'] ? 'selected' : '').' data-class="priority_img priority_dwn" data-style='.$data_style.' >'.$value['name'].'</option>';
}

// Get linked tickets data
function getLinkedTickets($customers , $ticket){
    global $hesk_settings, $hesklang;

    if (empty($customers)) {
        $result["linked_num"] = 0;
        $result["res"] = "";
        $result["show_linked_tickets"] = 0;
        return $result;
    }

    $r = $result = $ids = [];
    // How many linked tickets should we show?
    $show_linked_tickets = 5;

    $first_customer = $customers[0];
    // Get Linked ticket ids
    $res_linked = hesk_dbQuery("SELECT `id`,`ticket_id1`,`ticket_id2` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets`
     WHERE (`ticket_id1` = ".$ticket['id']." OR `ticket_id2` = ".$ticket['id'].")");

    while ($row = hesk_dbFetchAssoc($res_linked))
	{
        if($row["ticket_id1"] != $ticket['id']){
            $ids[] = $row["ticket_id1"];
        }
        if($row["ticket_id2"] != $ticket['id']){
            $ids[] = $row["ticket_id2"];
        }
    }

    $where_in = '';
    if (!empty($ids)) {
        $id = implode(", ", $ids);
        $where_in = "`id` IN (".$id.") AND ";
    } else {
        $result["linked_num"] = 0;
        $result["res"] = "";
        $result["show_linked_tickets"] = $show_linked_tickets;
        return $result;
    }
    // Get recent tickets, ordered by last change
    $res = hesk_dbQuery("SELECT `id`, `trackid`, `status`, `subject` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` `tickets`
        WHERE ".$where_in."".hesk_myCategories()."
            AND ".hesk_myOwnership()."
        ORDER BY `lastchange` DESC
        LIMIT " . ($show_linked_tickets+1));
    $result["linked_num"] = hesk_dbNumRows($res);
    $result["res"] = $res;
    $result["show_linked_tickets"] = $show_linked_tickets;
    return $result;
}

// Get linked tickets html view
function getLinkedHtml($customers, $ticket, $can_link_tickets){
    global $hesk_settings, $hesklang;

    if (empty($customers)) {
        return '';
    }

    $result = getLinkedTickets($customers, $ticket);

    $trackingID = $ticket['trackid'];
    $first_customer = $customers[0];

    $linked_num = $result['linked_num'];
    $res = $result['res'];
    $show_linked_tickets = $result['show_linked_tickets'];
    $i = 0;
    $html = "";
    if ($linked_num > 0){
        while ($linked_ticket = hesk_dbFetchAssoc($res)) {
            $i++;
            if ($i > $show_linked_tickets) {
                hesk_dbFreeResult($res);
                break;
            }

            $html.="<div class = 'linked_ticket_html mb-5'>";
                if (isset($hesk_settings['statuses'][$linked_ticket['status']]['class'])):
                $html.='<span class="dot bg-'.$hesk_settings['statuses'][$linked_ticket['status']]['class'].'" title="'.$hesk_settings['statuses'][$linked_ticket['status']]['name'].'"></span>';
                else:
                $html.='<span class="dot" style="background-color:'.$hesk_settings['statuses'][$linked_ticket['status']]['color'].'" title="'.$hesk_settings['statuses'][$linked_ticket['status']]['name'].'"></span>';
                endif;
            $html.='<a href="admin_ticket.php?track='.$linked_ticket['trackid'].'&amp;Refresh='.rand(10000,99999).'">'.$linked_ticket['subject'].'</a>';
            if($can_link_tickets){
                $html.='<a class="btn btn-links unlink" data-ticket1='.$linked_ticket['id'].' data-ticket2='.$ticket['id'].' data-trackid='.$linked_ticket['trackid'].' data-action="admin_ticket.php?track='.$trackingID.'&amp;Refresh='.rand(10000,99999).'" href="javascript:;">'.$hesklang['unlink_btn'].'</a>';
            }
            $html.="</div>";
        }
    }
    if ($linked_num > 0 && $i > $show_linked_tickets) {
        $html.= '<br><a href="find_tickets.php?q='.urlencode($first_customer['email']).'&amp;what=email&amp;s_my=1&amp;s_ot=1&amp;s_un=1">'.$hesklang['all_previous'].'</a>';
    } elseif ($linked_num == 0) {
        $html.= '<div class = "linked_ticket_html">'.$hesklang['no_linked_tickets'].'</div>';
    }
    return $html;
}

// Get ticket history html view
function getTicketHistory($history_pieces){
    $html = '';
    foreach ($history_pieces as $history_piece) {
        $history_piece = str_replace('<li class="smaller">', '', $history_piece);
        $date_and_contents = explode(' | ', $history_piece);
        if ( ! isset($date_and_contents[1])) {
            $date_and_contents[1] = $date_and_contents[0];
            $date_and_contents[0] = '';
        }

        $html.='<div class="row">';
        $html.='<div class="title">'.$date_and_contents[0].'</div>';
        $html.=' <div class="value">'.$date_and_contents[1].'</div>';
        $html.='</div>';
    }
    return $html;
}
?>
<div class="main__content ticket">
    <div class="ticket__body" <?php echo ($hesk_settings['limit_width'] ? 'style="max-width:'.$hesk_settings['limit_width'].'px"' : ''); ?>>

        <?php if ($hesk_settings['new_top']): ?>
        <!-- START new replies on top subject line -->
        <article class="ticket__body_block original-message" style="padding-bottom: 0px; margin-bottom: 16px; min-height: 48px; border-radius: 2px; box-shadow: 0 2px 8px 0 rgba(38, 40, 42, 0.1);">
            <div style="display:flex; justify-content: space-between; flex-wrap: wrap;">
            <h3>
                <?php if ($ticket['archive']): ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-tag">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tag"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['archived']; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif;
                if ($ticket['is_bookmark']):
                ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-pin is-bookmark">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-pin"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['bookmark']; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif;
                if ($ticket['locked']):
                ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-lock">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-lock"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['loc'].' - '.$hesklang['isloc']; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php echo $ticket['subject']; ?>
            </h3>
            <div class="note__link">
                <?php if ($can_reply): ?>
                <a href="#reply-form" title="<?php echo $hesklang['add_a_reply']; ?>" style="margin-right: 15px;">
                    <svg class="icon icon-edit-ticket">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                    </svg>&nbsp;&nbsp;
                    <?php echo $hesklang['add_a_reply']; ?>
                </a>
                <?php endif; ?>
                <a href="javascript:" title="<?php echo $hesklang['add_a_note']; ?>" onclick="hesk_toggleLayerDisplay('notesDivTop'); $('#notemsg').focus();">
                    <svg class="icon icon-note">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-note"></use>
                    </svg>&nbsp;&nbsp;
                    <?php echo $hesklang['add_a_note']; ?>
                </a>
            </div>
            </div>

            <?php
            $res = hesk_dbQuery("SELECT t1.*, t2.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` AS t1 LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='".intval($ticket['id'])."' ORDER BY t1.`id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
            ?>
            <div class="block--notes" <?php echo hesk_dbNumRows($res) ? 'style="padding-bottom: 15px"' : ''; ?>>
                <div id="notesDivTop" style="display:<?php echo isset($_SESSION['note_message']) ? 'block' : 'none'; ?>; margin-top: 20px; padding-bottom: 15px;">
                    <form id="notesformTop" method="post" action="admin_ticket.php" class="form" enctype="multipart/form-data">
                        <i><?php echo $hesklang['nhid']; ?></i><br>
                        <textarea class="form-control" name="notemsg" id="notemsg" rows="6" cols="60" style="height: auto; resize: vertical; transition: none;"><?php echo isset($_SESSION['note_message']) ? stripslashes(hesk_input($_SESSION['note_message'])) : ''; ?></textarea>
                        <?php
                        // attachments
                        if ($hesk_settings['attachments']['use'])
                        {
                        ?>
                        <div class="attachments">
                            <div class="block--attach">
                                <svg class="icon icon-attach">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-attach"></use>
                                </svg>
                                <div>
                                    <?php echo $hesklang['attachments'] . ':<br>'; ?>
                                </div>
                            </div>
                            <?php
                            require_once(HESK_PATH . 'inc/attachments.inc.php');
                            build_dropzone_markup(true, 'notesFiledropTop');
                            display_dropzone_field(HESK_PATH . 'upload_attachment.php', true, 'notesFiledropTop');
                            dropzone_display_existing_files(hesk_SESSION_array('note_attachments'), 'notesFiledropTop');
                            ?>
                        </div>
                        <?php
                        }
                        ?>
                        <button type="submit" class="btn btn-full">
                            <?php echo $hesklang['sub_note']; ?>
                        </button>
                        <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                        <input type="hidden" id="time_worked_notesTop" name="time_worked_notes" value="">
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    </form>
                </div>
                <?php
                while ($note = hesk_dbFetchAssoc($res)) {
                    ?>
                    <div class="note">
                        <div class="note__head">
                            <div class="name">
                                <?php echo $hesklang['noteby']; ?>
                                <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b>
                                &raquo;
                                <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($note['dt'])) ; ?>" title="<?php echo hesk_date($note['dt'], true); ?>"><?php echo hesk_date($note['dt'], true); ?></time>
                            </div>
                            <?php
                            if ($can_del_notes || $note['who'] == $_SESSION['id'])
                            {
                            ?>
                            <div class="actions">
                                <a class="tooltip" href="edit_note.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;note=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" title="<?php echo $hesklang['ednote']; ?>">
                                    <svg class="icon icon-edit-ticket">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                    </svg>
                                </a>
                                <a class="tooltip" href="admin_ticket.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;delnote=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" onclick="return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['delnote']).'?'; ?>');" title="<?php echo $hesklang['delnote']; ?>">
                                    <svg class="icon icon-delete">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                    </svg>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="note__description">
                            <p><?php echo $note['message']; ?></p>
                        </div>
                        <div class="note__attachments">
                            <?php
                            // Attachments
                            if ( $hesk_settings['attachments']['use'] && strlen($note['attachments']) )
                            {
                                echo strlen($note['message']) ? '<br>' : '';

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
                                            echo '<a class="tooltip" data-ztt_vertical_offset="0" style="margin-right: 8px;" href="admin_ticket.php?delnote='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');" title="'.$hesklang['dela'].'">
                                                    <svg class="icon icon-delete" style="text-decoration: none; vertical-align: text-bottom;">
                                                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
                                                    </svg>
                                                </a> &raquo;';
                                        }
                                        // Show "delete attachment" link
                                        else
                                        {
                                            echo '<a class="tooltip" data-ztt_vertical_offset="0" style="margin-right: 8px;" href="admin_ticket.php?delatt='.$att_id.'&amp;note='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');" title="'.$hesklang['dela'].'">
                                                    <svg class="icon icon-delete" style="vertical-align: text-bottom;">
                                                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
                                                    </svg>
                                                </a> &raquo;';
                                        }
                                    }

                                    echo '
				<a href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'" title="'.$hesklang['dnl'].' '.$att_name.'">
				    <svg class="icon icon-attach" style="vertical-align: text-bottom;">
                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-attach"></use>
                    </svg>
                </a>
				<a class="underline" href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'" title="'.$hesklang['dnl'].' '.$att_name.'">'.$att_name.'</a><br>
				';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

        </article>
        <!-- END new replies on top subject line -->
        <?php endif; ?>

        <?php
        /* Reply form on top? */
        if ($can_reply && $hesk_settings['reply_top'] == 1)
        {
            hesk_printReplyForm();
        }

        if ($hesk_settings['new_top'])
        {
            $i = hesk_printTicketReplies() ? 0 : 1;
        }
        else
        {
            $i = 1;
        }
        ?>
        <article class="ticket__body_block original-message">
            <?php if ( ! $hesk_settings['new_top'] || ($hesk_settings['new_top'] && ! $ticket['replies'])): ?>
            <h3>
                <?php if ($ticket['archive']): ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-tag">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tag"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['archived']; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif;
                if ($ticket['is_bookmark']):
                ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-pin is-bookmark">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-pin"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['bookmark']; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif;
                if ($ticket['locked']):
                ?>
                    <div class="tooltype right out-close">
                        <svg class="icon icon-lock">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-lock"></use>
                        </svg>
                        <div class="tooltype__content">
                            <div class="tooltype__wrapper">
                                <?php echo $hesklang['loc'].' - '.$hesklang['isloc']; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( ! $hesk_settings['new_top']) {echo $ticket['subject'];} ?>
            </h3>
            <?php endif; ?>
            <div class="block--head">
                <div class="contact grid">
                    <div class="requester-header">
                        <span><?php echo $hesklang['m_from'] ?>:</span>
                    </div>
                    <div class="requester">
                        <?php
                        if (!$found_requester):
                            echo $hesklang['anon_name'];
                        else:
                            ?>
                            <div class="dropdown customer left out-close">
                                <label>
                                    <svg class="icon icon-person">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-person"></use>
                                    </svg>
                                    <span><?php echo $requester['name']; ?></span>
                                    <svg class="icon icon-chevron-down">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                    </svg>
                                </label>
                                <ul class="dropdown-list">
                                    <?php
                                    if ($requester['email'] != '')
                                    {
                                        ?>
                                        <li class="noclose">
                                            <span class="title"><?php echo $hesklang['email']; ?>:</span>
                                            <span class="value"><a href="mailto:<?php echo $requester['email']; ?>"><?php echo $requester['email']; ?></a></span>
                                            <a href="javascript:" title="<?php echo $hesklang['copy_value']; ?>" onclick="navigator.clipboard.writeText('<?php echo $requester['email']; ?>');$('#copy-email').addClass('copied');setTimeout(function(){$('#copy-email').removeClass('copied')}, 150);">
                                                <svg class="icon icon-merge copy-me" id="copy-email">
                                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-merge"></use>
                                                </svg>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                    <li class="noclose">
                                        <span class="title"><?php echo $hesklang['ip']; ?>:</span>
                                        <?php if ($ticket['ip'] == '' || $ticket['ip'] == 'Unknown' || $ticket['ip'] == $hesklang['unknown']): ?>
                                        <span class="value"><?php echo $hesklang['unknown']; ?></span>
                                        <?php else: ?>
                                        <span class="value"><a href="../ip_whois.php?ip=<?php echo urlencode($ticket['ip']); ?>"><?php echo $ticket['ip']; ?></a></span>
                                        <a href="javascript:" title="<?php echo $hesklang['copy_value']; ?>" onclick="navigator.clipboard.writeText('<?php echo $ticket['ip']; ?>');$('#copy-ip').addClass('copied');setTimeout(function(){$('#copy-ip').removeClass('copied')}, 150);">
                                            <svg class="icon icon-merge copy-me" id="copy-ip">
                                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-merge"></use>
                                            </svg>
                                        </a>
                                        <?php endif; ?>
                                    </li>
                                    <li class="separator"></li>
                                    <?php if (($hesk_settings['customer_accounts'] && $can_man_customers) ||
                                        (!$hesk_settings['customer_accounts'] && $can_edit)): ?>
                                    <li>
                                        <svg class="icon icon-edit">
                                            <use xlink:href="../img/sprite.svg#icon-edit"></use>
                                        </svg>
                                        <a href="manage_customers.php?a=edit&track=<?php echo $trackingID; ?>&id=<?php echo intval($requester['id']); ?>">
                                            <?php echo $hesklang['customer_manage_edit']; ?>
                                        </a>
                                    </li>
                                    <li class="separator"></li>
                                    <?php endif; ?>
                                    <?php
                                    if ($requester['email'] != '' && $can_ban_emails) {
                                        echo '<li>';
                                        if ( $email_id = hesk_isBannedEmail($requester['email']) ) {
                                            if ($can_unban_emails) {
                                                echo '
                                        <svg class="icon icon-eye-close">
                                            <use xlink:href="../img/sprite.svg#icon-eye-close"></use>
                                        </svg>
                                        <a href="banned_emails.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($email_id).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['unban_email'].'</a>
                                    ';
                                            } else {
                                                echo $hesklang['eisban'];
                                            }
                                        } else {
                                            echo '
                                    <svg class="icon icon-eye-open">
                                        <use xlink:href="../img/sprite.svg#icon-eye-open"></use>
                                    </svg>
                                    <a href="banned_emails.php?a=ban&amp;track='.$trackingID.'&amp;email='.urlencode($requester['email']).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['savebanemail'].'</a>
                                ';
                                        }
                                        echo '</li>';
                                    }

                                    // Format IP for lookup
                                    if ($ticket['ip'] != '' && $ticket['ip'] != 'Unknown' && $ticket['ip'] != $hesklang['unknown']) {
                                        echo '<li>';
                                        if ($can_ban_ips) {
                                            if ( $ip_id = hesk_isBannedIP($ticket['ip']) ) {
                                                if ($can_unban_ips) {
                                                    echo '
                                            <svg class="icon icon-eye-close">
                                                <use xlink:href="../img/sprite.svg#icon-eye-close"></use>
                                            </svg>
                                            <a href="banned_ips.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($ip_id).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['unban_ip'].'</a>
                                        ';
                                                } else {
                                                    echo $hesklang['ipisban'];
                                                }
                                            } else {
                                                echo '
                                        <svg class="icon icon-eye-open">
                                            <use xlink:href="../img/sprite.svg#icon-eye-open"></use>
                                        </svg>
                                        <a href="banned_ips.php?a=ban&amp;track='.$trackingID.'&amp;ip='.urlencode($ticket['ip']).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['savebanip'].'</a>
                                    ';
                                            }
                                        }
                                        echo '</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        &raquo; <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($ticket['dt'])) ; ?>" title="<?php echo hesk_date($ticket['dt'], true); ?>"><?php echo hesk_date($ticket['dt'], true); ?></time>
                    </div>
                    <?php
                    if (count($followers) > 0):
                    ?>
                    <div class="cc-header">
                        <span><?php echo $hesklang['cc']; ?>:</span>
                    </div>
                    <div class="cc">
                        <?php foreach ($followers as $customer): ?>
                            <div class="dropdown customer left out-close">
                                <label>
                                    <svg class="icon icon-person">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-person"></use>
                                    </svg>
                                    <span><?php echo $customer['name'] === '' ? $customer['email'] : $customer['name']; ?></span>
                                    <svg class="icon icon-chevron-down">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                    </svg>
                                </label>
                                <ul class="dropdown-list">
                                    <?php
                                    if ($customer['email'] != '')
                                    {
                                        ?>
                                        <li class="noclose">
                                            <span class="title"><?php echo $hesklang['email']; ?>:</span>
                                            <span class="value"><a href="mailto:<?php echo $customer['email']; ?>"><?php echo $customer['email']; ?></a></span>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                    <li class="noclose">
                                        <span class="title"><?php echo $hesklang['ip']; ?>:</span>
                                        <span class="value">
                                <?php
                                if ($ticket['ip'] == '' || $ticket['ip'] == 'Unknown' || $ticket['ip'] == $hesklang['unknown']) {
                                    echo $hesklang['unknown'];
                                } else {
                                    ?>
                                    <a href="../ip_whois.php?ip=<?php echo urlencode($ticket['ip']); ?>"><?php echo $ticket['ip']; ?></a>
                                <?php } ?>
                            </span>
                                    </li>
                                    <li class="separator"></li>
                                    <?php if (($hesk_settings['customer_accounts'] && $can_man_customers) ||
                                        (!$hesk_settings['customer_accounts'] && $can_edit)): ?>
                                        <li>
                                            <svg class="icon icon-edit">
                                                <use xlink:href="../img/sprite.svg#icon-edit"></use>
                                            </svg>
                                            <a href="manage_customers.php?a=edit&track=<?php echo $trackingID; ?>&id=<?php echo intval($customer['id']); ?>">
                                                <?php echo $hesklang['customer_manage_edit']; ?>
                                            </a>
                                        </li>
                                        <li class="separator"></li>
                                    <?php endif;
                                    if ($customer['email'] != '' && $can_ban_emails) {
                                        echo '<li>';
                                        if ( $email_id = hesk_isBannedEmail($customer['email']) ) {
                                            if ($can_unban_emails) {
                                                echo '
                                        <svg class="icon icon-eye-close">
                                            <use xlink:href="../img/sprite.svg#icon-eye-close"></use>
                                        </svg>
                                        <a href="banned_emails.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($email_id).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['unban_email'].'</a>
                                    ';
                                            } else {
                                                echo $hesklang['eisban'];
                                            }
                                        } else {
                                            echo '
                                    <svg class="icon icon-eye-open">
                                        <use xlink:href="../img/sprite.svg#icon-eye-open"></use>
                                    </svg>
                                    <a href="banned_emails.php?a=ban&amp;track='.$trackingID.'&amp;email='.urlencode($customer['email']).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['savebanemail'].'</a>
                                ';
                                        }
                                        echo '</li>';
                                    }

                                    // Format IP for lookup
                                    if ($ticket['ip'] != '' && $ticket['ip'] != 'Unknown' && $ticket['ip'] != $hesklang['unknown']) {
                                        echo '<li>';
                                        if ($can_ban_ips) {
                                            if ( $ip_id = hesk_isBannedIP($ticket['ip']) ) {
                                                if ($can_unban_ips) {
                                                    echo '
                                            <svg class="icon icon-eye-close">
                                                <use xlink:href="../img/sprite.svg#icon-eye-close"></use>
                                            </svg>
                                            <a href="banned_ips.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($ip_id).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['unban_ip'].'</a>
                                        ';
                                                } else {
                                                    echo $hesklang['ipisban'];
                                                }
                                            } else {
                                                echo '
                                        <svg class="icon icon-eye-open">
                                            <use xlink:href="../img/sprite.svg#icon-eye-open"></use>
                                        </svg>
                                        <a href="banned_ips.php?a=ban&amp;track='.$trackingID.'&amp;ip='.urlencode($ticket['ip']).'&amp;token='.hesk_token_echo(0).'">'.$hesklang['savebanip'].'</a>
                                    ';
                                            }
                                        }
                                        echo '</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            foreach ($hesk_settings['custom_fields'] as $k=>$v)
            {
                if ($v['use'] && $v['place']==0 && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category'])) )
                {

                    switch ($v['type'])
                    {
                        case 'email':
                            $ticket[$k] = '<a href="mailto:'.$ticket[$k].'">'.$ticket[$k].'</a>';
                            break;
                    }

                    echo '
					<div>
                        <span class="custom-field-title">'.$v['name:'].'</span>
                        <span>'.$ticket[$k].'</span>
					</div>';
                }
            }

            if ($ticket['message_html'] != '')
            {
                ?>
                <div class="block--description browser-default">
                    <p><?php echo $ticket['message_html']; ?></p>
                    <p></p>
                </div>
                <?php
            }

            /* custom fields after message */
            foreach ($hesk_settings['custom_fields'] as $k=>$v)
            {
                if ($v['use'] && $v['place'] && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category'])) )
                {
                    switch ($v['type'])
                    {
                        case 'email':
                            $ticket[$k] = '<a href="mailto:'.$ticket[$k].'">'.$ticket[$k].'</a>';
                            break;
                    }

                    echo '
					<div>
                        <span class="custom-field-title">'.$v['name:'].'</span>
                        <span>'.$ticket[$k].'</span>
					</div>';
                }
            }

            /* Print attachments */
            hesk_listAttachments($ticket['attachments'], 0 , $i);

            // Show suggested KB articles
            if ($hesk_settings['kb_enable'] && $hesk_settings['kb_recommendanswers'] && ! empty($ticket['articles']) )
            {
                $suggested = array();
                $suggested_list = '';

                // Get article info from the database
                $articles = hesk_dbQuery("SELECT `id`,`subject` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` WHERE `id` IN (".preg_replace('/[^0-9\,]/', '', $ticket['articles']).")");
                while ($article=hesk_dbFetchAssoc($articles))
                {
                    $suggested[$article['id']] = '<a href="../knowledgebase.php?article='.$article['id'].'">'.$article['subject'].'</a>';
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
                    ?>
                    <div class="block--suggested">
                        <b><?php echo $hesklang['taws']; ?></b>
                        <?php
                        if ($_SESSION['show_suggested']){
                            echo $suggested_list;
                        } else {
                            echo '<a href="Javascript:void(0)" onclick="hesk_toggleLayerDisplay(\'suggested_articles\', \'flex\')">'.$hesklang['sska'].'</a>
                                        <span id="suggested_articles" style="display:none">'.$suggested_list.'</span>';
                        }
                        ?>
                    </div>
                    <?php
                }
            }
            ?>

            <?php if ( ! $hesk_settings['new_top']): ?>
            <div class="block--notes">
                <?php
                $res = hesk_dbQuery("SELECT t1.*, t2.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` AS t1 LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='".intval($ticket['id'])."' ORDER BY t1.`id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
                while ($note = hesk_dbFetchAssoc($res)) {
                    ?>
                    <div class="note">
                        <div class="note__head">
                            <div class="name">
                                <?php echo $hesklang['noteby']; ?>
                                <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b>
                                &raquo;
                                <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($note['dt'])) ; ?>" title="<?php echo hesk_date($note['dt'], true); ?>"><?php echo hesk_date($note['dt'], true); ?></time>
                            </div>
                            <?php
                            if ($can_del_notes || $note['who'] == $_SESSION['id'])
                            {
                            ?>
                            <div class="actions">
                                <a class="tooltip" href="edit_note.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;note=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" title="<?php echo $hesklang['ednote']; ?>">
                                    <svg class="icon icon-edit-ticket">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                    </svg>
                                </a>
                                <a class="tooltip" href="admin_ticket.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;delnote=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" onclick="return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['delnote']).'?'; ?>');" title="<?php echo $hesklang['delnote']; ?>">
                                    <svg class="icon icon-delete">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                    </svg>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="note__description">
                            <p><?php echo $note['message']; ?></p>
                        </div>
                        <div class="note__attachments">
                            <?php
                            // Attachments
                            if ( $hesk_settings['attachments']['use'] && strlen($note['attachments']) )
                            {
                                echo strlen($note['message']) ? '<br>' : '';

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
                                            echo '<a class="tooltip" data-ztt_vertical_offset="0" style="margin-right: 8px;" href="admin_ticket.php?delnote='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');" title="'.$hesklang['dela'].'">
                                                    <svg class="icon icon-delete" style="text-decoration: none; vertical-align: text-bottom;">
                                                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
                                                    </svg>
                                                </a> &raquo;';
                                        }
                                        // Show "delete attachment" link
                                        else
                                        {
                                            echo '<a class="tooltip" data-ztt_vertical_offset="0" style="margin-right: 8px;" href="admin_ticket.php?delatt='.$att_id.'&amp;note='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');" title="'.$hesklang['dela'].'">
                                                    <svg class="icon icon-delete" style="vertical-align: text-bottom;">
                                                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
                                                    </svg>
                                                </a> &raquo;';
                                        }
                                    }

                                    echo '
				<a href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'" title="'.$hesklang['dnl'].' '.$att_name.'">
				    <svg class="icon icon-attach" style="vertical-align: text-bottom;">
                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-attach"></use>
                    </svg>
                </a>
				<a class="underline" href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'" title="'.$hesklang['dnl'].' '.$att_name.'">'.$att_name.'</a><br>
				';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <button class="btn btn--blue-border" type="button" onclick="hesk_toggleLayerDisplay('notesDiv')">
                    <svg class="icon icon-note">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-note"></use>
                    </svg>&nbsp;&nbsp;
                    <?php echo $hesklang['add_a_note']; ?>
                </button>
                <div id="notesDiv" style="display:<?php echo isset($_SESSION['note_message']) ? 'block' : 'none'; ?>; margin-top: 20px">
                    <form id="notesform" method="post" action="admin_ticket.php" class="form" enctype="multipart/form-data">
                        <i><?php echo $hesklang['nhid']; ?></i><br>
                        <textarea class="form-control" name="notemsg" rows="6" cols="60" style="height: auto; resize: vertical; transition: none;"><?php echo isset($_SESSION['note_message']) ? stripslashes(hesk_input($_SESSION['note_message'])) : ''; ?></textarea>
                        <?php
                        // attachments
                        if ($hesk_settings['attachments']['use'])
                        {
                        ?>
                            <div class="attachments">
                                <div class="block--attach">
                                    <svg class="icon icon-attach">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-attach"></use>
                                    </svg>
                                    <div>
                                        <?php echo $hesklang['attachments'] . ':<br>'; ?>
                                    </div>
                                </div>
                                <?php
                                require_once(HESK_PATH . 'inc/attachments.inc.php');
                                build_dropzone_markup(true, 'notesFiledrop');
                                display_dropzone_field(HESK_PATH . 'upload_attachment.php', true, 'notesFiledrop');
                                dropzone_display_existing_files(hesk_SESSION_array('note_attachments'), 'notesFiledrop');
                                ?>
                            </div>
                        <?php
                        }
                        ?>
                        <button type="submit" class="btn btn-full">
                            <?php echo $hesklang['sub_note']; ?>
                        </button>
                        <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                        <input type="hidden" id="time_worked_notes" name="time_worked_notes" value="">
                        &nbsp;
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    </form>
                    <?php
                    // Track time worked?
                    if ($hesk_settings['time_worked']) {
                        ?>
                            <script>
                                $('#notesform').submit(function() {
                                     $('#time_worked_notes').val($('#time_worked').val());
                                });
                            </script>
                        </section>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </article>
        <?php

        if ( ! $hesk_settings['new_top'])
        {
            hesk_printTicketReplies();
        }

        /* Reply form on bottom? */
        if ($can_reply && ! $hesk_settings['reply_top'])
        {
            hesk_printReplyForm();
        }

        $random=rand(10000,99999);

        // Prepare one-click action to open/resolve a ticket
        $status_action = '';
        if ($ticket['status'] == 3)
        {
            if ($can_reply)
            {
                $status_action = '[<a href="change_status.php?track='.$trackingID.'&amp;s=1&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">'.$hesklang['open_action'].'</a>]';
            }
        }
        elseif ($can_resolve)
        {
            $status_action = '[<a href="change_status.php?track='.$trackingID.'&amp;s=3&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">'.$hesklang['close_action'].'</a>]';
        }
        ?>
    </div>
    <div class="ticket__params" <?php echo ($hesk_settings['limit_width'] ? 'style="max-width:'.$hesk_settings['limit_width'].'px"' : ''); ?>>
        <section class="params--bar" style="padding-left: 0">
            <?php echo hesk_getAdminButtons(); ?>
        </section>
        <section class="params--block params">
            <!-- Ticket status -->
            <div class="row ts" id="ticket-status-div" <?php echo strlen($status_action) ? 'style="margin-bottom: 10px;"' : ''; ?>>
                <div class="title"><label for="select_s"><?php echo $hesklang['ticket_status']; ?>:</label></div>
                <?php if ($can_reply): ?>
                <div class="value dropdown-select center out-close">
                    <form action="change_status.php" method="post">
                        <select id="select_s" name="s" onchange="this.form.submit()">
                            <?php echo hesk_get_status_select('', $can_resolve, $ticket['status']); ?>
                        </select>
                        <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    </form>
                </div>
                <?php else: ?>
                <div class="value center">
                    <?php echo hesk_get_admin_ticket_status($ticket['status']); ?>
                </div>
                <?php
                endif;
                ?>
            </div>

            <!-- Ticket one click open/resolve -->
            <?php if (strlen($status_action)): ?>
            <div class="row">
                <div class="title">&nbsp;</div>
                <div class="value center out-close">
                    <?php echo $status_action; ?>
                </div>
            </div>
            <?php
            endif;
            ?>

            <!-- Ticket category -->
            <div class="row">
                <div class="title">
                    <label for="select_category">
                        <?php echo $hesklang['category']; ?>:
                    </label>
                </div>
                <?php if (strlen($categories_options) && ($can_change_cat || $can_change_own_cat)): ?>
                <form action="move_category.php" method="post">
                    <div class="value dropdown-select center out-close">
                        <select id="select_category" name="category" onchange="this.form.submit()">
                            <?php echo $categories_options; ?>
                        </select>
                    </div>
                    <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                </form>
                <?php else: ?>
                <div class="value center out-close">
                    <?php echo $category['name']; ?>
                </div>
                <?php
                endif;
                ?>
            </div>

            <!-- Ticket priority -->
            <div class="row">
                <div class="title">
                    <label for="select_priority">
                        <?php echo $hesklang['priority']; ?>:
                    </label>
                </div>
                <?php if ($can_reply): ?>
                <form action="priority.php" method="post">
                    <div class="dropdown-select center out-close priority select-priority">
                        <select id="select_priority" name="priority" onchange="this.form.submit()">
                            <?php echo implode('', $options); ?>
                        </select>
                    </div>
                    <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                </form>
                <?php else: ?>
                <div class="value center out-close">
                    <?php
                    foreach ($hesk_settings['priorities'] as $key => $value) {
                        if($ticket['priority'] == $value['id']){
                            $data_style ='border-top-color:'.$value['color'].';border-left-color:'.$value['color'].';border-bottom-color:'.$value['color'].';';
                            ?>
                            <span class=""> <div class='priority_img' style='<?php echo $data_style; ?>'></div> <?php echo $value['name']; ?></span>
                            <?php
                        }
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ticket assigned to -->
            <div class="row">
                <div class="title">
                    <label for="select_owner">
                        <?php echo $hesklang['assigned_to']; ?>:
                    </label>
                </div>
                <?php if ($can_assign_others): ?>
                <form action="assign_owner.php" method="post">
                    <div class="value dropdown-select center out-close">
                        <select id="select_owner" name="owner" onchange="this.form.submit()" data-append-icon-class="icon-person">
                            <option value="-1"> &gt; <?php echo $hesklang['unas']; ?> &lt; </option>
                            <?php
                            foreach ($admins as $k=>$v)
                            {
                                echo '<option value="'.$k.'" '.($k == $ticket['owner'] ? 'selected' : '').'>'.$v.'</option>';
                            }
                            ?>
                        </select>
                        <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                        <?php
                        if (!$ticket['owner'])
                        {
                            echo '<input type="hidden" name="unassigned" value="1">';
                        }
                        ?>
                    </div>
                </form>
                <?php else: ?>
                <div class="value center out-close">
                    <?php
                    echo isset($admins[$ticket['owner']]) ? '<b>'.$admins[$ticket['owner']].'</b>' : '<b>'.$hesklang['unas'].'</b>';
                    ?>
                </div>
                <?php
                endif;
                ?>
            </div>

            <!-- Ticket one click assign to self -->
            <?php if (!$ticket['owner'] && $can_assign_self): ?>
            <div class="row">
                <div class="title">&nbsp;</div>
                <div class="value center out-close">
                    <?php echo '[<a class="link" href="assign_owner.php?track='.$trackingID.'&amp;owner='.$_SESSION['id'].'&amp;token='.hesk_token_echo(0).'&amp;unassigned=1">'.$hesklang['asss'].'</a>]'; ?>
                </div>
            </div>
            <?php
            endif;
            ?>

            <!-- Ticket collaborators -->
            <?php
            // Get existing ticket collaborators
            $collaborators = array();
            $res_w = hesk_dbQuery("SELECT `u`.`id`,`u`.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `u` ON `w`.`user_id` = `u`.`id` WHERE `w`.`ticket_id`=".intval($ticket['id']));
            while ($collaborator = hesk_dbFetchAssoc($res_w)) {
                $collaborators[] = $collaborator;
            }

            // Get list of users who can be added as a collaborator on this ticket
            $possible_new_collaborators = array();
            foreach ($admins as $k=>$v) {
                // If the ticket is assigned to you, you cannot be a collaborator
                if ($k == $ticket['owner']) {
                    continue;
                }

                // Remove people who are already collaborators
                if (hesk_isTicketCollaborator($ticket['id'], $k)) {
                    continue;
                }

                $possible_new_collaborators[$k] = $v;
            }

            // Only display collaborators if we have existing or possible collaborators
            if (count($collaborators) || ($can_assign_others && count($possible_new_collaborators))): ?>
                <div class="row">
                    <div class="title">
                        <label for="select_owner">
                            <?php echo $hesklang['collaborators']; ?>:
                        </label>
                    </div>
                    <?php if ($can_assign_others): ?>
                    <form action="collaborator.php" method="post">
                        <div class="value center out-close removable-list">
                        <?php foreach($collaborators as $collaborator) {
                            echo '<div class="removable-list-item">
                                    <span>' . $collaborator['name'] . '</span>
                                    <a href="collaborator.php?track='.$trackingID.'&amp;user='.intval($collaborator['id']).'&amp;token='.hesk_token_echo(0).'&amp;collaborator=0">
                                        <i class="close">
                                            <svg class="icon icon-close">
                                              <use xlink:href="'. HESK_PATH.'img/sprite.svg#icon-close"></use>
                                            </svg>
                                        </i>
                                    </a>
                                </div>';
                        }

                        if (count($possible_new_collaborators) > 0) {
                            ?>

                                <div class="dropdown-select dropdown-fit-full-width">
                                <select id="select_user" name="user" onchange="this.form.submit()" data-append-icon-class="icon-person">
                                    <option value=""> &gt; <?php echo $hesklang['add_collaborator']; ?> &lt; </option>
                                    <?php
                                    foreach ($possible_new_collaborators as $k=>$v) {
                                        echo '<option value="'.$k.'">'.$v.'</option>';
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="collaborator" value="1">
                                <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                                </div>
                            </div>
                        </form>
                        <?php
                    }
                    ?>
                    <?php else: ?>
                    <div class="value center out-close removable-list">
                        <?php foreach($collaborators as $collaborator) {
                            echo '<div class="removable-list-item">
                                    <span>' . $collaborator['name'] . '</span>
                                </div>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Collaborate link -->
                <?php if (empty($ticket['am_I_collaborator']) && $ticket['owner'] != $_SESSION['id']): ?>
                <div class="row">
                    <div class="title">&nbsp;</div>
                    <div class="value center out-close">
                        <?php echo '[<a class="link" href="admin_ticket.php?track='.$trackingID.'&amp;token='.hesk_token_echo(0).'&amp;collaborator=1">'.$hesklang['collaborate'].'</a>]'; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="params--block details accordion visible">
            <h4 class="accordion-title">
                <span><?php echo $hesklang['ticket_details']; ?></span>
                <svg class="icon icon-chevron-down">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                </svg>
            </h4>
            <div class="accordion-body" style="display:block">
                <div class="row">
                    <div class="title"><?php echo $hesklang['trackID']; ?>:</div>
                    <div class="value"><?php echo $trackingID; ?>
                    <a href="javascript:" title="<?php echo $hesklang['copy_value']; ?>" onclick="navigator.clipboard.writeText('<?php echo $trackingID; ?>');$('#copy-tid').addClass('copied');setTimeout(function(){$('#copy-tid').removeClass('copied')}, 150);">
                        <svg class="icon icon-merge copy-me" id="copy-tid">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-merge"></use>
                        </svg>
                    </a>
                    </div>
                </div>
                <div class="row">
                    <div class="title">&nbsp;</div>
                    <div class="value">
                        <a class="tooltip" href="javascript:"
                           title="<?php echo $hesklang['copy_link_title']; ?>"
                           data-action="generate-link"
                           data-link="<?php echo htmlspecialchars($hesk_settings['hesk_url']) . '/ticket.php?track='.urlencode($trackingID).'&e='.urlencode(strpos($requester['email'], ',') ? strstr($requester['email'], ',', true) : $requester['email']); ?>">
                           <?php echo $hesklang['copy_link']; ?>
                        </a>
                        <div class="notification-flash green" data-type="link-generate-message">
                            <i class="close">
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                            </i>
                            <div class="notification--title error-title"><?php echo $hesklang['genl_not_copied']; ?></div>
                            <div class="notification--title"><?php echo $hesklang['genl']; ?></div>
                            <div class="notification--text"><?php echo $hesklang['copy_link_exp']; ?></div>
                        </div>
                    </div>
                </div>
                <?php
                if ($hesk_settings['sequential'])
                {
                    ?>
                    <div class="row">
                        <div class="title"><?php echo $hesklang['seqid']; ?>:</div>
                        <div class="value"><?php echo $ticket['id']; ?></div>
                    </div>
                    <?php
                }
                ?>
                <div class="row">
                    <div class="title"><?php echo $hesklang['created_on']; ?>:</div>
                    <div class="value"><?php echo hesk_date($ticket['dt'], true); ?></div>
                </div>
                <div class="row">
                    <div class="title"><?php echo $hesklang['last_update']; ?>:</div>
                    <div class="value"><?php echo hesk_date($ticket['lastchange'], true); ?></div>
                </div>
                <div class="row">
                    <div class="title"><?php echo $hesklang['replies']; ?>:</div>
                    <div class="value"><?php echo $ticket['replies']; ?></div>
                </div>
                <div class="row">
                    <div class="title"><?php echo $hesklang['last_replier']; ?>:</div>
                    <div class="value"><?php echo $ticket['repliername']; ?></div>
                </div>
                <?php
                if ($hesk_settings['time_worked'])
                {
                ?>
                <div class="row">
                    <div class="title"><?php echo $hesklang['ts']; ?>:</div>
                    <?php
                    if ($can_reply || $can_edit)
                    {
                        ?>
                    <div class="value">
                        <a href="javascript:" onclick="hesk_toggleLayerDisplay('modifytime')">
                            <?php echo $ticket['time_worked']; ?>
                        </a>

                        <?php $t = hesk_getHHMMSS($ticket['time_worked']); ?>

                        <div id="modifytime" style="display:none">
                            <form class="form" method="post" action="admin_ticket.php">
                                <div class="form-group">
                                    <label for="hours"><?php echo $hesklang['hh']; ?></label>
                                    <input class="form-control" type="text" id="hours" name="h" value="<?php echo $t[0]; ?>" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <label for="minutes"><?php echo $hesklang['mm']; ?></label>
                                    <input class="form-control" type="text" id="minutes" name="m" value="<?php echo $t[1]; ?>" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <label for="seconds"><?php echo $hesklang['ss']; ?></label>
                                    <input class="form-control" type="text" id="seconds" name="s" value="<?php echo $t[2]; ?>" autocomplete="off">
                                </div>

                                <button style="display: inline-flex; width: auto; height: 40px; padding: 0 16px; margin-bottom: 5px;" class="btn btn-full" type="submit"><?php echo $hesklang['save']; ?></button>
                                <a class="btn btn--blue-border" href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifytime')"><?php echo $hesklang['cancel']; ?></a>
                                <input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
                                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                            </form>
                        </div>
                    </div>
                        <?php
                    }
                    else
                    {
                        echo '<div class="value">' . $ticket['time_worked'] . '</div>';
                    }
                    ?>
                </div>
                <?php
                }
                ?>
                <div class="row">
                    <div class="title"><?php echo $hesklang['due_date']; ?></div>
                    <?php
                    $hesk_settings['datepicker'] = array();
                    $due_date = $hesklang['none'];
                    $datepicker_due_date = '';
                    if ($ticket['due_date'] != null) {
                        $datepicker_due_date = hesk_date($ticket['due_date'], true, true, false);
                        $hesk_settings['datepicker']['#new-due-date']['timestamp'] = $datepicker_due_date;
                        $due_date = hesk_format_due_date($datepicker_due_date, false);
                        $datepicker_due_date = hesk_datepicker_format_date($datepicker_due_date);
                    }

                    if ($can_due_date)
                    {
                        $hesk_settings['datepicker']['#new-due-date']['position'] = 'left bottom';
                        ?>
                        <div class="value">
                            <a href="javascript:" onclick="hesk_toggleLayerDisplay('modifyduedate')" class="showme" id="toggleDP">
                                <?php echo $due_date; ?>
                            </a>
                            <div id="modifyduedate" style="display:none">
                                <form class="form" method="post" action="admin_ticket.php">
                                    <section class="param calendar">
                                        <div class="calendar--button" id="due-date-button">
                                            <!--
                                            <button type="button">
                                                <svg class="icon icon-calendar">
                                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-calendar"></use>
                                                </svg>
                                            </button>
                                            -->
                                            <input name="new-due-date" id="new-due-date"
                                                   data-datepicker-position="left top"
                                                   value="<?php echo $datepicker_due_date; ?>"
                                                   type="text" class="datepicker">
                                        </div>
                                        <div class="calendar--value pt10 pb10" style="<?php echo $datepicker_due_date == '' ? '' : 'display: block'; ?>;">
                                            <span><?php echo $datepicker_due_date; ?></span>
                                            <i class="close">
                                                <svg class="icon icon-close">
                                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                                </svg>
                                            </i>
                                        </div>
                                    </section>
                                    <button style="display: inline-flex; width: auto; height: 40px; padding: 0 16px; margin-bottom: 5px;" class="btn btn-full" type="submit"><?php echo $hesklang['save']; ?></button>
                                    <a class="btn btn--blue-border" href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifyduedate')"><?php echo $hesklang['cancel']; ?></a>
                                    <input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
                                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                                    <input type="hidden" name="action" value="due_date">
                                </form>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="value">
                            <?php echo $due_date; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </section>
        <?php
        // Display previous tickets
        if (!empty($customers) && !empty($customers[0]['email']))
        {
            // How many previous tickets should we show?
            $show_previous_tickets = 5;

            $first_customer = $customers[0];

            // Get recent tickets, ordered by last change
            $res = hesk_dbQuery("SELECT `trackid`, `status`, `subject` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` `tickets` 
                WHERE ".hesk_myCategories()." 
                    AND ".hesk_myOwnership()." 
                    AND `id` <> ".$ticket['id']."
                    AND EXISTS (
                        SELECT 1
                        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
                        WHERE `ticket_id` = `tickets`.`id`
                        AND `customer_id` = ".intval($first_customer['id'])."
                    ) 
                ORDER BY `lastchange` DESC 
                LIMIT " . ($show_previous_tickets+1));
            $past_num = hesk_dbNumRows($res);
            ?>
            <section class="params--block details accordion <?php if ($past_num > 0) echo 'visible'; ?>">
                <h4 class="accordion-title">
                    <span><?php echo $hesklang['previous_tickets']; ?></span>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </h4>
                <div class="accordion-body" <?php if ($past_num > 0) echo 'style="display:block"'; ?>>
                    <?php
                    $i = 0;
                    while ($past_ticket = hesk_dbFetchAssoc($res)) {
                        $i++;
                        if ($i > $show_previous_tickets) {
                            hesk_dbFreeResult($res);
                            break;
                        }
                        ?>
                        <div>
                            <?php if (isset($hesk_settings['statuses'][$past_ticket['status']]['class'])): ?>
                                <span class="dot bg-<?php echo $hesk_settings['statuses'][$past_ticket['status']]['class']; ?>" title="<?php echo $hesk_settings['statuses'][$past_ticket['status']]['name']; ?>"></span>
                            <?php else: ?>
                                <span class="dot" style="background-color:<?php echo $hesk_settings['statuses'][$past_ticket['status']]['color']; ?>" title="<?php echo $hesk_settings['statuses'][$past_ticket['status']]['name']; ?>"></span>
                            <?php endif; ?>
                            <a href="admin_ticket.php?track=<?php echo $past_ticket['trackid']; ?>&amp;Refresh=<?php echo rand(10000,99999); ?>"><?php echo $past_ticket['subject']; ?></a>
                        </div>
                        <?php
                    }

                    if ($past_num > 0 && $i > $show_previous_tickets) {
                        echo '<br><a href="find_tickets.php?q='.urlencode($first_customer['email']).'&amp;what=email&amp;s_my=1&amp;s_ot=1&amp;s_un=1">'.$hesklang['all_previous'].'</a>';
                    } elseif ($past_num == 0) {
                        echo sprintf($hesklang['no_previous'], hesk_htmlspecialchars($first_customer['email']));
                    }
                    ?>
                </div>
            </section>
            <?php
        }
        // Display linked tickets

        if (count($customers)) {
            $result = getLinkedTickets($customers, $ticket);
            $linked_num = $result['linked_num'];
            $res = $result['res'];
            $show_linked_tickets = $result['show_linked_tickets'];
            ?>
            <section class="params--block details accordion <?php if ($linked_num > 0) echo 'visible'; ?>">
                <h4 class="accordion-title">
                    <span><?php echo $hesklang['linked_tickets']; ?></span>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </h4>
                <div class="accordion-body" <?php if ($linked_num > 0) echo 'style="display:block"'; ?>>
                    <div class="custom_ajax_msg"></div>
                    <div class="linked_html_view mb-10">
                        <?php
                            // get html view of linked ticket
                            echo getLinkedHtml($customers, $ticket, $can_link_tickets);
                        ?>
                    </div>
                    <?php 
                        if ($can_link_tickets) {
                    ?>        
                        <div class="show_link_a_ticket">
                            <a href="javascript:;" class="href_show_linked"><?php echo $hesklang['link_a_ticket'];?></a>
                        </div>
                        <div class="show_linked_form d_hide">
                            <form method="post" class="form" action="admin_ticket.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo rand(10000,99999); ?>" name="linked_ticket" id="linked_ticket">
                                <div class="form-group">
                                    <label for="ticket_track_id">
                                        <?php echo $hesklang['trackID']; ?>: <span class="important">*</span>
                                    </label>
                                    <input type="text" name="ticket_track_id" class="form-control" id="ticket_track_id" maxlength="100" value="">
                                </div>
                                <input type="hidden" name="token" id="linked_token" value="<?php hesk_token_echo(); ?>">
                                <input type="hidden" name="action_type" value="linked_ticket">
                                <div class="d-inline-flex">
                                    <button class="btn btn-full linked" type="button" ripple="ripple"><?php echo $hesklang['link_ticket']; ?></button>
                                    <button class="btn btn--blue-border cancel ml-10" type="button" ripple="ripple"><?php echo $hesklang['cancel']; ?></button>
                                </div>    
                            </form>
                        </div>
                    <?php
                        }
                    ?>

                </div>
            </section>
            <?php
        } // END if count($customers)

        /* Display ticket history */
        if (strlen($ticket['history']))
        {
            $history_pieces = explode('</li>', $ticket['history'], -1);

            ?>
            <section class="params--block history accordion">
                <h4 class="accordion-title">
                    <span><?php echo $hesklang['thist']; ?></span>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </h4>
                <div class="accordion-body history_html_view">
                    <?php
                        // get ticket history view
                        echo getTicketHistory($history_pieces);
                    ?>
                </div>
            </section>
            <?php
        }
        ?>
    </div>
</div>

<a href="#" class="back-to-top"><?php echo $hesklang['btt']; ?></a>
<div id="loading-overlay" class="loading-overlay">
    <div id="loading-message" class="loading-message">
        <div class="spinner"></div>
        <p><?php echo $hesklang['sending_wait']; ?></p>
    </div>
</div>
<?php
/* Clear unneeded session variables */
hesk_cleanSessionVars('ticket_message');
hesk_cleanSessionVars('time_worked');
hesk_cleanSessionVars('note_message');
hesk_cleanSessionVars('ar_attachments');
hesk_cleanSessionVars('note_attachments');

$hesk_settings['print_status_select_box_jquery'] = true;

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

	/* List attachments */
    $att_ids = array();
	$att=explode(',',substr($attachments, 0, -1));
    echo '<div class="block--uploads" style="display: block;">';
	foreach ($att as $myatt)
	{
		list($att_id, $att_name) = explode('#', $myatt);
        $att_ids[] = $att_id;

        /* Can edit and delete tickets? */
        if ($can_edit && $can_delete)
        {
        	echo '<a class="tooltip" data-ztt_vertical_offset="0" style="margin-right: 8px;" title="'.$hesklang['dela'].'" href="admin_ticket.php?delatt='.$att_id.'&amp;reply='.$reply.'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');">
        	    <svg class="icon icon-delete" style="width: 16px; height: 16px; vertical-align: text-bottom;">
                    <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
                </svg>
            </a> &raquo;';
        }

		echo '
		<a title="'.$hesklang['dnl'].' '.$att_name.'" href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">
            <svg class="icon icon-attach" style="width: 16px; height: 16px; margin-right: 0px; vertical-align: text-bottom;">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-attach"></use>
            </svg>
        </a>
		<a class="underline" title="'.$hesklang['dnl'].' '.$att_name.'" href="download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">'.$att_name.'</a><br />
        ';
	}

    if (count($att_ids) > 0 && class_exists('ZipArchive')) {
        $div_id = "d" . mt_rand(100000,999999);
        echo '<p id="'.$div_id.'"><a class="underline" title="'.$hesklang['download_all'].'" href="../download_all.php?att_id='.implode(',', $att_ids).'&amp;track='.$trackingID.'" onclick="document.getElementById(\''.$div_id.'\').innerHTML=\''.hesk_makeJsString($hesklang['download_prep']).'\'">'.$hesklang['download_all'].'</a></p>';
    }

    echo '</div>';

    return true;
} // End hesk_listAttachments()


function hesk_getAdminButtons($isReply=0,$white=1)
{
	global $hesk_settings, $hesklang, $ticket, $reply, $trackingID, $can_edit, $can_archive, $can_delete, $can_resolve, $can_privacy, $can_export;

	$buttons = array();

    // Edit
    if ($can_edit)
    {
        $tmp = $isReply ? '&amp;reply='.$reply['id'] : '';
        if ($isReply) {
            $buttons['more']['edit'] = '
        <a id="editreply'.$reply['id'].'" href="edit_post.php?track='.$trackingID.$tmp.'" title="'.$hesklang['btn_edit'].'" style="margin-right: 15px">
            <svg class="icon icon-edit-ticket">
                <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-edit-ticket"></use>
            </svg>
            '.$hesklang['btn_edit'].'
        </a>';
        } else {
            $buttons[] = '
        <a id="editticket" href="edit_post.php?track='.$trackingID.$tmp.'" title="'.$hesklang['btn_edit'].'">
            <svg class="icon icon-edit-ticket">
                <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-edit-ticket"></use>
            </svg>
            '.$hesklang['btn_edit'].'
        </a>';
        }

    }


    if (!$isReply) {
        // Print ticket button
        $buttons[] = '
        <a href="print.php?track='.$trackingID.'" title="'.$hesklang['btn_print'].'" target="_blank">
            <svg class="icon icon-print">
                <use xlink:href="' . HESK_PATH .'img/sprite.svg#icon-print"></use>
            </svg>
            '.$hesklang['btn_print'].'
        </a>';
    }


    // Lock ticket button
	if (!$isReply && $can_resolve) {
		if ($ticket['locked']) {
			$des = $hesklang['tul'] . ' - ' . $hesklang['isloc'];
            $buttons['more'][] = '
            <a id="unlock" href="lock.php?track='.$trackingID.'&amp;locked=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$des.'">
                <svg class="icon icon-lock">
                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-lock"></use>
                </svg> 
                '.$hesklang['btn_unlock'].'
            </a>';
		} else {
			$des = $hesklang['tlo'] . ' - ' . $hesklang['isloc'];
            $buttons['more'][] = '
            <a id="lock" href="lock.php?track='.$trackingID.'&amp;locked=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$des.'">
                <svg class="icon icon-lock">
                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-lock"></use>
                </svg>  
                '.$hesklang['btn_lock'].'
            </a>';
		}
	}

	// Tag ticket button
	if (!$isReply && $can_archive) {
		if ($ticket['archive']) {
        	$buttons['more'][] = '
        	<a id="untag" href="archive.php?track='.$trackingID.'&amp;archived=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['remove_archive'].'">
        	    <svg class="icon icon-tag">
                    <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-tag"></use>
                </svg>
                '.$hesklang['btn_untag'].'
            </a>';
		} else {
        	$buttons['more'][] = '
        	<a id="tag" href="archive.php?track='.$trackingID.'&amp;archived=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['add_archive'].'">
        	    <svg class="icon icon-tag">
                    <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-tag"></use>
                </svg>
                '.$hesklang['btn_tag'].'
            </a>';
		}
	}

    // Bookmark ticket button
    if (!$isReply) {
        if (empty($ticket['is_bookmark'])) {
            $buttons['more'][] = '
            <a id="add-bookmark" href="admin_ticket.php?track='.$trackingID.'&amp;bm_add=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['bookmarks_add'].'">
                <svg class="icon icon-pin">
                    <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-pin"></use>
                </svg>
                '.$hesklang['bookmarks_add'].'
            </a>';
        } else {
            $buttons['more'][] = '
            <a id="remove-bookmark" href="admin_ticket.php?track='.$trackingID.'&amp;bm_add=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['bookmarks_remove'].'">
                <svg class="icon icon-pin is-bookmark">
                    <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-pin"></use>
                </svg>
                '.$hesklang['bookmarks_remove'].'
            </a>';
        }
    }

	// Resend email notification button
    if (!$ticket['anonymized']) {
        $buttons['more'][] = '
        <a id="resendemail" href="resend_notification.php?track='.$trackingID.'&amp;reply='.($isReply && isset($reply['id']) ? intval($reply['id']) : 0).'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['btn_resend'].'">
            <svg class="icon icon-mail-small">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-mail-small"></use>
            </svg>
            '.$hesklang['btn_resend'].'
        </a>';
    }

    // Resend assigned staff email notification
    if ($ticket['owner']) {
        $buttons['more'][] = '
        <a id="remindstaff" href="resend_notification.php?track='.$trackingID.'&amp;remind=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['remind_assigned'].'">
            <svg class="icon icon-notification">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-notification"></use>
            </svg>
            '.$hesklang['remind_assigned'].'
        </a>';
    }

	// Import to knowledgebase button
    if (!$isReply && $hesk_settings['kb_enable'] && hesk_checkPermission('can_man_kb',0) && !$ticket['anonymized'])
	{
		$buttons['more'][] = '
		<a id="addtoknow" href="manage_knowledgebase.php?a=import_article&amp;track='.$trackingID.'" title="'.$hesklang['import_kb'].'">
		    <svg class="icon icon-knowledge">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-knowledge"></use>
            </svg>
		    '.$hesklang['btn_import_kb'].'
        </a>';
	}

    // Export ticket
    if (!$isReply && $can_export && !$ticket['anonymized'])
    {
        $buttons['more'][] = '
        <a id="exportticket" href="export_ticket.php?track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['btn_export'].'">
            <svg class="icon icon-export">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-export"></use>
            </svg> 
            '.$hesklang['btn_export'].'
        </a>';
    }

    // Anonymize ticket
    if (!$isReply && $can_privacy)
    {
        $modal_id = hesk_generate_old_delete_modal($hesklang['confirm_anony'], $hesklang['privacy_anon_info'], 'anonymize_ticket.php?track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0), $hesklang['confirm']);
		$buttons['more'][] = '
		<a id="anonymizeticket" href="javascript:" title="'.$hesklang['confirm_anony'].'" data-modal="[data-modal-id=\''.$modal_id.'\']">
		    <svg class="icon icon-anonymize">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-anonymize"></use>
            </svg>
             '.$hesklang['btn_anony'].'
        </a>';
    }

	// Delete ticket or reply
	if ($can_delete)
	{
		if ($isReply)
		{
			$url = 'admin_ticket.php';
			$tmp = 'delete_post='.$reply['id'];
			$txt = $hesklang['btn_delr'];
            $modal_text = $hesklang['confirm_delete_reply'];
		}
		else
		{
			$url = 'delete_tickets.php';
			$tmp = 'delete_ticket=1';
			$txt = $hesklang['btn_delt'];
            $modal_text = $hesklang['confirm_delete_ticket'];
		}
        $modal_id = hesk_generate_old_delete_modal($hesklang['confirm_deletion'], $modal_text, $url.'?track='.$trackingID.'&amp;'.$tmp.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0));
		$buttons['more'][] = '
		<a id="deleteticket" href="javascript:" title="'.$txt.'" data-modal="[data-modal-id=\''.$modal_id.'\']">
		    <svg class="icon icon-delete">
                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-delete"></use>
            </svg>
		    '.$txt.'
        </a>';
	}

    // Format and return the HTML for buttons
    $button_code = '';

    foreach ($buttons as $button) {
        if (is_array($button)) {
            $more_class = $isReply ? 'more ' : '';
            $label = '
            <label>
                <span>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </span>
            </label>
            ';

            if ($isReply) {
                $label = '
                <label>
                    <span>' . $hesklang['btn_more'] . '</span>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </label>';
            }

            $button_code .= '<div class="'.$more_class.'dropdown right out-close">';
            if (isset($button['edit']))
            {
                $button_code .= $button['edit'];
                unset($button['edit']);
            }

            $button_code .= $label.'<ul class="dropdown-list">';

            foreach ($button as $sub_button) {
                $button_code .= '<li>'.$sub_button.'</li>';
            }

            $button_code .= '</ul></div>';
        } else {
            $button_code .= $button;
        }
    }

    $button_code .= '';

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

    <div class="main__content categories">
        <div class="table-wrap">
            <?php
            /* This will handle error, success and notice messages */
            hesk_handle_messages();
            ?>
            <h3><?php echo $hesklang['view_existing']; ?></h3>
            <form action="admin_ticket.php" method="get" class="form">
                <div class="form-group">
                    <label for="find_ticket_track"><?php echo $hesklang['ticket_trackID']; ?></label>
                    <input id="find_ticket_track" class="form-control" type="text" name="track" maxlength="20" value="<?php echo $trackingID; ?>">
                </div>
                <div class="form-group">
                    <input type="submit" value="<?php echo $hesklang['view_ticket']; ?>" class="btn btn-full">
                    <input type="hidden" name="Refresh" value="<?php echo rand(10000,99999); ?>">
                </div>
            </form>
        </div>
    </div>

	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End print_form()


function hesk_printTicketReplies() {
	global $hesklang, $hesk_settings, $result, $reply, $ticket;

	$i = $hesk_settings['new_top'] ? 0 : 1;

	if ($reply === false)
	{
		return $i;
	}

    $replies = array();
    $collapsed_replies = array();
    $displayed_replies = array();
	$last_staff_reply_index = -1;
	$i = 0;
	while ($reply = hesk_dbFetchAssoc($result)) {
        if ($reply['staffid']) {
            $reply['name'] = $reply['staff_name'] === null ?
                $hesklang['staff_deleted'] :
                $reply['staff_name'];
        } else {
            $reply['name'] = $reply['customer_name'] === null ?
                $hesklang['anon_name'] :
                $reply['customer_name'];
        }

	    $replies[] = $reply;
        if ($reply['staffid'] && ( ! $hesk_settings['new_top'] || $last_staff_reply_index === -1)) {
	        $last_staff_reply_index = $i;
        }
	    $i++;
    }

    // Hide ticket replies?
    $i = 0;
    foreach ($replies as $reply) {
        // Show the last staff reply and any subsequent customer replies
        if ($hesk_settings['hide_replies'] == -1) {
            if ($hesk_settings['new_top']) {
                if ($i <= $last_staff_reply_index) {
                    $displayed_replies[] = $reply;
                } else {
                    $collapsed_replies[] = $reply;
                }
            } else {
                if ($i < $last_staff_reply_index) {
                    $collapsed_replies[] = $reply;
                } else {
                    $displayed_replies[] = $reply;
                }
            }
        // Hide all replies except the last X
        } elseif ($hesk_settings['hide_replies'] > 0) {
            if ($hesk_settings['new_top']) {
                if ($i >= $hesk_settings['hide_replies']) {
                    $collapsed_replies[] = $reply;
                } else {
                    $displayed_replies[] = $reply;
                }
            } else {
                if ($i < ($ticket['replies'] - $hesk_settings['hide_replies'])) {
                    $collapsed_replies[] = $reply;
                } else {
                    $displayed_replies[] = $reply;
                }
            }
        // Never, always show all replies
        } else {
            $displayed_replies[] = $reply;
        }
        $i++;
    }

    $start_previous_replies = true;
    for ($j = 0; $j < count($collapsed_replies) && $hesk_settings['new_top'] == 0; $j++) {
        $reply = $collapsed_replies[$j];
        if ($start_previous_replies):
            $start_previous_replies = false;
            ?>
            <section class="ticket__replies">
                <div class="ticket__replies_link">
                    <span><?php echo $hesklang['show_previous_replies']; ?></span>
                    <b><?php echo count($collapsed_replies); ?></b>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </div>
                <div class="ticket__replies_list">
            <?php
        endif;
        ?>
        <article class="ticket__body_block <?php echo $reply['staffid'] ? 'response' : ''; ?>">
            <div class="block--head">
                <div class="contact">
                    <?php echo $hesklang['reply_by']; ?>
                    <b><?php echo $reply['name']; ?></b>
                    &raquo;
                    <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($reply['dt'])) ; ?>" title="<?php echo hesk_date($reply['dt'], true); ?>"><?php echo hesk_date($reply['dt'], true); ?></time>
                </div>
                <?php echo hesk_getAdminButtons(1, $i); ?>
            </div>
            <div class="block--description browser-default">
                <p><?php echo $reply['message_html']; ?></p>
            </div>
            <?php

            /* Attachments */
            hesk_listAttachments($reply['attachments'], $reply['id'], $i);

            /* Staff rating */
            if ($hesk_settings['rating'] && $reply['staffid']) {
                if ($reply['rating'] == 1) {
                    echo '<p class="rate">' . $hesklang['rnh'] . '</p>';
                } elseif ($reply['rating'] == 5) {
                    echo '<p class="rate">' . $hesklang['rh'] . '</p>';
                }
            }

            /* Show "unread reply" message? */
            if ($reply['staffid'] && !$reply['read']) {
                echo '<p class="rate">' . $hesklang['unread'] . '</p>';
            }

            ?>
        </article>
        <?php
        if (!$start_previous_replies && $j == count($collapsed_replies) - 1) {
            echo '</div>
            </section>';
        }
    }

    for ($j = 0; $j < count($displayed_replies); $j++) {
        $reply = $displayed_replies[$j];
        ?>
        <article class="ticket__body_block <?php echo $reply['staffid'] ? 'response' : ''; ?>">
            <div class="block--head">
                <div class="contact">
                    <?php echo $hesklang['reply_by']; ?>
                    <b><?php echo $reply['name']; ?></b>
                    &raquo;
                    <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($reply['dt'])) ; ?>" title="<?php echo hesk_date($reply['dt'], true); ?>"><?php echo hesk_date($reply['dt'], true); ?></time>
                </div>
                <?php echo hesk_getAdminButtons(1,$i); ?>
            </div>
            <div class="block--description browser-default">
                <p><?php echo $reply['message_html']; ?></p>
            </div>
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
        </article>
        <?php
    }

    $start_previous_replies = true;
    for ($j = 0; $j < count($collapsed_replies) && $hesk_settings['new_top']; $j++) {
        $reply = $collapsed_replies[$j];
        if ($start_previous_replies):
            $start_previous_replies = false;
            ?>
            <section class="ticket__replies">
                <div class="ticket__replies_link">
                    <span><?php echo $hesklang['show_previous_replies']; ?></span>
                    <b><?php echo count($collapsed_replies); ?></b>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </div>
                <div class="ticket__replies_list">
            <?php
        endif;
        ?>
        <article class="ticket__body_block <?php echo $reply['staffid'] ? 'response' : ''; ?>">
        <div class="block--head">
            <div class="contact">
                <?php echo $hesklang['reply_by']; ?>
                <b><?php echo $reply['name']; ?></b>
                &raquo;
                <time class="timeago tooltip" datetime="<?php echo date("c", strtotime($reply['dt'])) ; ?>" title="<?php echo hesk_date($reply['dt'], true); ?>"><?php echo hesk_date($reply['dt'], true); ?></time>
            </div>
            <?php echo hesk_getAdminButtons(1, $i); ?>
        </div>
        <div class="block--description browser-default">
            <p><?php echo $reply['message_html']; ?></p>
        </div>
        <?php

        /* Attachments */
        hesk_listAttachments($reply['attachments'], $reply['id'], $i);

        /* Staff rating */
        if ($hesk_settings['rating'] && $reply['staffid']) {
            if ($reply['rating'] == 1) {
                echo '<p class="rate">' . $hesklang['rnh'] . '</p>';
            } elseif ($reply['rating'] == 5) {
                echo '<p class="rate">' . $hesklang['rh'] . '</p>';
            }
        }

        /* Show "unread reply" message? */
        if ($reply['staffid'] && !$reply['read']) {
            echo '<p class="rate">' . $hesklang['unread'] . '</p>';
        }

        ?>
        </article>
        <?php
        if (!$start_previous_replies && $j == count($collapsed_replies) - 1) {
            echo '</div>
            </section>';
        }
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
<article class="ticket__body_block">
    <a name="reply-form"></a>
    <form method="post" class="form" action="admin_reply_ticket.php" enctype="multipart/form-data" name="form1"
        onsubmit="
        <?php if ($hesk_settings['time_worked']): ?>force_stop();<?php endif; ?>
        <?php if ($hesk_settings['staff_ticket_formatting'] != 2): ?>clearTimeout(typingTimer);<?php endif; ?>
        <?php if ($hesk_settings['submitting_wait']): ?>hesk_showLoadingMessage('recaptcha-submit');<?php endif; ?>
        return true;"
        >
        <?php
        /* Ticket assigned to someone else? */
        if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && isset($admins[$ticket['owner']])) {
            hesk_show_notice($hesklang['nyt'] . ' ' . $admins[$ticket['owner']]);
        }

        /* Ticket locked? */
        if ($ticket['locked']) {
            hesk_show_notice($hesklang['tislock']);
        }

        if ($hesk_settings['time_worked'] && strlen($can_options)) {
            ?>
            <div class="time-and-canned">
            <?php
        }
        // Track time worked?
        if ($hesk_settings['time_worked']) {
            ?>
            <section class="block--timer">
                <span>
                    <label for="time_worked">
                        <?php echo $hesklang['ts']; ?>:
                    </label>
                </span>
                <div class="form-group short" style="margin-left: 8px; margin-bottom: 0">
                    <input type="text" class="form-control short" name="time_worked" id="time_worked" size="10" value="<?php echo ( isset($_SESSION['time_worked']) ? hesk_getTime($_SESSION['time_worked']) : '00:00:00'); ?>" autocomplete="off">
                </div>

                <a href="javascript:" class="tooltip" id="pause_btn" title="<?php echo $hesklang['start']; ?>">
                    <svg class="icon icon-pause">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-pause"></use>
                    </svg>
                </a>
                <a href="javascript:" class="tooltip" id="reset_btn" title="<?php echo $hesklang['reset']; ?>">
                    <svg class="icon icon-refresh">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-refresh"></use>
                    </svg>
                </a>
                <script>
                    $('#pause_btn').click(function() {
                        ss();
                        updatePauseButton();
                    });

                    $('#reset_btn').click(function() {
                        $('#pause_btn').find('svg').addClass('playing');
                        r();
                    });

                    function updatePauseButton() {
                        if (!timer_running()) {
                            $('#pause_btn').find('svg').addClass('playing');
                        } else {
                            $('#pause_btn').find('svg').removeClass('playing');
                        }
                    }

                    $(document).ready(function() {
                        setTimeout(updatePauseButton, 1000);
                    });

                    <?php if ($hesk_settings['new_top']): ?>
                    $('#notesformTop').submit(function() {
                         $('#time_worked_notesTop').val($('#time_worked').val());
                    });
                    <?php endif; ?>
                </script>
            </section>
            <?php
        }

        /* Do we have any canned responses? */
        if (strlen($can_options))
        {
            ?>
        <section class="block--timer canned-options">
            <div class="canned-header">
                <?php echo $hesklang['saved_replies']; ?>
            </div>
            <div class="options" style="text-align: left">
                <div>
                    <div class="radio-custom">
                        <input type="radio" name="mode" id="modeadd"
                               value="1" checked>
                        <label for="modeadd">
                            <?php echo $hesklang['madd']; ?>
                        </label>
                    </div>
                    <div class="radio-custom">
                        <input type="radio" name="mode" id="moderep"
                               value="0">
                        <label for="moderep">
                            <?php echo $hesklang['mrep']; ?>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo $hesklang['select_saved']; ?></label>
                        <select name="saved_replies" id="saved_replies" onchange="setMessage(this.value)">
                            <option value="0"> - <?php echo $hesklang['select_empty']; ?> - </option>
                            <?php echo $can_options; ?>
                        </select>
                        <script>
                            $('#saved_replies').selectize();
                        </script>
                </div>
            </div>
        </section>
            <?php
        }

        if ($hesk_settings['time_worked'] && strlen($can_options)) {
        ?>
            </div>
                <?php
                }
        ?>

            <div class="block--message" id="message-block">
                <textarea name="message" id="message" placeholder="<?php echo $hesklang['type_your_message']; ?>"><?php

                    // Do we have any message stored in session?
                    if ( isset($_SESSION['ticket_message']) )
                    {
                        echo stripslashes( hesk_input( $_SESSION['ticket_message'] ) );
                    }
                    // Perhaps a message stored in reply drafts?
                    else
                    {
                        $db_column = $hesk_settings['staff_ticket_formatting'] == 2 ? 'message_html' : 'message';
                        $res = hesk_dbQuery("SELECT `{$db_column}` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".intval($_SESSION['id'])." AND `ticket`=".intval($ticket['id'])." LIMIT 1");
                        if (hesk_dbNumRows($res) == 1)
                        {
                            echo $db_column === 'message_html' ? htmlspecialchars(hesk_dbResult($res)) : hesk_dbResult($res);
                        }
                    }

                ?></textarea>
            </div>

        <?php
        if ($hesk_settings['staff_ticket_formatting'] == 2) {
            hesk_tinymce_init('#message', 'hesk_save_draft_async');
        }

        /* attachments */
        if ($hesk_settings['attachments']['use'])
        {
            require_once(HESK_PATH . 'inc/attachments.inc.php');
            ?>
            <div class="attachments">
                <div class="block--attach">
                    <svg class="icon icon-attach">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-attach"></use>
                    </svg>
                    <div>
                        <?php echo $hesklang['attachments'] . ':<br>'; ?>
                    </div>
                </div>
                <?php
                build_dropzone_markup(true);
                display_dropzone_field(HESK_PATH . 'upload_attachment.php', true);
                dropzone_display_existing_files(hesk_SESSION_array('ar_attachments'));
                ?>
            </div>
        <?php
        }
        ?>

        <section class="block--checkboxs">
            <?php
            if ($ticket['owner'] != $_SESSION['id'] && $can_assign_self)
            {
                echo '<div class="checkbox-custom">';
                if (empty($ticket['owner']))
                {
                    echo '<input type="checkbox" id="assign_self" name="assign_self" value="1" autocomplete="off" checked="checked">';
                }
                else
                {
                    echo '<input type="checkbox" id="assign_self" name="assign_self" value="1" autocomplete="off">';
                }
                echo '<label for="assign_self">'.$hesklang['asss2'].'</label>';
                echo '</div>';
            }
            ?>

            <div class="checkbox-custom">
                <input type="checkbox" id="signature" name="signature" value="1" autocomplete="off" checked="checked">
                <label for="signature">
                    <?php echo $hesklang['attach_sign']; ?>
                    (<a class="link" href="profile.php"><?php echo $hesklang['profile_settings']; ?></a>)
                </label>
            </div>

            <div class="checkbox-custom">
                <input type="checkbox" id="set_priority" name="set_priority" autocomplete="off" value="1">
                <label for="set_priority"><?php echo $hesklang['change_priority']; ?></label>

                <div class="dropdown-select center out-close priority select-priority" data-value="low">
                    <select id="replypriority" name="priority">
                        <?php echo implode('',$options); ?>
                    </select>
                </div>
            </div>
            <div class="checkbox-custom">
                <input type="checkbox" id="no_notify" name="no_notify" value="1" autocomplete="off" <?php echo $_SESSION['notify_customer_reply'] ? '' : 'checked'; ?>>
                <label for="no_notify"><?php echo $hesklang['dsen']; ?></label>
            </div>
        </section>
        <section class="block--submit">
            <input type="hidden" name="orig_id" value="<?php echo $ticket['id']; ?>">
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
            <input class="btn btn-full" ripple="ripple" type="submit" value="<?php echo $hesklang['submit_reply']; ?>" id="recaptcha-submit">
            &nbsp;
            <input class="btn btn-border" ripple="ripple" type="submit" name="save_reply" value="<?php echo $hesklang['sacl']; ?>">
            <?php
            // If ticket is not locked, show additional submit options
            if ( ! $ticket['locked']) {
                ?>
                <input type="hidden" id="submit_as_name" value="1" name="">
                <div class="submit-us dropdown-select out-close" data-value="" id="submit-as-div">
                    <select onchange="
                        document.getElementById('submit_as_name').name = this.value;
                        <?php if ($hesk_settings['time_worked']): ?>force_stop();<?php endif; ?>
                        <?php if ($hesk_settings['staff_ticket_formatting'] != 2): ?>clearTimeout(typingTimer);<?php endif; ?>
                        <?php if ($hesk_settings['submitting_wait']): ?>hesk_showLoadingMessage('submit-as-div');<?php endif; ?>
                        this.form.submit()
                        ">
                        <option value="" selected><?php echo rtrim($hesklang['submit_as'], ':'); ?></option>
                        <option value="submit_as_customer"><?php echo $hesklang['sasc']; ?></option>
                        <?php
                        $echo_options = '';
                        foreach ($hesk_settings['statuses'] as $k => $v)
                        {
                            if ($k == 3)
                            {
                                if ($can_resolve)
                                {
                                    echo '<option value="submit_as-'.$k.'">'.$hesklang['submit_as'].' '.$v['name'].'</option>';
                                }
                            }
                            else
                            {
                                $echo_options .= '<option value="submit_as-'.$k.'">'.$hesklang['submit_as'].' '.$v['name'].'</option>';
                            }
                        }
                        echo $echo_options;
                        ?>
                    </select>
                </div>
                <?php
            }
            ?>

        </section>
    </form>
</article>

<script>
var draft_message = '';
var previous_draft_message = '';

function debug_to_console(msg) {
    <?php if ($hesk_settings['debug_mode']): ?>
    console.log(msg);
    <?php endif; ?>
}

function hesk_save_draft_async() {
    // Get the new message from the rich text editor or textbox
    <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
        draft_message = tinymce.get("message").getContent('');
    <?php else: ?>
        draft_message = $('#message').val();
    <?php endif; ?>

    // Only proceed if the message has changed
    if (draft_message == previous_draft_message) {
        debug_to_console("Message did not change");
        return true;
    }

    $.ajax({
        type: "POST",
        url: "save_ticket_draft_async.php",
        data:{orig_id: <?php echo $ticket['id']; ?>, message: draft_message},
        success: function(result, status){
            previous_draft_message = draft_message;
            debug_to_console("Request result: " + result + " " + status);
        },
        error: function(xhr, status, error) {
            debug_to_console("Ajax Error " + xhr + " " + status + " " + error)
        }
    });
}
/*Linked Button Click*/
$('body').on('click','.linked',function(){
    var action = $('#linked_ticket').attr('action');
    var ticket_track_id = $('#ticket_track_id').val();

    $("#linked_ticket").removeClass("invalid");
        $("#ticket_track_id").removeClass("isError");
    if(ticket_track_id == ""){
        $("#linked_ticket").addClass("invalid");
        $("#ticket_track_id").addClass("isError");
    }

    var data = {
        'action_type':'linked_ticket',
        'ticket_track_id': $('#ticket_track_id').val(),
        'token': $('#linked_token').val()
    }
    $.ajax({
        type: 'POST',
        url: action,
        data: data,
        cache: false,
        success: function(data){
            var result = JSON.parse(data);
            $('.custom_ajax_msg').html('');
            $('.custom_ajax_msg').html(result.message);
            if(result.status=='SUCCESS'){
                $('#ticket_track_id').val('');
                $('.linked_html_view').html('');
                $('.linked_html_view').html(result.linked_html);
                $('.history_html_view').html('');
                $('.history_html_view').html(result.history_html);
            }
        }
    });
});

/*Unlinked Button Click*/
$('body').on('click','.unlink',function(){
    var that = $(this);
    var action = that.attr('data-action');
    var ticket1 = that.attr('data-ticket1');
    var ticket2 = that.attr('data-ticket2');
    var trackid = that.attr('data-trackid');


    $("#linked_ticket").removeClass("invalid");
    $("#ticket_track_id").removeClass("isError");
    if(ticket_track_id == ""){
        $("#linked_ticket").addClass("invalid");
        $("#ticket_track_id").addClass("isError");
    }

    var data = {
        'action_type':'unlink_ticket',
        'ticket1': ticket1,
        'ticket2': ticket2,
        'trackid':trackid
    }
    $.ajax({
        type: 'POST',
        url: action,
        data: data,
        cache: false,
        success: function(data){
            var result = JSON.parse(data);
            $('.custom_ajax_msg').html('');
            $('.custom_ajax_msg').html(result.message);
            if(result.status=='SUCCESS'){
                that.parent().remove();
                $('.linked_html_view').html('');
                $('.linked_html_view').html(result.linked_html);
                $('.history_html_view').html('');
                $('.history_html_view').html(result.history_html);
            }
        }
    });
});

$('body').on('click','.href_show_linked',function(){
    $('.custom_ajax_msg').html('');
    $('.show_link_a_ticket').removeClass('d_show').addClass('d_hide')
    $('.show_linked_form').removeClass('d_hide').addClass('d_show');
    $("#linked_ticket").removeClass("invalid");
    $("#ticket_track_id").removeClass("isError");
    $('#ticket_track_id').val('');
});

$('body').on('click','.cancel',function(){
    $('.show_link_a_ticket').removeClass('d_hide').addClass('d_show')
    $('.show_linked_form').removeClass('d_show').addClass('d_hide');
});
<?php if ($hesk_settings['staff_ticket_formatting'] != 2): ?>
var typingTimer;
var doneTypingInterval = 3000;

$(document).ready(function() {
    $('#message').on('input', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(hesk_save_draft_async, doneTypingInterval);
    });
});
<?php endif; ?>
</script>

<!-- END REPLY FORM -->
<?php
} // End hesk_printReplyForm()


function hesk_printCanned()
{
	global $hesklang, $hesk_settings, $can_reply, $ticket, $admins, $category, $customers, $requester, $followers, $customer_emails;

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
	while ($mysaved = hesk_dbFetchAssoc($res))
	{
        $can_options .= '<option value="' . $mysaved['id'] . '">' . $mysaved['title']. "</option>\n";

        $message_text = $hesk_settings['staff_ticket_formatting'] == 2 ? $mysaved['message_html'] : $mysaved['message'];

        echo 'myMsgTxt['.$mysaved['id'].']=\''.preg_replace("/\r?\n|\r/","\\r\\n' + \r\n'", addslashes($message_text))."';\n";
	}

	?>

	function setMessage(msgid)
    {
		var myMsg=myMsgTxt[msgid];

        if (myMsg == '')
        {
            if (document.form1.mode[1].checked)
            {
            <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                tinymce.get("message").setContent('');
            <?php else: ?>
                document.getElementById('message').value = '';
            <?php endif; ?>
                $('.ticket .block--message .placeholder').click();
                return true;
            }
            return true;
        }

        <?php
        $formatted_followers = [];
        $formatted_follower_names = [];
        $formatted_follower_emails = [];
        foreach ($followers as $follower) {
            $formatted_followers[] = hesk_output_customer_name_and_email($follower);
            if ($follower['name'] !== null && $follower['name'] !== '') {
                $formatted_follower_names[] = $follower['name'];
            }
            if ($follower['email'] !== null && $follower['email'] !== '') {
                $formatted_follower_emails[] = $follower['email'];
            }
        }
        ?>

        // replace plain text
		myMsg = myMsg.replace(/%%HESK_ID%%/g, '<?php echo hesk_jsString($ticket['id']); ?>');
		myMsg = myMsg.replace(/%%HESK_TRACKID%%/g, '<?php echo hesk_jsString($ticket['trackid']); ?>');
		myMsg = myMsg.replace(/%%HESK_TRACK_ID%%/g, '<?php echo hesk_jsString($ticket['trackid']); ?>');
		myMsg = myMsg.replace(/%%HESK_SUBJECT%%/g, '<?php echo hesk_jsString($ticket['subject']); ?>');
		myMsg = myMsg.replace(/%%HESK_REQUESTER%%/g, '<?php echo hesk_jsString(hesk_output_customer_name_and_email($requester)); ?>');
        myMsg = myMsg.replace(/%%HESK_NAME%%/g, '<?php echo hesk_jsString($requester !== null ? $requester['name'] : $hesklang['anon_name']); ?>');
		myMsg = myMsg.replace(/%%HESK_REQUESTER_NAME%%/g, '<?php echo hesk_jsString($requester !== null ? $requester['name'] : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%%HESK_FIRST_NAME%%/g, '<?php echo hesk_jsString($requester !== null ? hesk_full_name_to_first_name($requester['name']) : $hesklang['anon_name']); ?>');
		myMsg = myMsg.replace(/%%HESK_REQUESTER_FIRST_NAME%%/g, '<?php echo hesk_jsString($requester !== null ? hesk_full_name_to_first_name($requester['name']) : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%%HESK_EMAIL%%/g, '<?php echo hesk_jsString($requester !== null ? $requester['email'] : $hesklang['anon_email']); ?>');
		myMsg = myMsg.replace(/%%HESK_REQUESTER_EMAIL%%/g, '<?php echo hesk_jsString($requester !== null ? $requester['email'] : $hesklang['anon_email']); ?>');
        myMsg = myMsg.replace(/%%HESK_FOLLOWERS%%/g, '<?php echo hesk_jsString(implode(', ', $formatted_followers)); ?>');
        myMsg = myMsg.replace(/%%HESK_FOLLOWER_NAMES%%/g, '<?php echo hesk_jsString(implode(', ', $formatted_follower_names)); ?>');
        myMsg = myMsg.replace(/%%HESK_FOLLOWER_EMAILS%%/g, '<?php echo hesk_jsString(implode(', ', $formatted_follower_emails)); ?>');
		myMsg = myMsg.replace(/%%HESK_OWNER%%/g, '<?php echo hesk_jsString( isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : ''); ?>');
        myMsg = myMsg.replace(/%%HESK_CATEGORY%%/g, '<?php echo hesk_jsString( isset($category['name']) ? $category['name'] : ''); ?>');
        myMsg = myMsg.replace(/%%HESK_DUE_DATE%%/g, '<?php echo hesk_jsString(hesk_format_due_date($ticket['due_date'])); ?>');

        // replace URL-encoded text
        myMsg = myMsg.replace(/%25%25HESK_ID%25%25/g, encodeURIComponent('<?php echo hesk_jsString($ticket['id']); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_TRACKID%25%25/g, encodeURIComponent('<?php echo hesk_jsString($ticket['trackid']); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_TRACK_ID%25%25/g, encodeURIComponent('<?php echo hesk_jsString($ticket['trackid']); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_SUBJECT%25%25/g, encodeURIComponent('<?php echo hesk_jsString($ticket['subject']); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_REQUESTER%25%25/g, '<?php echo hesk_jsString(hesk_output_customer_name_and_email($requester)); ?>');
        myMsg = myMsg.replace(/%25%25HESK_REQUESTER_NAME%25%25/g, '<?php echo hesk_jsString($requester !== null ? $requester['name'] : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_NAME%25%25/g, '<?php echo hesk_jsString($requester !== null ? $requester['name'] : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_REQUESTER_FIRST_NAME%25%25/g, '<?php echo hesk_jsString($requester !== null ? hesk_full_name_to_first_name($requester['name']) : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_FIRST_NAME%25%25/g, '<?php echo hesk_jsString($requester !== null ? hesk_full_name_to_first_name($requester['name']) : $hesklang['anon_name']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_REQUESTER_EMAIL%25%25/g, '<?php echo hesk_jsString($requester !== null ? $requester['email'] : $hesklang['anon_email']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_EMAIL%25%25/g, '<?php echo hesk_jsString($requester !== null ? $requester['email'] : $hesklang['anon_email']); ?>');
        myMsg = myMsg.replace(/%25%25HESK_FOLLOWERS%25%25/g, '<?php echo hesk_jsString(implode(', ', $formatted_followers)); ?>');
        myMsg = myMsg.replace(/%25%25HESK_FOLLOWER_NAMES%25%25/g, '<?php echo hesk_jsString(implode(', ', $formatted_follower_names)); ?>');
        myMsg = myMsg.replace(/%25%25HESK_FOLLOWER_EMAILS%25%25/g, '<?php echo hesk_jsString(implode(', ', $formatted_follower_emails)); ?>');
        myMsg = myMsg.replace(/%25%25HESK_OWNER%25%25/g, encodeURIComponent('<?php echo hesk_jsString( isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : ''); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_CATEGORY%25%25/g, encodeURIComponent('<?php echo hesk_jsString( isset($category['name']) ? $category['name'] : ''); ?>'));
        myMsg = myMsg.replace(/%25%25HESK_DUE_DATE%25%25/g, encodeURIComponent('<?php echo hesk_jsString(hesk_format_due_date($ticket['due_date'])); ?>'));

		<?php
        for ($i=1; $i<=100; $i++)
		{
            // replace plain text
        	echo 'myMsg = myMsg.replace(/%%HESK_custom'.$i.'%%/g, \''.hesk_jsString($ticket['custom'.$i]).'\');';

            // replace URL-encoded text
            echo 'myMsg = myMsg.replace(/%25%25HESK_custom'.$i.'%25%25/g, encodeURIComponent(\''.hesk_jsString($ticket['custom'.$i]).'\'));';
		}
		?>

        if (document.getElementById) {
            if (document.getElementById('moderep').checked) {
            <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                tinymce.get("message").setContent('');
                tinymce.get("message").setContent(myMsg);
            <?php else: ?>
                document.getElementById('message-block').innerHTML = '<textarea name="message" id="message" placeholder="<?php echo $hesklang['type_your_message']; ?>">' + myMsg + '</textarea>';
            <?php endif; ?>
            } else {
            <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                var oldMsg = tinymce.get("message").getContent();
                tinymce.get("message").setContent('');
                tinymce.get("message").setContent(oldMsg + myMsg);
            <?php else: ?>
                var oldMsg = escapeHtml(document.getElementById('message').value);
                document.getElementById('message-block').innerHTML = '<textarea name="message" id="message" placeholder="<?php echo $hesklang['type_your_message']; ?>">' + oldMsg + myMsg + '</textarea>';
            <?php endif; ?>
            }
            $('.ticket .block--message .placeholder').click();
	    } else {
            if (document.form1.mode[0].checked) {
                document.form1.message.value = myMsg;
            } else {
                var oldMsg = document.form1.message.value;
                document.form1.message.value = oldMsg + myMsg;
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
