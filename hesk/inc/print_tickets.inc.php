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

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

// Prepare total counts that we will use later
$totals = array(
    'all' => 0,
    'open' => 0,
    'resolved' => 0,
    'filtered' => array(
        'all' => 0,
        'open' => 0,
        'assigned_to_me' => 0,
        'assigned_to_others' => 0,
        'assigned_to_others_by_me' => 0,
        'unassigned' => 0,
        'bookmarks' => 0,
        'due_soon' => 0,
        'overdue' => 0,
        'by_status' => array(),
        'collaborator' => 0,
    ),
);

// Let's check some permissions
$can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
$can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
$can_view_ass_by = hesk_checkPermission('can_view_ass_by',0);

// Is this a quick link?
$is_quick_link = hesk_GET('ql', false);

// This will get number of ALL tickets this user has access to
$sql = "SELECT COUNT(*) AS `cnt`, IF (`status` = 3, 1, 0) AS `is_resolved`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
        WHERE
        (
            `w`.`user_id`=".intval($_SESSION['id'])."
            OR
            (".hesk_myOwnership().")
        )
        AND ".hesk_myCategories()."
        GROUP BY `is_resolved`";
$res = hesk_dbQuery($sql);

while ($row = hesk_dbFetchAssoc($res))
{
    // Total tickets found
    $totals['all'] += $row['cnt'];

    // Total by status
    if ($row['is_resolved'])
    {
        $totals['resolved'] += $row['cnt'];
    }
    else
    {
        $totals['open'] = $row['cnt'];
    }
}

$sql_final = ''; // SQL that fetches ticket data from the database
$sql_count = ''; // SQL that runs a quick count of tickets by status, due date and ownership
$sql_collaborator = ''; // SQL that runs a quick count of collaborated tickets

$sql_customer_count = "SELECT COUNT(1) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer_names`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `customer_names`
        ON `ticket_to_customer_names`.`customer_id` = `customer_names`.`id` 
    WHERE `ticket_id` = `ticket`.`id`";
$sql_email_count = "SELECT COUNT(1) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer_emails`
    INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `customer_emails`
        ON `ticket_to_customer_emails`.`customer_id` = `customer_emails`.`id` 
    WHERE `ticket_id` = `ticket`.`id`
        AND COALESCE(`customer_emails`.`email`, '') <> ''";

// This SQL code will be used to retrieve results
$sql_final = "SELECT
`ticket`.`id` AS `id`,
`trackid`,
COALESCE(`customer`.`name`, '".hesk_dbEscape($hesklang['anon_name'])."') AS `name`,
COALESCE(`customer`.`email`, '".hesk_dbEscape($hesklang['anon_email'])."') AS `email`,
({$sql_customer_count}) AS `customer_count`,
({$sql_email_count}) AS `email_count`,
`category`,
`priority`,
`priority_order` AS `vv`,
`subject`,
LEFT(`message`, 400) AS `message`,
`dt`,
`lastchange`,
`firstreply`,
`closedat`,
`status`,
`openedby`,
`firstreplyby`,
`closedby`,
`ticket`.`replies`,
`staffreplies`,
`owner`,
`time_worked`,
`due_date`,
`lastreplier`,
`lastreplier_customer`.`name` AS `lastreplier_customername`,
`replierid`,
`archive`,
`locked`,
CASE WHEN `bookmarks`.`id` IS NOT NULL THEN 1 ELSE 0 END AS `is_bookmark`
";

foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'])
	{
		$sql_final .= ", `".$k."`";
	}
}

if ($is_quick_link == 'cbm') {
    $sql_final.= " FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).") ";
} else {
    $sql_final.= " FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).") ";
}

$sql_final.= "
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
    ON `customer`.`id` = (
        SELECT MIN(`customer_id`)
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
        WHERE `ticket_id` = `ticket`.`id`
            AND `customer_type` = 'REQUESTER'
        LIMIT 1
    )
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `lastreplier_customer`
    ON `ticket`.`lastreplier` = '0'
    AND `lastreplier_customer`.`id` = (
        SELECT `customer_id`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies`
        WHERE `replyto` = `ticket`.`id`
            AND `customer_id` IS NOT NULL
        ORDER BY `id` DESC
        LIMIT 1)
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `lastreplier_staff`
    ON `ticket`.`lastreplier` <> '0'
    AND `ticket`.`replierid` = `lastreplier_staff`.`id`
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` AS `bookmarks` ON (`ticket`.`id` = `bookmarks`.`ticket_id` AND `bookmarks`.`user_id` = ".intval($_SESSION['id']).")
LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` AS `custom_priorities` ON `ticket`.`priority` = `custom_priorities`.`id`
WHERE
";

if ($is_quick_link == 'cbm') {
    $sql_final.= " `w`.`user_id`=".intval($_SESSION['id'])." AND ".hesk_myCategories()." ";
} else {
    $sql_final .= " ".hesk_myCategories()." AND ".hesk_myOwnership(1);
}

// This code will be used to count number of results for this specific search
$sql_count = " SELECT COUNT(*) AS `cnt`, `status`,
                      IF (`owner` = " . intval($_SESSION['id']) . ", 1, IF (`owner` = 0, 0, IF (`assignedby` = " . intval($_SESSION['id']) . ", 3, 2) ) ) AS `assigned_to`,
                      IF (`due_date` < NOW(), 2, IF (`due_date` BETWEEN NOW() AND (NOW() + INTERVAL ".intval($hesk_settings['due_soon'])." DAY), 1, 0) ) AS `due`,
                      CASE WHEN `bookmarks`.`id` IS NOT NULL THEN 1 ELSE 0 END AS `is_bookmark`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
                LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` AS `bookmarks` ON (`".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`.`id` = `bookmarks`.`ticket_id` AND `bookmarks`.`user_id` = ".intval($_SESSION['id']).")
                WHERE ".hesk_myCategories()." AND ".hesk_myOwnership(1);

// This code will be used to count collaborated tickets for this specific search
$sql_collaborator = " SELECT COUNT(*) AS `cnt`
                FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
                WHERE `w`.`user_id`=".intval($_SESSION['id'])." AND ".hesk_myCategories();

// This is common SQL for all queries
$sql = "";

// Some default settings
$archive = array(1=>0,2=>0);
$s_my = array(1=>1,2=>1);
$s_ot = array(1=>1,2=>1);
$s_un = array(1=>1,2=>1);

// For some specific quick links we will ignore some filters
$ignore_category = false;
$ignore_status = false;
$ignore_owner = false;
$ignore_archive = false;
$ignore_category = false;

// -> All tickets
if ($is_quick_link == 'all')
{
    $ignore_category = true;
    $ignore_status = true;
    $ignore_owner = true;
    $ignore_archive = true;
    $ignore_category = true;
}
// -> All open tickets
elseif ($is_quick_link == 'alo')
{
    $ignore_category = true;
    $ignore_owner = true;
    $ignore_archive = true;
    $ignore_category = true;
}
// -> Collaborated tickets
elseif ($is_quick_link == 'cbm')
{
    $ignore_owner = true;
}

// --> TICKET CATEGORY
$category = intval( hesk_GET('category', 0) );
if ( ! $ignore_category && $category && hesk_okCategory($category, 0) )
{
    $sql .= " AND `category`='{$category}' ";
}

// Show only tagged tickets?
if ( ! $ignore_archive && ! empty($_GET['archive']) )
{
	$archive[1]=1;
	$sql .= " AND `archive`='1' ";
}

$sql_count .= $sql;
$sql_collaborator .= $sql;

// Ticket owner preferences
$fid = 1;
require(HESK_PATH . 'inc/assignment_search.inc.php');

// --> TICKET STATUS
$status = $hesk_settings['statuses'];

// Process statuses unless overridden with "s_all" variable
if ( ! hesk_GET('s_all') )
{
	foreach ($status as $k => $v)
	{
		if (empty($_GET['s'.$k]))
		{
			unset($status[$k]);
	    }
	}
}

// How many statuses are we pulling out of the database?
$tmp = count($status);

// Do we need to search by status?
if ( $tmp < count($hesk_settings['statuses']) )
{
	// If no statuses selected, show default (all except RESOLVED)
	if ($tmp == 0)
	{
		$status = $hesk_settings['statuses'];
		unset($status[3]);
	}

	// Add to the SQL
	$sql .= " AND `status` IN ('" . implode("','", array_keys($status) ) . "') ";
    $sql_count .= " AND `status` IN ('" . implode("','", array_keys($status) ) . "') ";
    $sql_collaborator .= " AND `status` IN ('" . implode("','", array_keys($status) ) . "') ";
}

// --> TICKET PRIORITY

$possible_priority = hesk_possible_priorities();
$priority = $possible_priority;

foreach ($priority as $k => $v)
{
	if (empty($_GET['p'.$k]))
    {
    	unset($priority[$k]);
    }
}

// How many priorities are we pulling out of the database?
$tmp = count($priority);

// Create the SQL based on the number of priorities we need
if ($tmp == 0 || $tmp == count($possible_priority))
{
	// Nothing or all selected, no need to modify the SQL code
    $priority = $possible_priority;
}
else
{
	// A custom selection of priorities
	$sql .= " AND `priority` IN ('" . implode("','", array_keys($priority) ) . "') ";
    $sql_count .= " AND `priority` IN ('" . implode("','", array_keys($priority) ) . "') ";
    $sql_collaborator .= " AND `priority` IN ('" . implode("','", array_keys($priority) ) . "') ";
}

// Due date
if ($is_quick_link == 'due')
{
    $sql .= " AND `status` != 3 AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL ".intval($hesk_settings['due_soon'])." DAY) ";
}
elseif ($is_quick_link == 'ovr')
{
    $sql .= " AND `status` != 3 AND `due_date` < NOW() ";
}
elseif ($is_quick_link == 'bm')
{
    $sql .= " AND `bookmarks`.`id` IS NOT NULL";
}

// Did the user specify a due date (either specific or a range)?
$duedate_search_type = hesk_restricted_GET('duedate_option', ['specific', 'range'], 'specific');
$duedate_input = hesk_GET('duedate_specific_date');
$duedate_amount_value = intval(hesk_GET('duedate_amount_value'));
$duedate_amount_unit = hesk_restricted_GET('duedate_amount_unit', ['day', 'week'], 'day');
if ($duedate_search_type === 'specific' && $duedate_input && hesk_datepicker_get_date($duedate_input) !== false) {
    $formatted_due_date = hesk_datepicker_get_date($duedate_input);
    $hesk_settings['datepicker'] = array();
    $hesk_settings['datepicker']['#duedate_specific_date']['timestamp'] = $formatted_due_date->getTimestamp();;
    $formatted_due_date = $formatted_due_date->format('Y-m-d');
    $sql .= " AND DATE(`due_date`) = '".hesk_dbEscape($formatted_due_date)."' ";
    $sql_count .= " AND DATE(`due_date`) = '".hesk_dbEscape($formatted_due_date)."' ";
    $sql_collaborator .= " AND DATE(`due_date`) = '".hesk_dbEscape($formatted_due_date)."' ";
} elseif ($duedate_search_type === 'range' && $duedate_amount_value && $duedate_amount_unit) {
    $unit = $duedate_amount_unit === 'day' ? 'DAY' : 'WEEK';
    $sql .= " AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL {$duedate_amount_value} {$unit}) ";
    $sql_count .= " AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL {$duedate_amount_value} {$unit}) ";
    $sql_collaborator .= " AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL {$duedate_amount_value} {$unit}) ";
}

// That's all the SQL we need for count
$sql = $sql_final . $sql;

// Prepare variables used in search and forms
require(HESK_PATH . 'inc/prepare_ticket_search.inc.php');

// We need to group the count SQL by parameters to be able to extract different totals
$sql_count .= " GROUP BY `assigned_to`, `due`, `status`, `is_bookmark` ";

// List tickets?
if (!isset($_SESSION['hide']['ticket_list']))
{
	require(HESK_PATH . 'inc/ticket_list.inc.php');
}
