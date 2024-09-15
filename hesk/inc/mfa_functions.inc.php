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

use RobThree\Auth\TwoFactorAuth;

if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

// region Email Authentication
function generate_mfa_code() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function hash_and_store_mfa_verification_code($user_id, $verification_code) {
    global $hesk_settings;

    $hashed_verification_code = hesk_Pass2Hash($verification_code);

    // Allow 20 minutes to verify ("INTERVAL 20 MINUTE" is **very** intuitive...)
    hesk_dbQuery("INSERT INTO `" . hesk_dbEscape($hesk_settings['db_pfix']) . "mfa_verification_tokens` (`user_id`, `verification_token`, `expires_at`)
                    VALUES (" . intval($user_id) . ", '" . hesk_dbEscape($hashed_verification_code) . "', NOW() + INTERVAL 20 MINUTE)");
}

function send_mfa_email($name, $email, $verification_code) {
    global $hesk_settings, $hesklang;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return '';
    }

    // Prepare and send email
    if (!function_exists('hesk_mail')) {
        require(HESK_PATH . 'inc/email_functions.inc.php');
    }

    // Get the email message
    list($msg, $html_msg) = hesk_getEmailMessage('mfa_verification',array(),1,0,1);

    // Replace message special tags
    $name = hesk_msgToPlain($name, 1, 1);
    list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $name, $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FIRST_NAME%%', hesk_full_name_to_first_name($name), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%VERIFICATION_CODE%%', $verification_code, $msg, $html_msg);

    // Send email
    return hesk_mail($email, str_replace('%%VERIFICATION_CODE%%', $verification_code, $hesklang['mfa_verification']), $msg, $html_msg);
}

// Note: Deletes all verification codes upon successful validation
function is_mfa_email_code_valid($user_id, $verification_code) {
    global $hesk_settings;

    $hashed_verification_code = hesk_Pass2Hash($verification_code);

    //-- Purge any verification codes that are expired
    hesk_dbQuery("DELETE FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "mfa_verification_tokens` 
            WHERE `expires_at` < NOW()");

    //-- Also purge all but the last 3 codes for a given user
    $existing_tokens_rs = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id` = ".intval($user_id)." ORDER BY `id` DESC");
    $index = 0;
    while ($row = hesk_dbFetchAssoc($existing_tokens_rs)) {
        if ($index++ < 3) {
            continue;
        }

        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `id` = ".intval($row['id']));
    }

    //-- Check if our verification code is still there
    $res = hesk_dbQuery("SELECT 1 FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "mfa_verification_tokens` 
            WHERE `user_id` = " . intval($user_id) . " 
            AND `verification_token` = '" . hesk_dbEscape($hashed_verification_code) . "'");

    if (hesk_dbNumRows($res) === 1) {
        delete_mfa_codes($user_id);
        return true;
    }

    return false;
}

function delete_mfa_codes($user_id) {
    global $hesk_settings;

    hesk_dbQuery("DELETE FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "mfa_verification_tokens`
                WHERE `user_id` = " . intval($user_id));
}
// endregion

// region App-based Authentication
function build_tfa_instance() {
    global $hesk_settings;

    return new TwoFactorAuth($hesk_settings['hesk_title']);
}

function is_mfa_app_code_valid($user_id, $verification_code, $secret = null) {
    global $hesk_settings, $hesklang;

    $tfa = build_tfa_instance();

    try {
        $tfa->ensureCorrectTime(array(new \RobThree\Auth\Providers\Time\HttpTimeProvider()));
    } catch (\RobThree\Auth\TwoFactorAuthException $e) {
        hesk_error(sprintf($hesklang['mfa_server_time_issue'], $e->getMessage()));
    }

    if ($secret === null) {
        $res = hesk_dbQuery("SELECT `mfa_secret` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `id` = " . intval($user_id));
        $row = hesk_dbFetchAssoc($res);
        $secret = $row['mfa_secret'];
    }

    return $tfa->verifyCode($secret, $verification_code);
}
// endregion

// region Backup Codes
function generate_and_store_mfa_backup_codes($user_id, $delete_old_codes = true) {
    global $hesk_settings;

    if ($delete_old_codes) {
        delete_mfa_backup_codes($user_id);
    }

    $codes = array();
    for ($code_index = 0; $code_index < 10; $code_index++) {
        $unique_code_generated = false;
        do {
            $code = generate_backup_code();
            if (!in_array($code, $codes)) {
                $codes[] = $code;
                $unique_code_generated = true;
                store_backup_code($user_id, $code);
            }
        } while (!$unique_code_generated);
    }

    return $codes;
}

function generate_backup_code() {
    $valid_chars = '0123456789abcdef';
    $code = '';
    for ($char_index = 0; $char_index < 8; $char_index++) {
        $code .= $valid_chars[random_int(0, strlen($valid_chars) - 1)];
    }

    return $code;
}

function store_backup_code($user_id, $code) {
    global $hesk_settings;

    $hashed_code = hesk_password_hash($code);

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` (`user_id`, `code`)
        VALUES (".intval($user_id).", '".hesk_dbEscape($hashed_code)."')");
}

function delete_mfa_backup_codes($user_id) {
    global $hesk_settings;

    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` WHERE `user_id` = ".intval($user_id));
}

function delete_mfa_backup_code($user_id, $hashed_code) {
    global $hesk_settings;

    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` 
        WHERE `user_id` = ".intval($user_id)." AND `code` = '".hesk_dbEscape($hashed_code)."'");
}

function verify_mfa_backup_code($user_id, $code) {
    global $hesk_settings;

    // Allow spaces, dashes, etc... in the backup code for easier printout
    $code = preg_replace('/[^0-9a-f]/', '', strtolower($code));

    $res = hesk_dbQuery("SELECT `code` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` WHERE `user_id` = ".intval($user_id));
    while ($row = hesk_dbFetchAssoc($res)) {
        if (hesk_password_verify($code, $row['code'])) {
            delete_mfa_backup_code($user_id, $row['code']);
            return true;
        }
    }

    return false;
}
// endregion
