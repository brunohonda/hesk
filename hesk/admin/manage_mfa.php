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

// Demo mode
if ( defined('HESK_DEMO') ) {
    hesk_process_messages($hesklang['ddemo'], 'profile.php', 'NOTICE');
}

if ( ! isset($_SESSION['mfa_enrollment'])) {
    $_SESSION['mfa_enrollment'] = 0;
}

hesk_check_user_elevation('manage_mfa.php');

$display_step = 1;
$current_step = intval(hesk_POST('current-step'));
$tfa = build_tfa_instance();
if ($current_step === 1) {
    // Intro -> Verification
    $mfa_method = intval(hesk_POST('mfa-method'));
    if ($mfa_method === 1) {
        $verification_code = generate_mfa_code();
        hash_and_store_mfa_verification_code($_SESSION['id'], $verification_code);
        $mfa_email_sent = send_mfa_email($_SESSION['name'], $_SESSION['email'], $verification_code);

        $display_step = 2;
    } elseif ($mfa_method === 2) {
        $_SESSION['tfa_secret'] = $tfa->createSecret();
        $display_step = 2;
    } elseif ($mfa_method === 0 && $hesk_settings['require_mfa'] === 0) {
        hesk_dbQuery("UPDATE `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` 
                SET `mfa_enrollment` = 0 
                WHERE `id` = " . intval($_SESSION['id']));
        delete_mfa_codes($_SESSION['id']);
        delete_mfa_backup_codes($_SESSION['id']);
        $_SESSION['mfa_enrollment'] = 0;
        $display_step = 3;
    } else {
        hesk_process_messages($hesklang['mfa_invalid_method'], 'manage_mfa.php');
    }
} elseif ($current_step === 2) {
    $mfa_method = intval(hesk_POST('mfa-method'));
    if ($mfa_method === 1) {
        $verification_code = hesk_POST('verification-code');

        if (is_mfa_email_code_valid($_SESSION['id'], $verification_code)) {
            //-- Enable MFA for the user and delete the verification code
            hesk_dbQuery("UPDATE `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` 
                SET `mfa_enrollment` = 1 
                WHERE `id` = " . intval($_SESSION['id']));
            $_SESSION['mfa_enrollment'] = 1;
            $_SESSION['mfa_backup_codes'] = generate_and_store_mfa_backup_codes($_SESSION['id']);
            $display_step = 3;
        } else {
            //-- Invalid code entered
            hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            $display_step = 2;
        }
    } elseif ($mfa_method === 2) {
        $secret = $_SESSION['tfa_secret'];
        if (is_mfa_app_code_valid($_SESSION['id'], hesk_POST('verification-code'), $secret)) {
            hesk_dbQuery("UPDATE `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` 
                SET `mfa_enrollment` = 2,
                    `mfa_secret` = '" . hesk_dbEscape($secret) . "' 
                WHERE `id` = " . intval($_SESSION['id']));
            $_SESSION['mfa_backup_codes'] = generate_and_store_mfa_backup_codes($_SESSION['id']);
            unset($_SESSION['tfa_secret']);
            $_SESSION['mfa_enrollment'] = 2;
            $display_step = 3;
        } else {
            hesk_process_messages($hesklang['mfa_invalid_verification_code'], 'NOREDIRECT');
            $display_step = 2;
        }
    } else {
        hesk_process_messages($hesklang['mfa_invalid_method'], 'manage_mfa.php');
    }
} elseif (hesk_POST('delete_codes') === 'Y') {
    hesk_token_check();
    delete_mfa_backup_codes($_SESSION['id']);
    hesk_process_messages($hesklang['mfa_del_codes2'], 'NOREDIRECT', 'SUCCESS');
    $display_step = 1;
    $output_at_top = 1;
} elseif (hesk_POST('new_codes') === 'Y') {
    hesk_token_check();
    delete_mfa_backup_codes($_SESSION['id']);
    $new_mfa_backup_codes = generate_and_store_mfa_backup_codes($_SESSION['id']);
    $backup_codes = implode("\n", array_map(function($code, $key) { return str_pad(($key+1), 2, ' ', STR_PAD_LEFT) . '. ' . substr($code, 0, 4) . '-' . substr($code, 4); }, $new_mfa_backup_codes, array_keys($new_mfa_backup_codes)));
    hesk_process_messages($hesklang['mfa_new_codes2'] . '<p style="margin-top:10px">'.$hesklang['mfa_backup_codes_description'].'</p><pre style="margin-top:20px; font-family: monospace; font-size: 16px;">'.$backup_codes.'</pre>', 'NOREDIRECT', 'SUCCESS');
    $display_step = 1;
    $output_at_top = 1;
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<div class="main__content profile">
    <section class="mfa__head">
        <h2>
            <?php echo $hesklang['mfa']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['mfa_introduction']; ?>
                    </div>
                </div>
            </div>
        </h2>
    </section>

    <?php if (isset($output_at_top)) {hesk_handle_messages();} ?>

    <article class="profile__wrapper mfa" data-step="<?php echo $display_step; ?>">
        <?php if (intval($_SESSION['mfa_enrollment']) !== 0 && $display_step === 1) {
            hesk_show_notice($hesklang['mfa_reset_warning']);
        } ?>
        <div class="mfa-steps">
            <ul class="step-bar">
                <li data-link="1" data-all="3"><?php echo $hesklang['mfa_step_method']; ?></li>
                <li data-link="2" data-all="3"><?php echo $hesklang['mfa_step_verification']; ?></li>
                <li data-link="3" data-all="3"><?php echo $hesklang['mfa_step_complete']; ?></li>
            </ul>
        </div>
        <div class="step-slider">
            <?php if ( ! isset($output_at_top)) {hesk_handle_messages();} ?>
            <?php if ($display_step === 1) { ?>
            <div class="step-item step-1">
                <div><strong><?php echo $hesklang['mfa_select_method_colon']; ?><br>&nbsp;</strong></div>
                <form action="manage_mfa.php" method="post">
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" id="mfa_method_email" name="mfa-method" value="1" <?php echo intval($_SESSION['mfa_enrollment']) === 1 ? 'checked' : ''; ?>>
                            <label for="mfa_method_email">
                                <strong><?php echo $hesklang['mfa_method_email']; ?></strong><br>
                                <span><?php echo sprintf($hesklang['mfa_method_email_subtext'], $_SESSION['email']); ?><br>&nbsp;</span>
                            </label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="mfa_method_auth_app" name="mfa-method" value="2" <?php echo intval($_SESSION['mfa_enrollment']) === 2 ? 'checked' : ''; ?>>
                            <label for="mfa_method_auth_app">
                                <strong><?php echo $hesklang['mfa_method_auth_app']; ?></strong><br>
                                <span><?php echo $hesklang['mfa_method_auth_app_subtext']; ?><br>&nbsp;</span>
                            </label>
                        </div>
                        <?php if ($hesk_settings['require_mfa'] === 0): ?>
                        <div class="radio-custom">
                            <input type="radio" id="mfa_method_none" name="mfa-method" value="0" <?php echo intval($_SESSION['mfa_enrollment']) === 0 ? 'checked' : ''; ?>>
                            <label for="mfa_method_none">
                                <strong><?php echo $hesklang['mfa_method_none']; ?></strong><br>
                                <span><?php echo $hesklang['mfa_method_none_subtext']; ?><br>&nbsp;</span>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="current-step" value="1">
                    <button type="submit" class="btn btn-full next" ripple="ripple"><?php echo $hesklang['wizard_next']; ?></button>
                </form>
            </div>
            <?php } elseif ($display_step === 2) { ?>
            <div class="step-item step-2">
                <?php if ($mfa_method === 1) { ?>
                    <div>
                        <h3><?php echo sprintf($hesklang['mfa_verification_header'], $hesklang['mfa_method_email']); ?></h3>
                        <?php
                        if (isset($mfa_email_sent) && $mfa_email_sent === true) {
                            hesk_show_notice(sprintf($hesklang['mfa_verification_email_intro'], $_SESSION['email']), ' ', false);
                        }
                        ?>
                    </div>
                <?php } elseif ($mfa_method === 2) { ?>
                    <div>
                        <h3><?php echo sprintf($hesklang['mfa_verification_header'], $hesklang['mfa_method_auth_app']); ?></h3>
                        <p><?php echo $hesklang['mfa_verification_auth_app_intro']; ?></p>
                        <img src="<?php echo $tfa->getQRCodeImageAsDataUri($hesk_settings['hesk_title'], $_SESSION['tfa_secret']); ?>" alt="QR Code">
                        <?php
                        hesk_show_info(sprintf($hesklang['mfa_verification_auth_app_cant_scan'], chunk_split($_SESSION['tfa_secret'], 4, ' ')), ' ', false);
                        ?>
                        <p>&nbsp;</p>
                        <p><?php echo $hesklang['mfa_verification_auth_app_enter_code']; ?><br>&nbsp;</p>
                    </div>
                <?php } ?>
                <form id="verify-form" class="form" action="manage_mfa.php" method="post">
                    <div class="form-group">
                        <label><?php echo $hesklang['mfa_code']; ?></label>
                        <input name="verification-code" id="verify-input" type="text" class="form-control" maxlength="6" placeholder="000000" autocomplete="off">
                        <input type="hidden" name="current-step" value="2">
                        <input type="hidden" name="mfa-method" value="<?php echo $mfa_method; ?>">
                        <button type="submit" class="btn btn-full" ripple="ripple"><?php echo $hesklang['mfa_verify']; ?></button>
                    </div>
                </form>
                <script>
                    $('#verify-form').preventDoubleSubmission();
                    $('#verify-form').submit(function() {
                        $(this).find('button[type="submit"]')
                            .attr('disabled', 'disabled')
                            .addClass('disabled');
                    });
                    $('#verify-input').keyup(function() {
                        if (this.value.length === 6) {
                            $('#verify-form').submit();
                        }
                    });
                </script>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <a href="manage_mfa.php">
                    <button type="button" class="btn btn--blue-border"><?php echo $hesklang['wizard_back']; ?></button>
                </a>
            </div>
            <?php } elseif ($display_step === 3) { ?>
            <div class="step-item step-3">
                <?php if (intval($_SESSION['mfa_enrollment']) !== 0) {
                    $backup_codes = implode("\n", array_map(function($code, $key) { return str_pad(($key+1), 2, ' ', STR_PAD_LEFT) . '. ' . substr($code, 0, 4) . '-' . substr($code, 4); }, $_SESSION['mfa_backup_codes'], array_keys($_SESSION['mfa_backup_codes'])));
                    hesk_show_success('<div class="shield-icon"><svg class="icon icon-anonymize"><use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-anonymize"></use></svg></div>' . $hesklang['mfa_configured'], ' ', false);
                    hesk_show_info('<p style="margin-top:10px">'.$hesklang['mfa_backup_codes_description'].'</p><pre style="margin-top:20px; font-family: monospace; font-size: 16px;">'.$backup_codes.'</pre>', $hesklang['mfa_backup_codes_header'] . '<br>', false);
                } else {
                    hesk_show_info($hesklang['mfa_removed'], ' ', false);
                } ?>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <div class="verify-back">
                    <a href="profile.php" class="btn btn-full" ripple="ripple"><?php echo $hesklang['view_profile']; ?></a>
                </div>
            </div>
            <?php } ?>
        </div>
    </article>

    <?php
    if (intval($_SESSION['mfa_enrollment']) !== 0):
        $res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` WHERE `user_id`=".intval($_SESSION['id']));
        $num = hesk_dbResult($res,0,0);
    ?>

    <p>&nbsp;</p>
    <p>&nbsp;</p>
    <p>&nbsp;</p>

    <section class="mfa__head">
        <h2>
            <?php echo $hesklang['mfa_backup_codes']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['mfa_backup_codes_info']; ?>
                    </div>
                </div>
            </div>
        </h2>
    </section>
    <article class="profile__wrapper mfa">
        <div>
            <p><?php echo $hesklang['mfa_backup_codes_num']; ?></p>
            <p><?php echo sprintf($hesklang['mfa_backup_codes_num2'], $num); ?></p>
            <form class="form" action="manage_mfa.php" method="post">
                <div class="form-group">
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    <button type="submit" name="new_codes" value="Y" class="btn btn--blue-border" ripple="ripple"><?php echo $hesklang['mfa_new_codes']; ?></button>
                    <button type="submit" name="delete_codes" value="Y" class="btn btn--blue-border" ripple="ripple"><?php echo $hesklang['mfa_del_codes']; ?></button>
                </div>
            </form>
        </div>
    </article>

    <?php endif; ?>

</div>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
