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
define('LOAD_TABS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/profile_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions */
$can_view_tickets = hesk_checkPermission('can_view_tickets',0);
$can_reply_tickets = hesk_checkPermission('can_reply_tickets',0);
$can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);

/* Update profile? */
if ( ! empty($_POST['action']))
{
	// Demo mode
	if ( defined('HESK_DEMO') )
	{
		hesk_process_messages($hesklang['sdemo'], 'profile.php', 'NOTICE');
	}

    if ($_POST['action'] == 'password')
    {
        update_password();
    }
    else
    {
        update_profile();
    }
}
else
{
	$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id` = '".intval($_SESSION['id'])."' LIMIT 1");
	$tmp = hesk_dbFetchAssoc($res);

	foreach ($tmp as $k=>$v)
	{
		if ($k == 'pass')
        {
			if ($v == '499d74967b28a841c98bb4baaabaad699ff3c079')
			{
				define('WARN_PASSWORD',true);
			}
			continue;
        }
        elseif ($k == 'categories')
		{
			continue;
		}
		$_SESSION['new'][$k]=$v;
	}
}

if ( ! isset($_SESSION['new']['username']))
{
	$_SESSION['new']['username'] = '';
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION(array('new', 'errors')) && !hesk_SESSION(array('newpass', 'errors'))) {
    hesk_handle_messages();
}

if (defined('WARN_PASSWORD'))
{
	hesk_show_notice($hesklang['chdp2'],'<span class="important">'.$hesklang['security'].'</span>');
}
?>
<div class="main__content profile">
    <article class="profile__wrapper">
        <div class="profile__info">
            <div class="profile__info_list">
                <h3><?php echo $_SESSION['new']['name']; ?></h3>
                <div class="info--mail">
                    <a href="mailto:<?php echo $_SESSION['new']['email']; ?>"><?php echo $_SESSION['new']['email']; ?></a>
                </div>
            </div>
        </div>
        <div class="profile__control">
            <div class="profile__edit">
                <button class="btn btn--blue-border" data-action="profile-edit"><?php echo $hesklang['edit_profile']; ?></button>
            </div>
            <div class="profile__edit">
                <button class="btn btn--blue-border" data-action="profile-password"><?php echo $hesklang['edit_pass']; ?></button>
            </div>
            <a href="index.php?a=logout&token=<?php hesk_token_echo(); ?>" class="profile-log-out">
                <svg class="icon icon-log-out">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-log-out"></use>
                </svg>
                <span><?php echo $hesklang['logout']; ?></span>
            </a>
        </div>
    </article>
    <article class="profile__wrapper">
        <div class="profile__info">
            <div class="profile__info_list">
                <h3><?php echo $hesklang['mfa']; ?></h3>
                <div class="info--mail">
                    <?php if ($_SESSION['new']['mfa_enrollment'] === '0') { ?>
                        <div class="text-danger">
                            <?php echo $hesklang['mfa_disabled']; ?>
                        </div>
                    <?php } elseif ($_SESSION['new']['mfa_enrollment'] === '1') { ?>
                        <div class="text-success">
                            <?php echo sprintf($hesklang['mfa_enabled'], $hesklang['mfa_method_email']); ?>
                        </div>
                    <?php } elseif ($_SESSION['new']['mfa_enrollment'] === '2') { ?>
                        <div class="text-success">
                            <?php echo sprintf($hesklang['mfa_enabled'], $hesklang['mfa_method_auth_app']); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="profile__control">
            <div class="profile__edit">
                <a href="manage_mfa.php">
                    <button class="btn btn-full wider">
                        <?php echo $hesklang['mfa_manage_profile']; ?>
                    </button>
                </a>
            </div>
        </div>
    </article>
</div>
<div class="right-bar profile-edit" <?php echo hesk_SESSION(array('new','errors')) ? 'style="display: block"' : ''; ?>>
    <div class="right-bar__body form" data-step="1">
        <h3>
            <a href="javascript:">
                <svg class="icon icon-back">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                </svg>
                <span><?php echo $hesklang['profile_for'].' <b>'.$_SESSION['new']['user']; ?></span>
            </a>
        </h3>
        <?php
        /* This will handle error, success and notice messages */
        if (hesk_SESSION(array('new', 'errors'))) {
            hesk_handle_messages();
        }

        if ($hesk_settings['can_sel_lang'])
        {
            /* Update preferred language in the database? */
            if (isset($_GET['save_language']) )
            {
                $newlang = hesk_input( hesk_GET('language') );

                /* Only update if it's a valid language */
                if ( isset($hesk_settings['languages'][$newlang]) )
                {
                    $newlang = ($newlang == HESK_DEFAULT_LANGUAGE) ? "NULL" : "'" . hesk_dbEscape($newlang) . "'";
                    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `language`=$newlang WHERE `id`='".intval($_SESSION['id'])."'");
                }
            }

            $str  = '<form method="get" class="form" action="profile.php" style="margin:10px 0 0 0;padding:0;border:0;white-space:nowrap;">';
            $str .= '<input type="hidden" name="save_language" value="1" />';
            $str .= '<div class="form-group"><label for="prof_language">'.$hesklang['chol'].'</label>';

            if ( ! isset($_GET) )
            {
                $_GET = array();
            }

            foreach ($_GET as $k => $v)
            {
                if ($k == 'language' || $k == 'save_language')
                {
                    continue;
                }
                $str .= '<input type="hidden" name="'.htmlentitieshesk_htmlentities($k).'" value="'.hesk_htmlentities($v).'" />';
            }

            $str .= '<div class="dropdown-select center out-close"><select class="form-control" name="language" onchange="this.form.submit()">';
            $str .= hesk_listLanguages(0);
            $str .= '</select></div></div>';

            ?>
            <script language="javascript" type="text/javascript">
                document.write('<?php echo str_replace(array('"','<','=','>',"'"),array('\42','\74','\75','\76','\47'),$str . '</p></form>'); ?>');
            </script>
            <noscript>
                <?php
                echo $str . '<input type="submit" value="'.$hesklang['go'].'" /></p></form>';
                ?>
            </noscript>
            <?php
        }
        ?>
        <form name="form1" method="post" action="profile.php" class="form <?php echo hesk_SESSION(array('new','errors')) ? 'invalid' : ''; ?>">
            <?php hesk_profile_tab(); ?>

            <!-- Submit -->
            <div class="right-bar__footer">
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['update_profile']; ?></button>
            </div>
        </form>
    </div>
</div>
<div class="right-bar profile-password" <?php echo (hesk_SESSION(array('newpass','errors')) || hesk_SESSION('password_reset')) ? 'style="display: block"' : ''; ?>>
    <div class="right-bar__body form" data-step="1">
        <h3>
            <a href="javascript:">
                <svg class="icon icon-back">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                </svg>
                <span><?php echo $hesklang['edit_pass']; ?></span>
            </a>
        </h3>
        <?php
        /* This will handle error, success and notice messages */
        if (hesk_SESSION(array('newpass', 'errors'))) {
            hesk_handle_messages();
        } elseif (hesk_SESSION('password_reset')) {
            hesk_show_notice($hesklang['resim'], ' ', false);
            hesk_show_info($hesklang['cur_pass3'], ' ', false, 'no-padding-top');
        } else {
            hesk_show_info($hesklang['cur_pass2'] . '<br><br>' . $hesklang['cur_pass3'], ' ', false);
        }

        $session_array='newpass';
        $errors = hesk_SESSION(array($session_array, 'errors'));
        $errors = is_array($errors) ? $errors : array();
        ?>
        <form name="form1" method="post" action="profile.php" class="form <?php echo hesk_SESSION(array('newpass','errors')) ? 'invalid' : ''; ?>">
            <section class="item--section">
                <?php if ( ! hesk_SESSION('password_reset')): ?>
                <div class="form-group">
                    <label for="pass_cur"><?php echo $hesklang['cur_pass']; ?></label>
                    <input type="password" id="pass_cur" name="pass_cur" autocomplete="off" class="form-control <?php echo in_array('current', $errors) ? 'isError' : ''; ?>"
                           value="<?php echo isset($_SESSION[$session_array]['pass_cur']) ? $_SESSION[$session_array]['pass_cur'] : ''; ?>">
                </div>
                <p>&nbsp;</p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="pass_new"><?php echo $hesklang['new_pass']; ?></label>
                    <input type="password" id="pass_new" name="pass_new" autocomplete="off" class="form-control <?php echo in_array('new', $errors) ? 'isError' : ''; ?>"
                           value="<?php echo isset($_SESSION[$session_array]['pass_new']) ? $_SESSION[$session_array]['pass_new'] : ''; ?>"
                           onkeyup="hesk_checkPassword(this.value, 'progressBar2')">
                </div>
                <div class="form-group">
                    <label for="pass_new2"><?php echo $hesklang['confirm_pass']; ?></label>
                    <input type="password" id="pass_new2" name="pass_new2" autocomplete="off" class="form-control <?php echo in_array('new2', $errors) ? 'isError' : ''; ?>"
                           value="<?php echo isset($_SESSION[$session_array]['pass_new2']) ? $_SESSION[$session_array]['pass_new2'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label><?php echo $hesklang['pwdst']; ?></label>
                    <div style="border: 1px solid #d4d6e3; width: 100%; height: 14px">
                        <div id="progressBar2" style="font-size: 1px; height: 12px; width: 0px; border: none;">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Submit -->
            <div class="right-bar__footer">
                <input type="hidden" name="action" value="password" />
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['save_pass']; ?></button>
            </div>
        </form>
    </div>
</div>
<?php

hesk_cleanSessionVars('newpass');
unset($_SESSION['new']['errors']);

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function update_password() {
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    $hesk_error_buffer = '';
    $errors = array();

    // Current password
	$_SESSION['newpass']['pass_cur'] = hesk_input( hesk_POST('pass_cur') );
    if (hesk_SESSION('password_reset')) {
        // Allow password reset without the old password
    } elseif (!$_SESSION['newpass']['pass_cur']) {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_pass'] . '</li>';
        $errors[] = 'current';
    } elseif (strlen($_SESSION['newpass']['pass_cur']) > 64) {
        $hesk_error_buffer .= '<li>' . $hesklang['pass_len'] . '</li>';
        $errors[] = 'current';
    } else {
        hesk_limitInternalBfAttempts();

        // Get current password hash from DB
        $result = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id` = ".intval($_SESSION['id'])." LIMIT 1");
        if (hesk_dbNumRows($result) != 1)
        {
            hesk_forceLogout($hesklang['wrong_user']);
        }
        $user_row = hesk_dbFetchAssoc($result);

        // Validate current password
        if (hesk_password_verify($_SESSION['newpass']['pass_cur'], $user_row['pass'])) {
            hesk_cleanBfAttempts();
        } else {
            $hesk_error_buffer .= '<li>' . $hesklang['wrong_pass'] . '</li>';
            $errors[] = 'current';
        }
    }

    // New password
	$_SESSION['newpass']['pass_new'] = hesk_input( hesk_POST('pass_new') );
	if (!$_SESSION['newpass']['pass_new']) {
        $hesk_error_buffer .= '<li>' . $hesklang['e_new_pass'] . '</li>';
        $errors[] = 'new';
    } elseif (strlen($_SESSION['newpass']['pass_new']) < 5) {
        $hesk_error_buffer .= '<li>' . $hesklang['password_not_valid'] . '</li>';
        $errors[] = 'new';
    } elseif (strlen($_SESSION['newpass']['pass_new']) > 64) {
        $hesk_error_buffer .= '<li>' . $hesklang['pass_len'] . '</li>';
        $errors[] = 'new';
    }

    // Confirm password
	$_SESSION['newpass']['pass_new2'] = hesk_input( hesk_POST('pass_new2') );
	if ($_SESSION['newpass']['pass_new2'] != $_SESSION['newpass']['pass_new']) {
        $hesk_error_buffer .= '<li>' . $hesklang['passwords_not_same'] . '</li>';
        $errors[] = 'new2';
    }

    if (strlen($hesk_error_buffer))
    {
        $hesk_error_buffer = '<div class="browser-default"><ul>'.$hesk_error_buffer.'</ul></div>';
        $_SESSION['newpass']['errors'] = $errors;
        hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
    }
    else
    {
        $newpass_hash = hesk_password_hash($_SESSION['newpass']['pass_new']);
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `pass` = '".hesk_dbEscape($newpass_hash)."' WHERE `id` = ".intval($_SESSION['id']));

        // Force login after password change
        hesk_forceLogout($hesklang['pass_login'], null, null, 'NOTICE');
    }
} // End update_password()


function update_profile() {
	global $hesk_settings, $hesklang, $can_view_unassigned;

	/* A security check */
	hesk_token_check('POST');

    $sql_username = '';

    $hesk_error_buffer = '';
    $errors = array();

	$_SESSION['new']['name']  = hesk_input( hesk_POST('name') );
	if (!$_SESSION['new']['name']) {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_your_name'] . '</li>';
        $errors[] = 'name';
    }
	$_SESSION['new']['email'] = hesk_validateEmail( hesk_POST('email'), 'ERR', 0);
	if (!$_SESSION['new']['email']) {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_valid_email'] . '</li>';
        $errors[] = 'email';
    }
	$_SESSION['new']['signature'] = hesk_input( hesk_POST('signature') );

	/* Signature */
	if (hesk_mb_strlen($_SESSION['new']['signature'])>1000)
    {
		$hesk_error_buffer .= '<li>' . $hesklang['signature_long'] . '</li>';
		$errors[] = 'signature';
    }

    /* Admins can change username */
    if ($_SESSION['isadmin'])
    {
		$_SESSION['new']['user']  = hesk_input( hesk_POST('user') ) or $hesk_error_buffer .= '<li>' . $hesklang['enter_username'] . '</li>';

	    /* Check for duplicate usernames */
		$result = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user`='".hesk_dbEscape($_SESSION['new']['user'])."' AND `id`!='".intval($_SESSION['id'])."' LIMIT 1");
		if (hesk_dbNumRows($result) != 0)
		{
	        $hesk_error_buffer .= '<li>' . $hesklang['duplicate_user'] . '</li>';
	        $errors[] = 'user';
		}
        else
        {
        	$sql_username =  "`user`='" . hesk_dbEscape($_SESSION['new']['user']) . "', ";
        }
    }

    /* After reply */
    $_SESSION['new']['afterreply'] = intval( hesk_POST('afterreply') );
    if ($_SESSION['new']['afterreply'] != 1 && $_SESSION['new']['afterreply'] != 2)
    {
    	$_SESSION['new']['afterreply'] = 0;
    }

    // Defaults
    $_SESSION['new']['autostart']				= isset($_POST['autostart']) ? 1 : 0;
    $_SESSION['new']['notify_customer_new']		= isset($_POST['notify_customer_new']) ? 1 : 0;
    $_SESSION['new']['notify_customer_reply']	= isset($_POST['notify_customer_reply']) ? 1 : 0;
    $_SESSION['new']['show_suggested']			= isset($_POST['show_suggested']) ? 1 : 0;
    $_SESSION['new']['autoreload']				= isset($_POST['autoreload']) ? 1 : 0;

    if ($_SESSION['new']['autoreload'])
    {
        $_SESSION['new']['autoreload'] = intval(hesk_POST('reload_time'));

        if (hesk_POST('secmin') == 'min')
        {
            $_SESSION['new']['autoreload'] *= 60;
        }

        if ($_SESSION['new']['autoreload'] < 0 || $_SESSION['new']['autoreload'] > 65535)
        {
            $_SESSION['new']['autoreload'] = 30;
        }
    }
    else
    {
        hesk_setcookie('autorefresh', '');
    }

    /* Notifications */
    $_SESSION['new']['notify_new_unassigned']       = empty($_POST['notify_new_unassigned']) || ! $can_view_unassigned ? 0 : 1;
    $_SESSION['new']['notify_overdue_unassigned']   = empty($_POST['notify_overdue_unassigned']) || !$can_view_unassigned ? 0 : 1;
    $_SESSION['new']['notify_new_my'] 			    = empty($_POST['notify_new_my']) ? 0 : 1;
    $_SESSION['new']['notify_overdue_my']           = empty($_POST['notify_overdue_my']) ? 0 : 1;
    $_SESSION['new']['notify_reply_unassigned']     = empty($_POST['notify_reply_unassigned']) || ! $can_view_unassigned ? 0 : 1;
    $_SESSION['new']['notify_reply_my']			    = empty($_POST['notify_reply_my']) ? 0 : 1;
    $_SESSION['new']['notify_assigned']			    = empty($_POST['notify_assigned']) ? 0 : 1;
    $_SESSION['new']['notify_note'] 				= empty($_POST['notify_note']) ? 0 : 1;
    $_SESSION['new']['notify_pm']	    			= empty($_POST['notify_pm']) ? 0 : 1;

    /* Any errors? */
    if (strlen($hesk_error_buffer))
    {
		/* Process the session variables */
		$_SESSION['new'] = hesk_stripArray($_SESSION['new']);

		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        $_SESSION['new']['errors'] = $errors;
		hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
    }
    else
    {
		/* Update database */
		hesk_dbQuery(
		"UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET
		`name`='".hesk_dbEscape($_SESSION['new']['name'])."',
		`email`='".hesk_dbEscape($_SESSION['new']['email'])."',
		`signature`='".hesk_dbEscape($_SESSION['new']['signature'])."',
		$sql_username
		`afterreply`='".($_SESSION['new']['afterreply'])."' ,
		".($hesk_settings['time_worked'] ? "`autostart`='".($_SESSION['new']['autostart'])."'," : '')."
		`autoreload`='".($_SESSION['new']['autoreload'])."' ,
		`notify_customer_new`='".($_SESSION['new']['notify_customer_new'])."' ,
		`notify_customer_reply`='".($_SESSION['new']['notify_customer_reply'])."' ,
		`show_suggested`='".($_SESSION['new']['show_suggested'])."' ,
		`notify_new_unassigned`='".($_SESSION['new']['notify_new_unassigned'])."' ,
		`notify_overdue_unassigned`='".($_SESSION['new']['notify_overdue_unassigned'])."' ,
		`notify_new_my`='".($_SESSION['new']['notify_new_my'])."' ,
		`notify_overdue_my`='".($_SESSION['new']['notify_overdue_my'])."' ,
		`notify_reply_unassigned`='".($_SESSION['new']['notify_reply_unassigned'])."' ,
		`notify_reply_my`='".($_SESSION['new']['notify_reply_my'])."' ,
		`notify_assigned`='".($_SESSION['new']['notify_assigned'])."' ,
		`notify_pm`='".($_SESSION['new']['notify_pm'])."',
		`notify_note`='".($_SESSION['new']['notify_note'])."'
		WHERE `id`='".intval($_SESSION['id'])."'"
		);

		/* Process the session variables */
		$_SESSION['new'] = hesk_stripArray($_SESSION['new']);

		// Do we need a new session_veify tag?
		if ( strlen($sql_username) )
		{
			$res = hesk_dbQuery('SELECT `pass` FROM `'.hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id` = '".intval($_SESSION['id'])."' LIMIT 1");
			$_SESSION['session_verify'] = hesk_activeSessionCreateTag($_SESSION['new']['user'], hesk_dbResult($res) );
		}

        /* Update session variables */
        foreach ($_SESSION['new'] as $k => $v)
        {
        	$_SESSION[$k] = $v;
        }
        unset($_SESSION['new']);

		hesk_cleanSessionVars('as_notify');

	    hesk_process_messages($hesklang['profile_updated_success'],'profile.php','SUCCESS');
    }
} // End update_profile()

?>
