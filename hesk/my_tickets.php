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

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/statuses.inc.php');
require_once(HESK_PATH . 'inc/priorities.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

// Are we in "Knowledgebase only" mode?
hesk_check_kb_only();
hesk_session_start('CUSTOMER');
hesk_load_database_functions();
hesk_dbConnect();

$user_context = hesk_isCustomerLoggedIn();

// Fix a bug before we find a better way to do it :)
if ($user_context === true) {
    $user_context = hesk_isCustomerLoggedIn();
}

$additional_sql_filters = '';
$page_size = intval(hesk_REQUEST('page-size', 20));
$page_number = intval(hesk_REQUEST('page-number', 1));
$search_criteria = hesk_input(hesk_REQUEST('search', ''));
$search_by = hesk_input(hesk_REQUEST('search-by', ''));
$status = hesk_input(hesk_REQUEST('status', 'ALL'));
$order_by = hesk_input(hesk_REQUEST('order-by', ''));
$order_direction = hesk_input(hesk_REQUEST('order-direction', 'desc'));

if (!in_array($order_direction, ['asc','desc'])) {
    $order_direction = 'desc';
}

$sql_order_by = '`status_sort`, `lastchange`';
if (!in_array($order_by, ['id','trackid','lastchange','subject','status','priority'])) {
    // If no sort or an invalid sort is requested, revert to the default order direction
    $order_by = '';
    $order_direction = 'desc';
} else {
    if ($order_by == 'priority') {
        $sql_order_by = "`priority_order`";
    } else {
        $sql_order_by = "`{$order_by}`";
    }
}

if ($search_criteria !== '') {
    $criteria = '';
    switch ($search_by) {
        case 'subject':
            $criteria = "`tickets`.`subject` LIKE '%%".hesk_dbEscape(hesk_dbLike($search_criteria))."%%' COLLATE '".hesk_dbCollate()."'";
            break;
        case 'message':
            $criteria = "(`message` LIKE '%%".hesk_dbEscape(hesk_dbLike($search_criteria))."%%' COLLATE '".hesk_dbCollate()."'
            		OR `tickets`.`id` IN (
                        SELECT DISTINCT `replyto`
                        FROM  `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
                        WHERE  `replies`.`message` LIKE '%%".hesk_dbEscape(hesk_dbLike($search_criteria))."%%' COLLATE '".hesk_dbCollate()."')
                    )";
            break;
        case 'trackid':
        default:
            $criteria = "`tickets`.`trackid` = '".hesk_dbEscape($search_criteria)."' OR `tickets`.`merged` LIKE '%%#".hesk_dbEscape(hesk_dbLike($search_criteria))."#%%'";
            break;
    }

    // Escaping % due to sprintf
    $additional_sql_filters = " AND ({$criteria}) ";
}
if ($status !== 'ALL') {
    $operator = $status === 'CLOSED' ? '=' : '<>';
    $additional_sql_filters .= " AND `tickets`.`status` {$operator} 3";
}

// Fetch tickets
$offset = ($page_number - 1) * $page_size;

$sql_format = "SELECT %s,`priority_order` AS `vv`
 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_customers`
        ON `tickets`.`id` = `ticket_customers`.`ticket_id`
    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` AS `custom_priorities`
        ON `priority` = `custom_priorities`.`id`
    WHERE `customer_id` = ".intval($user_context['id'])." "
    .$additional_sql_filters;

//region Open vs Closed Tickets
$open_vs_closed_count_rs = hesk_dbQuery("SELECT SUM(`open`) AS `open`, SUM(`closed`) AS `closed` FROM (
    SELECT COUNT(1) AS `open`, 0 AS `closed`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_customers`
        ON `tickets`.`id` = `ticket_customers`.`ticket_id`
    WHERE `customer_id` = ".intval($user_context['id'])."
    AND `status` <> 3
    ".$additional_sql_filters."
    UNION
    SELECT 0 AS `open`, COUNT(1) AS `closed`
    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_customers`
        ON `tickets`.`id` = `ticket_customers`.`ticket_id`
    WHERE `customer_id` = ".intval($user_context['id'])."
    AND `status` = 3
    ".$additional_sql_filters."
) AS `t1`");
$ticket_counts = [
    'open' => 0,
    'closed' => 0
];
if ($row = hesk_dbFetchAssoc($open_vs_closed_count_rs)) {
    $ticket_counts = $row;
}
//endregion

//region Search Results
$tickets_rs = hesk_dbQuery(sprintf($sql_format,
    "CASE 
        WHEN `status` = 2 THEN 1
        WHEN `status` = 3 THEN 999
        ELSE 2
    END AS `status_sort`,
    `tickets`.*").
    "ORDER BY {$sql_order_by} {$order_direction} LIMIT {$page_size} OFFSET {$offset}");
$tickets = [];
while ($ticket = hesk_dbFetchAssoc($tickets_rs)) {
    // Copied from ticket_list.inc.php
    switch ($hesk_settings['updatedformat'])
    {
        case 1:
            $ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_timestamp']);
            break;
        case 2:
            $ticket['lastchange'] = hesk_time_lastchange($ticket['lastchange']);
            break;
        case 3:
            $ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_date']);
            break;
        case 4:
            $ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_updated']);
            break;
        default:
            $mysql_time = hesk_dbTime();
            $ticket['lastchange'] = hesk_time_since( strtotime($ticket['lastchange']) );
    }

    $ticket['status_id'] = $ticket['status'];
    $ticket['status'] = hesk_get_ticket_status($ticket['status']);
    if ( ! isset($hesk_settings['priorities'][$ticket['priority']])) {
        $ticket['priority'] = array_keys($hesk_settings['priorities'])[0];
    }

    $tickets[] = $ticket;
}
//endregion

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/my-tickets.php', array(
    'customerUserContext' => $user_context,
    'serviceMessages' => hesk_get_service_messages('c-main'),
    'tickets' => $tickets,
    'ticketCounts' => $ticket_counts,
    'searchCriteria' => $search_criteria,
    'searchType' => $search_by,
    'status' => $status,
    'ordering' => [
        'orderBy' => $order_by,
        'orderDirection' => $order_direction,
    ],
    'paging' => [
        'pageNumber' => $page_number,
        'pageSize' => $page_size
    ]
));
