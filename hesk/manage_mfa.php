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

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/statuses.inc.php');
require_once(HESK_PATH . 'inc/mfa_functions.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

// Are we in "Knowledgebase only" mode?
hesk_check_kb_only();
hesk_session_start('CUSTOMER');
hesk_load_database_functions();
hesk_dbConnect();

$user_context = hesk_isCustomerLoggedIn();

// Demo mode
if ( defined('HESK_DEMO') ) {
    hesk_process_messages($hesklang['ddemo'], 'profile.php', 'NOTICE');
}

if ( ! isset($_SESSION['customer']['mfa_enrollment'])) {
    $_SESSION['customer']['mfa_enrollment'] = 0;
}

hesk_check_user_elevation('manage_mfa.php', true);

$current_step = intval(hesk_POST('current-step'));
if ($current_step > 0) {
    $tfa = build_tfa_instance();
    if ($current_step === 1) {
        handle_mfa_method_selection($tfa, $user_context);
    } elseif ($current_step === 2) {
        handle_mfa_verification($tfa, $user_context);
    }
    return;
} elseif (hesk_POST('delete_codes') === 'Y') {
    hesk_token_check();
    delete_mfa_backup_codes($_SESSION['customer']['id'], 'CUSTOMER');
    hesk_process_messages($hesklang['mfa_del_codes2'], 'NOREDIRECT', 'SUCCESS');
    $display_step = 1;
    $output_at_top = 1;
} elseif (hesk_POST('new_codes') === 'Y') {
    hesk_token_check();
    delete_mfa_backup_codes($_SESSION['customer']['id'], 'CUSTOMER');
    $new_mfa_backup_codes = generate_and_store_mfa_backup_codes($_SESSION['customer']['id'], true, 'CUSTOMER');
    $backup_codes = implode("\n", array_map(function($code, $key) { return str_pad(($key+1), 2, ' ', STR_PAD_LEFT) . '. ' . substr($code, 0, 4) . '-' . substr($code, 4); }, $new_mfa_backup_codes, array_keys($new_mfa_backup_codes)));
    hesk_process_messages($hesklang['mfa_new_codes2'] . '<p style="margin-top:10px">'.$hesklang['mfa_backup_codes_description'].'</p><pre style="margin-top:20px; font-family: monospace; font-size: 16px;">'.$backup_codes.'</pre>', 'NOREDIRECT', 'SUCCESS');
    $display_step = 1;
    $output_at_top = 1;
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/method.php', array(
    'messages' => hesk_get_messages(),
    'customerUserContext' => $user_context
));

function handle_mfa_method_selection($tfa, $user_context) {
    global $hesklang, $hesk_settings;

    //-- Either removing MFA or setting it up
    $mfa_method = intval(hesk_POST('mfa-method', 0));
    if ($mfa_method === 0) {
        hesk_remove_mfa_for_customer($_SESSION['customer']['id']);
        delete_mfa_codes($_SESSION['customer']['id'], 'CUSTOMER');
        delete_mfa_backup_codes($_SESSION['customer']['id'], 'CUSTOMER');
        $_SESSION['customer']['mfa_enrollment'] = 0;

        $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/complete.php', array(
            'messages' => hesk_get_messages(),
            'customerUserContext' => $user_context,
            'model' => [
                'backupCodes' => []
            ]
        ));
        return;
    }

    if ($mfa_method === 1) {
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($_SESSION['customer']['id'], $verification_code, 'CUSTOMER');
        $email_sent = send_mfa_email($_SESSION['customer']['name'], $_SESSION['customer']['email'], $verification_code);

        $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/verify.php', array(
            'mfaMethod' => 'EMAIL',
            'model' => [
                'emailSent' => $email_sent
            ],
            'messages' => hesk_get_messages(),
            'customerUserContext' => $user_context
        ));
        return;
    }

    if ($mfa_method === 2) {
        $_SESSION['customer']['tfa_secret'] = $tfa->createSecret();
        $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/verify.php', array(
            'mfaMethod' => 'AUTH-APP',
            'model' => [
                'secret' => $_SESSION['customer']['tfa_secret'],
                'qrCodeUri' => function_exists('curl_init') ? $tfa->getQRCodeImageAsDataUri($hesk_settings['hesk_title'], $_SESSION['customer']['tfa_secret']) : false,
            ],
            'messages' => hesk_get_messages(),
            'customerUserContext' => $user_context
        ));
        return;
    }

    hesk_process_messages($hesklang['mfa_invalid_method'], 'manage_mfa.php');
}

function handle_mfa_verification($tfa, $user_context) {
    global $hesklang, $hesk_settings;

    // Template returns either 'EMAIL' or 'AUTH-APP'; not 1 nor 2.
    $mfa_method = hesk_POST('mfa-method');
    if ($mfa_method === 'EMAIL') {
        //-- Email
        $verification_code = hesk_POST('verification-code');
        if (is_mfa_email_code_valid($_SESSION['customer']['id'], $verification_code, 'CUSTOMER')) {
            //-- Enable MFA for the user and delete the verification code
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
                SET `mfa_enrollment` = 1
                WHERE `id` = ".intval($_SESSION['customer']['id']));
            $_SESSION['customer']['mfa_enrollment'] = 1;
            $_SESSION['customer']['mfa_backup_codes'] = generate_and_store_mfa_backup_codes($_SESSION['customer']['id'], true, 'CUSTOMER');

            $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/complete.php', array(
                'messages' => hesk_get_messages(),
                'customerUserContext' => $user_context,
                'model' => [
                    'backupCodes' => $_SESSION['customer']['mfa_backup_codes']
                ]
            ));
        } else {
            //-- Invalid code entered
            hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/verify.php', array(
                'mfaMethod' => 'EMAIL',
                'model' => [
                    'emailSent' => false
                ],
                'messages' => hesk_get_messages(),
                'customerUserContext' => $user_context
            ));
        }
    } elseif ($mfa_method === 'AUTH-APP') {
        $secret = $_SESSION['customer']['tfa_secret'];
        if (is_mfa_app_code_valid($_SESSION['customer']['id'], hesk_POST('verification-code'), $secret, 'CUSTOMER')) {
            hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
                SET `mfa_enrollment` = 2,
                    `mfa_secret` = '".hesk_dbEscape($secret)."'
                WHERE `id` = ".intval($_SESSION['customer']['id']));
            $_SESSION['customer']['mfa_backup_codes'] = generate_and_store_mfa_backup_codes($_SESSION['customer']['id'], true, 'CUSTOMER');
            unset($_SESSION['customer']['tfa_secret']);
            $_SESSION['customer']['mfa_enrollment'] = 2;
            $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/complete.php', array(
                'messages' => hesk_get_messages(),
                'customerUserContext' => $user_context,
                'model' => [
                    'backupCodes' => $_SESSION['customer']['mfa_backup_codes']
                ]
            ));
        } else {
            //-- Invalid code entered
            hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/mfa/verify.php', array(
                'mfaMethod' => 'AUTH-APP',
                'model' => [
                    'secret' => $_SESSION['customer']['tfa_secret'],
                    'qrCodeUri' => function_exists('curl_init') ? $tfa->getQRCodeImageAsDataUri($hesk_settings['hesk_title'], $_SESSION['customer']['tfa_secret']) : false,
                ],
                'messages' => hesk_get_messages(),
                'customerUserContext' => $user_context
            ));
        }
    } else {
        hesk_process_messages($hesklang['mfa_invalid_method'], 'manage_mfa.php');
    }
}
