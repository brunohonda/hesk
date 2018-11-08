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
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

define('CALENDAR',1);
define('AUTO_RELOAD',1);

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets');

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

?>
</td>
</tr>
<tr>
<td>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

<table style="width:100%;border:none;border-collapse:collapse;"><tr>
<td style="width:25%"><label><input type="checkbox" onclick="toggleAutoRefresh(this);" id="reloadCB"> <?php echo $hesklang['arp']; ?> <span id="timer"></span></label><script type="text/javascript">heskCheckReloading();</script></td>
<td style="width:50%;text-align:center"><h3><?php echo $hesklang['tickets']; ?></h3></td>
<td style="width:25%;text-align:right"><a href="new_ticket.php"><?php echo $hesklang['nti']; ?></a></td>
</tr>
</table>

<?php
/* Print the list of tickets */
$is_search = 1;
require_once(HESK_PATH . 'inc/print_tickets.inc.php');

/* Update staff default settings? */
if ( ! empty($_GET['def']))
{
	hesk_updateStaffDefaults();
}
?>

&nbsp;<br />

<?php
/* Print forms for listing and searching tickets */
require_once(HESK_PATH . 'inc/show_search_form.inc.php');
?>

<p>&nbsp;</p>
<?php

/* Print footer */
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

?>
