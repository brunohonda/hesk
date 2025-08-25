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

$tmp = intval( hesk_GET('limit') );
$maxresults = ($tmp > 0) ? $tmp : $hesk_settings['max_listings'];

$tmp = intval( hesk_GET('page', 1) );
$page = ($tmp > 1) ? $tmp : 1;

/* Acceptable $sort values and default asc(1)/desc(0) setting */
$sort_possible = array();
foreach (array_keys($hesk_settings['possible_ticket_list']) as $key)
{
	$sort_possible[$key] = 1;
}
$sort_possible['priority'] = 1;
$sort_possible['dt'] = 0;
$sort_possible['lastchange'] = 0;

/* These values should have collate appended in SQL */
$sort_collation = array(
'name',
'subject',
);
for ($i=1;$i<=100;$i++) {
    $sort_collation[] = 'custom'.$i;
}

/* Acceptable $group values and default asc(1)/desc(0) setting */
$group_possible = array(
'owner' 		=> 1,
'priority' 		=> 1,
'category' 		=> 1,
);
for ($i=1;$i<=100;$i++) {
    $group_possible['custom'.$i] = 1;
}

/* Start the order by part of the SQL query */
$sql .= " ORDER BY ";

// Group parameter
$group = hesk_GET('g');
if ( ! isset($group_possible[$group]))
{
    $group = '';
}

// Sort parameter
$sort = hesk_GET('sort', 'status');
if ( ! isset($sort_possible[$sort]))
{
    $sort = 'status';
}

// Group tickets?
if ($group != '')
{
    if ($group == 'priority' && $sort == 'priority')
    {
		// No need to group by priority if we are already sorting by priority
    }
    elseif ($group == 'owner')
    {
		// If group by owner place own tickets on top
		$sql .= " CASE WHEN `owner` = '".intval($_SESSION['id'])."' THEN 1 ELSE 0 END DESC, `owner` ASC, ";
    }
    elseif ($group == 'category' && $sort == 'category')
    {
        // No need to group by category if we are already sorting by category
    }
    elseif ($group == 'category')
    {
        // Get list of categories
        $hesk_settings['categories'] = array();
        $res2 = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` WHERE ' . hesk_myCategories('id') . ' ORDER BY `cat_order` ASC');
        while ($row=hesk_dbFetchAssoc($res2))
        {
            $hesk_settings['categories'][$row['id']] = $row['name'];
        }

        // Make sure categories are in correct order
        $sql .= ' FIELD(`category`, ' . preg_replace('/[^0-9,]/', '', implode(',' , array_keys($hesk_settings['categories']))) . '), ';
    }
    else
    {
	    $sql .= ' `'.hesk_dbEscape($group).'` ';
	    $sql .= $group_possible[$group] ? 'ASC, ' : 'DESC, ';
    }
}

// Show critical tickets always on top? Default: yes
$cot = hesk_GET('cot') == 1 ? 1 : 0;
if (!$cot)
{
	$sql .= " CASE WHEN `priority` = '0' THEN 1 ELSE 0 END DESC , ";
}

// Prepare sorting
if ($sort == 'category')
{
    // Get list of categories
    $hesk_settings['categories'] = array();
    $res2 = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` WHERE ' . hesk_myCategories('id') . ' ORDER BY `cat_order` ASC');
    while ($row=hesk_dbFetchAssoc($res2))
    {
        $hesk_settings['categories'][$row['id']] = $row['name'];
    }

    // Make sure categories are in correct order
    $sql .= ' FIELD(`category`, ' . preg_replace('/[^0-9,]/', '', implode(',' , array_keys($hesk_settings['categories']))) . ') ';
}
else
{
    if ($sort === 'lastreplier') {
        $sql .= " CASE WHEN `lastreplier` = '0' THEN COALESCE(`lastreplier_customer`.`name`, `customer`.`name`) ELSE `lastreplier_staff`.`name` END ";
    } elseif ($sort === 'name') {
        $sql .= " COALESCE(`customer`.`name`, '".hesk_dbEscape($hesklang['anon_name'])."') ";
    } elseif ($sort === 'priority') {
        $sql .= ' `priority_order` ';
    } else {
        $sql .= ' `'.hesk_dbEscape($sort).'` ';
    }


    // Need to set MySQL collation?
    if ( in_array($sort, $sort_collation) )
    {
    	$sql .= " COLLATE '" . hesk_dbCollate() . "' ";
    }
}

/* Ascending or Descending? */
if (isset($_GET['asc']) && intval($_GET['asc'])==0)
{
    $sql .= ' DESC ';
    $asc = 0;
    $asc_rev = 1;

    $sort_possible[$sort] = 1;
}
else
{
    $sql .= ' ASC ';
    $asc = 1;
    $asc_rev = 0;
    if (!isset($_GET['asc']))
    {
    	$is_default = 1;
    }

    $sort_possible[$sort] = 0;
}

/* In the end same results should always be sorted by priority */
if ($sort != 'priority')
{
	$sql .= ' , `priority_order` DESC ';
}

// Always sort by ID to make sure chached results don't cause different results between pages
if ($sort != 'id') {
    $sql .= ' , `id` ASC ';
}

# Uncomment for debugging purposes
# echo "SQL: $sql<br>";
