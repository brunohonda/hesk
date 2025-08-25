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
define('HESK_PATH','../../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
$hesk_settings['db_failure_response'] = 'json';
hesk_isLoggedIn();

//-- Grab search query params
$query = hesk_dbEscape($_GET['query']);

$customers_rs = hesk_dbQuery("SELECT `id`, `name`, `email` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `primary`
WHERE (`name` LIKE '%".$query."%' OR `email` LIKE '%".$query."%')
    AND `verified` <> 2
    AND NOT EXISTS (
        SELECT 1
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `secondary`
        WHERE `primary`.`email` <> '' 
        AND `primary`.`email` = `secondary`.`email`
        AND `secondary`.`id` > `primary`.`id`
    ) 
LIMIT 25");

$response_rows = [];
while ($row = hesk_dbFetchAssoc($customers_rs)) {
    $row['name'] = hesk_html_entity_decode($row['name']);
    $response_rows[] = [
        'id' => intval($row['id']),
        'name' => $row['name'],
        'email' => $row['email'],
        'displayName' => formatDisplayName($row)
    ];
}

if (defined('HESK_DEMO')) {
    array_walk($response_rows, function(&$k) {
        $k['email'] = 'hidden@demo.com';
        $k['displayName'] = formatDisplayName($k);
    });
}

http_response_code(200);
print json_encode($response_rows);
exit();

function formatDisplayName($row) {
    if ($row['name']) {
        return $row['email'] ? "{$row['name']} <{$row['email']}>" : $row['name'];
    }

    return $row['email'];
}
