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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_email_tpl');

// Define required constants
define('LOAD_TABS',1);

// Get valid email templates
require(HESK_PATH . 'inc/email_functions.inc.php');
$emails = array_keys(hesk_validEmails());

// Which language are we editing?
if ($hesk_settings['can_sel_lang'])
{
	$hesk_settings['edit_language'] = hesk_REQUEST('edit_language');
	if ( ! isset($hesk_settings['languages'][$hesk_settings['edit_language']]) )
	{
		$hesk_settings['edit_language'] = $hesk_settings['language'];
	}
}
else
{
	$hesk_settings['edit_language'] = $hesk_settings['language'];
}

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
    if ($action == 'edit') {
        if (hesk_GET('t') == 'html') {
            define('WYSIWYG',1);
            define('HTML_EMAIL_TEMPLATE',1);
            define('STYLE_CODE',1);
        }
    }
	elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'email_templates.php', 'NOTICE');}
	elseif ($action == 'save') {save_et();}
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if ($action != 'edit') {
    hesk_handle_messages();
}

// Let's tell users that plain text email templates will be ignored if we auto-generate them from HTML templates
if ($hesk_settings['email_formatting'] == 0) {
    hesk_show_info(sprintf($hesklang['email_formatting_note'], $hesklang['settings'], $hesklang['tab_6'], $hesklang['email_formatting'], $hesklang['email_formatting_plaintext']) . '<br><br>' . $hesklang['email_formatting_note0'], ' ', false);
} elseif ($hesk_settings['email_formatting'] == 1) {
    hesk_show_info(sprintf($hesklang['email_formatting_note'], $hesklang['settings'], $hesklang['tab_6'], $hesklang['email_formatting'], $hesklang['email_formatting_html']) . '<br><br>' . $hesklang['email_formatting_note1'], ' ', false);
} elseif ($hesk_settings['email_formatting'] == 2) {
    hesk_show_info(sprintf($hesklang['email_formatting_note'], $hesklang['settings'], $hesklang['tab_6'], $hesklang['email_formatting'], $hesklang['email_formatting_html_and_plaintext_auto']) . '<br><br>' . $hesklang['email_formatting_note2'], ' ', false);
}
?>
<div class="main__content tools">
    <section class="tools__between-head fw">
        <h2>
            <?php echo $hesklang['et_title']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['et_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <?php if ($hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1): ?>
            <form method="get" action="email_templates.php">
            <div class="dropdown-select center out-close">
                <select name="edit_language" onchange="this.form.submit()">
                <?php foreach ($hesk_settings['languages'] as $lang => $info): ?>
                    <option value="<?php echo $lang; ?>" <?php if ($lang === $hesk_settings['edit_language']): ?>selected<?php endif; ?>>
                        <?php echo $lang; ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php endif; ?>
    </section>
    <div class="table-wrapper email-templates">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['email_tpl_title']; ?></th>
                    <th><?php echo $hesklang['rdesc']; ?></th>
                    <th><?php echo $hesklang['ticket_formatting_plaintext']; ?></th>
                    <th><?php echo $hesklang['ticket_formatting_rich_text']; ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $all_files = true;
                $all_writable = true;
                foreach ($emails as $email):
                    $plaintext_eml_file = et_file_path($email, 'plaintext');
                    $html_eml_file = et_file_path($email, 'html');
                ?>
                <tr <?php if (hesk_SESSION('et_id') == $email) {echo 'class="ticket-new"'; unset($_SESSION['et_id']);} ?>>
                    <td><?php echo $email; ?>.txt</td>
                    <td><?php echo $hesklang['desc_'.$email]; ?></td>
                    <td class="buttons">
                        <?php
                        if (!file_exists($plaintext_eml_file)) {
                            $all_files = false;
                            echo '<span style="color:red">'.$hesklang['no_exists'].'</span>';
                        } elseif (!is_writable($plaintext_eml_file)) {
                            $all_writable = false;
                            echo '<span style="color:red">'.$hesklang['not_writable'].'</span>';
                        } else {
                            ?>
                            <a title="<?php echo $hesklang['edit']; ?>" href="email_templates.php?a=edit&amp;t=plaintext&amp;id=<?php echo $email; ?>&amp;edit_language=<?php echo urlencode($hesk_settings['edit_language']); ?>" class="edit tooltip">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>
                            <?php
                        }
                        ?>
                    </td>
                    <td class="buttons">
                        <?php
                        if (!file_exists($html_eml_file)) {
                            $all_files = false;
                            echo '<span style="color:red">'.$hesklang['no_exists'].'</span>';
                        } elseif (!is_writable($html_eml_file)) {
                            $all_writable = false;
                            echo '<span style="color:red">'.$hesklang['not_writable'].'</span>';
                        } else {
                            ?>
                            <a title="<?php echo $hesklang['edit']; ?>" href="email_templates.php?a=edit&amp;t=html&amp;id=<?php echo $email; ?>&amp;edit_language=<?php echo urlencode($hesk_settings['edit_language']); ?>" class="edit tooltip">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            // Any template missing?
            if (!$all_files)
            {
                hesk_show_error(sprintf($hesklang['etfm'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder']));
            }

            // Any template not writable?
            if (!$all_writable)
            {
                hesk_show_error(sprintf($hesklang['etfw'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder'], $hesk_settings['languages'][$hesk_settings['edit_language']]['folder']));
            }
            ?>
        </div>
    </div>
</div>
<?php
// EDIT
if ($action == 'edit')
{
    // Get email ID
    $email = hesk_GET('id');

    // Get email type
    if (($type = hesk_GET('t')) != 'html') {
        $type = 'plaintext';
    }

    // Get file path
    $eml_file = et_file_path($email, $type);

    // Make sure the file exists and is writable
    if ( ! file_exists($eml_file))
    {
        hesk_error($hesklang['et_fm']);
    }
    elseif ( ! is_writable($eml_file))
    {
        hesk_error($hesklang['et_fw']);
    }

    // Start the edit form
    ?>
    <script language="javascript" type="text/javascript"><!--

    function hesk_insertRichTag(tag) {
        var text_to_insert = '%%'+tag+'%%';
        <?php if ($type === 'html'): ?>
            tinymce.get("msg").execCommand('mceInsertContent', false, text_to_insert);
        <?php else: ?>
            hesk_insertAtCursor(document.getElementById('msg'), text_to_insert);
            document.getElementById('msg').focus();
        <?php endif; ?>
    }

    //-->
    </script>

    <div class="right-bar tools-email-template-edit" style="display: block">
        <div class="right-bar__body form">
            <h3>
                <a href="email_templates.php">
                    <svg class="icon icon-back">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                    </svg>
                    <span><?php echo $hesklang['edit_email_template']; ?></span>
                </a>
            </h3>
            <?php
            /* This will handle error, success and notice messages */
            echo '<div style="margin: -24px -24px 10px -16px;">';

            if (
                    ($type === 'html' && $hesk_settings['email_formatting'] === 0) ||
                    ($type === 'plaintext' && $hesk_settings['email_formatting'] === 1) ||
                    ($type === 'plaintext' && $hesk_settings['email_formatting'] === 2)
                ) {
                    hesk_show_notice(sprintf($hesklang['etnu'], $hesklang['settings'], $hesklang['tab_6'], $hesklang['email_formatting']), ' ', false);
            }

            hesk_handle_messages();
            echo '</div>';
            ?>
            <section class="param">
                <span><?php echo $hesklang['efile']; ?></span>
                <form method="get" action="email_templates.php">
                    <div class="dropdown-select center out-close">
                        <select name="id" onchange="this.form.submit()">
                            <?php
                            foreach ($emails as $email_tmp) {
                                $eml_file_tmp = et_file_path($email_tmp, $type);

                                if (!file_exists($eml_file_tmp) || !is_writable($eml_file_tmp)) {
                                    continue;
                                }

                                if ($email_tmp === $email) {
                                    echo '<option value="'.$email_tmp.'" selected>' . $hesklang['desc_'.$email_tmp].'</option>';
                                } else {
                                    echo '<option value="'.$email_tmp.'">' . $hesklang['desc_'.$email_tmp].'</option>';
                                }
                            }
                            ?>
                        </select>
                        <input type="hidden" name="t" value="<?php echo $type; ?>">
                        <input type="hidden" name="a" value="edit">
                        <input type="hidden" name="edit_language" value="<?php echo hesk_htmlspecialchars($hesk_settings['edit_language']); ?>">
                    </div>
                </form>
            </section>
            <?php if ($hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1): ?>
                <section class="param">
                    <form method="get" action="email_templates.php">
                        <span><?php echo $hesklang['lgs']; ?></span>
                        <div class="dropdown-select center out-close">
                            <select name="edit_language" onchange="this.form.submit()">
                                <?php foreach ($hesk_settings['languages'] as $lang => $info): ?>
                                    <option value="<?php echo $lang; ?>" <?php if ($lang === $hesk_settings['edit_language']) { ?>selected<?php } ?>>
                                        <?php echo $lang; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="t" value="<?php echo $type; ?>">
                            <input type="hidden" name="a" value="edit" />
                            <input type="hidden" name="id" value="<?php echo $email; ?>" />
                        </div>
                    </form>
                </section>
            <?php endif; ?>
            <form action="email_templates.php" method="post" name="form1">
                <div class="form-group">
                    <label for="message"><?php echo $hesklang['source'] . ': ' . substr($eml_file, 2); ?></label>
                    <span id="HeskMsg">
                        <textarea class="form-control" id="msg" name="msg" rows="35" cols="100"><?php echo hesk_htmlspecialchars(file_get_contents($eml_file)); ?></textarea>
                    </span>
                </div>
                <div class="template--tags">
                    <label><?php echo $hesklang['insert_special']; ?></label>
                    <div class="tag-list">
                        <?php if ($email == 'forgot_ticket_id'): ?>
                            <a href="javascript:" title="%%NAME%%" onclick="hesk_insertRichTag('NAME')">
                                <?php echo $hesklang['name']; ?>
                            </a>
                            <a href="javascript:" title="%%FIRST_NAME%%" onclick="hesk_insertRichTag('FIRST_NAME')">
                                <?php echo $hesklang['fname']; ?>
                            </a>
                            <a href="javascript:" title="%%NUM%%" onclick="hesk_insertRichTag('NUM')">
                                <?php echo $hesklang['et_num']; ?>
                            </a>
                            <a href="javascript:" title="%%LIST_TICKETS%%" onclick="hesk_insertRichTag('LIST_TICKETS')">
                                <?php echo $hesklang['et_list']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_TITLE%%" onclick="hesk_insertRichTag('SITE_TITLE')">
                                <?php echo $hesklang['wbst_title']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_URL%%" onclick="hesk_insertRichTag('SITE_URL')">
                                <?php echo $hesklang['wbst_url']; ?>
                            </a>
                        <?php elseif ($email == 'new_pm'): ?>
                            <a href="javascript:" title="%%NAME%%" onclick="hesk_insertRichTag('NAME')">
                                <?php echo $hesklang['name']; ?>
                            </a>
                            <a href="javascript:" title="%%FIRST_NAME%%" onclick="hesk_insertRichTag('FIRST_NAME')">
                                <?php echo $hesklang['fname']; ?>
                            </a>
                            <a href="javascript:" title="%%SUBJECT%%" onclick="hesk_insertRichTag('SUBJECT')">
                                <?php echo $hesklang['subject']; ?>
                            </a>
                            <a href="javascript:" title="%%MESSAGE%%" onclick="hesk_insertRichTag('MESSAGE')">
                                <?php echo $hesklang['message']; ?>
                            </a>
                            <a href="javascript:" title="%%TRACK_URL%%" onclick="hesk_insertRichTag('TRACK_URL')">
                                <?php echo $hesklang['pm_url']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_TITLE%%" onclick="hesk_insertRichTag('SITE_TITLE')">
                                <?php echo $hesklang['wbst_title']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_URL%%" onclick="hesk_insertRichTag('SITE_URL')">
                                <?php echo $hesklang['wbst_url']; ?>
                            </a>
                        <?php elseif ($email == 'reset_password'): ?>
                            <a href="javascript:" title="%%NAME%%" onclick="hesk_insertRichTag('NAME')">
                                <?php echo $hesklang['name']; ?>
                            </a>
                            <a href="javascript:" title="%%FIRST_NAME%%" onclick="hesk_insertRichTag('FIRST_NAME')">
                                <?php echo $hesklang['fname']; ?>
                            </a>
                            <a href="javascript:" title="%%PASSWORD_RESET%%" onclick="hesk_insertRichTag('PASSWORD_RESET')">
                                <?php echo $hesklang['passr']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_TITLE%%" onclick="hesk_insertRichTag('SITE_TITLE')">
                                <?php echo $hesklang['wbst_title']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_URL%%" onclick="hesk_insertRichTag('SITE_URL')">
                                <?php echo $hesklang['wbst_url']; ?>
                            </a>
                        <?php elseif ($email == 'mfa_verification'): ?>
                            <a href="javascript:" title="%%NAME%%" onclick="hesk_insertRichTag('NAME')">
                                <?php echo $hesklang['name']; ?>
                            </a>
                            <a href="javascript:" title="%%FIRST_NAME%%" onclick="hesk_insertRichTag('FIRST_NAME')">
                                <?php echo $hesklang['fname']; ?>
                            </a>
                            <a href="javascript:" title="%%VERIFICATION_CODE%%" onclick="hesk_insertRichTag('VERIFICATION_CODE')">
                                <?php echo $hesklang['mfa_short']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_TITLE%%" onclick="hesk_insertRichTag('SITE_TITLE')">
                                <?php echo $hesklang['wbst_title']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_URL%%" onclick="hesk_insertRichTag('SITE_URL')">
                                <?php echo $hesklang['wbst_url']; ?>
                            </a>
                        <?php else: ?>
                            <a href="javascript:" title="%%NAME%%" onclick="hesk_insertRichTag('NAME')">
                                <?php echo $hesklang['name']; ?>
                            </a>
                            <a href="javascript:" title="%%FIRST_NAME%%" onclick="hesk_insertRichTag('FIRST_NAME')">
                                <?php echo $hesklang['fname']; ?>
                            </a>
                            <a href="javascript:" title="%%EMAIL%%" onclick="hesk_insertRichTag('EMAIL')">
                                <?php echo $hesklang['email']; ?>
                            </a>
                            <a href="javascript:" title="%%CATEGORY%%" onclick="hesk_insertRichTag('CATEGORY')">
                                <?php echo $hesklang['category']; ?>
                            </a>
                            <a href="javascript:" title="%%PRIORITY%%" onclick="hesk_insertRichTag('PRIORITY')">
                                <?php echo $hesklang['priority']; ?>
                            </a>
                            <a href="javascript:" title="%%STATUS%%" onclick="hesk_insertRichTag('STATUS')">
                                <?php echo $hesklang['status']; ?>
                            </a>
                            <a href="javascript:" title="%%SUBJECT%%" onclick="hesk_insertRichTag('SUBJECT')">
                                <?php echo $hesklang['subject']; ?>
                            </a>
                            <a href="javascript:" title="%%MESSAGE%%" onclick="hesk_insertRichTag('MESSAGE')">
                                <?php echo $hesklang['message']; ?>
                            </a>
                            <a href="javascript:" title="%%ATTACHMENTS%%" onclick="hesk_insertRichTag('ATTACHMENTS')">
                                <?php echo $hesklang['attachments']; ?>
                            </a>
                            <a href="javascript:" title="%%CREATED%%" onclick="hesk_insertRichTag('CREATED')">
                                <?php echo $hesklang['created_on']; ?>
                            </a>
                            <a href="javascript:" title="%%UPDATED%%" onclick="hesk_insertRichTag('UPDATED')">
                                <?php echo $hesklang['updated_on']; ?>
                            </a>
                            <a href="javascript:" title="%%OWNER%%" onclick="hesk_insertRichTag('OWNER')">
                                <?php echo $hesklang['owner']; ?>
                            </a>
                            <a href="javascript:" title="%%LAST_REPLY_BY%%" onclick="hesk_insertRichTag('LAST_REPLY_BY')">
                                <?php echo $hesklang['last_replier']; ?>
                            </a>
                            <a href="javascript:" title="%%TIME_WORKED%%" onclick="hesk_insertRichTag('TIME_WORKED')">
                                <?php echo $hesklang['ts']; ?>
                            </a>
                            <a href="javascript:" title="%%DUE_DATE%%" onclick="hesk_insertRichTag('DUE_DATE')">
                                <?php echo $hesklang['due_date']; ?>
                            </a>
                            <a href="javascript:" title="%%TRACK_ID%%" onclick="hesk_insertRichTag('TRACK_ID')">
                                <?php echo $hesklang['trackID']; ?>
                            </a>
                            <a href="javascript:" title="%%ID%%" onclick="hesk_insertRichTag('ID')">
                                <?php echo $hesklang['seqid']; ?>
                            </a>
                            <a href="javascript:" title="%%TRACK_URL%%" onclick="hesk_insertRichTag('TRACK_URL')">
                                <?php echo $hesklang['ticket_url']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_TITLE%%" onclick="hesk_insertRichTag('SITE_TITLE')">
                                <?php echo $hesklang['wbst_title']; ?>
                            </a>
                            <a href="javascript:" title="%%SITE_URL%%" onclick="hesk_insertRichTag('SITE_URL')">
                                <?php echo $hesklang['wbst_url']; ?>
                            </a>
                            <?php
                            foreach ($hesk_settings['custom_fields'] as $k=>$v)
                            {
                                if ($v['use'])
                                {
                                    echo '<a href="javascript:" title="%%'.strtoupper($k).'%%" onclick="hesk_insertRichTag(\''.strtoupper($k).'\')">'.$v['name'].'</a>';
                                }
                            }

                            // Add survey tag?
                            if ($email == 'survey') {
                                ?>
                                <a href="javascript:" title="%%SURVEY_URL%%" onclick="hesk_insertRichTag('SURVEY_URL')">
                                    <?php echo rtrim($hesklang['satisfaction']['url'], ":"); ?>
                                </a>
                                <?php
                            }

                        endif;
                        ?>
                    </div>
                </div>
                <div class="right-bar__footer">
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                    <input type="hidden" name="a" value="save" />
                    <input type="hidden" name="edit_language" value="<?php echo hesk_htmlspecialchars($hesk_settings['edit_language']); ?>" />
                    <input type="hidden" name="id" value="<?php echo $email; ?>" />
                    <input type="hidden" name="t" value="<?php echo $type; ?>">
                    <button type="submit" class="btn btn-full save" ripple="ripple"><?php echo $hesklang['et_save']; ?></button>
                </div>
            </form>
            <?php
            if (function_exists('hesk_tinymce_init')) {
                hesk_tinymce_init('#msg');
            }
            ?>
        </div>
    </div>
    <?php
} // END EDIT

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function save_et()
{
	global $hesk_settings, $hesklang;

	// A security check
	# hesk_token_check('POST');

	// Get email ID
	$email = hesk_POST('id');

    // Get email type
    if (($type = hesk_POST('t')) != 'html') {
        $type = 'plaintext';
    }

	// Get file path
    $eml_file = et_file_path($email, $type);

	// Make sure the file exists and is writable
	if ( ! file_exists($eml_file))
	{
   		hesk_error($hesklang['et_fm']);
	}
	elseif ( ! is_writable($eml_file))
	{
		hesk_error($hesklang['et_fw']);
	}

	// Get message
	$message = trim(hesk_POST('msg'));

    if ($type == 'html') {
        // Trick to save a href="tel:" type links
        $message = preg_replace('/href="(tel:%%CUSTOM(\d{1,2})%%)"/', 'href="X-HESK-TEL-$2"', $message);

        // Clean the HTML content
        require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
        require(HESK_PATH . 'inc/html2text/html2text.php');
        $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
        $message = $purifier->heskPurify($message);

        // Restore a href="tel:" type links
        $message = preg_replace('/"X\-HESK\-TEL\-(\d{1,2})"/', '"tel:%%CUSTOM$1%%"', $message);

        // Make sure any Hesk emails tags are not URL-encoded
        $email_tags = array(
        'NAME',
        'FIRST_NAME',
        'SUBJECT',
        'TRACK_ID',
        'TRACK_URL',
        'SITE_TITLE',
        'SITE_URL',
        'CATEGORY',
        'PRIORITY',
        'OWNER',
        'STATUS',
        'EMAIL',
        'CREATED',
        'UPDATED',
        'DUE_DATE',
        'ID',
        'TIME_WORKED',
        'LAST_REPLY_BY',
        'FIRST_NAME',
        'ESCALATED_BY_RULE',
        'MESSAGE',
        'SURVEY_URL',
        'PASSWORD_RESET',
        'VERIFICATION_CODE',
        );

        for ($i = 1; $i <= 50; $i++) {
            $email_tags[] = 'CUSTOM' . $i;
        }

        $message = str_replace(
            array_map(function ($a) {return '%25%25' . $a . '%25%25';}, $email_tags),
            array_map(function ($a) {return '%%' . $a . '%%';}, $email_tags),
            $message
        );
    }

	// Do we need to remove backslashes from the message?
	if ( ! HESK_SLASH)
	{
    	$message = stripslashes($message);
	}

	// We won't accept an empty message
	if ( ! strlen($message))
	{
		hesk_process_messages($hesklang['et_empty'],'email_templates.php?a=edit&id=' . $email . '&t=' . $type . '&edit_language='.$hesk_settings['edit_language']);
	}

	// Save to the file
	file_put_contents($eml_file, $message);

	// Show success
    $_SESSION['et_id'] = $email;
    hesk_process_messages($hesklang['et_saved'],'email_templates.php?edit_language='.$hesk_settings['edit_language'],'SUCCESS');
} // End save_et()


function et_file_path($id, $type)
{
	global $hesk_settings, $hesklang, $emails;

	if ( ! in_array($id, $emails))
	{
    	hesk_error($hesklang['inve']);
	}

	$folder = $type === 'plaintext' ? 'emails' : 'html_emails';

	return HESK_PATH . 'language/' . $hesk_settings['languages'][$hesk_settings['edit_language']]['folder'] . '/' . $folder . '/' . $id . '.txt';
} // END et_file_path()
