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

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

// Are we in "Knowledgebase only" mode?
hesk_check_kb_only();

// What should we do?
$action = hesk_REQUEST('a');

switch ($action)
{
	case 'add':
		hesk_session_start();
        print_add_ticket();
	    break;

	case 'forgot_tid':
		hesk_session_start();
        forgot_tid();
	    break;

	default:
		print_start();
}

// Print footer
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

/*** START FUNCTIONS ***/


function print_select_category($number_of_categories)
{
	global $hesk_settings, $hesklang;

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['select_category'];
	require_once(HESK_PATH . 'inc/header.inc.php');

	// A categoy needs to be selected
	if (isset($_GET['category']) && empty($_GET['category']))
	{
		hesk_process_messages($hesklang['sel_app_cat'],'NOREDIRECT','NOTICE');
	}
?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
<td class="headersm"><?php hesk_showTopBar($hesklang['submit_ticket']); ?></td>
<td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
</tr>
</table>

<table width="100%" border="0" cellspacing="0" cellpadding="3">
<tr>
<td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
<a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a> &gt;
<?php echo $hesklang['submit_ticket']; ?></span></td>
</tr>
</table>

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

	<h3><?php echo $hesklang['select_category_text']; ?></h3>

	<div class="select_category">
		<?php
		// Print a select box if number of categories is large
		if ($number_of_categories > $hesk_settings['cat_show_select'])
		{
			?>
			<form action="index.php" method="get">
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
				<input type="hidden" name="a" value="add" />
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
				echo '<li><a href="index.php?a=add&amp;category='.$k.'">&raquo; '.$v.'</a></li>';
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
	return true;
} // END print_select_category()


function print_add_ticket()
{
	global $hesk_settings, $hesklang;

	// Connect to the database
	hesk_load_database_functions();
	hesk_dbConnect();

	// Load custom fields
	require_once(HESK_PATH . 'inc/custom_fields.inc.php');

	// Load calendar JS and CSS
    define('CALENDAR',1);

	// Auto-focus first empty or error field
	define('AUTOFOCUS', true);

	// Pre-populate fields
	// Customer name
	if ( isset($_REQUEST['name']) )
	{
		$_SESSION['c_name'] = $_REQUEST['name'];
	}

	// Customer email address
	if ( isset($_REQUEST['email']) )
	{
		$_SESSION['c_email']  = $_REQUEST['email'];
		$_SESSION['c_email2'] = $_REQUEST['email'];
	}

	// Priority
	if ( isset($_REQUEST['priority']) )
	{
		$_SESSION['c_priority'] = intval($_REQUEST['priority']);
	}

	// Subject
	if ( isset($_REQUEST['subject']) )
	{
		$_SESSION['c_subject'] = $_REQUEST['subject'];
	}

	// Message
	if ( isset($_REQUEST['message']) )
	{
		$_SESSION['c_message'] = $_REQUEST['message'];
	}

	// Custom fields
	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use']==1 && isset($_REQUEST[$k]) )
		{
			$_SESSION['c_'.$k] = $_REQUEST[$k];
		}
	}

	// Varibles for coloring the fields in case of errors
	if ( ! isset($_SESSION['iserror']))
	{
		$_SESSION['iserror'] = array();
	}

	if ( ! isset($_SESSION['isnotice']))
	{
		$_SESSION['isnotice'] = array();
	}

	hesk_cleanSessionVars('already_submitted');

	// Tell header to load reCaptcha API if needed
	if ($hesk_settings['recaptcha_use'])
	{
		define('RECAPTCHA',1);
	}

	// Get categories
	$hesk_settings['categories'] = array();
	$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `type`='0' ORDER BY `cat_order` ASC");
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

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['submit_ticket'];
	require_once(HESK_PATH . 'inc/header.inc.php');
	?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
<td class="headersm"><?php hesk_showTopBar($hesklang['submit_ticket']); ?></td>
<td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
</tr>
</table>

<table width="100%" border="0" cellspacing="0" cellpadding="3">
<tr>
<td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
<a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a> &gt;
<?php
if ($number_of_categories > 1)
{
	?>
	<a href="index.php?a=add" class="smaller"><?php echo $hesklang['submit_ticket']; ?></a> &gt;
	<?php echo $hesk_settings['categories'][$category];
}
else
{
	echo $hesklang['submit_ticket'];
}
?>
</span></td>
</tr>
</table>

</td>
</tr>
<tr>
<td>

<?php
// This will handle error, success and notice messages
hesk_handle_messages();
?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
	<td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornerstop"></td>
	<td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
</tr>
<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>
    <!-- START FORM -->

	<p style="text-align:center"><?php echo $hesklang['use_form_below']; ?> <font class="important"> *</font></p>

	<form method="post" action="submit_ticket.php?submit=1" name="form1" id="form1" enctype="multipart/form-data">

	<!-- Contact info -->
	<table border="0" width="100%">
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['name']; ?>: <font class="important">*</font></td>
	<td width="80%"><input type="text" name="name" size="40" maxlength="50" value="<?php if (isset($_SESSION['c_name'])) {echo stripslashes(hesk_input($_SESSION['c_name']));} ?>" <?php if (in_array('name',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> /></td>
	</tr>
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['email'] . ': ' . ($hesk_settings['require_email'] ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%"><input type="text" name="email" id="email" size="40" maxlength="1000" value="<?php if (isset($_SESSION['c_email'])) {echo stripslashes(hesk_input($_SESSION['c_email']));} ?>" <?php if (in_array('email',$_SESSION['iserror'])) {echo ' class="isError" ';} elseif (in_array('email',$_SESSION['isnotice'])) {echo ' class="isNotice" ';} ?> <?php if($hesk_settings['detect_typos']) { echo ' onblur="Javascript:hesk_suggestEmail(\'email\', \'email_suggestions\', 1, 0)"'; } ?> /></td>
	</tr>
    <?php
    if ($hesk_settings['confirm_email'])
    {
	    ?>
		<tr>
		<td style="text-align:right" width="150"><?php echo $hesklang['confemail'] . ': ' . ($hesk_settings['require_email'] ? '<font class="important">*</font>' : '') ; ?></td>
		<td width="80%"><input type="text" name="email2" size="40" maxlength="1000" value="<?php if (isset($_SESSION['c_email2'])) {echo stripslashes(hesk_input($_SESSION['c_email2']));} ?>" <?php if (in_array('email2',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> /></td>
		</tr>
	    <?php
    } // End if $hesk_settings['confirm_email']
    ?>
	</table>

	<div id="email_suggestions"></div>

	<!-- Priority -->

    <?php
	/* Can customer assign urgency? */
	if ($hesk_settings['cust_urgency'])
	{
		?>
        <hr />

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
		<option value="3" <?php if(isset($_SESSION['c_priority']) && $_SESSION['c_priority']==3) {echo 'selected="selected"';} ?>><?php echo $hesklang['low']; ?></option>
		<option value="2" <?php if(isset($_SESSION['c_priority']) && $_SESSION['c_priority']==2) {echo 'selected="selected"';} ?>><?php echo $hesklang['medium']; ?></option>
		<option value="1" <?php if(isset($_SESSION['c_priority']) && $_SESSION['c_priority']==1) {echo 'selected="selected"';} ?>><?php echo $hesklang['high']; ?></option>
		</select></td>
		</tr>
		</table>
		<?php
	}
	?>
	<!-- START CUSTOM BEFORE -->
	<?php

    $hidden_cf_buffer = '';

	/* custom fields BEFORE comments */

	$print_table = 0;

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use']==1 && $v['place']==0 && hesk_is_custom_field_in_category($k, $category) )
	    {
	    	if ($print_table == 0)
	        {
	        	echo '
                <hr />
                <table border="0" width="100%">
                ';
	        	$print_table = 1;
	        }

			$v['req'] = $v['req'] ? '<font class="important">*</font>' : '';

			if ($v['type'] == 'checkbox')
            {
            	$k_value = array();
                if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"]))
                {
	                foreach ($_SESSION["c_$k"] as $myCB)
	                {
	                	$k_value[] = stripslashes(hesk_input($myCB));
	                }
                }
            }
            elseif (isset($_SESSION["c_$k"]))
            {
            	$k_value  = stripslashes(hesk_input($_SESSION["c_$k"]));
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

                    $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 0'.($v['value']['multiple'] ? ',1' : '').')"' : '';

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
                case 'hidden':
                    if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
                    {
                        $v['value']['default_value'] = $k_value;
                    }
                    $hidden_cf_buffer .= '<input type="hidden" name="'.$k.'" value="'.$v['value']['default_value'].'" />';
                break;

	            /* Default text input */
	            default:
                	if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
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
		echo '</table>';
		$print_table = 0;
	}
	?>
	<!-- END CUSTOM BEFORE -->

	<!-- ticket info -->
    <?php
    if ($hesk_settings['require_subject'] != -1 || $hesk_settings['require_message'] != -1)
    {
    ?>
    <hr />
	<table border="0" width="100%">
    <?php
    // Show subject field?
    if ($hesk_settings['require_subject'] != -1)
    {
    ?>
	<tr>
	<td style="text-align:right" width="150"><?php echo $hesklang['subject'] . ': ' . ($hesk_settings['require_subject'] ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%"><input type="text" name="subject" size="40" maxlength="70" value="<?php if (isset($_SESSION['c_subject'])) {echo stripslashes(hesk_input($_SESSION['c_subject']));} ?>" <?php if (in_array('subject',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> /></td>
	</tr>
    <?php
    }
    // Show message field?
    if ($hesk_settings['require_message'] != -1)
    {
    ?>
	<tr>
	<td style="text-align:right" width="150" valign="top"><?php echo $hesklang['message'] . ': ' . ($hesk_settings['require_message'] ? '<font class="important">*</font>' : '') ; ?></td>
	<td width="80%"><textarea name="message" rows="12" cols="60" <?php if (in_array('message',$_SESSION['iserror'])) {echo ' class="isError" ';} ?> ><?php if (isset($_SESSION['c_message'])) {echo stripslashes(hesk_input($_SESSION['c_message']));} ?></textarea>

		<!-- START KNOWLEDGEBASE SUGGEST -->
		<?php
		if (has_public_kb() && $hesk_settings['kb_recommendanswers'])
		{
			?>
			<div id="kb_suggestions" style="display:none">
            <br />&nbsp;<br />
			<img src="img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo $hesklang['lkbs']; ?></i>
			</div>

			<script language="Javascript" type="text/javascript"><!--
			hesk_suggestKB();
			//-->
			</script>
			<?php
		}
		?>
		<!-- END KNOWLEDGEBASE SUGGEST -->
    </td>
	</tr>
    <?php
    }
    ?>
	</table>
    <?php
    }
    ?>

	<!-- START CUSTOM AFTER -->
	<?php
	/* custom fields AFTER comments */

	$print_table = 0;

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use']==1 && $v['place']==1 && hesk_is_custom_field_in_category($k, $category) )
	    {
	    	if ($print_table == 0 && $v['type'] != 'hidden')
	        {
	        	echo '
                <hr />
                <table border="0" width="100%">
                ';
	        	$print_table = 1;
	        }

			$v['req'] = $v['req'] ? '<font class="important">*</font>' : '';

			if ($v['type'] == 'checkbox')
            {
            	$k_value = array();
                if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"]))
                {
	                foreach ($_SESSION["c_$k"] as $myCB)
	                {
	                	$k_value[] = stripslashes(hesk_input($myCB));
	                }
                }
            }
            elseif (isset($_SESSION["c_$k"]))
            {
            	$k_value  = stripslashes(hesk_input($_SESSION["c_$k"]));
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

                    $suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 0'.($v['value']['multiple'] ? ',1' : '').')"' : '';

					echo '
					<tr>
					<td style="text-align:right" width="150">'.$v['name:'].' '.$v['req'].'</td>
					<td width="80%"><input type="text" name="'.$k.'" id="'.$k.'" size="40" '.$cls.' '.$suggest.' />
                    	<div id="'.$k.'_suggestions"></div>
                    </td>
					</tr>
					';
	            break;

                // Hidden
                case 'hidden':
                    if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
                    {
                        $v['value']['default_value'] = $k_value;
                    }
                    $hidden_cf_buffer .= '<input type="hidden" name="'.$k.'" value="'.$v['value']['default_value'].'" />';
                break;

	            /* Default text input */
	            default:
                	if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
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
		echo '</table>';
		$print_table = 0;
	}
	?>
	<!-- END CUSTOM AFTER -->

	<?php
	/* attachments */
	if ($hesk_settings['attachments']['use'])
    {
	?>
    <hr />

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
	<a href="file_limits.php" target="_blank" onclick="Javascript:hesk_window('file_limits.php',250,500);return false;"><?php echo $hesklang['ful']; ?></a>

	</td>
	</tr>
	</table>
	<?php
	}

	if ($hesk_settings['question_use'] || $hesk_settings['secimg_use'])
    {
		?>

        <hr />

        <!-- Security checks -->
		<table border="0" width="100%">
		<?php
		if ($hesk_settings['question_use'])
	    {
			?>
			<tr>
			<td style="text-align:right;vertical-align:top" width="150"><?php echo $hesklang['verify_q']; ?> <font class="important">*</font></td>
			<td width="80%">
            <?php
        	$value = '';
        	if (isset($_SESSION['c_question']))
            {
	        	$value = stripslashes(hesk_input($_SESSION['c_question']));
            }
            $cls = in_array('question',$_SESSION['iserror']) ? ' class="isError" ' : '';
		    echo $hesk_settings['question_ask'].'<br /><input type="text" name="question" size="20" value="'.$value.'" '.$cls.'  />';
            ?><br />&nbsp;
	        </td>
			</tr>
            <?php
		}

        if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] != 1)
	    {
			?>
			<tr>
			<td style="text-align:right;vertical-align:top" width="150"><?php echo $hesklang['verify_i']; ?> <font class="important">*</font></td>
			<td width="80%">
			<?php
			// SPAM prevention verified for this session
			if (isset($_SESSION['img_verified']))
			{
				echo '<img src="'.HESK_PATH.'img/success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> '.$hesklang['vrfy'];
			}
			// Use reCAPTCHA V2?
			elseif ($hesk_settings['recaptcha_use'] == 2)
			{
				?>
				<div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>"></div>
				<?php
			}
			// At least use some basic PHP generated image (better than nothing)
			else
			{
				$cls = in_array('mysecnum',$_SESSION['iserror']) ? ' class="isError" ' : '';

				echo $hesklang['sec_enter'].'<br />&nbsp;<br /><img src="print_sec_img.php?'.rand(10000,99999).'" width="150" height="40" alt="'.$hesklang['sec_img'].'" title="'.$hesklang['sec_img'].'" border="1" name="secimg" style="vertical-align:text-bottom" /> '.
				'<a href="javascript:void(0)" onclick="javascript:document.form1.secimg.src=\'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);"><img src="img/reload.png" height="24" width="24" alt="'.$hesklang['reload'].'" title="'.$hesklang['reload'].'" border="0" style="vertical-align:text-bottom" /></a>'.
				'<br />&nbsp;<br /><input type="text" name="mysecnum" size="20" maxlength="5" '.$cls.' />';
			}
			?>
			</td>
			</tr>
			<?php
		}
		?>
		</table>

    <?php
    }
	?>

	<!-- Submit -->
    <?php
    if ($hesk_settings['submit_notice'])
    {
	    ?>

	    <hr />

		<div align="center">
		<table border="0">
		<tr>
		<td>

	    <b><?php echo $hesklang['before_submit']; ?></b>
	    <ul>
	    <li><?php echo $hesklang['all_info_in']; ?>.</li>
		<li><?php echo $hesklang['all_error_free']; ?>.</li>
	    </ul>


		<b><?php echo $hesklang['we_have']; ?>:</b>
	    <ul>
	    <li><?php echo hesk_htmlspecialchars(hesk_getClientIP()).' '.$hesklang['recorded_ip']; ?></li>
		<li><?php echo $hesklang['recorded_time']; ?></li>
		</ul>

		<p align="center"><input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
	    <input type="submit" value="<?php echo $hesklang['sub_ticket']; ?>" class="orangebutton"  onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" id="recaptcha-submit" /></p>

	    </td>
		</tr>
		</table>
		</div>
	    <?php
    } // End IF submit_notice
    else
    {
	    ?>
        &nbsp;<br />&nbsp;<br />
		<table border="0" width="100%">
		<tr>
		<td style="text-align:right" width="150">&nbsp;</td>
		<td width="80%"><input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
	    <input type="submit" value="<?php echo $hesklang['sub_ticket']; ?>" class="orangebutton"  onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" id="recaptcha-submit" /><br />
	    &nbsp;<br />&nbsp;</td>
		</tr>
		</table>
	    <?php
    } // End ELSE submit_notice

    // Print hidden custom fields
    echo $hidden_cf_buffer;
    ?>

	<input type="hidden" name="category" value="<?php echo $category; ?>" />
	<!-- Do not delete or modify the code below, it is used to detect simple SPAM bots -->
	<input type="hidden" name="hx" value="3" /><input type="hidden" name="hy" value="" />
	<!-- >
	<input type="text" name="phone" value="3" />
	< -->

    <?php
    // Use Invisible reCAPTCHA?
    if ($hesk_settings['secimg_use'] && $hesk_settings['recaptcha_use'] == 1 && ! isset($_SESSION['img_verified']))
    {
        ?>
        <div class="g-recaptcha" data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>" data-bind="recaptcha-submit" data-callback="recaptcha_submitForm"></div>
        <?php
    }
    ?>

	</form>

    <!-- END FORM -->
	</td>
	<td class="roundcornersright">&nbsp;</td>
</tr>
<tr>
	<td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
</tr>
</table>

<?php

hesk_cleanSessionVars('iserror');
hesk_cleanSessionVars('isnotice');

} // End print_add_ticket()


function print_start()
{
	global $hesk_settings, $hesklang;

    // Connect to database
    hesk_load_database_functions();
    hesk_dbConnect();

    // This will be used to determine how much space to print after KB
    $hesk_settings['kb_spacing'] = 4;

    // Include KB functionality only if we have any public articles
    has_public_kb();
    if ($hesk_settings['kb_enable'])
    {
        require(HESK_PATH . 'inc/knowledgebase_functions.inc.php');
    }
    else
    {
        $hesk_settings['kb_spacing'] += 2;
    }

	/* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
<td class="headersm"><?php hesk_showTopBar($hesk_settings['hesk_title']); ?></td>
<td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
</tr>
</table>

<table width="100%" border="0" cellspacing="0" cellpadding="3">
<tr>
<td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
<?php echo $hesk_settings['hesk_title']; ?></span>
</td>

	<?php
    // Print small search box
    if ($hesk_settings['kb_enable'])
    {
    	hesk_kbSearchSmall();
    }
	?>
    
</tr>
</table>

</td>
</tr>
<tr>
<td>

	<?php
	// Print large search box
    if ($hesk_settings['kb_enable'])
    {
		hesk_kbSearchLarge();
    }
    // Knowledgebase disabled, print an empty line for formatting
    else
    {
    	echo '&nbsp;';
    }

	// Service messages
	$res = hesk_dbQuery('SELECT `title`, `message`, `style` FROM `'.hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` WHERE `type`='0' AND (`language` IS NULL OR `language` LIKE '".hesk_dbEscape($hesk_settings['language'])."') ORDER BY `order` ASC");
	while ($sm=hesk_dbFetchAssoc($res))
	{
		hesk_service_message($sm);
	}
	?>

<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td width="50%">
<!-- START SUBMIT -->
	<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
	    <table width="100%" border="0" cellspacing="0" cellpadding="0">
	    <tr>
	    	<td width="1"><img src="img/newticket.png" alt="" width="60" height="60" /></td>
	        <td>
	        <p><b><a href="index.php?a=add"><?php echo $hesklang['sub_support']; ?></a></b><br />
            <?php echo $hesklang['open_ticket']; ?></p>
	        </td>
	    </tr>
	    </table>
		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>
<!-- END SUBMIT -->
</td>
<td width="1"><img src="img/blank.gif" width="5" height="1" alt="" /></td>
<td width="50%">
<!-- START VIEW -->
	<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
	    <table width="100%" border="0" cellspacing="0" cellpadding="0">
	    <tr>
	    	<td width="1"><img src="img/existingticket.png" alt="" width="60" height="60" /></td>
	        <td>
	        <p><b><a href="ticket.php"><?php echo $hesklang['view_existing']; ?></a></b><br />
            <?php echo $hesklang['vet']; ?></p>
	        </td>
	    </tr>
	    </table>
		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>
<!-- END VIEW -->
</td>
</tr>
</table>

<?php
if ($hesk_settings['kb_enable'])
{
	?>
	<br />

	<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>

        <p><span class="homepageh3"><?php echo $hesklang['kb_text']; ?></span></p>

        <?php

        /* Get list of top articles */
        hesk_kbTopArticles($hesk_settings['kb_index_popart']);

        /* Get list of latest articles */
        hesk_kbLatestArticles($hesk_settings['kb_index_latest']);

        ?>

        <p>&raquo; <b><a href="knowledgebase.php"><?php echo $hesklang['viewkb']; ?></a></b></p>

		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>

    <br />
	<?php
}

    // Print blank space after KB?
    if ($hesk_settings['kb_spacing'] > 0)
    {
        echo str_repeat('<p>&nbsp;</p>', $hesk_settings['kb_spacing']);
    }

	// Show a link to admin panel?
	if ($hesk_settings['alink'])
	{
		?>
		<p style="text-align:center"><a href="<?php echo $hesk_settings['admin_dir']; ?>/" class="smaller"><?php echo $hesklang['ap']; ?></a></p>
		<?php
	}

} // End print_start()


function forgot_tid()
{
	global $hesk_settings, $hesklang;

	require(HESK_PATH . 'inc/email_functions.inc.php');

	$email = hesk_emailCleanup( hesk_validateEmail( hesk_POST('email'), 'ERR' ,0) ) or hesk_process_messages($hesklang['enter_valid_email'],'ticket.php?remind=1');

	if ( isset($_POST['open_only']) )
	{
    	$hesk_settings['open_only'] = $_POST['open_only'] == 1 ? 1 : 0;
	}

	/* Get ticket(s) from database */
	hesk_load_database_functions();
	hesk_dbConnect();

    // Get tickets from the database
	$res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` FORCE KEY (`statuses`) WHERE ' . ($hesk_settings['open_only'] ? "`status` IN ('0','1','2','4','5') AND " : '') . ' ' . hesk_dbFormatEmail($email) . ' ORDER BY `status` ASC, `lastchange` DESC ');

	$num = hesk_dbNumRows($res);
	if ($num < 1)
	{
		if ($hesk_settings['open_only'])
        {
        	hesk_process_messages($hesklang['noopen'],'ticket.php?remind=1&e='.rawurlencode($email));
        }
        else
        {
        	hesk_process_messages($hesklang['tid_not_found'],'ticket.php?remind=1&e='.rawurlencode($email));
        }
	}

	$tid_list = '';
	$name = '';

    $email_param = $hesk_settings['email_view_ticket'] ? '&e='.rawurlencode($email) : '';

	while ($my_ticket=hesk_dbFetchAssoc($res))
	{
		$name = $name ? $name : hesk_msgToPlain($my_ticket['name'], 1, 0);
$tid_list .= "
$hesklang[trackID]: "	. $my_ticket['trackid'] . "
$hesklang[subject]: "	. hesk_msgToPlain($my_ticket['subject'], 1, 0) . "
$hesklang[status]: "	. hesk_get_status_name($my_ticket['status']) . "
$hesk_settings[hesk_url]/ticket.php?track={$my_ticket['trackid']}{$email_param}
";
	}

	/* Get e-mail message for customer */
	$msg = hesk_getEmailMessage('forgot_ticket_id','',0,0,1);
	$msg = str_replace('%%NAME%%',			$name,												$msg);
	$msg = str_replace('%%NUM%%',			$num,												$msg);
	$msg = str_replace('%%LIST_TICKETS%%',	$tid_list,											$msg);
	$msg = str_replace('%%SITE_TITLE%%',	hesk_msgToPlain($hesk_settings['site_title'], 1),	$msg);
	$msg = str_replace('%%SITE_URL%%',		$hesk_settings['site_url'],							$msg);

    $subject = hesk_getEmailSubject('forgot_ticket_id');

	/* Send e-mail */
	hesk_mail($email, $subject, $msg);

	/* Show success message */
	$tmp  = '<b>'.$hesklang['tid_sent'].'!</b>';
	$tmp .= '<br />&nbsp;<br />'.$hesklang['tid_sent2'].'.';
	$tmp .= '<br />&nbsp;<br />'.$hesklang['check_spambox'];
	hesk_process_messages($tmp,'ticket.php?e='.$email,'SUCCESS');
	exit();

} // End forgot_tid()


function has_public_kb($use_cache=1)
{
    global $hesk_settings;

    // Return if KB is disabled
    if ( ! $hesk_settings['kb_enable'])
    {
        return 0;
    }

    // Do we have a cached version available
    $cache_dir = $hesk_settings['cache_dir'].'/';
    $cache_file = $cache_dir . 'kb.cache.php';

    if ($use_cache && file_exists($cache_file))
    {
        require($cache_file);
        return $hesk_settings['kb_enable'];
    }

    // Make sure we have database connection
    hesk_load_database_functions();
    hesk_dbConnect();

    // Do we have any public articles at all?
    $res = hesk_dbQuery("SELECT `t1`.`id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` AS `t1`
                        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                        WHERE `t1`.`type`='0' AND `t2`.`type`='0' LIMIT 1");

    // If no public articles, disable the KB functionality
    if (hesk_dbNumRows($res) < 1)
    {
        $hesk_settings['kb_enable'] = 0;
    }

    // Try to cache results
    if ($use_cache && (is_dir($cache_dir) || ( @mkdir($cache_dir, 0777) && is_writable($cache_dir) ) ) )
    {
        // Is there an index.htm file?
        if ( ! file_exists($cache_dir.'index.htm'))
        {
            @file_put_contents($cache_dir.'index.htm', '');
        }

        // Write data
        @file_put_contents($cache_file, '<?php if (!defined(\'IN_SCRIPT\')) {die();} $hesk_settings[\'kb_enable\']=' . $hesk_settings['kb_enable'] . ';' );
    }

    return $hesk_settings['kb_enable'];

} // End has_public_kb()
