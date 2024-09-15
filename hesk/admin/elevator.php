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
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/mfa_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

$mfa_enrollment = intval($_SESSION['mfa_enrollment']);
$skip_email = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hesk_POST('a') === 'backup_email') {
        // Force email verification instead of authenticator code
        $mfa_enrollment = 1;
        $force_send_email = true;

        // Let's limit the "Send another email" to max 3
        if (isset($_SESSION['mfa_emails_sent'])) {
            if ($_SESSION['mfa_emails_sent'] >= 3) {
                hesk_forceLogout($hesklang['bf_int']);
            }
            $_SESSION['mfa_emails_sent']++;
        } else {
            $_SESSION['mfa_emails_sent'] = 1;
        }
    } elseif (hesk_POST('a') === 'verify') {
        $skip_email = true;
        $mfa_method = intval(hesk_POST('mfa-method'));
        if ($mfa_method === 0) {
            $pass = hesk_input( hesk_POST('verification-code') );
            if ( ! $pass) {
                $error = $hesklang['enter_pass'];
            } elseif (strlen($pass) > 64) {
                $error = $hesklang['pass_len'];
            } else {
                hesk_limitInternalBfAttempts();
                if (hesk_password_verify($pass, fetch_current_user_password())) {
                    handle_successful_elevation();
                } else {
                    $error = $hesklang['wrong_pass'];
                }
            }
            hesk_process_messages($error, 'NOREDIRECT');
        } else {
            hesk_limitInternalBfAttempts();
            if (($mfa_method === 1 && is_mfa_email_code_valid($_SESSION['id'], hesk_POST('verification-code'))) ||
                ($mfa_method === 2 && is_mfa_app_code_valid($_SESSION['id'], hesk_POST('verification-code')))) {
                handle_successful_elevation();
            } else {
                // Verification failed
                hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            }
        }
    } elseif (hesk_POST('a') === 'do_backup_code_verification') {
        $skip_email = true;
        hesk_limitInternalBfAttempts();
        if (verify_mfa_backup_code($_SESSION['id'], hesk_POST('backup-code'))) {
            handle_successful_elevation();
        } else {
            // Verification failed
            hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
        }
    } else {
        // Invalid action, something strange is going on... Let's force logout
        hesk_forceLogout($hesklang['invalid_action']);
    }
}

$message = ''; //$hesklang['elevator_intro'] . '<br><br>';

if ($mfa_enrollment === 0) {
    $mfa_verify_option = 0;
    $message .= $hesklang['elevator_enter_password'];
} elseif ($mfa_enrollment === 1) {
    // Email
    $mfa_verify_option = 1;

    // Unless the "Send another email" link was clicked, don't send a new email until the old one is valid
    if (! $skip_email && empty($force_send_email) && isset($_SESSION['skip_mfa_emails_until']) && $_SESSION['skip_mfa_emails_until'] > date('Y-m-d H:i:s')) {
        $skip_email = true;
    }

    // Don't send a new email each time a verification fails
    if (! $skip_email) {
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($_SESSION['id'], $verification_code);
        send_mfa_email($_SESSION['name'], $_SESSION['email'], $verification_code);

        hesk_process_messages($hesklang['mfa_sent'], 'NOREDIRECT', 'INFO');

        // Don't send a new email until the old one is valid (with 15 min buffer) unless explicitly asked to
        $skip_mfa_emails_until = new DateTime();
        $skip_mfa_emails_until->add(new DateInterval('PT15M'));
        $_SESSION['skip_mfa_emails_until'] = $skip_mfa_emails_until->format('Y-m-d H:i:s');
    }

    $message .= $hesklang['mfa_verification_needed_email'];
} elseif ($mfa_enrollment === 2) {
    // Authenticator App
    $message .= $hesklang['mfa_verification_needed_auth_app'];
    $mfa_verify_option = 2;
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<div class="main__content profile">
    <section class="mfa__head">
        <h2>
            <?php echo $hesklang['elevator_header']; ?>
        </h2>
    </section>
    <article class="profile__wrapper mfa">
        <?php hesk_handle_messages(); ?>

        <div id="mfa-verify">

        <p><?php echo $message; ?></p>
        <form id="verify-form" class="form" action="elevator.php" method="post">
            <div class="form-group">
                <?php if ($mfa_verify_option === 0): ?>
                    <label><?php echo $hesklang['pass']; ?></label>
                    <div class="input-group">
                        <input name="verification-code" id="regInputPassword" type="password" class="form-control">
                        <div class="input-group-append--icon passwordIsHidden">
                            <svg class="icon icon-eye-close">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-eye-close"></use>
                            </svg>
                        </div>
                    </div>
                <?php else: ?>
                    <label><?php echo $hesklang['mfa_code']; ?></label>
                    <input name="verification-code" id="verify-input" type="text" class="form-control" maxlength="6" placeholder="000000" autocomplete="off">
                <?php endif; ?>
            </div>
            <button id="verify-submit" style="margin-top: 10px;" type="submit" class="btn btn-full" ripple="ripple"><?php echo $hesklang['mfa_verify']; ?></button>
            <input type="hidden" name="mfa-method" value="<?php echo $mfa_verify_option; ?>">
            <input type="hidden" name="a" value="verify">
        </form>

            <?php if ($mfa_verify_option === 1): ?>
                &nbsp;
                <form action="elevator.php" class="form" id="send-another-email-form" method="post" name="send-another-email-form" novalidate>
                    <button class="btn btn-link" type="submit">
                        <?php echo $hesklang['mfa_send_another_email']; ?>
                    </button>
                    <input type="hidden" name="a" value="backup_email">
                </form>
            <?php endif; ?>

            <?php if ($mfa_verify_option !== 0): ?>
                &nbsp;<br>
                <a href="javascript:hesk_toggleLayerDisplay('verify-another-way');hesk_toggleLayerDisplay('mfa-verify')">
                    <?php echo $hesklang['mfa_verify_another_way']; ?>
                </a>
            <?php endif; ?>

        </div>

        <?php if ($mfa_verify_option !== 0): ?>
            <div id="verify-another-way" style="display: none">
                <ul>
                    <?php if ($mfa_verify_option === 2): ?>
                        <li>
                            <div class="flex">
                                <div class="mfa-alt-icon" aria-hidden="true">
                                    <svg class="icon icon-mail">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-mail"></use>
                                    </svg>
                                </div>
                                <div class="mfa-alt-text">
                                    <form action="elevator.php" class="form" id="email-backup-form" method="post" name="email-backup-form" novalidate>
                                        <button class="btn btn-link" type="submit">
                                            <?php echo sprintf($hesklang['mfa_verify_another_way_email'], hesk_maskEmailAddress($_SESSION['email'])); ?>
                                        </button>
                                        <input type="hidden" name="a" value="backup_email">
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
                                    <form action="elevator.php" class="form" id="backup-form" method="post" name="backup-form" novalidate>
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
                                    </form>
                                </div>
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
        <?php endif; ?>
    </article>
</div>
<script>
    $('#verify-form').preventDoubleSubmission();
    $('#backup-form').preventDoubleSubmission();
    $('#verify-input').keyup(function() {
        if (this.value.length === 6) {
            $('#verify-form').submit();
        }
    });
    $('#backupCode').keyup(function() {
        if (this.value.length === 8 || this.value.length === 9) {
            $('#backup-form').submit();
        }
    });
    $('#verify-form').submit(function() {
        $('#verify-submit').attr('disabled', 'disabled')
            .addClass('disabled');
    });
    $('#backup-form').submit(function() {
        $('#backup-code-submit').attr('disabled', 'disabled')
            .addClass('disabled');
    });
</script>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');

function fetch_current_user_password() {
    global $hesk_settings, $hesklang;

    $res = hesk_dbQuery("SELECT `pass` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id` = ".intval($_SESSION['id'])." LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        hesk_forceLogout($hesklang['wrong_user']);
    }

    $row = hesk_dbFetchAssoc($res);

    return $row['pass'];
}

function handle_successful_elevation() {
    global $hesk_settings;

    hesk_session_regenerate_id();
    hesk_cleanBfAttempts();
    delete_mfa_codes($_SESSION['id']);
    hesk_cleanSessionVars('mfa_emails_sent');
    hesk_cleanSessionVars('skip_mfa_emails_until');

    $current_time = new DateTime();
    $interval_amount = $hesk_settings['elevator_duration'];
    if (in_array(substr($interval_amount, -1), array('M', 'H'))) {
        $interval_amount = 'T'.$interval_amount;
    }
    $elevation_expiration = $current_time->add(new DateInterval("P{$interval_amount}"));

    $_SESSION['elevated'] = $elevation_expiration;
    $elevator_target = hesk_SESSION('elevator_target', 'admin_main.php');
    unset($_SESSION['elevator_target']);
    header('Location: ' . $elevator_target);
    exit();
}

exit();
