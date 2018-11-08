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

/* Check permissions for this feature */
hesk_checkPermission('can_man_cat');

// Possible priorities
$priorities = array(
	3 => array('value' => 3, 'text' => $hesklang['low'],		'formatted' => $hesklang['low']),
	2 => array('value' => 2, 'text' => $hesklang['medium'],		'formatted' => '<font class="medium">'.$hesklang['medium'].'</font>'),
	1 => array('value' => 1, 'text' => $hesklang['high'],		'formatted' => '<font class="important">'.$hesklang['high'].'</font>'),
	0 => array('value' => 0, 'text' => $hesklang['critical'],	'formatted' => '<font class="critical">'.$hesklang['critical'].'</font>'),
);

/* What should we do? */
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'linkcode')       {generate_link_code();}
	elseif ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_categories.php', 'NOTICE');}
	elseif ($action == 'new')        {new_cat();}
	elseif ($action == 'rename')     {rename_cat();}
	elseif ($action == 'remove')     {remove();}
	elseif ($action == 'order')      {order_cat();}
	elseif ($action == 'autoassign') {toggle_autoassign();}
	elseif ($action == 'type')       {toggle_type();}
	elseif ($action == 'priority')   {change_priority();}
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

</td>
</tr>
<tr>
<td>

<script language="Javascript" type="text/javascript"><!--
function confirm_delete()
{
if (confirm('<?php echo hesk_makeJsString($hesklang['confirm_del_cat']); ?>')) {return true;}
else {return false;}
}
//-->
</script>

<?php
/* This will handle error, success and notice messages */
hesk_handle_messages();
?>

<h3 style="padding-bottom:5px"><?php echo $hesklang['manage_cat']; ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['cat_intro']); ?>')">?</a>]</h3>

&nbsp;<br />

<div align="center">
<table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
<tr>
<th class="admin_white" style="white-space:nowrap;width:1px;"><b><i>&nbsp;<?php echo $hesklang['id']; ?>&nbsp;</i></b></th>
<th class="admin_white" style="text-align:left"><b><i>&nbsp;<?php echo $hesklang['cat_name']; ?>&nbsp;</i></b></th>
<th class="admin_white" style="text-align:left"><b><i>&nbsp;<?php echo $hesklang['priority']; ?>&nbsp;</i></b></th>
<th class="admin_white" style="white-space:nowrap;width:1px;"><b><i>&nbsp;<?php echo $hesklang['not']; ?>&nbsp;</i></b></th>
<th class="admin_white" style="text-align:left"><b><i>&nbsp;<?php echo $hesklang['graph']; ?>&nbsp;</i></b></th>
<th class="admin_white" style="width:100px"><b><i>&nbsp;<?php echo $hesklang['opt']; ?>&nbsp;</i></b></th>
</tr>

<?php
/* Get number of tickets per category */
$tickets_all   = array();
$tickets_total = 0;

$res = hesk_dbQuery('SELECT COUNT(*) AS `cnt`, `category` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` GROUP BY `category`');
while ($tmp = hesk_dbFetchAssoc($res))
{
	$tickets_all[$tmp['category']] = $tmp['cnt'];
    $tickets_total += $tmp['cnt'];
}

/* Get list of categories */
$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
$options='';

$i=1;
$j=0;
$num = hesk_dbNumRows($res);

while ($mycat=hesk_dbFetchAssoc($res))
{
	$j++;

    if (isset($_SESSION['selcat2']) && $mycat['id'] == $_SESSION['selcat2'])
    {
		$color = 'admin_green';
		unset($_SESSION['selcat2']);
	}
    else
    {
		$color = $i ? 'admin_white' : 'admin_gray';
    }

	$tmp   = $i ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
    $i     = $i ? 0 : 1;

    /* Number of tickets and graph width */
	$all = isset($tickets_all[$mycat['id']]) ? $tickets_all[$mycat['id']] : 0;
	$width_all = 0;
	if ($tickets_total && $all)
	{
		$width_all  = round(($all / $tickets_total) * 100);
	}

    /* Deleting category with ID 1 (default category) is not allowed */
    if ($mycat['id'] == 1)
    {
        $remove_code=' <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
    }
    else
    {
        $remove_code=' <a name="Delete '.$mycat['name'].'" href="manage_categories.php?a=remove&amp;catid='.$mycat['id'].'&amp;token='.hesk_token_echo(0).'" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="'.$hesklang['remove'].'" title="'.$hesklang['remove'].'" '.$style.' /></a>';
    }

	/* Is category private or public? */
	if ($mycat['type'])
	{
		$type_code = '<a id="public" href="manage_categories.php?a=type&amp;s=0&amp;catid='.$mycat['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/private.png" width="16" height="16" alt="'.$hesklang['cat_private'].'" title="'.$hesklang['cat_private'].'" '.$style.' /></a>';
	}
	else
	{
		$type_code = '<a id="private" href="manage_categories.php?a=type&amp;s=1&amp;catid='.$mycat['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/public.png" width="16" height="16" alt="'.$hesklang['cat_public'].'" title="'.$hesklang['cat_public'].'" '.$style.' /></a>';
	}

	/* Is auto assign enabled? */
	if ($hesk_settings['autoassign'])
    {
    	if ($mycat['autoassign'])
        {
			$autoassign_code = '<a id="unassign" href="manage_categories.php?a=autoassign&amp;s=0&amp;catid='.$mycat['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/autoassign_on.png" width="16" height="16" alt="'.$hesklang['aaon'].'" title="'.$hesklang['aaon'].'" '.$style.' /></a>';
        }
        else
        {
			$autoassign_code = '<a id="assign" href="manage_categories.php?a=autoassign&amp;s=1&amp;catid='.$mycat['id'].'&amp;token='.hesk_token_echo(0).'"><img src="../img/autoassign_off.png" width="16" height="16" alt="'.$hesklang['aaoff'].'" title="'.$hesklang['aaoff'].'" '.$style.' /></a>';
        }
    }
    else
    {
		$autoassign_code = '';
    }

    $options .= '<option value="'.$mycat['id'].'" ';
    $options .= (isset($_SESSION['selcat']) && $mycat['id'] == $_SESSION['selcat']) ? ' selected="selected" ' : '';
    $options .= '>'.$mycat['name'].'</option>';

	echo '
	<tr>
	<td class="'.$color.'" name="'.$mycat['name'].'">'.$mycat['id'].'</td>
	<td class="'.$color.'">'.$mycat['name'].'</td>
	<td class="'.$color.'" width="1" style="white-space: nowrap;">'.$priorities[$mycat['priority']]['formatted'].'&nbsp;</td>
	<td class="'.$color.'" style="text-align:center"><a href="show_tickets.php?category='.$mycat['id'].'&amp;s_all=1&amp;s_my=1&amp;s_ot=1&amp;s_un=1" alt="'.$hesklang['list_tickets_cat'].'" title="'.$hesklang['list_tickets_cat'].'">'.$all.'</a></td>
	<td class="'.$color.'" width="1">
	<div class="progress-container" style="width: 160px" title="'.sprintf($hesklang['perat'],$width_all.'%').'">
	<div style="width: '.$width_all.'%;float:left;"></div>
	</div>
	</td>
	<td class="'.$color.'" style="text-align:center; white-space:nowrap;">
	<a href="Javascript:void(0)" onclick="Javascript:hesk_window(\'manage_categories.php?a=linkcode&amp;catid='.$mycat['id'].'&amp;p='.$mycat['type'].'\',\'200\',\'500\')"><img src="../img/code' . ($mycat['type'] ? '_off' : '') . '.png" width="16" height="16" alt="'.$hesklang['geco'].'" title="'.$hesklang['geco'].'" '.$style.' /></a>
	' . $autoassign_code . '
    ' . $type_code . ' ';

	if ($num > 1)
	{
		if ($j == 1)
		{
			echo'<img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" /> <a href="manage_categories.php?a=order&amp;catid='.$mycat['id'].'&amp;move=15&amp;token='.hesk_token_echo(0).'"><img src="../img/move_down.png" width="16" height="16" alt="'.$hesklang['move_dn'].'" title="'.$hesklang['move_dn'].'" '.$style.' /></a>';
		}
		elseif ($j == $num)
		{
			echo'<a href="manage_categories.php?a=order&amp;catid='.$mycat['id'].'&amp;move=-15&amp;token='.hesk_token_echo(0).'"><img src="../img/move_up.png" width="16" height="16" alt="'.$hesklang['move_up'].'" title="'.$hesklang['move_up'].'" '.$style.' /></a> <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
		}
		else
		{
			echo'
			<a href="manage_categories.php?a=order&amp;catid='.$mycat['id'].'&amp;move=-15&amp;token='.hesk_token_echo(0).'"><img src="../img/move_up.png" width="16" height="16" alt="'.$hesklang['move_up'].'" title="'.$hesklang['move_up'].'" '.$style.' /></a>
			<a href="manage_categories.php?a=order&amp;catid='.$mycat['id'].'&amp;move=15&amp;token='.hesk_token_echo(0).'"><img src="../img/move_down.png" width="16" height="16" alt="'.$hesklang['move_dn'].'" title="'.$hesklang['move_dn'].'" '.$style.' /></a>
			';
		}
	}

    echo $remove_code.'</td>
	</tr>
	';

} // End while

?>
</table>
</div>

<p>&nbsp;</p>

<?php
if ($hesk_settings['cust_urgency'])
{
	hesk_show_notice($hesklang['cat_pri_info'] . ' ' . $hesklang['cpri']);
}
?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
			<td class="roundcornerstop"></td>
			<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
		</tr>
		<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
        <!-- CONTENT -->

<form action="manage_categories.php" method="post">
<h3>&raquo; <?php echo $hesklang['add_cat']; ?></h3>

<p><b><?php echo $hesklang['cat_name']; ?></b> (<?php echo $hesklang['max_chars']; ?>)<b>:</b><br /><input type="text" name="name" size="40" maxlength="40"
<?php
	if (isset($_SESSION['catname']))
    {
    	echo ' value="'.$_SESSION['catname'].'" ';
    }
?>
/></p>

<p><b><?php echo $hesklang['def_pri']; ?></b> [<b><a href="javascript:void(0)" onclick="javascript:alert('<?php echo hesk_makeJsString($hesklang['cat_pri']); ?>')">?</a></b>]<br />
<select name="priority">
<?php
// Default priority: low
if ( ! isset($_SESSION['cat_priority']) )
{
	$_SESSION['cat_priority'] = 3;
}

// List possible priorities
foreach ($priorities as $value => $info)
{
	echo '<option value="'.$value.'"'.($_SESSION['cat_priority'] == $value ? ' selected="selected"' : '').'>'.$info['text'].'</option>';
}
?>
</select></p>

<p><b><?php echo $hesklang['opt']; ?>:</b><br />

<?php
if ($hesk_settings['autoassign'])
{
	?>
	<label><input type="checkbox" name="autoassign" value="Y" <?php if ( ! isset($_SESSION['cat_autoassign']) || $_SESSION['cat_autoassign'] == 1 ) {echo 'checked="checked"';} ?>  /> <?php echo $hesklang['cat_aa']; ?></label><br />
	<?php
}
?>
<label><input type="checkbox" name="type" value="Y" <?php if ( isset($_SESSION['cat_type']) && $_SESSION['cat_type'] == 1 ) {echo 'checked="checked"';} ?> /> <?php echo $hesklang['cat_type']; ?></label><br />
</p>

<input type="hidden" name="a" value="new" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="submit" value="<?php echo $hesklang['create_cat']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
</form>

		<!-- END CONTENT -->
        </td>
		<td class="roundcornersright">&nbsp;</td>
		</tr>
		<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
		</tr>
	</table>

<p>&nbsp;</p>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
			<td class="roundcornerstop"></td>
			<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
		</tr>
		<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
        <!-- CONTENT -->

<form action="manage_categories.php" method="post">

<h3>&raquo; <?php echo $hesklang['ren_cat']; ?></h3>

<table border="0" style="margin-top:10px;">
<tr>
	<td><?php echo $hesklang['oln']; ?></td>
	<td><select name="catid"><?php echo $options; ?></select></td>
</tr>
<tr>
	<td><?php echo $hesklang['nen']; ?></td>
	<td><input type="text" id="renamecat" name="name" size="40" maxlength="40" <?php if (isset($_SESSION['catname2'])) {echo ' value="'.$_SESSION['catname2'].'" ';} ?> /></td>
</tr>
</table>

<p><input type="hidden" name="a" value="rename" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="submit" id="rencategory" value="<?php echo $hesklang['ren_cat']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /></p>
</form>

		<!-- END CONTENT -->
        </td>
		<td class="roundcornersright">&nbsp;</td>
		</tr>
		<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
		</tr>
	</table>

<p>&nbsp;</p>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
			<td class="roundcornerstop"></td>
			<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
		</tr>
		<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
        <!-- CONTENT -->

<form action="manage_categories.php" method="post">

<h3>&raquo; <?php echo $hesklang['ch_cat_pri']; ?></h3>

<table border="0" style="margin-top:10px;">
<tr>
	<td><?php echo $hesklang['category']; ?>:</td>
	<td><select id="prioritycat" name="catid"><?php echo $options; ?></select></td>
</tr>
<tr>
	<td><?php echo $hesklang['priority']; ?>:</td>
	<td><select name="priority">
<?php
// Default priority: low
if ( ! isset($_SESSION['cat_ch_priority']) )
{
	$_SESSION['cat_ch_priority'] = 3;
}

// List possible priorities
foreach ($priorities as $value => $info)
{
	echo '<option value="'.$value.'"'.($_SESSION['cat_ch_priority'] == $value ? ' selected="selected"' : '').'>'.$info['text'].'</option>';
}
?>
</select></td>
</tr>
</table>

<p><input type="hidden" name="a" value="priority" />
<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
<input type="submit" id="setcatpriority" value="<?php echo $hesklang['ch_cat_pri']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /></p>
</form>

		<!-- END CONTENT -->
        </td>
		<td class="roundcornersright">&nbsp;</td>
		</tr>
		<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
		</tr>
	</table>

<!-- HR -->
<p>&nbsp;</p>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/

function change_priority()
{
	global $hesk_settings, $hesklang, $priorities;

	/* A security check */
	hesk_token_check('POST');

	$_SERVER['PHP_SELF'] = 'manage_categories.php?catid='.intval( hesk_POST('catid') );

	$catid = hesk_isNumber( hesk_POST('catid'), $hesklang['choose_cat_ren'], $_SERVER['PHP_SELF']);
	$_SESSION['selcat'] = $catid;
	$_SESSION['selcat2'] = $catid;

	$priority = intval( hesk_POST('priority', 3));
	if ( ! array_key_exists($priority, $priorities) )
	{
		$priority = 3;
	}

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `priority`='{$priority}' WHERE `id`='".intval($catid)."'");

    hesk_cleanSessionVars('cat_ch_priority');

	hesk_process_messages($hesklang['cat_pri_ch'].' '.$priorities[$priority]['formatted'],$_SERVER['PHP_SELF'],'SUCCESS');
} // END change_priority()


function generate_link_code() {
	global $hesk_settings, $hesklang;
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title><?php echo $hesklang['genl']; ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=<?php echo $hesklang['ENCODING']; ?>" />
<style type="text/css">
body
{
        margin:5px 5px;
        padding:0;
        background:#fff;
        color: black;
        font : 68.8%/1.5 Verdana, Geneva, Arial, Helvetica, sans-serif;
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
}
</style>
</head>
<body>

<div style="text-align:center">

<h3><?php echo $hesklang['genl']; ?></h3>

<?php
if ( ! empty($_GET['p']) )
{
	echo '<p>&nbsp;<br />' . $hesklang['cpric'] . '<br />&nbsp;</p>';
}
else
{
	?>
	<p><i><?php echo $hesklang['genl2']; ?></i></p>

	<textarea rows="3" cols="50" onfocus="this.select()"><?php echo $hesk_settings['hesk_url'].'/index.php?a=add&amp;catid='.intval( hesk_GET('catid') ); ?></textarea>
	<?php
}
?>

<p align="center"><a href="#" onclick="Javascript:window.close()"><?php echo $hesklang['cwin']; ?></a></p>

</div>

</body>

</html>
	<?php
    exit();
}


function new_cat()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    /* Options */
    $_SESSION['cat_autoassign'] = hesk_POST('autoassign') == 'Y' ? 1 : 0;
    $_SESSION['cat_type'] = hesk_POST('type') == 'Y' ? 1 : 0;

	// Default priority
	$_SESSION['cat_priority'] = intval( hesk_POST('priority', 3) );
	if ($_SESSION['cat_priority'] < 0 || $_SESSION['cat_priority'] > 3)
	{
		$_SESSION['cat_priority'] = 3;
	}

    /* Category name */
	$catname = hesk_input( hesk_POST('name') , $hesklang['enter_cat_name'], 'manage_categories.php');

    /* Do we already have a category with this name? */
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `name` LIKE '".hesk_dbEscape( hesk_dbLike($catname) )."' LIMIT 1");
    if (hesk_dbNumRows($res) != 0)
    {
		$_SESSION['catname'] = stripslashes($catname);
		hesk_process_messages($hesklang['cndupl'],'manage_categories.php');
    }

	/* Get the latest cat_order */
	$res = hesk_dbQuery("SELECT `cat_order` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` DESC LIMIT 1");
	$row = hesk_dbFetchRow($res);
	$my_order = $row[0]+10;

	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` (`name`,`cat_order`,`autoassign`,`type`, `priority`) VALUES ('".hesk_dbEscape($catname)."','".intval($my_order)."','".intval($_SESSION['cat_autoassign'])."','".intval($_SESSION['cat_type'])."','{$_SESSION['cat_priority']}')");

    hesk_cleanSessionVars('catname');
    hesk_cleanSessionVars('cat_autoassign');
    hesk_cleanSessionVars('cat_type');
    hesk_cleanSessionVars('cat_priority');

    $_SESSION['selcat2'] = hesk_dbInsertID();

	hesk_process_messages(sprintf($hesklang['cat_name_added'],'<i>'.stripslashes($catname).'</i>'),'manage_categories.php','SUCCESS');
} // End new_cat()


function rename_cat()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    $_SERVER['PHP_SELF'] = 'manage_categories.php?catid='.intval( hesk_POST('catid') );

	$catid = hesk_isNumber( hesk_POST('catid'), $hesklang['choose_cat_ren'], $_SERVER['PHP_SELF']);
	$_SESSION['selcat'] = $catid;
    $_SESSION['selcat2'] = $catid;

	$catname = hesk_input( hesk_POST('name'), $hesklang['cat_ren_name'], $_SERVER['PHP_SELF']);
    $_SESSION['catname2'] = stripslashes($catname);

	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `name` LIKE '".hesk_dbEscape( hesk_dbLike($catname) )."' LIMIT 1");
    if (hesk_dbNumRows($res) != 0)
    {
    	$old = hesk_dbFetchAssoc($res);
        if ($old['id'] == $catid)
        {
        	hesk_process_messages($hesklang['noch'],$_SERVER['PHP_SELF'],'NOTICE');
        }
        else
        {
    		hesk_process_messages($hesklang['cndupl'],$_SERVER['PHP_SELF']);
        }
    }

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `name`='".hesk_dbEscape($catname)."' WHERE `id`='".intval($catid)."'");

    unset($_SESSION['selcat']);
    unset($_SESSION['catname2']);

    hesk_process_messages($hesklang['cat_renamed_to'].' <i>'.stripslashes($catname).'</i>',$_SERVER['PHP_SELF'],'SUCCESS');
} // End rename_cat()


function remove()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

    $_SERVER['PHP_SELF'] = 'manage_categories.php';

	$mycat = intval( hesk_GET('catid') ) or hesk_error($hesklang['no_cat_id']);
	if ($mycat == 1)
    {
    	hesk_process_messages($hesklang['cant_del_default_cat'],$_SERVER['PHP_SELF']);
    }

	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($mycat)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `category`=1 WHERE `category`='".intval($mycat)."'");

    hesk_process_messages($hesklang['cat_removed_db'],$_SERVER['PHP_SELF'],'SUCCESS');
} // End remove()


function order_cat()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$catid = intval( hesk_GET('catid') ) or hesk_error($hesklang['cat_move_id']);
	$_SESSION['selcat2'] = $catid;

	$cat_move=intval( hesk_GET('move') );

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=`cat_order`+".intval($cat_move)." WHERE `id`='".intval($catid)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

	/* Update all category fields with new order */
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");

	$i = 10;
	while ($mycat=hesk_dbFetchAssoc($res))
	{
	    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=".intval($i)." WHERE `id`='".intval($mycat['id'])."'");
	    $i += 10;
	}

    header('Location: manage_categories.php');
    exit();
} // End order_cat()


function toggle_autoassign()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$catid = intval( hesk_GET('catid') ) or hesk_error($hesklang['cat_move_id']);
	$_SESSION['selcat2'] = $catid;

    if ( intval( hesk_GET('s') ) )
    {
		$autoassign = 1;
        $tmp = $hesklang['caaon'];
    }
    else
    {
        $autoassign = 0;
        $tmp = $hesklang['caaoff'];
    }

	/* Update auto-assign settings */
	$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `autoassign`='".intval($autoassign)."' WHERE `id`='".intval($catid)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['cat_not_found'],'./manage_categories.php');
    }

    hesk_process_messages($tmp,'./manage_categories.php','SUCCESS');

} // End toggle_autoassign()


function toggle_type()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$catid = intval( hesk_GET('catid') ) or hesk_error($hesklang['cat_move_id']);
	$_SESSION['selcat2'] = $catid;

    if ( intval( hesk_GET('s') ) )
    {
		$type = 1;
        $tmp = $hesklang['cpriv'];
    }
    else
    {
        $type = 0;
        $tmp = $hesklang['cpub'];
    }

	/* Update auto-assign settings */
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `type`='{$type}' WHERE `id`='".intval($catid)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['cat_not_found'],'./manage_categories.php');
    }

    hesk_process_messages($tmp,'./manage_categories.php','SUCCESS');

} // End toggle_type()
?>
