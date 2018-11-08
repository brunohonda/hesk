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

// Ticket priority
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

// Set last replier name
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

// Other variables that need processing
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
	<td>' . $ticket['trackid'] . '</td>
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
echo '<div style="page-break-after: always;">' . $hesklang['end_ticket'] . "</div>";
