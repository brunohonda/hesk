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

// Auto-focus first empty or error field
define('AUTOFOCUS', true);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Load calendar JS and CSS
define('CALENDAR',1);

// Pre-populate fields
// Customer name
if ( isset($_REQUEST['name']) )
{
	$_SESSION['as_name'] = $_REQUEST['name'];
}

// Customer email address
if ( isset($_REQUEST['email']) )
{
	$_SESSION['as_email']  = $_REQUEST['email'];
	$_SESSION['as_email2'] = $_REQUEST['email'];
}

// Category ID
if ( isset($_REQUEST['catid']) )
{
	$_SESSION['as_category'] = intval($_REQUEST['catid']);
}
if ( isset($_REQUEST['category']) )
{
	$_SESSION['as_category'] = intval($_REQUEST['category']);
}

// Priority
if ( isset($_REQUEST['priority']) )
{
	$_SESSION['as_priority'] = intval($_REQUEST['priority']);
}

// Subject
if ( isset($_REQUEST['subject']) )
{
	$_SESSION['as_subject'] = $_REQUEST['subject'];
}

// Message
if ( isset($_REQUEST['message']) )
{
	$_SESSION['as_message'] = $_REQUEST['message'];
}

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k=>$v)
{
	if ($v['use'] && isset($_REQUEST[$k]) )
	{
		$_SESSION['as_'.$k] = $_REQUEST[$k];
	}
}

/* Varibles for coloring the fields in case of errors */
if (!isset($_SESSION['iserror']))
{
	$_SESSION['iserror'] = array();
}

if (!isset($_SESSION['isnotice']))
{
	$_SESSION['isnotice'] = array();
}

/* List of users */
$admins = array();
$result = hesk_dbQuery("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row=hesk_dbFetchAssoc($result))
{
	/* Is this an administrator? */
	if ($row['isadmin'])
    {
	    $admins[$row['id']]=$row['name'];
	    continue;
    }

	/* Not admin, is user allowed to view tickets? */
	if (strpos($row['heskprivileges'], 'can_view_tickets') !== false)
	{
		$admins[$row['id']]=$row['name'];
		continue;
	}
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

// Get categories
$hesk_settings['categories'] = array();

if (hesk_checkPermission('can_submit_any_cat', 0))
{
    $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
}
else
{
    $res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".hesk_myCategories('id')." ORDER BY `cat_order` ASC");
}

while ($row=hesk_dbFetchAssoc($res))
{
	$hesk_settings['categories'][$row['id']] = $row['name'];
}

$number_of_categories = count($hesk_settings['categories']);

if ($number_of_categories == 0)
{
	$category = 1;
}
elseif ($number_of_categories == 1)
{
	$category = current(array_keys($hesk_settings['categories']));
}
else
{
	$category = isset($_GET['catid']) ? hesk_REQUEST('catid'): hesk_REQUEST('category');

	// Force the customer to select a category?
	if (! isset($hesk_settings['categories'][$category]) )
	{
		return print_select_category($number_of_categories);
	}
}
?>

</td>
</tr>
<tr>
<td>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

<p class="smaller">&nbsp;<a href="admin_main.php" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a> &gt;
<?php
if ($number_of_categories > 1)
{
	?>
	<a href="new_ticket.php" class="smaller"><?php echo $hesklang['nti2']; ?></a> &gt;
	<?php echo $hesk_settings['categories'][$category];
}
else
{
	echo $hesklang['nti2'];
}
?></p>

<p><?php echo $hesklang['nti3']; ?><br />&nbsp;</p>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
	<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornerstop"></td>
	<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
</tr>
<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>

	<h3 align="center"><?php echo $hesklang['nti2']; ?></h3>

	<p align="center"><?php echo $hesklang['req_marked_with']; ?> <font class="important">*</font></p>

    <!-- START FORM -->

	<form method="post" action="admin_submit_ticket.php" name="form1" enctype="multipart/form-data">

	<!-- Contact info -->
	<table border="0" width="100%">
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['name']; ?>: <font class="important">*</font></td>
	<td width="80%"><input type="text" name="name" size="40" maxlength="50" value="<?php if (isset($_SESSION['as_name'])) {echo stripslashes(hesk_input($_SESSION['as_name']));} ?>" <?php if (in_array('name',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> /></td>
	</tr>
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['email'] . ': ' . ($hesk_settings['require_email'] ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%"><input type="text" name="email" id="email" size="40" maxlength="1000" value="<?php if (isset($_SESSION['as_email'])) {echo stripslashes(hesk_input($_SESSION['as_email']));} ?>" <?php if (in_array('email',$_SESSION['iserror'])) {echo ' class="isError" ';} elseif (in_array('email',$_SESSION['isnotice'])) {echo ' class="isNotice" ';} ?> <?php if($hesk_settings['detect_typos']) { echo ' onblur="Javascript:hesk_suggestEmail(\'email\', \'email_suggestions\', 1, 1)"'; } ?> /></td>
	</tr>
	</table>

    <div id="email_suggestions"></div> 

	<hr />

	<!-- Priority -->
	<table border="0" width="100%">
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['priority']; ?>: <font class="important">*</font></td>
	<td width="80%"><select name="priority" <?php if (in_array('priority',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> >
	<?php
	// Show the "Click to select"?
	if ($hesk_settings['select_pri'])
	{
       	echo '<option value="">'.$hesklang['select'].'</option>';
	}
	?>
	<option value="3" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==3) {echo 'selected="selected"';} ?>><?php echo $hesklang['low']; ?></option>
	<option value="2" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==2) {echo 'selected="selected"';} ?>><?php echo $hesklang['medium']; ?></option>
	<option value="1" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==1) {echo 'selected="selected"';} ?>><?php echo $hesklang['high']; ?></option>
	<option value="0" <?php if(isset($_SESSION['as_priority']) && $_SESSION['as_priority']==0) {echo 'selected="selected"';} ?>><?php echo $hesklang['critical']; ?></option>
	</select></td>
	</tr>
	</table>

	<hr />

	<!-- START CUSTOM BEFORE -->
	<?php
	/* custom fields BEFORE comments */

	$print_table = 0;

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use'] && $v['place']==0 && hesk_is_custom_field_in_category($k, $category) )
	    {
	    	if ($print_table == 0)
	        {
	        	echo '<table border="0" width="100%">';
	        	$print_table = 1;
	        }

			$v['req'] = $v['req']==2 ? '<font class="important">*</font>' : '';

			if ($v['type'] == 'checkbox')
            {
            	$k_value = array();
                if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"]))
                {
	                foreach ($_SESSION["as_$k"] as $myCB)
	                {
	                	$k_value[] = stripslashes(hesk_input($myCB));
	                }
                }
            }
            elseif (isset($_SESSION["as_$k"]))
            {
            	$k_value  = stripslashes(hesk_input($_SESSION["as_$k"]));
            }
            else
            {
            	$k_value  = '';
            }

	        switch ($v['type'])
	        {
	        	/* Radio box */
	        	case 'radio':
					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%">';

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

	                foreach ($v['value']['radio_options'] as $option)
	                {
		            	if (strlen($k_value) == 0)
		                {
	                    	$k_value = $option;
                            $checked = empty($v['value']['no_default']) ? 'checked="checked"' : '';
	                    }
		            	elseif ($k_value == $option)
		                {
	                    	$k_value = $option;
							$checked = 'checked="checked"';
	                    }
	                    else
	                    {
	                    	$checked = '';
	                    }

	                	echo '<label><input type="radio" name="'.$k.'" value="'.$option.'" '.$checked.' '.$cls.' /> '.$option.'</label><br />';
	                }

	                echo '</td>
					</tr>
					';
	            break;

	            /* Select drop-down box */
	            case 'select':

                	$cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%"><select name="'.$k.'" '.$cls.'>';

					// Show "Click to select"?
					if ( ! empty($v['value']['show_select']))
					{
                    	echo '<option value="">'.$hesklang['select'].'</option>';
					}

	                foreach ($v['value']['select_options'] as $option)
	                {
		            	if ($k_value == $option)
		                {
	                    	$k_value = $option;
	                        $selected = 'selected="selected"';
		                }
	                    else
	                    {
	                    	$selected = '';
	                    }

	                	echo '<option '.$selected.'>'.$option.'</option>';
	                }

	                echo '</select></td>
					</tr>
					';
	            break;

	            /* Checkbox */
	        	case 'checkbox':
					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%">';

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

	                foreach ($v['value']['checkbox_options'] as $option)
	                {
		            	if (in_array($option,$k_value))
		                {
							$checked = 'checked="checked"';
	                    }
	                    else
	                    {
	                    	$checked = '';
	                    }

	                	echo '<label><input type="checkbox" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$cls.' /> '.$option.'</label><br />';
	                }

	                echo '</td>
					</tr>
					';
	            break;

	            /* Large text box */
	            case 'textarea':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><textarea name="'.$k.'" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" '.$cls.'>'.$k_value.'</textarea></td>
					</tr>
	                ';
	            break;

	            // Date
	            case 'date':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" value="'.$k_value.'" class="tcal'.(in_array($k,$_SESSION['iserror']) ? ' isError' : '').'" size="10" '.$cls.' /></td>
					</tr>
					';
	            break;

	            // Email
	            case 'email':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                    $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$cls.' '.$suggest.' />
                    	<div id="'.$k.'_suggestions"></div>
                    </td>
					</tr>
					';
	            break;

                // Hidden
                // Handle as text fields for staff

	            /* Default text input */
	            default:
                	if (strlen($k_value) != 0 || isset($_SESSION["as_$k"]))
                    {
                    	$v['value']['default_value'] = $k_value;
                    }

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'" '.$cls.' /></td>
					</tr>
					';
	        }
	    }
	}

	/* If table was started we need to close it */
	if ($print_table)
	{
		echo '</table> <hr />';
		$print_table = 0;
	}
	?>
	<!-- END CUSTOM BEFORE -->

	<!-- ticket info -->
	<table border="0" width="100%">
	<?php
	// Lets handle ticket templates
	$can_options = '';

	// Get ticket templates from the database
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` ORDER BY `tpl_order` ASC");

	// If we have any templates print them out
	if ( hesk_dbNumRows($res) )
	{
		?>
		<script language="javascript" type="text/javascript"><!--
		// -->
		var myMsgTxt = new Array();
		var mySubjectTxt = new Array();
		myMsgTxt[0]='';
		mySubjectTxt[0]='';

		<?php
		while ($mysaved = hesk_dbFetchRow($res))
		{
			$can_options .= '<option value="' . $mysaved[0] . '">' . $mysaved[1]. "</option>\n";
			echo 'myMsgTxt['.$mysaved[0].']=\''.str_replace("\r\n","\\r\\n' + \r\n'", addslashes($mysaved[2]))."';\n";
			echo 'mySubjectTxt['.$mysaved[0].']=\''.str_replace("\r\n","\\r\\n' + \r\n'", addslashes($mysaved[1]))."';\n";
		}

		?>

		function setMessage(msgid)
		{
			var myMsg=myMsgTxt[msgid];
			var mySubject=mySubjectTxt[msgid];

			if (myMsg == '')
			{
				if (document.form1.mode[1].checked)
				{
					document.getElementById('message').value = '';
					document.getElementById('subject').value = '';
				}
				return true;
			}
			if (document.getElementById)
			{
				if (document.getElementById('moderep').checked)
				{
					document.getElementById('HeskMsg').innerHTML='<textarea name="message" id="message" rows="12" cols="60">'+myMsg+'</textarea>';
					document.getElementById('HeskSub').innerHTML='<input type="text" name="subject" id="subject" size="40" maxlength="70" value="'+mySubject+'" />';
				}
				else
				{
					var oldMsg = document.getElementById('message').value;
					document.getElementById('HeskMsg').innerHTML='<textarea name="message" id="message" rows="12" cols="60">'+oldMsg+myMsg+'</textarea>';
					if (document.getElementById('subject').value == '')
					{
						document.getElementById('HeskSub').innerHTML='<input type="text" name="subject" id="subject" size="40" maxlength="70" value="'+mySubject+'" />';
					}
				}
			}
			else
			{
				if (document.form1.mode[0].checked)
				{
					document.form1.message.value=myMsg;
					document.form1.subject.value=mySubject;
				}
				else
				{
					var oldMsg = document.form1.message.value;
					document.form1.message.value=oldMsg+myMsg;
					if (document.form1.subject.value == '')
					{
						document.form1.subject.value=mySubject;
					}
				}
			}

		}
		//-->
		</script>
	<?php
	} // END fetchrows

	// Print templates
	if ( strlen($can_options) )
	{
		?>
		<tr>
		<td style="text-align:right" width="150" valign="top">&nbsp;</td>
		<td width="80%">
		<div align="center">
			<table class="white" style="width:100%">
				<tr>
					<td class="admin_gray" colspan="2"><b>&raquo;<?php echo $hesklang['ticket_tpl']; ?></b> <?php echo hesk_checkPermission('can_man_ticket_tpl', 0) ? '(<a href="manage_ticket_templates.php">' . $hesklang['ticket_tpl_man'] . '</a>)' : ''; ?></td>
				</tr>
				<tr>
					<td class="admin_gray">
					<label><input type="radio" name="mode" id="modeadd" value="1" checked="checked" /> <?php echo $hesklang['madd']; ?></label><br />
					<label><input type="radio" name="mode" id="moderep" value="0" /> <?php echo $hesklang['mrep']; ?></label>
					</td>
					<td class="admin_gray">
					<?php echo $hesklang['select_ticket_tpl']; ?>:<br />
					<select name="saved_replies" onchange="setMessage(this.value)">
					<option value="0"> - <?php echo $hesklang['select_empty']; ?> - </option>
					<?php echo $can_options; ?>
					</select>
					</td>
				</tr>
			</table>
		</div>
		</td>
		</tr>
		<?php
	} // END printing templates
	elseif ( hesk_checkPermission('can_man_ticket_tpl', 0) )
	{
		?>
		<tr>
		<td style="text-align:right" width="150">&nbsp;</td>
		<td width="80%"><a href="manage_ticket_templates.php"><?php echo $hesklang['ticket_tpl_man']; ?></a></td>
		</tr>
		<?php
	}
	?>
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['subject'] . ': ' . ($hesk_settings['require_subject']==1 ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%"><span id="HeskSub"><input type="text" name="subject" id="subject" size="40" maxlength="70" value="<?php if (isset($_SESSION['as_subject'])) {echo stripslashes(hesk_input($_SESSION['as_subject']));} ?>" <?php if (in_array('subject',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> /></span></td>
	</tr>
	<tr>
	<td style="text-align:right" width="150" valign="top"><?php echo $hesklang['message'] . ': ' . ($hesk_settings['require_message']==1 ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%">
	<span id="HeskMsg"><textarea name="message" id="message" rows="12" cols="60" <?php if (in_array('message',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> ><?php if (isset($_SESSION['as_message'])) {echo stripslashes(hesk_input($_SESSION['as_message']));} ?></textarea></span>
	</td>
	</tr>
	</table>

	<hr />

	<!-- START CUSTOM AFTER -->
	<?php
	/* custom fields AFTER comments */
	$print_table = 0;

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use'] && $v['place']==1 && hesk_is_custom_field_in_category($k, $category) )
	    {
	    	if ($print_table == 0)
	        {
	        	echo '<table border="0" width="100%">';
	        	$print_table = 1;
	        }

			$v['req'] = $v['req']==2 ? '<font class="important">*</font>' : '';

			if ($v['type'] == 'checkbox')
            {
            	$k_value = array();
                if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"]))
                {
	                foreach ($_SESSION["as_$k"] as $myCB)
	                {
	                	$k_value[] = stripslashes(hesk_input($myCB));
	                }
                }
            }
            elseif (isset($_SESSION["as_$k"]))
            {
            	$k_value  = stripslashes(hesk_input($_SESSION["as_$k"]));
            }
            else
            {
            	$k_value  = '';
            }

	        switch ($v['type'])
	        {
	        	/* Radio box */
	        	case 'radio':
					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%">';

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

	                foreach ($v['value']['radio_options'] as $option)
	                {
		            	if (strlen($k_value) == 0)
		                {
	                    	$k_value = $option;
                            $checked = empty($v['value']['no_default']) ? 'checked="checked"' : '';
	                    }
		            	elseif ($k_value == $option)
		                {
	                    	$k_value = $option;
							$checked = 'checked="checked"';
	                    }
	                    else
	                    {
	                    	$checked = '';
	                    }

	                	echo '<label><input type="radio" name="'.$k.'" value="'.$option.'" '.$checked.' '.$cls.' /> '.$option.'</label><br />';
	                }

	                echo '</td>
					</tr>
					';
	            break;

	            /* Select drop-down box */
	            case 'select':

                	$cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%"><select name="'.$k.'" '.$cls.'>';

					// Show "Click to select"?
					if ( ! empty($v['value']['show_select']))
					{
                    	echo '<option value="">'.$hesklang['select'].'</option>';
					}

	                foreach ($v['value']['select_options'] as $option)
	                {
		            	if ($k_value == $option)
		                {
	                    	$k_value = $option;
	                        $selected = 'selected="selected"';
		                }
	                    else
	                    {
	                    	$selected = '';
	                    }

	                	echo '<option '.$selected.'>'.$option.'</option>';
	                }

	                echo '</select></td>
					</tr>
					';
	            break;

	            /* Checkbox */
	        	case 'checkbox':
					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
	                <td width="80%">';

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

	                foreach ($v['value']['checkbox_options'] as $option)
	                {
		            	if (in_array($option,$k_value))
		                {
							$checked = 'checked="checked"';
	                    }
	                    else
	                    {
	                    	$checked = '';
	                    }

	                	echo '<label><input type="checkbox" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$cls.' /> '.$option.'</label><br />';
	                }

	                echo '</td>
					</tr>
					';
	            break;

	            /* Large text box */
	            case 'textarea':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150" valign="top">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><textarea name="'.$k.'" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" '.$cls.'>'.$k_value.'</textarea></td>
					</tr>
	                ';
	            break;

	            // Date
	            case 'date':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" value="'.$k_value.'" class="tcal'.(in_array($k,$_SESSION['iserror']) ? ' isError' : '').'" size="10" '.$cls.' /></td>
					</tr>
					';
	            break;

	            // Email
	            case 'email':
                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

                    $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 1'.($v['value']['multiple'] ? ',1' : '').')"' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" '.$cls.' '.$suggest.' />
                    	<div id="'.$k.'_suggestions"></div>
                    </td>
					</tr>
					';
	            break;

                // Hidden
                // Handle as text fields for staff

	            /* Default text input */
	            default:
                	if (strlen($k_value) != 0 || isset($_SESSION["as_$k"]))
                    {
                    	$v['value']['default_value'] = $k_value;
                    }

                    $cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'" '.$cls.' /></td>
					</tr>
					';
	        }
	    }
	}

	/* If table was started we need to close it */
	if ($print_table)
	{
		echo '</table> <hr />';
		$print_table = 0;
	}
	?>
	<!-- END CUSTOM AFTER -->

	<?php
	/* attachments */
	if ($hesk_settings['attachments']['use']) {

	?>
	<table border="0" width="100%">
	<tr>
	<td style="text-align:right" width="150" valign="top"><?php echo $hesklang['attachments']; ?>:</td>
	<td width="80%" valign="top">
	<?php
	for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
    {
    	$cls = ($i == 1 && in_array('attachments',$_SESSION['iserror'])) ? ' class="isError" ' : '';
		echo '<input type="file" name="attachment['.$i.']" size="50" '.$cls.' /><br />';
	}
	?>
	<a href="Javascript:void(0)" onclick="Javascript:hesk_window('../file_limits.php',250,500);return false;"><?php echo $hesklang['ful']; ?></a>
	</td>
	</tr>
	</table>

	<hr />
	<?php
	}
	?>

    <!-- Admin options -->
	<?php
	if ( ! isset($_SESSION['as_notify']) )
	{
		$_SESSION['as_notify'] = $_SESSION['notify_customer_new'] ? 1 : 0;
	}
	?>
	<table border="0" width="100%">
	<tr>
	<td style="text-align:right" width="150" valign="top"><b><?php echo $hesklang['addop']; ?>:</b></td>
	<td width="80%">
    	<label><input type="checkbox" name="notify" value="1" <?php echo empty($_SESSION['as_notify']) ? '' : 'checked="checked"'; ?> /> <?php echo $hesklang['seno']; ?></label><br />
        <label><input type="checkbox" name="show" value="1" <?php echo (!isset($_SESSION['as_show']) || !empty($_SESSION['as_show'])) ? 'checked="checked"' : ''; ?> /> <?php echo $hesklang['otas']; ?></label><br />
        <hr />
    </td>
	</tr>

	<?php
	if (hesk_checkPermission('can_assign_others',0))
	{
    ?>
	<tr>
	<td style="text-align:right" width="150" valign="top"><b><?php echo $hesklang['owner']; ?>:</b></td>
	<td width="80%">
		<?php echo $hesklang['asst2']; ?> <select name="owner" <?php if (in_array('owner',$_SESSION['iserror'])) {echo ' class="isError" ';} ?>>
		<option value="-1"> &gt; <?php echo $hesklang['unas']; ?> &lt; </option>
		<?php

		if ($hesk_settings['autoassign'])
		{
			echo '<option value="-2"> &gt; ' . $hesklang['aass'] . ' &lt; </option>';
		}

        $owner = isset($_SESSION['as_owner']) ? intval($_SESSION['as_owner']) : 0;

		foreach ($admins as $k=>$v)
		{
			if ($k == $owner)
			{
				echo '<option value="'.$k.'" selected="selected">'.$v.'</option>';
			}
            else
			{
				echo '<option value="'.$k.'">'.$v.'</option>';
			}

		}
		?>
		</select>
    </td>
	</tr>
    <?php
	}
	elseif (hesk_checkPermission('can_assign_self',0))
	{
    $checked = (!isset($_SESSION['as_owner']) || !empty($_SESSION['as_owner'])) ? 'checked="checked"' : '';
	?>
	<tr>
	<td style="text-align:right" width="150" valign="top"><b><?php echo $hesklang['owner']; ?>:</b></td>
	<td width="80%">
    	<label><input type="checkbox" name="assing_to_self" value="1" <?php echo $checked; ?> /> <?php echo $hesklang['asss2']; ?></label><br />
    </td>
	</tr>
    <?php
	}
	?>
	</table>

    <hr />

	<!-- Submit -->
	<p align="center"><input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
	<input type="hidden" name="category" value="<?php echo $category; ?>" />
    <input type="submit" value="<?php echo $hesklang['sub_ticket']; ?>" class="orangebutton"  onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /></p>

	</form>

    <!-- END FORM -->

	</td>
	<td class="roundcornersright">&nbsp;</td>
</tr>
<tr>
	<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
</tr>
</table>
<?php

hesk_cleanSessionVars('iserror');
hesk_cleanSessionVars('isnotice');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function print_select_category($number_of_categories)
{
	global $hesk_settings, $hesklang;

	// A categoy needs to be selected
	if (isset($_GET['category']) && empty($_GET['category']))
	{
		hesk_process_messages($hesklang['sel_app_cat'],'NOREDIRECT','NOTICE');
	}
?>

</td>
</tr>
<tr>
<td>

&nbsp;<br />

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

<div style="text-align: center">

	<h3><?php echo $hesklang['select_category_staff']; ?></h3>

	<div class="select_category">
		<?php
		// Print a select box if number of categories is large
		if ($number_of_categories > $hesk_settings['cat_show_select'])
		{
			?>
			<form action="new_ticket.php" method="get">
				<select name="category" id="select_category">
				<?php
				if ($hesk_settings['select_cat'])
				{
					echo '<option value="">'.$hesklang['select'].'</option>';
				}
				foreach ($hesk_settings['categories'] as $k=>$v)
				{
					echo '<option value="'.$k.'">'.$v.'</option>';
				}
				?>
				</select>

				&nbsp;<br />

				<div style="text-align:center">
				<input type="submit" value="<?php echo $hesklang['c2c']; ?>" class="orangebutton"  onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
				</div>
			</form>
			<?php
		}
		// Otherwise print quick links
		else
		{
			?>
			<ul id="ul_category">
			<?php
			foreach ($hesk_settings['categories'] as $k=>$v)
			{
				echo '<li><a href="new_ticket.php?a=add&amp;category='.$k.'">&raquo; '.$v.'</a></li>';
			}
			?>
			</ul>
			<?php
		}
		?>
	</div>
</div>

<p>&nbsp;</p>

<?php

	hesk_cleanSessionVars('iserror');
	hesk_cleanSessionVars('isnotice');

	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // END print_select_category()
