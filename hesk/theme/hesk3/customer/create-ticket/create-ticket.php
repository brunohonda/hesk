<?php
global $hesk_settings, $hesklang;
/**
 * @var string $categoryName
 * @var int $categoryId
 * @var array $visibleCustomFieldsBeforeMessage
 * @var array $visibleCustomFieldsAfterMessage
 * @var array $customFieldsBeforeMessage
 * @var array $customFieldsAfterMessage
 * @var bool $customerLoggedIn - `true` if a customer is logged in, `false` otherwise
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

require_once(TEMPLATE_PATH . 'customer/util/alerts.php');
require_once(TEMPLATE_PATH . 'customer/util/custom-fields.php');
require_once(TEMPLATE_PATH . 'customer/util/attachments.php');
require_once(TEMPLATE_PATH . 'customer/partial/login-navbar-elements.php');
require_once(HESK_PATH . 'inc/priorities.inc.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $hesk_settings['tmp_title']; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0" />
    <?php include(HESK_PATH . 'inc/favicon.inc.php'); ?>
    <meta name="format-detection" content="telephone=no" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/dropzone.min.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <!--[if IE]>
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/ie9.css" />
    <![endif]-->
    <style>
        .form-footer .btn {
            margin-top: 20px;
        }
    </style>
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
                    <a href="index.php">
                        <span><?php echo $hesk_settings['hesk_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <a href="index.php?a=add">
                        <span><?php echo $hesklang['submit_ticket'] ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <div class="last"><?php echo $categoryName; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($serviceMessages); ?>
                    <?php hesk3_show_messages($messages); ?>
                </div>
                <h1 class="article__heading article__heading--form">
                    <span class="icon-in-circle" aria-hidden="true">
                        <svg class="icon icon-submit-ticket">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-submit-ticket"></use>
                        </svg>
                    </span>
                    <span class="ml-1"><?php echo $hesklang['submit_a_support_request']; ?></span>
                </h1>
                <div class="article-heading-tip">
                    <span><?php echo $hesklang['req_marked_with']; ?></span>
                    <span class="label required"></span>
                </div>
                <form class="form form-submit-ticket ticket-create <?php echo count($_SESSION['iserror']) ? 'invalid' : ''; ?>" method="post" action="submit_ticket.php?submit=1" aria-label="<?php echo $hesklang['create_a_ticket']; ?>" name="form1" id="form1" enctype="multipart/form-data" onsubmit="<?php if ($hesk_settings['submitting_wait']): ?>hesk_showLoadingMessage('recaptcha-submit');<?php endif; ?>">
                    <?php if (!$customerLoggedIn) { ?>
                    <div class="form-group">
                        <label class="label required" for="name"><?php echo $hesklang['name']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('name', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        ?>
                        <input type="text" id="name" name="name"
                               class="<?php echo $input_css; ?>"
                               maxlength="50"
                               value="<?php
                               if (isset($_SESSION['c_name'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_name']));
                               } ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="label <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>" for="email"><?php echo $hesklang['email']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('email', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('email', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        ?>
                        <input type="email"
                               class="<?php echo $input_css; ?>"
                               name="email" id="email" maxlength="1000"
                               value="<?php
                               if (isset($_SESSION['c_email'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_email']));
                               } ?>" <?php if($hesk_settings['detect_typos']) { echo ' onblur="HESK_FUNCTIONS.suggestEmail(\'email\', \'email_suggestions\', 0)"'; } ?>
                               <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>>
                        <div id="email_suggestions"></div>
                    </div>
                    <?php
                    if ($hesk_settings['confirm_email']):
                        ?>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('email2', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('email2', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        if ($customerLoggedIn) {
                            $input_css .= ' as-text';
                        }
                        ?>
                        <div class="form-group">
                            <label class="label <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>" for="email2"><?php echo $hesklang['confemail']; ?>:</label>
                            <input type="<?php echo $hesk_settings['multi_eml'] ? 'text' : 'email'; ?>"
                                   class="<?php echo $input_css; ?>"
                                   name="email2" id="email2" maxlength="1000"
                                <?php if ($customerLoggedIn) { echo 'readonly'; } ?>
                                   value="<?php if (isset($_SESSION['c_email2'])) {echo stripslashes(hesk_input($_SESSION['c_email2']));} ?>"
                                   <?php if ($hesk_settings['require_email']) { ?>required<?php } ?>>
                        </div>
                    <?php endif;
                    }
                    if ($hesk_settings['multi_eml'] && !isset($_SESSION['c_followers'])): ?>
                    <div class="form-group" id="cc-link">
                        <a href="#" onclick="HESK_FUNCTIONS.toggleLayerDisplay('cc-div');HESK_FUNCTIONS.toggleLayerDisplay('cc-link')">
                            <?php echo $hesklang['add_cc']; ?>
                        </a>
                    </div>
                    <?php endif;
                    if ($hesk_settings['multi_eml']):
                        $display = isset($_SESSION['c_followers']) ? 'block' : 'none';
                    ?>
                    <div class="form-group" id="cc-div" style="display: <?php echo $display; ?>">
                        <label class="label" for="follower_email"><?php echo $hesklang['cc']; ?>:</label>
                        <?php
                        $input_css = 'form-control';
                        if (in_array('followers', $_SESSION['iserror'])) {
                            $input_css .= ' isError';
                        }
                        if (in_array('followers', $_SESSION['isnotice'])) {
                            $input_css .= ' isNotice';
                        }
                        ?>
                        <input type="text"
                               class="<?php echo $input_css; ?>"
                               name="follower_email" id="follower_email" maxlength="1000"
                               value="<?php
                               if (isset($_SESSION['c_followers'])) {
                                   echo stripslashes(hesk_input($_SESSION['c_followers']));
                               } ?>" <?php if($hesk_settings['detect_typos']) { echo ' onblur="HESK_FUNCTIONS.suggestEmail(\'follower_email\', \'follower_email_suggestions\', 0)"'; } ?>>
                        <div id="follower_email_suggestions"></div>
                        <p><?php if ($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required']) echo $hesklang['only_verified_cc'] . ' '; echo $hesklang['cc_help']; ?></p>
                    </div>
                    <?php
                    endif;
                    if ($hesk_settings['cust_urgency']): ?>
                        <section class="param">
                            <span class="label required <?php if (in_array('priority',$_SESSION['iserror'])) echo 'isErrorStr'; ?>"><?php echo $hesklang['priority']; ?>:</span>
                            <div class="dropdown-select center out-close priority select-priority">
                                <select name="priority">
                                    <?php if ($hesk_settings['select_pri']): ?>
                                        <option value=""><?php echo $hesklang['select']; ?></option>
                                    <?php endif; ?>
                                    <?php
                                        //Get User access priority
                                        echo hesk_get_priority_select('', 0, $_SESSION['c_priority']);
                                    ?>
                                </select>
                            </div>
                        </section>
                    <?php
                    endif;
                    if (count($visibleCustomFieldsBeforeMessage) > 0):
                    ?>
                    <div class="divider"></div>
                    <?php
                    endif;
                    hesk3_output_custom_fields($customFieldsBeforeMessage);

                    if ($hesk_settings['require_subject'] != -1 || $hesk_settings['require_message'] != -1): ?>
                        <div class="divider"></div>
                        <?php if ($hesk_settings['require_subject'] != -1): ?>
                            <div class="form-group">
                                <label class="label <?php if ($hesk_settings['require_subject']) { ?>required<?php } ?>" for="subject">
                                    <?php echo $hesklang['subject']; ?>:
                                </label>
                                <input type="text" id="subject" class="form-control <?php if (in_array('subject',$_SESSION['iserror'])) {echo 'isError';} ?>"
                                       name="subject" maxlength="70"
                                       value="<?php if (isset($_SESSION['c_subject'])) {echo stripslashes(hesk_input($_SESSION['c_subject']));} ?>"
                                       <?php if ($hesk_settings['require_subject']) { ?>required<?php } ?>>
                            </div>
                            <?php
                        endif;
                        if ($hesk_settings['require_message'] != -1): ?>
                            <div class="form-group">
                                <label class="label <?php if ($hesk_settings['require_message']) { ?>required<?php } ?>" for="message">
                                    <?php echo $hesklang['message']; ?>:
                                </label>
                                <textarea class="form-control <?php if (in_array('message',$_SESSION['iserror'])) {echo 'isError';} ?>"
                                          id="message" name="message" rows="12" cols="60"
                                          <?php if ($hesk_settings['require_message']) { ?>required<?php } ?>><?php if (isset($_SESSION['c_message'])) {echo stripslashes(hesk_input($_SESSION['c_message']));} ?></textarea>
                                <?php if (has_public_kb() && $hesk_settings['kb_recommendanswers'] && ! isset($_REQUEST['do_not_suggest'])): ?>
                                    <div class="kb-suggestions">
                                        <h2><?php echo $hesklang['sc']; ?>:</h2>
                                        <ul id="kb-suggestion-list" class="type--list">
                                        </ul>
                                        <div id="suggested-article-hidden-inputs" style="display: none">
                                            <?php // Will be populated with the list sent to the create ticket logic ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        endif;
                    endif;

                    if (count($visibleCustomFieldsAfterMessage) > 0): ?>
                    <div class="divider"></div>
                    <?php
                    endif;

                    hesk3_output_custom_fields($customFieldsAfterMessage);

                    if ($hesk_settings['attachments']['use']):
                    ?>
                        <div class="divider"></div>
                        <section class="param param--attach">
                            <span class="label"><?php echo $hesklang['attachments']; ?>:</span>
                            <div class="attach">
                                <div>
                                    <?php hesk3_output_drag_and_drop_attachment_holder(); ?>
                                </div>
                                <div class="attach-tooltype">
                                    <a class="link" href="file_limits.php" onclick="HESK_FUNCTIONS.openWindow('file_limits.php',250,500);return false;">
                                        <?php echo $hesklang['ful']; ?>
                                    </a>
                                </div>
                            </div>
                        </section>
                        <div class="divider"></div>
                        <?php
                    endif;

                    if ($hesk_settings['question_use'] || ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] !== 1)):
                    ?>
                    <div class="captcha-block">
                        <h2><?php echo $hesklang['verify_header']; ?></h2>

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
                            <input type="text" id="question" class="form-control <?php echo in_array('question',$_SESSION['iserror']) ? 'isError' : ''; ?>"
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
                                        $cls = in_array('mysecnum',$_SESSION['iserror']) ? 'isError' : '';
                                        ?>
                                        <img name="secimg" src="print_sec_img.php?<?php echo rand(10000,99999); ?>" width="150" height="40" alt="<?php echo $hesklang['sec_img']; ?>" title="<?php echo $hesklang['sec_img']; ?>" style="vertical-align:text-bottom">
                                        <a class="btn btn-refresh" href="javascript:void(0)" onclick="javascript:document.form1.secimg.src='print_sec_img.php?'+ ( Math.floor((90000)*Math.random()) + 10000);">
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
                    <div class="divider"></div>
                        <?php
                    endif;

                    if ($hesk_settings['submit_notice']):
                    ?>
                    <div class="alert">
                        <div class="alert__inner">
                            <b class="font-weight-bold"><?php echo $hesklang['before_submit']; ?>:</b>
                            <ul>
                                <li><?php echo $hesklang['all_info_in']; ?>.</li>
                                <li><?php echo $hesklang['all_error_free']; ?>.</li>
                            </ul>
                            <br>
                            <b class="font-weight-bold"><?php echo $hesklang['we_have']; ?>:</b>
                            <ul>
                                <li><?php echo hesk_htmlspecialchars(hesk_getClientIP()).' '.$hesklang['recorded_ip']; ?></li>
                                <li><?php echo $hesklang['recorded_time']; ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>


                    <div class="form-footer">
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                        <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                        <button type="submit" class="btn btn-full" ripple="ripple" id="recaptcha-submit">
                            <?php echo $hesklang['sub_ticket']; ?>
                        </button>
                        <!-- Do not delete or modify the code below, it is used to detect simple SPAM bots -->
                        <input type="hidden" name="hx" value="3" /><input type="hidden" name="hy" value="">
                        <!-- >
                        <input type="text" name="phone" value="3">
                        < -->
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
            </div>
        </div>
        <div id="loading-overlay" class="loading-overlay">
            <div id="loading-message" class="loading-message">
                <div class="spinner"></div>
                <p><?php echo $hesklang['sending_wait']; ?></p>
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
    <!-- end main -->
</div>
<?php include(TEMPLATE_PATH . '../../footer.txt'); ?>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery-3.5.1.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/hesk_functions.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/svg4everybody.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/selectize.min.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/datepicker.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/dropzone.min.js"></script>
<script type="text/javascript">
(function ($) { $.fn.datepicker.language['en'] = {
    days: ['<?php echo $hesklang['d0']; ?>', '<?php echo $hesklang['d1']; ?>', '<?php echo $hesklang['d2']; ?>', '<?php echo $hesklang['d3']; ?>', '<?php echo $hesklang['d4']; ?>', '<?php echo $hesklang['d5']; ?>', '<?php echo $hesklang['d6']; ?>'],
    daysShort: ['<?php echo $hesklang['sun']; ?>', '<?php echo $hesklang['mon']; ?>', '<?php echo $hesklang['tue']; ?>', '<?php echo $hesklang['wed']; ?>', '<?php echo $hesklang['thu']; ?>', '<?php echo $hesklang['fri']; ?>', '<?php echo $hesklang['sat']; ?>'],
    daysMin: ['<?php echo $hesklang['su']; ?>', '<?php echo $hesklang['mo']; ?>', '<?php echo $hesklang['tu']; ?>', '<?php echo $hesklang['we']; ?>', '<?php echo $hesklang['th']; ?>', '<?php echo $hesklang['fr']; ?>', '<?php echo $hesklang['sa']; ?>'],
    months: ['<?php echo $hesklang['m1']; ?>','<?php echo $hesklang['m2']; ?>','<?php echo $hesklang['m3']; ?>','<?php echo $hesklang['m4']; ?>','<?php echo $hesklang['m5']; ?>','<?php echo $hesklang['m6']; ?>', '<?php echo $hesklang['m7']; ?>','<?php echo $hesklang['m8']; ?>','<?php echo $hesklang['m9']; ?>','<?php echo $hesklang['m10']; ?>','<?php echo $hesklang['m11']; ?>','<?php echo $hesklang['m12']; ?>'],
    monthsShort: ['<?php echo $hesklang['ms01']; ?>','<?php echo $hesklang['ms02']; ?>','<?php echo $hesklang['ms03']; ?>','<?php echo $hesklang['ms04']; ?>','<?php echo $hesklang['ms05']; ?>','<?php echo $hesklang['ms06']; ?>', '<?php echo $hesklang['ms07']; ?>','<?php echo $hesklang['ms08']; ?>','<?php echo $hesklang['ms09']; ?>','<?php echo $hesklang['ms10']; ?>','<?php echo $hesklang['ms11']; ?>','<?php echo $hesklang['ms12']; ?>'],
    today: '<?php echo hesk_slashJS($hesklang['r1']); ?>',
    clear: '<?php echo hesk_slashJS($hesklang['clear']); ?>',
    dateFormat: '<?php echo hesk_slashJS($hesk_settings['format_datepicker_js']); ?>',
    timeFormat: '<?php echo hesk_slashJS($hesk_settings['format_time']); ?>',
    firstDay: <?php echo $hesklang['first_day_of_week']; ?>
}; })(jQuery);
</script>
<?php
if (defined('RECAPTCHA'))
{
    echo '<script src="https://www.google.com/recaptcha/api.js?hl='.$hesklang['RECAPTCHA'].'" async defer></script>';
    echo '<script type="text/javascript">
        function recaptcha_submitForm() {
            document.getElementById("form1").submit();
        }
        </script>';
}

function hesk_jsString($str)
{
    $str  = addslashes($str);
    $str  = str_replace('<br />' , '' , $str);
    $from = array("/\r\n|\n|\r/", '/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
    $to   = array("\\r\\n' + \r\n'", "$1", "$1");
    return preg_replace($from,$to,$str);
} // END hesk_jsString()
?>
<script>
    $(document).ready(function() {
        $('#select_category').selectize();
        hesk_loadNoResultsSelectizePlugin('<?php echo hesk_jsString($hesklang['no_results_found']); ?>');
        <?php

        foreach ($customFieldsBeforeMessage as $customField)
        {
            if ($customField['type'] == 'select')
            {
                if ($customField['value']['is_searchable'] == 1) {
                    echo "$('#{$customField['name']}').addClass('read-write').attr('placeholder', '".$hesklang["search_by_pattern"]."').selectize({
                        delimiter: ',',
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['displayName'],
                        create: false,
                        copyClassesToDropdown: true,
                        plugins: ['no_results'],
                    });";
                } else {
                    echo "$('#{$customField['name']}').selectize();";
                }
            }
        }
        foreach ($customFieldsAfterMessage as $customField)
        {
            if ($customField['type'] == 'select')
            {
                if ($customField['value']['is_searchable'] == 1) {
                    echo "$('#{$customField['name']}').addClass('read-write').attr('placeholder', '".$hesklang["search_by_pattern"]."').selectize({
                        delimiter: ',',
                        valueField: 'id',
                        labelField: 'displayName',
                        searchField: ['displayName'],
                        create: false,
                        copyClassesToDropdown: true,
                        plugins: ['no_results'],
                    });";
                } else {
                    echo "$('#{$customField['name']}').selectize();";
                }
            }
        }
        ?>
    });
</script>
<?php hesk3_output_drag_and_drop_script('c_attachments'); ?>
<?php if (has_public_kb() && $hesk_settings['kb_recommendanswers']): ?>
<script type="text/javascript">
    var noArticlesFoundText = <?php echo json_encode($hesklang['nsfo']); ?>;

    $(document).ready(function() {
        HESK_FUNCTIONS.getKbTicketSuggestions($('input[name="subject"]'),
            $('textarea[name="message"]'),
            function(data) {
                $('.kb-suggestions').show();
                var $suggestionList = $('#kb-suggestion-list');
                var $suggestedArticlesHiddenInputsList = $('#suggested-article-hidden-inputs');
                $suggestionList.html('');
                $suggestedArticlesHiddenInputsList.html('');
                var format = '<a href="knowledgebase.php?article={0}" class="suggest-preview" target="_blank">' +
                    '<span class="icon-in-circle" aria-hidden="true">' +
                    '<svg class="icon icon-knowledge">' +
                    '<use xlink:href="./theme/hesk3/customer/img/sprite.svg#icon-knowledge"></use>' +
                    '</svg>' +
                    '</span>' +
                    '<div class="suggest-preview__text">' +
                    '<p class="suggest-preview__title">{1}</p>' +
                    '<p>{2}</p>' +
                    '</div>' +
                    '</a>';
                var hiddenInputFormat = '<input type="hidden" name="suggested[]" value="{0}">';
                var results = false;
                $.each(data, function() {
                    results = true;
                    $suggestionList.append(format.replace('{0}', this.id).replace('{1}', this.subject).replace('{2}', this.contentPreview));
                    $suggestedArticlesHiddenInputsList.append(hiddenInputFormat.replace('{0}', this.hiddenInputValue));
                });

                if (!results) {
                    $suggestionList.append('<li class="no-articles-found">' + noArticlesFoundText + '</li>');
                }
            }
        );
    });
</script>
<?php endif; ?>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<?php
// Any adjustments to datepicker?
if (isset($hesk_settings['datepicker'])):
    ?>
    <script>
    $(document).ready(function () {
        const myDP = {};
        <?php
        foreach ($hesk_settings['datepicker'] as $selector => $data) {
            echo "
                myDP['{$selector}'] = $('{$selector}').datepicker(".((isset($data['position']) && is_string($data['position'])) ? "{position: '{$data['position']}'}" : "").");
            ";
            if (isset($data['timestamp']) && ($ts = intval($data['timestamp']))) {
                echo "
                    myDP['{$selector}'].data('datepicker').selectDate(new Date({$ts} * 1000));
                ";
            }
        }
        ?>
    });
    </script>
    <?php
endif;
?>
</body>
</html>
