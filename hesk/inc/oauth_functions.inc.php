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

function hesk_get_oauth_redirect_url() {
    global $hesk_settings;

    return $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/oauth_providers.php';
}

function hesk_oauth_fetch_and_store_initial_token($provider, $code, $redirect_to = './oauth_providers.php') {
    global $hesk_settings, $hesklang;

    // Grab an access token and refresh token from the server
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $provider['token_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_POST, 1);

    if ($provider['no_val_ssl']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    $redirect_url = hesk_get_oauth_redirect_url();
    $post_fields = array(
        "grant_type=authorization_code",
        "client_id={$provider['client_id']}",
        "scope={$provider['scope']}",
        "redirect_uri={$redirect_url}",
        "client_secret={$provider['client_secret']}",
        "code={$code}",
        "access_type=offline"
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post_fields));
    $response = curl_exec($ch);
    if ($response === false) {
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        die("cURL {$hesklang['error']}: $http_status<br>\n{$hesklang['error']}: $error");
    }
    curl_close($ch);

    $decoded_response = json_decode($response, true);

    if ($decoded_response === false) {
        //-- We didn't get a JSON response; this should never happen!
        hesk_process_messages($hesklang['oauth_error_unknown'], $redirect_to);
    } else {
        if (isset($decoded_response['error'])) {
            //-- Error occurred; go back to settings with an error prompt
            $error = isset($decoded_response['error_description']) ? $decoded_response['error_description'] : $hesklang['oauth_error_unknown'];

            hesk_process_messages(
                $error . ($hesk_settings['debug_mode'] ? '
                    <br><br>
                    <textarea rows="10" cols="60">Debug info: ' . "\n" .
                        "Response: " . var_export($response, true) . "\n" .
                        "JSON: " . var_export($decoded_response, true) . "\n" .
                    "</textarea>" : ''),
                $redirect_to
            );
        }

        // Did we get an access token at all?
        if ( ! isset($decoded_response['access_token'])) {
            hesk_process_messages(
                $hesklang['oauth_error_no_token'] . ($hesk_settings['debug_mode'] ? '
                    <br><br>
                    <textarea rows="10" cols="60">Debug info: ' . "\n" .
                        "Response: " . var_export($response, true) . "\n" .
                        "JSON: " . var_export($decoded_response, true) . "\n" .
                    "</textarea>" : ''),
                $redirect_to
            );
        }

        //-- Save tokens to DB, return to email settings as we probably came from there.
        hesk_store_oauth_token($provider['id'], $decoded_response['access_token'], 'access_token', $decoded_response['expires_in']);

        // Refresh tokens are long-lasting, so we won't store an expiry as we'll always attempt to use it.
        if (isset($decoded_response['refresh_token'])) {
            hesk_store_oauth_token($provider['id'], $decoded_response['refresh_token'], 'refresh_token');
        }

        //-- This provider is now verified
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` SET `verified` = 1 WHERE `id`={$provider['id']}");
        hesk_process_messages($hesklang['oauth_provider_saved'] . '<br><br>' . sprintf($hesklang['oauth_provider_use'], $hesklang['settings'], $hesklang['tab_6']), $redirect_to, 'SUCCESS');
    }
}

function hesk_store_oauth_token($provider_id, $token, $token_type, $expiry_in_seconds = null) {
    global $hesk_settings;

    // If we have another token with the same provider ID and type, purge it
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens` 
        WHERE `provider_id` = '".intval($provider_id)."' 
            AND `token_type` = '".hesk_dbEscape($token_type)."'");

    // Store the new token. Subtracting 15 from the expiry to ensure that we won't attempt to use an expired access token down the road
    $expiry_time = $expiry_in_seconds === null ?
        'NULL' :
        "NOW() + INTERVAL ".intval($expiry_in_seconds - 15)." SECOND";
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens` (`provider_id`, `token_value`, `token_type`, `expires`)
    VALUES ('".intval($provider_id)."',
            '".hesk_dbEscape($token)."',
            '".hesk_dbEscape($token_type)."',
            ".$expiry_time.")");
}

function hesk_fetch_access_token($provider_id) {
    global $hesk_settings;

    // Check if we have a token that is still valid for the next 30 seconds
    $res = hesk_dbQuery("SELECT `token_value` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens`
        WHERE `provider_id` = '".hesk_dbEscape($provider_id)."'
            AND `token_type` = 'access_token'
            AND `expires` >= NOW() + INTERVAL 30 SECOND");

    if ($row = hesk_dbFetchAssoc($res)) {
        return $row['token_value'];
    }

    // No token available.  Fetch a new token via its refresh token, store the new access / refresh, and return the new access token.
    $res = hesk_dbQuery("SELECT `token_value` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens`
        WHERE `provider_id` = '".hesk_dbEscape($provider_id)."'
            AND `token_type` = 'refresh_token'");

    if ($row = hesk_dbFetchAssoc($res)) {
        return hesk_retrieve_new_access_token($provider_id, $row['token_value']);
    }

    //-- Something went horribly wrong.  We should never *not* have a refresh token.
    return false;
}

function hesk_retrieve_new_access_token($provider_id, $refresh_token) {
    global $hesk_settings, $hesklang;

    $provider_rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` WHERE `id` = ".intval($provider_id));
    $provider = hesk_dbFetchAssoc($provider_rs);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $provider['token_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_POST, 1);

    if ($provider['no_val_ssl']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    $redirect_url = hesk_get_oauth_redirect_url();
    $post_fields = array(
        "grant_type=refresh_token",
        "client_id={$provider['client_id']}",
        "scope={$provider['scope']}",
        "redirect_uri={$redirect_url}",
        "client_secret={$provider['client_secret']}",
        "refresh_token={$refresh_token}",
        "access_type=offline"
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post_fields));
    $response = curl_exec($ch);
    if ($response === false) {
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        die("cURL {$hesklang['error']}: $http_status<br>\n{$hesklang['error']}: $error");
    }
    curl_close($ch);

    $decoded_response = json_decode($response, true);

    if ($decoded_response === false) {
        // Something terribly bad happened if we get here.
        return false;
    }

    // Did we get an access token at all?
    if ( ! isset($decoded_response['access_token'])) {
        return false;
    }

    //-- Save tokens to DB
    hesk_store_oauth_token($provider_id, $decoded_response['access_token'], 'access_token', $decoded_response['expires_in']);

    if (isset($decoded_response['refresh_token'])) {
        hesk_store_oauth_token($provider_id, $decoded_response['refresh_token'], 'refresh_token');
    }

    return $decoded_response['access_token'];
}
