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

// Are customer accounts enabled? If not, redirect
if (!$hesk_settings['customer_accounts']) {
    return hesk_process_messages($hesklang['customer_accounts_disabled'], 'index.php');
}

// Are customers allowed to create their own accounts? If not, redirect
if (!$hesk_settings['customer_accounts_customer_self_register']) {
    return hesk_process_messages($hesklang['customer_accounts_registration_disabled'], 'index.php');
}

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

// Are we creating a new account?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Demo mode
    if ( defined('HESK_DEMO') ) {
        hesk_process_messages($hesklang['ddemo'], 'register.php', 'NOTICE');
    }

    handle_registration();

    // If we've made it past this point, registration was successful
    hesk_process_messages($hesklang['customer_registration_successful'], 'NOREDIRECT', 'SUCCESS');
    $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/register-success.php', [
        'messages' => hesk_get_messages(),
        'serviceMessages' => hesk_get_service_messages('c-ok', $hesk_settings['customer_accounts_required'] == 2),
        'model' => hesk_SESSION_array('userdata')
    ]);
    unset($_SESSION['iserror']);
    unset($_SESSION['userdata']);
    return;
}

$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/register.php', [
    'validationFailures' => hesk_SESSION_array('iserror'),
    'messages' => hesk_get_messages(),
    'serviceMessages' => hesk_get_service_messages('c-register', $hesk_settings['customer_accounts_required'] == 2),
    'model' => hesk_SESSION_array('userdata')
]);
unset($_SESSION['iserror']);
unset($_SESSION['userdata']);

function handle_registration() {
    global $hesk_settings, $hesklang;

    $hesk_error_buffer = array();

    // Check anti-SPAM question
    if ($hesk_settings['question_use'])
    {
        $question = hesk_input( hesk_POST('question') );

        if ( strlen($question) == 0)
        {
            $hesk_error_buffer['question'] = $hesklang['q_miss'];
        }
        elseif (hesk_mb_strtolower($question) != hesk_mb_strtolower($hesk_settings['question_ans']))
        {
            $hesk_error_buffer['question'] = $hesklang['q_wrng'];
        }
        else
        {
            $_SESSION['c_question'] = $question;
        }
    }

    // Check anti-SPAM image
    if ($hesk_settings['secimg_use'] && ! isset($_SESSION['img_verified'])) {
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
                $hesk_error_buffer['mysecnum']=$hesklang['recaptcha_error'];
            }
        } else {
            // Using PHP generated image
            $mysecnum = intval(hesk_POST('mysecnum', 0));

            if (empty($mysecnum)) {
                $hesk_error_buffer['mysecnum']=$hesklang['sec_miss'];
            } else {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                    $_SESSION['img_verified']=true;
                    unset($_SESSION['checksum']);
                } else {
                    $hesk_error_buffer['mysecnum']=$hesklang['sec_wrng'];
                }
            }
        }
    }

    $name = hesk_input(hesk_POST('name'));
    if ($name) {
        $myuser['name'] = $name;
    } else {
        $hesk_error_buffer['name'] = $hesklang['enter_real_name'];
    }

    $email = hesk_validateEmail(hesk_POST('email'), 'ERR', 0);
    if ($email) {
        $myuser['email'] = $email;
    } else {
        $hesk_error_buffer['email'] = $hesklang['enter_valid_email'];
    }

    // Are we banned?
    if (hesk_isBannedEmail($email) || hesk_isBannedIP(hesk_getClientIP())) {
        // Don't bother validating the rest at this point
        hesk_process_messages($hesklang['customer_accounts_email_banned'], 'register.php');
        return;
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

    // Do we have an existing customer record (could be either a duplicate, or someone who lost their verification email
    $existing_account = null;
    if (isset($myuser['email'])) {
        $existing_account = hesk_get_customer_account_by_email($myuser['email'], true);
        if ($existing_account !== null) {
            if ($existing_account['verified'] == 0) {
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists_pending_email_verification'], $myuser['email']);
            } elseif ($existing_account['verified'] == 1) {
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists'], $myuser['email']);
            } else {
                $hesk_error_buffer['email'] = sprintf($hesklang['customer_registration_email_exists_pending_approval'], $myuser['email']);
            }
        }
    }

    $_SESSION['userdata'] = $myuser;

    // Validation errors?
    if (count($hesk_error_buffer)) {
        $_SESSION['iserror'] = array_keys($hesk_error_buffer);

        $tmp = '';
        foreach ($hesk_error_buffer as $error) {
            $tmp .= "<li>$error</li>\n";
        }

        $hesk_error_buffer = $hesklang['pcer'] . '<br /><br /><ul>' . $tmp . '</ul>';
        hesk_process_messages($hesk_error_buffer, 'register.php');
    }

    // Generate a verification token for this user
    $verification_token = bin2hex(random_bytes(16));
    $hashed_password = hesk_password_hash($password);

    $myuser['language'] = HESK_DEFAULT_LANGUAGE;
    if ($hesk_settings['can_sel_lang']) {
        $myuser['language'] = $hesklang['LANGUAGE'];
    }

    if ($existing_account === null) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (`pass`, `name`, 
            `email`, `language`, `verified`, `verification_token`, `verification_email_sent_at`) VALUES (
                '".hesk_dbEscape($hashed_password)."',
                '".hesk_dbEscape($myuser['name'])."',
                '".hesk_dbEscape($myuser['email'])."',
                '".hesk_dbEscape($myuser['language'])."',
                0,
                '".hesk_dbEscape($verification_token)."',
                NOW()
            )");
    }
    unset($_SESSION['img_verified']);

    // Send verification email to the user
    require_once(HESK_PATH . 'inc/email_functions.inc.php');
    hesk_sendCustomerRegistrationEmail($myuser, $verification_token);
}
