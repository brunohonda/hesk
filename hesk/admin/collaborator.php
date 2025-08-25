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

$can_assign_others = hesk_checkPermission('can_assign_others',0);
if ($can_assign_others)
{
    $can_assign_self = TRUE;
}
else
{
    $can_assign_self = hesk_checkPermission('can_assign_self',0);
}

/* A security check */
hesk_token_check();

// Find ticket ID
$trackingID = hesk_cleanID() or die($hesklang['int_error'].': '.$hesklang['no_trackID']);
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");
if (hesk_dbNumRows($res) != 1) {
    hesk_error($hesklang['ticket_not_found']);
}
$ticket = hesk_dbFetchAssoc($res);

$collaborator = empty($_REQUEST['collaborator']) ? 0 : 1;
$_SERVER['PHP_SELF'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.rand(10000,99999);

$user = intval(hesk_REQUEST('user'));

if (empty($user)) {
    hesk_process_messages($hesklang['no_valid_id'],$_SERVER['PHP_SELF']);
}

// Verify the user has access to the ticket category
$res = hesk_dbQuery("SELECT `id`,`user`,`name`,`email`,`isadmin`,`language`,`categories`,`notify_collaborator_added` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='{$user}' LIMIT 1");
$row = hesk_dbFetchAssoc($res);
if ( ! $row['isadmin'])
{
    $row['categories']=explode(',',$row['categories']);
    if (!in_array($ticket['category'],$row['categories']))
    {
        hesk_error($hesklang['unoa']);
    }
}

if ($collaborator) {
    // ADD AS A COLLABORATOR
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` (`ticket_id`, `user_id`) VALUES ({$ticket['id']}, {$user}) ");

    $revision = sprintf($hesklang['thist24'], hesk_date(), addslashes($row['name']).' ('.$row['user'].')', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `id`=" . intval($ticket['id']));

    if ($user != intval($_SESSION['id']) && $row['notify_collaborator_added']) {
        $customers = hesk_get_customers_for_ticket($ticket['id']);
        $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);
        $customer_names = array_map(function($customer) { return $customer['name']; }, $customers);

        /* --> Prepare message */

        // 1. Generate the array with ticket info that can be used in emails
        $info = array(
        'email'         => implode(';', $customer_emails),
        'category'      => $ticket['category'],
        'priority'      => $ticket['priority'],
        'owner'         => $ticket['owner'],
        'collaborators' => hesk_getTicketsCollaboratorIDs($ticket['id']),
        'trackid'       => $ticket['trackid'],
        'status'        => $ticket['status'],
        'name'          => implode(';', $customer_names),
        'subject'       => $ticket['subject'],
        'message'       => $ticket['message'],
        'message_html'  => $ticket['message_html'],
        'attachments'   => $ticket['attachments'],
        'dt'            => hesk_date($ticket['dt'], true),
        'lastchange'    => hesk_date($ticket['lastchange'], true),
        'due_date'      => hesk_format_due_date($ticket['due_date']),
        'id'            => $ticket['id'],
        'time_worked'   => $ticket['time_worked'],
        'last_reply_by' => hesk_getReplierName($ticket),
        );

        // 2. Add custom fields to the array
        foreach ($hesk_settings['custom_fields'] as $k => $v)
        {
            $info[$k] = $v['use'] ? $ticket[$k] : '';
        }

        // 3. Make sure all values are properly formatted for email
        $ticket = hesk_ticketToPlain($info, 1, 0);

        hesk_notifyAssignedStaff($row, 'collaborator_added', 'notify_collaborator_added', false);
    }

    hesk_process_messages($hesklang['user_collaborator_added'],$_SERVER['PHP_SELF'],'SUCCESS');
} else {
    // REMOVE COLLABORATOR
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` WHERE `ticket_id`={$ticket['id']} AND `user_id`={$user}");

    $revision = sprintf($hesklang['thist25'], hesk_date(), addslashes($row['name']).' ('.$row['user'].')', addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `id`=" . intval($ticket['id']));

    if ($user == $_SESSION['id']) {
        hesk_process_messages($hesklang['not_collaborating'],$_SERVER['PHP_SELF'],'SUCCESS');
    } {
        hesk_process_messages($hesklang['user_collaborator_removed'],$_SERVER['PHP_SELF'],'SUCCESS');
    }
}
