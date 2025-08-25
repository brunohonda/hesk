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
define('HESK_PATH','./');
define('HESK_NO_ROBOTS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

hesk_load_database_functions();
hesk_session_start('CUSTOMER');

hesk_dbConnect();

// Is the user already logged in? If so, they shouldn't be needing this page at all.
if (hesk_isCustomerLoggedIn(false) !== null) {
    header('Location: ' . $hesk_settings['hesk_url'] . '/index.php');
    exit();
}

// Tell header to load reCaptcha API if needed
if ($hesk_settings['recaptcha_use'])
{
    define('RECAPTCHA',1);
}

// Are we setting a new password?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_new_password();

    // If we've made it past this point, password reset was successful
    unset($_SESSION['a_iserror']);
    unset($_SESSION['userdata']);
    hesk_process_messages($hesklang['customer_password_reset_successful'], 'login.php', 'SUCCESS');
}

if ( ! isset($_SESSION['a_iserror'])) {
    $_SESSION['a_iserror'] = array();
}

// Verify the provided hash.  Don't even provide a reset form if the hash is invalid.
$hash = hesk_REQUEST('hash', '');
$hash_valid = hesk_verify_customer_password_reset_hash($hash);
if (!$hash_valid['success']) {
    hesk_process_messages($hash_valid['content'], 'NOREDIRECT');
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/reset-password.php', [
    'validationFailures' => hesk_SESSION_array('a_iserror'),
    'messages' => hesk_get_messages(),
    'validHash' => $hash_valid['success'],
    'resetPasswordHash' => $hash
]);
unset($_SESSION['a_iserror']);
unset($_SESSION['userdata']);

function handle_new_password() {
    global $hesk_settings, $hesklang;

    $hesk_error_buffer = [];

    // Check anti-SPAM question
    if ($hesk_settings['question_use'])
    {
        $question = hesk_input( hesk_POST('question') );

        if ( strlen($question) == 0)
        {
            $hesk_error_buffer[] = $hesklang['q_miss'];
        }
        elseif (hesk_mb_strtolower($question) != hesk_mb_strtolower($hesk_settings['question_ans']))
        {
            $hesk_error_buffer[] = $hesklang['q_wrng'];
        }
        else
        {
            $_SESSION['c_question'] = $question;
        }
    }

    // Check anti-SPAM image
    if ($hesk_settings['secimg_use'] && !isset($_SESSION['img_verified'])) {
        // Using reCAPTCHA?
        if ($hesk_settings['recaptcha_use']) {
            require(HESK_PATH . 'inc/recaptcha/recaptchalib_v2.php');

            $resp = null;
            $reCaptcha = new ReCaptcha($hesk_settings['recaptcha_private_key']);

            // Was there a reCAPTCHA response?
            if (isset($_POST["g-recaptcha-response"])) {
                $resp = $reCaptcha->verifyResponse(hesk_getClientIP(), hesk_POST("g-recaptcha-response"));
            }

            if ($resp != null && $resp->success) {
                $_SESSION['img_verified']=true;
            } else {
                $hesk_error_buffer[]=$hesklang['recaptcha_error'];
            }
        } else {
            // Using PHP generated image
            $mysecnum = intval(hesk_POST('mysecnum', 0));

            if (empty($mysecnum)) {
                $hesk_error_buffer[]=$hesklang['sec_miss'];
            } else {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                    $_SESSION['img_verified']=true;
                    unset($_SESSION['checksum']);
                } else {
                    $hesk_error_buffer[]=$hesklang['sec_wrng'];
                }
            }
        }
    }

    $password = hesk_input(hesk_POST('password'));
    $password_length = strlen($password);

    if ($password_length < 5) {
        $hesk_error_buffer['password'] = $hesklang['password_not_valid'];
    } else {
        // TODO Should we care about passwords being "too long"?
        $confirm_password = hesk_input(hesk_POST('confirm-password'));
        if ($password !== $confirm_password) {
            $hesk_error_buffer['password'] = $hesklang['passwords_not_same'];
        }
    }

    $hash = hesk_input(hesk_POST('hash'));
    $hash_valid = hesk_verify_customer_password_reset_hash($hash);
    if (!$hash_valid['success']) {
        $hesk_error_buffer['hash'] = $hash_valid['content'];
    }

    // Is this password the same as their old one?
    $existing_passsword_rs = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            WHERE `id` = ".intval($hash_valid['content']));
    $existing_password = hesk_dbFetchAssoc($existing_passsword_rs);
    if (hesk_password_verify($password, $existing_password['pass'])) {
        $hesk_error_buffer['password'] = $hesklang['customer_edit_pass_same'];
    }

    // Validation errors?
    if (count($hesk_error_buffer)) {
        $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);
        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $hesk_error_buffer = $tmp;
        $hesk_buffer_string = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_buffer_string, 'reset_password.php?hash='.$hash);
        return;
    }
    unset($_SESSION['img_verified']);
    $hashed_password = hesk_password_hash($password);

    // Update their password
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            SET `pass` = '".hesk_dbEscape($hashed_password)."',
                `verification_token` = NULL,
                `verified` = 1
            WHERE `id` = ".intval($hash_valid['content']));

    // Purge existing reset hashes
    hesk_verify_customer_password_reset_hash($hash, true);
}
