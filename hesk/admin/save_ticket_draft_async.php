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
require(HESK_PATH . 'inc/email_functions.inc.php');
require(HESK_PATH . 'inc/posting_functions.inc.php');

// We only allow POST requests from the HESK form to this file
if ( $_SERVER['REQUEST_METHOD'] != 'POST' )
{
    http_response_code(400);
    exit();
}

// Check for POST requests larger than what the server can handle
if ( empty($_POST) && ! empty($_SERVER['CONTENT_LENGTH']) )
{
    http_response_code(400);
    exit();
}

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_reply_tickets');

// A security check
# hesk_token_check('POST');

// Original ticket ID
$ticket['id'] = intval( hesk_POST('orig_id', 0) ) or die($hesklang['int_error']);

// Get existing draft ID
$result = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".intval($_SESSION['id'])." AND `ticket`=".intval($ticket['id']));
if (hesk_dbNumRows($result) == 1) {
    $draft_id = hesk_dbResult($result);
} else {
    $draft_id = 0;
}

// Get the message
$message = hesk_input(hesk_POST('message'));

if (strlen($message))
{
    $message_html = $message;

    // Handle rich-text tickets
    if ($hesk_settings['staff_ticket_formatting'] == 2) {
        // Decode the message we encoded earlier
        $message_html = hesk_html_entity_decode($message_html);

        // Clean the HTML code and set the plaintext version
        require(HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php');
        require(HESK_PATH . 'inc/html2text/html2text.php');
        $purifier = new HeskHTMLPurifier($hesk_settings['cache_dir']);
        $message_html = $purifier->heskPurify($message_html);

        $message = convert_html_to_text($message_html);
        $message = fix_newlines($message);

        // Prepare plain message for storage as HTML
        $message = hesk_htmlspecialchars($message);
        // nl2br done after adding signature
    } elseif ($hesk_settings['staff_ticket_formatting'] == 0) {
        $message_html = hesk_makeURL($message_html);
        $message_html = nl2br($message_html);
    }

    if ($draft_id) {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` SET `message`='".hesk_dbEscape($message)."', `message_html`='".hesk_dbEscape($message_html)."' WHERE `id`=".intval($draft_id));
        echo "Draft updated";
    } else {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` (`owner`, `ticket`, `message`, `message_html`) VALUES (".intval($_SESSION['id']).", ".intval($ticket['id']).", '".hesk_dbEscape($message)."', '".hesk_dbEscape($message_html)."')");
        echo "Draft saved";
    }
} elseif ($draft_id > 0) {
    // Delete any existing drafts from this owner for this ticket
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".intval($_SESSION['id'])." AND `ticket`=".intval($ticket['id']));
    echo "Draft deleted";
} else {
    echo "No message";
}

exit();
