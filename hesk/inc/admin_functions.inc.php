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

// Possible fields to be displayed in ticket list
$hesk_settings['possible_ticket_list'] = array(
'id' => $hesklang['id'],
'trackid' => $hesklang['trackID'],
'dt' => $hesklang['submitted'],
'lastchange' => $hesklang['last_update'],
'category' => $hesklang['category'],
'name' => $hesklang['customer'],
'email' => $hesklang['email'],
'subject' => $hesklang['subject'],
'status' => $hesklang['status'],
'owner' => $hesklang['owner'],
'replies' => $hesklang['replies'],
'staffreplies' => $hesklang['replies'] . ' (' . $hesklang['staff'] .')',
'lastreplier' => $hesklang['last_replier'],
'time_worked' => $hesklang['ts'],
'due_date' => $hesklang['due_date']
);

define('HESK_NO_ROBOTS', true);

/*** FUNCTIONS ***/


function hesk_show_column($column)
{
	global $hesk_settings;

	return in_array($column, $hesk_settings['ticket_list']) ? true : false;

} // END hesk_show_column()


function hesk_getHHMMSS($in)
{
	$in = hesk_getTime($in);
    return explode(':', $in);
} // END hesk_getHHMMSS();


function hesk_getTime($in)
{
	$in = trim($in);

	/* If everything is OK this simple check should return true */
    if ( preg_match('/^([0-9]{2,3}):([0-5][0-9]):([0-5][0-9])$/', $in) )
    {
    	return $in;
    }

	/* No joy, let's try to figure out the correct values to use... */
    $h = 0;
    $m = 0;
    $s = 0;

    /* How many parts do we have? */
    $parts = substr_count($in, ':');

    switch ($parts)
    {
    	/* Only two parts, let's assume minutes and seconds */
		case 1:
	        list($m, $s) = explode(':', $in);
	        break;

        /* Three parts, so explode to hours, minutes and seconds */
        case 2:
	        list($h, $m, $s) = explode(':', $in);
	        break;

        /* Something other was entered, let's assume just minutes */
        default:
	        $m = $in;
    }

	/* Make sure all inputs are integers */
	$h = intval($h);
    $m = intval($m);
    $s = intval($s);

	/* Convert seconds to minutes if 60 or more seconds */
    if ($s > 59)
    {
    	$m = floor($s / 60) + $m;
        $s = intval($s % 60);
    }

	/* Convert minutes to hours if 60 or more minutes */
    if ($m > 59)
    {
    	$h = floor($m / 60) + $h;
        $m = intval($m % 60);
    }

    /* MySQL accepts max time value of 838:59:59 */
    if ($h > 838)
    {
    	return '838:59:59';
    }    

	/* That's it, let's send out formatted time string */
    return str_pad($h, 2, "0", STR_PAD_LEFT) . ':' . str_pad($m, 2, "0", STR_PAD_LEFT) . ':' . str_pad($s, 2, "0", STR_PAD_LEFT);

} // END hesk_getTime();


function hesk_mergeTickets($merge_these, $merge_into)
{
	global $hesk_settings, $hesklang, $hesk_db_link;

    /* Target ticket must not be in the "merge these" list */
    if ( in_array($merge_into, $merge_these) )
    {
        $merge_these = array_diff($merge_these, array( $merge_into ) );
    }

    /* At least 1 ticket needs to be merged with target ticket */
    if ( count($merge_these) < 1 )
    {
    	$_SESSION['error'] = $hesklang['merr1'];
    	return false;
    }

    /* Make sure target ticket exists */
	$res = hesk_dbQuery("SELECT `id`,`trackid`,`category` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($merge_into)."' LIMIT 1");
	if (hesk_dbNumRows($res) != 1)
	{
    	$_SESSION['error'] = $hesklang['merr2'];
		return false;
	}
	$ticket = hesk_dbFetchAssoc($res);

	/* Make sure user has access to ticket category */
	if ( ! hesk_okCategory($ticket['category'], 0) )
	{
    	$_SESSION['error'] = $hesklang['merr3'];
		return false;
	}

    /* Set some variables for later */
    $sec_worked = 0;
    $history = '';
    $merged = '';

	/* Get messages, replies, notes and attachments of tickets that will be merged */
    foreach ($merge_these as $this_id)
    {
		/* Validate ID */
    	if ( is_array($this_id) )
        {
        	continue;
        }
    	$this_id = intval($this_id) or hesk_error($hesklang['id_not_valid']);

        /* Get required ticket information */
        $res = hesk_dbQuery("SELECT `tickets`.`id` AS `id`,`trackid`,`category`,`ticket_customers`.`customer_id` AS `customer_id`,`message`,`message_html`,`dt`,`time_worked`,`attachments` 
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
            LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_customers`
                ON `tickets`.`id` = `ticket_customers`.`ticket_id`
                AND `ticket_customers`.`customer_type` = 'REQUESTER' 
            WHERE `tickets`.`id`='".intval($this_id)."' LIMIT 1");
		if (hesk_dbNumRows($res) != 1)
		{
			continue;
		}
        $row = hesk_dbFetchAssoc($res);

        /* Has this user access to the ticket category? */
        if ( ! hesk_okCategory($row['category'], 0) )
        {
        	continue;
        }

        /* Insert ticket message as a new reply to target ticket */
        $customer_id = $row['customer_id'] !== null ? intval($row['customer_id']) : 'NULL';
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (`replyto`,`customer_id`,`message`,`message_html`,`dt`,`attachments`) VALUES ('".intval($ticket['id'])."',".$customer_id.",'".hesk_dbEscape(addslashes($row['message']))."','".hesk_dbEscape(addslashes($row['message_html']))."','".hesk_dbEscape($row['dt'])."','".hesk_dbEscape($row['attachments'])."')");

		/* Update attachments  */
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` SET `ticket_id`='".hesk_dbEscape($ticket['trackid'])."' WHERE `ticket_id`='".hesk_dbEscape($row['trackid'])."'");

        /* Get old ticket replies and insert them as new replies */
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($row['id'])."' ORDER BY `id` ASC");
        while ( $reply = hesk_dbFetchAssoc($res) )
        {
            $customer_id = $reply['customer_id'] !== null ? intval($reply['customer_id']) : 'NULL';
			hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (`replyto`,`message`,`message_html`,`dt`,`attachments`,`staffid`,`customer_id`,`rating`,`read`) VALUES ('".intval($ticket['id'])."','".hesk_dbEscape(addslashes($reply['message']))."','".hesk_dbEscape(addslashes($reply['message_html']))."','".hesk_dbEscape($reply['dt'])."','".hesk_dbEscape($reply['attachments'])."','".intval($reply['staffid'])."',".$customer_id.",'".intval($reply['rating'])."','".intval($reply['read'])."')");
        }

		/* Delete replies to the old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($row['id'])."'");

        /* Get old ticket notes and insert them as new notes */
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($row['id'])."' ORDER BY `id` ASC");
        while ( $note = hesk_dbFetchAssoc($res) )
        {
			hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`) VALUES ('".intval($ticket['id'])."','".intval($note['who'])."','".hesk_dbEscape($note['dt'])."','".hesk_dbEscape(addslashes($note['message']))."','".hesk_dbEscape($note['attachments'])."')");
        }

		/* Delete replies to the old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($row['id'])."'");

        /* Insert old ticket's requester and followers to the new tickets as followers, assuming they're not already on it */
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` (`ticket_id`, `customer_id`, `customer_type`)
            SELECT ".intval($ticket['id']).", `customer_id`, 'FOLLOWER'
            FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `outer_ticket_to_customer`
            WHERE `ticket_id` = ".intval($row['id'])."
                AND NOT EXISTS (
                    SELECT 1
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `inner_ticket_to_customer`
                    WHERE `inner_ticket_to_customer`.`ticket_id` = ".intval($ticket['id'])."
                    AND `inner_ticket_to_customer`.`customer_id` = `outer_ticket_to_customer`.`customer_id` 
                )");
        /* Delete old ticket's customer mappings */
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer`
            WHERE `ticket_id` = ".intval($row['id']));

	    /* Delete old ticket */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($row['id'])."'");

		/* Log that ticket has been merged */
		$history .= sprintf($hesklang['thist13'],hesk_date(),$row['trackid'],addslashes($_SESSION['name']).' ('.$_SESSION['user'].')');

        /* Add old ticket ID to target ticket "merged" field */
        $merged .= '#' . $row['trackid'];

		/* Convert old ticket "time worked" to seconds and add to $sec_worked variable */
		list ($hr, $min, $sec) = explode(':', $row['time_worked']);
		$sec_worked += (((int)$hr) * 3600) + (((int)$min) * 60) + ((int)$sec);
    }

	/* Convert seconds to HHH:MM:SS */
	$sec_worked = hesk_getTime('0:'.$sec_worked);

	// Get number of replies
	$total			= 0;
	$staffreplies	= 0;

	$res = hesk_dbQuery("SELECT COUNT(*) as `cnt`, (CASE WHEN `staffid` = 0 THEN 0 ELSE 1 END) AS `staffcnt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`=".intval($ticket['id'])." GROUP BY `staffcnt`");
	while ( $row = hesk_dbFetchAssoc($res) )
	{
		$total += $row['cnt'];
		$staffreplies += ($row['staffcnt'] ? $row['cnt'] : 0);
	}

	$replies_sql = " `replies`={$total}, `staffreplies`={$staffreplies} , ";

	// Get first staff reply
	if ($staffreplies)
	{
		$res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`=".intval($ticket['id'])." AND `staffid`>0 ORDER BY `dt` ASC LIMIT 1");
		$reply = hesk_dbFetchAssoc($res);
		$replies_sql .= " `firstreply`='".hesk_dbEscape($reply['dt'])."', `firstreplyby`=".intval($reply['staffid'])." , ";
	}

    /* Update history (log) and merged IDs of target ticket */
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET $replies_sql `time_worked`=ADDTIME(`time_worked`, '".hesk_dbEscape($sec_worked)."'), `merged`=CONCAT(`merged`,'".hesk_dbEscape($merged . '#')."'), `history`=CONCAT(`history`,'".hesk_dbEscape($history)."') WHERE `id`='".intval($merge_into)."'");

    return true;

} // END hesk_mergeTickets()


function hesk_updateStaffDefaults()
{
	global $hesk_settings, $hesklang;

	// Demo mode
	if ( defined('HESK_DEMO') )
	{
		return true;
	}
	// Remove the part that forces saving as default - we don't need it every time
    $default_list = str_replace('&def=1','',$_SERVER['QUERY_STRING']);

    // Update database
	$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `default_list`='".hesk_dbEscape($default_list)."' WHERE `id`='".intval($_SESSION['id'])."'");

    // Update session values so the changes take effect immediately
    $_SESSION['default_list'] = $default_list;

    return true;
    
} // END hesk_updateStaffDefaults()


function hesk_makeJsString($in)
{
	return addslashes(preg_replace("/\s+/",' ',$in));
} // END hesk_makeJsString()


function hesk_checkNewMail()
{
	global $hesk_settings, $hesklang;

	$res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `to`='".intval($_SESSION['id'])."' AND `read`='0' AND `deletedby`!='".intval($_SESSION['id'])."' ");
	$num = hesk_dbResult($res,0,0);

	return $num;
} // END hesk_checkNewMail()


function hesk_dateToString($dt, $returnName=1, $returnTime=0, $returnMonth=0, $from_database=false)
{
	global $hesk_settings, $hesklang;

	$dt = strtotime($dt);

	// Adjust MySQL time if different from PHP time
	if ($from_database)
	{
		if ( ! defined('MYSQL_TIME_DIFF') )
		{
			define('MYSQL_TIME_DIFF', time()-hesk_dbTime() );
		}

		if (MYSQL_TIME_DIFF != 0)
		{
			$dt += MYSQL_TIME_DIFF;
		}
	}

	list($y,$m,$n,$d,$G,$i,$s) = explode('-', date('Y-n-j-w-G-i-s', $dt) );

	$m = $hesklang['m'.$m];
	$d = $hesklang['d'.$d];

	if ($returnName)
	{
		return "$d, $m $n, $y";
	}

    if ($returnTime)
    {
    	return "$d, $m $n, $y $G:$i:$s";
    }

    if ($returnMonth)
    {
    	return "$m $y";
    }

	return "$m $n, $y";
} // End hesk_dateToString()


function hesk_getCategoriesArray($kb = 0) {
	global $hesk_settings, $hesklang, $hesk_db_link;

	$categories = array();
    if ($kb)
    {
    	$result = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'kb_categories` ORDER BY `cat_order` ASC');
    }
    else
    {
		$result = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` ORDER BY `cat_order` ASC');
    }

	while ($row=hesk_dbFetchAssoc($result))
	{
		$categories[$row['id']] = $row['name'];
	}

    return $categories;
} // END hesk_getCategoriesArray()


function hesk_getHTML($in)
{
	global $hesk_settings, $hesklang;

	$replace_from = array("\t","<?","?>","$","<%","%>");
	$replace_to   = array("","&lt;?","?&gt;","\$","&lt;%","%&gt;");

	$in = trim($in);
	$in = str_replace($replace_from,$replace_to,$in);
	$in = preg_replace('/\<script(.*)\>(.*)\<\/script\>/Uis',"<script$1></script>",$in);
	$in = preg_replace('/\<\!\-\-(.*)\-\-\>/Uis',"<!-- comments have been removed -->",$in);

	if (HESK_SLASH === true)
	{
		$in = addslashes($in);
	}
    $in = str_replace('\"','"',$in);

	return $in;
} // END hesk_getHTML()


function hesk_autoLogin($noredirect=0)
{
	global $hesk_settings, $hesklang, $hesk_db_link;

    if (!$hesk_settings['autologin']) {
        return false;
    }

    if (empty($remember = hesk_COOKIE('hesk_remember')) || substr_count($remember, ':') !== 1) {
        return false;
    }

    // Login cookies exist, now lets limit brute force attempts
    hesk_limitBfAttempts();

    // Admin login URL
    $url = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/index.php?a=login&notice=1';

    // Get and verify authentication tokens
    list($selector, $authenticator) = explode(':', $remember);
    $authenticator = base64_decode($authenticator);
    if (strlen($authenticator) > 256) {
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    $result = hesk_dbQuery('SELECT * FROM `'.$hesk_settings['db_pfix']."auth_tokens` WHERE `selector` = '".hesk_dbEscape($selector)."' AND `expires` > NOW() LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
	}

    $auth = hesk_dbFetchAssoc($result);

    if ( ! hash_equals($auth['token'], hash('sha256', $authenticator))) {
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    // Token OK, let's regenerate session ID and get user data
    hesk_session_regenerate_id();

    $result = hesk_dbQuery('SELECT * FROM `'.$hesk_settings['db_pfix']."users` WHERE `id` = ".intval($auth['user_id'])." LIMIT 1");
    if (hesk_dbNumRows($result) != 1) {
        hesk_setcookie('hesk_remember', '');
        header('Location: '.$url);
        exit();
    }

    $row = hesk_dbFetchAssoc($result);
    foreach ($row as $k => $v) {
        if ($k == 'pass') {
            continue;
        }
        $_SESSION[$k] = $v;
    }

    $user = $row['user'];
    define('HESK_USER', $user);

    // Each token should only be used once, so update the old one with a new one
    $selector = base64_encode(random_bytes(9));
    $authenticator = random_bytes(33);
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` SET `selector`='".hesk_dbEscape($selector)."', `token` = '".hesk_dbEscape(hash('sha256', $authenticator))."', `created` = NOW() WHERE `id` = ".intval($auth['id']));
    hesk_setcookie('hesk_remember', $selector.':'.base64_encode($authenticator), strtotime('+1 year'));

	// Set a tag that will be used to expire sessions after username or password change
	$_SESSION['session_verify'] = hesk_activeSessionCreateTag($user, $row['pass']);

	/* Login successful, clean brute force attempts */
	hesk_cleanBfAttempts();

	/* Get allowed categories */
	if (empty($_SESSION['isadmin']))
	{
	    $_SESSION['categories']=explode(',',$_SESSION['categories']);
	}

    /* Close any old tickets here so Cron jobs aren't necessary */
	if ($hesk_settings['autoclose'])
    {
    	$revision = sprintf($hesklang['thist3'],hesk_date(),$hesklang['auto']);
    	$dt  = date('Y-m-d H:i:s',time() - $hesk_settings['autoclose']*86400);

		// Notify customer of closed ticket?
		if ($hesk_settings['notify_closed'])
		{
			// Get list of tickets
			$result = hesk_dbQuery("SELECT * FROM `".$hesk_settings['db_pfix']."tickets` WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."' ");
			if (hesk_dbNumRows($result) > 0)
			{
				global $ticket;

				// Load required functions?
				if ( ! function_exists('hesk_notifyCustomer') )
				{
					require(HESK_PATH . 'inc/email_functions.inc.php');
				}

				while ($ticket = hesk_dbFetchAssoc($result))
				{
					$ticket['dt'] = hesk_date($ticket['dt'], true);
					$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
					$ticket = hesk_ticketToPlain($ticket, 1, 0);
					hesk_notifyCustomer('ticket_closed');
				}
			}
		}

		// Update ticket statuses and history in database
		hesk_dbQuery("UPDATE `".$hesk_settings['db_pfix']."tickets` SET `status`='3', `closedat`=NOW(), `closedby`='-1', `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `status` = '2' AND `lastchange` <= '".hesk_dbEscape($dt)."' ");
    }

	/* If session expired while a HESK page is open just continue using it, don't redirect */
    if ($noredirect)
    {
    	return true;
    }

	/* Redirect to the destination page */
	header('Location: ' . hesk_verifyGoto() );
	exit();
} // END hesk_autoLogin()


function hesk_isLoggedIn()
{
	global $hesk_settings;

	$referer = hesk_input($_SERVER['REQUEST_URI']);
	$referer = str_replace('&amp;','&',$referer);

	// Admin login URL
	$url = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/index.php?a=login&notice=1&goto='.urlencode($referer);

    if ( empty($_SESSION['id']) || empty($_SESSION['session_verify']))
    {
    	if ($hesk_settings['autologin'] && hesk_autoLogin(1) )
        {
			// Users online
        	if ($hesk_settings['online'])
            {
            	require(HESK_PATH . 'inc/users_online.inc.php');
                hesk_initOnline($_SESSION['id']);
            }

        	return true;
        }

		hesk_session_stop();
        header('Location: '.$url);
        exit();
    }
    else
    {
        // hesk_session_regenerate_id();

		// Let's make sure access data is up-to-date
		$res = hesk_dbQuery( "SELECT `user`, `pass`, `isadmin`, `categories`, `heskprivileges`, `signature` FROM `".$hesk_settings['db_pfix']."users` WHERE `id` = '".intval($_SESSION['id'])."' LIMIT 1" );

		// Exit if user not found
		if (hesk_dbNumRows($res) != 1)
		{
			hesk_session_stop();
			header('Location: '.$url);
			exit();
		}

		// Fetch results from database
		$me = hesk_dbFetchAssoc($res);

		// Verify this session is still valid
		if ( ! hesk_activeSessionValidate($me['user'], $me['pass'], $_SESSION['session_verify']) )
		{
			hesk_session_stop();
			header('Location: '.$url);
			exit();
		}

		// Update session variables as needed
		if ($me['isadmin'] == 1)
		{
			$_SESSION['isadmin'] = 1;
		}
		else
		{
			$_SESSION['isadmin'] = 0;
			$_SESSION['categories'] = explode(',', $me['categories']);
			$_SESSION['heskprivileges'] = $me['heskprivileges'];
		}

        $_SESSION['signature'] = $me['signature'];

		// Users online
		if ($hesk_settings['online'])
		{
			require(HESK_PATH . 'inc/users_online.inc.php');
            hesk_initOnline($_SESSION['id']);
		}

        return true;
    }

} // END hesk_isLoggedIn()


function hesk_Pass2Hash($plaintext) {
    // This is a LEGACY function, only used to check and update legacy passwords
    // Use hesk_password_hash/hesk_password_verify functions instead!
    $majorsalt  = '';
    $len = strlen($plaintext);
    for ($i=0;$i<$len;$i++)
    {
        $majorsalt .= sha1(substr($plaintext,$i,1));
    }
    $corehash = sha1($majorsalt);
    return $corehash;
} // END hesk_Pass2Hash()


function hesk_formatDate($dt, $from_database=true)
{
    $dt=hesk_date($dt, $from_database);
	$dt=str_replace(' ','<br />',$dt);
    return $dt;
} // End hesk_formatDate()


function hesk_jsString($str)
{
    $str  = addslashes($str);
    $str  = str_replace('<br />' , '' , $str);
    $from = array("/\r\n|\n|\r/", '/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
    $to   = array("\\r\\n' + \r\n'", "$1", "$1");
    return preg_replace($from,$to,$str);
} // END hesk_jsString()


function hesk_myOwnership($consider_collaborators = false)
{
    // Admins can see all tickets
    if ( ! empty($_SESSION['isadmin']) )
    {
        return '1';
    }

    // For staff, let's check permissions
    $can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
    $can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
    $can_view_ass_by     = hesk_checkPermission('can_view_ass_by', 0);

    // Can view all tickets, regrdless of ownership
    if ($can_view_unassigned == 1 && $can_view_ass_others == 1) {
        return '1';
    }

    $sql_ownership = '';

    // Can view assigned to me + unassigned
    if ($can_view_unassigned == 1 && $can_view_ass_others == 0 && $can_view_ass_by == 0) {
        $sql_ownership .= " `owner` IN ('0', '" . intval($_SESSION['id']) . "') ";
    }

    // Can view assigned to me + unassigned + tickets I assigned to others
    elseif ($can_view_unassigned == 1 && $can_view_ass_others == 0 && $can_view_ass_by == 1) {
        $sql_ownership .= " (`owner` IN ('0', '" . intval($_SESSION['id']) . "') OR `assignedby` = " . intval($_SESSION['id']) . ") ";
    }

    // Can view assigned to me + assigned to others
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 1) {
        $sql_ownership .= " `owner` != 0 ";
    }

    // Can view assigned to me + tickets I assigned to others
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 0 && $can_view_ass_by == 1) {
        $sql_ownership .= " (`owner` = " . intval($_SESSION['id']) . " OR `assignedby` = " . intval($_SESSION['id']) . ") ";
    }

    // Can only view assigned to me
    elseif ($can_view_unassigned == 0 && $can_view_ass_others == 0 && $can_view_ass_by == 0) {
        $sql_ownership .= " `owner` = " . intval($_SESSION['id']) . " ";
    }

    // Must be an internal error
    else {
        die('Invalid view attempt (1)');
    }

    // Add a collaborator check for certain use cases
    if ($consider_collaborators) {
        return " ($sql_ownership OR `w`.`user_id`= " . intval($_SESSION['id']) . ") ";
    } else {
        return $sql_ownership;
    }

} // END hesk_myOwnership()


function hesk_myCategories($what='category')
{
    if ( ! empty($_SESSION['isadmin']) )
    {
        return '1';
    }
    else
    {
        return " `".hesk_dbEscape($what)."` IN ('" . implode("','", array_map('intval', $_SESSION['categories']) ) . "')";
    }
} // END hesk_myCategories()


function hesk_okCategory($cat,$error=1,$user_isadmin=false,$user_cat=false)
{
	global $hesklang;

	/* Checking for current user or someone else? */
    if ($user_isadmin === false)
    {
		$user_isadmin = $_SESSION['isadmin'];
    }

    if ($user_cat === false)
    {
		$user_cat = $_SESSION['categories'];
    }

    /* Is admin? */
    if ($user_isadmin)
    {
        return true;
    }
    /* Staff with access? */
    elseif (in_array($cat,$user_cat))
    {
        return true;
    }
    /* No access */
    else
    {
        if ($error)
        {
        	hesk_error($hesklang['not_authorized_tickets']);
        }
        else
        {
        	return false;
        }
    }

} // END hesk_okCategory()


function hesk_checkPermission($feature,$showerror=1) {
	global $hesklang;

    /* Admins have full access to all features */
    if ( isset($_SESSION['isadmin']) && $_SESSION['isadmin'])
    {
        return true;
    }

    /* Check other staff for permissions */
    if ( isset($_SESSION['heskprivileges']) && strpos($_SESSION['heskprivileges'], $feature) === false)
    {
    	if ($showerror)
        {
        	hesk_error($hesklang['no_permission'].'<p>&nbsp;</p><p align="center"><a href="index.php">'.$hesklang['click_login'].'</a>');
        }
        else
        {
        	return false;
        }
    }
    else
    {
        return true;
    }

} // END hesk_checkPermission()


function hesk_purge_cache($type = '', $expire_after_seconds = 0)
{
    global $hesk_settings;

    $cache_dir = dirname(dirname(__FILE__)).'/'.$hesk_settings['cache_dir'].'/';

    if ( ! is_dir($cache_dir))
    {
        return false;
    }

    switch ($type)
    {
        case 'export':
            $files = glob($cache_dir.'hesk_export_*', GLOB_NOSORT);
            break;
        case 'status':
            $files = glob($cache_dir.'status_*', GLOB_NOSORT);
            break;
        case 'priority':
            $files = glob($cache_dir.'priority_*', GLOB_NOSORT);
            break;
        case 'cf':
            $files = glob($cache_dir.'cf_*', GLOB_NOSORT);
            break;
        case 'kb':
            $files = array($cache_dir.'kb.cache.php');
            break;
        default:
            hesk_rrmdir(trim($cache_dir, '/'), true);
            return true;
    }

    if (is_array($files))
    {
        array_walk($files, 'hesk_unlink_callable', $expire_after_seconds);
    }

    return true;

} // END hesk_purge_cache()


function hesk_rrmdir($dir, $keep_top_level=false)
{
    if ( ! is_dir($dir)) {
        return false;
    }

    $files = $keep_top_level ? array_diff(scandir($dir), array('.','..','index.htm')) : array_diff(scandir($dir), array('.','..'));

    foreach ($files as $file)
    {
        (is_dir("$dir/$file")) ? hesk_rrmdir("$dir/$file") : @unlink("$dir/$file");
    }

    return $keep_top_level ? true : @rmdir($dir);

} // END hesk_rrmdir()


function hesk_deleteTicketsForCustomer($customer_id) {
    global $hesk_settings;

    $sql = "SELECT `tickets`.`id`, `tickets`.`trackid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `ticket_to_customer`
            ON `ticket_to_customer`.`ticket_id` = `tickets`.`id`
        WHERE `ticket_to_customer`.`customer_id` = ".intval($customer_id)."
        AND `ticket_to_customer`.`customer_type` = 'REQUESTER'";

    $tickets_rs = hesk_dbQuery($sql);
    while ($ticket = hesk_dbFetchAssoc($tickets_rs)) {
        hesk_fullyDeleteTicket($ticket['id'], $ticket['trackid']);
    }
}


function hesk_fullyDeleteTicket($ticket_id, $ticket_trackid)
{
    global $hesk_settings;

    /* Delete attachment files */
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket_trackid)."'");
    if (hesk_dbNumRows($res))
    {
        $hesk_settings['server_path'] = dirname(dirname(__FILE__));

        while ($file = hesk_dbFetchAssoc($res))
        {
            hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
        }
    }

    /* Delete attachments info from the database */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($ticket_trackid)."'");

    /* Delete the ticket */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `id`='".intval($ticket_id)."'");

    /* Delete replies to the ticket */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket_id)."'");

    /* Delete ticket notes */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `ticket`='".intval($ticket_id)."'");

    /* Delete ticket reply drafts */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `ticket`=".intval($ticket_id));

    /* Delete ticket/customer mappings */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `ticket_id` = ".intval($ticket_id));

    /* Delete bookmarks */
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."bookmarks` WHERE `ticket_id` = ".intval($ticket_id));

    return true;
}


function hesk_isTicketBookmarked($ticket_id, $user_id)
{
    global $hesk_settings, $hesklang, $hesk_db_link;

    $result = hesk_dbQuery('SELECT `id` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'bookmarks` WHERE `ticket_id`='.intval($ticket_id).' AND `user_id`='.intval($user_id).' LIMIT 1');

    return hesk_dbNumRows($result);
} // END hesk_isTicketBookmarked()


function hesk_json_exit($status = 'Error', $data = '') {
    $json_data = [
        'status' => $status,
        'data'   => $data,
    ];
    echo json_encode($json_data);
    exit;
} // END hesk_json_exit()

