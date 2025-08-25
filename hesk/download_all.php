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
define('HESK_PATH','./');

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
hesk_load_database_functions();

// Page used by staff only
hesk_session_start();

// Are we in maintenance mode? (check customers only)
if ( empty($_SESSION['id']) )
{
    hesk_check_maintenance();
}

// This function requires the ZipArchive class
if ( ! class_exists('ZipArchive')) {
    hesk_error($hesklang['download_class']);
}

// Attachmend ID and ticket tracking ID
$att_id = hesk_GET('att_id') or die($hesklang['id_not_valid']);
$tic_id = hesk_cleanID() or die("$hesklang[int_error]: $hesklang[no_trackID]");

$att_ids = explode(',', preg_replace('/[^0-9,]/', '', $att_id));
$att_ids = array_filter($att_ids);

// Too many attachments...
if (count($att_ids) > 10) {
    hesk_error($hesklang['download_tma']);
} elseif (count($att_ids) < 1) {
    hesk_error($hesklang['download_nva']);
}

$files_to_download = array();

// Connect to database
hesk_dbConnect();

// Get attachment info
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` IN (".implode(',', $att_ids).")");
if (hesk_dbNumRows($res) != count($att_ids)) {
    hesk_error($hesklang['id_not_valid'].' (att_id)');
}

while ($file = hesk_dbFetchAssoc($res))
{
    // Is ticket ID valid for this attachment?
    if ($file['ticket_id'] != $tic_id) {
        hesk_error($hesklang['trackID_not_found']);
    }

    // Verify email address match if needed
    if ( empty($_SESSION['id']) ) {
        hesk_verifyEmailMatch($tic_id);

        // Only staff may download attachments to notes
        if ($file['type']) {
            hesk_error($hesklang['perm_deny']);
        }
    }

    // Path of the file on the server
    $realpath = $hesk_settings['attach_dir'] . '/' . $file['saved_name'];

    // Perhaps the file has been deleted?
    if ( ! file_exists($realpath)) {
        hesk_error($hesklang['attdel']);
    }

    $files_to_download[$file['real_name']] = $realpath;
}

if ( ! count($files_to_download)) {
    die($hesklang['download_ntd']);
}

$zip_name = $tic_id . '_' . implode('-', $att_ids) . '.zip';
$export_dir = HESK_PATH . $hesk_settings['cache_dir'] . '/';
$save_to_zip = $export_dir . $zip_name;
$zip_full_path = dirname(__FILE__) . '/' . $hesk_settings['cache_dir'] . '/' . $zip_name;

register_shutdown_function('unlink', $zip_full_path);

$zip = new ZipArchive;
$res = $zip->open($save_to_zip, ZipArchive::CREATE);
if ($res === TRUE) {
    foreach ($files_to_download as $name => $file) {
        $zip->addFile($file, $tic_id . '/' . $name);
    }
    $zip->close();
} else {
    die("{$hesklang['eZIP']} <$save_to_zip>\n");
}

$zip_size = filesize($save_to_zip);

// Send the file as an attachment to prevent malicious code from executing
header("Pragma: "); # To fix a bug in IE when running https
header("Cache-Control: "); # To fix a bug in IE when running https
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Length: ' . $zip_size);
header('Content-Disposition: attachment; filename=' . $zip_name);

// For larger files use chunks, smaller ones can be read all at once
$chunksize = 1048576; // = 1024 * 1024 (1 Mb)
if ($zip_size > $chunksize)
{
    $handle = fopen($save_to_zip, 'rb');
    $buffer = '';
    while ( ! feof($handle))
    {
        set_time_limit(300);
        $buffer = fread($handle, $chunksize);
        echo $buffer;
        flush();
    }
    fclose($handle);
}
else
{
    readfile($save_to_zip);
}

exit();

