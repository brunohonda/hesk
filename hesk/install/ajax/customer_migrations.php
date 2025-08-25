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

require(HESK_PATH . 'install/install_functions.inc.php');
require(HESK_PATH . 'install/customer_migration_functions.inc.php');

$action = hesk_REQUEST('action');

switch ($action) {
    case 'intro':
        print json_encode(customer_migration_get_customer_count());
        break;
    case 'migrate':
        $batch_migrated = customer_migration_do_migration();
        print json_encode(['migratedCount' => $batch_migrated]);
        break;
    case 'cleanup':
        customer_migration_drop_work_table();
        hesk_iDropOldPreCustomerColumns();
        print json_encode(['message' => 'OK']);
        break;
    default:
        print json_encode(['message' => "Invalid action {$action} provided"]);
        break;
}

//region Functions
function customer_migration_get_customer_count() {
    $customers_to_migrate = customer_migration_get_customers_to_migrate();

    return [
        'numberOfCustomers' => $customers_to_migrate['amountToProcess']
    ];
}

function customer_migration_do_migration() {
    /*
     * 1. Create customer records of next 100 customers in work table
     * 2. Map tickets to new customer records
     * 3. Mark work table records as processed
     */
    $customers_to_migrate = customer_migration_create_customer_batch();

    if (!$customers_to_migrate) {
        return 0;
    }

    return customer_migration_associate_tickets();
}

function customer_migration_drop_work_table() {
    global $hesk_settings;

    hesk_dbQuery("DROP TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers`");
}
//endregion