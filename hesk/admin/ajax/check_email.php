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
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
$hesk_settings['db_failure_response'] = 'json';
hesk_isLoggedIn();

//-- Grab search query params
$email = hesk_dbEscape(hesk_GET('email'));

$existing_customer = hesk_get_customer_account_by_email(hesk_GET('email'));

http_response_code(200);
print json_encode([
    'emailAvailable' => $existing_customer === null,
    'emailValid' => (empty($hesk_settings['require_email']) && empty($email) ? true : hesk_isValidEmail($email))
]);
exit();
