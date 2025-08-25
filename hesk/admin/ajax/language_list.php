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

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    hesk_json_exit('Error', 'Invalid request');
}

try {
    // This URL will return a JSON of all available languages
    $get_language_json_url = "https://www.hesk.com/language/get-available-languages.php?version=".urlencode($hesk_settings['hesk_version']);

    // Try using cURL
    if ( function_exists('curl_init') ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $get_language_json_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        $langauges_json = curl_exec($ch);
        curl_close($ch);
        $available_languages = json_decode($langauges_json, true);
    }

    // Try using a simple PHP function instead
    if (empty($available_languages)) {
        $langauges_json = @file_get_contents($get_language_json_url);
        $available_languages = json_decode($langauges_json, true);
    }

    // Exit if we don't have a valid languages JSON
    if (empty($available_languages)) {
        hesk_json_exit('Error', 'No valid JSON');
    }
} catch (Exception $e) {
    if ($hesk_settings['debug_mode']) {
        hesk_json_exit('Error', 'Exception: ' . var_export($e));
    } else {
        hesk_json_exit('Error', 'Exception');
    }
}

$language_folders = scandir(HESK_PATH.'language');

$html = '';
$html .= '<div class="main__content main_language_content">';
$html .= '<div class="grid-container">';
foreach ($available_languages as $k => $v) {
    $html .= '<div>';
    $html .= '<p><span>'.$hesklang['title_lan'].': </span>'.$v["title"].'</p>';
    $html .= '<p><span>'.$hesklang['description_lan'].': </span>'.$v['description'].'</p>';
    $html .= '<p><span>'.$hesklang['completed_lan'].': </span>'.$v['completed'].'%</p>';
    $install_class = "d-none";
    $remove_class = "d-inline-flex";
    if( ! in_array($v["tag"], $language_folders)) {
        $install_class = "d-inline-flex";
        $remove_class = "d-none";
    }
    $html .= '<div class="d-inline-flex">';
    $html .= '<a href="javascript:;" data-version="'.$v["version"].'" data-tag="'.$v["tag"].'" data-description="'.$v["description"].'" data-title="'.$v["title"].'" class="btn btn-full btn_custom install_language '.$v["tag"].'_install '.$install_class.'">'.$hesklang["install_lan"].'</a> ';
    $html .= '<a href="javascript:;" data-version="'.$v["version"].'" data-tag="'.$v["tag"].'" data-description="'.$v["description"].'" data-title="'.$v["title"].'" class="btn btn--blue-border btn_custom remove_language '.$v["tag"].'_remove '.$remove_class.'" >'.$hesklang["remove_lan"].'</a> ';
    $html .= '<a href="javascript:;" data-version="'.$v["version"].'" data-tag="'.$v["tag"].'" data-description="'.$v["description"].'" data-title="'.$v["title"].'" class="btn btn-full btn_custom upgrade_language '.$v["tag"].'_upgrade '.$remove_class.'">'.$hesklang["upgrade_lan"].'</a> ';
    $html .= '</div>';
    $html .= '</div>';
}
$html .= '</div>';
$html .= '</div>';

hesk_json_exit('Success', $html);

