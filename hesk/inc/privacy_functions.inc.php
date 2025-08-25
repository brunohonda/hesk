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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}


/*** FUNCTIONS ***/
function hesk_anonymizeTicketsForCustomer($customer_id) {
    global $hesk_settings;

    $tickets = hesk_dbQuery("SELECT `ticket_id` 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
        WHERE `customer_id` = ".intval($customer_id)."
        AND `customer_type` = 'REQUESTER'");

    while ($row = hesk_dbFetchAssoc($tickets)) {
        hesk_anonymizeTicket(intval($row['ticket_id']));
    }
}

function hesk_anonymizeTicket($id, $trackingID = null, $have_ticket = false)
{
	global $hesk_settings, $hesklang;

    // Do we already have ticket info?
    if ($have_ticket)
    {
        global $ticket;
    }
    else
    {
        // Get ticket info by tracking or numerical ID
        if ($trackingID !== null)
        {
            $res = hesk_dbQuery("SELECT `id`, `trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' AND ".hesk_myOwnership());
        }
        else
        {
    	    $res = hesk_dbQuery("SELECT `id`, `trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`=".intval($id)." AND ".hesk_myOwnership());
        }
        if ( ! hesk_dbNumRows($res))
        {
            return false;
        }
        $ticket = hesk_dbFetchAssoc($res);
    }

    if (!function_exists('hesk_get_customers_for_ticket')) {
        require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
    }
    $customers = hesk_get_customers_for_ticket($ticket['id']);

    // Delete attachment files
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket['trackid'])."'");
    if (hesk_dbNumRows($res))
    {
    	$hesk_settings['server_path'] = dirname(dirname(__FILE__));

    	while ($file = hesk_dbFetchAssoc($res))
        {
        	hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
        }
    }

    // Delete attachments info from the database
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket['trackid'])."'");

    // Anonymize customer names on ticket history
    foreach ($customers as $customer) {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            SET `history`=REPLACE(`history`, ' ".hesk_dbEscape(addslashes($customer['name']))."</li>', ' ".hesk_dbEscape($hesklang['anon_name'])."</li>')
            WHERE `id` = ".intval($ticket['id']));
    }

    // Anonymize ticket
    $sql = "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET
    `u_name`  = '".hesk_dbEscape($hesklang['anon_name'])."',
    `u_email` = '".hesk_dbEscape($hesklang['anon_email'])."',
    `subject` = '".hesk_dbEscape($hesklang['anon_subject'])."',
    `message` = '".hesk_dbEscape($hesklang['anon_message'])."',
    `message_html` = '".hesk_dbEscape($hesklang['anon_message'])."',
    `ip`      = '".hesk_dbEscape($hesklang['anon_IP'])."',
    ";
    for($i=1; $i<=50; $i++)
    {
        $sql .= "`custom{$i}` = '',";
    }
    $sql .= "
    attachments='',
    `history`=CONCAT(`history`,'".hesk_dbEscape(sprintf($hesklang['thist18'],hesk_date(),addslashes($_SESSION['name']).' ('.$_SESSION['user'].')'))."')
    WHERE `id`='".intval($ticket['id'])."'";
	hesk_dbQuery($sql);

    // Delete customer relationships
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `ticket_id` = ".intval($ticket['id']));

    // Anonymize replies
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `customer_id` = NULL, `message` = '".hesk_dbEscape($hesklang['anon_message'])."', `message_html` = '".hesk_dbEscape($hesklang['anon_message'])."', attachments='' WHERE `replyto`='".intval($ticket['id'])."'");

    // Delete ticket notes
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($ticket['id'])."'");

	// Delete ticket reply drafts
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `ticket`=".intval($ticket['id']));

    // Delete linked ticket associations
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."linked_tickets` WHERE `ticket_id1`='".intval($ticket['id'])."' OR `ticket_id2`='".intval($ticket['id'])."'");

    return true;
} // END hesk_anonymizeTicket()
