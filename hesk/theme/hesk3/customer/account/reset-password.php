<?php
global $hesk_settings, $hesklang;
/**
 * @var array $validationFailures - Form fields that resulted in an error when attempting to reset password, if any
 * @var array $messages - Feedback messages to be displayed, if any
 * @var string $validHash - `true` if hash provided is valid, `false` otherwise.
 * @var string $resetPasswordHash - The hash provided via email.  Needed to verify identity of user resetting their password
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
                    <div class="last"><?php echo $hesklang['passs']; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($messages); ?>
                </div>
                <?php if ($validHash): ?>
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-team"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['passs']; ?></span>
                </h1>
                <div class="article-heading-tip">
                    <span><?php echo $hesklang['req_marked_with']; ?></span>
                    <span class="label required"></span>
                </div>
                <form action="reset_password.php" method="post" name="form1" id="formNeedValidation"
                      class="form form-submit-ticket ticket-create <?php echo count($validationFailures) ? 'invalid' : ''; ?>"
                      novalidate>
                    <section class="form-groups centered">
                        <div class="form-group required">
                            <label class="label"><?php echo $hesklang['new_pass']; ?></label>
                            <input type="password" name="password"
                                   class="form-control <?php if (in_array('password', $validationFailures)) {echo 'isError';} ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <div class="form-group required">
                            <label class="label"><?php echo $hesklang['confirm_new_pass']; ?></label>
                            <input type="password" name="confirm-password"
                                   class="form-control <?php if (in_array('password', $validationFailures)) {echo 'isError';} ?>"
                                   required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <div class="form-group">
                            <label class="label"><?php echo $hesklang['pwdst']; ?></label>
                            <div style="border: 1px solid #d4d6e3; width: 100%; height: 14px">
                                <div id="progressBar" style="font-size: 1px; height: 12px; width: 0px; border: none;">
                                </div>
                            </div>
                        </div>
                        <?php
                        if ($hesk_settings['question_use'] || ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] !== 1)):
                        ?>
                            <div class="captcha-block">
                                <h2><?php echo $hesklang['verify_header']; ?></h2>

                                <?php if ($hesk_settings['question_use']): ?>
                                    <div class="form-group">
                                        <label class="required"><?php echo $hesk_settings['question_ask']; ?></label>
                                        <?php
                                        $value = '';
                                        if (isset($_SESSION['c_question']))
                                        {
                                            $value = stripslashes(hesk_input($_SESSION['c_question']));
                                        }
                                        ?>
                                        <input type="text" class="form-control <?php echo in_array('question',$_SESSION['a_iserror']) ? 'isError' : ''; ?>"
                                               name="question" size="20" value="<?php echo $value; ?>">
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
                                            $cls = in_array('mysecnum',$_SESSION['a_iserror']) ? 'isError' : '';
                                            ?>
                                            <img name="secimg" src="print_sec_img.php?<?php echo rand(10000,99999); ?>" width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                            <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.form1.secimg.src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                                <svg class="icon icon-refresh">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                                </svg>
                                            </a>
                                            <label class="required"><?php echo $hesklang['sec_enter']; ?></label>
                                            <input type="text" name="mysecnum" size="20" maxlength="5" autocomplete="off" class="form-control <?php echo $cls; ?>">
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                            <div class="divider"></div>
                        <?php
                        endif;
                        ?>
                    </section>
                    <div class="form-footer">
                        <input type="hidden" name="hash" value="<?php echo $resetPasswordHash ?>">
                        <button type="submit" class="btn btn-full" ripple="ripple" id="recaptcha-submit"><?php echo $hesklang['passs']; ?></button>
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
            document.getElementById("formNeedValidation").submit();
        }
    </script>
<?php endif; ?>
<script>
    $('input[name="password"]').keyup(function() {
        HESK_FUNCTIONS.checkPasswordStrength(this.value);
    });
</script>
</body>
</html>
