<?php
global $hesk_settings, $hesklang;
/**
 * @var string $trackingId
 * @var string $email
 * @var boolean $rememberEmail
 * @var boolean $displayForgotTrackingIdForm
 * @var boolean $submittedForgotTrackingIdForm
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

require_once(TEMPLATE_PATH . 'customer/util/alerts.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo HESK_PATH; ?>img/favicon/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo HESK_PATH; ?>img/favicon/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo HESK_PATH; ?>img/favicon/favicon-16x16.png" />
    <link rel="manifest" href="<?php echo HESK_PATH; ?>img/favicon/site.webmanifest" />
    <link rel="mask-icon" href="<?php echo HESK_PATH; ?>img/favicon/safari-pinned-tab.svg" color="#5bbad5" />
    <link rel="shortcut icon" href="<?php echo HESK_PATH; ?>img/favicon/favicon.ico" />
    <meta name="msapplication-TileColor" content="#2d89ef" />
    <meta name="msapplication-config" content="<?php echo HESK_PATH; ?>img/favicon/browserconfig.xml" />
    <meta name="theme-color" content="#ffffff" />
    <meta name="format-detection" content="telephone=no" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <!--[if IE]>
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/ie9.css" />
    <![endif]-->
    <style>
        #forgot-tid-submit {
            width: 200px;
        }
    </style>
    <link rel="stylesheet" href="<?php echo TEMPLATE_PATH; ?>customer/css/jquery.modal.css" />
    <?php include(TEMPLATE_PATH . '../../head.txt'); ?>
</head>

<body class="cust-help">
<?php include(TEMPLATE_PATH . '../../header.txt'); ?>
<div class="wrapper">
    <main class="main">
        <header class="header">
            <div class="contr">
                <div class="header__inner">
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="header__logo">
                        <?php echo $hesk_settings['hesk_title']; ?>
                    </a>
                    <?php if ($hesk_settings['can_sel_lang']): ?>
                        <div class="header__lang">
                            <form method="get" action="" style="margin:0;padding:0;border:0;white-space:nowrap;">
                                <div class="dropdown-select center out-close">
                                    <select name="language" onchange="this.form.submit()">
                                        <?php hesk_listLanguages(); ?>
                                    </select>
                                </div>
                                <?php foreach (hesk_getCurrentGetParameters() as $key => $value): ?>
                                    <input type="hidden" name="<?php echo hesk_htmlentities($key); ?>"
                                           value="<?php echo hesk_htmlentities($value); ?>">
                                <?php endforeach; ?>
                            </form>
                        </div>
                    <?php endif; ?>
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
                    <div class="last"><?php echo $hesklang['view_ticket']; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php
                    if (!$submittedForgotTrackingIdForm) {
                        hesk3_show_messages($messages);
                    }
                    ?>
                </div>
                <h3 class="article__heading article__heading--form">
                    <div class="icon-in-circle">
                        <svg class="icon icon-document">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-document"></use>
                        </svg>
                    </div>
                    <span class="ml-1"><?php echo $hesklang['view_existing']; ?></span>
                </h3>
                <form action="ticket.php" method="get" name="form2" id="formNeedValidation" class="form form-submit-ticket ticket-create" novalidate>
                    <section class="form-groups centered">
                        <div class="form-group required">
                            <label class="label"><?php echo $hesklang['ticket_trackID']; ?></label>
                            <input type="text" name="track" maxlength="20" class="form-control" value="<?php echo $trackingId; ?>" required>
                            <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                        </div>
                        <?php
                        $tmp = '';
                        if ($hesk_settings['email_view_ticket'])
                        {
                            $tmp = 'document.form1.email.value=document.form2.e.value;';
                            ?>
                            <div class="form-group required">
                                <label class="label"><?php echo $hesklang['email']; ?></label>
                                <input type="email" class="form-control" name="e" size="35" value="<?php echo $email; ?>" required>
                                <div class="form-control__error"><?php echo $hesklang['this_field_is_required']; ?></div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-custom">
                                    <input type="hidden" name="f" value="1">
                                    <input type="checkbox" name="r" value="Y" id="inputRememberMyEmail" <?php if ($rememberEmail) { ?>checked<?php } ?>>
                                    <label for="inputRememberMyEmail"><?php echo $hesklang['rem_email']; ?></label>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </section>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-full" ripple="ripple"><?php echo $hesklang['view_ticket']; ?></button>
                        <a href="ticket.php?forgot=1#modal-contents" data-modal="#forgot-modal" class="link"><?php echo $hesklang['forgot_tid']; ?></a>
                    </div>
                    </form>

                    <!-- Start ticket reminder form -->
                    <div id="forgot-modal" class="<?php echo !$displayForgotTrackingIdForm ? 'modal' : ''; ?>">
                        <div id="modal-contents" class="<?php echo !$displayForgotTrackingIdForm ? '' : 'notification orange'; ?>" style="padding-bottom:15px">
                            <?php
                            if ($submittedForgotTrackingIdForm) {
                                hesk3_show_messages($messages);
                            }
                            ?>
                            <b><?php echo $hesklang['forgot_tid']; ?></b><br><br>
                            <?php echo $hesklang['tid_mail']; ?>
                            <form action="index.php" method="post" name="form1" id="form1" class="form">
                                <div class="form-group">
                                    <label class="label" style="display: none"><?php echo $hesklang['email']; ?></label>
                                    <input id="forgot-email" type="email" class="form-control" name="email" value="<?php echo $email; ?>">
                                </div>
                                <div class="form-group">
                                    <div class="radio-custom">
                                        <input type="radio" name="open_only" id="open_only1" value="1" <?php echo $hesk_settings['open_only'] ? 'checked' : ''; ?>>
                                        <label for="open_only1">
                                            <?php echo $hesklang['oon1']; ?>
                                        </label>
                                    </div>
                                    <div class="radio-custom">
                                        <input type="radio" name="open_only" id="open_only0" value="0" <?php echo !$hesk_settings['open_only'] ? 'checked' : ''; ?>>
                                        <label for="open_only0">
                                            <?php echo $hesklang['oon2']; ?>
                                        </label>
                                    </div>
                                </div>

                                <?php
                                // Use Invisible reCAPTCHA?
                                if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] == 1) {
                                    define('RECAPTCHA',1);
                                    ?>
                                    <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="forgot-tid-submit" data-callback="recaptcha_submitForm"></div>
                                <?php
                                } elseif ($hesk_settings['secimg_use']) {
                                ?>
                                <div class="captcha-remind">
                                    <div class="form-group">
                                        <?php
                                        // Use reCAPTCHA V2?
                                        if ($hesk_settings['recaptcha_use'] == 2) {
                                            define('RECAPTCHA',1);
                                            ?>
                                            <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
                                        <?php } else { ?>
                                            <img name="secimg" src="print_sec_img.php?<?php echo rand(10000,99999); ?>" width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                            <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.form1.secimg.src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
                                                <svg class="icon icon-refresh">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-refresh"></use>
                                                </svg>
                                            </a>
                                            <label class="required"><?php echo $hesklang['sec_enter']; ?></label>
                                            <input type="text" name="mysecnum" size="20" maxlength="5" autocomplete="off" class="form-control">
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                                }
                                ?>

                                <input type="hidden" name="a" value="forgot_tid">
                                <input type="hidden" id="js" name="forgot" value="<?php echo (hesk_GET('forgot') ? '1' : '0'); ?>">
                                <button id="forgot-tid-submit" type="submit" class="btn btn-full"><?php echo $hesklang['tid_send']; ?></button>
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
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/selectize.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery.modal.min.js"></script>
<script>
    $(document).ready(function() {
        $('#select_category').selectize();
        $('a[data-modal]').on('click', function() {
            $($(this).data('modal')).modal();
            return false;
        });
        <?php if ($submittedForgotTrackingIdForm) { ?>
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
        document.getElementById("form1").submit();
    }
</script>
<?php endif; ?>
</body>
</html>
