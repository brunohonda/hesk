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
require(HESK_PATH . 'inc/mfa_functions.inc.php');
require(HESK_PATH . 'inc/email_functions.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

hesk_load_database_functions();
hesk_session_start('CUSTOMER');
hesk_dbConnect();

// Are customer accounts enabled? If not, redirect
if (!$hesk_settings['customer_accounts']) {
    return hesk_process_messages($hesklang['customer_accounts_disabled'], 'index.php');
}

// Are actually logging out (and not in?)
if (hesk_GET('a') === 'logout') {
    if (hesk_isCustomerLoggedIn(false) === null) {
        // User isn't even logged in; just redirect
        header('Location: ' . $hesk_settings['hesk_url'] . '/index.php');
        exit();
    }

    // Clear user's tokens
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` 
        WHERE `user_id` = ".intval($_SESSION['customer']['id'])." 
        AND `user_type` = 'CUSTOMER'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens`
        WHERE `user_id` = ".intval($_SESSION['customer']['id'])."
        AND `user_type` = 'CUSTOMER'");

    /* Destroy session and cookies */
    hesk_session_stop();
    hesk_session_start('CUSTOMER');

    /* Show success message and reset the cookie */
    hesk_setcookie('hesk_customer_username', '');
    hesk_setcookie('hesk_customer_remember', '');
    hesk_process_messages($hesklang['logout_success'],$hesk_settings['hesk_url'] . '/index.php','SUCCESS');
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

if (hesk_isREQUEST('notice')) {
    if ($hesk_settings['customer_accounts_customer_self_register']) {
        hesk_process_messages($hesklang['customer_must_be_logged_in_to_view'] . ' ' . $hesklang['customer_register_here'],'NOREDIRECT','NOTICE');
    } else {
        hesk_process_messages($hesklang['customer_must_be_logged_in_to_view'],'NOREDIRECT','NOTICE');
    }
}

$savedUser = hesk_REQUEST('email', '');
if ($savedUser === '') {
    $savedUser = hesk_SESSION('login_email');
}

if ($savedUser === '') {
    if (defined('HESK_USER_CUSTOMER')) {
        $savedUser = HESK_USER_CUSTOMER;
    } else {
        $savedUser = hesk_htmlspecialchars(hesk_COOKIE('hesk_customer_username'));
    }
}

$model = [
    'email' => $savedUser
];

$action = hesk_REQUEST('a');
if ($action !== false) {
    switch ($action) {
        case 'forgot_password':
            handle_reset_password_request();
            break;
        case 'login':
            handle_login_request();
            return;
        case 'mfa_verify':
            handle_mfa_verification();
            return;
        case 'mfa_backup_code':
            handle_mfa_backup_code();
            return;
        case 'mfa_backup_email':
            send_mfa_backup_email();
            return;
        case 'resend_verification_email':
            resend_verification_email();
            return;
    }
} else {
    hesk_customerAutoLogin();
}

$messages = hesk_get_messages();

if ( ! isset($_SESSION['a_iserror'])) {
    $_SESSION['a_iserror'] = array();
}

$remember_user = hesk_POST('remember_user');
$select_autologin = $hesk_settings['customer_autologin'] && (isset($_COOKIE['hesk_customer_remember']) || $remember_user === 'AUTOLOGIN');
$select_save_username = isset($_COOKIE['hesk_customer_username']) || $remember_user === 'JUSTUSER';
$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/account/login.php', array(
    'messages' => $messages,
    'serviceMessages' => hesk_get_service_messages('c-login', $hesk_settings['customer_accounts_required'] == 2),
    'model' => $model,
    'validationFailures' => hesk_SESSION_array('a_iserror'),
    'displayForgotPasswordLink' => $hesk_settings['reset_pass'],
    'displayForgotPasswordModal' => !empty($_REQUEST['forgot']),
    'submittedForgotPasswordForm' => !empty($_REQUEST['submittedForgot']),
    'redirectUrl' => hesk_REQUEST('goto', ''),
    'allowAutologin' => $hesk_settings['customer_autologin'] === 1,
    'selectAutologin' => $select_autologin,
    'selectSaveEmail' => $select_save_username,
    'selectDoNotRemember' => !($select_autologin || $select_save_username)
));

hesk_cleanSessionVars('login_email');
hesk_cleanSessionVars('a_iserror');

function handle_login_request() {
    global $hesk_settings, $hesklang;

    hesk_dbConnect();

    /* Limit brute force attempts */
    hesk_limitBfAttempts();

    // Validation checks
    validate_data();

    // Only fetch users who are verified in some way, or are pending verification
    $res = hesk_dbQuery("SELECT `customers`.*,
            CASE
                WHEN `verification_email_sent_at` + INTERVAL ".intval($hesk_settings['customer_accounts_verify_email_cooldown'])." MINUTE < NOW() THEN 1
                ELSE 0
            END AS `can_request_new_verification_email`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customers`
        WHERE `email` = '".hesk_dbEscape($_SESSION['login_email'])."'
            AND (`verified` > 0 OR (`verified` = 0 AND `verification_token` IS NOT NULL))
        LIMIT 1");

    if (hesk_dbNumRows($res) === 0) {
        hesk_process_messages(sprintf($hesklang['customer_wrong_user'], $_SESSION['login_email']), 'login.php');
    }

    $user = hesk_dbFetchAssoc($res);

    if (!hesk_password_verify($_POST['password'], $user['pass'])) {
        hesk_process_messages($hesklang['customer_wrong_pass'], 'login.php');
    }

    if (intval($user['verified']) === 0) {
        //-- Not verified.  If it's been past the cooldown period, provide a resend link
        $resend_link = '<br><br>' . $hesklang['customer_login_not_verified2'];
        if (intval($user['can_request_new_verification_email']) === 1) {
            $resend_link .= '<br><br><a href="'.$hesk_settings['hesk_url'] . '/login.php?a=resend_verification_email&email='.urlencode($_POST['email']).'">'.hesk_htmlspecialchars($hesklang['customer_login_resend_verification_email']).'</a>';
        } else {
            $resend_link .= '<br><br>' . $hesklang['customer_login_resend_verification_email_too_early'];
        }
        hesk_process_messages($hesklang['customer_login_not_verified'].$resend_link, 'login.php');
    }

    if (intval($user['verified']) === 2) {
        //-- Pending approval
        hesk_process_messages($hesklang['customer_login_not_approved'], 'login.php', 'NOTICE');
    }

    if (hesk_isBannedEmail($user['email'])) {
        //-- Customer is banned from the helpdesk
        hesk_process_messages($hesklang['customer_accounts_email_banned'], 'login.php');
    }

    if (hesk_password_needs_rehash($user['pass'])) {
        $user['pass'] = hesk_password_hash($_POST['password']);
        hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."customers` SET `pass`='".hesk_dbEscape($user['pass'])."' WHERE `id`=".intval($user['id']));
    }

    $mfa_enrollment = intval($user['mfa_enrollment']);
    if ($mfa_enrollment === 0) {
        // Do we require MFA? Force it if the user has an email
        if ($hesk_settings['require_mfa_customers'] && strlen($user['email'])) {
            $mfa_enrollment = 1;
        } else {
            unset($_SESSION['login_email']);
            hesk_process_successful_customer_login($user);
            return;
        }
    }

    $message = $hesklang['mfa_verification_needed'] . '<br><br>';
    $mfa_verify_option = 1;
    if ($mfa_enrollment === 1) {
        // Email
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($user['id'], $verification_code, 'CUSTOMER');
        send_mfa_email($user['name'], $user['email'], $verification_code);

        $message .= $hesklang['mfa_verification_needed_email'];
    } elseif ($mfa_enrollment === 2) {
        // Authenticator app
        $message .= $hesklang['mfa_verification_needed_auth_app'];
        $mfa_verify_option = 2;
    }
    hesk_process_messages($message, 'NOREDIRECT', 'INFO');

    if ( ! isset($_SESSION['a_iserror'])) {
        $_SESSION['a_iserror'] = array();
    }

    $hesk_settings['render_template'](TEMPLATE_PATH . "customer/account/mfa-needed.php", array(
        'messages' => hesk_get_messages(),
        'model' => [
            'token' => hesk_token_echo(0),
            'verifyMethod' => $mfa_verify_option === 1 ? 'EMAIL' : 'AUTH-APP',
            'email' => $user['email']
        ]
    ));
    hesk_cleanSessionVars('a_iserror');
    exit();
}

function validate_data() {
    global $hesk_settings, $hesklang;

    $hesk_error_buffer = array();

    $_SESSION['login_email'] = hesk_validateEmail( hesk_POST('email'), 'ERR', 0) or $hesk_error_buffer['email'] = $hesklang['customer_login_email_required'];

    if (empty($_POST['password'])) {
        $hesk_error_buffer['password'] = $hesklang['customer_login_password_required'];
    }

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

    if ($hesk_settings['secimg_use'] && !isset($_SESSION['img_verified']))
    {
        // Using reCAPTCHA?
        if ($hesk_settings['recaptcha_use'])
        {
            require(HESK_PATH . 'inc/recaptcha/recaptchalib_v2.php');

            $resp = null;
            $reCaptcha = new ReCaptcha($hesk_settings['recaptcha_private_key']);

            // Was there a reCAPTCHA response?
            if ( isset($_POST["g-recaptcha-response"]) )
            {
                $resp = $reCaptcha->verifyResponse(hesk_getClientIP(), hesk_POST("g-recaptcha-response") );
            }

            if ($resp != null && $resp->success)
            {
                $_SESSION['img_verified']=true;
            }
            else
            {
                $hesk_error_buffer['mysecnum']=$hesklang['recaptcha_error'];
            }
        }
        // Using PHP generated image
        else
        {
            $mysecnum = intval( hesk_POST('mysecnum', 0) );

            if ( empty($mysecnum) )
            {
                $hesk_error_buffer['mysecnum'] = $hesklang['sec_miss'];
            }
            else
            {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);

                if ( isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum']) )
                {
                    $_SESSION['img_verified'] = true;
                    unset($_SESSION['checksum']);
                }
                else
                {
                    $hesk_error_buffer['mysecnum'] = $hesklang['sec_wrng'];
                }
            }
        }
    }

    /* Any missing fields? */
    if (count($hesk_error_buffer)!=0)
    {
        $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);

        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $hesk_error_buffer = $tmp;

        $hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,'login.php');
    }
    elseif (isset($_SESSION['img_verified']))
    {
        unset($_SESSION['img_verified']);
    }
}

function handle_reset_password_request() {
    global $hesklang, $hesk_settings;

    // Are customers permitted to submit a reset request?
    if (!$hesk_settings['reset_pass']) {
        hesk_process_messages($hesklang['attempt'], 'login.php');
    }

    //region Security image + data validation
    $hesk_error_buffer = [];
    if ($hesk_settings['secimg_use'] && !isset($_SESSION['img_verified_reset']))
    {
        // Using reCAPTCHA?
        if ($hesk_settings['recaptcha_use'])
        {
            require(HESK_PATH . 'inc/recaptcha/recaptchalib_v2.php');

            $resp = null;
            $reCaptcha = new ReCaptcha($hesk_settings['recaptcha_private_key']);

            // Was there a reCAPTCHA response?
            if ( isset($_POST["g-recaptcha-response"]) )
            {
                $resp = $reCaptcha->verifyResponse(hesk_getClientIP(), hesk_POST("g-recaptcha-response") );
            }

            if ($resp != null && $resp->success)
            {
                $_SESSION['img_verified_reset']=true;
            }
            else
            {
                $hesk_error_buffer['mysecnum']=$hesklang['recaptcha_error'];
            }
        }
        // Using PHP generated image
        else
        {
            $mysecnum = intval( hesk_POST('mysecnum', 0) );

            if ( empty($mysecnum) )
            {
                $hesk_error_buffer['mysecnum'] = $hesklang['sec_miss'];
            }
            else
            {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);

                if ( isset($_SESSION['checksumreset']) && $sc->checkCode($mysecnum, $_SESSION['checksumreset']) )
                {
                    $_SESSION['img_verified_reset'] = true;
                    unset($_SESSION['checksumreset']);
                }
                else
                {
                    $hesk_error_buffer['mysecnum'] = $hesklang['sec_wrng'];
                }
            }
        }
    }

    /* Any missing fields? */
    if (count($hesk_error_buffer)!=0)
    {
        $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);
        $_SESSION['login_email'] = hesk_POST('reset-email');

        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $hesk_error_buffer = $tmp;

        $hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,'login.php?submittedForgot=1');
    }
    elseif (isset($_SESSION['img_verified_reset']))
    {
        unset($_SESSION['img_verified_reset']);
    }

    // Make sure email is valid
    $email = hesk_validateEmail(hesk_POST('reset-email'), 'ERR' , 0);
    if (!$email) {
        hesk_process_messages($hesklang['enter_valid_email'], 'login.php?submittedForgot=1');
    }

    $model['email'] = hesk_emailCleanup($email);

    hesk_handle_customer_password_reset_request($model['email']);

    hesk_process_messages($hesklang['password_reset_link_sent'], 'NOREDIRECT', 'SUCCESS');
}

function handle_mfa_verification() {
    global $hesk_settings, $hesklang;

    $mfa_method = hesk_POST('mfa-method');

    hesk_limitInternalBfAttempts();

    $user = get_user_for_email($_SESSION['login_email']);

    if (($mfa_method === 'EMAIL' && is_mfa_email_code_valid($user['id'], hesk_POST('verification-code'), 'CUSTOMER')) ||
        ($mfa_method === 'AUTH-APP' && is_mfa_app_code_valid($user['id'], hesk_POST('verification-code'), null, 'CUSTOMER'))) {
        hesk_cleanBfAttempts();
        unset($_SESSION['login_email']);
        hesk_process_successful_customer_login($user);
        return;
    }

    hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
    $hesk_settings['render_template'](TEMPLATE_PATH . "customer/account/mfa-needed.php", array(
        'messages' => hesk_get_messages(),
        'model' => [
            'token' => hesk_token_echo(0),
            'verifyMethod' => $mfa_method,
            'email' => $user['email']
        ]
    ));
}

function get_user_for_email($email) {
    global $hesk_settings;
    //-- Grab user record again for the email
    $user_rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `email` = '".hesk_dbEscape($email)."'");

    if ($row = hesk_dbFetchAssoc($user_rs)) {
        return $row;
    }

    return null;
}

function handle_mfa_backup_code() {
    global $hesk_settings, $hesklang;

    hesk_limitInternalBfAttempts();


    $user = get_user_for_email($_SESSION['login_email']);
    if (verify_mfa_backup_code($user['id'], hesk_POST('backup-code'), 'CUSTOMER')) {
        hesk_cleanBfAttempts();
        unset($_SESSION['login_email']);
        hesk_process_successful_customer_login($user);
        return;
    }

    //-- Verification failed
    hesk_process_messages($hesklang['mfa_invalid_backup_code'], 'NOREDIRECT');
    $hesk_settings['render_template'](TEMPLATE_PATH . "customer/account/mfa-needed.php", array(
        'messages' => hesk_get_messages(),
        'model' => [
            'token' => hesk_token_echo(0),
            'verifyMethod' => hesk_POST('mfa-method'),
            'email' => $user['email']
        ]
    ));
}

function send_mfa_backup_email() {
    global $hesk_settings, $hesklang;

    // Let's limit the "Send another email" to max 3
    if (isset($_SESSION['customer']['mfa_emails_sent'])) {
        if ($_SESSION['customer']['mfa_emails_sent'] >= 3) {
            hesk_forceLogoutCustomer($hesklang['bf_int']);
        }
        $_SESSION['customer']['mfa_emails_sent']++;
    } else {
        $_SESSION['customer']['mfa_emails_sent'] = 1;
    }

    $user = get_user_for_email($_SESSION['login_email']);
    $verification_code = generate_mfa_code();
    hash_and_store_mfa_verification_code($user['id'], $verification_code, 'CUSTOMER');
    send_mfa_email($user['name'], $user['email'], $verification_code);

    $message = $hesklang['mfa_verification_needed_email'];
    hesk_process_messages($message, 'NOREDIRECT', 'INFO');

    $hesk_settings['render_template'](TEMPLATE_PATH . "customer/account/mfa-needed.php", array(
        'messages' => hesk_get_messages(),
        'model' => [
            'token' => hesk_token_echo(0),
            'verifyMethod' => 'EMAIL',
            'email' => $user['email']
        ]
    ));
    exit();
}

function resend_verification_email() {
    global $hesklang, $hesk_settings;

    /* Limit brute force attempts */
    hesk_limitBfAttempts();

    $email = hesk_GET('email');
    if ($email === '' || !hesk_isValidEmail($email)) {
        hesk_process_messages($hesklang['customer_resend_verification_email_needed'], 'login.php');
        return;
    }

    $user_info_rs = hesk_dbQuery("SELECT `customer`.*,
            CASE
                WHEN `verification_email_sent_at` + INTERVAL ".intval($hesk_settings['customer_accounts_verify_email_cooldown'])." MINUTE < NOW() THEN 1
                ELSE 0
            END AS `can_request_new_verification_email`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer` 
        WHERE `email` = '".hesk_dbEscape($email)."' 
        AND `verified` = 0 
        LIMIT 1");

    if (hesk_dbNumRows($user_info_rs) !== 1) {
        hesk_process_messages($hesklang['customer_resend_verification_email_not_found'], 'login.php');
        return;
    }

    $user_info = hesk_dbFetchAssoc($user_info_rs);
    if (!intval($user_info['can_request_new_verification_email'])) {
        hesk_process_messages($hesklang['customer_login_resend_verification_email_too_early'], 'login.php');
        return;
    }

    hesk_sendCustomerRegistrationEmail($user_info, $user_info['verification_token']);
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `verification_email_sent_at` = NOW()
        WHERE `id` = ".intval($user_info['id']));

    if (isset($_SESSION['img_verified']))
    {
        unset($_SESSION['img_verified']);
    }
    hesk_process_messages($hesklang['customer_resend_verification_email_sent'], 'login.php', 'SUCCESS');
}
