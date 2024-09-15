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
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

/***************************
Function hesk_uploadFiles()
***************************/
function hesk_uploadFile($i)
{
	global $hesk_settings, $hesklang, $trackingID, $hesk_error_buffer;

	/* Return if name is empty */
	if (empty($_FILES['attachment']['name'][$i])) {return '';}

    /* Parse the name */
	$file_realname = hesk_cleanFileName($_FILES['attachment']['name'][$i]);

	/* Check file extension */
	$ext = strtolower(strrchr($file_realname, "."));
	if ( ! in_array($ext,$hesk_settings['attachments']['allowed_types']))
	{
        return hesk_fileError(sprintf($hesklang['type_not_allowed'], $ext, $file_realname));
	}

	/* Check file size */
	if ($_FILES['attachment']['size'][$i] > $hesk_settings['attachments']['max_size'])
	{
	    return hesk_fileError(sprintf($hesklang['file_too_large'], $file_realname));
	}
	else
	{
	    $file_size = $_FILES['attachment']['size'][$i];
	}

	/* Generate a random file name */
    $file_name = hesk_generateAttachmentName($file_realname, $ext, $trackingID);

    // Does the temporary file exist? If not, probably server-side configuration limits have been reached
    // Uncomment this for debugging purposes
    /*
    if ( ! file_exists($_FILES['attachment']['tmp_name'][$i]) )
    {
		return hesk_fileError($hesklang['fnuscphp']);
    }
    */

	/* If upload was successful let's create the headers */
	if ( ! move_uploaded_file($_FILES['attachment']['tmp_name'][$i], dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/'.$file_name))
	{
	    return hesk_fileError($hesklang['cannot_move_tmp']);
	}

	$info = array(
	    'saved_name'=> $file_name,
	    'real_name' => $file_realname,
	    'size'      => $file_size
	);

	return $info;
} // End hesk_uploadFile()

function hesk_generateAttachmentName($file_realname, $ext, $tracking_id = '') {
    /* Generate a random file name */
    $useChars='AEUYBDGHJLMNPQRSTVWXZ123456789';
    $tmp = uniqid();
    for($j=1;$j<10;$j++) {
        $tmp .= $useChars[mt_rand(0,29)];
    }

    if (defined('KB') || $tracking_id === '') {
        return substr(md5($tmp . $file_realname), 0, 200) . $ext;
    }

    return substr($tracking_id . '_' . md5($tmp . $file_realname), 0, 200) . $ext;
}

function hesk_uploadTempFile() {
    global $hesk_settings, $hesklang;

    /* Return if name is empty */
    if (empty($_FILES['attachment']['name'])) {
        return null;
    }

    /* Parse the name */
    $file_realname = hesk_cleanFileName($_FILES['attachment']['name']);

    /* Check file extension */
    $ext = strtolower(strrchr($file_realname, "."));
    if (!in_array($ext,$hesk_settings['attachments']['allowed_types'])) {
        return array(
            'status' => 'failure',
            'status_code' => 400,
            'message' => sprintf($hesklang['type_not_allowed'], $ext, $file_realname)
        );
    }

    /* Check file size */
    if ($_FILES['attachment']['size'] > $hesk_settings['attachments']['max_size']) {
        return array(
            'status' => 'failure',
            'status_code' => 400,
            'message' => sprintf($hesklang['file_too_large'], $file_realname)
        );
    } else {
        $file_size = $_FILES['attachment']['size'];
    }

    /* Check for potential attachment flooding */
    $ip = hesk_getClientIP();
    if (hesk_attachmentFloodingDetected($ip)) {
        return array(
            'status' => 'failure',
            'status_code' => 429,
            'message' => $hesklang['attachment_too_many_uploads']
        );
    }

    $file_name = hesk_generateAttachmentName($file_realname, $ext);

    // Does the temporary file exist? If not, probably server-side configuration limits have been reached
    // Uncomment this for debugging purposes
    /*
    if ( ! file_exists($_FILES['attachment']['tmp_name']) )
    {
		return hesk_fileError($hesklang['fnuscphp']);
    }
    */

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    if (!is_dir($hesk_settings['server_path'])) {
        @mkdir($hesk_settings['server_path']);
        @file_put_contents($hesk_settings['server_path'].'index.htm', '');
    }

    /* If upload was successful let's create the headers */
    ob_start();
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $hesk_settings['server_path'].$file_name))
    {
        ob_end_clean();
        return array(
            'status' => 'failure',
            'status_code' => 500,
            'message' => $hesklang['error'] . ': ' . $hesklang['cannot_move_tmp']
        );
    }
    ob_end_clean();

    // Generate a random ID to use when deleting temporary attachments
    $unique_id = uniqid(rand(), true);

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` (`saved_name`, `unique_id`, `real_name`, `expires_at`, `size`)
    VALUES ('".hesk_dbEscape($file_name)."', '".hesk_dbEscape($unique_id)."', '".hesk_dbEscape($file_realname)."', NOW() + INTERVAL 3 HOUR, ".intval($file_size).")");

    // Increment limits used for IP
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` (`ip`,`upload_count`)
        VALUES ('".hesk_dbEscape(hesk_getClientIP())."', 1) ON DUPLICATE KEY UPDATE `upload_count` = `upload_count` + 1");

    $info = array(
        'status' => 'success',
        'status_code' => 200,
        'file_key'=> $unique_id
    );

    return $info;
} // End hesk_uploadTempFile

function hesk_attachmentFloodingDetected($ip) {
    global $hesk_settings;

    // Reset counters for any IPs that haven't uploaded in an hour
    hesk_dbEscape("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `last_upload_at` < (NOW() - INTERVAL 1 HOUR)");

    $res = hesk_dbQuery("SELECT `upload_count` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `ip` = '".hesk_dbEscape($ip)."'");
    if (hesk_dbNumRows($res) < 1) {
        return false;
    }
    $row = hesk_dbFetchAssoc($res);

    // Change "100" to whatever max amount is appropriate.
    return $row['upload_count'] > 100;
}

function hesk_fileError($error)
{
	global $hesk_settings, $hesklang, $trackingID;
    global $hesk_error_buffer;

	$hesk_error_buffer['attachments'] = $error;

	return false;
} // End hesk_fileError()


function hesk_removeAttachments($attachments)
{
	global $hesk_settings, $hesklang;

	$hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/';

	foreach ($attachments as $myatt)
	{
		hesk_unlink($hesk_settings['server_path'].$myatt['saved_name']);
	}

	return true;
} // End hesk_removeAttachments()

function hesk_removeExpiredTempAttachments() {
    global $hesk_settings;

    // 1. Grab temp attachments that are expired
    $res = hesk_dbQuery("SELECT `att_id`, `saved_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments`
        WHERE `expires_at` < NOW()");

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';

    while ($row = hesk_dbFetchAssoc($res)) {
        hesk_unlink($hesk_settings['server_path'].$row['saved_name']);
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` WHERE `att_id` = ".intval($row['att_id']));
    }
}

function hesk_migrateTempAttachments($attachments, $tracking_id = '') {
    global $hesk_settings;

    $moved_attachments = array();
    foreach ($attachments as $myatt) {
        hesk_deleteTempAttachment($myatt['file_key']);

        $old_name = $myatt['saved_name'];
        $myatt['saved_name'] = ($tracking_id !== '') ? "{$tracking_id}_{$old_name}" : $old_name;
        hesk_moveAttachment($old_name, $myatt['saved_name']);

        $moved_attachments[] = $myatt;
    }

    // Reset limits for the IP
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `ip` = '".hesk_dbEscape(hesk_getClientIP())."'");

    return $moved_attachments;
}

function hesk_moveAttachment($old_name, $new_name) {
    global $hesk_settings;

    $hesk_settings['temp_server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/';

    hesk_rename($hesk_settings['temp_server_path'].$old_name, $hesk_settings['server_path'].$new_name);
}

function hesk_deleteTempAttachment($file_key, $delete_file = false) {
    global $hesk_settings;

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';

    $res = hesk_dbQuery("SELECT `att_id`, `saved_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments`
        WHERE `unique_id` = '".hesk_dbEscape($file_key)."'");

    if ($row = hesk_dbFetchAssoc($res)) {
        if ($delete_file) {
            hesk_unlink($hesk_settings['server_path'].$row['saved_name']);
        }
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` WHERE `att_id` = ".intval($row['att_id']));
    }
}

function hesk_getTemporaryAttachment($file_key) {
    global $hesk_settings;

    $rs = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "temp_attachments` WHERE `unique_id` = '" . hesk_dbEscape($file_key) . "'");
    if (hesk_dbNumRows($rs) == 0) {
        return NULL;
    }
    $row = hesk_dbFetchAssoc($rs);

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    if (!file_exists($hesk_settings['server_path'].$row['saved_name'])) {
        // Not deleting the file itself because it, well, doesn't exist.
        hesk_deleteTempAttachment($file_key);
        return null;
    }

    $info = array(
        'saved_name' => $row['saved_name'],
        'real_name' => $row['real_name'],
        'size' => $row['size'],
        'file_key' => $row['unique_id']
    );

    return $info;
}

//region Dropzone
function build_dropzone_markup($admin = false, $id = 'filedrop', $startingId = 1, $show_file_limits = true) {
    global $hesklang, $hesk_settings;

    $directory_separator = $admin ? '../' : '';
    echo '<div class="dropzone dz-click-'.$id.'" id="' . $id . '">
        <div class="fallback">
            <input type="hidden" name="use-legacy-attachments" value="1">';
    for ($i = $startingId; $i <= $hesk_settings['attachments']['max_number']; $i++) {
        $cls = ($i == 1 && isset($_SESSION['iserror']) && in_array('attachments', $_SESSION['iserror'])) ? ' class="isError" ' : '';
        echo '<input type="file" name="attachment[' . $i . ']" size="50" ' . $cls . ' /><br />';
    }
    echo '</div>
    </div>
    <div class="btn btn-full fileinput-button filedropbutton-' . $id . ' dz-click-'.$id.'">' . $hesklang['attachment_add_files'] . '</div>';
    if ($show_file_limits) {
        echo '<a class="link" href="' . $directory_separator . 'file_limits.php" target="_blank"
       onclick="Javascript:hesk_window(\'' . $directory_separator . 'file_limits.php\',250,500);return false;">'. $hesklang['ful'] . '</a>';
    }

    output_attachment_id_holder_container($id);
}

function display_dropzone_field($url, $is_admin, $id = 'filedrop', $max_files_override = -1) {
    global $hesk_settings, $hesklang;

    output_dropzone_window();

    $acceptedFiles = implode(',', $hesk_settings['attachments']['allowed_types']);
    $size = hesk_bytesToUnits($hesk_settings['attachments']['max_size']);
    $max_files = $max_files_override > -1 ? $max_files_override : $hesk_settings['attachments']['max_number'];

    // Let's define this function we may need if it's not defined already
    if ( ! isset($hesk_settings['HeskWithout_defined'])) {
        echo "<script>const HeskWithout = (list, rejectedItem) => list.filter((item) => item !== rejectedItem).map((item) => item);</script>";
        $hesk_settings['HeskWithout_defined'] = true;
    }

    // Dropzone auto-discovery is being removed in v6.  As such, autodiscovery is disabled here should we want to
    // upgrade in the future.
    echo "
    <script>
    Dropzone.autoDiscover = false;
    var dropzone{$id} = new Dropzone('#{$id}', {
        paramName: 'attachment',
        url: '{$url}',
        parallelUploads: {$max_files},
        maxFiles: {$max_files},
        acceptedFiles: ".json_encode($acceptedFiles).",
        maxFilesize: {$size}, // MB
        dictDefaultMessage: ".json_encode($hesklang['attachment_viewer_message']).",
        dictFallbackMessage: '',
        dictInvalidFileType: ".json_encode($hesklang['attachment_invalid_type_message']).",
        dictResponseError: ".json_encode($hesklang['attachment_upload_error']).",
        dictFileTooBig: ".json_encode($hesklang['attachment_too_large']).",
        dictCancelUpload: ".json_encode($hesklang['attachment_cancel']).",
        dictCancelUploadConfirmation: ".json_encode($hesklang['attachment_confirm_cancel']).",
        dictRemoveFile: ".json_encode($hesklang['attachment_remove']).",
        dictMaxFilesExceeded: ".json_encode($hesklang['attachment_max_exceeded']).",
        previewTemplate: $('#previews').html(),
        clickable: '.dz-click-".$id."',
        uploadMultiple: false
    });
    
    dropzone{$id}.on('success', function(file, response) {
        var jsonResponse = JSON.parse(response);
        // console.log(JSON.stringify(jsonResponse, null, 4));

        if(jsonResponse.hasOwnProperty('status') && jsonResponse['status'] == 'failure'){
            // Upload was OK, but something failed on the server-side
            dropzone{$id}.emit('uploadprogress', file, 0);
            dropzone{$id}.files = HeskWithout(dropzone{$id}.files, file);
            dropzone{$id}.options.error(file, jsonResponse['message']);
        } else {
            // The response will only be a JSON object holding the saved and real name
            outputAttachmentIdHolder(jsonResponse['file_key'], '".$id."');

            // Add the database id to the file
            file['databaseResponse'] = jsonResponse['file_key'];
        }
    });
    dropzone{$id}.on('addedfile', function() {
        var numberOfFiles = $('#" . $id . " .file-row').length;

        var disabled = false;
        if (numberOfFiles >= " . $max_files . ") {
            disabled = true;
        }

        $('." . $id . "button-" . $id . "').attr('disabled', disabled);
    });
    dropzone{$id}.on('removedfile', function(file) {
        if (file.beingRetried) {
            return;
        }
    
        // Remove the attachment from the database and the filesystem.
        removeAttachment(".$id.", file['databaseResponse'], ".($is_admin ? "true" : "false").");

        var numberOfFiles = $('#" . $id . " .file-row').length;

        var disabled = false;
        if (numberOfFiles >= " . $max_files . ") {
            disabled = true;
        }
        $('." . $id . "button-" . $id . "').attr('disabled', disabled);
        
        dropzone{$id}.getRejectedFiles().forEach(function(file) {
            file.beingRetried = true;
            dropzone{$id}.removeFile(file);
            file.status = undefined;
            file.accepted = undefined;
            file.beingRetried = false;
            dropzone{$id}.addFile(file);
        });
    });
    dropzone{$id}.on('queuecomplete', function() {
        $('input[type=\"submit\"]').attr('disabled', false);
    });
    dropzone{$id}.on('processing', function() {
        $('input[type=\"submit\"]').attr('disabled', true);
    });
    dropzone{$id}.on('uploadprogress', function(file, percentage) {
        $(file.previewTemplate).find('#percentage').text(percentage + '%');
    });
    dropzone{$id}.on('error', function(file, message) {
        $(file.previewTemplate).addClass('alert-danger');
        
        var actualMessage = message.title + ': ' + message.message;
        if (!message.message) {
            actualMessage = message;
        }
        
        $(file.previewElement).addClass('dz-error').find('[data-dz-errormessage]').text(actualMessage);
    });
    </script>
    ";

}

function dropzone_display_existing_files($files, $dropzone_id = 'filedrop') {
    foreach ($files as $file) {
        dropzone_display_existing_file($file['real_name'], $file['size'], $file['file_key'], $dropzone_id);
    }
}

function dropzone_display_existing_file($name, $size, $file_key, $dropzone_id = 'filedrop') {
    $uniqid = uniqid();
    $successPayload = json_encode(array(
        'file_key' => $file_key
    ));
    echo "
    <script>
    var tempFile{$uniqid} = { 
        name: ".json_encode($name).", 
        size: ".$size."
    };
    tempFile{$uniqid}.accepted = true;
    tempFile{$uniqid}.status = Dropzone.SUCCESS;
    dropzone{$dropzone_id}.files.push(tempFile{$uniqid});
    dropzone{$dropzone_id}._updateMaxFilesReachedClass();
    dropzone{$dropzone_id}.emit('addedfile', tempFile{$uniqid});
    dropzone{$dropzone_id}.emit('complete', tempFile{$uniqid});
    dropzone{$dropzone_id}.emit('success', tempFile{$uniqid}, '{$successPayload}');
    dropzone{$dropzone_id}.emit('uploadprogress', tempFile{$uniqid}, 100);
    </script>
    ";
}

function output_dropzone_window() {
    echo '
    <div id="previews" style="display:none">
        <div id="template" class="file-row">
            <!-- This is used as the file preview template -->
            <div class="attachment-row">
                <div class="name-size-delete">
                    <div class="name-size">
                        <div class="name">
                            <p class="name" data-dz-name></p>
                        </div>
                        <div class="size">
                            (<span data-dz-size></span>)
                        </div>
                    </div>
                    <div class="delete-button">
                        <svg class="icon icon-delete" data-dz-remove>
                            <use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-delete"></use>
                        </svg>
                    </div>
                </div>
                <div class="upload-progress">
                    <div style="border: 1px solid #d4d6e3; width: 100%; height: 19px">
                        <div style="font-size: 1px; height: 17px; width: 0px; border: none; background-color: green" data-dz-uploadprogress>
                        </div>
                    </div>
                </div>
            </div>
            <div class="error">
                <strong class="error text-danger" data-dz-errormessage></strong>
            </div>
        </div>
    </div>';
}

function output_attachment_id_holder_container($id) {
    echo '<div id="attachment-holder-' . $id . '" class="hide"></div>';
}

//endregion
