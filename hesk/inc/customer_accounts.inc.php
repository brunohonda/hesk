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

use RobThree\Auth\TwoFactorAuth;

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

function hesk_get_customer_account_by_name($name) {
    global $hesk_settings;

    // Verified = 1 (approved, verified) | 2 (pending approval)
    // Verified = 0, but with verification_token (pending verification)
    $sql = "SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "customers`
            WHERE `name` = '" . hesk_dbEscape($name) . "'
                AND TRIM(`email`) = ''";

    $rs = hesk_dbQuery($sql);

    if ($row = hesk_dbFetchAssoc($rs)) {
        return $row;
    }

    return null;
}

function hesk_get_customer_account_by_email($email, $registration = false, $verified_only = false) {
    global $hesk_settings;

    // Verified = 1 (approved, verified) | 2 (pending approval)
    // Verified = 0, but with verification_token (pending verification)
    $sql = "SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "customers`
            WHERE `email` LIKE '" . hesk_dbEscape($email) . "'";

    // If we're registering, we should only check if an existing account is verified or pending verification
    if ($registration) {
        $sql .= " AND (`verified` IN (1, 2) OR (`verified` = 0 AND `verification_token` IS NOT NULL))";
    }

    // Only return verified accounts?
    if ($verified_only) {
        $sql .= " AND `verified` = '1' ";
    }

    $rs = hesk_dbQuery($sql);

    if ($row = hesk_dbFetchAssoc($rs)) {
        if (empty($row['email'])) {
            $row['email'] = '';
        }
        return $row;
    }

    return null;
}

function hesk_get_customer_account_by_id($id) {
    global $hesk_settings;

    $sql = "SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "customers` 
            WHERE `id` = " . intval($id);

    $rs = hesk_dbQuery($sql);

    if ($row = hesk_dbFetchAssoc($rs)) {
        if (empty($row['email'])) {
            $row['email'] = '';
        }
        return $row;
    }

    return null;
}

function hesk_get_or_create_customer($name, $email, $create_if_not_found = true) {
    global $hesk_settings, $hesklang;

    $name = $name === null ? '' : trim($name);
    $email = $email === null ? '' : $email;

    // If email is empty just create a new account
    if (empty($email)) {
        if ($create_if_not_found) {
            hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`name`) VALUES ('".hesk_dbEscape($name)."')");
            return hesk_dbInsertID();
        }
        return null;
    }

    //-- If we already have a customer record based on name and email, return its id
    $existing_customer_rs = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `email` = '".hesk_dbEscape(trim($email))."'
            AND `name` = '".hesk_dbEscape(trim($name))."'
        LIMIT 1");
    if ($row = hesk_dbFetchAssoc($existing_customer_rs)) {
        return $row['id'];
    }

    //-- No match on name+email.  How about email, ignoring the name?
    $existing_customer_rs = hesk_dbQuery("SELECT `id`, `name`, `verified` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
        WHERE `email` = '".hesk_dbEscape(trim($email))."'
        LIMIT 1");
    if ($row = hesk_dbFetchAssoc($existing_customer_rs)) {
        if (intval($row['verified']) === 0 && $row['name'] !== $name && $name !== '' && $name !== $hesklang['pde']) {
            // Name on an unverified account is different. Update it to the name passed in
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
                SET `name` = '".hesk_dbEscape($name)."' 
                WHERE `id` = " . intval($row['id']));
        }

        return $row['id'];
    }

    //-- No match.  Create a new customer if the user wants to
    if ($create_if_not_found) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`name`, `email`)
        VALUES ('".hesk_dbEscape(trim($name))."', '".hesk_dbEscape(trim($email))."')");

        return hesk_dbInsertID();
    }

    return null;
}

function hesk_get_or_create_follower($email) {
    global $hesk_settings;

    $email = $email === null ? '' : $email;

    //-- If we already have a customer record based on email, return its id
    $existing_customer_rs = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
        WHERE `email` = '".hesk_dbEscape(trim($email))."'
        LIMIT 1");
    if ($row = hesk_dbFetchAssoc($existing_customer_rs)) {
        return $row['id'];
    }

    //-- No match.  Create a new customer
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`name`, `email`)
        VALUES ('', '".hesk_dbEscape(trim($email))."')");

    return hesk_dbInsertID();
}

function hesk_get_customer_id_by_email($email, $verified_only = false) {
    global $hesk_settings;

    $sql = "SELECT `id` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "customers`
            WHERE `email` LIKE '" . hesk_dbEscape($email) . "'";

    // Only return verified accounts?
    if ($verified_only) {
        $sql .= " AND `verified` = '1' ";
    }

    $rs = hesk_dbQuery($sql);

    if ($row = hesk_dbFetchAssoc($rs)) {
        return $row['id'];
    }

    return null;
}

function hesk_verify_customer_account($email, $verification_token) {
    global $hesk_settings;

    $sql = "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `verified` = 1,
            `verification_token` = NULL
        WHERE `email` = '".hesk_dbEscape($email)."'
            AND `verification_token` = '".hesk_dbEscape($verification_token)."'";

    hesk_dbQuery($sql);

    return hesk_dbAffectedRows() === 1;
}

function hesk_merge_customer_accounts($email) {
    global $hesk_settings;

    $destination_customer_id_rs = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `email` = '".hesk_dbEscape($email)."' 
            AND `verified` = 1
            AND `verification_token` IS NULL
        LIMIT 1");
    $row = hesk_dbFetchAssoc($destination_customer_id_rs);
    $destination_customer_id = $row['id'];

    // Migrate ticket mappings to the new customer ID
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
        SET `customer_id` = ".intval($destination_customer_id)."
        WHERE `customer_id` IN (
            SELECT `id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            WHERE `email` = '".hesk_dbEscape($email)."'
                AND `verified` = 0
        )");

    // Migrate ticket replies to the new customer ID
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies`
        SET `customer_id` = ".intval($destination_customer_id)."
        WHERE `customer_id` IN (
            SELECT `id`
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            WHERE `email` = '".hesk_dbEscape($email)."'
                AND `verified` = 0
        )");

    // Delete old customer records
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `email` = '".hesk_dbEscape($email)."'
            AND `verified` = 0");
}

function hesk_mark_account_needing_approval($email) {
    global $hesk_settings;

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `verified` = 2
        WHERE `email` = '".hesk_dbEscape($email)."'");
}

// Very similar to admin/index.php's process_successful_login function, but segregated so we don't mix staff/customer logic
function hesk_process_successful_customer_login($user, $noredirect = false, $is_autologin = false) {
    global $hesk_settings, $hesklang;

    // User authenticated, let's regenerate the session ID
    hesk_session_regenerate_id();

    // Set a tag that will be used to expire sessions after username or password change
    $_SESSION['customer']['session_verify'] = hesk_activeSessionCreateTag($user['email'], $user['pass']);

    // Set data we need for the session
    unset($user['pass']);
    unset($user['mfa_secret']);
    foreach ($user as $k => $v) {
        $_SESSION['customer'][$k] = $v;
    }

    // Reset repeated emails session data
    hesk_cleanSessionVars('mfa_emails_sent');

    /* Login successful, clean brute force attempts */
    hesk_cleanBfAttempts();

    // Give the user some time before requiring re-authentication for sensitive pages
    $current_time = new DateTime();
    $interval_amount = $hesk_settings['elevator_duration'];
    if (in_array(substr($interval_amount, -1), array('M', 'H'))) {
        $interval_amount = 'T'.$interval_amount;
    }
    $elevation_expiration = $current_time->add(new DateInterval("P{$interval_amount}"));
    $_SESSION['customer']['elevated'] = $elevation_expiration;

    // Remember username?
    if (!$is_autologin) {
        if ($hesk_settings['customer_autologin'] && hesk_POST('remember_user') === 'AUTOLOGIN') {
            $selector = base64_encode(random_bytes(9));
            $authenticator = random_bytes(33);
            hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` (`selector`,`token`,`user_id`,`user_type`,`expires`) VALUES ('".hesk_dbEscape($selector)."','".hesk_dbEscape(hash('sha256', $authenticator))."','".intval($_SESSION['customer']['id'])."','CUSTOMER', NOW() + INTERVAL 1 YEAR)");
            hesk_setcookie('hesk_customer_username', '');
            hesk_setcookie('hesk_customer_remember', $selector.':'.base64_encode($authenticator), strtotime('+1 year'));
        } elseif (hesk_POST('remember_user') === 'JUSTUSER') {
            hesk_setcookie('hesk_customer_username', $user['email'], strtotime('+1 year'));
            hesk_setcookie('hesk_customer_remember', '');
        } else {
            hesk_setcookie('hesk_customer_username', '');
            hesk_setcookie('hesk_customer_remember', '');
        }
    }

    /* If session expired while a HESK page is open just continue using it, don't redirect */
    if ($noredirect)
    {
        return true;
    }

    /* Redirect to the destination page */
    header('Location: ' . hesk_verifyGoto('CUSTOMER') );
    exit();
}

// Similar to hesk_isLoggedIn(), but for customers
function hesk_isCustomerLoggedIn($redirect = true) {
    global $hesk_settings;

    // If customer accounts are disabled, no one is ever logged in, and we should simply go back to the index page
    if ( ! $hesk_settings['customer_accounts']) {
        if ( ! $redirect) {
            return null;
        }
        header('Location: index.php');
        exit();
    }

    $referer = hesk_input($_SERVER['REQUEST_URI']);
    $referer = str_replace('&amp;','&',$referer);

    // Customer login URL
    $url = $hesk_settings['hesk_url'] . '/login.php?notice=1&goto='.urlencode($referer);

    if (empty($_SESSION['customer']['id']) || empty($_SESSION['customer']['session_verify'])) {
        //-- We only want to auto-login if we're going to a page that requires authentication
        if ($hesk_settings['customer_autologin'] && $redirect && hesk_customerAutoLogin(true)) {
            return true;
        }
    } else {
        // hesk_session_regenerate_id();

        // Let's make sure user still exists
        $res = hesk_dbQuery( "SELECT `id`, `email`, `pass`, `name`, `email`, `language`, `mfa_enrollment` FROM `".$hesk_settings['db_pfix']."customers` WHERE `id` = '".intval($_SESSION['customer']['id'])."' LIMIT 1" );

        // Exit if user not found
        if (hesk_dbNumRows($res) === 1) {
            // Fetch results from database
            $me = hesk_dbFetchAssoc($res);

            // Verify this session is still valid
            if (hesk_activeSessionValidate($me['email'], $me['pass'], $_SESSION['customer']['session_verify'])) {
                return $me;
            }
        }
    }

    // If we get here, then we're not logged in.
    if ($redirect) {
        // Only destroy the session if redirecting...otherwise things get messed up
        hesk_session_stop();
        header('Location: '.$url);
        exit();
    } else {
        return null;
    }
}

function hesk_handle_customer_password_reset_request($email) {
    global $hesk_settings, $hesklang;

    // Get user data from the database
    $res = hesk_dbQuery("SELECT `id`, `name`, `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `verified`=1 AND `email` = '".hesk_dbEscape($email)."' LIMIT 1");
    if (hesk_dbNumRows($res) != 1)
    {
        hesk_process_messages($hesklang['novace'],'login.php?submittedForgot=1');
    }
    else
    {
        $row = hesk_dbFetchAssoc($res);
        $hash = sha1(microtime() . hesk_getClientIP() . mt_rand() . $row['id'] . $row['name'] . $row['pass']);

        // Insert the verification hash into the database
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` (`user`, `hash`, `ip`, `user_type`) VALUES (".intval($row['id']).", '{$hash}', '".hesk_dbEscape(hesk_getClientIP())."', 'CUSTOMER') ");

        // Prepare and send email
        require_once(HESK_PATH . 'inc/email_functions.inc.php');

        // Get the email message
        list($msg, $html_msg) = hesk_getEmailMessage('customer_reset_password',array(),1,0,1);

        // Replace message special tags
        list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', hesk_msgToPlain($row['name'],1,0), $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'], $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%', $hesk_settings['site_title'], $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%PASSWORD_RESET%%',
            $hesk_settings['hesk_url'].'/reset_password.php?hash='.$hash,
            $msg,
            $html_msg);

        // Check two additional tags (avoid a bug in 3.3.0)
        list($msg, $html_msg) = hesk_replace_email_tag('%25%25PASSWORD_RESET%25%25',
            $hesk_settings['hesk_url'].'/reset_password.php?hash='.$hash,
            $msg,
            $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_URL%%',
            $hesk_settings['hesk_url'].'/reset_password.php?hash='.$hash,
            $msg,
            $html_msg);

        // Send email
        hesk_mail($email, [], $hesklang['customer_reset_password'], $msg, $html_msg);
    }
}

function hesk_verify_customer_password_reset_hash($hash, $purge_user_hashes = false) {
    global $hesk_settings, $hesklang;

    // Get the hash
    $hash = preg_replace('/[^a-zA-Z0-9]/', '', $hash);

    // Expire verification hashes older than 1 hour
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` WHERE `dt` < (NOW() - INTERVAL 1 HOUR)");

    // Verify the hash exists
    $res = hesk_dbQuery("SELECT `user`, `ip` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` WHERE `hash` = '{$hash}' AND `user_type` = 'CUSTOMER' LIMIT 1");
    if (hesk_dbNumRows($res) !== 1) {
        // Not a valid hash
        hesk_limitBfAttempts();
        return [
            'success' => false,
            'content' => $hesklang['ehash']
        ];
    }

    // Get info from database
    $row = hesk_dbFetchAssoc($res);

    // Only allow resetting password from the same IP address that submitted password reset request
    if ($row['ip'] != hesk_getClientIP()) {
        hesk_limitBfAttempts();
        return [
            'success' => false,
            'content' => $hesklang['ehaip']
        ];
    }

    // Expire all verification hashes for this user if requested
    if ($purge_user_hashes) {
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` 
                    WHERE `user_type` = 'CUSTOMER' 
                    AND `user`=".intval($row['user']));
    }

    // Clean brute force attempts
    hesk_cleanBfAttempts();
    return [
        'success' => true,
        'content' => $row['user']
    ];
}

//region MFA
function hesk_remove_mfa_for_customer($customer_id) {
    global $hesk_settings;

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `mfa_enrollment` = 0,
            `mfa_secret` = NULL
        WHERE `id` = ".intval($customer_id));
}
//endregion

function hesk_get_customers_for_ticket($ticket_id) {
    global $hesk_settings;

    $customers_res = hesk_dbQuery("SELECT `customers`.`id`, `customers`.`name`, `customers`.`email`, `customers`.`language`, `ticket_to_customer`.`customer_type`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` `customers`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` `ticket_to_customer`
            ON `customers`.`id` = `ticket_to_customer`.`customer_id`
        WHERE `ticket_to_customer`.`ticket_id` = ".intval($ticket_id));

    $customers = [];
    while ($row = hesk_dbFetchAssoc($customers_res)) {
        if (empty($row['email'])) {
            $row['email'] = '';
        }
        $customers[] = $row;
    }

    if (defined('HESK_DEMO')) {
        array_walk($customers, function(&$k) {
            $k['email'] = 'hidden@demo.com';
        });
    }

    return $customers;
}

function hesk_purge_expired_email_change_requests() {
    global $hesk_settings;

    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `expires_at` < NOW()");
}

function hesk_purge_email_change_requests($user_id) {
    global $hesk_settings;

    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `customer_id` = ".intval($user_id));
}

function hesk_get_pending_email_change_for_user($user_id) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `new_email`,
        CASE
            WHEN `expires_at` > (NOW() + INTERVAL ".intval(60 - $hesk_settings['customer_accounts_verify_email_cooldown'])." MINUTE) THEN 1
            ELSE 0
        END AS `email_sent_too_recently`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `customer_id` = ".intval($user_id)." AND `expires_at` > NOW()");

    if ($row = hesk_dbFetchAssoc($res)) {
        return $row;
    }

    return null;
}

function hesk_get_pending_email_change_for_email($email, $user_id) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `new_email` = '".hesk_dbEscape($email)."' AND `customer_id` <> ".intval($user_id));

    return hesk_dbNumRows($res);
}

function hesk_insert_email_change_request($email, $user_id) {
    global $hesk_settings;

    $verification_token = bin2hex(random_bytes(16));
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes` (`customer_id`,`new_email`,`verification_token`,`expires_at`)
        VALUES (".intval($user_id).", '".hesk_dbEscape($email)."', '".hesk_dbEscape($verification_token)."', NOW() + INTERVAL 60 MINUTE)");

    return $verification_token;
}

function hesk_verify_email_change_request($email, $verification_token) {
    global $hesk_settings;

    $change_request_rs = hesk_dbQuery("SELECT `id`, `customer_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `new_email` = '".hesk_dbEscape($email)."'
            AND `verification_token` = '".hesk_dbEscape($verification_token)."'
            AND `expires_at` >= NOW()");

    $row = hesk_dbFetchAssoc($change_request_rs);
    if (!$row) {
        return false;
    }

    $sql = "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `email` = '".hesk_dbEscape($email)."',
            `verification_token` = NULL
        WHERE `id` = ".intval($row['customer_id']);
    hesk_dbQuery($sql);

    if (hesk_dbAffectedRows() === 1) {
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."pending_customer_email_changes`
        WHERE `id` = ".intval($row['id']));

        return true;
    }

    return false;
}

function hesk_customerAutoLogin($noredirect = false)
{
    global $hesk_settings, $hesklang, $hesk_db_link;
    $cookie_name = 'hesk_customer_remember';

    if (!$hesk_settings['customer_autologin']) {
        return false;
    }

    if (empty($remember = hesk_COOKIE($cookie_name)) || substr_count($remember, ':') !== 1) {
        return false;
    }

    // Login cookies exist, now lets limit brute force attempts
    hesk_limitBfAttempts();

    // Admin login URL
    $url = $hesk_settings['hesk_url'] . '/login.php?notice=1';

    // Get and verify authentication tokens
    list($selector, $authenticator) = explode(':', $remember);
    $authenticator = base64_decode($authenticator);
    if (strlen($authenticator) > 256) {
        hesk_setcookie($cookie_name, '');
        header('Location: '.$url);
        exit();
    }

    $result = hesk_dbQuery('SELECT * FROM `'.$hesk_settings['db_pfix']."auth_tokens` 
        WHERE `selector` = '".hesk_dbEscape($selector)."' 
            AND `expires` > NOW() 
            AND `user_type` = 'CUSTOMER' 
        LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        hesk_setcookie($cookie_name, '');
        header('Location: '.$url);
        exit();
    }

    $auth = hesk_dbFetchAssoc($result);

    if ( ! hash_equals($auth['token'], hash('sha256', $authenticator))) {
        hesk_setcookie($cookie_name, '');
        header('Location: '.$url);
        exit();
    }

    // Token OK, let's regenerate session ID and get user data
    hesk_session_regenerate_id();

    $result = hesk_dbQuery('SELECT * FROM `'.$hesk_settings['db_pfix']."customers` WHERE `id` = ".intval($auth['user_id'])." LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        hesk_setcookie($cookie_name, '');
        header('Location: '.$url);
        exit();
    }

    $row = hesk_dbFetchAssoc($result);
    $user = $row['email'];
    define('HESK_USER_CUSTOMER', $user);

    // Change language?
    if ( ! empty($row['language']) && $hesk_settings['language'] != $row['language']) {
        hesk_setLanguage($row['language']);
        hesk_setcookie('hesk_language',$row['language'],time()+31536000,'/');
    }

    // Each token should only be used once, so update the old one with a new one
    $selector = base64_encode(random_bytes(9));
    $authenticator = random_bytes(33);
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` SET `selector`='".hesk_dbEscape($selector)."', `token` = '".hesk_dbEscape(hash('sha256', $authenticator))."', `created` = NOW() WHERE `id` = ".intval($auth['id']));
    hesk_setcookie($cookie_name, $selector.':'.base64_encode($authenticator), strtotime('+1 year'));

    // Set a tag that will be used to expire sessions after username or password change
    $_SESSION['customer']['session_verify'] = hesk_activeSessionCreateTag($user, $row['pass']);

    /* Login successful, clean brute force attempts */
    hesk_cleanBfAttempts();

    return hesk_process_successful_customer_login($row, $noredirect, true);
} // END hesk_customerAutoLogin()
