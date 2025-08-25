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

$formatted_tickets = array();
foreach ($tickets as $ticket) {
    // Ticket priority
    switch ($ticket['priority'])
    {
        case 0:
            $ticket['priority']='<b>'.$hesklang['critical'].'</b>';
            break;
        case 1:
            $ticket['priority']='<b>'.$hesklang['high'].'</b>';
            break;
        case 2:
            $ticket['priority']=$hesklang['medium'];
            break;
        default:
            $ticket['priority']=$hesklang['low'];
    }

    // Replies
    $replies = array();
    foreach ($ticket['replies'] as $reply) {
        $reply['dt'] = hesk_date($reply['dt'], true);
        $reply['message'] = hesk_unhortenUrl($reply['message']);
        if ($reply['name'] === null) {
            if (intval($reply['staffid']) > 0) {
                $reply['name'] = $hesklang['staff_deleted'];
            } else {
                $reply['name'] = $hesklang['anon_name'];
            }
        }

        $replies[] = $reply;
    }
    $ticket['replies'] = $replies;

    // Set last replier name
    if ($ticket['lastreplier']) {
        if (empty($ticket['repliername'])) {
            $ticket['repliername'] = $hesklang['staff'];
        }
    } else {
        if (!function_exists('hesk_get_customers_for_ticket')) {
            require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
        }

        // Get the last reply and pull its name
        $reply_count = count($ticket['replies']);
        if ($reply_count > 0) {
            $last_reply = $ticket['replies'][$reply_count - 1];

            $customers = hesk_get_customers_for_ticket($ticket['id']);
            $customer_names = array_map(function($customer) { return $customer['name']; }, $customers);
            $ticket['repliername'] = $last_reply['name'];
        } else {
            $ticket['repliername'] = '';
        }
    }

    // Other variables that need processing
    $ticket['dt'] = hesk_date($ticket['dt'], true);
    $ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
    $ticket['due_date'] = hesk_format_due_date($ticket['due_date']);
    $random=mt_rand(10000,99999);

    $ticket['status'] = hesk_get_status_name($ticket['status']);

    if ($ticket['owner'] && ! empty($_SESSION['id']) )
    {
        $ticket['owner'] = hesk_getOwnerName($ticket['owner']);
    } else {
        $ticket['owner'] = '';
    }

    // Custom fields
    $custom_fields = array();
    foreach ($hesk_settings['custom_fields'] as $k=>$v) {
        if (($v['use'] == 1 || (!empty($_SESSION['id']) && $v['use'] == 2)) && (strlen($ticket[$k]) || hesk_is_custom_field_in_category($k, $ticket['category']))) {
            if ($v['type'] == 'date') {
                $ticket[$k] = hesk_custom_date_display_format($ticket[$k], $v['value']['date_format']);
            }

            $custom_fields[] = array(
                'name' => $v['name:'],
                'value' => hesk_unhortenUrl($ticket[$k])
            );
        }
    }
    $ticket['custom_fields'] = $custom_fields;

    // Initial ticket message
    if ($ticket['message'] != '')
    {
        $ticket['message'] = hesk_unhortenUrl($ticket['message']);
    }

    $formatted_tickets[] = $ticket;
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'print-ticket.php', array(
    'tickets' => $formatted_tickets,
    'showStaffOnlyFields' => !empty($_SESSION['id'])
), true, true);
