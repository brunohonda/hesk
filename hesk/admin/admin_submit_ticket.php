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

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();
require(HESK_PATH . 'inc/email_functions.inc.php');
require(HESK_PATH . 'inc/posting_functions.inc.php');

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// We only allow POST requests from the HESK form to this file
if ( $_SERVER['REQUEST_METHOD'] != 'POST' )
{
	header('Location: admin_main.php');
	exit();
}

// Check for POST requests larger than what the server can handle
if ( empty($_POST) && ! empty($_SERVER['CONTENT_LENGTH']) )
{
	hesk_error($hesklang['maxpost']);
}

// Changing category? Remember data and redirect to category select page
if (hesk_POST('change_category') == 1)
{
    $_SESSION['as_customer_id'] = hesk_POST('customer_id');
    $_SESSION['as_follower_ids'] = hesk_POST_array('follower_id');
    $_SESSION['as_priority'] = hesk_POST('priority');
    $_SESSION['as_status']   = hesk_POST('status');
    $_SESSION['as_subject']  = hesk_POST('subject');
    $_SESSION['as_message']  = hesk_POST('message');
    $_SESSION['as_due_date'] = hesk_POST('due_date');
    $_SESSION['as_owner']    = hesk_POST('owner');
    $_SESSION['as_notify']   = hesk_POST('notify');
    $_SESSION['as_show']     = hesk_POST('show');
    $_SESSION['as_language'] = hesk_POST('as_language');

    foreach ($hesk_settings['custom_fields'] as $k=>$v)
    {
        if ($v['use'] && ! in_array($v['type'], array('date', 'email')))
        {
            $_SESSION["as_$k"] = ($v['type'] == 'checkbox') ? hesk_POST_array($k) : hesk_POST($k);
        }
    }

    header('Location: new_ticket.php');
    exit();
}

$hesk_error_buffer = array();


$tmpvar['customer_id'] = hesk_POST('customer_id') or $hesk_error_buffer['customer-id']=$hesklang['customer_required'];

// Ensure that the customer (1) exists and (2) isn't pending approval
$customer_verification_rs = hesk_dbQuery("SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
    WHERE `id` = ".intval($tmpvar['customer_id'])."
        AND `verified` <> 2");
if (hesk_dbNumRows($customer_verification_rs) < 1) {
    $hesk_error_buffer['customer-id'] = $hesklang['customer_required'];
}

$tmpvar['follower_ids'] = hesk_POST_array('follower_id');

// Remove followers that are also the requester, and remove duplicate followers
$actual_followers = [];
foreach ($tmpvar['follower_ids'] as $follower_id) {
    $follower_id_int = intval($follower_id);
    if ($follower_id_int === intval($tmpvar['customer_id']) || in_array($follower_id_int, $actual_followers)) {
        continue;
    }

    $follower_record = hesk_get_customer_account_by_id($follower_id_int);
    if ($follower_record['email'] && !hesk_isBannedEmail($follower_record['email'])) {
        $actual_followers[] = $follower_id_int;
    }
}
$tmpvar['follower_ids'] = $actual_followers;

$tmpvar['category'] = intval( hesk_POST('category') ) or $hesk_error_buffer['category']=$hesklang['sel_app_cat'];
$tmpvar['priority'] = hesk_POST('priority');
$tmpvar['priority'] = strlen($tmpvar['priority']) ? intval($tmpvar['priority']) : -1;

$tmpvar['status'] = intval(hesk_POST('status', 0));
if ( ! isset($hesk_settings['statuses'][$tmpvar['status']])) {
    $tmpvar['status'] = 0;
}

$tmpvar['subject'] = hesk_input( hesk_POST('subject') );
if ($hesk_settings['require_subject'] == 1 && $tmpvar['subject'] == '')
{
    $hesk_error_buffer['subject'] = $hesklang['enter_ticket_subject'];
}

$tmpvar['message']  = hesk_input( hesk_POST('message') );
if ($hesk_settings['require_message'] == 1 && $tmpvar['message'] == '')
{
    $hesk_error_buffer['message'] = $hesklang['enter_message'];
}

if ($hesk_settings['staff_ticket_formatting'] == 2 && ! class_exists('DOMDocument')) {
    $hesk_error_buffer['message'] = $hesklang['require_xml'];
}

// Is category a valid choice?
if ($tmpvar['category'])
{
    if ( ! hesk_checkPermission('can_submit_any_cat', 0) && ! hesk_okCategory($tmpvar['category'], 0) )
    {
        hesk_process_messages($hesklang['noauth_submit'],'new_ticket.php');
    }

	hesk_verifyCategory(1);
}

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'] && hesk_is_custom_field_in_category($k, $tmpvar['category']))
	{
        if ($v['type'] == 'checkbox')
        {
			$tmpvar[$k]='';

        	if (isset($_POST[$k]) && is_array($_POST[$k]))
            {
				foreach ($_POST[$k] as $myCB)
				{
					$tmpvar[$k] .= ( is_array($myCB) ? '' : hesk_input($myCB) ) . '<br />';;
				}
				$tmpvar[$k]=substr($tmpvar[$k],0,-6);
            }
            else
            {
            	if ($v['req'] == 2)
                {
					$hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
                }
            	$_POST[$k] = '';
            }
        }
        elseif ($v['type'] == 'date')
        {
        	$tmpvar[$k] = hesk_POST($k);
            $_SESSION["as_$k"] = '';

            if ($date = hesk_datepicker_get_date($tmpvar[$k], false, 'UTC'))
            {
                $_SESSION["as_$k"] = $tmpvar[$k];

                $date->setTime(0, 0);
                $dmin = strlen($v['value']['dmin']) ? new DateTime($v['value']['dmin'] . ' t00:00:00 UTC') : false;
                $dmax = strlen($v['value']['dmax']) ? new DateTime($v['value']['dmax'] . ' t00:00:00 UTC') : false;

                if ($dmin && $dmin->format('Y-m-d') > $date->format('Y-m-d'))
	            {
					$hesk_error_buffer[$k] = sprintf($hesklang['d_emin'], $v['name'], hesk_translate_date_string($dmin->format($hesk_settings['format_datepicker_php'])));
	            }
	            elseif ($dmax && $dmax->format('Y-m-d') < $date->format('Y-m-d'))
	            {
					$hesk_error_buffer[$k] = sprintf($hesklang['d_emax'], $v['name'], hesk_translate_date_string($dmax->format($hesk_settings['format_datepicker_php'])));
	            }
                else
                {
                	$tmpvar[$k] = $date->getTimestamp();
                }
			}
            else
            {
            	$tmpvar[$k] = '';

				if ($v['req'] == 2)
				{
					$hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
				}
            }
        }
        elseif ($v['type'] == 'email')
        {
			$tmp = $hesk_settings['multi_eml'];
            $hesk_settings['multi_eml'] = $v['value']['multiple'];
			$tmpvar[$k] = hesk_validateEmail( hesk_POST($k), 'ERR', 0);
            $hesk_settings['multi_eml'] = $tmp;

            if ($tmpvar[$k] != '')
            {
				$_SESSION["as_$k"] = hesk_input($tmpvar[$k]);
            }
            else
            {
            	$_SESSION["as_$k"] = '';

                if ($v['req'] == 2)
                {
            		$hesk_error_buffer[$k] = $v['value']['multiple'] ? sprintf($hesklang['cf_noem'], $v['name']) : sprintf($hesklang['cf_noe'], $v['name']);
                }
            }
        }
		elseif ($v['req'] == 2)
        {
        	$tmpvar[$k]=hesk_makeURL(nl2br(hesk_input( hesk_POST($k) )));
            if ($tmpvar[$k] == '')
            {
            	$hesk_error_buffer[$k]=$hesklang['fill_all'].': '.$v['name'];
            }
        }
        else
        {
    		$tmpvar[$k]=hesk_makeURL(nl2br(hesk_input(hesk_POST($k))));
        }
	}
    else
    {
    	$tmpvar[$k] = '';
    }
}


// If use doesn't have permission to set due dates, try using the category default due date
if (hesk_checkPermission('can_due_date',0)) {
    $tmpvar['due_date'] = hesk_input(hesk_POST('due_date'));
    if ($tmpvar['due_date'] != '') {
        $date = hesk_datepicker_get_date($tmpvar['due_date']);
        if ($date === false) {
            $hesk_error_buffer['due_date'] = $hesklang['invalid_due_date'];
        }
    }
} else {
    $tmpvar['due_date'] = '';
    if (($default_due_date_info = hesk_getCategoryDueDateInfo($tmpvar['category'])) !== null) {
        $due_date = new DateTime('today midnight');
        $due_date->add(DateInterval::createFromDateString("+{$default_due_date_info['amount']} {$default_due_date_info['unit']}s"));
        $tmpvar['due_date'] = hesk_datepicker_format_date($due_date->getTimestamp());

        // Don't set a due date if any unexpected errors
        if ($tmpvar['due_date'] === false) {
            $tmpvar['due_date'] = '';
        }
    }
}

// Generate tracking ID
$tmpvar['trackid'] = hesk_createID();

// Log who submitted ticket
$tmpvar['history'] = sprintf($hesklang['thist7'], hesk_date(), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
$tmpvar['openedby'] = $_SESSION['id'];

// Was the ticket submitted as "Resolved"?
if ($tmpvar['status'] == 3) {
    // Check permission
    if ( ! hesk_checkPermission('can_resolve', 0))  {
        $hesk_error_buffer['status'] = $hesklang['noauth_resolve'];
    }

    $tmpvar['history'] .= sprintf($hesklang['thist3'], hesk_date(), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

    if ($hesk_settings['custopen'] != 1)  {
        $tmpvar['locked'] = 1;
    }

    // Log who marked the ticket resolved
    $tmpvar['closedat'] = 1;
    $tmpvar['closedby'] = intval($_SESSION['id']);
} elseif ($tmpvar['status'] != 0) {
    // Status set to something different than "New" or "Resolved", let's log it
    $status_name = hesk_get_status_name($tmpvar['status']);
    $tmpvar['history'] .= sprintf($hesklang['thist9'], hesk_date(), addslashes($status_name), addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
}

// Owner
$tmpvar['owner'] = 0;
if (hesk_checkPermission('can_assign_others',0))
{
	$tmpvar['owner'] = intval( hesk_POST('owner') );

	// If ID is -1 the ticket will be unassigned
	if ($tmpvar['owner'] == -1)
	{
		$tmpvar['owner'] = 0;
	}
    // Automatically assign owner?
    elseif ($tmpvar['owner'] == -2 && $hesk_settings['autoassign'] == 1)
    {
		$autoassign_owner = hesk_autoAssignTicket($tmpvar['category']);
		if ($autoassign_owner)
		{
			$tmpvar['owner']    = intval($autoassign_owner['id']);
			$tmpvar['history'] .= sprintf($hesklang['thist10'],hesk_date(),addslashes($autoassign_owner['name']).' ('.$autoassign_owner['user'].')');
		}
        else
        {
        	$tmpvar['owner'] = 0;
        }
    }
    // Check for invalid owner values
	elseif ($tmpvar['owner'] < 1)
	{
	    $tmpvar['owner'] = 0;
	}
    else
    {
	    // Has the new owner access to the selected category?
		$res = hesk_dbQuery("SELECT `name`,`user`,`isadmin`,`categories` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='{$tmpvar['owner']}' LIMIT 1");
	    if (hesk_dbNumRows($res) == 1)
	    {
	    	$row = hesk_dbFetchAssoc($res);
	        if (!$row['isadmin'])
	        {
				$row['categories']=explode(',',$row['categories']);
				if (!in_array($tmpvar['category'],$row['categories']))
				{
                	$_SESSION['isnotice'][] = 'category';
					$hesk_error_buffer['owner']=$hesklang['onasc'];
				}
	        }
            $tmpvar['history'] .= sprintf($hesklang['thist2'],hesk_date(),addslashes($row['name']).' ('.$row['user'].')',addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');
	    }
	    else
	    {
        	$_SESSION['isnotice'][] = 'category';
	    	$hesk_error_buffer['owner']=$hesklang['onasc'];
	    }
    }
}
elseif (hesk_checkPermission('can_assign_self',0) && hesk_okCategory($tmpvar['category'],0) && !empty($_POST['assing_to_self']))
{
	$tmpvar['owner'] = intval($_SESSION['id']);
}

// Notify customer of the ticket?
$notify = ! empty($_POST['notify']) ? 1 : 0;

// Show ticket after submission?
$show = ! empty($_POST['show']) ? 1 : 0;

// Is the ticket language different than current language?
if ($hesk_settings['can_sel_lang'])
{
    $new_lang = hesk_POST('as_language');
    if (isset($hesk_settings['languages'][$new_lang]))
    {
        $hesklang['LANGUAGE'] = $new_lang;
    }
}

// Attachments
$use_legacy_attachments = hesk_POST('use-legacy-attachments', 0);
if ($hesk_settings['attachments']['use'])
{
    require_once(HESK_PATH . 'inc/attachments.inc.php');

    $attachments = array();
    $trackingID  = $tmpvar['trackid'];

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
$tmpvar['attachments'] = '';

// If we have any errors lets store info in session to avoid re-typing everything
if (count($hesk_error_buffer)!=0)
{
	$_SESSION['iserror'] = array_keys($hesk_error_buffer);

    $_SESSION['as_customer_id'] = $tmpvar['customer_id'];
    $_SESSION['as_follower_ids'] = $tmpvar['follower_ids'];
    $_SESSION['as_priority'] = $tmpvar['priority'];
    $_SESSION['as_status']   = $tmpvar['status'];
    $_SESSION['as_subject']  = hesk_POST('subject');
    $_SESSION['as_message']  = hesk_POST('message');
    $_SESSION['as_due_date'] = hesk_POST('due_date');
    $_SESSION['as_owner']    = $tmpvar['owner'];
    $_SESSION['as_notify']   = $notify;
    $_SESSION['as_show']     = $show;
    $_SESSION['as_language'] = hesk_POST('as_language');

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use'] && ! in_array($v['type'], array('date', 'email')))
		{
			$_SESSION["as_$k"] = ($v['type'] == 'checkbox') ? hesk_POST_array($k) : hesk_POST($k);
		}
	}

    $tmp = '';
    foreach ($hesk_error_buffer as $error)
    {
        $tmp .= "<li>$error</li>\n";
    }
    $hesk_error_buffer = $tmp;

	// Remove any successfully uploaded attachments
	if ($hesk_settings['attachments']['use'])
	{
        if ($use_legacy_attachments) {
            hesk_removeAttachments($attachments);
        } else {
            $_SESSION['as_attachments'] = $attachments;
        }
	}

    $hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    hesk_process_messages($hesk_error_buffer,'new_ticket.php?category='.$tmpvar['category']);
}

if ($hesk_settings['attachments']['use'] && !empty($attachments))
{
    // Delete temp attachment records and set the new filename
    if (!$use_legacy_attachments) {
        $attachments = hesk_migrateTempAttachments($attachments, $tmpvar['trackid']);
    }

    foreach ($attachments as $myatt)
    {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('".hesk_dbEscape($tmpvar['trackid'])."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."')");
        $tmpvar['attachments'] .= hesk_dbInsertID() . '#' . $myatt['real_name'] .',';
    }
}

$tmpvar['message_html'] = $tmpvar['message'];

if ($hesk_settings['staff_ticket_formatting'] == 2) {
    // Decode the message we encoded earlier
    $tmpvar['message_html'] = hesk_html_entity_decode($tmpvar['message_html']);

    // Clean the HTML code and set the plaintext version
    require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
    require(HESK_PATH . 'inc/html2text/html2text.php');
    $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
    $tmpvar['message_html'] = $purifier->heskPurify($tmpvar['message_html']);

    $tmpvar['message'] = convert_html_to_text($tmpvar['message_html']);
    $tmpvar['message'] = fix_newlines($tmpvar['message']);

    // Prepare plain message for storage as HTML
    $tmpvar['message'] = hesk_htmlspecialchars($tmpvar['message']);
    $tmpvar['message'] = nl2br($tmpvar['message']);
} else {
    // `message` already contains a HTML friendly version. May as well just re-use it
    $tmpvar['message'] = hesk_makeURL($tmpvar['message']);
    $tmpvar['message'] = nl2br($tmpvar['message']);
    $tmpvar['message_html'] = $tmpvar['message'];
}

// Track who assigned the ticket
if ($tmpvar['owner'] > 0)
{
    $tmpvar['assignedby'] = ! empty($autoassign_owner) ? -1 : $_SESSION['id'];
}

// Demo mode
if ( defined('HESK_DEMO') ) {
    hesk_process_messages(sprintf($hesklang['antdemo'], 'https://www.hesk.com/demo/index.php?a=add'), 'new_ticket.php?category='.$tmpvar['category']);
}

// Let's not add staff IP address as the ticket IP address
$hesk_settings['client_IP'] = '';

// Insert ticket to database
$ticket = hesk_newTicket($tmpvar);
$customers = hesk_get_customers_for_ticket($ticket['id']);
$email_available = count(array_filter($customers, function($customer) { return $customer['email'] !== ''; })) > 0;

// Notify the customer about the ticket?
if ($notify && $email_available)
{
    if ($tmpvar['status'] == 3) {
        hesk_notifyCustomer('ticket_closed');
    } else {
        hesk_notifyCustomer('new_ticket_by_staff');
    }
}

// If ticket is assigned to someone notify them?
if ($ticket['owner'] && $ticket['owner'] != intval($_SESSION['id']))
{
	// If we don't have info from auto-assign get it from database
    if ( ! isset($autoassign_owner['email']) )
    {
		hesk_notifyAssignedStaff(false, 'ticket_assigned_to_you', 'notify_assigned', false);
	}
    else
    {
		hesk_notifyAssignedStaff($autoassign_owner, 'ticket_assigned_to_you', 'notify_assigned', false);
    }
}

// Ticket unassigned, notify everyone that selected to be notified about unassigned tickets
elseif ( ! $ticket['owner'])
{
	hesk_notifyStaff('new_ticket_staff', " `id` != ".intval($_SESSION['id'])." AND `notify_new_unassigned` = '1' ");
}

// Unset temporary variables
unset($tmpvar);
hesk_cleanSessionVars('tmpvar');
hesk_cleanSessionVars('as_customer_id');
hesk_cleanSessionVars('as_follower_ids');
hesk_cleanSessionVars('as_name');
hesk_cleanSessionVars('as_email');
hesk_cleanSessionVars('as_category');
hesk_cleanSessionVars('as_priority');
hesk_cleanSessionVars('as_status');
hesk_cleanSessionVars('as_subject');
hesk_cleanSessionVars('as_message');
hesk_cleanSessionVars('as_owner');
hesk_cleanSessionVars('as_notify');
hesk_cleanSessionVars('as_show');
hesk_cleanSessionVars('as_due_date');
hesk_cleanSessionVars('as_language');
hesk_cleanSessionVars('as_attachments');
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
    hesk_cleanSessionVars("as_$k");
}

// If ticket has been assigned to the person submitting it lets show a message saying so
if ($ticket['owner'] && $ticket['owner'] == intval($_SESSION['id']))
{
	$hesklang['new_ticket_submitted'] .= '<br />&nbsp;<br />
    <b>' . (isset($autoassign_owner) ? $hesklang['taasy'] : $hesklang['tasy']) . '</b>';
}

// Show the ticket or just a success message

// --> Cannot view tickets, go back to the new_ticket.php page
if ( ! hesk_checkPermission('can_view_tickets',0)) {
    hesk_process_messages($hesklang['new_ticket_submitted'], 'new_ticket.php', 'SUCCESS');
}

// --> Unassigned ticket with no view permission, go back to the new_ticket.php page
if ($ticket['owner'] == 0 && ! hesk_checkPermission('can_view_unassigned',0)) {
    hesk_process_messages($hesklang['new_ticket_submitted'], 'new_ticket.php', 'SUCCESS');
}

// --> Ticket assigned to someone else automatically which I cannot view
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && isset($autoassign_owner) && ! hesk_checkPermission('can_view_ass_others', 0) ) {
    hesk_process_messages($hesklang['new_ticket_submitted'], 'new_ticket.php', 'SUCCESS');
}

// --> Ticket assigned to someone else by me, but I don't have permission to view tickets I assign to others
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! isset($autoassign_owner) && ! hesk_checkPermission('can_view_ass_others', 0) && ! hesk_checkPermission('can_view_ass_by', 0) ) {
    hesk_process_messages($hesklang['new_ticket_submitted'], 'new_ticket.php', 'SUCCESS');
}

// --> Show the ticket
if ($show) {
    hesk_process_messages($hesklang['new_ticket_submitted'],'admin_ticket.php?track=' . $ticket['trackid'] . '&Refresh=' . mt_rand(10000,99999), 'SUCCESS');
}

// --> No matches, show a success message with a link to the ticket
hesk_process_messages($hesklang['new_ticket_submitted'].'. <a href="admin_ticket.php?track=' . $ticket['trackid'] . '&Refresh=' . mt_rand(10000,99999) . '">' . $hesklang['view_ticket'] . '</a>', 'new_ticket.php', 'SUCCESS');

