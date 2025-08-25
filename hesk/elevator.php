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

define('IN_SCRIPT',1);
define('HESK_PATH','./');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/mfa_functions.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');
hesk_load_database_functions();

hesk_session_start('CUSTOMER');
hesk_dbConnect();

$customerUserContext = hesk_isCustomerLoggedIn();

$mfa_enrollment = intval($_SESSION['customer']['mfa_enrollment']);
$skip_email = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hesk_POST('a') === 'backup_email') {
        // Force email verification instead of authenticator code
        $mfa_enrollment = 1;
        $force_send_email = true;

        // Let's limit the "Send another email" to max 3
        if (isset($_SESSION['customer']['mfa_emails_sent'])) {
            if ($_SESSION['customer']['mfa_emails_sent'] >= 3) {
                hesk_forceLogoutCustomer($hesklang['bf_int']);
            }
            $_SESSION['customer']['mfa_emails_sent']++;
        } else {
            $_SESSION['customer']['mfa_emails_sent'] = 1;
        }
    } elseif (hesk_POST('a') === 'verify') {
        $skip_email = true;
        $mfa_method = hesk_POST('mfa-method');
        if ($mfa_method === 'PASSWORD') {
            $pass = hesk_input( hesk_POST('verification-code') );
            if ( ! $pass) {
                $error = $hesklang['enter_pass'];
            } else {
                hesk_limitInternalBfAttempts();
                if (hesk_password_verify($pass, fetch_current_user_password())) {
                    hesk_cleanBfAttempts();
                    handle_successful_elevation();
                } else {
                    $error = $hesklang['wrong_pass'];
                }
            }
            hesk_process_messages($error, 'NOREDIRECT');
        } else {
            hesk_limitInternalBfAttempts();
            if (($mfa_method === 'EMAIL' && is_mfa_email_code_valid($_SESSION['customer']['id'], hesk_POST('verification-code'), 'CUSTOMER')) ||
                ($mfa_method === 'AUTH-APP' && is_mfa_app_code_valid($_SESSION['customer']['id'], hesk_POST('verification-code'), null, 'CUSTOMER'))) {
                hesk_cleanBfAttempts();
                handle_successful_elevation();
            } else {
                // Verification failed
                hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            }
        }
    } elseif (hesk_POST('a') === 'do_backup_code_verification') {
        $skip_email = true;
        hesk_limitInternalBfAttempts();
        if (verify_mfa_backup_code($_SESSION['customer']['id'], hesk_POST('backup-code'), 'CUSTOMER')) {
            hesk_cleanBfAttempts();
            handle_successful_elevation();
        } else {
            // Verification failed
            hesk_process_messages($hesklang['mfa_invalid_backup_code'], 'NOREDIRECT');
        }
    } else {
        // Invalid action, something strange is going on... Let's force logout
        hesk_forceLogoutCustomer($hesklang['invalid_action']);
    }
}

$message = '';

if ($mfa_enrollment === 0) {
    $mfa_verify_option = 'PASSWORD';
    $message .= $hesklang['elevator_enter_password'];
} elseif ($mfa_enrollment === 1) {
    // Email
    $mfa_verify_option = 'EMAIL';

    // Unless the "Send another email" link was clicked, don't send a new email until the old one is valid
    if (! $skip_email && empty($force_send_email) && isset($_SESSION['customer']['skip_mfa_emails_until']) && $_SESSION['customer']['skip_mfa_emails_until'] > date('Y-m-d H:i:s')) {
        $skip_email = true;
    }

    // Don't send a new email each time a verification fails
    if (! $skip_email) {
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($_SESSION['customer']['id'], $verification_code, 'CUSTOMER');
        send_mfa_email($_SESSION['customer']['name'], $_SESSION['customer']['email'], $verification_code);

        hesk_process_messages($hesklang['mfa_sent'], 'NOREDIRECT', 'INFO');

        // Don't send a new email until the old one is valid (with 15 min buffer) unless explicitly asked to
        $skip_mfa_emails_until = new DateTime();
        $skip_mfa_emails_until->add(new DateInterval('PT15M'));
        $_SESSION['customer']['skip_mfa_emails_until'] = $skip_mfa_emails_until->format('Y-m-d H:i:s');
    }

    $message .= $hesklang['mfa_verification_needed_email'];
} elseif ($mfa_enrollment === 2) {
    // Authenticator App
    $message .= $hesklang['mfa_verification_needed_auth_app'];
    $mfa_verify_option = 'AUTH-APP';
}

$messages = hesk_get_messages();


$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/elevator.php', array(
    'message' => $message,
    'messages' => $messages,
    'customerUserContext' => $customerUserContext,
    'verificationMethod' => $mfa_verify_option
));

function fetch_current_user_password() {
    global $hesk_settings, $hesklang;

    $res = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($_SESSION['customer']['id'])." LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        hesk_forceLogoutCustomer($hesklang['wrong_user']);
    }

    $row = hesk_dbFetchAssoc($res);

    return $row['pass'];
}

function handle_successful_elevation() {
    global $hesk_settings;

    hesk_session_regenerate_id();
    hesk_cleanBfAttempts();
    delete_mfa_codes($_SESSION['customer']['id'], 'CUSTOMER');
    hesk_cleanSessionVars('mfa_emails_sent');
    hesk_cleanSessionVars('skip_mfa_emails_until');

    $current_time = new DateTime();
    $interval_amount = $hesk_settings['elevator_duration'];
    if (in_array(substr($interval_amount, -1), array('M', 'H'))) {
        $interval_amount = 'T'.$interval_amount;
    }
    $elevation_expiration = $current_time->add(new DateInterval("P{$interval_amount}"));

    $_SESSION['customer']['elevated'] = $elevation_expiration;
    $elevator_target = hesk_SESSION('elevator_target', 'index.php');
    unset($_SESSION['customer']['elevator_target']);
    header('Location: ' . $elevator_target);
    exit();
}

exit();
