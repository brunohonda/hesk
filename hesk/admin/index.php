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
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();

/* What should we do? */
$action = hesk_REQUEST('a');

switch ($action)
{
    case 'do_login':
    	do_login();
        break;
    case 'do_mfa_verification':
        do_mfa_verification();
        break;
    case 'do_backup_code_verification':
        do_backup_code_verification();
        break;
    case 'backup_email':
        do_backup_email_verification();
        break;
    case 'login':
    	print_login();
        break;
    case 'logout':
    	logout();
        break;
    default:
    	hesk_autoLogin();
    	print_login();
}

/* Print footer */
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

/*** START FUNCTIONS ***/
function do_login()
{
	global $hesk_settings, $hesklang;

    $hesk_error_buffer = array();

    $user = hesk_input( hesk_POST('user') );
    if (empty($user))
    {
		$myerror = $hesk_settings['list_users'] ? $hesklang['select_username'] : $hesklang['enter_username'];
        $hesk_error_buffer['user'] = $myerror;
    }
    define('HESK_USER', $user);

	$pass = hesk_input( hesk_POST('pass') );
	if (empty($pass))
	{
    	$hesk_error_buffer['pass'] = $hesklang['enter_pass'];
	}
    elseif (strlen($pass) > 64)
    {
        $hesk_error_buffer['pass'] = $hesklang['pass_len'];
    }

	if ($hesk_settings['secimg_use'] == 2 && !isset($_SESSION['img_a_verified']))
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
				$_SESSION['img_a_verified']=true;
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
					$_SESSION['img_a_verified'] = true;
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
	    hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
        print_login();
        exit();
	}
    elseif (isset($_SESSION['img_a_verified']))
    {
		unset($_SESSION['img_a_verified']);
    }

	/* User entered all required info, now lets limit brute force attempts */
	hesk_limitBfAttempts();

	$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($user)."' LIMIT 1");
	if (hesk_dbNumRows($result) != 1)
	{
        hesk_session_stop();
    	$_SESSION['a_iserror'] = array('user','pass');
    	hesk_process_messages($hesklang['wrong_user'],'NOREDIRECT');
        print_login();
        exit();
	}

    $user_row = hesk_dbFetchAssoc($result);

    // Verify password
    if (hesk_password_verify($pass, $user_row['pass'])) {
        if (hesk_password_needs_rehash($user_row['pass'])) {
            $user_row['pass'] = hesk_password_hash($pass);
            hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."users` SET `pass`='".hesk_dbEscape($user_row['pass'])."' WHERE `id`=".intval($user_row['id']));
        }
    } elseif (hesk_Pass2Hash($pass) == $user_row['pass']) {
        // Legacy password, update it
        $user_row['pass'] = hesk_password_hash($pass);
        hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."users` SET `pass`='".hesk_dbEscape($user_row['pass'])."' WHERE `id`=".intval($user_row['id']));
    } else {
        hesk_session_stop();
        $_SESSION['a_iserror'] = array('pass');
        hesk_process_messages($hesklang['wrong_pass'],'NOREDIRECT');
        print_login();
        exit();
    }

    // User authenticated; if MFA is disabled, complete the login
    $mfa_enrollment = intval($user_row['mfa_enrollment']);
    if (empty($mfa_enrollment)) {
        process_successful_login($user_row);
    }

    // Handle MFA
    require(HESK_PATH . 'inc/mfa_functions.inc.php');

    $message = $hesklang['mfa_verification_needed'] . '<br><br>';
    $mfa_verify_option = 1;
    if ($mfa_enrollment === 1) {
        // Email
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($user_row['id'], $verification_code);
        send_mfa_email($user_row['name'], $user_row['email'], $verification_code);

        $message .= $hesklang['mfa_verification_needed_email'];
    } elseif ($mfa_enrollment === 2) {
        // Authenticator App
        $message .= $hesklang['mfa_verification_needed_auth_app'];
        $mfa_verify_option = 2;
    }
    $user_id = $user_row['id'];
    $email = $user_row['email'];
    $name = $user_row['name'];
    hesk_session_stop();
    hesk_session_start();
    $_SESSION['HESK_USER'] = $user;
    $_SESSION['id'] = $user_id;
    $_SESSION['mfa_enrollment'] = $mfa_enrollment;
    $_SESSION['email'] = $email;
    $_SESSION['remember_user_form_val'] = hesk_POST('remember_user');
    $_SESSION['mfa_verify_option'] = $mfa_verify_option;
    $_SESSION['name'] = $name;
    hesk_process_messages($message, 'NOREDIRECT', 'INFO');

    print_mfa_verification();
    exit();
} // End do_login()

function do_mfa_verification() {
    global $hesk_settings, $hesklang;

    require(HESK_PATH . 'inc/mfa_functions.inc.php');

    if (($_SESSION['mfa_verify_option'] === 1 && !is_mfa_email_code_valid($_SESSION['id'], hesk_POST('verification-code'))) ||
        ($_SESSION['mfa_verify_option'] === 2 && !is_mfa_app_code_valid($_SESSION['id'], hesk_POST('verification-code')))) {
        hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
        // Invalid attempts increase the lockout limit
        hesk_limitBfAttempts();
        $_SESSION['remember_user_form_val'] = hesk_POST('remember_user');
        print_mfa_verification();
        exit();
    }

    set_session_and_process_login();
}

function set_session_and_process_login() {
    global $hesk_settings;

    $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($_SESSION['HESK_USER'])."' LIMIT 1");
    $res = hesk_dbFetchAssoc($result);
    process_successful_login($res);
}

function do_backup_code_verification() {
    global $hesklang;

    require(HESK_PATH . 'inc/mfa_functions.inc.php');

    if (!verify_mfa_backup_code($_SESSION['id'], hesk_POST('backup-code'))) {
        hesk_process_messages($hesklang['mfa_invalid_backup_code'], 'NOREDIRECT');
        // Invalid attempts increase the lockout limit
        hesk_limitBfAttempts();
        $_SESSION['remember_user_form_val'] = hesk_POST('remember_user');
        print_mfa_verification();
        exit();
    }

    set_session_and_process_login();
}

function process_successful_login($user_row) {
    global $hesk_settings, $hesklang;

    // User authenticated, let's regenerate the session ID
    hesk_session_regenerate_id();

    // Set a tag that will be used to expire sessions after username or password change
    $_SESSION['session_verify'] = hesk_activeSessionCreateTag($user_row['user'], $user_row['pass']);

    // Set data we need for the session
    unset($user_row['pass']);
    unset($user_row['mfa_secret']);
    foreach ($user_row as $k => $v) {
        $_SESSION[$k] = $v;
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
    $_SESSION['elevated'] = $elevation_expiration;

	/* Remember username? */
	if ($hesk_settings['autologin'] && hesk_POST('remember_user') == 'AUTOLOGIN')
	{
        $selector = base64_encode(random_bytes(9));
        $authenticator = random_bytes(33);
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` (`selector`,`token`,`user_id`,`expires`) VALUES ('".hesk_dbEscape($selector)."','".hesk_dbEscape(hash('sha256', $authenticator))."','".intval($_SESSION['id'])."', NOW() + INTERVAL 1 YEAR)");
        hesk_setcookie('hesk_username', '');
        hesk_setcookie('hesk_remember', $selector.':'.base64_encode($authenticator), strtotime('+1 year'));
	}
	elseif ( hesk_POST('remember_user') == 'JUSTUSER')
	{
		hesk_setcookie('hesk_username', $user_row['user'], strtotime('+1 year'));
		hesk_setcookie('hesk_remember', '');
	}
	else
	{
		// Expire cookie if set otherwise
		hesk_setcookie('hesk_username', '');
		hesk_setcookie('hesk_remember', '');
	}

    /* Close any old tickets here so Cron jobs aren't necessary */
    if ($hesk_settings['autoclose'])
    {
        $revision = sprintf($hesklang['thist3'],hesk_date(),$hesklang['auto']);
        $dt  = date('Y-m-d H:i:s',time() - $hesk_settings['autoclose']*86400);

        // Notify customer of closed ticket?
        if ($hesk_settings['notify_closed'])
        {
            // Get list of tickets
            $result = hesk_dbQuery("SELECT * FROM `".$hesk_settings['db_pfix']."tickets` WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."' ");
            if (hesk_dbNumRows($result) > 0)
            {
                global $ticket;

                // Load required functions?
                if ( ! function_exists('hesk_notifyCustomer') )
                {
                    require(HESK_PATH . 'inc/email_functions.inc.php');
                }

                while ($ticket = hesk_dbFetchAssoc($result))
                {
                    $ticket['dt'] = hesk_date($ticket['dt'], true);
                    $ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
                    $ticket = hesk_ticketToPlain($ticket, 1, 0);
                    hesk_notifyCustomer('ticket_closed');
                }
            }
        }

        // Update ticket statuses and history in database
        hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."tickets` SET `status`='3', `closedat`=NOW(), `closedby`='-1', `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."' ");
    }

    /* Redirect to the destination page */
    header('Location: ' . hesk_verifyGoto() );
    exit();
}

function do_backup_email_verification() {
    global $hesklang;

    // Let's limit the "Send another email" to max 3
    if (isset($_SESSION['mfa_emails_sent'])) {
        if ($_SESSION['mfa_emails_sent'] >= 3) {
            hesk_forceLogout($hesklang['bf_int']);
        }
        $_SESSION['mfa_emails_sent']++;
    } else {
        $_SESSION['mfa_emails_sent'] = 1;
    }

    require_once(HESK_PATH . 'inc/mfa_functions.inc.php');

    $verification_code = generate_mfa_code();
    hash_and_store_mfa_verification_code($_SESSION['id'], $verification_code);
    send_mfa_email($_SESSION['name'], $_SESSION['email'], $verification_code);
    $_SESSION['mfa_verify_option'] = 1;
    $_SESSION['remember_user_form_val'] = hesk_GET('remember_user');

    hesk_process_messages($hesklang['mfa_verification_needed_email'], 'NOREDIRECT', 'INFO');

    print_mfa_verification();
    exit();
}


function print_login()
{
	global $hesk_settings, $hesklang;

	// Tell header to load reCaptcha API if needed
	if ($hesk_settings['recaptcha_use'])
	{
		define('RECAPTCHA',1);
	}

    $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' .$hesklang['admin_login'];
	require_once(HESK_PATH . 'inc/header.inc.php');

	if ( hesk_isREQUEST('notice') )
	{
    	hesk_process_messages($hesklang['session_expired'],'NOREDIRECT');
	}

    if (!isset($_SESSION['a_iserror']))
    {
    	$_SESSION['a_iserror'] = array();
    }

    $login_wrapper = true;
	?>
    <div class="wrapper login">
        <main class="main">
            <div class="reg__wrap">
                <div class="reg__image">
                    <div class="bg-absolute"><img src="<?php echo HESK_PATH; ?>img/hero-bg.png" alt="Hesk" /></div>
                </div>
                <div class="reg__section">
                    <div class="reg__box">
                        <h2 class="reg__heading"><?php echo $hesklang['admin_login']; ?></h2>
                        <div style="margin-right: -24px; margin-left: -16px">
                            <?php
                            /* This will handle error, success and notice messages */
                            hesk_handle_messages();
                            ?>
                        </div>
                        <form action="index.php" class="form <?php echo isset($_SESSION['a_iserror']) && count($_SESSION['a_iserror']) ? 'invalid' : ''; ?>" id="form1" method="post" name="form1" novalidate>
                            <div class="form-group">
                                <label for="regInputUsername"><?php echo $hesklang['username']; ?></label>
                                <?php

                                $cls = in_array('user',$_SESSION['a_iserror']) ? 'isError' : '';

                                if ( defined('HESK_DEMO')) {
                                    $savedUser = 'Demo';
                                } elseif (defined('HESK_USER')) {
                                    $savedUser = HESK_USER;
                                } else {
                                    $savedUser = hesk_htmlspecialchars(hesk_COOKIE('hesk_username'));
                                }

                                $is_1 = '';
                                $is_2 = '';
                                $is_3 = '';

                                $remember_user = hesk_POST('remember_user');

                                if ($hesk_settings['autologin'] && (isset($_COOKIE['hesk_remember']) || $remember_user == 'AUTOLOGIN') )
                                {
                                    $is_1 = 'checked';
                                }
                                elseif (isset($_COOKIE['hesk_username']) || $remember_user == 'JUSTUSER' )
                                {
                                    $is_2 = 'checked';
                                }
                                else
                                {
                                    $is_3 = 'checked';
                                }

                                if ($hesk_settings['list_users']) {
                                    echo '<select name="user" class="'.$cls.'">';
                                    $res = hesk_dbQuery('SELECT `user` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'users` ORDER BY `user` ASC');
                                    while ($row=hesk_dbFetchAssoc($res))
                                    {
                                        $sel = (hesk_mb_strtolower($savedUser) == hesk_mb_strtolower($row['user'])) ? 'selected="selected"' : '';
                                        echo '<option value="'.$row['user'].'" '.$sel.'>'.$row['user'].'</option>';
                                    }
                                    echo '</select>';

                                } else {
                                    echo '<input type="text" class="form-control '.$cls.'" id="regInputUsername" name="user" value="'.$savedUser.'" autocomplete="off" required>';
                                }
                                ?>
                                <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="regInputPassword"><?php echo $hesklang['pass']; ?></label>
                                <div class="input-group">
                                    <?php
                                    $class = 'class="form-control';
                                    if (in_array('pass',$_SESSION['a_iserror'])) {
                                        $class .= ' isError';
                                    }
                                    $class .= '"';
                                    ?>
                                    <input type="password" name="pass" id="regInputPassword" <?php echo $class; ?>
                                        <?php if (defined('HESK_DEMO')) {echo ' value="demo1"';} ?>>
                                    <div class="input-group-append--icon passwordIsHidden">
                                        <svg class="icon icon-eye-close">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-eye-close"></use>
                                        </svg>
                                    </div>
                                </div>
                                <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                            </div>
                            <?php if ($hesk_settings['secimg_use'] == 2 && $hesk_settings['recaptcha_use'] != 1): ?>
                            <div>
                                <?php
                                // SPAM prevention verified for this session
                                if (isset($_SESSION['img_a_verified']))
                                {
                                    //-- No-op
                                }
                                // Use reCaptcha API v2?
                                elseif ($hesk_settings['recaptcha_use'] == 2)
                                {
                                    ?>
                                    <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
                                    <?php
                                }
                                // At least use some basic PHP generated image (better than nothing)
                                else
                                {
                                    $cls = in_array('mysecnum',$_SESSION['a_iserror']) ? ' class="form-control isError" ' : ' class="form-control" ';

                                    echo '<div class="form-group"><label>'.$hesklang['sec_enter'].'</label><img src="'.HESK_PATH.'print_sec_img.php?'.rand(10000,99999).'" width="150" height="40" alt="'.$hesklang['sec_img'].'" title="'.$hesklang['sec_img'].'" border="1" name="secimg" style="vertical-align:middle" /> '.
                                        '<a style="vertical-align: middle; display: inline" class="btn btn-refresh" href="javascript:" onclick="document.form1.secimg.src=\''.HESK_PATH.'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                            <svg class="icon icon-refresh">
                                                <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-refresh"></use>
                                            </svg>
                                         </a>'.
                                        '<br><br><input type="text" name="mysecnum" size="20" maxlength="5" autocomplete="off" '.$cls.'></div>';
                                }
                                ?>
                            </div>
                            <?php
                            endif;
                            if ($hesk_settings['autologin']):
                            ?>
                                <div class="radio-group">
                                    <div class="radio-list">
                                        <div class="radio-custom" style="margin-top: 5px;">
                                            <input type="radio" id="remember_userAUTOLOGIN" name="remember_user" value="AUTOLOGIN" <?php echo $is_1; ?>>
                                            <label for="remember_userAUTOLOGIN"><?php echo $hesklang['autologin']; ?></label>
                                        </div>
                                        <div class="radio-custom" style="margin-top: 5px;">
                                            <input type="radio" id="remember_userJUSTUSER" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?>>
                                            <label for="remember_userJUSTUSER"><?php echo $hesklang['just_user']; ?></label>
                                        </div>
                                        <div class="radio-custom" style="margin-top: 5px;">
                                            <input type="radio" id="remember_userNOTHANKS" name="remember_user" value="NOTHANKS" <?php echo $is_3; ?>>
                                            <label for="remember_userNOTHANKS"><?php echo $hesklang['nothx']; ?></label>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="reg__checkboxes">
                                    <div class="form-group">
                                        <div class="checkbox-custom">
                                            <input type="checkbox" id="tableCheckboxId2" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?> />
                                            <label for="tableCheckboxId2"><?php echo $hesklang['remember_user']; ?></label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="form__submit">
                                <button class="btn btn-full" ripple="ripple" type="submit" id="recaptcha-submit">
                                    <?php echo $hesklang['click_login']; ?>
                                </button>
                                <input type="hidden" name="a" value="do_login">
                                <?php
                                if (hesk_isREQUEST('goto') && $url=hesk_REQUEST('goto'))
                                {
                                    echo '<input type="hidden" name="goto" value="'.$url.'">';
                                }
                                ?>
                            </div>
                            <?php if ($hesk_settings['reset_pass']): ?>
                                <div class="reg__footer">
                                    <a href="password.php" class="link"><?php echo $hesklang['fpass']; ?></a>
                                </div>
                            <?php
                            endif;

                            // Use Invisible reCAPTCHA?
                            if ($hesk_settings['secimg_use'] == 2 && $hesk_settings['recaptcha_use'] == 1 && ! isset($_SESSION['img_a_verified'])): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="recaptcha-submit" data-callback="recaptcha_submitForm"></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

        <script>
        $(() => {
            $('form :visible[class*=isError]:first').focus();
        })
        </script>
	<?php
	hesk_cleanSessionVars('a_iserror');

    require_once(HESK_PATH . 'inc/footer.inc.php');
    exit();
} // End print_login()

function print_mfa_verification()
{
    global $hesk_settings, $hesklang;

    $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' .$hesklang['admin_login'];
    require_once(HESK_PATH . 'inc/header.inc.php');

    if (!isset($_SESSION['a_iserror']))
    {
        $_SESSION['a_iserror'] = array();
    }
    ?>
            <div class="wrapper login">
                <main class="main">
                    <div class="reg__wrap">
                        <div class="reg__image">
                            <div class="bg-absolute"><img src="<?php echo HESK_PATH; ?>img/hero-bg.png" alt="Hesk" /></div>
                        </div>
                        <div class="reg__section">
                            <div class="reg__box">
                                <h2 class="reg__heading"><?php echo $hesklang['mfa']; ?></h2>
                                <div id="mfa-verify">
                                <div style="margin-right: -24px; margin-left: -16px">
                                    <?php
                                    /* This will handle error, success and notice messages */
                                    hesk_handle_messages();
                                    ?>
                                </div>
                                <form action="index.php" class="form <?php echo isset($_SESSION['a_iserror']) && count($_SESSION['a_iserror']) ? 'invalid' : ''; ?>" id="form1" method="post" name="form1" novalidate>
                                    <div class="form-group" id="verification-code-group">
                                        <label for="verificationCode"><?php echo $hesklang['mfa_verification_code']; ?></label>
                                        <?php
                                        $cls = in_array('user',$_SESSION['a_iserror']) ? 'isError' : '';
                                        ?>
                                        <input type="text" class="form-control <?php echo $cls; ?>" id="verificationCode" name="verification-code" autocomplete="off" maxlength="6" required>
                                        <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                                    </div>
                                    <div class="form__submit mfa">
                                        <button class="btn btn-full" ripple="ripple" type="submit" id="verify-submit">
                                            <?php echo $hesklang['mfa_verify']; ?>
                                        </button>
                                        <input type="hidden" name="a" value="do_mfa_verification">
                                        <input type="hidden" name="remember_user" value="<?php echo stripslashes(hesk_input($_SESSION['remember_user_form_val'])); ?>">
                                        <?php

                                        if (hesk_isREQUEST('goto') && $url=hesk_REQUEST('goto'))
                                        {
                                            echo '<input type="hidden" name="goto" value="'.$url.'">';
                                        }
                                        ?>
                                    </div>
                                </form>

                                <?php if ($_SESSION['mfa_verify_option'] === 1): ?>
                                    &nbsp;
                                    <form action="index.php" class="form <?php echo isset($_SESSION['a_iserror']) && count($_SESSION['a_iserror']) ? 'invalid' : ''; ?>" id="send-another-email-form" method="post" name="send-another-email-form" novalidate>
                                        <button class="btn btn-link" type="submit">
                                            <?php echo $hesklang['mfa_send_another_email']; ?>
                                        </button>
                                        <input type="hidden" name="a" value="backup_email">
                                        <input type="hidden" name="remember_user" value="<?php echo stripslashes(hesk_input($_SESSION['remember_user_form_val'])); ?>">
                                    </form>
                                <?php
                                endif;
                                ?>
                                    &nbsp;<br>
                                    <a href="javascript:hesk_toggleLayerDisplay('verify-another-way');hesk_toggleLayerDisplay('mfa-verify')">
                                        <?php echo $hesklang['mfa_verify_another_way']; ?>
                                    </a>
                                </div>

                                <div id="verify-another-way" style="display: none">
                                    &nbsp;
                                    <ul>
                                        <?php if ($_SESSION['mfa_verify_option'] === 2): ?>
                                        <li>
                                            <div class="flex">
                                                <div class="mfa-alt-icon" aria-hidden="true">
                                                    <svg class="icon icon-mail">
                                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-mail"></use>
                                                    </svg>
                                                </div>
                                                <div class="mfa-alt-text">
                                                    <form action="index.php" class="form <?php echo isset($_SESSION['a_iserror']) && count($_SESSION['a_iserror']) ? 'invalid' : ''; ?>" id="email-backup-form" method="post" name="email-backup-form" novalidate>
                                                        <button class="btn btn-link" type="submit">
                                                            <?php echo sprintf($hesklang['mfa_verify_another_way_email'], hesk_maskEmailAddress($_SESSION['email'])); ?>
                                                        </button>
                                                        <input type="hidden" name="a" value="backup_email">
                                                        <input type="hidden" name="remember_user" value="<?php echo stripslashes(hesk_input($_SESSION['remember_user_form_val'])); ?>">
                                                    </form>
                                                </div>
                                            </div>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <div class="flex">
                                                <div class="mfa-alt-icon" aria-hidden="true">
                                                    <svg class="icon icon-lock">
                                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-lock"></use>
                                                    </svg>
                                                </div>
                                                <div class="mfa-alt-text">
                                                    <a href="javascript:hesk_toggleLayerDisplay('backup-code-field')"><?php echo $hesklang['mfa_verify_another_way_code']; ?></a>
                                                    <div id="backup-code-field" style="display: none">
                                                        <form action="index.php" class="form <?php echo isset($_SESSION['a_iserror']) && count($_SESSION['a_iserror']) ? 'invalid' : ''; ?>" id="backup-form" method="post" name="backup-form" novalidate>
                                                            <div class="form-group">
                                                                <label for="backupCode"><?php echo $hesklang['mfa_backup_code']; ?></label>
                                                                <input type="text" class="form-control" id="backupCode" name="backup-code" minlength="8" maxlength="9" autocomplete="off">
                                                            </div>
                                                            <div class="form__submit mfa">
                                                                <button class="btn btn-full" ripple="ripple" type="submit" id="backup-code-submit">
                                                                    <?php echo $hesklang['s']; ?>
                                                                </button>
                                                            </div>
                                                            <input type="hidden" name="a" value="do_backup_code_verification">
                                                            <input type="hidden" name="remember_user" value="<?php echo stripslashes(hesk_input($_SESSION['remember_user_form_val'])); ?>">
                                                            <?php

                                                            if (hesk_isREQUEST('goto') && $url=hesk_REQUEST('goto'))
                                                            {
                                                                echo '<input type="hidden" name="goto" value="'.$url.'">';
                                                            }
                                                            ?>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                        <?php if (hesk_isThereAnotherAdmin($_SESSION['id'])): ?>
                                        <li>
                                            <div class="flex">
                                                <div class="mfa-alt-icon" aria-hidden="true">
                                                    <svg class="icon icon-assign">
                                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-assign"></use>
                                                    </svg>
                                                </div>
                                                <div class="mfa-alt-text">
                                                    <?php echo $hesklang['mfa_verify_another_way_admin']; ?>
                                                </div>
                                            </div>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <div class="flex">
                                                <div class="mfa-alt-icon" aria-hidden="true">
                                                    <svg class="icon icon-move-to">
                                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-move-to"></use>
                                                    </svg>
                                                </div>
                                                <div class="mfa-alt-text">
                                                    <a href="https://www.hesk.com/knowledgebase/?article=108" target="_blank">
                                                        <?php echo $hesklang['mfa_verify_another_way_reset']; ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>

                                    &nbsp;

                                    <p style="text-align: center">
                                        <a href="javascript:hesk_toggleLayerDisplay('verify-another-way');hesk_toggleLayerDisplay('mfa-verify')">
                                            <?php echo $hesklang['back']; ?>
                                        </a>
                                    </p>
                                </div>
                                <?php unset($_SESSION['remember_user_form_val']); ?>
                            </div>
                        </div>
                    </div>

                    <script>
                        $(() => {
                            $('form :visible[class*=isError]:first').focus();
                        });
                        $('#form1').preventDoubleSubmission();
                        $('#backup-form').preventDoubleSubmission();
                        $('#verificationCode').keyup(function() {
                            if (this.value.length === 6) {
                                $('#form1').submit();
                            }
                        });
                        $('#backupCode').keyup(function() {
                            if (this.value.length === 8 || this.value.length === 9) {
                                $('#backup-form').submit();
                            }
                        });
                        $('#form1').submit(function() {
                            $('#verify-submit').attr('disabled', 'disabled')
                                .addClass('disabled');
                        });
                        $('#backup-form').submit(function() {
                            $('#backup-code-submit').attr('disabled', 'disabled')
                                .addClass('disabled');
                        });
                    </script>
<?php
hesk_cleanSessionVars('a_iserror');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
} // End print_mfa_verification()


function logout() {
	global $hesk_settings, $hesklang;

    if ( ! hesk_token_check('GET', 0))
    {
		print_login();
        exit();
    }

    /* Delete from Who's online database */
	if ($hesk_settings['online'])
	{
    	require(HESK_PATH . 'inc/users_online.inc.php');
		hesk_setOffline($_SESSION['id']);
	}

    // Clear users' authentication tokens
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id` = ".intval($_SESSION['id']));
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id` = ".intval($_SESSION['id']));

    /* Destroy session and cookies */
	hesk_session_stop();

    /* If we're using the security image for admin login start a new session */
	if ($hesk_settings['secimg_use'] == 2)
    {
    	hesk_session_start();
    }

	/* Show success message and reset the cookie */
    hesk_process_messages($hesklang['logout_success'],'NOREDIRECT','SUCCESS');
    hesk_setcookie('hesk_username', '');
    hesk_setcookie('hesk_remember', '');

    /* Print the login form */
	print_login();
	exit();
} // End logout()

?>
