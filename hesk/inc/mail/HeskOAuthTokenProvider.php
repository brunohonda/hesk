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

namespace PHPMailer\PHPMailer;

class HeskOAuthTokenProvider implements OAuthTokenProvider
{
    public $username;
    public $provider;

    /**
     * Generate a base64-encoded OAuth token.
     * @return string or boolean
     */
    public function getOauth64()
    {
        if (($access_token = hesk_fetch_access_token($this->provider)) === false) {
            return false;
        }

        return base64_encode(
            'user=' .
            $this->username .
            "\001auth=Bearer " .
            $access_token .
            "\001\001"
        );
    }
}
