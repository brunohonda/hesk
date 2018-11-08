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

	$res=hesk_dbFetchAssoc($result);
	foreach ($res as $k=>$v)
	{
	    $_SESSION[$k]=$v;
	}

	/* Check password */
	if (hesk_Pass2Hash($pass) != $_SESSION['pass'])
    {
        hesk_session_stop();
    	$_SESSION['a_iserror'] = array('pass');
		hesk_process_messages($hesklang['wrong_pass'],'NOREDIRECT');
		print_login();
		exit();
	}

    $pass_enc = hesk_Pass2Hash($_SESSION['pass'].hesk_mb_strtolower($user).$_SESSION['pass']);

    /* Check if default password */
    if ($_SESSION['pass'] == '499d74967b28a841c98bb4baaabaad699ff3c079')
    {
    	hesk_process_messages($hesklang['chdp'],'NOREDIRECT','NOTICE');
    }

	// Set a tag that will be used to expire sessions after username or password change
	$_SESSION['session_verify'] = hesk_activeSessionCreateTag($user, $_SESSION['pass']);

	// We don't need the password hash anymore
	unset($_SESSION['pass']);

	/* Login successful, clean brute force attempts */
	hesk_cleanBfAttempts();

	/* Regenerate session ID (security) */
	hesk_session_regenerate_id();

	/* Remember username? */
	if ($hesk_settings['autologin'] && hesk_POST('remember_user') == 'AUTOLOGIN')
	{
		hesk_setcookie('hesk_username', "$user", strtotime('+1 year'));
		hesk_setcookie('hesk_p', "$pass_enc", strtotime('+1 year'));
	}
	elseif ( hesk_POST('remember_user') == 'JUSTUSER')
	{
		hesk_setcookie('hesk_username', "$user", strtotime('+1 year'));
		hesk_setcookie('hesk_p', '');
	}
	else
	{
		// Expire cookie if set otherwise
		hesk_setcookie('hesk_username', '');
		hesk_setcookie('hesk_p', '');
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
} // End do_login()


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

	?>
	<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
	<td width="3"><img src="../img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
	<td class="headersm"><?php echo $hesklang['login']; ?></td>
	<td width="3"><img src="../img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
	</tr>
	</table>

	<table width="100%" border="0" cellspacing="0" cellpadding="3">
	<tr>
	<td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
	<?php echo $hesklang['admin_login']; ?></span></td>
	</tr>
	</table>

	</td>
	</tr>
	<tr>
	<td>

	<br />

	<?php
	/* This will handle error, success and notice messages */
	hesk_handle_messages();
	?>

    <br />

    <div align="center">
	<table border="0" cellspacing="0" cellpadding="0" width="<?php echo ($hesk_settings['secimg_use'] == 2) ? '60' : '50'; ?>% ">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>

        <form action="index.php" method="post" name="form1" id="form1">

        <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
                <td width="60" style="text-align:center"><img src="../img/login.png" alt="" width="24" height="24" /></td>
                <td>
                <p><b><?php echo $hesklang['admin_login']; ?></b></p>
                </td>
        </tr>
        <tr>
                <td width="60">&nbsp;</td>
                <td>&nbsp;</td>
        </tr>
        <tr>
                <td width="60">&nbsp;</td>
                <td><?php echo $hesklang['username']; ?>:<br />
				<?php

                $cls = in_array('user',$_SESSION['a_iserror']) ? ' class="isError" ' : '';

				if ( defined('HESK_DEMO') )
				{
					$savedUser = 'Demo';
				}
				elseif (defined('HESK_USER'))
				{
					$savedUser = HESK_USER;
				}
				else
				{
					$savedUser = hesk_htmlspecialchars( hesk_COOKIE('hesk_username') );
				}

		        $is_1 = '';
		        $is_2 = '';
		        $is_3 = '';

				$remember_user = hesk_POST('remember_user');

				if ($hesk_settings['autologin'] && (isset($_COOKIE['hesk_p']) || $remember_user == 'AUTOLOGIN') )
		        {
		        	$is_1 = 'checked="checked"';
		        }
		        elseif (isset($_COOKIE['hesk_username']) || $remember_user == 'JUSTUSER' )
		        {
		        	$is_2 = 'checked="checked"';
		        }
		        else
		        {
		        	$is_3 = 'checked="checked"';
		        }

				if ($hesk_settings['list_users'])
				{
				    echo '<select name="user" '.$cls.'>';
				    $res = hesk_dbQuery('SELECT `user` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'users` ORDER BY `user` ASC');
				    while ($row=hesk_dbFetchAssoc($res))
				    {
				        $sel = (hesk_mb_strtolower($savedUser) == hesk_mb_strtolower($row['user'])) ? 'selected="selected"' : '';
				        echo '<option value="'.$row['user'].'" '.$sel.'>'.$row['user'].'</option>';
				    }
				    echo '</select>';

				}
				else
				{
				    echo '<input type="text" name="user" size="35" value="'.$savedUser.'" '.$cls.' />';
				}
				?>
                </td>
        </tr>
        <tr>
                <td width="60">&nbsp;</td>
                <td>&nbsp;</td>
        </tr>
        <tr>
                <td width="60">&nbsp;</td>
                <td><?php echo $hesklang['pass']; ?>:<br /><input type="password" name="pass" size="35" <?php if (in_array('pass',$_SESSION['a_iserror'])) {echo ' class="isError" ';} if ( defined('HESK_DEMO') ) {echo ' value="demo1"';} ?> /></td>
        </tr>
		<?php
        if ($hesk_settings['secimg_use'] == 2 && $hesk_settings['recaptcha_use'] != 1)
	    {
			?>
			<tr>
				<td width="60">&nbsp;</td>
				<td>
                <hr />
				<?php
				// SPAM prevention verified for this session
				if (isset($_SESSION['img_a_verified']))
				{
					echo '<img src="'.HESK_PATH.'img/success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> '.$hesklang['vrfy'];
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
					$cls = in_array('mysecnum',$_SESSION['a_iserror']) ? ' class="isError" ' : '';

					echo $hesklang['sec_enter'].'<br />&nbsp;<br /><img src="'.HESK_PATH.'print_sec_img.php?'.rand(10000,99999).'" width="150" height="40" alt="'.$hesklang['sec_img'].'" title="'.$hesklang['sec_img'].'" border="1" name="secimg" style="vertical-align:text-bottom" /> '.
					'<a href="javascript:void(0)" onclick="javascript:document.form1.secimg.src=\''.HESK_PATH.'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);"><img src="'.HESK_PATH.'img/reload.png" height="24" width="24" alt="'.$hesklang['reload'].'" title="'.$hesklang['reload'].'" border="0" style="vertical-align:text-bottom" /></a>'.
					'<br />&nbsp;<br /><input type="text" name="mysecnum" size="20" maxlength="5" '.$cls.' />';
				}
                ?>
                <hr />
				</td>
			</tr>
            <?php
		}
        else
        {
        	?>
	        <tr>
	                <td width="60">&nbsp;</td>
	                <td>&nbsp;</td>
	        </tr>
	        <?php
        } // End if $hesk_settings['secimg_use'] == 2

		if ($hesk_settings['autologin'])
		{
			?>
			<tr>
				<td width="60">&nbsp;</td>
				<td><label><input type="radio" name="remember_user" value="AUTOLOGIN" <?php echo $is_1; ?> /> <?php echo $hesklang['autologin']; ?></label><br />
				<label><input type="radio" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?> /> <?php echo $hesklang['just_user']; ?></label><br />
				<label><input type="radio" name="remember_user" value="NOTHANKS" <?php echo $is_3; ?> /> <?php echo $hesklang['nothx']; ?></label></td>
			</tr>
			<?php
		}
		else
		{
			?>
			<tr>
				<td width="60">&nbsp;</td>
				<td><label><input type="checkbox" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?> /> <?php echo $hesklang['remember_user']; ?></label></td>
			</tr>
			<?php
		} // End if $hesk_settings['autologin']
		?>
        <tr>
                <td width="60">&nbsp;</td>
                <td>&nbsp;</td>
        </tr>
        <tr>
                <td width="60">&nbsp;</td>
				<td><input type="submit" value="<?php echo $hesklang['click_login']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" id="recaptcha-submit" />
                <input type="hidden" name="a" value="do_login" />
				<?php
				if ( hesk_isREQUEST('goto') && $url=hesk_REQUEST('goto') )
				{
					echo '<input type="hidden" name="goto" value="'.$url.'" />';
				}

				// Do we allow staff password reset?
				if ($hesk_settings['reset_pass'])
				{
                	echo '<br />&nbsp;<br /><a href="password.php" class="smaller">'.$hesklang['fpass'].'</a>';
				}
				?>
                <br />&nbsp;
				</td>
        </tr>
        </table>

        <?php
        // Use Invisible reCAPTCHA?
        if ($hesk_settings['secimg_use'] == 2 && $hesk_settings['recaptcha_use'] == 1 && ! isset($_SESSION['img_a_verified']))
        {
            ?>
            <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="recaptcha-submit" data-callback="recaptcha_submitForm"></div>
            <?php
        }
        ?>

        </form>

		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>
    </div>

    <p>&nbsp;</p>

	<?php
	hesk_cleanSessionVars('a_iserror');

    require_once(HESK_PATH . 'inc/footer.inc.php');
    exit();
} // End print_login()


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
    /* Destroy session and cookies */
	hesk_session_stop();

    /* If we're using the security image for admin login start a new session */
	if ($hesk_settings['secimg_use'] == 2)
    {
    	hesk_session_start();
    }

	/* Show success message and reset the cookie */
    hesk_process_messages($hesklang['logout_success'],'NOREDIRECT','SUCCESS');
    hesk_setcookie('hesk_p', '');

    /* Print the login form */
	print_login();
	exit();
} // End logout()

?>
