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
define('HESK_PATH','./');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

hesk_load_database_functions();
require(HESK_PATH . 'inc/email_functions.inc.php');
require(HESK_PATH . 'inc/posting_functions.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');

// We only allow POST requests to this file
if ( $_SERVER['REQUEST_METHOD'] != 'POST' )
{
	header('Location: index.php');
	exit();
}

// Check for POST requests larger than what the server can handle
if ( empty($_POST) && ! empty($_SERVER['CONTENT_LENGTH']) )
{
	hesk_error($hesklang['maxpost']);
}

hesk_session_start('CUSTOMER');

// Prevent flooding - multiple replies within a few seconds are probably not valid
if ($hesk_settings['flood'])
{
    if (isset($_SESSION['last_reply_timestamp']) && (time() - $_SESSION['last_reply_timestamp']) < $hesk_settings['flood'])
    {
        hesk_error($hesklang['e_flood']);
    }
    else
    {
        $_SESSION['last_reply_timestamp'] = time();
    }
}

/* A security check */
# hesk_token_check('POST');

$hesk_error_buffer = array();

// Tracking ID
$trackingID  = hesk_cleanID('orig_track') or die($hesklang['int_error'].': No orig_track');

// Email required to view ticket?
if (hesk_isCustomerLoggedIn(false)) {
    $my_email = $_SESSION['customer']['email'];
} else {
    $my_email = hesk_getCustomerEmail();
}

// Setup required session vars
$_SESSION['t_track'] = $trackingID;
$_SESSION['t_email'] = $my_email;

// Get message
$message = hesk_input( hesk_POST('message') );

// If the message was entered, further parse it
if ( strlen($message) )
{
	// Make links clickable
	$message = hesk_makeURL($message);

	// Turn newlines into <br />
	$message = nl2br($message);
}
else
{
	$hesk_error_buffer[] = $hesklang['enter_message'];
}

/* Connect to database */
hesk_dbConnect();

/* Attachments */
$use_legacy_attachments = hesk_POST('use-legacy-attachments', 0);
if ($hesk_settings['attachments']['use'])
{
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

/* Any errors? */
if (count($hesk_error_buffer)!=0)
{
    $_SESSION['ticket_message'] = hesk_POST('message');

	// If this was a reply after re-opening a ticket, force the form at the top
	if ( hesk_POST('reopen') == 1)
	{
		$_SESSION['force_form_top'] = true;
	}

	// Remove any successfully uploaded attachments
	if ($hesk_settings['attachments']['use'])
	{
		if ($use_legacy_attachments) {
			hesk_removeAttachments($attachments);
		} else {
			$_SESSION['r_attachments'] = $attachments;
		}

	}

    $tmp = '';
    foreach ($hesk_error_buffer as $error)
    {
        $tmp .= "<li>$error</li>\n";
    }
    $hesk_error_buffer = $tmp;

    $hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    hesk_process_messages($hesk_error_buffer,'ticket.php');
}

// Check if this IP is temporarily locked out
$res = hesk_dbQuery("SELECT `number` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` WHERE `ip`='".hesk_dbEscape(hesk_getClientIP())."' AND `last_attempt` IS NOT NULL AND DATE_ADD(`last_attempt`, INTERVAL ".intval($hesk_settings['attempt_banmin'])." MINUTE ) > NOW() LIMIT 1");
if (hesk_dbNumRows($res) == 1)
{
	if (hesk_dbResult($res) >= $hesk_settings['attempt_limit'])
	{
		unset($_SESSION);
		hesk_error( sprintf($hesklang['yhbb'],$hesk_settings['attempt_banmin']) , 0);
	}
}

/* Get details about the original ticket */
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket` WHERE `trackid`='{$trackingID}' LIMIT 1");
if (hesk_dbNumRows($res) != 1)
{
	hesk_error($hesklang['ticket_not_found']);
}
$ticket = hesk_dbFetchAssoc($res);
$customers = hesk_get_customers_for_ticket($ticket['id']);
$customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);

/* If we require e-mail to view tickets check if it matches the one in database */
hesk_verifyEmailMatch($trackingID, $my_email, $customer_emails);

/* Ticket locked? */
if ($ticket['locked'])
{
	hesk_process_messages($hesklang['tislock2'],'ticket.php');
	exit();
}

// Prevent flooding ticket replies
$res = hesk_dbQuery("SELECT `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='{$ticket['id']}' AND `dt` > DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY `id` ASC");
if (hesk_dbNumRows($res) > 0)
{
	$sequential_customer_replies = 0;
	while ($tmp = hesk_dbFetchAssoc($res))
	{
		$sequential_customer_replies = $tmp['staffid'] ? 0 : $sequential_customer_replies + 1;
	}
	if ($sequential_customer_replies > 10)
	{
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` (`ip`, `number`) VALUES ('".hesk_dbEscape(hesk_getClientIP())."', ".intval($hesk_settings['attempt_limit'] + 1).")");
		hesk_error( sprintf($hesklang['yhbr'],$hesk_settings['attempt_banmin']) , 0);
	}
}

/* Insert attachments */
if ($hesk_settings['attachments']['use'] && !empty($attachments))
{
	// Delete temp attachment records and set the new filename
	if (!$use_legacy_attachments) {
		$attachments = hesk_migrateTempAttachments($attachments, $trackingID);
	}

    foreach ($attachments as $myatt)
    {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('{$trackingID}','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."')");
        $myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
    }
}

// If staff hasn't replied yet, keep ticket status "New", otherwise set it to "Waiting reply from staff"
if (hesk_can_customer_change_status($ticket['status']))
{
    $ticket['status'] = $ticket['status'] ? 1 : 0;
}

/* Update ticket as necessary */
$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `status`='{$ticket['status']}', `replies`=`replies`+1, `lastreplier`='0' WHERE `id`='{$ticket['id']}'");

// Insert reply into database
$my_customer = null;
foreach ($customers as $customer) {
	if ($customer['email'] === $my_email) {
		$my_customer = $customer;
		break;
	}
}

if (empty($my_customer)) {
    foreach ($customers as $customer) {
        if ($customer['customer_type'] == 'REQUESTER') {
            $my_customer = $customer;
            break;
        }
    }
}

hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (`replyto`,`message`,`message_html`,`dt`,`attachments`, `customer_id`) VALUES ({$ticket['id']},'".hesk_dbEscape($message)."','".hesk_dbEscape($message)."',NOW(),'".hesk_dbEscape($myattachments)."', ".intval($my_customer['id']).")");


/*** Need to notify any staff? ***/

// --> Prepare reply message

// 1. Generate the array with ticket info that can be used in emails
$combined_emails = implode(';', $customer_emails);
$customer_names = array_map(function($customer) { return $customer['name']; }, $customers);
$combined_names = implode(',', $customer_names);

$info = array(
'email'			=> $combined_emails,
'category'		=> $ticket['category'],
'priority'		=> $ticket['priority'],
'owner'			=> $ticket['owner'],
'collaborators' => hesk_getTicketsCollaboratorIDs($ticket['id']),
'trackid'		=> $ticket['trackid'],
'status'		=> $ticket['status'],
'name'			=> $combined_names,
'subject'		=> $ticket['subject'],
'message'		=> stripslashes($message),
'attachments'	=> $myattachments,
'dt'			=> hesk_date($ticket['dt'], true),
'lastchange'	=> hesk_date(),
'due_date'      => hesk_format_due_date($ticket['due_date']),
'id'			=> $ticket['id'],
'time_worked'   => $ticket['time_worked'],
'last_reply_by' => $my_customer['name'],
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

// --> If ticket is assigned, notify the owner plus collaborators
if ($ticket['owner']) {
    hesk_notifyAssignedStaff(false, 'new_reply_by_customer', 'notify_reply_my', 'notify_collaborator_customer_reply');
}
// --> No owner assigned, find and notify appropriate staff, including collaborators
elseif ($ticket['collaborators']) {
    hesk_notifyStaff('new_reply_by_customer',"`notify_reply_unassigned`='1' OR (`notify_collaborator_customer_reply`='1' AND `id` IN (".implode(",", $ticket['collaborators'])."))");
}
// --> No owner assigned, find and notify appropriate staff, no collaborators
else {
    hesk_notifyStaff('new_reply_by_customer',"`notify_reply_unassigned`='1'");
}

/* Clear unneeded session variables */
hesk_cleanSessionVars('ticket_message');
hesk_cleanSessionVars('r_attachments');

/* Show the ticket and the success message */
hesk_process_messages($hesklang['reply_submitted_success'],'ticket.php','SUCCESS');
exit();
