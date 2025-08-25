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
hesk_checkPermission('can_reply_tickets');
hesk_checkPermission('can_resolve');

/* A security check */
hesk_token_check();

/* Ticket ID */
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);

/* New locked status */
if (empty($_GET['locked']))
{
	$status = 0;
	$tmp = $hesklang['tunlock'];
    $revision = sprintf($hesklang['thist6'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
	$closedby_sql = ' , `closedat`=NULL, `closedby`=NULL ';
}
else
{
	$status = 1;
	$tmp = $hesklang['tlock'];
    $revision = sprintf($hesklang['thist5'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
	$closedby_sql = ' , `closedat`=NOW(), `closedby`='.intval($_SESSION['id']).' ';

	// Notify customer of closed ticket?
	if ($hesk_settings['notify_closed'])
	{
		// Get ticket info
		$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
		if (hesk_dbNumRows($result) != 1)
		{
			hesk_error($hesklang['ticket_not_found']);
		}
		$ticket = hesk_dbFetchAssoc($result);

		// Notify customer, but only if ticket is not already closed
		if ($ticket['status'] != 3)
		{
			require(HESK_PATH . 'inc/email_functions.inc.php');
            $customers = hesk_get_customers_for_ticket($ticket['id']);
            $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);
            $customer_names = array_map(function($customer) { return $customer['name']; }, $customers);
            $ticket['email'] = implode(';', $customer_emails);
            $ticket['name'] = implode(';', $customer_names);
			$ticket['dt'] = hesk_date($ticket['dt'], true);
			$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
            $ticket['due_date'] = hesk_format_due_date($ticket['due_date']);
            $ticket['last_reply_by'] = hesk_getReplierName($ticket);
			hesk_notifyCustomer('ticket_closed');
		}
	}
}

/* Update database */
hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `status`='3',`locked`='{$status}' $closedby_sql , `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."')  WHERE `trackid`='".hesk_dbEscape($trackingID)."'");

/* Back to ticket page and show a success message */
hesk_process_messages($tmp,'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999),'SUCCESS');
?>
