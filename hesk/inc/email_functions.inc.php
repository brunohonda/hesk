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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {
    die('Invalid attempt');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Make sure custom fields are loaded
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Make sure statuses are loaded
require_once(HESK_PATH . 'inc/statuses.inc.php');

function hesk_notifyCustomer($email_template = 'new_ticket')
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    // No customer email
    if ($ticket['email'] == '') {
        return true;
    }

    // Make sure customer gets response in correct language
    if (isset($ticket['language'])) {
        hesk_setLanguage($ticket['language']);
    }

    // Format email subject and message
    $subject = hesk_getEmailSubject($email_template, $ticket);
    list($message, $html_message) = hesk_getEmailMessage($email_template, $ticket);

    // Send e-mail
    hesk_mail($ticket['email'], $subject, $message, $html_message, $ticket['trackid']);

    // Reset language if needed
    hesk_resetLanguage();

    return true;

} // END hesk_notifyCustomer()


function hesk_notifyAssignedStaff($autoassign_owner, $email_template, $type = 'notify_assigned')
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    $ticket['owner'] = intval($ticket['owner']);

    /* Need to lookup owner info from the database? */
    if ($autoassign_owner === false) {
        $res = hesk_dbQuery("SELECT `name`, `email`,`language`,`notify_assigned`,`notify_reply_my` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . $ticket['owner'] . "' LIMIT 1");

        $autoassign_owner = hesk_dbFetchAssoc($res);
        $hesk_settings['user_data'][$ticket['owner']] = $autoassign_owner;

        /* If owner selected not to be notified or invalid stop here */
        if (empty($autoassign_owner[$type])) {
            return false;
        }
    }

    /* Set new language if required */
    hesk_setLanguage($autoassign_owner['language']);

    /* Format email subject and message for staff */
    $subject = hesk_getEmailSubject($email_template, $ticket);
    list($message, $html_message) = hesk_getEmailMessage($email_template, $ticket, 1);

    /* Send email to staff */
    hesk_mail($autoassign_owner['email'], $subject, $message, $html_message);

    /* Reset language to original one */
    hesk_resetLanguage();

    return true;

} // END hesk_notifyAssignedStaff()


function hesk_notifyStaff($email_template, $sql_where, $is_ticket = 1)
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    $admins = array();

    $res = hesk_dbQuery("SELECT `email`,`language`,`isadmin`,`categories` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE $sql_where ORDER BY `language`");
    while ($myuser = hesk_dbFetchAssoc($res)) {
        /* Is this an administrator? */
        if ($myuser['isadmin']) {
            $admins[] = array('email' => $myuser['email'], 'language' => $myuser['language']);
            continue;
        }

        /* Not admin, is he/she allowed this category? */
        $myuser['categories'] = explode(',', $myuser['categories']);
        if (in_array($ticket['category'], $myuser['categories'])) {
            $admins[] = array('email' => $myuser['email'], 'language' => $myuser['language']);
            continue;
        }
    }

    if (count($admins) > 0) {
        /* Make sure each user gets email in his/her preferred language */
        $current_language = 'NONE';
        $recipients = array();

        /* Loop through staff */
        foreach ($admins as $admin) {
            /* If admin language is NULL force default HESK language */
            if (!$admin['language'] || !isset($hesk_settings['languages'][$admin['language']])) {
                $admin['language'] = HESK_DEFAULT_LANGUAGE;
            }

            /* Generate message or add email to the list of recepients */
            if ($admin['language'] == $current_language) {
                /* We already have the message, just add email to the recipients list */
                $recipients[] = $admin['email'];
            } else {
                /* Send email messages in previous languages (if required) */
                if ($current_language != 'NONE') {
                    /* Send e-mail to staff */
                    hesk_mail(implode(',', $recipients), $subject, $message, $html_message);

                    /* Reset list of email addresses */
                    $recipients = array();
                }

                /* Set new language */
                hesk_setLanguage($admin['language']);

                /* Format staff email subject and message for this language */
                $subject = hesk_getEmailSubject($email_template, $ticket);
                list($message, $html_message) = hesk_getEmailMessage($email_template, $ticket, $is_ticket);

                /* Add email to the recipients list */
                $recipients[] = $admin['email'];

                /* Remember the last processed language */
                $current_language = $admin['language'];
            }
        }

        /* Send email messages to the remaining staff */
        hesk_mail(implode(',', $recipients), $subject, $message, $html_message);

        /* Reset language to original one */
        hesk_resetLanguage();
    }

    return true;

} // END hesk_notifyStaff()

function hesk_sendOverdueTicketReminder($ticket, $users)
{

    if (defined('HESK_DEMO')) {
        return true;
    }

    hesk_setLanguage($ticket['user_language']);

    // Format email subject and message
    $subject = hesk_getEmailSubject('overdue_ticket', $ticket);
    list($message, $html_message) = hesk_getEmailMessage('overdue_ticket', $ticket, 1);

    $emails = array();
    if ($ticket['user_email'] != null) {
        $emails[] = $ticket['user_email'];
    } else {
        foreach ($users as $user) {
            $categories = explode(',', $user['categories']);
            if ($user['notify_overdue_unassigned'] && ($user['isadmin'] || in_array($ticket['category'], $categories))) {
                $emails[] = $user['email'];
            }
        }
    }

    if (count($emails)) {
        hesk_mail(implode(',', $emails), $subject, $message, $html_message);
    }

    return true;
}


function hesk_validEmails()
{
    global $hesklang;

    return array(

        /*** Emails sent to CLIENT ***/

        // --> Send reminder about existing tickets
        'forgot_ticket_id' => $hesklang['forgot_ticket_id'],

        // --> Staff replied to a ticket
        'new_reply_by_staff' => $hesklang['new_reply_by_staff'],

        // --> New ticket submitted
        'new_ticket' => $hesklang['ticket_received'],

        // --> New ticket submitted by staff
        'new_ticket_by_staff' => $hesklang['new_ticket_by_staff'], 

        // --> Ticket closed
        'ticket_closed' => $hesklang['ticket_closed'],

        /*** Emails sent to STAFF ***/

        // --> Ticket moved to a new category
        'category_moved' => $hesklang['category_moved'],

        // --> Client replied to a ticket
        'new_reply_by_customer' => $hesklang['new_reply_by_customer'],

        // --> New ticket submitted
        'new_ticket_staff' => $hesklang['new_ticket_staff'],

        // --> New ticket assigned to staff
        'ticket_assigned_to_you' => $hesklang['ticket_assigned_to_you'],

        // --> New private message
        'new_pm' => $hesklang['new_pm'],

        // --> New note by someone to a ticket assigned to you
        'new_note' => $hesklang['new_note'],

        // --> Staff password reset email
        'reset_password' => $hesklang['reset_password'],

        // --> Overdue ticket email
        'overdue_ticket' => $hesklang['overdue_ticket'],

        // --> MFA Verification email
        'mfa_verification' => $hesklang['mfa_verification'],

    );
} // END hesk_validEmails()


function hesk_mail($to, $subject, $message, $html_message, $tracking_ID = null)
{
    global $hesk_settings, $hesklang;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    // usleep(100);

    // Empty recipient?
    if ($to == '') {
        return true;
    }

    // Stop if we find anything suspicious in the headers
    if (preg_match("/\n|\r|\t|%0A|%0D|%08|%09/", $to . $subject)) {
        return false;
    }

    // Encode subject to UTF-8
    $subject = hesk_html_entity_decode($subject);

    // Setup "name <email>" for headers
    if ($hesk_settings['noreply_name']) {
        $hesk_settings['from_name'] = $hesk_settings['noreply_name'];
    } else {
        $hesk_settings['from_name'] = $hesk_settings['noreply_mail'];
    }

    // Uncomment for debugging
    # echo "<p>TO: $to<br >SUBJECT: $subject<br >MSG: $message</p>";
    # return true;

    // Remove duplicate recipients
    $to_arr = array_unique(explode(',', $to));
    $to_arr = array_values($to_arr);

    // Start output buffering so that any errors don't break headers
    ob_start();

    try {
        $mailer = new PHPMailer(true);
        $mailer->XMailer = ' ';

        if ($hesk_settings['smtp']) {
            $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $mailer->isSMTP();
            $mailer->Host = $hesk_settings['smtp_host_name'];
            $mailer->Port = $hesk_settings['smtp_host_port'];

            if (strlen($hesk_settings['smtp_user']) || strlen($hesk_settings['smtp_password']) || $hesk_settings['smtp_oauth_provider']) {
                $mailer->SMTPAuth = true;
                $mailer->Username = $hesk_settings['smtp_user'];

                if ($hesk_settings['smtp_conn_type'] == 'oauth') {
                    require_once(HESK_PATH . 'inc/oauth_functions.inc.php');
                    require_once(HESK_PATH . 'inc/mail/HeskOAuthTokenProvider.php');

                    $oauthTokenProvider = new \PHPMailer\PHPMailer\HeskOAuthTokenProvider();
                    $oauthTokenProvider->username = $hesk_settings['smtp_user'];
                    $oauthTokenProvider->provider = $hesk_settings['smtp_oauth_provider'];

                    $mailer->AuthType = 'XOAUTH2';
                    $mailer->setOAuth($oauthTokenProvider);
                } else {
                    $mailer->Password = hesk_htmlspecialchars_decode($hesk_settings['smtp_password']);
                }
            }

            $mailer->Timeout = $hesk_settings['smtp_timeout'];
            if ($hesk_settings['smtp_noval_cert']) {
                $mailer->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
            }
            if ($hesk_settings['smtp_enc'] == 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($hesk_settings['smtp_enc'] == 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }

        $mailer->setFrom($hesk_settings['noreply_mail'], $hesk_settings['noreply_name']);
        $mailer->addReplyTo($hesk_settings['noreply_mail'], $hesk_settings['noreply_name']);
        foreach ($to_arr as $to) {
            $mailer->addAddress($to);
        }
        $mailer->Subject = $subject;
        $mailer->CharSet = $hesklang['ENCODING'];

        // Save ticket ID in a custom header
        if ($tracking_ID !== null) {
            $mailer->addCustomHeader('X-Hesk-Tracking_ID', $tracking_ID);
        }

        // Do we auto-generate the AltBody from the HTML template?
        if ($hesk_settings['email_formatting'] == 2) {
            $mailer->msgHTML($html_message);
        } else {
            // HTML email?
            if ($hesk_settings['email_formatting']) {
                $mailer->isHTML();
            }

            // Body holds plaintext if we're not sending HTML
            $mailer->Body = $hesk_settings['email_formatting'] ?
                $html_message :
                $message;

            // Only include plain text in Alt if both HTML and plain text are being sent
            if ($hesk_settings['email_formatting'] == 3) {
                $mailer->AltBody = $message;
            }
        }
    } catch (Exception $e) {
        if ($hesk_settings['debug_mode']) {
            $error = $hesklang['cnsm'] . ' ' . $to . '<br /><br />' . $hesklang['error'] . ': ' . htmlspecialchars($mailer->ErrorInfo);
            if ($debug_log = ob_get_contents()) {
                $error .= '<br /><br /><textarea name="smtp_log" rows="10" cols="60">' . $debug_log . '</textarea>';
            }
            $_SESSION['HESK_2ND_NOTICE'] = true;
            $_SESSION['HESK_2ND_MESSAGE'] = $hesklang['esf'] . ' ' . $error;
        } else {
            $_SESSION['HESK_2ND_NOTICE'] = true;
            $_SESSION['HESK_2ND_MESSAGE'] = $hesklang['esf'] . ' ' . $hesklang['contact_webmsater'] . ' <a href="mailto:' . $hesk_settings['webmaster_mail'] . '">' . $hesk_settings['webmaster_mail'] . '</a>';
        }

        ob_end_clean();
        return false;
    }

    try {
        ob_start();
        $mailer->send();
        ob_end_clean();
    } catch (Exception $e) {
        if ($hesk_settings['debug_mode']) {
            $error = $hesklang['cnsm'] . ' ' . $to . '<br /><br />' . $hesklang['error'] . ': ' . htmlspecialchars($mailer->ErrorInfo);
            if ($debug_log = ob_get_contents()) {
                $error .= '<br /><br /><textarea name="smtp_log" rows="10" cols="60">' . $debug_log . '</textarea>';
            }
            $_SESSION['HESK_2ND_NOTICE'] = true;
            $_SESSION['HESK_2ND_MESSAGE'] = $hesklang['esf'] . ' ' . $error;
        } else {
            $_SESSION['HESK_2ND_NOTICE'] = true;
            $_SESSION['HESK_2ND_MESSAGE'] = $hesklang['esf'] . ' ' . $hesklang['contact_webmsater'] . ' <a href="mailto:' . $hesk_settings['webmaster_mail'] . '">' . $hesk_settings['webmaster_mail'] . '</a>';
        }

        ob_end_clean();
        return false;
    }

    ob_end_clean();
    return true;
} // END hesk_mail()


function hesk_getEmailSubject($eml_file, $ticket = '', $is_ticket = 1, $strip = 0)
{
    global $hesk_settings, $hesklang;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return '';
    }

    /* Get list of valid emails */
    $valid_emails = hesk_validEmails();

    /* Verify this is a valid email include */
    if (!isset($valid_emails[$eml_file])) {
        hesk_error($hesklang['inve']);
    } else {
        $msg = $valid_emails[$eml_file];
    }

    /* If not a ticket-related email return subject as is */
    if (!$ticket) {
        return $msg;
    }

    /* Strip slashes from the subject only if it's a new ticket */
    if ($strip) {
        $ticket['subject'] = stripslashes($ticket['subject']);
    }

    /* Not a ticket, but has some info in the $ticket array */
    if (!$is_ticket) {
        return str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
    }

    /* Set category title */
    $ticket['category'] = hesk_msgToPlain(hesk_getCategoryName($ticket['category']), 1, 0);

    /* Get priority */
    switch ($ticket['priority']) {
        case 0:
            $ticket['priority'] = $hesklang['critical'];
            break;
        case 1:
            $ticket['priority'] = $hesklang['high'];
            break;
        case 2:
            $ticket['priority'] = $hesklang['medium'];
            break;
        default:
            $ticket['priority'] = $hesklang['low'];
    }

    /* Set status */
    $ticket['status'] = hesk_get_status_name($ticket['status']);

    // Convert any entities in site title to plain text
    $site_title = hesk_msgToPlain($hesk_settings['site_title'], 1, 0);

    /* Replace all special tags */
    $msg = str_replace('%%SITE_TITLE%%', $site_title, $msg);
    $msg = str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
    $msg = str_replace('%%TRACK_ID%%', $ticket['trackid'], $msg);
    $msg = str_replace('%%CATEGORY%%', $ticket['category'], $msg);
    $msg = str_replace('%%PRIORITY%%', $ticket['priority'], $msg);
    $msg = str_replace('%%STATUS%%', $ticket['status'], $msg);

    return $msg;

} // hesk_getEmailSubject()


function hesk_getEmailMessage($eml_file, $ticket, $is_admin = 0, $is_ticket = 1, $just_message = 0)
{
    global $hesk_settings, $hesklang;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return '';
    }

    /* Get list of valid emails */
    $valid_emails = hesk_validEmails();

    /* Verify this is a valid email include */
    if (!isset($valid_emails[$eml_file])) {
        hesk_error($hesklang['inve']);
    }

    /* Get email template */
    $orig_eml_file = $eml_file;
    $eml_file = 'language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/emails/' . $orig_eml_file . '.txt';
    $html_eml_file = 'language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/html_emails/' . $orig_eml_file . '.txt';

    if (file_exists(HESK_PATH . $eml_file)) {
        $msg = file_get_contents(HESK_PATH . $eml_file);
    } else {
        hesk_error($hesklang['emfm'] . ': ' . $eml_file);
    }

    if (file_exists(HESK_PATH . $html_eml_file)) {
        $html_msg = file_get_contents(HESK_PATH . $html_eml_file);
    } else {
        hesk_error($hesklang['emfm'] . ': ' . $html_eml_file);
    }

    /* Return just the message without any processing? */
    if ($just_message) {
        return array($msg, $html_msg);
    }

    // Convert any entities in site title to plain text
    $site_title = hesk_msgToPlain($hesk_settings['site_title'], 1, 0);

    // Create a HTML-version of the message if needed
    if (isset($ticket['message']) && ! isset($ticket['message_html']) ) {
        $ticket['message_html'] = '';
    }

    /* If it's not a ticket-related mail (like "a new PM") just process quickly */
    if (!$is_ticket) {
        $trackingURL = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/mail.php?a=read&id=' . intval($ticket['id']);

        list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $ticket['name'], $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%SUBJECT%%', $ticket['subject'], $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_URL%%', $trackingURL . ' ', $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%', $site_title, $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $msg, $html_msg);
        list($msg, $html_msg) = hesk_replace_email_tag('%%FIRST_NAME%%', hesk_full_name_to_first_name($ticket['name']), $msg, $html_msg);

        if (isset($ticket['message'])) {
            $msg = str_replace('%%MESSAGE%%', $ticket['message'], $msg);
            $html_msg = str_replace('%%MESSAGE%%', $ticket['message_html'], $html_msg);
        }

        return array($msg, $html_msg);
    }

    // Is email required to view ticket (for customers only)?
    $hesk_settings['e_param'] = $hesk_settings['email_view_ticket'] ? '&e=' . rawurlencode($ticket['email']) : '';

    /* Generate the ticket URLs */
    $trackingURL = $hesk_settings['hesk_url'];
    $trackingURL .= $is_admin ? '/' . $hesk_settings['admin_dir'] . '/admin_ticket.php' : '/ticket.php';
    $trackingURL .= '?track=' . $ticket['trackid'] . ($is_admin ? '' : $hesk_settings['e_param']) . '&Refresh=' . rand(10000, 99999);

    /* Set category title */
    $ticket['category'] = hesk_msgToPlain(hesk_getCategoryName($ticket['category']), 1, 0);

    /* Set priority title */
    switch ($ticket['priority']) {
        case 0:
            $ticket['priority'] = $hesklang['critical'];
            break;
        case 1:
            $ticket['priority'] = $hesklang['high'];
            break;
        case 2:
            $ticket['priority'] = $hesklang['medium'];
            break;
        default:
            $ticket['priority'] = $hesklang['low'];
    }

    /* Get owner name */
    $ticket['owner'] = hesk_msgToPlain(hesk_getOwnerName($ticket['owner']), 1, 0);

    /* Set status */
    $ticket['status'] = hesk_get_status_name($ticket['status']);

    // Get name of the person who posted the last message
    if (!isset($ticket['last_reply_by'])) {
        $ticket['last_reply_by'] = hesk_getReplierName($ticket);
    }

    /* Replace all special tags */
    list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $ticket['name'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SUBJECT%%', $ticket['subject'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_ID%%', $ticket['trackid'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_URL%%', $trackingURL . ' ', $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%', $site_title, $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%CATEGORY%%', $ticket['category'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%PRIORITY%%', $ticket['priority'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%OWNER%%', $ticket['owner'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%STATUS%%', $ticket['status'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%EMAIL%%', $ticket['email'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%CREATED%%', $ticket['dt'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%UPDATED%%', $ticket['lastchange'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%DUE_DATE%%', $ticket['due_date'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%ID%%', $ticket['id'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TIME_WORKED%%', $ticket['time_worked'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%LAST_REPLY_BY%%', $ticket['last_reply_by'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FIRST_NAME%%', hesk_full_name_to_first_name($ticket['name']), $msg, $html_msg);

    /* All custom fields */
    for ($i = 1; $i <= 50; $i++) {
        $k = 'custom' . $i;

        if (isset($hesk_settings['custom_fields'][$k])) {
            $v = $hesk_settings['custom_fields'][$k];

            switch ($v['type']) {
                case 'checkbox':
                    $ticket[$k] = str_replace("<br />", "\n", $ticket[$k]);
                    break;
                case 'date':
                    $ticket[$k] = hesk_custom_date_display_format($ticket[$k], $v['value']['date_format']);
                    break;
            }

            list($msg, $html_msg) = hesk_replace_email_tag('%%' . strtoupper($k) . '%%', $ticket[$k], $msg, $html_msg);
        } else {
            list($msg, $html_msg) = hesk_replace_email_tag('%%' . strtoupper($k) . '%%', '', $msg, $html_msg);
        }
    }

    // Let's handle the attachments tag
    $att_links = array();
    $html_att_links = array();

    if ($hesk_settings['attachments']['use'] && isset($ticket['attachments']) && strlen($ticket['attachments'])) {
        $att = explode(',', substr($ticket['attachments'], 0, -1));
        foreach ($att as $myatt) {
            list($att_id, $att_name) = explode('#', $myatt);
            $att_links[] = $att_name . "\n" . $hesk_settings['hesk_url'] . '/download_attachment.php?att_id=' . $att_id . '&track=' . $ticket['trackid'] . $hesk_settings['e_param'];
            $html_att_links[] = '<a href="'.$hesk_settings['hesk_url'] . '/download_attachment.php?att_id=' . $att_id . '&amp;track=' . $ticket['trackid'] . str_replace('&', '&amp;', $hesk_settings['e_param']).'">'.$att_name.'</a>';
        }
    }

    $att_links = implode(" \n\n", $att_links);
    $html_att_links = implode("<br/>", $html_att_links);

    $msg = str_replace('%%ATTACHMENTS%%', $att_links, $msg, $count_plain);
    $html_msg = str_replace('%%ATTACHMENTS%%', $html_att_links, $html_msg, $count_html);

    // Is message tag in email template?
    if (strpos($msg, '%%MESSAGE%%') !== false || strpos($html_msg, '%%MESSAGE%%') !== false) {
        // If there are attachments to this email and the %%ATTACHMENTS%% tag was not present, add links to attachments below the message
        if ($hesk_settings['attachments']['use'] && isset($ticket['attachments']) && strlen($ticket['attachments'])) {
            if ($count_plain == 0) {
                $ticket['message'] .= "\n\n" . $hesklang['fatt'] . "\n\n" . $att_links;
            }
            if ($count_html == 0) {
                $ticket['message_html'] .= "<br/><br/>" . $hesklang['fatt'] . "<br/>" .  $html_att_links;
            }
        }

        // Replace message
        $msg = str_replace('%%MESSAGE%%', $ticket['message'], $msg);
        $html_msg = str_replace('%%MESSAGE%%', $ticket['message_html'], $html_msg);

        // For customer notifications: if we allow email piping/pop 3 fetching and
        // stripping quoted replies add an "reply above this line" tag
        if (!$is_admin && ($hesk_settings['email_piping'] || $hesk_settings['pop3'] || $hesk_settings['imap']) && $hesk_settings['strip_quoted']) {
            $msg = $hesklang['EMAIL_HR'] . "\n\n" . $msg;
            $html_msg = $hesklang['EMAIL_HR'] . '<br/><br/>' . $html_msg;
        }
    }

    return array($msg, $html_msg);

} // END hesk_getEmailMessage

function hesk_replace_email_tag($tag, $value, $message, $html_message, $nl2br = false, $html_value = false) {
    if ($html_value) {
        return array(
            str_replace($tag, $value, $message),
            str_replace($tag, ($nl2br ? nl2br($html_value) : $html_value), $html_message)
        );
    }
    return array(
        str_replace($tag, $value, $message),
        str_replace($tag, ($nl2br ? nl2br(hesk_htmlspecialchars(trim($value))) : hesk_htmlspecialchars(trim($value))), $html_message)
    );
}

function hesk_encodeIfNotAscii($str, $escape_header = false)
{
    // Match anything outside of ASCII range
    if (preg_match('/[^\x00-\x7F]/', $str)) {
        return "=?UTF-8?B?" . base64_encode($str) . "?=";
    }

    // Do we need to wrap the header in double quotes?
    if ($escape_header && preg_match("/[^-A-Za-z0-9!#$%&'*+\/=?^_`{|}~\\s]+/", $str)) {
        return '"' . str_replace('"', '\\"', $str) . '"';
    }

    return $str;
} // END hesk_encodeIfNotAscii()


function hesk_PMtoMainAdmin($landmark)
{
    global $hesk_settings, $hesklang;

    $offer_license = file_exists(HESK_PATH.'hesk_license.php') ? "" : "<h3>&raquo; Look professional</h3>\r\n\r\n<p>To not only support Hesk development but also look more professional, <a href=\"https://www.hesk.com/get/hesk3-license\">remove &quot;Powered by&quot; links</a> from your help desk.</p>\r\n\r\n";

    switch ($landmark) {
        case 100:
            $subject = "Congratulations on your 100th ticket!";
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">You are now part of the Hesk family, and we want to serve you better!</h2>\r\n\r\n<h3>&raquo; Help us improve</h3>\r\n\r\n<p>Suggest what features we should add to Hesk by posting them <a href=\"https://hesk.uservoice.com/forums/69851-general\" target=\"_blank\">here</a>.</p>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://twitter.com/HESKdotCOM\">follow Hesk on <b>Twitter</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n{$offer_license}<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
            break;
        case 1000:
            $subject = "We're excited about your 1,000th ticket!";
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">With 1,000 support tickets under the hood, you\'ve become a Hesk Power User. Congratulations!</h2>\r\n\r\n<h3>&raquo; Help us improve</h3>\r\n\r\n<p>Suggest what features we should add to Hesk by posting them <a href=\"https://hesk.uservoice.com/forums/69851-general\" target=\"_blank\">here</a>.</p>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://twitter.com/HESKdotCOM\">follow Hesk on <b>Twitter</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n{$offer_license}<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
            break;
        case 10000:
            $subject = "Wow, you've reached 10,000 tickets!";
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">You received 10,000 support tickets, outstanding! You are officially a Hesk Hero!</h2>\r\n\r\n<h3>&raquo; Help us improve</h3>\r\n\r\n<p>Suggest what features we should add to Hesk by posting them <a href=\"https://hesk.uservoice.com/forums/69851-general\" target=\"_blank\">here</a>.</p>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://twitter.com/HESKdotCOM\">follow Hesk on <b>Twitter</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n{$offer_license}<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
            break;
        default:
            return false;
    }

    // Insert private message for main admin
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` (`id`, `from`, `to`, `subject`, `message`, `dt`, `read`, `deletedby`) VALUES (NULL, 9999, 1, '".hesk_dbEscape($subject)."', '{$message}', NOW(), '0', 9999)");
    $pm_id = hesk_dbInsertID();

    // Notify admin
    $res = hesk_dbQuery("SELECT `name`,`email` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`=1");
    $row = hesk_dbFetchAssoc($res);

    $pm = array(
        'name'    => 'HESK.com',
        'subject' => $subject,
        'message' => 'Please log in to see the message',
        'id'      => $pm_id,
    );

    // Format email subject and message for recipient
    $subject = hesk_getEmailSubject('new_pm',$pm,0);
    list($message, $html_message) = hesk_getEmailMessage('new_pm',$pm,1,0);

    // Send e-mail
    hesk_mail($row['email'], $subject, $message, $html_message);

    return true;

} // END hesk_PMtoMainAdmin()
