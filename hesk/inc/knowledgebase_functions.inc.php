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

/*** FUNCTIONS ***/

function hesk_kbArticleContentPreview($txt)
{
	global $hesk_settings;

	// Strip HTML tags
	$txt = strip_tags($txt);

	// If text is larger than article preview length, shorten it
	if (hesk_mb_strlen($txt) > $hesk_settings['kb_substrart'])
	{
		// The quick but not 100% accurate way (number of chars displayed may be lower than the limit)
		return hesk_mb_substr($txt, 0, $hesk_settings['kb_substrart']) . '...';

		// If you want a more accurate, but also slower way, use this instead
		// return hesk_htmlentities( hesk_mb_substr( hesk_html_entity_decode($txt), 0, $hesk_settings['kb_substrart'] ) ) . '...';
	}

	return $txt;
} // END hesk_kbArticleContentPreview()


function hesk_kbTopArticles($how_many, $index = 1)
{
	global $hesk_settings, $hesklang;

	// Index page or KB main page?
	if ($index)
	{
		// Disabled?
		if ( ! $hesk_settings['kb_index_popart'])
		{
			return true;
		}

		// Show title in italics
		$font_weight = 'i';
	}
	else
	{
		// Disabled?
		if ( ! $hesk_settings['kb_popart'])
		{
			return true;
		}

		// Show title in bold
		$font_weight = 'b';
    }

    /* Get list of articles from the database */
    $res = hesk_dbQuery("SELECT `t1`.`id`,`t1`.`subject`,`t1`.`views` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` AS `t1`
                        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                        WHERE `t1`.`type`='0' AND `t2`.`type`='0'
                        ORDER BY `t1`.`sticky` DESC, `t1`.`views` DESC, `t1`.`art_order` ASC LIMIT ".intval($how_many));

    /* If no results found end here */
    if (hesk_dbNumRows($res) == 0)
    {
        echo '<p><i>'.$hesklang['noa'].'</i><br />&nbsp;</p>';
        return true;
    }

    // This is used to count required empty lines for index page
    if ( ! isset($hesk_settings['kb_spacing']))
    {
        $hesk_settings['kb_spacing'] = 0;
    }

    // Print a line for spacing if we don't show popular articles
    if ( ! $index)
    {
        echo '<hr />';
    }
	?>

    <table border="0" width="100%">
	<tr>
	<td>&raquo; <<?php echo $font_weight; ?>><?php echo $hesklang['popart']; ?></<?php echo $font_weight; ?>></td>

	<?php
    /* Show number of views? */
	if ($hesk_settings['kb_views'])
	{
		echo '<td style="text-align:right"><i>' . $hesklang['views'] . '</i></td>';
	}
	?>

	</tr>
	</table>

    <div align="center">
    <table border="0" cellspacing="1" cellpadding="3" width="100%">
    <?php
    // Remember what articles are printed for "Top" so we don't print them again in "Latest"
    $hesk_settings['kb_top_articles_printed'] = array();

	while ($article = hesk_dbFetchAssoc($res))
	{
        $hesk_settings['kb_spacing']--;

        $hesk_settings['kb_top_articles_printed'][] = $article['id'];

		echo '
		<tr>
		<td>
		<table border="0" width="100%" cellspacing="0" cellpadding="0">
		<tr>
		<td width="1" valign="top"><img src="img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
		<td valign="top">&nbsp;<a href="knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>
		';

		if ($hesk_settings['kb_views'])
		{
			echo '<td valign="top" style="text-align:right" width="200">' . $article['views'] . '</td>';
		}

		echo '
		</tr>
		</table>
		</td>
		</tr>
		';
	}
	?>

    </table>
    </div>

    &nbsp;

    <?php
} // END hesk_kbTopArticles()


function hesk_kbLatestArticles($how_many, $index = 1)
{
	global $hesk_settings, $hesklang;

	// Index page or KB main page?
	if ($index)
	{
		// Disabled?
		if ( ! $hesk_settings['kb_index_latest'])
		{
			return true;
		}

		// Show title in italics
		$font_weight = 'i';
	}
	else
	{
		// Disabled?
		if ( ! $hesk_settings['kb_latest'])
		{
			return true;
		}

		// Show title in bold
		$font_weight = 'b';
    }

    // Don't include articles that have already been printed under "Top" articles
    $sql_top = '';
    if (isset($hesk_settings['kb_top_articles_printed']) && count($hesk_settings['kb_top_articles_printed']))
    {
        $sql_top = ' AND `t1`.`id` NOT IN ('.implode(',', $hesk_settings['kb_top_articles_printed']).')';
    }

    /* Get list of articles from the database */
    $res = hesk_dbQuery("SELECT `t1`.`id`,`t1`.`subject`,`t1`.`dt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` AS `t1`
                        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                        WHERE `t1`.`type`='0' AND `t2`.`type`='0' {$sql_top}
                        ORDER BY `t1`.`dt` DESC LIMIT ".intval($how_many));

    // Print a line for spacing if we don't show popular articles
    if ( ! $index && ! $hesk_settings['kb_popart'])
    {
        echo '<hr />';
    }

    // If no results found end here
    if (hesk_dbNumRows($res) == 0)
    {
        if ( ! $hesk_settings['kb_popart'])
        {
            echo '<p><i>'.$hesklang['noa'].'</i><br />&nbsp;</p>';
        }
        return true;
    }

    // This is used to count required empty lines for index page
    if ( ! isset($hesk_settings['kb_spacing']))
    {
        $hesk_settings['kb_spacing'] = 0;
    }
	?>

    <table border="0" width="100%">
	<tr>
	<td>&raquo; <<?php echo $font_weight; ?>><?php echo $hesklang['latart']; ?></<?php echo $font_weight; ?>></td>

	<?php
    /* Show number of views? */
	if ($hesk_settings['kb_date'])
	{
		echo '<td style="text-align:right"><i>' . $hesklang['dta'] . '</i></td>';
	}
	?>

	</tr>
	</table>

    <div align="center">
    <table border="0" cellspacing="1" cellpadding="3" width="100%">
    <?php

	while ($article = hesk_dbFetchAssoc($res))
	{
        $hesk_settings['kb_spacing']--;

		echo '
		<tr>
		<td>
		<table border="0" width="100%" cellspacing="0" cellpadding="0">
		<tr>
		<td width="1" valign="top"><img src="img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
		<td valign="top">&nbsp;<a href="knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>
		';

		if ($hesk_settings['kb_date'])
		{
			echo '<td valign="top" style="text-align:right" width="200">' . hesk_date($article['dt'], true) . '</td>';
		}

		echo '
		</tr>
		</table>
		</td>
		</tr>
		';
	}
	?>

    </table>
    </div>

    &nbsp;

    <?php
} // END hesk_kbLatestArticles()


function hesk_kbSearchLarge($admin = '')
{
	global $hesk_settings, $hesklang;

	$action = 'knowledgebase.php';

	if ($admin)
	{
		if ( ! $hesk_settings['kb_search'])
		{
			return '';
		}
		$action = 'knowledgebase_private.php';
	}
	elseif ($hesk_settings['kb_search'] != 2)
	{
		return '';
	}
	?>
	<br />

	<div style="text-align:center">
		<form action="<?php echo $action; ?>" method="get" style="display: inline; margin: 0;" name="searchform">
		<span class="largebold"><?php echo $hesklang['ask']; ?></span>
        <input type="text" name="search" class="searchfield" />
		<input type="submit" value="<?php echo $hesklang['search']; ?>" title="<?php echo $hesklang['search']; ?>" class="searchbutton" /><br />
		</form>
	</div>

	<br />

	<!-- START KNOWLEDGEBASE SUGGEST -->
		<div id="kb_suggestions" style="display:none">
			<img src="<?php echo HESK_PATH; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo $hesklang['lkbs']; ?></i>
		</div>

		<script language="Javascript" type="text/javascript"><!--
		hesk_suggestKBsearch(<?php echo $admin; ?>);
		//-->
		</script>
	<!-- END KNOWLEDGEBASE SUGGEST -->

	<br />

	<?php
} // END hesk_kbSearchLarge()


function hesk_kbSearchSmall()
{
	global $hesk_settings, $hesklang;

	if ($hesk_settings['kb_search'] != 1)
	{
		return '';
	}
    ?>

	<td style="text-align:right" valign="top" width="300">
		<div style="display:inline;">
			<form action="knowledgebase.php" method="get" style="display: inline; margin: 0;">
			<input type="text" name="search" class="searchfield sfsmall" />
			<input type="submit" value="<?php echo $hesklang['search']; ?>" title="<?php echo $hesklang['search']; ?>" class="searchbutton sbsmall" />
			</form>
		</div>
	</td>

	<?php
} // END hesk_kbSearchSmall()


function hesk_detect_bots()
{
	$botlist = array('googlebot', 'msnbot', 'slurp', 'alexa', 'teoma', 'froogle',
	'gigabot', 'inktomi', 'looksmart', 'firefly', 'nationaldirectory',
	'ask jeeves', 'tecnoseek', 'infoseek', 'webfindbot', 'girafabot',
	'crawl', 'www.galaxy.com', 'scooter', 'appie', 'fast', 'webbug', 'spade', 'zyborg', 'rabaz',
	'baiduspider', 'feedfetcher-google', 'technoratisnoop', 'rankivabot',
	'mediapartners-google', 'crawler', 'spider', 'robot', 'bot/', 'bot-','voila');

	if ( ! isset($_SERVER['HTTP_USER_AGENT']))
    {
    	return false;
    }

    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

	foreach ($botlist as $bot)
    {
    	if (strpos($ua,$bot) !== false)
        {
        	return true;
        }
    }

	return false;
} // END hesk_detect_bots()
