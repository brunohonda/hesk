<?php
require_once(HESK_PATH . 'inc/attachments.inc.php');

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

function hesk3_output_drag_and_drop_attachment_holder($id = 'filedrop') {
    global $hesk_settings;

    if ($hesk_settings['attachments']['use']) {
        build_dropzone_markup(false, $id, 1, false);
    }
}

function hesk3_output_drag_and_drop_script($failed_attachments_key, $id = 'filedrop') {
    global $hesk_settings;

    if ($hesk_settings['attachments']['use']) {
        display_dropzone_field(HESK_PATH . 'upload_attachment.php', false, $id);

        dropzone_display_existing_files(hesk_SESSION_array($failed_attachments_key), $id);
        hesk_cleanSessionVars($failed_attachments_key);
    }
}