<?php
global $hesk_settings, $hesklang;
/**
 * @var array $messages - Feedback messages to be displayed, if any
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

require_once(TEMPLATE_PATH . 'customer/util/alerts.php');
require_once(TEMPLATE_PATH . 'customer/partial/login-navbar-elements.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0" />
    <?php include(HESK_PATH . 'inc/favicon.inc.php'); ?>
    <meta name="format-detection" content="telephone=no" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <!--[if IE]>
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/ie9.css" />
    <![endif]-->
    <?php include(TEMPLATE_PATH . '../../head.txt'); ?>
</head>

<body class="cust-help">
<?php include(TEMPLATE_PATH . '../../header.txt'); ?>
<?php renderCommonElementsAfterBody(); ?>
<div class="wrapper">
    <main class="main" id="maincontent">
        <header class="header">
            <div class="contr">
                <div class="header__inner">
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="header__logo">
                        <?php echo $hesk_settings['hesk_title']; ?>
                    </a>
                    <?php renderLoginNavbarElements($customerUserContext); ?>
                    <?php renderNavbarLanguageSelect(); ?>
                </div>
            </div>
        </header>
        <div class="breadcrumbs">
            <div class="contr">
                <div class="breadcrumbs__inner">
                    <a href="<?php echo $hesk_settings['site_url']; ?>">
                        <span><?php echo $hesk_settings['site_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>">
                        <span><?php echo $hesk_settings['hesk_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <a href="profile.php">
                        <span><?php echo $hesklang['customer_profile']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <div class="last"><?php echo $hesklang['mfa']; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($messages); ?>
                </div>
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-lock"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['mfa']; ?></span>
                </h1>
                <form action="manage_mfa.php" method="post" name="form1" id="formNeedValidation" class="form form-submit-ticket ticket-create" novalidate>
                    <?php hesk_show_info($hesklang['mfa_introduction']); ?>
                    <?php if ($customerUserContext['mfa_enrollment'] > 0) {
                        hesk_show_notice($hesklang['mfa_reset_warning']);
                    } ?>
                    <div data-step="1">
                        <ul class="step-bar no-click">
                            <li data-link="1" data-all="3">
                                <?php echo $hesklang['mfa_step_method']; ?>
                            </li>
                            <li data-link="2" data-all="3">
                                <?php echo $hesklang['mfa_step_verification']; ?>
                            </li>
                            <li data-link="3" data-all="3">
                                <?php echo $hesklang['mfa_step_complete']; ?>
                            </li>
                        </ul>
                    </div>
                    <div class="step-item step-1">
                        <div><strong><?php echo $hesklang['mfa_select_method_colon']; ?><br>&nbsp;</strong></div>
                        <div class="radio-list">
                            <div class="radio-custom">
                                <input type="radio" id="mfa_method_email" name="mfa-method" value="1" <?php echo intval($_SESSION['customer']['mfa_enrollment']) === 1 ? 'checked' : ''; ?>>
                                <label for="mfa_method_email">
                                    <strong><?php echo $hesklang['mfa_method_email']; ?></strong><br>
                                    <span><?php echo sprintf($hesklang['mfa_method_email_subtext'], $_SESSION['customer']['email']); ?><br>&nbsp;</span>
                                </label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio" id="mfa_method_auth_app" name="mfa-method" value="2" <?php echo intval($_SESSION['customer']['mfa_enrollment']) === 2 ? 'checked' : ''; ?>>
                                <label for="mfa_method_auth_app">
                                    <strong><?php echo $hesklang['mfa_method_auth_app']; ?></strong><br>
                                    <span><?php echo $hesklang['mfa_method_auth_app_subtext']; ?><br>&nbsp;</span>
                                </label>
                            </div>
                            <?php if ($hesk_settings['require_mfa'] === 0): ?>
                            <div class="radio-custom">
                                <input type="radio" id="mfa_method_none" name="mfa-method" value="0" <?php echo intval($_SESSION['customer']['mfa_enrollment']) === 0 ? 'checked' : ''; ?>>
                                <label for="mfa_method_none">
                                    <strong><?php echo $hesklang['mfa_method_none']; ?></strong><br>
                                    <span><?php echo $hesklang['mfa_method_none_subtext']; ?><br>&nbsp;</span>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="current-step" value="1">
                        <button type="submit" class="btn btn-full next" ripple="ripple"><?php echo $hesklang['wizard_next']; ?></button>
                    </div>
                </form>
                <?php
                if ($customerUserContext['mfa_enrollment'] > 0):
                    $res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` WHERE `user_id`=".intval($_SESSION['customer']['id']) . " AND `user_type`='CUSTOMER'");
                    $num = hesk_dbResult($res,0,0);
                ?>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <form action="manage_mfa.php" method="post" name="form2" id="mfaBackupCodesForm" class="form form-submit-ticket ticket-create" novalidate>
                    <div class="step-item step-1">
                        <div>
                            <strong><?php echo $hesklang['mfa_backup_codes']; ?></strong>
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
                            <br>&nbsp;
                        </div>
                    </div>
                    <div>
                        <p><?php echo $hesklang['mfa_backup_codes_num']; ?></p>
                        <p><?php echo sprintf($hesklang['mfa_backup_codes_num2'], $num); ?></p>
                            <div class="form-group">
                                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                                <button type="submit" name="new_codes" value="Y" class="btn btn--blue-border" ripple="ripple"><?php echo $hesklang['mfa_new_codes']; ?></button>
                                <button type="submit" name="delete_codes" value="Y" class="btn btn--blue-border" ripple="ripple"><?php echo $hesklang['mfa_del_codes']; ?></button>
                            </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
        /*******************************************************************************
        The code below handles HESK licensing and must be included in the template.

        Removing this code is a direct violation of the HESK End User License Agreement,
        will void all support and may result in unexpected behavior.

        To purchase a HESK license and support future HESK development please visit:
        https://www.hesk.com/buy.php
         *******************************************************************************/
        $hesk_settings['hesk_license']('Qo8Zm9vdGVyIGNsYXNzPSJmb290ZXIiPg0KICAgIDxwIGNsY
XNzPSJ0ZXh0LWNlbnRlciI+UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3d3dy5oZXNrLmNvbSIgY
2xhc3M9ImxpbmsiPkhlbHAgRGVzayBTb2Z0d2FyZTwvYT4gPHNwYW4gY2xhc3M9ImZvbnQtd2VpZ2h0L
WJvbGQiPkhFU0s8L3NwYW4+PGJyPk1vcmUgSVQgZmlyZXBvd2VyPyBUcnkgPGEgaHJlZj0iaHR0cHM6L
y93d3cuc3lzYWlkLmNvbS8/dXRtX3NvdXJjZT1IZXNrJmFtcDt1dG1fbWVkaXVtPWNwYyZhbXA7dXRtX
2NhbXBhaWduPUhlc2tQcm9kdWN0X1RvX0hQIiBjbGFzcz0ibGluayI+U3lzQWlkPC9hPjwvcD4NCjwvZ
m9vdGVyPg0K',"\104", "a809404e0adf9823405ee0b536e5701fb7d3c969");
        /*******************************************************************************
        END LICENSE CODE
         *******************************************************************************/
        ?>
    </main>
</div>
<?php include(TEMPLATE_PATH . '../../footer.txt'); ?>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery-3.5.1.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/hesk_functions.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/svg4everybody.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/selectize.min.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery.modal.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<?php if (defined('RECAPTCHA')) : ?>
    <script src="https://www.google.com/recaptcha/api.js?hl=<?php echo $hesklang['RECAPTCHA']; ?>" async defer></script>
    <script>
        function recaptcha_submitForm() {
            document.getElementById("form1").submit();
        }
    </script>
<?php endif; ?>
</body>
</html>
