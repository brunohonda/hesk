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
define('HESK_PATH','../../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
$hesk_settings['db_failure_response'] = 'json';
hesk_isLoggedIn();

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || ! isset($_POST['action'])) {
    hesk_json_exit('Error', 'Invalid request');
}

$action = hesk_POST('action');

$tag = hesk_POST('tag', '');
$tag = preg_replace('/[^a-zA-Z0-9\-]/', '', $tag);

if (strlen($tag) == 0) {
    hesk_json_exit('Error', 'No tag');
}

$lang_path = HESK_PATH . 'language/';
$dir_path = $lang_path . $tag;
$zip_path = $dir_path . '.zip';
$upgrade_path = $dir_path.'_old';

// Remove a language folder
if ($action == 'remove') {
    hesk_rrmdir($dir_path);
    if (is_dir($dir_path)) {
        hesk_json_exit('Error', 'Folder still exists');
    }
    hesk_unlink($zip_path);
    hesk_rrmdir($upgrade_path);
    hesk_purge_cache();
    hesk_json_exit('Success');
}

// Handle installing or updating a language

$version = hesk_POST('version', '');
$version = preg_replace('/[^a-zA-Z0-9\.]/', '', $version);

if (strlen($version) == 0) {
    hesk_json_exit('Error', 'No version');
}

try {
    // Let's do some cleanup first in case there are files/folders from previous installs
    hesk_unlink($zip_path);
    hesk_rrmdir($upgrade_path);

    // Here is where we will download the languge pack from
    $download_url = 'https://www.hesk.com/language/download.php?tag='.urlencode($tag).'&version='.urlencode($version);

    // Try using cURL
    if ( function_exists('curl_init') ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        $zip_data = curl_exec($ch);
        curl_close($ch);
    }

    // Try using a simple PHP function instead
    if (empty($zip_data)) {
        $zip_data = @file_get_contents($download_url);
    }

    // Unsuccessful download
    if (empty($zip_data)) {
        hesk_json_exit('Error', 'No zip data');
    }

    // Save the zip file and check that it exists
    file_put_contents($zip_path, $zip_data);
    if ( ! file_exists($zip_path)) {
        hesk_json_exit('Error', 'Cannot save zip file');
    }

    // We need to preserve old data for upgrades
    if ($action == 'upgrade') {
        rename($dir_path, $upgrade_path);
        if ( ! is_dir($upgrade_path)) {
            hesk_json_exit('Error', 'Cannot backup old files');
        }
    }

    hesk_extractZip($zip_path, $lang_path);
    hesk_unlink($zip_path);

    if ( ! file_exists($dir_path . '/text.php')) {
        if ($action == 'upgrade') {
            hesk_rrmdir($dir_path);
            rename($upgrade_path, $dir_path);
        }
        hesk_json_exit('Error', 'text.php missing');
    }

    // Copy modified data for upgrades
    if ($action == 'upgrade') {
        // Copy custom-text.php
        if (file_exists($upgrade_path . '/custom-text.php')) {
            rename($upgrade_path . '/custom-text.php', $dir_path . '/custom-text.php');
        }
        // Copy plain text and html email templates in case they were modified
        $emails = array_diff(scandir($upgrade_path . '/emails/'), array('.','..','index.htm'));
        foreach ($emails as $email) {
            hesk_unlink($dir_path . '/emails/' . $email);
            rename($upgrade_path . '/emails/' . $email, $dir_path . '/emails/' . $email);
        }
        $emails = array_diff(scandir($upgrade_path . '/html_emails/'), array('.','..','index.htm'));
        foreach ($emails as $email) {
            hesk_unlink($dir_path . '/html_emails/' . $email);
            rename($upgrade_path . '/html_emails/' . $email, $dir_path . '/html_emails/' . $email);
        }
        // Remove the backup
        hesk_rrmdir($upgrade_path);
    }

    hesk_purge_cache();
    hesk_json_exit('Success');

} catch (Exception $e) {
    if ($hesk_settings['debug_mode']) {
        hesk_json_exit('Error', 'Exception: ' . var_export($e));
    } else {
        hesk_json_exit('Error', 'Exception');
    }
}

hesk_json_exit('Error', 'Invalid action');


function hesk_extractZip($zip_file, $destination_dir) {

    if ( ! is_dir($destination_dir)) {
        @mkdir($destination_dir, 0777, true);
    }

    if ( ! is_writable($destination_dir)) {
        @chmod($destination_dir, 0777);
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === true) {
            $zip->extractTo($destination_dir);
            $zip->close();
            return true;
        }
    } else {
        require(HESK_PATH . 'inc/zip/pclzip.lib.php');
        $zip = new PclZip($zip_file);
        $result = $zip->extract(PCLZIP_OPT_PATH, $destination_dir);
        return true;
    }

    hesk_json_exit('Error', 'Cannot unzip');
} // END hesk_extractZip()

