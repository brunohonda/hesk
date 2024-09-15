<?php
define('IN_SCRIPT', 1);
define('HESK_PATH', './');
define('HESK_NO_ROBOTS',1);
require_once(HESK_PATH . 'hesk_settings.inc.php');
require_once(HESK_PATH . 'inc/common.inc.php');
require_once(HESK_PATH . 'inc/attachments.inc.php');
require_once(HESK_PATH . 'inc/posting_functions.inc.php');

hesk_load_database_functions();
hesk_dbConnect();

// Demo mode?
if ( defined('HESK_DEMO') )
{
    hesk_show_notice($hesklang['ddemo']);
    exit();
}
$hesk_settings['db_failure_response'] = 'json';

// Remove any expired temp attachments
hesk_removeExpiredTempAttachments();

// Check if we are deleting an attachment or if we have a file to upload
if (hesk_GET('action') === 'delete') {
    if (hesk_GET('fileKey', 'undefined') === 'undefined') {
        //-- Failed dropzone uploads will return an undefined saved name when removing them
        return http_response_code(204);
    }

    hesk_deleteTempAttachment(hesk_GET('fileKey'), true);
    return http_response_code(204);
} elseif (!empty($_FILES)) {
    $info = hesk_uploadTempFile();
    http_response_code($info['status_code']);
    print json_encode($info);
    return '';
}

return http_response_code(400);
