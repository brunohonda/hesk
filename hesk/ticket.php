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
define('HESK_NO_ROBOTS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

// Connect to the database and start a session
hesk_load_database_functions();
hesk_dbConnect();
hesk_session_start('CUSTOMER');

// Do we require logged-in customers to view the help desk?
hesk_isCustomerLoggedIn($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required']);

$hesk_error_buffer = array();
$do_remember = '';
$display = 'none';

if ($hesk_settings['customer_accounts']) {
    $user_context = hesk_isCustomerLoggedIn(false);
} else {
    $user_context = null;
}


/* A message from ticket reminder? */
if ( ! empty($_GET['remind']) )
{
    $display = 'block';
    $my_email = hesk_emailCleanup( hesk_validateEmail( hesk_GET('e'), 'ERR' , 0) );
    print_form();
}

// Do we have parameters in query string? If yes, store them in session and redirect
if ( isset($_GET['track']) || isset($_GET['e']) || isset($_GET['f']) || isset($_GET['r']) )
{
    $_SESSION['t_track'] = hesk_GET('track');
    $_SESSION['t_email'] = hesk_getCustomerEmail(1);
    $_SESSION['t_form']  = hesk_GET('f');
    $_SESSION['t_remember'] = strlen($do_remember) ? 'Y' : hesk_GET('r');

    header('Location: ticket.php');
    die();
}

/* Was this accessed by the form or link? */
$is_form = hesk_SESSION('t_form');

/* Get the tracking ID */
$trackingID = hesk_cleanID('', hesk_SESSION('t_track'));

/* Email required to view ticket? */
$my_email = $user_context !== null ?
    $user_context['email'] :
    hesk_getCustomerEmail(1, 't_email', 1);


/* Remember email address? */
$do_remember = strlen($do_remember) || strlen(hesk_SESSION('t_remember'));

/* Clean ticket parameters from the session data, we don't need them anymore */
hesk_cleanSessionVars( array('t_track', 't_email', 't_form', 't_remember') );

/* Any errors? Show the form */
if ($is_form)
{
	if ( empty($trackingID) )
    {
    	$hesk_error_buffer[] = $hesklang['eytid'];
    }

    if ($hesk_settings['email_view_ticket'] && $hesk_settings['require_email'] && empty($my_email) )
    {
    	$hesk_error_buffer[] = $hesklang['enter_valid_email'];
    }

    $tmp = count($hesk_error_buffer);
    if ($tmp == 1)
    {
    	$hesk_error_buffer = implode('',$hesk_error_buffer);
		hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
        print_form();
    }
    elseif ($tmp == 2)
    {
    	$hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul><li>'.$hesk_error_buffer[0].'</li><li>'.$hesk_error_buffer[1].'</li></ul>';
		hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
        print_form();
    }
}
elseif (empty($trackingID))
{
	print_form();
}
elseif (empty($my_email) && $hesk_settings['email_view_ticket'])
{
    if ($hesk_settings['require_email']) {
        print_form();
    } else {
        $my_email = '';
    }
}

/* Limit brute force attempts */
hesk_limitBfAttempts();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

/* Get ticket info */
$res = hesk_dbQuery( "SELECT `t1`.* , `t2`.name AS `repliername` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t1` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `t2` ON `t1`.`replierid` = `t2`.`id` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");

/* Ticket found? */
if (hesk_dbNumRows($res) != 1)
{
	/* Ticket not found, perhaps it was merged with another ticket? */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `merged` LIKE '%#".hesk_dbEscape($trackingID)."#%' LIMIT 1");

	if (hesk_dbNumRows($res) == 1)
	{
    	/* OK, found in a merged ticket. Get info */
        $ticket = hesk_dbFetchAssoc($res);
        $customers = hesk_get_customers_for_ticket($ticket['id']);
        $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);

		/* If we require e-mail to view tickets check if it matches the one from merged ticket */
		if ( hesk_verifyEmailMatch($ticket['trackid'], $my_email, $customer_emails, 0) )
        {
        	hesk_process_messages( sprintf($hesklang['tme'], $trackingID, $ticket['trackid']) ,'NOREDIRECT','NOTICE');
            $trackingID = $ticket['trackid'];
        }
        else
        {
        	hesk_process_messages( sprintf($hesklang['tme1'], $trackingID, $ticket['trackid']) . '<br /><br />' . sprintf($hesklang['tme2'], $ticket['trackid']) ,'NOREDIRECT','NOTICE');
            $trackingID = $ticket['trackid'];
            print_form();
        }
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
    $customers = hesk_get_customers_for_ticket($ticket['id']);
    $customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);

	/* If we require e-mail to view tickets check if it matches the one in database */
    $match = hesk_verifyEmailMatch($trackingID, $my_email, $customer_emails);
    if ($match === 'EMAIL_REQUIRED') {
        hesk_process_messages($hesklang['e_c_email'],'NOREDIRECT');
        print_form();
    } elseif ($match === 'EMAIL_FALSE') {
        hesk_process_messages($hesklang['enmdb'],'NOREDIRECT');
        print_form();
    }
}

/* Ticket exists, clean brute force attempts */
hesk_cleanBfAttempts();

/* Remember email address? */
if ($is_form)
{
	if ($do_remember)
	{
		hesk_setcookie('hesk_myemail', $my_email, strtotime('+1 year'));
	}
	elseif ( isset($_COOKIE['hesk_myemail']) )
	{
		hesk_setcookie('hesk_myemail', '');
	}
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
    $ticket['repliername'] = hesk_getReplierName($ticket);
}

// If IP is unknown (tickets via email pipe/pop3 fetching) assume current visitor IP as customer IP
if ($ticket['ip'] == '' || $ticket['ip'] == 'Unknown' || $ticket['ip'] == $hesklang['unknown'])
{
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `ip` = '".hesk_dbEscape(hesk_getClientIP())."' WHERE `id`=".intval($ticket['id']));
}

if ( ! isset($hesk_settings['priorities'][$ticket['priority']])) {
    $ticket['priority'] = array_keys($hesk_settings['priorities'])[0];
}

/* Get category name and ID */
$result = hesk_dbQuery("SELECT `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($ticket['category'])."' LIMIT 1");

/* If this category has been deleted use the default category with ID 1 */
if (hesk_dbNumRows($result) != 1)
{
	$result = hesk_dbQuery("SELECT `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='1' LIMIT 1");
}

$category = hesk_dbFetchAssoc($result);

/* Get replies */
$result  = hesk_dbQuery("SELECT `replies`.*, `customers`.`name` AS `customer_name`, `users`.`name` AS `staff_name`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customers`
        ON `customers`.`id` = `replies`.`customer_id`
    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `users`
        ON `users`.`id` = `replies`.`staffid`  
    WHERE `replyto`='".intval($ticket['id'])."' 
    ORDER BY `id` ".($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
$replies = hesk_dbNumRows($result);
$repliesArray = array();
$unread_replies = array();
while ($row = hesk_dbFetchAssoc($result)) {
    if ($row['staffid']) {
        $row['name'] = $row['staff_name'] === null ?
            $hesklang['staff_deleted'] :
            $row['staff_name'];

        if (!$row['read']) {
            $unread_replies[] = $row['id'];
        }
    } else {
        $row['name'] = $row['customer_name'] === null ?
            $hesklang['anon_name'] :
            $row['customer_name'];
    }

    $repliesArray[] = $row;
}
/* If needed update unread replies as read for staff to know */
if (count($unread_replies))
{
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `read` = '1' WHERE `id` IN ('".implode("','", $unread_replies)."')");
}

// Demo mode
if ( defined('HESK_DEMO') )
{
    foreach ($customers as $customer) {
        $customer['email'] = 'hidden@demo.com';
    }
}
$ticket['customers'] = $customers;

if (count($ticket['customers']) === 0) {
    // If a ticket has 0 customers, it was anonymized
    $ticket['customers'] = [
        [
            'name' => $hesklang['anon_name'],
            'email' => $hesklang['anon_email'],
            'customer_type' => 'REQUESTER'
        ]
    ];
}

$messages = hesk_get_messages();

$custom_fields_before_message = array();
$custom_fields_after_message = array();
foreach ($hesk_settings['custom_fields'] as $k=>$v) {
    if ($v['use']==1 && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category'])))
    {
        $custom_field = array(
            'name' => $v['name'],
            'name:' => $v['name:'],
            'value' => $ticket[$k],
            'type' => $v['type']
        );

        if ($v['type'] == 'date') {
            $custom_field['date_format'] = $v['value']['date_format'];
        }


        if ($v['place'] == 1) {
            $custom_fields_after_message[] = $custom_field;
        } else {
            $custom_fields_before_message[] = $custom_field;
        }
    }
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/view-ticket/view-ticket.php', array(
    'customerUserContext' => $user_context,
    'messages' => $messages,
    'serviceMessages' => hesk_get_service_messages('t-view'),
    'ticketJustReopened' => isset($_SESSION['force_form_top']),
    'ticket' => $ticket,
    'trackingID' => $trackingID,
    'numberOfReplies' => $replies,
    'replies' => $repliesArray,
    'category' => $category,
    'email' => $my_email,
    'customFieldsBeforeMessage' => $custom_fields_before_message,
    'customFieldsAfterMessage' => $custom_fields_after_message
));
unset($_SESSION['force_form_top']);

/* Clear unneeded session variables */
hesk_cleanSessionVars('ticket_message');

/*** START FUNCTIONS ***/

function print_form()
{
	global $hesk_settings, $hesklang;
    global $hesk_error_buffer, $my_email, $trackingID, $do_remember, $display, $user_context;

	/* Print header */
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['view_ticket'];

	$messages = hesk_get_messages();

	$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/view-ticket/form.php', array(
        'customerUserContext' => $user_context,
        'messages' => $messages,
        'serviceMessages' => hesk_get_service_messages('t-form'),
        'trackingId' => $trackingID,
        'email' => $my_email,
        'rememberEmail' => $do_remember,
        'displayForgotTrackingIdForm' => !empty($_GET['forgot']),
        'submittedForgotTrackingIdForm' => $display === 'block'
    ));

exit();
} // End print_form()
