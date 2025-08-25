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
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_view_tickets');

// A security check
hesk_token_check('POST');

// Ticket ID
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

// Ticket details
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
if (hesk_dbNumRows($res) != 1)
{
	hesk_error($hesklang['ticket_not_found']);
}
$ticket = hesk_dbFetchAssoc($res);
$opened_by = $ticket['openedby'];

// Do we have permission to view this ticket?
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! hesk_checkPermission('can_view_ass_others',0))
{
    // Maybe this user is allowed to view tickets he/she assigned?
    if ( ! $can_view_ass_by || $ticket['assignedby'] != $_SESSION['id'])
    {
        hesk_error($hesklang['ycvtao']);
    }
}

if ( ! $ticket['owner'] && ! hesk_checkPermission('can_view_unassigned',0))
{
	hesk_error($hesklang['ycovtay']);
}

// Is this user allowed to view tickets inside this category?
hesk_okCategory($ticket['category']);

// Reply or original message?
$reply_id  = intval( hesk_GET('reply', 0) );

if ($reply_id > 0)
{
    $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`={$reply_id} AND `replyto`=".intval($ticket['id'])." LIMIT 1");
    if (hesk_dbNumRows($res) != 1)
    {
        hesk_error($hesklang['ernf']);
    }

    $reply = hesk_dbFetchAssoc($result);

    $ticket['message'] = $reply['message'];
    $ticket['message_html'] = $reply['message_html'];
    $ticket['attachments'] = $reply['attachments'];
}

/* --> Prepare message */
$customers = hesk_get_customers_for_ticket($ticket['id']);
$customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);
$customer_names = array_map(function($customer) { return $customer['name']; }, $customers);

// 1. Generate the array with ticket info that can be used in emails
$info = array(
'email'			=> implode(';', $customer_emails),
'category'		=> $ticket['category'],
'priority'		=> $ticket['priority'],
'owner'			=> $ticket['owner'],
'collaborators' => hesk_getTicketsCollaboratorIDs($ticket['id']),
'trackid'		=> $ticket['trackid'],
'status'		=> $ticket['status'],
'name'			=> implode(';', $customer_names),
'subject'		=> $ticket['subject'],
'message'		=> $ticket['message'],
'message_html'  => $ticket['message_html'],
'attachments'	=> $ticket['attachments'],
'dt'			=> hesk_date($ticket['dt'], true),
'lastchange'	=> hesk_date($ticket['lastchange'], true),
'due_date'      => hesk_format_due_date($ticket['due_date']),
'id'			=> $ticket['id'],
'time_worked'   => $ticket['time_worked'],
'last_reply_by' => hesk_getReplierName($ticket),
'language'      => $ticket['language'],
);

// 2. Add custom fields to the array
foreach ($hesk_settings['custom_fields'] as $k => $v)
{
	$info[$k] = $v['use'] ? $ticket[$k] : '';
}

// 3. Make sure all values are properly formatted for email
$ticket = hesk_ticketToPlain($info, 1, 0);

// Remind assigned staff?
if (hesk_GET('remind') == 1 && $ticket['owner']) {
    hesk_notifyAssignedStaff(false, 'ticket_assigned_to_you');

    if ($ticket['collaborators']) {
        hesk_notifyCollaborators($ticket['collaborators'], 'collaborator_added', 'notify_collaborator_added');
    }

    $res = hesk_dbQuery("SELECT `user`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`=".intval($ticket['owner'])." LIMIT 1");
    $row = hesk_dbFetchAssoc($res);
    $revision = sprintf($hesklang['thist23'],hesk_date(),addslashes($row['name']).' ('.$row['user'].')',addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=`lastchange`, `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `id`=" . intval($ticket['id']));
    hesk_process_messages($hesklang['remind_sent'],'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
}

// Notification of a reply
if ($reply_id > 0)
{
    // Reply by staff, send notification to customer
    if ($reply['staffid']) {
        hesk_notifyCustomer('new_reply_by_staff');

        if ($ticket['collaborators']) {
            hesk_notifyAssignedStaff(false, 'collaborator_staff_reply', 'notify_collaborator_staff_reply', 'notify_collaborator_staff_reply');
        }
    }
    // --> If ticket is assigned, notify the owner plus collaborators
    elseif ($ticket['owner']) {
        hesk_notifyAssignedStaff(false, 'new_reply_by_customer', 'notify_reply_my', 'notify_collaborator_customer_reply');
    }
    // --> No owner assigned, find and notify appropriate staff, including collaborators
    elseif ($ticket['collaborators']) {
        hesk_notifyStaff('new_reply_by_customer',"`notify_reply_unassigned`='1' OR (`notify_collaborator_customer_reply`='1' AND `id` IN (".implode(",", $ticket['collaborators'])."))", 1);
    }
    // --> No owner assigned, find and notify appropriate staff, no collaborators
    else {
        hesk_notifyStaff('new_reply_by_customer',"`notify_reply_unassigned`='1'", 1);
    }

    hesk_process_messages($hesklang['rns'],'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
}

// Notification of the original ticket
if ($opened_by) {
    hesk_notifyCustomer('new_ticket_by_staff');
} else {
    hesk_notifyCustomer();
}

// Notify staff?
if ($ticket['owner'])
{
    hesk_notifyAssignedStaff(false, 'ticket_assigned_to_you');

    if ($ticket['collaborators']) {
        hesk_notifyCollaborators($ticket['collaborators'], 'collaborator_added', 'notify_collaborator_added');
    }
}
else
{
    hesk_notifyStaff('new_ticket_staff', "`notify_new_unassigned`='1' OR (`notify_collaborator_added`='1' AND `id` IN (".implode(",", $ticket['collaborators'])."))", 1);
}

hesk_process_messages($hesklang['tns'],'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
