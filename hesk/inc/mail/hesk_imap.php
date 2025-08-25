#!/usr/bin/php -q
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
define('HESK_PATH', dirname(dirname(dirname(__FILE__))) . '/');

// Do not send out the default UTF-8 HTTP header
define('NO_HTTP_HEADER',1);

// Get required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/oauth_functions.inc.php');
require(HESK_PATH . 'inc/mail/imap/HeskIMAP.php');
//============================================================================//
//                           OPTIONAL MODIFICATIONS                           //
//============================================================================//

// Set category ID where new tickets will be submitted to
$set_category = 1;

// Set ticket priority of new tickets with the following options:
// -1  = use default category priority
//  0  = critical
//  1  = high
//  2  = medium
//  3  = low
$set_priority = -1;

// Uncomment lines below to use different IMAP login details than in settings
/*
$hesk_settings['imap']           = 1;
$hesk_settings['imap_job_wait']  = 15;
$hesk_settings['imap_host_name'] = 'imap.gmail.com';
$hesk_settings['imap_host_port'] = 993;
$hesk_settings['imap_enc']       = 'ssl';
$hesk_settings['imap_keep']      = 0;
$hesk_settings['imap_user']      = 'test@example.com';
$hesk_settings['imap_password']  = 'password';
*/

//============================================================================//
//                         END OPTIONAL MODIFICATIONS                         //
//============================================================================//

// Do we require a key if not accessed over CLI?
hesk_authorizeNonCLI();

// Is this feature enabled?
if (empty($hesk_settings['imap']))
{
	die($hesklang['ifd']);
}

// Are we in maintenance mode?
if ( hesk_check_maintenance(false) )
{
	// If Debug mode is ON show "Maintenance mode" message
	$message = $hesk_settings['debug_mode'] ? $hesklang['mm1'] : '';
	die($message);
}

// Don't start IMAP fetching if an existing job is in progress
if ($hesk_settings['imap_job_wait'])
{
	// A log file used to store start of IMAP fetching
	$job_file = HESK_PATH . $hesk_settings['cache_dir'] . '/__imap-' . sha1(__FILE__) . '.txt';

	// If the job file already exists, wait for the previous job to complete unless expired
	if ( file_exists($job_file ) )
	{
		// Get time when the active IMAP fetching started
		$last = intval( file_get_contents($job_file) );

		// Give a running process at least X minutes to finish
		if ( $last + $hesk_settings['imap_job_wait'] * 60 > time() )
		{
			$message = $hesk_settings['debug_mode'] ? $hesklang['ifr'] : '';
			die($message);
		}
		else
		{
			// Start the process (force)
			file_put_contents($job_file, time() );
		}
	}
	else
	{
		// No job in progress, log when this one started
		file_put_contents($job_file, time() );
	}
}

// Get other required includes
require(HESK_PATH . 'inc/pipe_functions.inc.php');

// Tell Hesk we are in IMAP mode
define('HESK_IMAP', true);

// Connect to the database
hesk_dbConnect();

// Are we in test mode?
$hesk_settings['TEST-MODE'] = (hesk_GET('test_mode') == 1) ? true : false;

$imap = new HeskIMAP();
$imap->host = $hesk_settings['imap_host_name'];
$imap->port = $hesk_settings['imap_host_port'];
$imap->username = $hesk_settings['imap_user'];
if ($hesk_settings['imap_conn_type'] === 'basic') {
    $imap->password = hesk_htmlspecialchars_decode($hesk_settings['imap_password']);
    $imap->useOAuth = false;
} elseif ($hesk_settings['imap_conn_type'] === 'oauth') {
    $access_token = hesk_fetch_access_token($hesk_settings['imap_oauth_provider']);
    if (!$access_token) {
        echo "<pre>" . $hesklang['oauth_error_retrieve'] . "</pre>";
        if ($hesk_settings['imap_job_wait']) {
            unlink($job_file);
        }
        return null;
    }

    $imap->accessToken = $access_token;
    $imap->useOAuth = true;
    $imap->password = null;
}

$imap->readOnly = $hesk_settings['TEST-MODE'];
$imap->ignoreCertificateErrors = $hesk_settings['imap_noval_cert'];
$imap->disableGSSAPI = $hesk_settings['imap_disable_GSSAPI'];
$imap->connectTimeout = 15;
$imap->responseTimeout = 15;
$imap->imap_mailbox = $hesk_settings['imap_mailbox'];// Added for IMAP Mailbox
$imap->folder = $hesk_settings['imap_mailbox']; //Change for IMAP Mailbox Folder;

if ($hesk_settings['imap_enc'] === 'ssl')
{
    $imap->ssl = true;
    $imap->tls = false;
}
elseif ($hesk_settings['imap_enc'] === 'tls')
{
    $imap->ssl = false;
    $imap->tls = true;
}
else
{
    $imap->ssl = false;
    $imap->tls = false;
}

// We don't want the script to run forever if we can't connect to IMAP...
set_time_limit($imap->connectTimeout * 4);

// Connect to IMAP
if ($imap->login())
{
    echo $hesk_settings['debug_mode'] ? "<pre>Connected to the IMAP server &quot;" . $imap->host . ":" . $imap->port . "&quot;.</pre>\n" : '';

    if ($imap->hasUnseenMessages())
    {
        $emails = $imap->getUnseenMessageIDs();
        $emails_found = count($emails);
        echo $hesk_settings['debug_mode'] ? "<pre>Unread messages found: $emails_found</pre>\n" : '';
        //print_r($emails);

        if ($hesk_settings['TEST-MODE'])
        {
            $imap->logout();
            echo $hesk_settings['debug_mode'] ? "<pre>TEST MODE, NO EMAILS PROCESSED\n\nDisconnected from the IMAP server.</pre>\n" : '';
            if ($hesk_settings['imap_job_wait'])
            {
                unlink($job_file);
            }
            return null;
        }

        $this_email = 0;

        // This is a bit tricky - we don't want the fetching to run forever, but we also don't want it to end prematurely
        // Let's try to figure out a reasonable time limit:
        // - let it run at least 300 seconds (5 minutes) per unseen email, to handle large attachments
        // - let it run least 1800 seconds (30 minutes) in total
        // - if it runs for over 3600 seconds (60 minutes) in total, something probably went wrong
        if (function_exists('set_time_limit'))
        {
            $time_limit = $emails_found * 300;
            if ($time_limit < 1800)
            {
                $time_limit = 1800;
            }
            elseif ($time_limit > 3600)
            {
                $time_limit = 3600;
            }

            set_time_limit($time_limit);
            echo $hesk_settings['debug_mode'] ? "<pre>Time limit set to {$time_limit} seconds.</pre>\n" : '';
        }

        // Download and parse each email
        foreach($emails as $email_number)
        {
            $this_email++;
            echo $hesk_settings['debug_mode'] ? "<pre>Parsing message $this_email of $emails_found.</pre>\n" : '';

            // Parse email from the stream
            if (($results = parser()) === false)
            {
                echo $hesk_settings['debug_mode'] ? "<pre>Error parsing email, see debug log. Aborting fetching.</pre>\n" : '';
                break;
            }

            // Convert email into a ticket (or new reply)
            if ( $id = hesk_email2ticket($results, 2, $set_category, $set_priority) )
            {
                echo $hesk_settings['debug_mode'] ? "<pre>Ticket $id created/updated.</pre>\n" : '';
            }
            elseif (isset($hesk_settings['DEBUG_LOG']['PIPE']))
            {
                echo "<pre>Ticket NOT inserted: " . $hesk_settings['DEBUG_LOG']['PIPE'] . "</pre>\n";
            }

            // Queue message to be deleted on connection close
            if ( ! $hesk_settings['imap_keep'])
            {
                $imap->delete($email_number);
            }

            echo $hesk_settings['debug_mode'] ? "<br /><br />\n\n" : '';
        }

        if ( ! $hesk_settings['imap_keep'])
        {
            $imap->expunge();
            echo $hesk_settings['debug_mode'] ? "<pre>Expunged mail folder.</pre>\n" : '';
        }
    }
    else
    {
        echo $hesk_settings['debug_mode'] ? "<pre>No unread messages found.</pre>\n" : '';
    }

    $imap->logout();
    echo $hesk_settings['debug_mode'] ? "<pre>Disconnected from the IMAP server.</pre>\n" : '';
}
elseif (!$hesk_settings['debug_mode'])
{
    echo "<p>Unable to connect to the IMAP server.</p>\n";
}

if($errors = $imap->getErrors())
{
    if ($hesk_settings['debug_mode'])
    {
        foreach ($errors as $error)
        {
            echo "<pre>" . hesk_htmlspecialchars($error) . "</pre>\n";
        }
    }
    else
    {
        echo "<h2>An error occured.</h2><p>For details turn <b>Debug mode</b> ON in settings and run this script again.</p>\n";
    }
}

unset($imap);

// Remove active IMAP fetching log file
if ($hesk_settings['imap_job_wait'])
{
	unlink($job_file);
}

return NULL;
