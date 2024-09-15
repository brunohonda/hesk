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

// Auto-focus first empty or error field
define('AUTOFOCUS', true);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

// Load calendar JS and CSS
define('CALENDAR',1);
define('ATTACHMENTS',1);

if ($hesk_settings['staff_ticket_formatting'] == 2) {
    define('WYSIWYG',1);
}

$hesk_settings['datepicker'] = array();

// Pre-populate fields

// First, reset data if any query string value is present
if (isset($_REQUEST['name']) ||
    isset($_REQUEST['email']) ||
    isset($_REQUEST['priority']) ||
    isset($_REQUEST['status']) ||
    isset($_REQUEST['subject']) ||
    isset($_REQUEST['message']) ||
    isset($_REQUEST['due_date']) ||
    isset($_REQUEST['ticket_language'])
    ) {
    hesk_new_ticket_reset_data();
}

foreach ($hesk_settings['custom_fields'] as $k=>$v) {
    if ($v['use'] && isset($_REQUEST[$k])) {
        hesk_new_ticket_reset_data();
    }
}

// Customer name
if (isset($_REQUEST['name'])) {
	$_SESSION['as_name'] = $_REQUEST['name'];
}

// Customer email address
if (isset($_REQUEST['email'])) {
	$_SESSION['as_email']  = $_REQUEST['email'];
	$_SESSION['as_email2'] = $_REQUEST['email'];
}

// Category ID
if (isset($_REQUEST['catid'])) {
	$_SESSION['as_category'] = intval($_REQUEST['catid']);
}
if (isset($_REQUEST['category'])) {
	$_SESSION['as_category'] = intval($_REQUEST['category']);
}

// Priority
if (isset($_REQUEST['priority'])) {
	$_SESSION['as_priority'] = intval($_REQUEST['priority']);
}

// Status
if (isset($_REQUEST['status'])) {
    $_SESSION['as_status'] = intval($_REQUEST['status']);
}

// Subject
if (isset($_REQUEST['subject'])) {
	$_SESSION['as_subject'] = $_REQUEST['subject'];
}

// Message
if (isset($_REQUEST['message'])) {
	$_SESSION['as_message'] = $_REQUEST['message'];
}

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v) {
	if ($v['use'] && isset($_REQUEST[$k]) ) {
		$_SESSION['as_'.$k] = $_REQUEST[$k];
	}
}

// Due date
$can_due_date = hesk_checkPermission('can_due_date',0);
if ($can_due_date && isset($_REQUEST['due_date'])) {
    // Should be in one of valid formats
    // - in the datepicker format
    if (($dd = hesk_datepicker_get_date($_REQUEST['due_date']))) {
        $_SESSION['as_due_date'] = $_REQUEST['due_date'];
        $hesk_settings['datepicker']['#due_date']['timestamp'] = $dd->getTimestamp();
    }
    // - in a valid datetime format: https://www.php.net/manual/en/datetime.formats.date.php
    else {
        try {
            $current_date = new DateTime($_REQUEST['due_date']);
            $hesk_settings['datepicker']['#due_date']['timestamp'] = $current_date->getTimestamp();
            $_REQUEST['due_date'] = hesk_datepicker_format_date($current_date->getTimestamp());
            $_SESSION['as_due_date'] = $_REQUEST['due_date'];
        } catch(Exception $e) {
            $_SESSION['HESK_2ND_NOTICE']  = true;
            $_SESSION['HESK_2ND_MESSAGE'] = $hesklang['epdd'] . ' ' . $e->getMessage();
        }
    }
}

// Ticket language
if (isset($_REQUEST['ticket_language'])) {
    $_SESSION['as_language'] = $_REQUEST['ticket_language'];
}

/* Varibles for coloring the fields in case of errors */
if (!isset($_SESSION['iserror'])) {
	$_SESSION['iserror'] = array();
}

if (!isset($_SESSION['isnotice'])) {
	$_SESSION['isnotice'] = array();
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

// Get categories
$hesk_settings['categories'] = array();

if (hesk_checkPermission('can_submit_any_cat', 0))
{
    $res = hesk_dbQuery("SELECT `id`, `name`, `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
}
else
{
    $res = hesk_dbQuery("SELECT `id`, `name`, `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".hesk_myCategories('id')." ORDER BY `cat_order` ASC");
}

while ($row=hesk_dbFetchAssoc($res))
{
	$hesk_settings['categories'][$row['id']] = array('name' => $row['name'], 'priority' => $row['priority']);
}

$number_of_categories = count($hesk_settings['categories']);

if ($number_of_categories == 0)
{
	$category = 1;
}
elseif ($number_of_categories == 1)
{
	$category = current(array_keys($hesk_settings['categories']));
}
else
{
	$category = isset($_GET['catid']) ? hesk_REQUEST('catid'): hesk_REQUEST('category');

	// Force the customer to select a category?
	if (! isset($hesk_settings['categories'][$category]) )
	{
		return print_select_category($number_of_categories);
	}
}

// List of users whom this ticket can be assigned to
$admins = array();
$res = hesk_dbQuery("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row = hesk_dbFetchAssoc($res))
{
    // Is this an administrator?
    if ($row['isadmin'])
    {
        $admins[$row['id']]=$row['name'];
        continue;
    }

    // Not admin, is user allowed to view tickets?
    if (strpos($row['heskprivileges'], 'can_view_tickets') !== false)
    {
        // Is user allowed to access this category?
        $cat = substr($row['categories'], 0);
        $row['categories'] = explode(',', $cat);
        if (in_array($category, $row['categories']))
        {
            $admins[$row['id']] = $row['name'];
            continue;
        }
    }
}

// Set the default category priority
if ( ! isset($_SESSION['as_priority']))
{
    $_SESSION['as_priority'] = intval($hesk_settings['categories'][$category]['priority']);
}

// Set the default ticket status
if ( ! isset($_SESSION['as_status']))
{
    $_SESSION['as_status'] = 0;
}
?>
<div class="main__content categories ticket-create">
    <div class="table-wrap">

        <?php
        if ( ! isset($_SESSION['HESK_ERROR']))
        {
            hesk_show_info($hesklang['nti3'], ' ', false);
        }

        /* This will handle error, success and notice messages */
        hesk_handle_messages();
        ?>

        <h3 style="font-size: 1.3rem; margin-top: 10px"><?php echo $hesklang['nti2']; ?></h3>
        <h4><?php echo $hesklang['req_marked_with']; ?> <span class="important">*</span></h4>

        <form method="post" class="form <?php echo isset($_SESSION['iserror']) && count($_SESSION['iserror']) ? 'invalid' : ''; ?>" action="admin_submit_ticket.php" name="form1" id="submit-ticket" enctype="multipart/form-data">

            <?php if ($number_of_categories > 1): ?>
            <div class="form-group" style="margin-bottom: 0px;">
                <label for="create_name" style="display: inline;">
                    <?php echo $hesklang['category']; ?>:
                </label>
                &nbsp;
                <button type="button" class="btn btn--blue-border change_category" name="cc-btn" id="cc-btn" title="<?php echo $hesklang['chg_cat']; ?>"><?php echo hesk_getCategoryName($category); ?>
                    &nbsp;
                    <svg class="icon icon-edit">
                        <use xlink:href="../img/sprite.svg#icon-edit"></use>
                    </svg>
                </button>
                <input type="hidden" name="change_category" id="change_category" value="0">
                <script>
                $("#cc-btn").click(function() {
                    $("#change_category").val(1);
                    $("#submit-ticket").submit();
                });
                </script>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="create_name">
                    <?php echo $hesklang['name']; ?>: <span class="important">*</span>
                </label>
                <input type="text" id="create_name" name="name" class="form-control <?php if (in_array('name',$_SESSION['iserror'])) {echo 'isError';} ?>" maxlength="50" value="<?php if (isset($_SESSION['as_name'])) {echo stripslashes(hesk_input($_SESSION['as_name']));} ?>">
            </div>
            <div class="form-group">
                <label for="email">
                    <?php echo $hesklang['email'] . ':' . ($hesk_settings['require_email'] ? ' <span class="important">*</span>' : '') ; ?>
                </label>
                <input type="<?php echo ($hesk_settings['multi_eml'] ? 'text' : 'email'); ?>"
                       class="form-control <?php if (in_array('email',$_SESSION['iserror'])) {echo 'isError';} elseif (in_array('email',$_SESSION['isnotice'])) {echo 'isNotice';} ?>"
                       name="email" id="email" maxlength="1000"
                       value="<?php if (isset($_SESSION['as_email'])) {echo stripslashes(hesk_input($_SESSION['as_email']));} ?>"
                    <?php if($hesk_settings['detect_typos']) { echo ' onblur="Javascript:hesk_suggestEmail(\'email\', \'email_suggestions\', 1, 1)"'; } ?>>
            </div>
            <div id="email_suggestions"></div>
            <div class="form-group">
                <label class="priority <?php if (in_array('priority',$_SESSION['iserror'])) {echo 'isErrorStr';} ?>"><?php echo $hesklang['priority']; ?>: <?php if ($hesk_settings['select_pri']) {echo '<span class="important">*</span>';} ?></label>
                <div class="dropdown-select center out-close priority">
                    <select name="priority">
                        <?php
                        // Show the "Click to select"?
                        if ($hesk_settings['select_pri'])
                        {
                            echo '<option value="">'.$hesklang['select'].'</option>';
                        }
                        ?>
                        <option value="3" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==3) {echo 'selected';} ?>><?php echo $hesklang['low']; ?></option>
                        <option value="2" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==2) {echo 'selected';} ?>><?php echo $hesklang['medium']; ?></option>
                        <option value="1" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==1) {echo 'selected';} ?>><?php echo $hesklang['high']; ?></option>
                        <option value="0" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==0) {echo 'selected';} ?>><?php echo $hesklang['critical']; ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group ts" id="ticket-status-div">
                <label><?php echo $hesklang['status']; ?>:</label>
                <div class="dropdown-select center out-close">
                    <select id="status-select" name="status" onchange="hesk_update_status_color(this.value)">
                        <?php echo hesk_get_status_select('', hesk_checkPermission('can_resolve', 0), $_SESSION['as_status']); ?>
                    </select>
                </div>
            </div>

            <!-- START CUSTOM BEFORE -->
            <?php

            foreach ($hesk_settings['custom_fields'] as $k=>$v)
            {
                if ($v['use'] && $v['place']==0 && hesk_is_custom_field_in_category($k, $category) )
                {
                    $v['req'] = $v['req']==2 ? '<span class="important">*</span>' : '';

                    if ($v['type'] == 'checkbox')
                    {
                        $k_value = array();
                        if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"]))
                        {
                            foreach ($_SESSION["as_$k"] as $myCB)
                            {
                                $k_value[] = stripslashes(hesk_input($myCB));
                            }
                        }
                    }
                    elseif (isset($_SESSION["as_$k"]))
                    {
                        $k_value  = stripslashes(hesk_input($_SESSION["as_$k"]));
                    }
                    else
                    {
                        $k_value  = '';
                    }

                    switch ($v['type'])
                    {
                        /* Radio box */
                        case 'radio':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            echo '
                                <div class="form-group '.$cls.'">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="radio-list">';

                            $index = 0;
                            foreach ($v['value']['radio_options'] as $option)
                            {
                                if (strlen($k_value) == 0)
                                {
                                    $k_value = $option;
                                    $checked = empty($v['value']['no_default']) ? 'checked' : '';
                                }
                                elseif ($k_value == $option)
                                {
                                    $k_value = $option;
                                    $checked = 'checked';
                                }
                                else
                                {
                                    $checked = '';
                                }

                                echo '
                                            <div class="radio-custom" style="margin-bottom: 5px">
                                                <input type="radio" id="edit_'.$k.$index.'" name="'.$k.'" value="'.$option.'" '.$checked.'>
                                                <label for="edit_'.$k.$index.'">'.$option.'</label>
                                            </div>';
                                $index++;
                            }
                            echo '</div>
                                </div>';
                            break;

                        /* Select drop-down box */
                        case 'select':

                            $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                            echo '
                                <div class="form-group">
                                    <label for="edit_">'.$v['name:'].' '.$v['req'].'</label>
                                        <select name="'.$k.'" id="'.$k.'" '.$cls.'>';
                            // Show "Click to select"?
                            if ( ! empty($v['value']['show_select']))
                            {
                                echo '<option value="">'.$hesklang['select'].'</option>';
                            }

                            foreach ($v['value']['select_options'] as $option)
                            {
                                if ($k_value == $option)
                                {
                                    $k_value = $option;
                                    $selected = 'selected';
                                }
                                else
                                {
                                    $selected = '';
                                }

                                echo '<option '.$selected.'>'.$option.'</option>';
                            }
                            echo '</select>
                                </div>
                                <script>
                                    $(\'#'.$k.'\').selectize();
                                </script>
                                ';
                            break;

                        /* Checkbox */
                        case 'checkbox':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            echo '
                                <div class="form-group '.$cls.'">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>';

                            $index = 0;
                            foreach ($v['value']['checkbox_options'] as $option)
                            {
                                if (in_array($option,$k_value))
                                {
                                    $checked = 'checked';
                                }
                                else
                                {
                                    $checked = '';
                                }

                                echo '
                                    <div class="checkbox-custom">
                                        <input type="checkbox" id="edit_'.$k.$index.'" name="'.$k.'[]" value="'.$option.'" '.$checked.'>
                                        <label for="edit_'.$k.$index.'"> '.$option.'</label>
                                    </div>';
                                $index++;
                            }

                            echo '</div>';
                            break;

                        /* Large text box */
                        case 'textarea':
                            $cls = in_array($k,$_SESSION['iserror']) ? ' isError" ' : '';
                            $k_value = hesk_msgToPlain($k_value,0,0);

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <textarea name="'.$k.'" class="form-control'.$cls.'" style="height: inherit" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" >'.$k_value.'</textarea>
                                </div>';
                            break;

                        // Date
                        case 'date':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isErrorStr' : '';
                            if (is_string($k_value) && ($dd = hesk_datepicker_get_date($k_value))) {
                                $hesk_settings['datepicker']['#'.$k]['timestamp'] = $dd->getTimestamp();
                            }
                            echo '
                                <section class="param calendar">
                                    <label class="'.$cls.'">'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="calendar--button">
                                        <button type="button">
                                            <svg class="icon icon-calendar">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-calendar"></use>
                                            </svg>
                                        </button>
                                        <input name="'. $k .'" id="'. $k .'"
                                               value="'. $k_value .'"
                                               type="text" class="datepicker">
                                    </div>
                                    <div class="calendar--value" '. ($k_value ? 'style="display: block"' : '') . '>
                                        <span class="'. ($cls && ! empty($k_value) ? $cls : '') .'"><i>'. $k_value .'</i></span>
                                        <i class="close">
                                            <svg class="icon icon-close">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-close"></use>
                                            </svg>
                                        </i>
                                    </div>
                                </section>';
                            break;

                        // Email
                        case 'email':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="'.($v['value']['multiple'] ? 'text' : 'email').'" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$suggest.'>
                                </div>
                                <div id="'.$k.'_suggestions"></div>';
                            break;

                        // Hidden
                        // Handle as text fields for staff

                        /* Default text input */
                        default:
                            if (strlen($k_value) != 0 || isset($_SESSION["as_$k"]))
                            {
                                $v['value']['default_value'] = $k_value;
                            }

                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'">
                                </div>';
                    }
                }
            }
            ?>
            <!-- END CUSTOM BEFORE -->
            <?php
            // Lets handle ticket templates
            $can_options = '';

            // Get ticket templates from the database
            $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` ORDER BY `tpl_order` ASC");

            // If we have any templates print them out
            if ( hesk_dbNumRows($res) )
            {
                ?>
                <script language="javascript" type="text/javascript"><!--
                    // -->
                    var myMsgTxt = new Array();
                    var mySubjectTxt = new Array();
                    myMsgTxt[0]='';
                    mySubjectTxt[0]='';

                    <?php
                    while ($mysaved = hesk_dbFetchAssoc($res))
                    {
                        $can_options .= '<option value="' . $mysaved['id'] . '">' . $mysaved['title']. "</option>\n";
                        $message_text = $hesk_settings['staff_ticket_formatting'] == 2 ? $mysaved['message_html'] : $mysaved['message'];
                        echo 'myMsgTxt['.$mysaved['id'].']=\''.preg_replace("/\r?\n|\r/","\\r\\n' + \r\n'", addslashes($message_text))."';\n";
                        echo 'mySubjectTxt['.$mysaved['id'].']=\''.preg_replace("/\r?\n|\r/","\\r\\n' + \r\n'", addslashes($mysaved['title']))."';\n";
                    }

                    ?>

                    function setMessage(msgid)
                    {
                        var myMsg=myMsgTxt[msgid];
                        var mySubject=mySubjectTxt[msgid];

                        if (myMsg == '')
                        {
                            if (document.form1.mode[1].checked)
                            {
                            <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                                tinymce.get("message").setContent('');
                            <?php else: ?>
                                document.getElementById('message').value = '';
                            <?php endif; ?>
                                document.getElementById('subject').value = '';
                            }
                            return true;
                        }
                        if (document.getElementById)
                        {
                            if (document.getElementById('moderep').checked)
                            {
                                <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                                tinymce.get("message").setContent('');
                                tinymce.get("message").execCommand('mceInsertRawHTML', false, myMsg);
                                <?php else: ?>
                                document.getElementById('HeskMsg').innerHTML='<textarea style="height: inherit" class="form-control" name="message" id="message" rows="12" cols="60">'+myMsg+'</textarea>';
                                <?php endif; ?>
                                document.getElementById('HeskSub').innerHTML='<input class="form-control" type="text" name="subject" id="subject" maxlength="70" value="'+mySubject+'">';
                            }
                            else
                            {
                                <?php if ($hesk_settings['staff_ticket_formatting'] == 2): ?>
                                var oldMsg = tinymce.get("message").getContent();
                                tinymce.get("message").setContent('');
                                tinymce.get("message").execCommand('mceInsertRawHTML', false, oldMsg + myMsg);
                                <?php else: ?>
                                var oldMsg = escapeHtml(document.getElementById('message').value);
                                document.getElementById('HeskMsg').innerHTML='<textarea style="height: inherit" class="form-control" name="message" id="message" rows="12" cols="60">'+oldMsg+myMsg+'</textarea>';
                                <?php endif; ?>
                                if (document.getElementById('subject').value == '')
                                {
                                    document.getElementById('HeskSub').innerHTML='<input class="form-control" type="text" name="subject" id="subject" maxlength="70" value="'+mySubject+'">';
                                }
                            }
                        }
                        else
                        {
                            if (document.form1.mode[0].checked)
                            {
                                document.form1.message.value=myMsg;
                                document.form1.subject.value=mySubject;
                            }
                            else
                            {
                                var oldMsg = document.form1.message.value;
                                document.form1.message.value=oldMsg+myMsg;
                                if (document.form1.subject.value == '')
                                {
                                    document.form1.subject.value=mySubject;
                                }
                            }
                        }

                    }
                    //-->
                </script>
                <?php
            } // END fetchrows

            // Print templates
            if ( strlen($can_options) )
            {
                ?>
                <div class="form-group">
                    <label>
                        <?php echo $hesklang['ticket_tpl']; ?>
                        <?php echo hesk_checkPermission('can_man_ticket_tpl', 0) ? '(<a class="link" href="manage_ticket_templates.php">' . $hesklang['ticket_tpl_man'] . '</a>)' : ''; ?>
                    </label>
                    <div class="radio-list">
                        <div class="radio-custom" style="margin-bottom: 5px">
                            <input type="radio" name="mode" id="modeadd" value="1" checked="checked">
                            <label for="modeadd"><?php echo $hesklang['madd']; ?></label>
                        </div>
                        <div class="radio-custom" style="margin-bottom: 5px">
                            <input type="radio" name="mode" id="moderep" value="0">
                            <label for="moderep"><?php echo $hesklang['mrep']; ?></label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo $hesklang['select_ticket_tpl']; ?>:</label>
                    <div class="dropdown-select center out-close">
                        <select name="saved_replies" onchange="setMessage(this.value)">
                            <option value="0"> - <?php echo $hesklang['select_empty']; ?> - </option>
                            <?php echo $can_options; ?>
                        </select>
                    </div>
                </div>
                <?php
            } // END printing templates
            elseif ( hesk_checkPermission('can_man_ticket_tpl', 0) )
            {
                ?>
                <div class="form-group">
                    <label><a href="manage_ticket_templates.php" class="link"><?php echo $hesklang['ticket_tpl_man']; ?></a></label>
                </div>
                <?php
            }
            ?>
            <div class="form-group">
                <label><?php echo $hesklang['subject'] . ': ' . ($hesk_settings['require_subject']==1 ? '<span class="important">*</span>' : '') ; ?></label>
                <span id="HeskSub"><input class="form-control <?php if (in_array('subject',$_SESSION['iserror'])) {echo 'isError';} ?>" type="text" name="subject" id="subject" maxlength="70" value="<?php if (isset($_SESSION['as_subject'])) {echo stripslashes(hesk_input($_SESSION['as_subject']));} ?>"></span>
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['message'] . ': ' . ($hesk_settings['require_message']==1 ? '<span class="important">*</span>' : '') ; ?></label>
                <span id="HeskMsg">
                    <textarea style="height: inherit" class="form-control <?php if (in_array('message',$_SESSION['iserror'])) {echo 'isError';} ?>"
                              name="message" id="message" rows="12" cols="60"><?php if (isset($_SESSION['as_message'])) {echo stripslashes(hesk_input($_SESSION['as_message']));} ?></textarea>
                </span>
            </div>
            <?php
            if ($hesk_settings['staff_ticket_formatting'] == 2) {
                hesk_tinymce_init('#message');
            }

            /* custom fields AFTER comments */
            foreach ($hesk_settings['custom_fields'] as $k=>$v)
            {
                if ($v['use'] && $v['place']==1 && hesk_is_custom_field_in_category($k, $category) )
                {
                    $v['req'] = $v['req']==2 ? '<span class="important">*</span>' : '';

                    if ($v['type'] == 'checkbox')
                    {
                        $k_value = array();
                        if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"]))
                        {
                            foreach ($_SESSION["as_$k"] as $myCB)
                            {
                                $k_value[] = stripslashes(hesk_input($myCB));
                            }
                        }
                    }
                    elseif (isset($_SESSION["as_$k"]))
                    {
                        $k_value  = stripslashes(hesk_input($_SESSION["as_$k"]));
                    }
                    else
                    {
                        $k_value  = '';
                    }

                    switch ($v['type'])
                    {
                        /* Radio box */
                        case 'radio':
                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="radio-list">';

                            $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                            $index = 0;
                            foreach ($v['value']['radio_options'] as $option)
                            {
                                if (strlen($k_value) == 0)
                                {
                                    $k_value = $option;
                                    $checked = empty($v['value']['no_default']) ? 'checked' : '';
                                }
                                elseif ($k_value == $option)
                                {
                                    $k_value = $option;
                                    $checked = 'checked';
                                }
                                else
                                {
                                    $checked = '';
                                }

                                echo '
                                            <div class="radio-custom" style="margin-bottom: 5px">
                                                <input type="radio" id="edit_'.$k.$index.'" name="'.$k.'" value="'.$option.'" '.$checked.' '.$cls.'>
                                                <label for="edit_'.$k.$index.'">'.$option.'</label>
                                            </div>';
                                $index++;
                            }
                            echo '</div>
                                </div>';
                            break;

                        /* Select drop-down box */
                        case 'select':

                            $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                            echo '
                                <div class="form-group">
                                    <label for="edit_">'.$v['name:'].' '.$v['req'].'</label>
                                        <select name="'.$k.'" id="'.$k.'" '.$cls.'>';
                            // Show "Click to select"?
                            if ( ! empty($v['value']['show_select']))
                            {
                                echo '<option value="">'.$hesklang['select'].'</option>';
                            }

                            foreach ($v['value']['select_options'] as $option)
                            {
                                if ($k_value == $option)
                                {
                                    $k_value = $option;
                                    $selected = 'selected';
                                }
                                else
                                {
                                    $selected = '';
                                }

                                echo '<option '.$selected.'>'.$option.'</option>';
                            }
                            echo '</select>
                                </div>
                                <script>
                                    $(\'#'.$k.'\').selectize();
                                </script>
                                ';
                            break;

                        /* Checkbox */
                        case 'checkbox':
                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>';

                            $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                            $index = 0;
                            foreach ($v['value']['checkbox_options'] as $option)
                            {
                                if (in_array($option,$k_value))
                                {
                                    $checked = 'checked';
                                }
                                else
                                {
                                    $checked = '';
                                }

                                echo '
                                    <div class="checkbox-custom">
                                        <input type="checkbox" id="edit_'.$k.$index.'" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$cls.'>
                                        <label for="edit_'.$k.$index.'"> '.$option.'</label>
                                    </div>';
                                $index++;
                            }

                            echo '</div>';
                            break;

                        /* Large text box */
                        case 'textarea':
                            $cls = in_array($k,$_SESSION['iserror']) ? ' isError" ' : '';
                            $k_value = hesk_msgToPlain($k_value,0,0);

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <textarea name="'.$k.'" class="form-control'.$cls.'" style="height: inherit" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" >'.$k_value.'</textarea>
                                </div>';
                            break;

                        // Date
                        case 'date':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isErrorStr' : '';
                            if (is_string($k_value) && ($dd = hesk_datepicker_get_date($k_value))) {
                                $hesk_settings['datepicker']['#'.$k]['timestamp'] = $dd->getTimestamp();
                            }
                            echo '
                                <section class="param calendar">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <div class="calendar--button">
                                        <button type="button">
                                            <svg class="icon icon-calendar">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-calendar"></use>
                                            </svg>
                                        </button>
                                        <input name="'. $k .'" id="'. $k .'"
                                               value="'. $k_value .'"
                                               type="text" class="datepicker">
                                    </div>
                                    <div class="calendar--value" '. ($k_value ? 'style="display: block"' : '') . '>
                                        <span class="'. $cls .'"><i>'. $k_value .'</i></span>
                                        <i class="close">
                                            <svg class="icon icon-close">
                                                <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-close"></use>
                                            </svg>
                                        </i>
                                    </div>
                                </section>';
                            break;

                        // Email
                        case 'email':
                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="'.($v['value']['multiple'] ? 'text' : 'email').'" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$suggest.'>
                                </div>
                                <div id="'.$k.'_suggestions"></div>';
                            break;

                        // Hidden
                        // Handle as text fields for staff

                        /* Default text input */
                        default:
                            if (strlen($k_value) != 0 || isset($_SESSION["as_$k"]))
                            {
                                $v['value']['default_value'] = $k_value;
                            }

                            $cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

                            echo '
                                <div class="form-group">
                                    <label>'.$v['name:'].' '.$v['req'].'</label>
                                    <input class="form-control '.$cls.'" type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'">
                                </div>';
                    }
                }
            }
            ?>
            <!-- END CUSTOM AFTER -->

            <?php
            /* attachments */
            if ($hesk_settings['attachments']['use']) {
                require(HESK_PATH . 'inc/attachments.inc.php');
                ?>
                <div class="attachments">
                    <div class="block--attach">
                        <svg class="icon icon-attach">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-attach"></use>
                        </svg>
                        <div>
                            <?php echo $hesklang['attachments']; ?>:
                        </div>
                    </div>
                    <?php
                    build_dropzone_markup(true);
                    display_dropzone_field(HESK_PATH . 'upload_attachment.php', true);
                    dropzone_display_existing_files(hesk_SESSION_array('as_attachments'));
                    ?>
                </div>
                <?php
            }

            // Admin options
            if ( ! isset($_SESSION['as_notify']) )
            {
                $_SESSION['as_notify'] = $_SESSION['notify_customer_new'] ? 1 : 0;
            }
            ?>
            <div class="form-group" style="margin-top: 20px">
                <label><?php echo $hesklang['addop']; ?>:</label>
                <div class="checkbox-list">
                    <div class="checkbox-custom">
                        <input type="checkbox" id="create_notify1" name="notify" value="1" <?php echo empty($_SESSION['as_notify']) ? '' : 'checked'; ?>>
                        <label for="create_notify1"><?php echo $hesklang['seno']; ?></label>
                    </div>
                    <?php if (hesk_checkPermission('can_view_tickets',0)): ?>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="create_show1" name="show" value="1" <?php echo (!isset($_SESSION['as_show']) || !empty($_SESSION['as_show'])) ? 'checked' : ''; ?>>
                        <label for="create_show1"><?php echo $hesklang['otas']; ?></label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($can_due_date): ?>
            <section class="param calendar">
                <?php
                // Default due date
                $default_due_date_info = hesk_getCategoryDueDateInfo($category);

                $due_date = isset($_SESSION['as_due_date']) ? $_SESSION['as_due_date'] : null;
                if ($due_date && ($dd = hesk_datepicker_get_date($due_date))) {
                    $hesk_settings['datepicker']['#due_date']['timestamp'] = $dd->getTimestamp();
                } elseif ($default_due_date_info !== null && $due_date === null) {
                    $current_date = new DateTime('today midnight');
                    $current_date->add(DateInterval::createFromDateString("+{$default_due_date_info['amount']} {$default_due_date_info['unit']}s"));
                    $hesk_settings['datepicker']['#due_date']['timestamp'] = $current_date->getTimestamp();
                    $due_date = hesk_datepicker_format_date($current_date->getTimestamp());
                }
                ?>
                <label><?php echo $hesklang['due_date']; ?>:</label>
                <div class="calendar--button">
                    <button type="button">
                        <svg class="icon icon-calendar">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-calendar"></use>
                        </svg>
                    </button>
                    <input name="due_date" id="due_date"
                           value="<?php if (isset($due_date)) {echo stripslashes(hesk_input($due_date));} ?>"
                           type="text" class="datepicker">
                </div>
                <div class="calendar--value" style="<?php echo empty($due_date) ? '' : 'display: block'; ?>">
                <span><?php echo isset($due_date) ? stripslashes($due_date) : ''; ?></span>
                <i class="close">
                    <svg class="icon icon-close">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                    </svg>
                </i>
                </div>
            </section>
            <br>
            <?php endif; ?>
            <?php if ($hesk_settings['can_sel_lang']): ?>
            <div class="form-group">
                <label for="as_language"><?php echo $hesklang['tlan']; ?>:</label>
                <select name="as_language" id="as_language">
                    <?php
                        if (isset($_SESSION['as_language']) && isset($hesk_settings['languages'][$_SESSION['as_language']]))
                        {
                            $hesk_settings['language_copy'] = $hesk_settings['language'];
                            $hesk_settings['language'] = $_SESSION['as_language'];
                            hesk_listLanguages();
                            $hesk_settings['language'] = $hesk_settings['language_copy'];
                        }
                        else
                        {
                            hesk_listLanguages();
                        }
                    ?>
                </select>
            </div>
            <script>
                $('#as_language').selectize();
            </script>
            <?php endif; ?>
            <?php if (hesk_checkPermission('can_assign_others',0)) { ?>
                <div class="form-group">
                    <label><?php echo $hesklang['asst2']; ?>:</label>
                        <select name="owner" id="owner-select" <?php if (in_array('owner',$_SESSION['iserror'])) {echo ' class="isError" ';} ?>>
                            <option value="-1"> &gt; <?php echo $hesklang['unas']; ?> &lt; </option>
                            <?php

                            if ($hesk_settings['autoassign'])
                            {
                                echo '<option value="-2"> &gt; ' . $hesklang['aass'] . ' &lt; </option>';
                            }

                            $owner = isset($_SESSION['as_owner']) ? intval($_SESSION['as_owner']) : 0;

                            foreach ($admins as $k=>$v)
                            {
                                if ($k == $owner)
                                {
                                    echo '<option value="'.$k.'" selected="selected">'.$v.'</option>';
                                }
                                else
                                {
                                    echo '<option value="'.$k.'">'.$v.'</option>';
                                }

                            }
                            ?>
                        </select>
                        <script>
                            $('#owner-select').selectize();
                        </script>
                </div>
                <?php
            }
            elseif (hesk_checkPermission('can_assign_self',0))
            {
                $checked = (!isset($_SESSION['as_owner']) || !empty($_SESSION['as_owner'])) ? 'checked' : '';
                ?>
                <div class="form-group">
                    <label><?php echo $hesklang['owner']; ?></label>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="create_assing_to_self1" name="assing_to_self" value="1" <?php echo $checked; ?>>
                        <label for="create_assing_to_self1"><?php echo $hesklang['asss2']; ?></label>
                    </div>
                </div>
                <?php
            }
            ?>

            <?php if ( defined('HESK_DEMO') ): ?>
                 <?php hesk_show_notice(sprintf($hesklang['antdemo'], 'https://www.hesk.com/demo/index.php?a=add')); ?>
                <button class="btn btn-full"><?php echo $hesklang['sub_ticket']; ?></button>
            <?php else: ?>
                <button type="submit" class="btn btn-full"><?php echo $hesklang['sub_ticket']; ?></button>
            <?php endif; ?>
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
            <input type="hidden" name="category" value="<?php echo $category; ?>">
        </form>
        <p>&nbsp;</p>
        <p>&nbsp;</p>
        <p>&nbsp;</p>
        <p>&nbsp;</p>
        <p>&nbsp;</p>
    </div>
</div>
<?php

hesk_cleanSessionVars('iserror');
hesk_cleanSessionVars('isnotice');

// Clearing it out, otherwise users could delete an attachment, refresh, and it'll "supposedly" be back
hesk_cleanSessionVars('as_attachments');

$hesk_settings['print_status_select_box_jquery'] = true;

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function print_select_category($number_of_categories)
{
	global $hesk_settings, $hesklang;

	// A categoy needs to be selected
	if (isset($_GET['category']) && empty($_GET['category']))
	{
		hesk_process_messages($hesklang['sel_app_cat'],'NOREDIRECT','NOTICE');
	}

    /* This will handle error, success and notice messages */
    hesk_handle_messages();
    ?>
    <div class="main__content categories">
        <?php
        // Print a select box if number of categories is large
        if ($number_of_categories > $hesk_settings['cat_show_select']) {
            ?>
            <div class="table-wrap">
                <h2 class="select__title-alt"><?php echo $hesklang['select_category_staff']; ?></h2>
                <form action="new_ticket.php" method="get" class="form">
                    <select class="form-control" name="category" id="select_category">
                        <?php
                        if ($hesk_settings['select_cat'])
                        {
                            echo '<option value="">'.$hesklang['select'].'</option>';
                        }
                        foreach ($hesk_settings['categories'] as $k=>$v)
                        {
                            echo '<option value="'.$k.'">'.$v['name'].'</option>';
                        }
                        ?>
                    </select>
                    <button style="margin-top: 10px" type="submit" class="btn btn-full"><?php echo $hesklang['c2c']; ?></button>
                </form>
                <script>
                    $(document).ready(function() {
                        $('#select_category').selectize();
                    });
                </script>
            </div>
            <?php
        }
        // Otherwise print quick links
        else
        {
            ?>
            <h2 class="select__title"><?php echo $hesklang['select_category_staff']; ?></h2>
            <div class="nav">
                <?php foreach ($hesk_settings['categories'] as $k => $v): ?>
                <a href="new_ticket.php?a=add&amp;category=<?php echo $k; ?>" class="navlink <?php if ($number_of_categories > 8) echo "navlink-condensed"; ?>">
                    <div class="icon-in-circle">
                        <svg class="icon icon-chevron-right">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-right"></use>
                        </svg>
                    </div>
                    <div>
                        <h5 class="navlink__title"><!--[if IE]> &raquo; <![endif]--><?php echo $v['name']; ?></h5>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php
        }
        ?>
    </div>
    <?php

	hesk_cleanSessionVars('iserror');
	hesk_cleanSessionVars('isnotice');
    hesk_cleanSessionVars('as_priority');

	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // END print_select_category()


function hesk_new_ticket_reset_data()
{
    global $hesk_settings;

    // Already reset
    if (isset($hesk_settings['POPULATE_DATA_RESET'])) {
        return true;
    }

    hesk_cleanSessionVars('as_name');
    hesk_cleanSessionVars('as_email');
    hesk_cleanSessionVars('as_category');
    hesk_cleanSessionVars('as_priority');
    hesk_cleanSessionVars('as_status');
    hesk_cleanSessionVars('as_subject');
    hesk_cleanSessionVars('as_message');
    hesk_cleanSessionVars('as_owner');
    hesk_cleanSessionVars('as_notify');
    hesk_cleanSessionVars('as_show');
    hesk_cleanSessionVars('as_due_date');
    hesk_cleanSessionVars('as_language');
    foreach ($hesk_settings['custom_fields'] as $k=>$v) {
        hesk_cleanSessionVars("as_$k");
    }

    $hesk_settings['POPULATE_DATA_RESET'] = true;

    return true;

} // END hesk_new_ticket_reset_data()
