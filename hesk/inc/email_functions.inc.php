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

// Load required includes if not already
require_once(HESK_PATH . 'inc/custom_fields.inc.php');
require_once(HESK_PATH . 'inc/statuses.inc.php');
require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
require_once(HESK_PATH . 'inc/priorities.inc.php');

function hesk_notifyCustomer($email_template = 'new_ticket')
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    // We don't need to attach any original ticket files when marking as "Resolved" without replying
    if ($email_template == 'ticket_closed') {
        $ticket['attachments'] = '';
    }

    $customers = hesk_get_customers_for_ticket($ticket['id']);
    $ticket['customers'] = $customers;

    $language_to_customers = [];
    foreach ($customers as $customer) {
        if ($customer['email'] === null || $customer['email'] === '') {
            continue;
        }

        // Make sure customer gets response in correct language
        $language = HESK_DEFAULT_LANGUAGE;
        if (isset($customer['language'])) {
            $language = $customer['language'];
        } elseif (isset($ticket['language'])) {
            $language = $ticket['language'];
        }

        if (!isset($language)) {
            $language_to_customers[$language] = [];
        }
        $language_to_customers[$language][] = $customer;
    }

    foreach ($language_to_customers as $language => $customers) {
        hesk_setLanguage($language);
        $subject = hesk_getEmailSubject($email_template, $ticket);
        list($message, $html_message, $mail_direct_attachment) = hesk_getEmailMessage($email_template, $ticket);

        $customer_emails = hesk_getEmailsForCustomers($customers);
        hesk_mail($customer_emails['requester'], $customer_emails['followers'], $subject, $message, $html_message, $ticket['trackid'], $mail_direct_attachment);

        // Reset language if needed
        hesk_resetLanguage();
    }


    return true;

} // END hesk_notifyCustomer()

function hesk_getEmailsForCustomers($customers) {
    $requester = null;
    $followers = [];
    foreach ($customers as $customer) {
        if ($customer['customer_type'] === 'REQUESTER') {
            $requester = $customer['email'];
        } else {
            $followers[] = $customer['email'];
        }
    }

    return [
        'requester' => $requester,
        'followers' => $followers
    ];
}


function hesk_notifyCollaborators($collaborator_ids, $email_template, $type = 'notify_collaborator_customer_reply', $exclude_users = array())
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    if (count($exclude_users)) {
        foreach ($collaborator_ids as $k => $v) {
            if (in_array($v, $exclude_users)) {
                unset($collaborator_ids[$k]);
            }
        }
    }

    if (count($collaborator_ids) == 0) {
        return true;
    }

    $admins = array();
    $res = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `id` IN (".implode(',', $collaborator_ids).") ORDER BY `language`");
    while ($user = hesk_dbFetchAssoc($res)) {
        if (empty($user[$type])) {
            continue;
        }
        $admins[] = $user;
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
                    hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message);

                    /* Reset list of email addresses */
                    $recipients = array();
                }

                /* Set new language */
                hesk_setLanguage($admin['language']);

                /* Format staff email subject and message for this language */
                $subject = hesk_getEmailSubject($email_template, $ticket);
                list($message, $html_message, $mail_direct_attachment) = hesk_getEmailMessage($email_template, $ticket, 1);

                /* Add email to the recipients list */
                $recipients[] = $admin['email'];

                /* Remember the last processed language */
                $current_language = $admin['language'];
            }
        }

        /* Send email messages to the remaining staff */
        hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message, $ticket['trackid'], $mail_direct_attachment);

        /* Reset language to original one */
        hesk_resetLanguage();
    }

    return true;

} // END hesk_notifyCollaborators()


function hesk_notifyAssignedStaff($autoassign_owner, $email_template, $type = 'notify_assigned', $type_collaborators = false, $exclude_users = array())
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    $ticket['owner'] = intval($ticket['owner']);

    // This will be list of staff we need to lookup from the DB
    $lookup = array();

    // This will be staff that we need to notify
    $admins = array();

    if ($autoassign_owner === false) {
        $lookup[] = $ticket['owner'];
    } else {
        $admins[] = $autoassign_owner;
        $hesk_settings['user_data'][$ticket['owner']] = $autoassign_owner;
    }

    if ($type_collaborators !== false && count($ticket['collaborators'])) {
        $lookup = array_merge($lookup, $ticket['collaborators']);
    }

    $lookup = array_unique($lookup);

    if (count($exclude_users)) {
        foreach ($lookup as $k => $v) {
            if (in_array($v, $exclude_users)) {
                unset($lookup[$k]);
            }
        }
    }

    // print_r($lookup);

    // Get user info from the DB if we need it
    if (count($lookup)) {
        $res = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `id` IN (".implode(',', $lookup).") ORDER BY `language`");

        while ($user = hesk_dbFetchAssoc($res)) {
            $hesk_settings['user_data'][$user['id']] = $user;

            // Owner, but doesn't want to be notified
            if ($user['id'] == $ticket['owner'] && empty($user[$type])) {
                continue;
            }

            // Collaborator, but doesn't want to be notified
            if ($user['id'] != $ticket['owner'] && $type_collaborators !== false && empty($user[$type_collaborators])) {
                continue;
            }

            // Wants to be notified
            $admins[] = $user;
        }
    }

    if (count($exclude_users)) {
        foreach ($admins as $k => $v) {
            if (in_array($v, $exclude_users)) {
                unset($admins[$k]);
            }
        }
    }

    // print_r($admins);

    // We have staff to notify; make sure they get emails in ther preferred langauge
    if (count($admins) > 0) {

        $to = array();
        $cc = array();
        $current_language = 'NONE';

        foreach ($admins as $admin) {
            // If admin language is NULL force default HESK language
            if (!$admin['language'] || !isset($hesk_settings['languages'][$admin['language']])) {
                $admin['language'] = HESK_DEFAULT_LANGUAGE;
            }

            // Generate message or add email to the list of recepients
            if ($admin['language'] == $current_language) {
                // We already have the message, just add email to the recipients list
                // Owner will be "To", Collaborators in "Cc"
                if ($admin['id'] == $ticket['owner']) {
                    $to[] = $admin['email'];
                } else {
                    $cc[] = $admin['email'];
                }
            } else {
                // Send email messages in previous languages (if required)
                if ($current_language != 'NONE') {
                    hesk_mail(implode(',', $to), $cc, $subject, $message, $html_message);

                    // Reset recipients
                    $to = array();
                    $cc = array();
                }

                // Set new language
                hesk_setLanguage($admin['language']);

                // Format staff email subject and message for this language
                $subject = hesk_getEmailSubject($email_template, $ticket);
                list($message, $html_message, $mail_direct_attachment) = hesk_getEmailMessage($email_template, $ticket, 1);

                // Add email to the recipients list
                if ($admin['id'] == $ticket['owner']) {
                    $to[] = $admin['email'];
                } else {
                    $cc[] = $admin['email'];
                }

                // Remember the last processed language
                $current_language = $admin['language'];
            }
        }

        // Send email messages to the remaining staff
        hesk_mail(implode(',', $to), $cc, $subject, $message, $html_message, $ticket['trackid'], $mail_direct_attachment);

        // Reset language to original one
        hesk_resetLanguage();
    }

    return true;

} // END hesk_notifyAssignedStaff()


function hesk_notifyStaff($email_template, $sql_where, $is_ticket = 1, $exclude_users = array())
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    $admins = array();

    $res = hesk_dbQuery("SELECT `id`,`email`,`language`,`isadmin`,`categories` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE $sql_where ORDER BY `language`");
    while ($myuser = hesk_dbFetchAssoc($res)) {
        /* Is this an administrator? */
        if ($myuser['isadmin']) {
            $admins[$myuser['id']] = array('email' => $myuser['email'], 'language' => $myuser['language']);
            continue;
        }

        /* Not admin, is he/she allowed this category? */
        $myuser['categories'] = explode(',', $myuser['categories']);
        if (in_array($ticket['category'], $myuser['categories'])) {
            $admins[$myuser['id']] = array('email' => $myuser['email'], 'language' => $myuser['language']);
            continue;
        }
    }

    if (count($exclude_users)) {
        foreach ($admins as $k => $v) {
            if (in_array($k, $exclude_users)) {
                unset($admins[$k]);
            }
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
                    hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message);

                    /* Reset list of email addresses */
                    $recipients = array();
                }

                /* Set new language */
                hesk_setLanguage($admin['language']);

                /* Format staff email subject and message for this language */
                $subject = hesk_getEmailSubject($email_template, $ticket);
                list($message, $html_message, $mail_direct_attachment) = hesk_getEmailMessage($email_template, $ticket, $is_ticket);

                /* Add email to the recipients list */
                $recipients[] = $admin['email'];

                /* Remember the last processed language */
                $current_language = $admin['language'];
            }
        }

        /* Send email messages to the remaining staff */
        hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message, $ticket['trackid'], $mail_direct_attachment);

        /* Reset language to original one */
        hesk_resetLanguage();
    }

    return true;

} // END hesk_notifyStaff()


function hesk_notifyStaffOfPendingApprovals($num)
{
    global $hesk_settings, $hesklang, $ticket;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    $admins = array();
    $res = hesk_dbQuery("SELECT `email`,`language` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `notify_customer_approval` = '1' AND (`isadmin` = '1' OR `heskprivileges` LIKE '%can_man_customers%') ORDER BY `language`");
    while ($myuser = hesk_dbFetchAssoc($res)) {
        $admins[] = array('email' => $myuser['email'], 'language' => $myuser['language']);
    }

    if (count($admins) == 0) {
        return true;
    }

    $email_template = 'new_customer_approval';
    $verification_url = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/manage_customers.php';

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
                hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message);

                /* Reset list of email addresses */
                $recipients = array();
            }

            /* Set new language */
            hesk_setLanguage($admin['language']);

            // Format email subject and message
            $subject = hesk_getEmailSubject($email_template, '', 0);
            list($message, $html_message) = hesk_getEmailMessage($email_template, $admin, 0, 0, 1);

            // Replace message special tags
            list($message, $html_message) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $message, $html_message);
            list($message, $html_message) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $message, $html_message);
            list($message, $html_message) = hesk_replace_email_tag('%%VERIFICATION_URL%%', $verification_url, $message, $html_message);
            list($message, $html_message) = hesk_replace_email_tag('%%NUM%%', $num, $message, $html_message);

            /* Add email to the recipients list */
            $recipients[] = $admin['email'];

            /* Remember the last processed language */
            $current_language = $admin['language'];
        }
    }

    /* Send email messages to the remaining staff */
    hesk_mail(implode(',', $recipients), [], $subject, $message, $html_message);

    /* Reset language to original one */
    hesk_resetLanguage();

    return true;

} // END hesk_notifyStaffOfPendingApprovals()


function hesk_sendOverdueTicketReminder($ticket, $users)
{
    if (defined('HESK_DEMO')) {
        return true;
    }

    // --> If ticket is assigned, notify the owner plus collaborators
    if ($ticket['owner']) {
        hesk_notifyAssignedStaff(false, 'overdue_ticket', 'notify_overdue_my', 'notify_collaborator_overdue');
    }
    // --> No owner assigned, find and notify appropriate staff, including collaborators
    elseif ($ticket['collaborators']) {
        hesk_notifyStaff('overdue_ticket',"`notify_overdue_my`='1' OR (`notify_collaborator_overdue`='1' AND `id` IN (".implode(",", $ticket['collaborators'])."))");
    }
    // --> No owner assigned, find and notify appropriate staff, no collaborators
    else {
        hesk_notifyStaff('overdue_ticket',"`notify_overdue_my`='1'");
    }

    return true;
}

function hesk_sendCustomerRegistrationEmail($user, $verification_token, $email_template = 'customer_verify_registration') {
    global $hesklang, $hesk_settings;

    if (defined('HESK_DEMO')) {
        return true;
    }

    hesk_setLanguage($user['language']);

    // Format email subject and message
    $subject = hesk_getEmailSubject($email_template, '', 0);
    list($message, $html_message) = hesk_getEmailMessage($email_template,
        $user,
        0,
        0,
        1);

    // Replace message special tags
    $verification_url = $hesk_settings['hesk_url'] . '/verify_registration.php?email='.urlencode($user['email']).'&verificationToken='.urlencode($verification_token);
    list($message, $html_message) = hesk_replace_email_tag('%%NAME%%', hesk_full_name_to_first_name(hesk_msgToPlain($user['name'], 1, 1)), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%VERIFICATION_URL%%', $verification_url, $message, $html_message);

    // Send email
    hesk_mail($user['email'], [], $subject, $message, $html_message);

    // Reset language if needed
    hesk_resetLanguage();
}

function hesk_sendCustomerRegistrationApprovedEmail($user) {
    global $hesklang, $hesk_settings;

    if (defined('HESK_DEMO')) {
        return true;
    }

    hesk_setLanguage($user['language']);

    // Format email subject and message
    $subject = hesk_getEmailSubject('customer_approved', '', 0);
    list($message, $html_message) = hesk_getEmailMessage('customer_approved',
        $user,
        0,
        0,
        1);

    // Replace message special tags
    list($message, $html_message) = hesk_replace_email_tag('%%NAME%%', hesk_full_name_to_first_name(hesk_msgToPlain($user['name'], 1, 1)), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%CUSTOMER_LOGIN_URL%%', $hesk_settings['hesk_url'] . '/login.php', $message, $html_message);

    // Send email
    hesk_mail($user['email'], [], $subject, $message, $html_message);

    // Reset language if needed
    hesk_resetLanguage();
}

function hesk_sendCustomerRegistrationRejectedEmail($user) {
    global $hesklang, $hesk_settings;

    if (defined('HESK_DEMO')) {
        return true;
    }

    hesk_setLanguage($user['language']);

    // Format email subject and message
    $subject = hesk_getEmailSubject('customer_rejected', '', 0);
    list($message, $html_message) = hesk_getEmailMessage('customer_rejected',
        $user,
        0,
        0,
        1);

    // Replace message special tags
    list($message, $html_message) = hesk_replace_email_tag('%%NAME%%', hesk_full_name_to_first_name(hesk_msgToPlain($user['name'], 1, 1)), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $message, $html_message);

    // Send email
    hesk_mail($user['email'], [], $subject, $message, $html_message);

    // Reset language if needed
    hesk_resetLanguage();
}

function hesk_sendCustomerTicket2EmailFailure($email_info, $template) {
    global $hesklang, $hesk_settings;

    if (defined('HESK_DEMO')) {
        return true;
    }

    // Format email subject and message
    $subject = hesk_getEmailSubject($template, '', 0);
    list($message, $html_message) = hesk_getEmailMessage($template, null, 0, 0, 1);

    // Replace message special tags
    list($message, $html_message) = hesk_replace_email_tag('%%NAME%%', hesk_full_name_to_first_name(hesk_msgToPlain($email_info['name'], 1, 1)), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SUBJECT%%', hesk_msgToPlain($email_info['subject'], 1, 1), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%CUSTOMER_REGISTER_URL%%', $hesk_settings['hesk_url'] . '/register.php', $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1, 0), $message, $html_message);
    list($message, $html_message) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $message, $html_message);

    // Send email
    hesk_mail($email_info['email'], [], $subject, $message, $html_message);

    // Reset language if needed
    hesk_resetLanguage();
}

function hesk_getCustomerEmailFilenames()
{
    return [
        'forgot_ticket_id',
        'new_reply_by_staff',
        'new_ticket',
        'new_ticket_by_staff',
        'ticket_closed',
        'survey',
        'customer_reset_password',
        'customer_verify_registration',
        'customer_verify_new_email',
        'customer_approved',
        'customer_rejected',
        'email_rejected_can_self_register',
        'email_rejected_cannot_self_register'
    ];
} // END hesk_getCustomerEmails()


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

        // --> Customer password reset email
        'customer_reset_password' => $hesklang['customer_reset_password'],

        // --> Customer Registration email
        'customer_verify_registration' => $hesklang['customer_verify_registration'],

        // --> Customer change email
        'customer_verify_new_email' => $hesklang['customer_verify_new_email'],

        // --> Customer registration approved
        'customer_approved' => $hesklang['customer_approved'],

        // --> Customer registration rejected
        'customer_rejected' => $hesklang['customer_rejected'],

        // --> Email-to-ticket rejection emails
        'email_rejected_can_self_register' => $hesklang['email_rejected'],
        'email_rejected_cannot_self_register' => $hesklang['email_rejected'],

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

        // --> Staff notification of escalated ticket
        'ticket_escalated' => $hesklang['ticket_escalated'],

        // --> Email notifying of new customer accounts pending approval
        'new_customer_approval' => $hesklang['new_customer_approval'],

        // --> Added as a collaborator
        'collaborator_added' => $hesklang['collaborator_added'],

        // --> Staff replied to a collaborator ticket
        'collaborator_staff_reply' => $hesklang['collaborator_staff_reply'],

        // --> New note in a collaborator ticket
        'collaborator_note' => $hesklang['collaborator_note'],

        // --> A collaborator ticket is resolved
        'collaborator_resolved' => $hesklang['collaborator_resolved'],

        // --> A collaborator ticket is overdue
        'collaborator_overdue' => $hesklang['collaborator_overdue'],

        /*** Emails sent to STAFF or CUSTOMERS ***/

        // --> MFA Verification email
        'mfa_verification' => $hesklang['mfa_verification']

    );
} // END hesk_validEmails()


function hesk_mail($to, $cc, $subject, $message, $html_message, $tracking_ID = null, $mail_direct_attachment = [])
{
    global $hesk_settings, $hesklang;

    // Demo mode
    if (defined('HESK_DEMO')) {
        return true;
    }

    // usleep(100);

    // Let's check our recipients
    if (empty($to)) {
         if (empty($cc)) {
            // No recipients at all
            return true;
         } else {
            // If we don't have a To, let's use the first Cc as To; spam filters don't like empty To headers.
            $to = array_shift($cc);
         }
    }

    // Empty CCs?
    if ($cc === null) {
        $cc = [];
    }

    // Stop if we find anything suspicious in the headers
    $ccstring = implode('',$cc);
    if (preg_match("/\n|\r|\t|%0A|%0D|%08|%09/", $to . $ccstring . $subject)) {
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
    if ($to !== null && $to !== '') {
        $to_arr = array_unique(explode(',', $to));
        $to_arr = array_values($to_arr);
    } else {
        $to_arr = [];
    }
    $cc_arr = array_unique($cc);

    // Check the number of email recipients
    if ($hesk_settings['email_max_recipients']) {
        $to_num = count($to_arr);
        if ($to_num >= $hesk_settings['email_max_recipients']) {
            $to_arr = array_splice($to_arr, 0, $hesk_settings['email_max_recipients']);
            $cc_arr = [];
        } elseif (($to_num + count($cc_arr)) > $hesk_settings['email_max_recipients']) {
            $cc_arr = array_splice($cc_arr, 0, $hesk_settings['email_max_recipients'] - $to_num);
        }
    }

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
        foreach ($cc_arr as $address) {
            $mailer->addCC($address);
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
        // Attachments
        if(!empty($mail_direct_attachment)){
            foreach ($mail_direct_attachment as $a => $b) {
                if (file_exists($b)) {
                    $mailer->addAttachment($b); //Put real path of attachments
                }
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
    $ticket['priority'] = hesk_get_priority_name($ticket['priority']);

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

    $hesk_settings['e_param'] = $hesk_settings['email_view_ticket'] ? '&e=' . rawurlencode($ticket['email']) : '';

    /* Generate the ticket URLs */
    $trackingURL = $hesk_settings['hesk_url'];
    $trackingURL .= $is_admin ? '/' . $hesk_settings['admin_dir'] . '/admin_ticket.php' : '/ticket.php';
    $trackingURL .= '?track=' . $ticket['trackid'] . ($is_admin ? '' : $hesk_settings['e_param']) . '&Refresh=' . rand(10000, 99999);

    /* Set category title */
    $ticket['category'] = hesk_msgToPlain(hesk_getCategoryName($ticket['category']), 1, 0);

    /* Set priority title */
    $ticket['priority'] = hesk_get_priority_name($ticket['priority']);

    /* Get owner name */
    $ticket['owner'] = hesk_msgToPlain(hesk_getOwnerName($ticket['owner']), 1, 0);

    /* Set status */
    $ticket['status'] = hesk_get_status_name($ticket['status']);

    // Get name of the person who posted the last message
    if (!isset($ticket['last_reply_by'])) {
        $ticket['last_reply_by'] = hesk_getReplierName($ticket);
    }

    /* Replace all special tags */
    if (!function_exists('hesk_get_customers_for_ticket')) {
        require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
    }

    if (!isset($ticket['customers'])) {
        // Apparently the caller didn't get the customers for us :(
        $ticket['customers'] = hesk_get_customers_for_ticket($ticket['id']);
    }
    $customer_info = hesk_getCustomersByRequesterAndFollowers($ticket['customers']);
    $customer_info = hesk_ticketToPlain($customer_info, 1, 0);

    list($msg, $html_msg) = hesk_replace_email_tag('%%REQUESTER%%', hesk_output_customer_name_and_email($customer_info['requester'], false), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%REQUESTER_NAME%%', $customer_info['requester']['name'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%REQUESTER_FIRST_NAME%%', hesk_full_name_to_first_name($customer_info['requester']['name']), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%REQUESTER_EMAIL%%', $customer_info['requester']['email'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FOLLOWERS%%', hesk_emailFormatFollowers($customer_info['followers'], 'BOTH'), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FOLLOWER_NAMES%%', hesk_emailFormatFollowers($customer_info['followers'], 'NAMES'), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FOLLOWER_EMAILS%%', hesk_emailFormatFollowers($customer_info['followers'], 'EMAILS'), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SUBJECT%%', $ticket['subject'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_ID%%', $ticket['trackid'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TRACK_URL%%', $trackingURL . ' ', $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%', $site_title, $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'] . ' ', $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%CATEGORY%%', $ticket['category'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%PRIORITY%%', $ticket['priority'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%OWNER%%', $ticket['owner'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%STATUS%%', $ticket['status'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%CREATED%%', $ticket['dt'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%UPDATED%%', $ticket['lastchange'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%DUE_DATE%%', $ticket['due_date'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%ID%%', $ticket['id'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%TIME_WORKED%%', $ticket['time_worked'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%LAST_REPLY_BY%%', $ticket['last_reply_by'], $msg, $html_msg);

    // These fields are deprecated, but keeping them for backwards compatibility
    // %%NAME%% can be set to Staff name is some cases
    if (in_array($orig_eml_file, array('new_note', 'collaborator_staff_reply'))) {
        list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $_SESSION['name'], $msg, $html_msg);
    } else {
        list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $customer_info['requester']['name'], $msg, $html_msg);
    }
    list($msg, $html_msg) = hesk_replace_email_tag('%%FIRST_NAME%%', hesk_full_name_to_first_name($customer_info['requester']['name']), $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%EMAIL%%', $customer_info['requester']['email'], $msg, $html_msg);

    if (isset($ticket['ESCALATED_BY_RULE'])) {
        list($msg, $html_msg) = hesk_replace_email_tag('%%ESCALATED_BY_RULE%%', $ticket['ESCALATED_BY_RULE'], $msg, $html_msg);
    }

    /* All custom fields */
    for ($i = 1; $i <= 100; $i++) {
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
    $mail_direct_attachment = [];

    // Is message tag in email template?
    if (strpos($msg, '%%MESSAGE%%') !== false || strpos($html_msg, '%%MESSAGE%%') !== false) {
        if ($hesk_settings['attachments']['use'] && isset($ticket['attachments']) && strlen($ticket['attachments'])) {
            $att = explode(',', substr($ticket['attachments'], 0, -1));
            $is_customer_email = in_array(basename($eml_file, '.txt'), hesk_getCustomerEmailFilenames());
            
            if ($hesk_settings['attachments']['attachment_in_email_type'] == '1') {
                //get all attachment id,name and size

                $att_ids = array();
                foreach ($att as $myatt) {
                    list($att_id, $att_name) = explode('#', $myatt);
                    $att_ids[] = intval($att_id);
                }

                $result = hesk_dbQuery("SELECT `att_id`,`saved_name`, `size`,`real_name` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "attachments` WHERE `att_id` IN (".implode(',', $att_ids).")");
                $get_attachment_name_size = [];
                while ($get_all_attachment = hesk_dbFetchAssoc($result)) {
                    $get_attachment_name_size[] = [
                        'att_id' => $get_all_attachment['att_id'],
                        'saved_name' => $get_all_attachment['saved_name'],
                        'att_name' => $get_all_attachment['real_name'],
                        'size' => $get_all_attachment['size']
                    ];
                }

                $d = 1;
                foreach ($get_attachment_name_size as $x => $y) {

                    $directory = $hesk_settings['attach_dir']; // get attachment directory;
                    $r_path = dirname( __FILE__, 2);
                    if ($hesk_settings['attachments']['direct_attachment_in_email'] == "0" &&  $y['size'] < $hesk_settings['attachments']['file_max_size'] && $hesk_settings['attachments']['direct_attachment_in_email_no_of_files'] == '3') {
                        $mail_direct_attachment[] = $r_path.'/'.$directory.'/'.$y['saved_name'];
                    } elseif ($hesk_settings['attachments']['direct_attachment_in_email'] == "0" && $y['size'] < $hesk_settings['attachments']['file_max_size'] && $hesk_settings['attachments']['direct_attachment_in_email_no_of_files'] == '2' && $d <= $hesk_settings['attachments']['first_x_attachments']){
                        $mail_direct_attachment[] = $r_path.'/'.$directory.'/'.$y['saved_name'];
                        $d = $d + 1;
                    } elseif ($hesk_settings['attachments']['direct_attachment_in_email'] == "1" && $hesk_settings['attachments']['direct_attachment_in_email_no_of_files'] == '2' && $d <= $hesk_settings['attachments']['first_x_attachments']){
                        $mail_direct_attachment[] = $r_path.'/'.$directory.'/'.$y['saved_name'];
                        $d = $d + 1;
                    } elseif ($hesk_settings['attachments']['direct_attachment_in_email'] == "1" && $hesk_settings['attachments']['direct_attachment_in_email_no_of_files'] == '3'){
                        $mail_direct_attachment[] = $r_path.'/'.$directory.'/'.$y['saved_name'];
                    } else {
                        $att_id = $y['att_id'];
                        $att_name = $y['att_name'];
                        $url_path = $is_customer_email ? '' : '/admin';
                        $att_links[] = $att_name . "\n" . $hesk_settings['hesk_url'] . $url_path . '/download_attachment.php?att_id=' . $att_id . '&track=' . $ticket['trackid'] . $hesk_settings['e_param'];
                        $html_att_links[] = '<a href="'.$hesk_settings['hesk_url'] . $url_path . '/download_attachment.php?att_id=' . $att_id . '&amp;track=' . $ticket['trackid'] . str_replace('&', '&amp;', $hesk_settings['e_param']).'">'.$att_name.'</a>';
                    }
                }
                
            } else {
                //Direct Link to attchments in emails
                foreach ($att as $myatt) {
                    list($att_id, $att_name) = explode('#', $myatt);
                    $url_path = $is_customer_email ? '' : '/admin';
                    $att_links[] = $att_name . "\n" . $hesk_settings['hesk_url'] . $url_path . '/download_attachment.php?att_id=' . $att_id . '&track=' . $ticket['trackid'] . $hesk_settings['e_param'];
                    $html_att_links[] = '<a href="'.$hesk_settings['hesk_url'] . $url_path . '/download_attachment.php?att_id=' . $att_id . '&amp;track=' . $ticket['trackid'] . str_replace('&', '&amp;', $hesk_settings['e_param']).'">'.$att_name.'</a>';
                }
                //Direct Link to attchments in emails
            }
        }
        //Show text based on email link and direct attachment
        $fatt = $hesklang['fatt'];
        if (!empty($att_links) && !empty($mail_direct_attachment)) {
            $fatt = $hesklang['fatt_2'];
        }

        $att_links = implode(" \n\n", $att_links);
        $html_att_links = implode("<br/>", $html_att_links);

        $msg = str_replace('%%ATTACHMENTS%%', $att_links, $msg, $count_plain);
        $html_msg = str_replace('%%ATTACHMENTS%%', $html_att_links, $html_msg, $count_html);

        // Is message tag in email template?
        if (strpos($msg, '%%MESSAGE%%') !== false || strpos($html_msg, '%%MESSAGE%%') !== false) {
            // If there are attachments to this email and the %%ATTACHMENTS%% tag was not present, add links to attachments below the message
            if ($hesk_settings['attachments']['use'] && isset($ticket['attachments']) && strlen($ticket['attachments'])) {
                if ($count_plain == 0 && $att_links !=='') {
                    $ticket['message'] .= "\n\n" . $fatt . "\n\n" . $att_links;
                }
                if ($count_html == 0  && $att_links !=='') {
                    $ticket['message_html'] .= "<br/><br/>" . $fatt . "<br/>" .  $html_att_links;
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
    }
    return array($msg, $html_msg, $mail_direct_attachment);

} // END hesk_getEmailMessage

function hesk_getCustomersByRequesterAndFollowers($customers) {
    $requester = null;
    $followers = [];
    foreach ($customers as $customer) {
        if ($customer['customer_type'] === 'REQUESTER') {
            $requester = $customer;
        } else {
            $followers[] = $customer;
        }
    }

    if ( ! is_array($requester)) {
        $requester['name'] = '';
        $requester['email'] = '';
    }

    return [
        'requester' => $requester,
        'followers' => $followers
    ];
}

function hesk_emailFormatFollowers($followers, $format) {
    $output = [];
    foreach ($followers as $follower) {
        if ($format === 'BOTH') {
            $output[] = hesk_output_customer_name_and_email($follower, false);
        } elseif ($format === 'NAMES' && $follower['name'] !== null && $follower['name'] !== '') {
            $output[] = $follower['name'];
        } elseif ($format === 'EMAILS' && $follower['email'] !== null && $follower['email'] !== '') {
            $output[] = $follower['email'];
        }
    }

    return implode(', ', $output);
}

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
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">You are now part of the Hesk family, and we want to serve you better!</h2>\r\n\r\n<h3>&raquo; Rate us</h3>\r\n\r\n<p>Positive ratings and reviews motivate us to continue developing Hesk. Please take a moment to:</p>\r\n\r\n<ul>\r\n<li>rate or review Hesk at <a href=\"https://softaculous.com/rate/HESK\" rel=\"nofollow\">Softaculous</a></li>\r\n<li>rate or review Hesk at <a href=\"https://alternativeto.net/software/hesk/about/\" rel=\"nofollow\">AlternativeTo</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://x.com/HESKdotCOM\" rel=\"nofollow\">follow Hesk on <b>X</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume, zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Look professional</h3>\r\n\r\n<p><a href=\"https://www.hesk.com/get/hesk3-license\">Remove &quot;Powered by&quot; links</a> to support Hesk development and make it look more professional.</p>\r\n\r\n&nbsp;\r\n\r\n<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
            break;
        case 1000:
            $subject = "We're excited about your 1,000th ticket!";
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">With 1,000 support tickets under the hood, you\'ve become a Hesk Power User. Congratulations!</h2>\r\n\r\n<h3>&raquo; Rate us</h3>\r\n\r\n<p>Positive ratings and reviews motivate us to continue developing Hesk. Please take a moment to:</p>\r\n\r\n<ul>\r\n<li>rate or review Hesk at <a href=\"https://softaculous.com/rate/HESK\" rel=\"nofollow\">Softaculous</a></li>\r\n<li>rate or review Hesk at <a href=\"https://alternativeto.net/software/hesk/about/\" rel=\"nofollow\">AlternativeTo</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://x.com/HESKdotCOM\" rel=\"nofollow\">follow Hesk on <b>X</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume, zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Look professional</h3>\r\n\r\n<p><a href=\"https://www.hesk.com/get/hesk3-license\">Remove &quot;Powered by&quot; links</a> to support Hesk development and make it look more professional.</p>\r\n\r\n&nbsp;\r\n\r\n<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
            break;
        case 10000:
            $subject = "Wow, you've reached 10,000 tickets!";
            $message = "</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\r\n\r\n<h2 style=\"padding-left:0px\">You received 10,000 support tickets, outstanding! You are officially a Hesk Hero!</h2>\r\n\r\n<h3>&raquo; Rate us</h3>\r\n\r\n<p>Positive ratings and reviews motivate us to continue developing Hesk. Please take a moment to:</p>\r\n\r\n<ul>\r\n<li>rate or review Hesk at <a href=\"https://softaculous.com/rate/HESK\" rel=\"nofollow\">Softaculous</a></li>\r\n<li>rate or review Hesk at <a href=\"https://alternativeto.net/software/hesk/about/\" rel=\"nofollow\">AlternativeTo</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Stay updated</h3>\r\n\r\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\r\n<ul>\r\n<li>for fast notifications, <a href=\"https://x.com/HESKdotCOM\" rel=\"nofollow\">follow Hesk on <b>X</b></a></li>\r\n<li>for email notifications, subscribe to our low-volume, zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\r\n</ul>\r\n\r\n<h3>&raquo; Look professional</h3>\r\n\r\n<p><a href=\"https://www.hesk.com/get/hesk3-license\">Remove &quot;Powered by&quot; links</a> to support Hesk development and make it look more professional.</p>\r\n\r\n&nbsp;\r\n\r\n<h3>&raquo; Upgrade to Hesk Cloud for the ultimate experience</h3>\r\n\r\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\r\n<ul>\r\n<li>exclusive advanced modules,</li>\r\n<li>automated updates,</li>\r\n<li>free migration of your existing Hesk tickets and settings,</li>\r\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\r\n</ul>\r\n\r\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n<p>Best regards,</p>\r\n\r\n<p>Klemen Stirn<br>\r\nFounder<br>\r\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\r\n\r\n<p>&nbsp;</p>\r\n\r\n</div><p>";
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
    hesk_mail($row['email'], [], $subject, $message, $html_message);

    return true;

} // END hesk_PMtoMainAdmin()
