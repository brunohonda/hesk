<?php
global $hesk_settings, $hesklang;
/**
 * @var array $messages - Feedback messages to be displayed, if any
 * @var array $model - Relevant information for the page:
 *   - token - A verification token used by the backend for security purposes
 *   - verifyMethod - 'EMAIL' if user is authenticating via email, or 'AUTH-APP' if authenticating via an authenticator app
 *   - email - The user's email address
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
    <link rel="stylesheet" href="<?php echo TEMPLATE_PATH; ?>customer/css/jquery.modal.css" />
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
                    <?php renderLoginNavbarElements(); ?>
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
                    <div class="last"><?php echo $hesklang['customer_login']; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-team"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['customer_login']; ?></span>
                </h1>
                <section class="ticket__body_block">
                    <div id="mfa-verify">
                        <form action="login.php" method="post" name="form1" id="verify-form" class="form ticket-create" novalidate>
                            <?php hesk3_show_messages($messages); ?>
                            <section class="form-groups centered">
                                <div class="form-group">
                                    <label class="label"><?php echo $hesklang['mfa_verification_code']; ?></label>
                                    <input type="text" name="verification-code" maxlength="6"
                                           id="verify-input"
                                           class="form-control"
                                           placeholder="000000"
                                           required>
                                    <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                                </div>
                            </section>
                            <div class="form-footer">
                                <input type="hidden" name="a" value="mfa_verify">
                                <input type="hidden" name="mfa-method" value="<?php echo $model['verifyMethod']; ?>">
                                <button id="verify-submit" type="submit" class="btn btn-full" ripple="ripple"><?php echo $hesklang['mfa_verify']; ?></button>
                            </div>
                        </form>
                        <?php if ($model['verifyMethod'] === 'EMAIL'): ?>
                            &nbsp;
                            <form action="login.php" class="form" id="send-another-email-form" method="post" name="send-another-email-form" novalidate>
                                <button class="btn btn-link underline" type="submit">
                                    <?php echo $hesklang['mfa_send_another_email']; ?>
                                </button>
                                <input type="hidden" name="a" value="mfa_backup_email">
                            </form>
                            <br>
                        <?php endif; ?>
                        <a href="javascript:HESK_FUNCTIONS.toggleLayerDisplay('verify-another-way');HESK_FUNCTIONS.toggleLayerDisplay('mfa-verify')"  class="underline">
                            <?php echo $hesklang['mfa_verify_another_way']; ?>
                        </a>
                    </div>
                    <div id="verify-another-way" style="display: none">
                        <ul>
                            <?php if ($model['verifyMethod'] === 'AUTH-APP'): ?>
                                <li>
                                    <div class="flex">
                                        <div class="mfa-alt-icon" aria-hidden="true">
                                            <svg class="icon icon-mail">
                                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-mail"></use>
                                            </svg>
                                        </div>
                                        <div class="mfa-alt-text">
                                            <form action="login.php" class="form" id="email-backup-form" method="post" name="email-backup-form" novalidate>
                                                <button class="btn btn-link underline" type="submit">
                                                    <?php echo sprintf($hesklang['mfa_verify_another_way_email'], hesk_maskEmailAddress($model['email'])); ?>
                                                </button>
                                                <input type="hidden" name="a" value="mfa_backup_email">
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                            <li>
                                <div class="flex">
                                    <div class="mfa-alt-icon" aria-hidden="true">
                                        <svg class="icon icon-lock">
                                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-lock"></use>
                                        </svg>
                                    </div>
                                    <div class="mfa-alt-text">
                                        <a href="javascript:HESK_FUNCTIONS.toggleLayerDisplay('backup-code-field')" class="underline">
                                            <?php echo $hesklang['mfa_verify_another_way_code']; ?>
                                        </a>
                                        <div id="backup-code-field" style="display: none">
                                            &nbsp;
                                            <form action="login.php" class="form" id="backup-form" method="post" name="backup-form" novalidate>
                                                <div class="form-group">
                                                    <label for="backupCode"><?php echo $hesklang['mfa_backup_code']; ?>:</label>
                                                    <input type="text" class="form-control" id="backupCode" name="backup-code" minlength="8" maxlength="9" autocomplete="off">
                                                </div>
                                                <div class="form__submit mfa">
                                                    <button class="btn btn-full" ripple="ripple" type="submit" id="backup-code-submit">
                                                        <?php echo $hesklang['s']; ?>
                                                    </button>
                                                </div>
                                                <input type="hidden" name="a" value="mfa_backup_code">
                                                <input type="hidden" name="mfa-method" value="<?php echo $model['verifyMethod']; ?>">
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <p style="text-align: center">
                            <a href="javascript:HESK_FUNCTIONS.toggleLayerDisplay('verify-another-way');HESK_FUNCTIONS.toggleLayerDisplay('mfa-verify')" class="underline">
                                <?php echo $hesklang['back']; ?>
                            </a>
                        </p>
                    </div>
                </section>
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
<script>
    var $verifyForm = $('#verify-form');
    var $backupForm = $('#backup-form');
    $verifyForm.preventDoubleSubmission();
    $backupForm.preventDoubleSubmission();
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
    $verifyForm.submit(function() {
        $('#verify-submit').attr('disabled', 'disabled')
            .addClass('disabled');
    });
    $backupForm.submit(function() {
        $('#backup-code-submit').attr('disabled', 'disabled')
            .addClass('disabled');
    });
</script>
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
