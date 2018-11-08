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

define('LOAD_TABS',1);

// Make sure the install folder is deleted
if (is_dir(HESK_PATH . 'install')) {die('Please delete the <b>install</b> folder from your server for security reasons then refresh this page!');}

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');

// Save the default language for the settings page before choosing user's preferred one
$hesk_settings['language_default'] = $hesk_settings['language'];
require(HESK_PATH . 'inc/common.inc.php');
$hesk_settings['language'] = $hesk_settings['language_default'];
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_man_settings');

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');

// Test languages function
if (isset($_GET['test_languages']))
{
	hesk_testLanguage(0);
}

$help_folder = '../language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/help_files/';

$enable_save_settings   = 0;
$enable_use_attachments = 0;

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

// Demo mode? Hide values of sensitive settings
if ( defined('HESK_DEMO') )
{
	$hesk_settings['db_host']			= $hesklang['hdemo'];
	$hesk_settings['db_name']			= $hesklang['hdemo'];
	$hesk_settings['db_user']			= $hesklang['hdemo'];
	$hesk_settings['db_pass']			= $hesklang['hdemo'];
	$hesk_settings['db_pfix']			= $hesklang['hdemo'];
	$hesk_settings['smtp_host_name']	= $hesklang['hdemo'];
	$hesk_settings['smtp_user']			= $hesklang['hdemo'];
	$hesk_settings['smtp_password']		= $hesklang['hdemo'];
	$hesk_settings['pop3_host_name']	= $hesklang['hdemo'];
	$hesk_settings['pop3_user']			= $hesklang['hdemo'];
	$hesk_settings['pop3_password']		= $hesklang['hdemo'];
	$hesk_settings['imap_host_name']	= $hesklang['hdemo'];
	$hesk_settings['imap_user']			= $hesklang['hdemo'];
	$hesk_settings['imap_password']		= $hesklang['hdemo'];
	$hesk_settings['recaptcha_public_key']	= $hesklang['hdemo'];
	$hesk_settings['recaptcha_private_key']	= $hesklang['hdemo'];
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

<h3><?php echo $hesklang['settings']; ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['settings_intro']) . '\n\n' . hesk_makeJsString($hesklang['all_req']); ?>')">?</a>]</h3>

&nbsp;

<?php
$hesklang['err_custname'] = addslashes($hesklang['err_custname']);
?>

<script language="javascript" type="text/javascript"><!--
function hesk_checkFields()
{
d=document.form1;

// GENERAL
if (d.s_site_title.value=='') {alert('<?php echo addslashes($hesklang['err_sname']); ?>'); return false;}
if (d.s_site_url.value=='') {alert('<?php echo addslashes($hesklang['err_surl']); ?>'); return false;}
if (d.s_webmaster_mail.value=='' || d.s_webmaster_mail.value.indexOf(".") == -1 || d.s_webmaster_mail.value.indexOf("@") == -1)
{alert('<?php echo addslashes($hesklang['err_wmmail']); ?>'); return false;}
if (d.s_noreply_mail.value=='' || d.s_noreply_mail.value.indexOf(".") == -1 || d.s_noreply_mail.value.indexOf("@") == -1)
{alert('<?php echo addslashes($hesklang['err_nomail']); ?>'); return false;}

if (d.s_db_host.value=='') {alert('<?php echo addslashes($hesklang['err_dbhost']); ?>'); return false;}
if (d.s_db_name.value=='') {alert('<?php echo addslashes($hesklang['err_dbname']); ?>'); return false;}
if (d.s_db_user.value=='') {alert('<?php echo addslashes($hesklang['err_dbuser']); ?>'); return false;}
if (d.s_db_pass.value=='')
{
	if (!confirm('<?php echo addslashes($hesklang['mysql_root']); ?>'))
    {
    	return false;
    }
}

// HELPDESK
if (d.s_hesk_title.value=='') {alert('<?php echo addslashes($hesklang['err_htitle']); ?>'); return false;}
if (d.s_hesk_url.value=='') {alert('<?php echo addslashes($hesklang['err_hurl']); ?>'); return false;}
if (d.s_max_listings.value=='') {alert('<?php echo addslashes($hesklang['err_max']); ?>'); return false;}
if (d.s_print_font_size.value=='') {alert('<?php echo addslashes($hesklang['err_psize']); ?>'); return false;}

// KNOWLEDGEBASE

// MISC

// DISABLE SUBMIT BUTTON
d.submitbutton.disabled=true;
d.submitbutton.value='<?php echo addslashes($hesklang['saving']); ?>';

return true;
}

function hesk_toggleLayer(nr,setto) {
        if (document.all)
                document.all[nr].style.display = setto;
        else if (document.getElementById)
                document.getElementById(nr).style.display = setto;
}

function hesk_testLanguage()
{
    window.open('admin_settings.php?test_languages=1',"Hesk_window","height=400,width=500,menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
    return false;
}

var tabberOptions = {

  'cookie':"tabber",
  'onLoad': function(argsObj)
  {
    var t = argsObj.tabber;
    var i;
    if (t.id) {
      t.cookie = t.id + t.cookie;
    }

    i = parseInt(getCookie(t.cookie));
    if (isNaN(i)) { return; }
    t.tabShow(i);
  },

  'onClick':function(argsObj)
  {
    var c = argsObj.tabber.cookie;
    var i = argsObj.index;
    setCookie(c, i);
  }
};

function checkRequiredEmail(field)
{
    if (document.getElementById('s_require_email_0').checked && document.getElementById('s_email_view_ticket').checked)
    {
        if (field == 's_require_email_0' && confirm('<?php echo addslashes($hesklang['re_confirm1']); ?>'))
        {
            document.getElementById('s_email_view_ticket').checked = false;
            return true;
        }
        else if (field == 's_email_view_ticket' && confirm('<?php echo addslashes($hesklang['re_confirm2']); ?>'))
        {
            document.getElementById('s_require_email_1').checked = true;
            return true;
        }
        return false;
    }
    return true;
}
//-->
</script>

<form method="post" action="admin_settings_save.php" name="form1" onsubmit="return hesk_checkFields()">

<!-- Checkign status of files and folders -->
<span class="section">&raquo; <?php echo $hesklang['check_status']; ?></span>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
	<td class="roundcornersleft">&nbsp;</td>
	<td>

	<table border="0">
	<tr>
	<td width="200" valign="top"><?php echo $hesklang['v']; ?>:</td>
	<td><b><?php echo $hesk_settings['hesk_version']; ?></b>

	<?php
	if ($hesk_settings['check_updates'])
	{
		$latest = hesk_checkVersion();

		if ($latest === true)
		{
			echo ' - <span style="color:green">' . $hesklang['hud'] . '</span> ';
		}
		elseif ($latest != -1)
		{
			// Is this a beta/dev version?
			if ( strpos($hesk_settings['hesk_version'], 'beta') || strpos($hesk_settings['hesk_version'], 'dev') || strpos($hesk_settings['hesk_version'], 'RC') )
			{
				echo ' <span style="color:darkorange">' . $hesklang['beta'] . '</span> '; ?> <a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
			}
			else
			{
				echo ' - <span style="color:darkorange;font-weight:bold">' . $hesklang['hnw'] . '</span> '; ?> <a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['getup']; ?></a><?php
			}
		}
		else
		{
			?> - <a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
		}
	}
	else
	{
		?> - <a href="https://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo $hesklang['check4updates']; ?></a><?php
	}
	?>

    </td>
	</tr>
	<tr>
	<td width="200" valign="top"><?php echo $hesklang['phpv']; ?>:</td>
	<td><?php echo defined('HESK_DEMO') ? $hesklang['hdemo'] : PHP_VERSION . ' ' . (function_exists('mysqli_connect') ? '(MySQLi)' : '(MySQL)'); ?></td>
	</tr>
	<tr>
	<td width="200" valign="top"><?php echo $hesklang['mysqlv']; ?>:</td>
	<td><?php echo defined('HESK_DEMO') ? $hesklang['hdemo'] : hesk_dbResult( hesk_dbQuery('SELECT VERSION() AS version') ); ?></td>
	</tr>
	<tr>
	<td width="200" valign="top">/hesk_settings.inc.php</td>
	<td>
	<?php
	if (is_writable(HESK_PATH . 'hesk_settings.inc.php')) {
	    $enable_save_settings=1;
	    echo '<font class="success">'.$hesklang['exists'].'</font>, <font class="success">'.$hesklang['writable'].'</font>';
	} else {
	    echo '<font class="success">'.$hesklang['exists'].'</font>, <font class="error">'.$hesklang['not_writable'].'</font><br />'.$hesklang['e_settings'];
	}
	?>
	</td>
	</tr>
	<tr>
	<td width="200">/<?php echo $hesk_settings['attach_dir']; ?></td>
	<td>
	<?php
    /*
	if (!file_exists(HESK_PATH . $hesk_settings['attach_dir']))
	{
	    @mkdir(HESK_PATH . $hesk_settings['attach_dir'], 0777);
	}
    */

	if (is_dir(HESK_PATH . $hesk_settings['attach_dir']))
	{
	    echo '<font class="success">'.$hesklang['exists'].'</font>, ';
	    if (is_writable(HESK_PATH . $hesk_settings['attach_dir']))
	    {
	        $enable_use_attachments=1;
	        echo '<font class="success">'.$hesklang['writable'].'</font>';
	    }
	    else
	    {
	        echo '<font class="error">'.$hesklang['not_writable'].'</font><br />'.$hesklang['e_attdir'];
	    }
	}
	else
	{
	    echo '<font class="error">'.$hesklang['no_exists'].'</font>, <font class="error">'.$hesklang['not_writable'].'</font><br />'.$hesklang['e_attdir'];
	}
	?>
	</td>
	</tr>
	<tr>
	<td width="200">/<?php echo $hesk_settings['cache_dir']; ?></td>
	<td>
	<?php
	if (is_dir(HESK_PATH . $hesk_settings['cache_dir']))
	{
	    echo '<font class="success">'.$hesklang['exists'].'</font>, ';
	    if (is_writable(HESK_PATH . $hesk_settings['cache_dir']))
	    {
	        $enable_use_attachments=1;
	        echo '<font class="success">'.$hesklang['writable'].'</font>';
	    }
	    else
	    {
	        echo '<font class="error">'.$hesklang['not_writable'].'</font><br />'.$hesklang['e_cdir'];
	    }
	}
	else
	{
	    echo '<font class="error">'.$hesklang['no_exists'].'</font>, <font class="error">'.$hesklang['not_writable'].'</font><br />'.$hesklang['e_cdir'];
	}
	?>
	</td>
	</tr>
	</table>

	<?php
	// Check file attachment limits
	if ($hesk_settings['attachments']['use'] && ! defined('HESK_DEMO') )
	{
		// Check number of attachments per post
		if ( version_compare(phpversion(), '5.2.12', '>=') && @ini_get('max_file_uploads') && @ini_get('max_file_uploads') < $hesk_settings['attachments']['max_number'] )
		{
			hesk_show_notice($hesklang['fatte1']);
		}

		// Check max attachment size
		$tmp = @ini_get('upload_max_filesize');
		if ($tmp)
		{
			$last = strtoupper(substr($tmp,-1));
			$number = substr($tmp, 0, -1);

			switch ($last)
			{
				case 'K':
					$tmp = $number * 1024;
					break;
				case 'M':
					$tmp = $number * 1048576;
					break;
				case 'G':
					$tmp = $number * 1073741824;
					break;
				default:
				$tmp = $number;
			}

			if ($tmp < $hesk_settings['attachments']['max_size'])
			{
				hesk_show_notice($hesklang['fatte2']);
			}
		}

		// Check max post size
		$tmp = @ini_get('post_max_size');
		if ($tmp)
		{
			$last = strtoupper(substr($tmp,-1));
			$number = substr($tmp, 0, -1);

			switch ($last)
			{
				case 'K':
					$tmp = $number * 1024;
					break;
				case 'M':
					$tmp = $number * 1048576;
					break;
				case 'G':
					$tmp = $number * 1073741824;
					break;
				default:
					$tmp = $number;
			}

			if ($tmp < ( $hesk_settings['attachments']['max_size'] * $hesk_settings['attachments']['max_number'] + 524288 ) )
			{
				hesk_show_notice($hesklang['fatte3']);
			}
		}

        // If SMTP server is used, "From email" should match SMTP username
        if ($hesk_settings['smtp'] && strtolower($hesk_settings['smtp_user']) != strtolower($hesk_settings['noreply_mail']) && hesk_validateEmail($hesk_settings['smtp_user'], 'ERR', 0))
        {
            hesk_show_notice(sprintf($hesklang['from_warning'], $hesklang['email_noreply'], $hesklang['tab_1'], $hesk_settings['smtp_user']));
        }

        // If POP3 fetching is active, no user should have the same email address
        if ($hesk_settings['pop3'] && hesk_validateEmail($hesk_settings['pop3_user'], 'ERR', 0))
        {
            $res = hesk_dbQuery("SELECT `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['pop3_user'])."'");

            if (hesk_dbNumRows($res) > 0)
            {
                hesk_show_notice(sprintf($hesklang['pop3_warning'], hesk_dbResult($res,0,0), $hesk_settings['pop3_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
            }
        }

        // If IMAP fetching is active, no user should have the same email address
        if ($hesk_settings['imap'] && hesk_validateEmail($hesk_settings['imap_user'], 'ERR', 0))
        {
            $res = hesk_dbQuery("SELECT `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['imap_user'])."'");

            if (hesk_dbNumRows($res) > 0)
            {
                hesk_show_notice(sprintf($hesklang['imap_warning'], hesk_dbResult($res,0,0), $hesk_settings['imap_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
            }
        }
	}
	?>

	</td>
	<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
	<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
	<td class="roundcornersbottom"></td>
	<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
</table>

<br />

<script language="Javascript" type="text/javascript" src="<?php echo HESK_PATH; ?>inc/tabs/tabber-minimized.js"></script>

<!-- TABS -->
<div class="tabber" id="tab1">

	<!-- GENERAL -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_1']; ?></h2>

		&nbsp;<br />

		<!-- Website info -->
		<span class="section">&raquo; <?php echo $hesklang['gs']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['wbst_title']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#1','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_site_title" size="40" maxlength="255" value="<?php echo $hesk_settings['site_title']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['wbst_url']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#2','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_site_url" size="40" maxlength="255" value="<?php echo $hesk_settings['site_url']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['hesk_title']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#6','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_hesk_title" size="40" maxlength="255" value="<?php echo $hesk_settings['hesk_title']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['hesk_url']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#7','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_hesk_url" size="40" maxlength="255" value="<?php echo $hesk_settings['hesk_url']; ?>" /></td>
		</tr>
		<tr>
		<td colspan="2">&nbsp;</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['email_wm']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#4','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_webmaster_mail" size="40" maxlength="255" value="<?php echo $hesk_settings['webmaster_mail']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['email_noreply']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#5','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_noreply_mail" size="40" maxlength="255" value="<?php echo $hesk_settings['noreply_mail']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['email_name']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#6','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_noreply_name" size="40" maxlength="255" value="<?php echo $hesk_settings['noreply_name']; ?>" /></td>
		</tr>
		</table>

		<br />

		<span class="section">&raquo; <?php echo $hesklang['lgs']; ?></span>

		<!-- Language -->
		<table border="0" width="100%">
		<tr>
		<td style="text-align:right;vertical-align:top" width="200"><?php echo $hesklang['hesk_lang']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#9','400','500')"><b>?</b></a>]</td>
		<td>
		<select name="s_language">
		<?php echo hesk_testLanguage(1); ?>
		</select>
		&nbsp;
		<a href="Javascript:void(0)" onclick="Javascript:return hesk_testLanguage()"><?php echo $hesklang['s_inl']; ?></a>
		</td>
		</tr>
		<tr>
		<td style="text-align:right;vertical-align:top;" width="200"><?php echo $hesklang['s_mlang']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#43','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['can_sel_lang'] ? 'checked="checked"' : '';
		$off = $hesk_settings['can_sel_lang'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_can_sel_lang" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_can_sel_lang" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		</table>

		<br />

		<!-- Database -->
		<span class="section">&raquo; <?php echo $hesklang['db']; ?></span>

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['db_host']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#32','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_db_host" id="m1" size="40" maxlength="255" value="<?php echo $hesk_settings['db_host']; ?>" autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['db_name']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#33','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_db_name" id="m2" size="40" maxlength="255" value="<?php echo $hesk_settings['db_name']; ?>" autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['db_user']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#34','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_db_user" id="m3" size="40" maxlength="255" value="<?php echo $hesk_settings['db_user']; ?>" autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['db_pass']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#35','400','500')"><b>?</b></a>]</td>
		<td><input type="password" name="s_db_pass" id="m4" size="40" maxlength="255" value="<?php echo $hesk_settings['db_pass'] ; ?>" autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['prefix']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>general.html#36','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_db_pfix" id="m5" size="40" maxlength="255" value="<?php echo $hesk_settings['db_pfix']; ?>" autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="hesk_testMySQL()" value="<?php echo $hesklang['mysqltest']; ?>" style="margin-top:4px" /></td>
		</tr>
		</table>

		<!-- START MYSQL TEST -->
		<div id="mysql_test" style="display:none">
		</div>

		<script language="Javascript" type="text/javascript"><!--
		function hesk_testMySQL()
		{
			var element = document.getElementById('mysql_test');
			element.innerHTML = '<img src="<?php echo HESK_PATH; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes($hesklang['contest']); ?></i>';
			element.style.display = 'block';

			var s_db_host = document.getElementById('m1').value;
			var s_db_name = document.getElementById('m2').value;
			var s_db_user = document.getElementById('m3').value;
			var s_db_pass = document.getElementById('m4').value;
			var s_db_pfix = document.getElementById('m5').value;

			var params = "test=mysql" +
						 "&s_db_host=" + encodeURIComponent( s_db_host ) +
						 "&s_db_name=" + encodeURIComponent( s_db_name ) +
						 "&s_db_user=" + encodeURIComponent( s_db_user ) +
						 "&s_db_pass=" + encodeURIComponent( s_db_pass ) +
						 "&s_db_pfix=" + encodeURIComponent( s_db_pfix );

			xmlHttp=GetXmlHttpObject();
			if (xmlHttp==null)
			{
				return;
			}

			xmlHttp.open('POST','test_connection.php',true);
			xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xmlHttp.setRequestHeader("Content-length", params.length);
			xmlHttp.setRequestHeader("Connection", "close");

			xmlHttp.onreadystatechange = function()
			{
				if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
				{
					element.innerHTML = xmlHttp.responseText;
				}
			}

			xmlHttp.send(params);
		}
		//-->
		</script>
		<!-- END MYSQL TEST -->

	</div>
	<!-- GENERAL -->

	<!-- HELP DESK -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_2']; ?></h2>

		&nbsp;<br />

        <!-- Help Desk -->
		<span class="section">&raquo; <?php echo $hesklang['hd']; ?></span>

        <table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['adf']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#61','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_admin_dir" size="40" maxlength="255" value="<?php echo $hesk_settings['admin_dir']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['atf']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#62','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_attach_dir" size="40" maxlength="255" value="<?php echo $hesk_settings['attach_dir']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['cf']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#77','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_cache_dir" size="40" maxlength="255" value="<?php echo $hesk_settings['cache_dir']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['max_listings']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#10','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_max_listings" size="5" maxlength="30" value="<?php echo $hesk_settings['max_listings']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['print_size']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#11','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_print_font_size" size="5" maxlength="3" value="<?php echo $hesk_settings['print_font_size']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['aclose']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#15','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_autoclose" size="5" maxlength="3" value="<?php echo $hesk_settings['autoclose']; ?>" />
		<?php echo $hesklang['aclose2']; ?></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['mop']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#58','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_max_open" size="5" maxlength="3" value="<?php echo $hesk_settings['max_open']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right;vertical-align:text-top" width="200"><?php echo $hesklang['rord']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#59','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['new_top'] ? 'checked="checked"' : '';
		$off = $hesk_settings['new_top'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_new_top" value="1" '.$on.' /> '.$hesklang['newtop'].'</label><br />
		<label><input type="radio" name="s_new_top" value="0" '.$off.' /> '.$hesklang['newbot'].'</label>';
		?>
        </td>
		</tr>
		<tr>
		<td style="text-align:right;vertical-align:text-top" width="200"><?php echo $hesklang['ford']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#60','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['reply_top'] ? 'checked="checked"' : '';
		$off = $hesk_settings['reply_top'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_reply_top" value="1" '.$on.' /> '.$hesklang['formtop'].'</label><br />
		<label><input type="radio" name="s_reply_top" value="0" '.$off.' /> '.$hesklang['formbot'].'</label>';
		?>
        </td>
		</tr>
        </table>

        <br />

        <!-- Features -->
		<span class="section">&raquo; <?php echo $hesklang['features']; ?></span>

        <table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['alo']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#44','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['autologin'] ? 'checked="checked"' : '';
		$off = $hesk_settings['autologin'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_autologin" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_autologin" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['saass']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#51','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['autoassign'] ? 'checked="checked"' : '';
		$off = $hesk_settings['autoassign'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_autoassign" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_autoassign" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
        <tr>
        <td style="text-align:right" width="200"><?php echo $hesklang['req_email']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#73','400','500')"><b>?</b></a>]</td>
        <td>
        <?php
        $on = $hesk_settings['require_email'] ? 'checked="checked"' : '';
        $off = $hesk_settings['require_email'] ? '' : 'checked="checked"';
        echo '
        <label><input type="radio" id="s_require_email_0" name="s_require_email" value="0" onclick="return checkRequiredEmail(\'s_require_email_0\');" '.$off.' /> '.$hesklang['off'].'</label> |
        <label><input type="radio" id="s_require_email_1" name="s_require_email" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
        ?>
        </td>
        </tr>        
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['fass']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#70','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['require_owner'] ? 'checked="checked"' : '';
		$off = $hesk_settings['require_owner'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_require_owner" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_require_owner" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
        <tr>
        <td style="text-align:right" width="200"><?php echo $hesklang['req_sub']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#72','400','500')"><b>?</b></a>]</td>
        <td>
        <?php
        $on = $hesk_settings['require_subject']==1 ? 'checked="checked"' : '';
        $off = $hesk_settings['require_subject']==0 ? 'checked="checked"' : '';
        $hide = $hesk_settings['require_subject']==-1 ? 'checked="checked"' : '';
        echo '
        <label><input type="radio" name="s_require_subject" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
        <label><input type="radio" name="s_require_subject" value="1" '.$on.' /> '.$hesklang['on'].'</label> &nbsp;|
        <label><input type="radio" name="s_require_subject" value="-1" '.$hide.' /> '.$hesklang['off-hide'].'</label>';
        ?>
        </td>
        </tr>
        <tr>
        <td style="text-align:right" width="200"><?php echo $hesklang['req_msg']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#74','400','500')"><b>?</b></a>]</td>
        <td>
        <?php
        $on = $hesk_settings['require_message']==1 ? 'checked="checked"' : '';
        $off = $hesk_settings['require_message']==0 ? 'checked="checked"' : '';
        $hide = $hesk_settings['require_message']==-1 ? 'checked="checked"' : '';
        echo '
        <label><input type="radio" name="s_require_message" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
        <label><input type="radio" name="s_require_message" value="1" '.$on.' /> '.$hesklang['on'].'</label> &nbsp;|
        <label><input type="radio" name="s_require_message" value="-1" '.$hide.' /> '.$hesklang['off-hide'].'</label>';
        ?>
        </td>
        </tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['ccct']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#67','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['custclose'] ? 'checked="checked"' : '';
		$off = $hesk_settings['custclose'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_custclose" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_custclose" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_ucrt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#16','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['custopen'] ? 'checked="checked"' : '';
		$off = $hesk_settings['custopen'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_custopen" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_custopen" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['urate']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#17','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['rating'] ? 'checked="checked"' : '';
		$off = $hesk_settings['rating'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_rating" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_rating" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['cpri']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#45','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['cust_urgency'] ? 'checked="checked"' : '';
		$off = $hesk_settings['cust_urgency'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_cust_urgency" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_cust_urgency" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['eseqid']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#49','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['sequential'] ? 'checked="checked"' : '';
		$off = $hesk_settings['sequential'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_sequential" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_sequential" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['ts']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#66','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['time_worked'] ? 'checked="checked"' : '';
		$off = $hesk_settings['time_worked'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_time_worked" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_time_worked" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['spamn']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#68','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['spam_notice'] ? 'checked="checked"' : '';
		$off = $hesk_settings['spam_notice'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_spam_notice" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_spam_notice" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['lu']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#14','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['list_users'] ? 'checked="checked"' : '';
		$off = $hesk_settings['list_users'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_list_users" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_list_users" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['debug_mode']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#12','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['debug_mode'] ? 'checked="checked"' : '';
		$off = $hesk_settings['debug_mode'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_debug_mode" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_debug_mode" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['shu']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#63','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['short_link'] ? 'checked="checked"' : '';
		$off = $hesk_settings['short_link'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_short_link" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_short_link" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200" valign="top"><?php echo $hesklang['select']; ?> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#65','400','500')"><b>?</b></a>]</td>
		<td>
		<label><input type="checkbox" name="s_select_cat" value="1" <?php if ($hesk_settings['select_cat']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['category']; ?></label><br />
		<label><input type="checkbox" name="s_select_pri" value="1" <?php if ($hesk_settings['select_pri']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['priority']; ?></label><br />
		</td>
		</tr>
        <tr>
        <td style="text-align:right" width="200"><?php echo $hesklang['scat']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#71','400','500')"><b>?</b></a>]</td>
        <td><input type="text" name="s_cat_show_select" size="5" maxlength="3" value="<?php echo $hesk_settings['cat_show_select']; ?>" />
        <?php echo $hesklang['scat2']; ?></td>
        </tr>
		</table>

		<br />

		<!-- SPAM prevention -->
		<span class="section">&raquo; <?php echo $hesklang['sp']; ?></span>

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200" valign="top"><?php echo $hesklang['use_secimg']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#13','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$onc = $hesk_settings['secimg_use'] == 1 ? 'checked="checked"' : '';
		$ons = $hesk_settings['secimg_use'] == 2 ? 'checked="checked"' : '';
		$off = $hesk_settings['secimg_use'] ? '' : 'checked="checked"';
        $div = $hesk_settings['secimg_use'] ? 'block' : 'none';

		echo '
		<label><input type="radio" name="s_secimg_use" value="0" '.$off.' onclick="javascript:hesk_toggleLayer(\'captcha\',\'none\')" /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_secimg_use" value="1" '.$onc.' onclick="javascript:hesk_toggleLayer(\'captcha\',\'block\')" /> '.$hesklang['onc'].'</label> |
		<label><input type="radio" name="s_secimg_use" value="2" '.$ons.' onclick="javascript:hesk_toggleLayer(\'captcha\',\'block\')" /> '.$hesklang['ons'].'</label>
		';

		?>
		<div id="captcha" style="display: <?php echo $div; ?>;">

        &nbsp;<br />

		<b><?php echo $hesklang['sit']; ?>:</b><br />

        <?php

		$on  = '';
		$on2 = '';
		$off = '';
		$div = 'block';

		if ($hesk_settings['recaptcha_use'] == 1)
		{
			$on = 'checked="checked"';
		}
		elseif ($hesk_settings['recaptcha_use'] == 2)
		{
			$on2 = 'checked="checked"';
		}
		else
		{
			$off = 'checked="checked"';
			$div = 'none';
		}
        ?>

		<label><input type="radio" name="s_recaptcha_use" value="0" onclick="javascript:hesk_toggleLayer('recaptcha','none')" <?php echo $off; ?> /> <?php echo $hesklang['sis']; ?></label> <br />
		<label><input type="radio" name="s_recaptcha_use" value="2" onclick="javascript:hesk_toggleLayer('recaptcha','block')" <?php echo $on2; ?> /> <?php echo $hesklang['recaptcha']; ?> v2</label> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500')"><b>?</b></a>] <br />
		<label><input type="radio" name="s_recaptcha_use" value="1" onclick="javascript:hesk_toggleLayer('recaptcha','block')" <?php echo $on; ?> /> <?php echo $hesklang['sir3']; ?></label> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500')"><b>?</b></a>] <br />

        	<div id="recaptcha" style="display: <?php echo $div; ?>;">

				&nbsp;<br />

                <i><?php echo $hesklang['rcpb']; ?></i> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500')"><b>?</b></a>]<br />
                <input type="text" name="s_recaptcha_public_key" size="50" maxlength="255" value="<?php echo $hesk_settings['recaptcha_public_key']; ?>" /><br />
                &nbsp;<br />

                <i><?php echo $hesklang['rcpv']; ?></i> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#64','400','500')"><b>?</b></a>]<br />
                <input type="text" name="s_recaptcha_private_key" size="50" maxlength="255" value="<?php echo $hesk_settings['recaptcha_private_key']; ?>" /><br />
                &nbsp;<br />

            </div>

            &nbsp;<br />

		</div>

		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200" valign="top"><?php echo $hesklang['use_q']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#42','400','500')"><b>?</b></a>]</td>
		<td>
		<?php

		$on  = '';
		$off = '';
		$div = 'block';

		if ($hesk_settings['question_use'])
		{
			$on = 'checked="checked"';
		}
		else
		{
			$off = 'checked="checked"';
			$div = 'none';
		}
		echo '
		<label><input type="radio" name="s_question_use" value="0" '.$off.' onclick="javascript:hesk_toggleLayer(\'question\',\'none\')" /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_question_use" value="1" '.$on.' onclick="javascript:hesk_toggleLayer(\'question\',\'block\')" /> '.$hesklang['on'].'</label>';
		?>
		<div id="question" style="display: <?php echo $div; ?>;">

		&nbsp;<br />
        <a href="Javascript:void(0)" onclick="Javascript:hesk_rate('generate_spam_question.php','question')"><?php echo $hesklang['genq']; ?></a><br />
        &nbsp;<br />

		<?php echo $hesklang['q_q']; ?>:<br />
		<textarea name="s_question_ask" rows="3" cols="40"><?php echo hesk_htmlentities($hesk_settings['question_ask']); ?></textarea><br />
        &nbsp;<br />

		<?php echo $hesklang['q_a']; ?>:<br />
		<input type="text" name="s_question_ans" value="<?php echo $hesk_settings['question_ans']; ?>" size="10" /><br />
        &nbsp;<br />

		</div>
		</td>
		</tr>
        </table>

        <br />

		<!-- Security -->
		<span class="section">&raquo; <?php echo $hesklang['security']; ?></span>

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['banlim']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#47','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_attempt_limit" size="5" maxlength="30" value="<?php echo ($hesk_settings['attempt_limit'] ? ($hesk_settings['attempt_limit']-1) : 0); ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['banmin']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#47','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_attempt_banmin" size="5" maxlength="3" value="<?php echo $hesk_settings['attempt_banmin']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['passr']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#69','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_reset_pass" value="1" <?php if ($hesk_settings['reset_pass']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['passa']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['viewvtic']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#46','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" id="s_email_view_ticket" name="s_email_view_ticket" onclick="return checkRequiredEmail('s_email_view_ticket');" value="1" <?php if ($hesk_settings['email_view_ticket']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['reqetv']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['frames']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#76','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_x_frame_opt" value="1" <?php if ($hesk_settings['x_frame_opt']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['frames2']; ?></label></td>
		</tr>
        <tr>
        <td style="text-align:right" width="200"><?php echo $hesklang['ssl']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#75','400','500')"><b>?</b></a>]</td>
        <td><label>
        <?php echo HESK_SSL ? '<input type="checkbox" name="s_force_ssl" value="1" ' . ($hesk_settings['force_ssl'] ? 'checked="checked"' : '') . ' /> '.$hesklang['force_ssl'] : $hesklang['d_ssl']; ?></label></td>
        </tr>
        </table>

        <br />

		<!-- Attachments -->
		<span class="section">&raquo; <?php echo $hesklang['attachments']; ?></span>

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200" valign="top"><?php echo $hesklang['attach_use']; $onload_status=''; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#37','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		if ($enable_use_attachments)
		{
			?>
			<label><input type="radio" name="s_attach_use" value="0" onclick="hesk_attach_disable(new Array('a1','a2','a3','a4'))" <?php if(!$hesk_settings['attachments']['use']) {echo ' checked="checked" '; $onload_status=' disabled="disabled" ';} ?> />
			<?php echo $hesklang['no']; ?></label> |
			<label><input type="radio" name="s_attach_use" value="1" onclick="hesk_attach_enable(new Array('a1','a2','a3','a4'))" <?php if($hesk_settings['attachments']['use']) {echo ' checked="checked" ';} ?> />
			<?php echo $hesklang['yes'].'</label>'; ?>

            <?php
            if ( ! defined('HESK_DEMO') )
            {
				?>

				&nbsp; (<a href="javascript:void(0);" onclick="hesk_toggleLayerDisplay('attachments_limits');"><?php echo $hesklang['vscl']; ?></a>)

				<div id="attachments_limits" style="display:none">
				<i>upload_max_filesize</i>: <?php echo @ini_get('upload_max_filesize'); ?><br />
				<?php
				if ( version_compare(phpversion(), '5.2.12', '>=') )
				{
					echo '<i>max_file_uploads</i>: ' . @ini_get('max_file_uploads') . '<br />';
				}
				?>
				<i>post_max_size</i>: <?php echo @ini_get('post_max_size'); ?><br />
				</div>
				<?php
            }
		}
		else
		{
			$onload_status=' disabled="disabled" ';
			echo '<input type="hidden" name="s_attach_use" value="0" /><font class="notice">'.$hesklang['e_attach'].'</font>';
		}
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['attach_num']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#38','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_max_number" size="5" maxlength="2" id="a1" value="<?php echo $hesk_settings['attachments']['max_number']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
        <?php
        $suffixes = array(
        'B'  => $hesklang['B'] . ' (' . $hesklang['bytes'] . ')',
        'kB' => $hesklang['kB'] . ' (' . $hesklang['kilobytes'] . ')',
        'MB' => $hesklang['MB'] . ' (' . $hesklang['megabytes'] . ')',
        'GB' => $hesklang['GB'] . ' (' . $hesklang['gigabytes'] . ')',
        );
        $tmp = hesk_formatBytes($hesk_settings['attachments']['max_size'], 0);
        list($size, $unit) = explode(' ', $tmp);
        ?>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['attach_size']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#39','400','500')"><b>?</b></a>]</td>
		<td>
        	<input type="text" name="s_max_size" size="5" maxlength="6" id="a2" value="<?php echo $size; ?>" <?php echo $onload_status; ?> />
			<select name="s_max_unit" id="a4" <?php echo $onload_status; ?> >
            <?php
            foreach ($suffixes as $k => $v)
            {
            	if ($k == $unit)
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
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['attach_type']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>helpdesk.html#40','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_allowed_types" size="40" maxlength="255" id="a3" value="<?php echo implode(',',$hesk_settings['attachments']['allowed_types']); ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		</table>

	</div>
	<!-- HELP DESK -->

	<!-- KNOWLEDGEBASE -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_3']; ?></h2>

		&nbsp;<br />

		<span class="section">&raquo; <?php echo $hesklang['s_ekb']; ?> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#22','400','500')"><b>?</b></a>]</span><br />&nbsp;

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="0"></td>
		<td>
		<?php
		$on = $hesk_settings['kb_enable'] == 1 ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_enable'] ? '' : 'checked="checked"';
		$only = $hesk_settings['kb_enable'] == 2 ? 'checked="checked"' : '';
		echo '
		<label><input type="radio" name="s_kb_enable" value="1" '.$on.'   /> '.$hesklang['ekb_y'].'</label><br />
		<label><input type="radio" name="s_kb_enable" value="2" '.$only.' /> '.$hesklang['ekb_o'].'</label><br />
		<label><input type="radio" name="s_kb_enable" value="0" '.$off.'  /> '.$hesklang['ekb_n'].'</label><br />
		';
		?>
		<br />&nbsp;
		</td>
		</tr>
		</table>

		<span class="section">&raquo; <?php echo $hesklang['kb_set']; ?></span><br />&nbsp;

		<table width="100%" border="0">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['swyse']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#52','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['kb_wysiwyg'] ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_wysiwyg'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_kb_wysiwyg" value="0" '.$off.' /> '.$hesklang['no'].'</label> |
		<label><input type="radio" name="s_kb_wysiwyg" value="1" '.$on.' /> '.$hesklang['yes'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_suggest']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#23','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['kb_recommendanswers'] ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_recommendanswers'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_kb_recommendanswers" value="0" '.$off.' /> '.$hesklang['no'].'</label> |
		<label><input type="radio" name="s_kb_recommendanswers" value="1" '.$on.' /> '.$hesklang['yes'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_kbr']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#24','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['kb_rating'] ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_rating'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_kb_rating" value="0" '.$off.' /> '.$hesklang['no'].'</label> |
		<label><input type="radio" name="s_kb_rating" value="1" '.$on.' /> '.$hesklang['yes'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['sav']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#58','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['kb_views'] ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_views'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_kb_views" value="0" '.$off.' /> '.$hesklang['no'].'</label> |
		<label><input type="radio" name="s_kb_views" value="1" '.$on.' /> '.$hesklang['yes'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['sad']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#59','400','500')"><b>?</b></a>]<br />&nbsp;</td>
		<td>
		<?php
		$on = $hesk_settings['kb_date'] ? 'checked="checked"' : '';
		$off = $hesk_settings['kb_date'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_kb_date" value="0" '.$off.' /> '.$hesklang['no'].'</label> |
		<label><input type="radio" name="s_kb_date" value="1" '.$on.' /> '.$hesklang['yes'].'</label>';
		?>
        <br />&nbsp;
		</td>
		</tr>

		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_kbs']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#25','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$off = $hesk_settings['kb_search'] ? '' : 'checked="checked"';
		$small = $hesk_settings['kb_search'] == 1 ? 'checked="checked"' : '';
		$large = $hesk_settings['kb_search'] == 2 ? 'checked="checked"' : '';

		echo '
		<label><input type="radio" name="s_kb_search" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_kb_search" value="1" '.$small.' /> '.$hesklang['small'].'</label> |
		<label><input type="radio" name="s_kb_search" value="2" '.$large.' /> '.$hesklang['large'].'</label>
		';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_maxsr']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#26','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_kb_search_limit" size="5" maxlength="3" value="<?php echo $hesk_settings['kb_search_limit']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_ptxt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#27','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_kb_substrart" size="5" maxlength="5" value="<?php echo $hesk_settings['kb_substrart']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_scol']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#28','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_kb_cols" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_cols']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_psubart']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#29','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_kb_numshow" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_numshow']; ?>" /></td>
		</tr>
		<tr>
		<td valign="top" style="text-align:right" width="200"><?php echo $hesklang['s_spop']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#30','400','500')"><b>?</b></a>]</td>
		<td>
		<input type="text" name="s_kb_index_popart" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_index_popart']; ?>" /> <?php echo $hesklang['s_onin']; ?><br />
		<input type="text" name="s_kb_popart" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_popart']; ?>" /> <?php echo $hesklang['s_onkb']; ?>
		</td>
		</tr>
		<tr>
		<td valign="top" style="text-align:right" width="200"><?php echo $hesklang['s_slat']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#31','400','500')"><b>?</b></a>]</td>
		<td>
		<input type="text" name="s_kb_index_latest" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_index_latest']; ?>" /> <?php echo $hesklang['s_onin']; ?><br />
		<input type="text" name="s_kb_latest" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_latest']; ?>" /> <?php echo $hesklang['s_onkb']; ?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['s_relart']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>knowledgebase.html#60','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_kb_related" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_related']; ?>" /></td>
		</tr>
		</table>

	</div>
	<!-- KNOWLEDGEBASE -->

	<!-- EMAIL -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_6']; ?></h2>

		&nbsp;<br />

		<!-- Email sending -->
		<span class="section">&raquo; <?php echo $hesklang['emlsend']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['emlsend2']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
        $on = '';
        $off = '';
        $onload_div = 'none';
        $onload_status = '';

        if ($hesk_settings['smtp'])
        {
        	$on = 'checked="checked"';
            $onload_div = 'block';
        }
        else
        {
			$off = 'checked="checked"';
            $onload_status=' disabled="disabled" ';
        }

		echo '
		<label><input type="radio" name="s_smtp" value="0" onclick="hesk_attach_disable(new Array(\'s1\',\'s2\',\'s3\',\'s4\',\'s5\',\'s6\',\'s7\',\'s8\',\'s9\'))" onchange="hesk_toggleLayerDisplay(\'smtp_settings\');" '.$off.' /> '.$hesklang['phpmail'].'</label> |
		<label><input type="radio" name="s_smtp" value="1" onclick="hesk_attach_enable(new Array(\'s1\',\'s2\',\'s3\',\'s4\',\'s5\',\'s6\',\'s7\',\'s8\',\'s9\'))"  onchange="hesk_toggleLayerDisplay(\'smtp_settings\');" '.$on.' /> '.$hesklang['smtp'].'</label>';
		?>
		<input type="hidden" name="tmp_smtp_host_name" value="<?php echo $hesk_settings['smtp_host_name']; ?>" />
		<input type="hidden" name="tmp_smtp_host_port" value="<?php echo $hesk_settings['smtp_host_port']; ?>" />
		<input type="hidden" name="tmp_smtp_timeout" value="<?php echo $hesk_settings['smtp_timeout']; ?>" />
		<input type="hidden" name="tmp_smtp_user" value="<?php echo $hesk_settings['smtp_user']; ?>" />
		<input type="hidden" name="tmp_smtp_password" value="<?php echo $hesk_settings['smtp_password']; ?>" />
		<input type="hidden" name="tmp_smtp_ssl" value="<?php echo $hesk_settings['smtp_ssl']; ?>" />
		<input type="hidden" name="tmp_smtp_tls" value="<?php echo $hesk_settings['smtp_tls']; ?>" />
		</td>
		</tr>
        </table>

        <div id="smtp_settings" style="display:<?php echo $onload_div; ?>">
        <table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtph']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="s1" name="s_smtp_host_name" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_host_name']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtpp']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="s2" name="s_smtp_host_port" size="5" maxlength="255" value="<?php echo $hesk_settings['smtp_host_port']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtpt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="s3" name="s_smtp_timeout" size="5" maxlength="255" value="<?php echo $hesk_settings['smtp_timeout']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtpssl']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['smtp_ssl'] ? 'checked="checked"' : '';
		$off = $hesk_settings['smtp_ssl'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_smtp_ssl" value="0" id="s6" '.$off.' '.$onload_status.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_smtp_ssl" value="1" id="s7" '.$on.' '.$onload_status.' /> '.$hesklang['on'].'</label>';
		?>
        </td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtptls']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['smtp_tls'] ? 'checked="checked"' : '';
		$off = $hesk_settings['smtp_tls'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_smtp_tls" value="0" id="s8" '.$off.' '.$onload_status.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_smtp_tls" value="1" id="s9" '.$on.' '.$onload_status.' /> '.$hesklang['on'].'</label>';
		?>
        </td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtpu']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="s4" name="s_smtp_user" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_user']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['smtpw']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#55','400','500')"><b>?</b></a>]</td>
		<td><input type="password" id="s5" name="s_smtp_password" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_password']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="hesk_testSMTP()" value="<?php echo $hesklang['smtptest']; ?>" style="margin-top:4px" /></td>
		</tr>
		</table>

		<!-- START SMTP TEST -->
		<div id="smtp_test" style="display:none">
		</div>

		<script language="Javascript" type="text/javascript"><!--
		function hesk_testSMTP()
		{
			var element = document.getElementById('smtp_test');
			element.innerHTML = '<img src="<?php echo HESK_PATH; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes($hesklang['contest']); ?></i>';
			element.style.display = 'block';

			var s_smtp_host_name = document.getElementById('s1').value;
			var s_smtp_host_port = document.getElementById('s2').value;
			var s_smtp_timeout   = document.getElementById('s3').value;
			var s_smtp_user      = document.getElementById('s4').value;
			var s_smtp_password  = document.getElementById('s5').value;
			var s_smtp_ssl       = document.getElementById('s7').checked ? 1 : 0;
			var s_smtp_tls       = document.getElementById('s9').checked ? 1 : 0;

			var params = "test=smtp" +
						 "&s_smtp_host_name=" + encodeURIComponent( s_smtp_host_name ) +
						 "&s_smtp_host_port=" + encodeURIComponent( s_smtp_host_port ) +
						 "&s_smtp_timeout="   + encodeURIComponent( s_smtp_timeout ) +
						 "&s_smtp_user="      + encodeURIComponent( s_smtp_user ) +
						 "&s_smtp_password="  + encodeURIComponent( s_smtp_password ) +
						 "&s_smtp_ssl="       + encodeURIComponent( s_smtp_ssl ) +
						 "&s_smtp_tls="       + encodeURIComponent( s_smtp_tls );

			xmlHttp=GetXmlHttpObject();
			if (xmlHttp==null)
			{
				return;
			}

			xmlHttp.open('POST','test_connection.php',true);
			xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xmlHttp.setRequestHeader("Content-length", params.length);
			xmlHttp.setRequestHeader("Connection", "close");

			xmlHttp.onreadystatechange = function()
			{
				if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
				{
					element.innerHTML = xmlHttp.responseText;
				}
			}

			xmlHttp.send(params);
		}
		//-->
		</script>
		<!-- END SMTP TEST -->

        </div> <!-- END SMTP SETTINGS DIV -->

		<br />

		<!-- Email piping -->
		<span class="section">&raquo; <?php echo $hesklang['emlpipe']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['emlpipe']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#54','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['email_piping'] ? 'checked="checked"' : '';
		$off = $hesk_settings['email_piping'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_email_piping" value="0" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_email_piping" value="1" '.$on.' /> '.$hesklang['on'].'</label>';
		?>
        </td>
		</tr>
		</table>

		<br />

		<!-- POP3 Fetching -->
		<span class="section">&raquo; <?php echo $hesklang['pop3']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
        $on = '';
        $off = '';
        $onload_div = 'none';
        $onload_status = '';

        if ($hesk_settings['pop3'])
        {
        	$on = 'checked="checked"';
            $onload_div = 'block';
        }
        else
        {
			$off = 'checked="checked"';
            $onload_status=' disabled="disabled" ';
        }

		echo '
		<label><input type="radio" name="s_pop3" value="0" onclick="hesk_attach_disable(new Array(\'p0\',\'p1\',\'p2\',\'p3\',\'p4\',\'p5\',\'p6\',\'p7\',\'p8\'))" onchange="hesk_toggleLayerDisplay(\'pop3_settings\');" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_pop3" value="1" onclick="hesk_attach_enable(new Array(\'p0\',\'p1\',\'p2\',\'p3\',\'p4\',\'p5\',\'p6\',\'p7\',\'p8\'))" onchange="hesk_toggleLayerDisplay(\'pop3_settings\');"  '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		<input type="hidden" name="tmp_pop3_host_name" value="<?php echo $hesk_settings['pop3_host_name']; ?>" />
		<input type="hidden" name="tmp_pop3_host_port" value="<?php echo $hesk_settings['pop3_host_port']; ?>" />
		<input type="hidden" name="tmp_pop3_user" value="<?php echo $hesk_settings['pop3_user']; ?>" />
		<input type="hidden" name="tmp_pop3_password" value="<?php echo $hesk_settings['pop3_password']; ?>" />
		<input type="hidden" name="tmp_pop3_tls" value="<?php echo $hesk_settings['pop3_tls']; ?>" />
		<input type="hidden" name="tmp_pop3_keep" value="<?php echo $hesk_settings['pop3_keep']; ?>" />
		</td>
		</tr>
        </table>

		<div id="pop3_settings" style="display:<?php echo $onload_div; ?>">
		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pjt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="p0" name="s_pop3_job_wait" size="5" maxlength="5" value="<?php echo $hesk_settings['pop3_job_wait']; ?>" <?php echo $onload_status; ?> /> <?php echo $hesklang['pjt2']; ?></td>
		</tr>
		<tr>
		<td colspan="2">&nbsp;</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3h']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="p1" name="s_pop3_host_name" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_host_name']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3p']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="p2" name="s_pop3_host_port" size="5" maxlength="255" value="<?php echo $hesk_settings['pop3_host_port']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3tls']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['pop3_tls'] ? 'checked="checked"' : '';
		$off = $hesk_settings['pop3_tls'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_pop3_tls" value="0" id="p3" '.$off.' '.$onload_status.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_pop3_tls" value="1" id="p4" '.$on.' '.$onload_status.' /> '.$hesklang['on'].'</label>';
		?>
        </td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3keep']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['pop3_keep'] ? 'checked="checked"' : '';
		$off = $hesk_settings['pop3_keep'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_pop3_keep" value="0" id="p7" '.$off.' '.$onload_status.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_pop3_keep" value="1" id="p8" '.$on.' '.$onload_status.' /> '.$hesklang['on'].'</label>';
		?>
        </td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3u']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="p5" name="s_pop3_user" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_user']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3w']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#59','400','500')"><b>?</b></a>]</td>
		<td><input type="password" id="p6" name="s_pop3_password" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_password']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="hesk_testPOP3()" value="<?php echo $hesklang['pop3test']; ?>" style="margin-top:4px" /></td>
		</tr>
		</table>

		<!-- START POP3 TEST -->
		<div id="pop3_test" style="display:none">
		</div>

		<script language="Javascript" type="text/javascript"><!--
		function hesk_testPOP3()
		{
			var element = document.getElementById('pop3_test');
			element.innerHTML = '<img src="<?php echo HESK_PATH; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes($hesklang['contest']); ?></i>';
			element.style.display = 'block';

			var s_pop3_host_name = document.getElementById('p1').value;
			var s_pop3_host_port = document.getElementById('p2').value;
			var s_pop3_user      = document.getElementById('p5').value;
			var s_pop3_password  = document.getElementById('p6').value;
			var s_pop3_tls       = document.getElementById('p4').checked ? 1 : 0;

			var params = "test=pop3" +
						 "&s_pop3_host_name="  + encodeURIComponent( s_pop3_host_name ) +
						 "&s_pop3_host_port=" + encodeURIComponent( s_pop3_host_port ) +
						 "&s_pop3_user="      + encodeURIComponent( s_pop3_user ) +
						 "&s_pop3_password="  + encodeURIComponent( s_pop3_password ) +
						 "&s_pop3_tls="       + encodeURIComponent( s_pop3_tls );

			xmlHttp=GetXmlHttpObject();
			if (xmlHttp==null)
			{
				return;
			}

			xmlHttp.open('POST','test_connection.php',true);
			xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xmlHttp.setRequestHeader("Content-length", params.length);
			xmlHttp.setRequestHeader("Connection", "close");

			xmlHttp.onreadystatechange = function()
			{
				if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
				{
					element.innerHTML = xmlHttp.responseText;
				}
			}

			xmlHttp.send(params);
		}
		//-->
		</script>
		<!-- END POP3 TEST -->

		</div> <!-- END POP3 SETTINGS DIV -->

		<br />

		<!-- IMAP Fetching -->
		<span class="section">&raquo; <?php echo $hesklang['imap']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['imap']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = '';
		$off = '';
		$onload_div = 'none';
		$onload_status = '';

		if ($hesk_settings['imap'])
		{
			$on = 'checked="checked"';
			$onload_div = 'block';
		}
		else
		{
			$off = 'checked="checked"';
			$onload_status=' disabled="disabled" ';
		}

        // Is IMAP extension loaded?
        if ( ! function_exists('imap_open'))
        {
            echo '<i>'. $hesklang['disabled'] . '</i> - ' . $hesklang['imap_not'];
            $onload_div = 'none';
        }
        else
        {
		echo '
		<label><input type="radio" name="s_imap" value="0" onclick="hesk_attach_disable(new Array(\'i0\',\'i1\',\'i2\',\'i3\',\'i4\',\'i5\',\'i6\',\'i7\',\'i8\',\'i9\'))" onchange="hesk_toggleLayerDisplay(\'imap_settings\');" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_imap" value="1" onclick="hesk_attach_enable(new Array(\'i0\',\'i1\',\'i2\',\'i3\',\'i4\',\'i5\',\'i6\',\'i7\',\'i8\',\'i9\'))" onchange="hesk_toggleLayerDisplay(\'imap_settings\');"  '.$on.' /> '.$hesklang['on'].'</label>';
        }
		?>
		<input type="hidden" name="tmp_imap_job_wait" value="<?php echo $hesk_settings['imap_job_wait']; ?>" />
		<input type="hidden" name="tmp_imap_host_name" value="<?php echo $hesk_settings['imap_host_name']; ?>" />
		<input type="hidden" name="tmp_imap_host_port" value="<?php echo $hesk_settings['imap_host_port']; ?>" />
		<input type="hidden" name="tmp_imap_user" value="<?php echo $hesk_settings['imap_user']; ?>" />
		<input type="hidden" name="tmp_imap_password" value="<?php echo $hesk_settings['imap_password']; ?>" />
		<input type="hidden" name="tmp_imap_enc" value="<?php echo $hesk_settings['imap_enc']; ?>" />
		<input type="hidden" name="tmp_imap_keep" value="<?php echo $hesk_settings['imap_keep']; ?>" />
		</td>
		</tr>
		</table>

		<div id="imap_settings" style="display:<?php echo $onload_div; ?>">
		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pjt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="i0" name="s_imap_job_wait" size="5" maxlength="5" value="<?php echo $hesk_settings['imap_job_wait']; ?>" <?php echo $onload_status; ?> /> <?php echo $hesklang['pjt2']; ?></td>
		</tr>
		<tr>
		<td colspan="2">&nbsp;</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['imaph']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="i1" name="s_imap_host_name" size="40" maxlength="255" value="<?php echo $hesk_settings['imap_host_name']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['imapp']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="i2" name="s_imap_host_port" size="5" maxlength="255" value="<?php echo $hesk_settings['imap_host_port']; ?>" <?php echo $onload_status; ?> /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['enc']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$none = $hesk_settings['imap_enc'] == '' ? 'checked="checked"' : '';
		$ssl = $hesk_settings['imap_enc'] == 'ssl' ? 'checked="checked"' : '';
		$tls = $hesk_settings['imap_enc'] == 'tls' ? 'checked="checked"' : '';
		echo '
		<label><input type="radio" name="s_imap_enc" value="ssl" id="i9" '.$ssl.' '.$onload_status.' /> '.$hesklang['ssl'].'</label> |
		<label><input type="radio" name="s_imap_enc" value="tls" id="i4" '.$tls.' '.$onload_status.' /> '.$hesklang['tls'].'</label> |
		<label><input type="radio" name="s_imap_enc" value="" id="i3" '.$none.' '.$onload_status.' /> '.$hesklang['none'].'</label>
		';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['pop3keep']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
		$on = $hesk_settings['imap_keep'] ? 'checked="checked"' : '';
		$off = $hesk_settings['imap_keep'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_imap_keep" value="0" id="i7" '.$off.' '.$onload_status.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_imap_keep" value="1" id="i8" '.$on.' '.$onload_status.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['imapu']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td><input type="text" id="i5" name="s_imap_user" size="40" maxlength="255" value="<?php echo $hesk_settings['imap_user']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['imapw']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#67','400','500')"><b>?</b></a>]</td>
		<td><input type="password" id="i6" name="s_imap_password" size="40" maxlength="255" value="<?php echo $hesk_settings['imap_password']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><input type="button" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" onclick="hesk_testIMAP()" value="<?php echo $hesklang['imaptest']; ?>" style="margin-top:4px" /></td>
		</tr>
		</table>

		<!-- START IMAP TEST -->
		<div id="imap_test" style="display:none">
		</div>

		<script language="Javascript" type="text/javascript"><!--
		function hesk_testIMAP()
		{
			var element = document.getElementById('imap_test');
			element.innerHTML = '<img src="<?php echo HESK_PATH; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes($hesklang['contest']); ?></i>';
			element.style.display = 'block';

			var s_imap_host_name = document.getElementById('i1').value;
			var s_imap_host_port = document.getElementById('i2').value;
			var s_imap_user      = document.getElementById('i5').value;
			var s_imap_password  = document.getElementById('i6').value;
			var s_imap_enc       = document.getElementById('i4').checked ? 'tls' : (document.getElementById('i9').checked ? 'ssl' : '');

			var params = "test=imap" +
						 "&s_imap_host_name="  + encodeURIComponent( s_imap_host_name ) +
						 "&s_imap_host_port=" + encodeURIComponent( s_imap_host_port ) +
						 "&s_imap_user="      + encodeURIComponent( s_imap_user ) +
						 "&s_imap_password="  + encodeURIComponent( s_imap_password ) +
						 "&s_imap_enc="       + encodeURIComponent( s_imap_enc );

			xmlHttp=GetXmlHttpObject();
			if (xmlHttp==null)
			{
				return;
			}

			xmlHttp.open('POST','test_connection.php',true);
			xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xmlHttp.setRequestHeader("Content-length", params.length);
			xmlHttp.setRequestHeader("Connection", "close");

			xmlHttp.onreadystatechange = function()
			{
				if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
				{
					element.innerHTML = xmlHttp.responseText;
				}
			}

			xmlHttp.send(params);
		}
		//-->
		</script>
		<!-- END IMAP TEST -->

		</div> <!-- END IMAP SETTINGS DIV -->

		<br />

		<!-- Email loops -->
		<span class="section">&raquo; <?php echo $hesklang['loops']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['looph']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#60','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_loop_hits" size="5" maxlength="5" value="<?php echo $hesk_settings['loop_hits']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['loopt']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#60','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_loop_time" size="5" maxlength="5" value="<?php echo $hesk_settings['loop_time']; ?>" /> <?php echo $hesklang['ss']; ?></td>
		</tr>
		</table>

		<br />

		<!-- Detect email typos -->
		<span class="section">&raquo; <?php echo $hesklang['suge']; ?></span>

		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['suge']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#62','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
        $on = '';
        $off = '';
        $onload_div = 'none';
        $onload_status = '';

        if ($hesk_settings['detect_typos'])
        {
        	$on = 'checked="checked"';
            $onload_div = 'block';
        }
        else
        {
			$off = 'checked="checked"';
            $onload_status=' disabled="disabled" ';
        }

		echo '
		<label><input type="radio" name="s_detect_typos" value="0" onclick="hesk_attach_disable(new Array(\'d1\'))" onchange="hesk_toggleLayerDisplay(\'detect_typos\');" '.$off.' /> '.$hesklang['off'].'</label> |
		<label><input type="radio" name="s_detect_typos" value="1" onclick="hesk_attach_enable(new Array(\'d1\'))" onchange="hesk_toggleLayerDisplay(\'detect_typos\');"  '.$on.' /> '.$hesklang['on'].'</label>';
		?>
		</td>
		</tr>
        </table>

		<div id="detect_typos" style="display:<?php echo $onload_div; ?>">
		<table border="0"  width="100%">
		<tr>
		<td style="text-align:right;vertical-align:top" width="200"><?php echo $hesklang['epro']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#63','400','500')"><b>?</b></a>]</td>
		<td><textarea name="s_email_providers" id="d1" rows="5" cols="40"><?php echo implode("\n", $hesk_settings['email_providers']); ?></textarea></td>
		</tr>
		</table>
        </div>

		<br />

		<!-- Notify customer when -->
		<span class="section">&raquo; <?php echo $hesklang['custnot']; ?> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#65','400','500')"><b>?</b></a>]</span>

		<table border="0" width="100%">
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><label><input type="checkbox" name="s_notify_new" value="1" onchange="hesk_toggleLayerDisplay('skip_notify');" <?php if ($hesk_settings['notify_new']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['notnew']; ?></label>

		<div id="skip_notify" style="margin-left:25px;display:<?php echo $hesk_settings['notify_new'] ? 'block' : 'none'; ?>">
		<p><label><input type="checkbox" name="s_notify_skip_spam" value="1" <?php if ($hesk_settings['notify_skip_spam']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['enn']; ?></label><br />
		<textarea name="s_notify_spam_tags" rows="5" cols="40" style="margin-left:25px;"><?php echo hesk_htmlspecialchars( implode("\n", $hesk_settings['notify_spam_tags']) ); ?></textarea></p>
		</div>

		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td><label><input type="checkbox" name="s_notify_closed" value="1" <?php if ($hesk_settings['notify_closed']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['notclo']; ?></label></td>
		</tr>
		</table>

		<br />

		<!-- Other -->
		<span class="section">&raquo; <?php echo $hesklang['other']; ?></span>

		<table border="0" width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['remqr']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#61','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_strip_quoted" value="1" <?php if ($hesk_settings['strip_quoted']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['remqr2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['emlreqmsg']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#66','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_eml_req_msg" value="1" <?php if ($hesk_settings['eml_req_msg']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['emlreqmsg2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['embed']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#64','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_save_embedded" value="1" <?php if ($hesk_settings['save_embedded']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['embed2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['meml']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#57','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_multi_eml" value="1" <?php if ($hesk_settings['multi_eml']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['meml2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['sconfe']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#50','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_confirm_email" value="1" <?php if ($hesk_settings['confirm_email']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['sconfe2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['oo']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>email.html#58','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_open_only" value="1" <?php if ($hesk_settings['open_only']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['ool']; ?></label></td>
		</tr>
        </table>

		<br />

	</div>
	<!-- EMAIL -->

	<!-- TICKET LIST -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_7']; ?></h2>

		&nbsp;<br />

		<span class="section">&raquo; <?php echo $hesklang['fitl']; ?></span> [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>ticket_list.html#1','300','500')"><b>?</b></a>]

		<table border="0" width="100%">
		<?php
		// List available fields
		foreach ($hesk_settings['possible_ticket_list'] as $key => $title)
		{
			echo '
			<tr>
			<td style="text-align:right" width="200">&nbsp;</td>
			<td><label><input type="checkbox" name="s_tl_'.$key.'" value="1" '.( in_array($key, $hesk_settings['ticket_list']) ? 'checked="checked"' : '' ).' /> '.$title.'</label></td>
			</tr>
			';
		}
		?>
		</table>

		<br />

		<!-- Other -->
		<span class="section">&raquo; <?php echo $hesklang['other']; ?></span>

		<table border="0" width="100%">
		<tr>
		<td style="text-align:right;vertical-align:text-top" width="200"><?php echo $hesklang['sdf']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>ticket_list.html#2','400','600')"><b>?</b></a>]</td>
		<td>
		<?php
		$off = $hesk_settings['new_top'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_submittedformat" value="2" '.($hesk_settings['submittedformat'] == 2 ? 'checked="checked"' : '').' /> '.$hesklang['lcf2'].'</label><br />
		<label><input type="radio" name="s_submittedformat" value="1" '.($hesk_settings['submittedformat'] == 1 ? 'checked="checked"' : '').' /> '.$hesklang['lcf1'].'</label><br />
		<label><input type="radio" name="s_submittedformat" value="0" '.($hesk_settings['submittedformat'] == 0 ? 'checked="checked"' : '').' /> '.$hesklang['lcf0'].'</label>';
		?>
		</td>
		</tr>
		<tr>
		<td style="text-align:right;vertical-align:text-top" width="200"><?php echo $hesklang['lcf']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>ticket_list.html#2','400','600')"><b>?</b></a>]</td>
		<td>
		<?php
		$off = $hesk_settings['new_top'] ? '' : 'checked="checked"';
		echo '
		<label><input type="radio" name="s_updatedformat" value="2" '.($hesk_settings['updatedformat'] == 2 ? 'checked="checked"' : '').' /> '.$hesklang['lcf2'].'</label><br />
		<label><input type="radio" name="s_updatedformat" value="1" '.($hesk_settings['updatedformat'] == 1 ? 'checked="checked"' : '').' /> '.$hesklang['lcf1'].'</label><br />
		<label><input type="radio" name="s_updatedformat" value="0" '.($hesk_settings['updatedformat'] == 0 ? 'checked="checked"' : '').' /> '.$hesklang['lcf0'].'</label>';
		?>
		</td>
		</tr>
		</table>

	</div>
	<!-- TICKET LIST -->

	<!-- MISC -->
	<div class="tabbertab">
		<h2><?php echo $hesklang['tab_5']; ?></h2>

		&nbsp;<br />

		<span class="section">&raquo; <?php echo $hesklang['dat']; ?></span>

		<!-- Date & Time -->
		<table border="0" width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['TZ']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#63','400','500')"><b>?</b></a>]</td>
		<td>
		<?php
        // Get list of supported timezones
        $timezone_list = hesk_generate_timezone_list();

        // Do we need to localize month names?
        if ($hesk_settings['language'] != 'English')
        {
            $timezone_list = hesk_translate_timezone_list($timezone_list);
        }
        ?>
        <select name="s_timezone">
        <?php
        foreach ($timezone_list as $timezone => $description)
        {
            echo '<option value="' . $timezone . '"' . ($hesk_settings['timezone'] == $timezone ? ' selected="selected"' : '') . '>' . $description . '</option>';
        }
        ?>
        </select>
		</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['tfor']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#20','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_timeformat" size="40" maxlength="255" value="<?php echo $hesk_settings['timeformat']; ?>" /></td>
		</tr>
		</table>

		<br />

		<!-- Other -->
		<span class="section">&raquo; <?php echo $hesklang['other']; ?></span>

		<table border="0" width="100%">
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['ip_whois']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#61','400','500')"><b>?</b></a>]</td>
		<td><input type="text" name="s_ip_whois_url" size="40" maxlength="255" value="<?php echo $hesk_settings['ip_whois']; ?>" /></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200">&nbsp;</td>
		<td>&nbsp;</td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['mms']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#62','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_maintenance_mode" value="1" <?php if ($hesk_settings['maintenance_mode']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['mmd']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['al']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#21','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_alink" value="1" <?php if ($hesk_settings['alink']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['dap']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['subnot']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#48','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_submit_notice" value="1" <?php if ($hesk_settings['submit_notice']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['subnot2']; ?></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['sonline']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#56','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_online" value="1" <?php if ($hesk_settings['online']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['sonline2']; ?> <input type="text" name="s_online_min" size="5" maxlength="4" value="<?php echo $hesk_settings['online_min']; ?>" /></label></td>
		</tr>
		<tr>
		<td style="text-align:right" width="200"><?php echo $hesklang['updates']; ?>: [<a href="Javascript:void(0)" onclick="Javascript:hesk_window('<?php echo $help_folder; ?>misc.html#59','400','500')"><b>?</b></a>]</td>
		<td><label><input type="checkbox" name="s_check_updates" value="1" <?php if ($hesk_settings['check_updates']) {echo 'checked="checked"';} ?>/> <?php echo $hesklang['updates2']; ?></label></td>
		</tr>
		</table>

	</div>
	<!-- MISC -->

</div>
<!-- TABS -->


<p>&nbsp;</p>

<p align="center">
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<?php
if ($enable_save_settings)
{
    echo '<input type="submit" id="submitbutton" value="'.$hesklang['save_changes'].'" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" />';
}
else
{
    echo '<input type="button" value="'.$hesklang['save_changes'].' ('.$hesklang['disabled'].')" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" disabled="disabled" /><br /><font class="error">'.$hesklang['e_save_settings'].'</font>';
}
?></p>

</form>

<p>&nbsp;</p>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


function hesk_checkVersion()
{
	global $hesk_settings;

	if ($latest = hesk_getLatestVersion() )
    {
    	if ( strlen($latest) > 12 )
        {
        	return -1;
        }
		elseif ($latest == $hesk_settings['hesk_version'])
        {
        	return true;
        }
        else
        {
        	return $latest;
        }
    }
    else
    {
		return -1;
    }

} // END hesk_checkVersion()


function hesk_getLatestVersion()
{
	global $hesk_settings;

	// Do we have a cached version file?
	if ( file_exists(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt') )
    {
        if ( preg_match('/^(\d+)\|([\d.]+)+$/', @file_get_contents(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt'), $matches) && (time() - intval($matches[1])) < 3600  )
        {
			return $matches[2];
        }
    }

	// No cached file or older than 3600 seconds, try to get an update
    $hesk_version_url = 'http://hesk.com/version';

	// Try using cURL
	if ( function_exists('curl_init') )
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $hesk_version_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
		$latest = curl_exec($ch);
		curl_close($ch);
		return hesk_cacheLatestVersion($latest);
	}

    // Try using a simple PHP function instead
	if ($latest = @file_get_contents($hesk_version_url) )
    {
		return hesk_cacheLatestVersion($latest);
    }

	// Can't check automatically, will need a manual check
    return false;

} // END hesk_getLatestVersion()


function hesk_cacheLatestVersion($latest)
{
	global $hesk_settings;

	@file_put_contents(HESK_PATH . $hesk_settings['cache_dir'] . '/__latest.txt', time() . '|' . $latest);

	return $latest;

} // END hesk_cacheLatestVersion()


function hesk_testLanguage($return_options = 0)
{
	global $hesk_settings, $hesklang;

	/* Get a list of valid emails */
    include_once(HESK_PATH . 'inc/email_functions.inc.php');
    $valid_emails = array_keys( hesk_validEmails() );

	$dir = HESK_PATH . 'language/';
	$path = opendir($dir);

    $text = '';
    $html = '';

	$text .= "/language\n";

    /* Test all folders inside the language folder */
	while (false !== ($subdir = readdir($path)))
	{
		if ($subdir == "." || $subdir == "..")
	    {
	    	continue;
	    }

		if (filetype($dir . $subdir) == 'dir')
		{
        	$add   = 1;
	    	$langu = $dir . $subdir . '/text.php';
	        $email = $dir . $subdir . '/emails';

			/* Check the text.php */
			$text .= "   |-> /$subdir\n";
	        $text .= "        |-> text.php: ";
	        if (file_exists($langu))
	        {
	        	$tmp = file_get_contents($langu);

				// Some servers add slashes to file_get_contents output
				if ( strpos ($tmp, '[\\\'LANGUAGE\\\']') !== false )
				{
					$tmp = stripslashes($tmp);
				}

	            $err = '';
	        	if (!preg_match('/\$hesklang\[\'LANGUAGE\'\]\=\'(.*)\'\;/',$tmp,$l))
	            {
	                $err .= "              |---->  MISSING: \$hesklang['LANGUAGE']\n";
	            }

	            if (strpos($tmp,'$hesklang[\'ENCODING\']') === false)
	            {
	            	$err .= "              |---->  MISSING: \$hesklang['ENCODING']\n";
	            }

	            if (strpos($tmp,'$hesklang[\'_COLLATE\']') === false)
	            {
	            	$err .= "              |---->  MISSING: \$hesklang['_COLLATE']\n";
	            }

	            if (strpos($tmp,'$hesklang[\'EMAIL_HR\']') === false)
	            {
	            	$err .= "              |---->  MISSING: \$hesklang['EMAIL_HR']\n";
	            }

                /* Check if language file is for current version */
	            if (strpos($tmp,'$hesklang[\'LANGUAGE_EN\']') === false)
	            {
	            	$err .= "              |---->  WRONG VERSION (not ".$hesk_settings['hesk_version'].")\n";
	            }

	            if ($err)
	            {
	            	$text .= "ERROR\n" . $err;
                    $add   = 0;
	            }
	            else
	            {
                	$l[1]  = hesk_input($l[1]);
                    $l[1]  = str_replace('|',' ',$l[1]);
	        		$text .= "OK ($l[1])\n";
	            }
	        }
	        else
	        {
	        	$text .= "ERROR\n";
	            $text .= "              |---->  MISSING: text.php\n";
                $add   = 0;
	        }

            /* Check emails folder */
	        $text .= "        |-> /emails:  ";
	        if (file_exists($email) && filetype($email) == 'dir')
	        {
	        	$err = '';
	            foreach ($valid_emails as $eml)
	            {
	            	if (!file_exists($email.'/'.$eml.'.txt'))
	                {
	                	$err .= "              |---->  MISSING: $eml.txt\n";
	                }
	            }

	            if ($err)
	            {
	            	$text .= "ERROR\n" . $err;
                    $add   = 0;
	            }
	            else
	            {
	        		$text .= "OK\n";
	            }
	        }
	        else
	        {
	        	$text .= "ERROR\n";
	            $text .= "              |---->  MISSING: /emails folder\n";
                $add   = 0;
	        }

	        $text .= "\n";

            /* Add an option for the <select> if needed */
            if ($add)
            {
				if ($l[1] == $hesk_settings['language'])
				{
					$html .= '<option value="'.$subdir.'|'.$l[1].'" selected="selected">'.$l[1].'</option>';
				}
				else
				{
					$html .= '<option value="'.$subdir.'|'.$l[1].'">'.$l[1].'</option>';
				}
            }
		}
	}

	closedir($path);

    /* Output select options or the test log for debugging */
    if ($return_options)
    {
		return $html;
    }
    else
    {
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
		<head>
		<title><?php echo $hesklang['s_inl']; ?></title>
		<meta http-equiv="Content-Type" content="text/html;charset=<?php echo $hesklang['ENCODING']; ?>" />
		<style type="text/css">
		body
		{
		        margin:5px 5px;
		        padding:0;
		        background:#fff;
		        color: black;
		        font : 68.8%/1.5 Verdana, Geneva, Arial, Helvetica, sans-serif;
		        text-align:left;
		}

		p
		{
		        color : black;
		        font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
		        font-size: 1.0em;
		}
		h3
		{
		        color : #AF0000;
		        font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
		        font-weight: bold;
		        font-size: 1.0em;
		        text-align:center;
		}
		.title
		{
		        color : black;
		        font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
		        font-weight: bold;
		        font-size: 1.0em;
		}
		.wrong   {color : red;}
		.correct {color : green;}
        pre {font-size:1.2em;}
		</style>
		</head>
		<body>

		<h3><?php echo $hesklang['s_inl']; ?></h3>

		<p><i><?php echo $hesklang['s_inle']; ?></i></p>

		<pre><?php echo $text; ?></pre>

		<p>&nbsp;</p>

		<p align="center"><a href="admin_settings.php?test_languages=1&amp;<?php echo rand(10000,99999); ?>"><?php echo $hesklang['ta']; ?></a> | <a href="#" onclick="Javascript:window.close()"><?php echo $hesklang['cwin']; ?></a></p>

		<p>&nbsp;</p>

		</body>

		</html>
		<?php
		exit();
    }
} // END hesk_testLanguage()
?>
