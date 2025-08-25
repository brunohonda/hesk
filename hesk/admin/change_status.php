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

/* A security check */
hesk_token_check();

/* Ticket ID */
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

/* New status */
$status = intval( hesk_REQUEST('s') );
if ( ! isset($hesk_settings['statuses'][$status]))
{
	hesk_process_messages($hesklang['instat'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'NOTICE');
}

// We need can_reply_tickets permission unless we are closing a ticket
if ($status != 3)
{
    hesk_checkPermission('can_reply_tickets');
}

$locked = 0;

// Is the new status same as old status?
if (hesk_get_ticket_status_from_DB($trackingID) == $status) {
    hesk_process_messages($hesklang['noch'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'NOTICE');
}

if ($status == 3) // Closed
{
    if ( ! hesk_checkPermission('can_resolve', 0))
    {
        hesk_process_messages($hesklang['noauth_resolve'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'NOTICE');
    }

	$action = $hesklang['ticket_been'] . ' ' . $hesklang['closed'];
    $revision = sprintf($hesklang['thist3'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

    if ($hesk_settings['custopen'] != 1)
    {
    	$locked = 1;
    }

    // If customer notifications are off, we need to check if the tickets has collaborators for potential notification
    if ( ! $hesk_settings['notify_closed']) {
        $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
        if (hesk_dbNumRows($result) != 1) {
            hesk_error($hesklang['ticket_not_found']);
        }
        $ticket = hesk_dbFetchAssoc($result);
        $ticket['collaborators'] = hesk_getTicketsCollaboratorIDs($ticket['id']);
    }

	// Notify customer of closed ticket?
	if ($hesk_settings['notify_closed'] || ! empty( $ticket['collaborators']))
	{
        // Get ticket info
        if ( ! isset($ticket)) {
            $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
            if (hesk_dbNumRows($result) != 1) {
                hesk_error($hesklang['ticket_not_found']);
            }
            $ticket = hesk_dbFetchAssoc($result);
            $ticket['collaborators'] = hesk_getTicketsCollaboratorIDs($ticket['id']);
        }

		$ticket['dt'] = hesk_date($ticket['dt'], true);
		$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
        $ticket['due_date'] = hesk_format_due_date($ticket['due_date']);

        require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
        $customers = hesk_get_customers_for_ticket($ticket['id']);
        $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);
        $customer_names = array_map(function($customer) { return $customer['name']; }, $customers);
        
        $ticket['email'] = implode(';', $customer_emails);
        $ticket['name'] = implode(';', $customer_names);
        $ticket['last_reply_by'] = hesk_getReplierName($ticket);
		$ticket = hesk_ticketToPlain($ticket, 1, 0);

		// Notify customer
		require(HESK_PATH . 'inc/email_functions.inc.php');

        if ($hesk_settings['notify_closed']) {
            hesk_notifyCustomer('ticket_closed');
        }

        if (count($ticket['collaborators'])) {
            hesk_notifyAssignedStaff(false, 'collaborator_resolved', 'notify_collaborator_resolved', 'notify_collaborator_resolved', array($_SESSION['id']));
        }
	}

	// Log who marked the ticket resolved
	$closedby_sql = ' , `closedat`=NOW(), `closedby`='.intval($_SESSION['id']).' ';
}
elseif ($status != 0)
{
    $status_name = hesk_get_status_name($status);
	$action = sprintf($hesklang['tsst'], $status_name);
    $revision = sprintf($hesklang['thist9'],hesk_date(),addslashes($status_name),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

	// Ticket is not resolved
	$closedby_sql = ' , `closedat`=NULL, `closedby`=NULL ';
}
else // Opened
{
	$action = $hesklang['ticket_been'] . ' ' . $hesklang['opened'];
    $revision = sprintf($hesklang['thist4'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

	// Ticket is not resolved
	$closedby_sql = ' , `closedat`=NULL, `closedby`=NULL ';
}

hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `status`='{$status}', `locked`='{$locked}' $closedby_sql , `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `trackid`='".hesk_dbEscape($trackingID)."'");

if (hesk_dbAffectedRows() != 1)
{
	hesk_error("$hesklang[int_error]: $hesklang[trackID_not_found].");
}

hesk_process_messages($action,'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
