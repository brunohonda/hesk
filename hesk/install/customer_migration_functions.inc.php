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

$hesk_settings['db_failure_response'] = 'json';

hesk_dbConnect();

function customer_migration_get_customers_to_migrate() {
    global $hesk_settings;

    // Check if we've already done this.  If so, hand it back
    $already_done_rs = hesk_dbQuery("SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
         WHERE TABLE_SCHEMA = '".hesk_dbEscape($hesk_settings['db_name'])."' 
         AND TABLE_NAME = '".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers'");
    if (hesk_dbNumRows($already_done_rs) > 0) {
        $customer_count_rs = hesk_dbQuery("SELECT COUNT(1) AS `total_count` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` UNION ALL
            SELECT COUNT(1) AS `total_count` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` WHERE `processed` = 0");
        $customerCount = -1;
        $amountToProcess = -1;
        $index = 0;
        while ($row = hesk_dbFetchAssoc($customer_count_rs)) {
            if ($index === 0) {
                $customerCount = $row['total_count'];
            } else {
                $amountToProcess = $row['total_count'];
            }
            $index++;
        }

        return [
            'customerCount' => $customerCount,
            'amountToProcess' => $amountToProcess
        ];
    }

    /*
     * 0. Create work table
     * 1. Grab distinct customers, based on their newest info (excluding tickets with multiple emails and no emails)
     * 2. Store customer information into work table
     * 3. Grab multi-email tickets
     * 4. Create new work table records or assign to existing work table record
     * 5. Grab no-email tickets (treat each distinct name as separate)
     * 6. Insert work table records
     */
    hesk_dbQuery("CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` (
        `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
        `fake_customer_id` int not null,
        `name` varchar(255) not null COLLATE utf8_unicode_ci,
        `email` varchar(255) not null COLLATE utf8_unicode_ci,
        `language` varchar(50) null,
        `processed` smallint(1) not null,
        PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

    // Single-email customers
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` (`fake_customer_id`, `name`, `email`, `language`, `processed`)
        SELECT `user_info`.`id`, TRIM(`u_name`), TRIM(`u_email`), `user_info`.`language`, 0
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `user_info`
        INNER JOIN (
            SELECT MAX(`id`) AS id, TRIM(`u_email`)
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            GROUP BY TRIM(`u_email`)
        ) AS `newest_ticket`
            ON `user_info`.`id` = `newest_ticket`.`id`
        WHERE (`u_email` NOT LIKE '%,%' AND TRIM(`u_email`) <> '')
        ORDER BY `user_info`.`id`");

    // Multi-email customers
    $multi_rs = hesk_dbQuery("SELECT `user_info`.`id`, TRIM(`u_name`) AS `name`, TRIM(`u_email`) AS `email`, `language`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `user_info`
        INNER JOIN (
            SELECT MAX(`id`) AS id, TRIM(`u_email`)
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            GROUP BY TRIM(`u_email`)
        ) AS `newest_ticket`
            ON `user_info`.`id` = `newest_ticket`.`id`
        WHERE `u_email` LIKE '%,%'
        ORDER BY `id`");
    while ($row = hesk_dbFetchAssoc($multi_rs)) {
        $split_emails = explode(',', $row['email']);
        $first = true;
        foreach ($split_emails as $email) {
            $customer_exists_rs = hesk_dbQuery("SELECT `fake_customer_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers`
             WHERE `email` = '".hesk_dbEscape($email)."'");
            if ($existing_customer = hesk_dbFetchAssoc($customer_exists_rs)) {
                if (($first && $existing_customer['fake_customer_id'] < $row['id']) || $row['name'] === '') {
                    // Use this name instead as it's newer or because the current one is blank
                    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` 
                        SET `name` = '".hesk_dbEscape($row['name'])."' 
                        WHERE `fake_customer_id` = ".intval($existing_customer['fake_customer_id']));
                }
            } else {
                // No customer record; insert one
                $name = $first ? $row['name'] : '';
                $language = $row['language'] ? "'".hesk_dbEscape($row['language'])."'" : 'NULL';
                hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` (`fake_customer_id`, `name`, `email`, `language`, `processed`)
                    VALUES (".intval($row['id']).", '".hesk_dbEscape($name)."', '".hesk_dbEscape($email)."', ".$language.", 0)");
            }

            $first = false;
        }
    }

    // No email customers
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` (`fake_customer_id`, `name`, `email`, `language`, `processed`)
        SELECT `user_info`.`id`, TRIM(`u_name`), '', `user_info`.`language`, 0
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `user_info`
        INNER JOIN (
            SELECT MAX(`id`) AS id, TRIM(`u_name`), TRIM(`u_email`)
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
            GROUP BY TRIM(`u_name`), TRIM(`u_email`)
        ) AS `newest_ticket`
            ON `user_info`.`id` = `newest_ticket`.`id`
        WHERE TRIM(COALESCE(`u_email`, '')) = ''
        ORDER BY `user_info`.`id`");

    // Grab the total number of customers
    $total_count_rs = hesk_dbQuery("SELECT COUNT(1) AS `cnt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers`");
    $total_count = hesk_dbFetchAssoc($total_count_rs);

    return [
        'customerCount' => $total_count['cnt'],
        'amountToProcess' => $total_count['cnt']
    ];
}

function customer_migration_create_customer_batch() {
    global $hesk_settings;

    $next_batch = hesk_dbQuery("SELECT `id`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers`
        WHERE `processed` = 0
        ORDER BY `id`
        LIMIT 100");
    $ids = [];
    while ($row = hesk_dbFetchAssoc($next_batch)) {
        $ids[] = intval($row['id']);
    }

    if (count($ids) === 0) {
        return false;
    }

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`pass`, `name`, `email`,
        `language`, `verified`, `verification_token`, `mfa_enrollment`, `mfa_secret`)
        SELECT NULL, `temp_customer`.`name`, `temp_customer`.`email`, `temp_customer`.`language`, 350, NULL, 0, NULL
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers` AS `temp_customer`
        WHERE `id` IN (".implode(',', $ids).")");
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_customers`
    SET `processed` = 1
    WHERE `id` IN (".implode(',', $ids).")");

    return true;
}

function customer_migration_associate_tickets() {
    global $hesk_settings;

    $new_customers_rs = hesk_dbQuery("SELECT COUNT(1) AS `cnt`,
        IF(EXISTS (SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `verified` = 350 AND `email` <> ''), 1, 0) AS `has_email`,
        IF(EXISTS (SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `verified` = 350 AND `email` = ''), 1, 0) AS `has_no_email`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `verified` = 350");
    $customer_stats = hesk_dbFetchAssoc($new_customers_rs);
    $migrated_count = intval($customer_stats['cnt']);

    // Insert requester records (with email, no multi-email)
    if (intval($customer_stats['has_email']) === 1) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`) 
        SELECT `ticket`.`id`, `customer`.`id`, 'REQUESTER' 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
            ON TRIM(`ticket`.`u_email`) = `customer`.`email`
        WHERE `customer`.`verified` = 350
            AND `ticket`.`u_email` <> ''
            AND `ticket`.`u_email` NOT LIKE '%,%'");

        // Insert requester records (with email, multi-email)
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`) 
        SELECT `ticket`.`id`, `customer`.`id`, 'REQUESTER' 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
            ON TRIM(`ticket`.`u_email`) LIKE CONCAT(`customer`.`email`, ',%')
        WHERE `customer`.`verified` = 350
            AND `ticket`.`u_email` LIKE '%,%'");

        // Insert follower records (with email)
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`) 
        SELECT `ticket`.`id`, `customer`.`id`, 'FOLLOWER' 
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
            ON (`ticket`.`u_email` LIKE CONCAT('%,', `customer`.`email`, ',%')
                OR `ticket`.`u_email` LIKE CONCAT('%,', `customer`.`email`))
        WHERE `customer`.`verified` = 350
            AND `customer`.`email` <> ''
            AND `ticket`.`u_email` LIKE '%,%'");
    }

    if (intval($customer_stats['has_no_email']) === 1) {
        // Insert requester records (no email)
        // I'm intentionally using a STRAIGHT_JOIN here.  For some reason, an INNER JOIN uses a really bad execution plan
        // which causes the query to take minutes instead of a few seconds (worst-case)
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`)
            SELECT `ticket`.`id`, `customer`.`id`, 'REQUESTER'
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
            STRAIGHT_JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
              ON TRIM(`ticket`.`u_name`) = `customer`.`name`
              AND TRIM(`ticket`.`u_email`) = `customer`.`email`
            WHERE `customer`.`verified` = 350
              AND TRIM(`ticket`.`u_email`) = ''");
    }

    // Update replies for requesters
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
            INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                ON `replies`.`replyto` = `ticket_to_customer`.`ticket_id`
                AND `ticket_to_customer`.`customer_type` = 'REQUESTER'
            INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer`
                ON `ticket_to_customer`.`customer_id` = `customer`.`id`
            SET `replies`.`customer_id` = `ticket_to_customer`.`customer_id`
            WHERE `customer`.`verified` = 350
                AND `staffid` = 0");

    // Mark customers as migrated
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `verified` = 0
        WHERE `verified` = 350");

    return $migrated_count;
}

function customer_migration_map_customer_to_tickets($customer, $tickets, $customer_type) {
    global $hesk_settings;
    $BATCH_SIZE = 1000;

    // Insert ticket/customer mappings
    for ($i = 0; $i < count($tickets); $i += $BATCH_SIZE) {
        $ticket_sublist = array_slice($tickets, $i, $BATCH_SIZE);
        $ticket_ids = [];

        $sql = "INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`) ";
        $ticket_query_parts = [];
        foreach ($ticket_sublist as $ticket) {
            $ticket_query_parts[] = "SELECT ".intval($ticket['id']).", ".intval($customer['id']).", '".hesk_dbEscape($customer_type)."'";
            $ticket_ids[] = intval($ticket['id']);
        }
        $sql .= implode(' UNION ', $ticket_query_parts);
        hesk_dbQuery($sql);
    }

    // Update customer_id on non-staff replies
    if ($customer_type === 'REQUESTER') {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
            INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
                ON `replies`.`replyto` = `ticket_to_customer`.`ticket_id`
            SET `replies`.`customer_id` = `ticket_to_customer`.`customer_id`
            WHERE `replies`.`replyto` IN (".implode(',', $ticket_ids).")
                AND `staffid` = 0");
    }
}
