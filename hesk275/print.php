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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
hesk_load_database_functions();

hesk_session_start();

// Do we have parameters in query string? If yes, store them in session and redirect
if ( isset($_GET['track']) || isset($_GET['e']) )
{
    $_SESSION['p_track'] = hesk_GET('track');
    $_SESSION['p_email'] = hesk_GET('e');

    header('Location: print.php');
    die();
}

/* Get the tracking ID */
$trackingID = hesk_cleanID('p_track') or die("$hesklang[int_error]: $hesklang[no_trackID]");

/* Connect to database */
hesk_dbConnect();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

// Perform additional checks for customers
if ( empty($_SESSION['id']) )
{
	// Are we in maintenance mode?
	hesk_check_maintenance();

	// Verify email address match
    $my_email = hesk_getCustomerEmail(0, 'p_email');
    hesk_verifyEmailMatch($trackingID, $my_email);
}

/* Clean ticket parameters from the session data, we don't need them anymore */
hesk_cleanSessionVars( array('p_track', 'p_email') );

/* Get ticket info */
$res = hesk_dbQuery("SELECT `t1`.* , `t2`.name AS `repliername`
					FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t1` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `t2` ON `t1`.`replierid` = `t2`.`id`
					WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");

if (hesk_dbNumRows($res) != 1)
{
	hesk_error($hesklang['ticket_not_found']);
}
$ticket = hesk_dbFetchAssoc($res);

// Demo mode
if ( defined('HESK_DEMO') )
{
	$ticket['email'] = 'hidden@demo.com';
	$ticket['ip']	 = '127.0.0.1';
}

/* Get category name and ID */
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='{$ticket['category']}' LIMIT 1");

/* If this category has been deleted use the default category with ID 1 */
if (hesk_dbNumRows($res) != 1)
{
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='1' LIMIT 1");
}
$category = hesk_dbFetchAssoc($res);

/* Get replies */
$res  = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='{$ticket['id']}' ORDER BY `id` ASC");
$replies = hesk_dbNumRows($res);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<title><?php echo $hesk_settings['hesk_title']; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $hesklang['ENCODING']; ?>">
<style type="text/css">
body, table, td, p
{
    color : black;
    font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
    font-size : <?php echo $hesk_settings['print_font_size']; ?>px;
}
table
{
	border-collapse:collapse;
}
hr
{
	border: 0;
	color: #9e9e9e;
	background-color: #9e9e9e;
	height: 1px;
	width: 100%;
	text-align: left;
}
</style>
</head>
<body onload="window.print()">

<?php
/* Ticket priority */
switch ($ticket['priority'])
{
	case 0:
		$ticket['priority']='<b>'.$hesklang['critical'].'</b>';
		break;
	case 1:
		$ticket['priority']='<b>'.$hesklang['high'].'</b>';
		break;
	case 2:
		$ticket['priority']=$hesklang['medium'];
		break;
	default:
		$ticket['priority']=$hesklang['low'];
}

/* Set last replier name */
if ($ticket['lastreplier'])
{
	if (empty($ticket['repliername']))
	{
		$ticket['repliername'] = $hesklang['staff'];
	}
}
else
{
	$ticket['repliername'] = $ticket['name'];
}

/* Other variables that need processing */
$ticket['dt'] = hesk_date($ticket['dt'], true);
$ticket['lastchange'] = hesk_date($ticket['lastchange'], true);
$random=mt_rand(10000,99999);

// Print ticket head
echo '
<table border="0">
<tr>
	<td>' . $hesklang['subject'] . ':</td>
	<td><b>' . $ticket['subject'] . '</b></td>
</tr>
<tr>
	<td>' . $hesklang['trackID'] . ':</td>
	<td>' . $trackingID . '</td>
</tr>
<tr>
	<td>' . $hesklang['ticket_status'] . ':</td>
	<td>' . hesk_get_status_name($ticket['status']) . '</td>
</tr>
<tr>
	<td>' . $hesklang['created_on'] . ':</td>
	<td>' . $ticket['dt'] . '</td>
</tr>
<tr>
	<td>' . $hesklang['last_update'] . ':</td>
	<td>' . $ticket['lastchange'] . '</td>
</tr>
';

// Assigned to?
if ($ticket['owner'] && ! empty($_SESSION['id']) )
{
	$ticket['owner'] = hesk_getOwnerName($ticket['owner']);
	echo'
	<tr>
		<td>' . $hesklang['taso3'] . '</td>
		<td>' . $ticket['owner'] . '</td>
	</tr>
	';
}

// Continue with ticket head
echo '
<tr>
	<td>' . $hesklang['last_replier'] . ':</td>
	<td>' . $ticket['repliername'] . '</td>
</tr>
<tr>
	<td>' . $hesklang['category'] . ':</td>
	<td>' . $category['name'] . '</td>
</tr>
';

// Show IP and time worked to staff
if ( ! empty($_SESSION['id']) )
{
	echo '
	<tr>
		<td>' . $hesklang['ts'] . ':</td>
		<td>' . $ticket['time_worked'] . '</td>
	</tr>
	<tr>
		<td>' . $hesklang['ip'] . ':</td>
		<td>' . $ticket['ip'] . '</td>
	</tr>
	<tr>
		<td>' . $hesklang['email'] . ':</td>
		<td>' . $ticket['email'] . '</td>
	</tr>
	';
}

echo '
	<tr>
		<td>' . $hesklang['name'] . ':</td>
		<td>' . $ticket['name'] . '</td>
	</tr>
    ';

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ( ($v['use'] == 1 || (! empty($_SESSION['id']) && $v['use'] == 2)) && hesk_is_custom_field_in_category($k, $ticket['category']) )
	{
		switch ($v['type'])
		{
			case 'date':
				$ticket[$k] = hesk_custom_date_display_format($ticket[$k], $v['value']['date_format']);
				break;
		}
	?>
	<tr>
		<td><?php echo $v['name:']; ?></td>
		<td><?php echo hesk_unhortenUrl($ticket[$k]); ?></td>
	</tr>
	<?php
	}
}

// Close ticket head table
echo '</table>';

// Print initial ticket message
if ($ticket['message'] != '')
{
    echo '<p>' . hesk_unhortenUrl($ticket['message']) . '</p>';
}

// Print replies
while ($reply = hesk_dbFetchAssoc($res))
{
	$reply['dt'] = hesk_date($reply['dt'], true);

    echo '
    <hr />

	<table border="0">
	<tr>
		<td>' . $hesklang['date'] . ':</td>
		<td>' . $reply['dt'] . '</td>
	</tr>
	<tr>
		<td>' . $hesklang['name'] . ':</td>
		<td>' . $reply['name'] . '</td>
	</tr>
	</table>

    <p>' . hesk_unhortenUrl($reply['message']) . '</p>
    ';
}

// Print "end of ticket" message
echo $hesklang['end_ticket'];
?>

</body>
</html>
