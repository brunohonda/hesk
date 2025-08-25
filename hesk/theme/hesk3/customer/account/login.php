<?php
global $hesk_settings, $hesklang;
/**
 * @var array $messages - Feedback messages to be displayed, if any
 * @var array $model - User's entered email if login attempt failed
 * @var array $validationFailures - Form fields that resulted in an error when attempting to register, if any
 * @var bool $displayForgotPasswordLink - `true` if customers are allowed to submit a "forgot password" request
 * @var bool $submittedForgotPasswordForm - `true` if the user submitted the "forgot password" form
 * @var bool $displayForgotPasswordModal - `true` if the "forgot password" form should be visible on page load
 * @var string $redirectUrl - URL to redirect the user to after logging in, if any
 * @var bool $allowAutologin - `true` if the user is permitted to autologin, `false` otherwise
 * @var bool $selectAutologin - `true` if the "automatically log in user" radio option should be selected
 * @var bool $selectSaveEmail - `true` if the "save email" radio option / checkbox should be selected/checked
 * @var bool $selectDoNotRemember - `true` if the "No thanks" radio option should be selected
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
    <style>
        #forgot-pw-submit {
            width: 200px;
        }
    </style>
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
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($serviceMessages); ?>
                    <?php
                    if (!$submittedForgotPasswordForm) {
                        hesk3_show_messages($messages);
                    }
                    ?>
                </div>
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-team"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['customer_login']; ?></span>
                </h1>
                <form action="login.php" method="post" name="form1"
                      id="formNeedValidation"
                      class="form form-submit-ticket ticket-create <?php echo count($validationFailures) ? 'invalid' : '' ?>"
                      novalidate>
                    <section class="form-groups centered">
                        <div class="form-group required">
                            <label class="label" for="email"><?php echo $hesklang['customer_email']; ?></label>
                            <input type="text" id="email" name="email" maxlength="255"
                                   class="form-control <?php echo in_array('login_email', $validationFailures) ? 'iserror' : '' ?>"
                                   value="<?php echo stripslashes(hesk_input($model['email'])); ?>" required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <div class="form-group required">
                            <label class="label" for="password"><?php echo $hesklang['pass']; ?></label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <?php if ($hesk_settings['customer_autologin']): ?>
                            <div class="radio-group">
                                <div class="radio-list">
                                    <div class="radio-custom" style="margin-top: 5px;">
                                        <input type="radio" id="remember_userAUTOLOGIN" name="remember_user" value="AUTOLOGIN" <?php echo $selectAutologin ? 'checked' : ''; ?>>
                                        <label for="remember_userAUTOLOGIN"><?php echo $hesklang['autologin']; ?></label>
                                    </div>
                                    <div class="radio-custom" style="margin-top: 5px;">
                                        <input type="radio" id="remember_userJUSTUSER" name="remember_user" value="JUSTUSER" <?php echo $selectSaveEmail ? 'checked' : ''; ?>>
                                        <label for="remember_userJUSTUSER"><?php echo $hesklang['customer_login_remember_just_email']; ?></label>
                                    </div>
                                    <div class="radio-custom" style="margin-top: 5px;">
                                        <input type="radio" id="remember_userNOTHANKS" name="remember_user" value="NOTHANKS" <?php echo $selectDoNotRemember ? 'checked' : ''; ?>>
                                        <label for="remember_userNOTHANKS"><?php echo $hesklang['nothx']; ?></label>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="reg__checkboxes">
                                <div class="form-group">
                                    <div class="checkbox-custom">
                                        <input type="checkbox" id="tableCheckboxId2" name="remember_user" value="JUSTUSER"  <?php echo $selectSaveEmail ? 'checked' : ''; ?> />
                                        <label for="tableCheckboxId2"><?php echo $hesklang['customer_login_remember_email']; ?></label>
                                    </div>
                                </div>
                            </div>
                        <?php endif;
                        if ($hesk_settings['question_use'] || ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] !== 1)):
                            ?>
                            <div class="captcha-block">
                                <h3><?php echo $hesklang['verify_header']; ?></h3>

                                <?php if ($hesk_settings['question_use']): ?>
                                    <div class="form-group">
                                        <label class="required" for="question"><?php echo $hesk_settings['question_ask']; ?></label>
                                        <?php
                                        $value = '';
                                        if (isset($_SESSION['c_question']))
                                        {
                                            $value = stripslashes(hesk_input($_SESSION['c_question']));
                                        }
                                        ?>
                                        <input type="text" class="form-control <?php echo in_array('question',$validationFailures) ? 'isError' : ''; ?>"
                                               id="question" name="question" size="20" value="<?php echo $value; ?>">
                                    </div>
                                <?php
                                endif;

                                if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] != 1)
                                {
                                    ?>
                                    <div class="form-group">
                                        <?php
                                        // SPAM prevention verified for this session
                                        if (isset($_SESSION['img_verified']))
                                        {
                                            echo $hesklang['vrfy'];
                                        }
                                        // Use reCAPTCHA V2?
                                        elseif ($hesk_settings['recaptcha_use'] == 2)
                                        {
                                            ?>
                                            <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
                                            <?php
                                        }
                                        // At least use some basic PHP generated image (better than nothing)
                                        else
                                        {
                                            $cls = in_array('mysecnum',$validationFailures) ? 'isError' : '';
                                            ?>
                                            <img data-sec-img="1" name="secimg" id="secimgLogin" src="print_sec_img.php?<?php echo rand(10000,99999); ?>" width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                            <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.getElementById('secimgLogin').src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                                <svg class="icon icon-refresh">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                                </svg>
                                            </a>
                                            <label class="required" for="mysecnum"><?php echo $hesklang['sec_enter']; ?></label>
                                            <input type="text" id="mysecnum" name="mysecnum" size="20" maxlength="5" autocomplete="off" class="form-control <?php echo $cls; ?>">
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="divider"></div>
                    </section>
                    <div class="form-footer">
                        <input type="hidden" name="a" value="login">
                        <input type="hidden" name="goto" value="<?php echo $redirectUrl; ?>">
                        <button type="submit" class="btn btn-full" ripple="ripple" id="recaptcha-submit"><?php echo $hesklang['customer_login']; ?></button>
                        <?php if ($displayForgotPasswordLink): ?>
                        <a href="login.php?forgot=1#modal-contents" data-modal="#forgot-modal" title="<?php echo $hesklang['opens_dialog']; ?>" role="button" class="link"><?php echo $hesklang['customer_forgot_password']; ?></a>
                        <?php endif; ?>
                        <?php if ($hesk_settings['customer_accounts_customer_self_register']): ?>
                        <a href="register.php" class="link"><?php echo $hesklang['create_account']; ?></a>
                        <?php endif; ?>
                    </div>
                    <?php
                    // Use Invisible reCAPTCHA?
                    if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] == 1 && ! isset($_SESSION['img_verified']))
                    {
                        ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="recaptcha-submit" data-callback="recaptcha_submitForm"></div>
                        <?php
                    }
                    ?>
                </form>
                <?php
                if ($hesk_settings['alink'] && $hesk_settings['customer_accounts_required'] === 2):
                ?>
                <div class="article__footer">
                    <a href="<?php echo $hesk_settings['admin_dir']; ?>/" class="link"><?php echo $hesklang['ap']; ?></a>
                </div>
                <?php endif; ?>
                <!-- Start forgot password form -->
                <div id="forgot-modal" role="dialog" aria-modal="true" aria-label="<?php echo $hesklang['reset_your_password']; ?>" class="<?php echo !$displayForgotPasswordModal ? 'modal' : ''; ?>">
                    <div id="modal-contents" class="<?php echo !$displayForgotPasswordModal ? '' : 'notification orange'; ?>" style="padding-bottom:15px">
                        <?php
                        if ($submittedForgotPasswordForm) {
                            hesk3_show_messages($messages);
                        }
                        ?>
                        <b><?php echo $hesklang['reset_your_password']; ?></b><br><br>
                        <?php echo $hesklang['reset_password_instructions']; ?>
                        <form action="login.php" method="post" name="form2" id="form2" class="form">
                            <div class="form-group">
                                <label class="label screen-reader-text skiplink" for="forgot-email"><?php echo $hesklang['email']; ?></label>
                                <input id="forgot-email" type="email" class="form-control" name="reset-email" value="<?php echo $model['email']; ?>">
                            </div>

                            <?php
                            // Use Invisible reCAPTCHA?
                            if (isset($_SESSION['img_verified'])) {
                                //-- No-op; user is verified
                            } elseif ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] == 1) {
                                ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="forgot-password-submit" data-callback="recaptcha_submitForgotPasswordForm"></div>
                                <?php
                            } elseif ($hesk_settings['secimg_use']) {
                                ?>
                                <div class="captcha-remind">
                                    <div class="form-group">
                                        <?php
                                        // Use reCAPTCHA V2?
                                        if ($hesk_settings['recaptcha_use'] == 2) {
                                            ?>
                                            <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
                                        <?php } else { ?>
                                            <img data-sec-img="1" name="secimg" id="secimgReset" src="print_sec_img.php?p=reset&amp;<?php echo rand(10000,99999); ?>" width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                            <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.getElementById('secimgReset').src='print_sec_img.php?p=reset&amp;'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                                <svg class="icon icon-refresh">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                                </svg>
                                            </a>
                                            <label class="required" for="mysecnum"><?php echo $hesklang['sec_enter']; ?></label>
                                            <input type="text" id="mysecnum" name="mysecnum" size="20" maxlength="5" autocomplete="off" class="form-control">
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>

                            <input type="hidden" name="a" value="forgot_password">
                            <input type="hidden" id="js" name="forgot" value="<?php echo (hesk_GET('forgot') ? '1' : '0'); ?>">
                            <button id="forgot-password-submit" type="submit" class="btn btn-full"><?php echo $hesklang['passs']; ?></button>
                        </form>
                    </div>
                </div>
                <!-- End ticket reminder form -->
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
    $(document).ready(function() {
        $('#select_category').selectize();
        $('a[data-modal]').on('click', function() {
            $($(this).data('modal')).modal();
            return false;
        });
        <?php if ($submittedForgotPasswordForm) { ?>
        $('#forgot-modal').modal();
        $('#forgot-email').select();
        <?php } ?>
    });
</script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<?php if (defined('RECAPTCHA')) : ?>
    <script src="https://www.google.com/recaptcha/api.js?hl=<?php echo $hesklang['RECAPTCHA']; ?>" async defer></script>
    <script>
        function recaptcha_submitForm() {
            document.getElementById("formNeedValidation").submit();
        }
        function recaptcha_submitForgotPasswordForm() {
            document.getElementById("form2").submit();
        }
    </script>
<?php endif; ?>
</body>
</html>
