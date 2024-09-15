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

define('INSTALL_PAGE', 'install.php');
require(HESK_PATH . 'install/install_functions.inc.php');

// If no step is defined, start with step 1
if ( ! isset($_SESSION['step']) )
{
    $_SESSION['step']=1;
}
// Check if the license has been agreed to and verify sessions are working
elseif ($_SESSION['step']==1)
{
    $agree = hesk_POST('agree', '');
    if ($agree == 'YES')
    {
		// Are sessions working?
		if ( empty($_SESSION['works']) )
        {
        	hesk_iSessionError();
        }

		// All OK, continue
        $_SESSION['license_agree']=1;
        $_SESSION['step']=2;
    }
    else
    {
        $_SESSION['step']=1;
    }
}

// Test database connection?
if ($_SESSION['step'] == 3 && isset($_POST['dbtest']) )
{
    // Name
    $_SESSION['admin_name'] = hesk_input( hesk_POST('admin_name') );
    if ( strlen($_SESSION['admin_name']) == 0 )
    {
        $_SESSION['admin_name'] = 'Your name';
    }

    // Email
    $_SESSION['admin_email'] = hesk_validateEmail( hesk_POST('admin_email'), 'ERR', 0);
    if ( strlen($_SESSION['admin_email']) == 0 )
    {
        $_SESSION['admin_email'] = 'you@example.com';
    }

	// Username
	$_SESSION['admin_user'] = hesk_input( hesk_POST('admin_user') );
	if ( strlen($_SESSION['admin_user']) == 0 )
	{
		$_SESSION['admin_user'] = 'Administrator';
	}

	// Password
	$_SESSION['admin_pass'] = hesk_input( hesk_POST('admin_pass') );
	if ( strlen($_SESSION['admin_pass']) == 0 )
	{
		$_SESSION['admin_pass'] = substr(str_shuffle("23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ"), 0, mt_rand(8,12) );
	}

	// Password hash for the database
	$_SESSION['admin_hash'] = hesk_password_hash($_SESSION['admin_pass']);

	// Test DB connection
	$hesk_db_link = hesk_iTestDatabaseConnection();

    // Generate HESK table names
	$hesk_tables = array(
        $hesk_settings['db_pfix'].'attachments',
        $hesk_settings['db_pfix'].'auth_tokens',
        $hesk_settings['db_pfix'].'banned_emails',
        $hesk_settings['db_pfix'].'banned_ips',
        $hesk_settings['db_pfix'].'categories',
        $hesk_settings['db_pfix'].'custom_fields',
        $hesk_settings['db_pfix'].'custom_statuses',
        $hesk_settings['db_pfix'].'kb_articles',
        $hesk_settings['db_pfix'].'kb_attachments',
        $hesk_settings['db_pfix'].'kb_categories',
        $hesk_settings['db_pfix'].'logins',
        $hesk_settings['db_pfix'].'log_overdue',
        $hesk_settings['db_pfix'].'mail',
        $hesk_settings['db_pfix'].'mfa_backup_codes',
        $hesk_settings['db_pfix'].'mfa_verification_tokens',
        $hesk_settings['db_pfix'].'notes',
        $hesk_settings['db_pfix'].'online',
        $hesk_settings['db_pfix'].'pipe_loops',
        $hesk_settings['db_pfix'].'replies',
        $hesk_settings['db_pfix'].'reply_drafts',
        $hesk_settings['db_pfix'].'reset_password',
        $hesk_settings['db_pfix'].'service_messages',
        $hesk_settings['db_pfix'].'std_replies',
        $hesk_settings['db_pfix'].'tickets',
        $hesk_settings['db_pfix'].'ticket_templates',
        $hesk_settings['db_pfix'].'users',
	);

	// Check if any of the HESK tables exists
	$res = hesk_dbQuery('SHOW TABLES FROM `'.hesk_dbEscape($hesk_settings['db_name']).'`');

	while ($row = hesk_dbFetchRow($res))
	{
		if (in_array($row[0],$hesk_tables))
		{
			hesk_iDatabase(2);
		}
	}

    // Timezone
    $hesk_settings['timezone'] = hesk_input( hesk_POST('timezone') );
    if ( ! in_array($hesk_settings['timezone'], timezone_identifiers_list()) )
    {
        $hesk_settings['timezone'] = 'UTC';
    }

	// All ok, let's save settings
	hesk_iSaveSettings();

	// Now install HESK database tables
	hesk_iTables();

	// And move to the next step
	$_SESSION['step']=4;
}

// Which step are we at?
switch ($_SESSION['step'])
{
	case 2:
	   hesk_iCheckSetup();
	   break;
	case 3:
	   hesk_iDatabase();
	   break;
	case 4:
	   hesk_iFinish();
	   break;
	default:
	   hesk_iStart();
}


// ******* FUNCTIONS ******* //


function hesk_iFinish()
{
    global $hesk_settings;
    hesk_iHeader();
	?>

	<br />
	<?php hesk_show_success('Congratulations, you have successfully completed HESK database setup!'); ?>

    <h3>Next steps:</h3>

    <ol>

    <li><span style="color:#ff0000">Delete the <b>/install</b> folder from your server!</span><br />&nbsp;</li>

    <li>Remember your login details:<br />

<pre style="font-size: 1.17em">
Username: <span style="color:red; font-weight:bold"><?php echo stripslashes($_SESSION['admin_user']); ?></span>
Password: <span style="color:red; font-weight:bold"><?php echo stripslashes($_SESSION['admin_pass']); ?></span>
</pre>

    <br />&nbsp;

    </li>

    <table>
        <tr>
            <td>
                <form action="<?php echo HESK_PATH . $hesk_settings['admin_dir']; ?>/index.php" method="post">
                <input type="hidden" name="a" value="do_login" />
                <input type="hidden" name="remember_user" value="JUSTUSER" />
                <input type="hidden" name="user" value="<?php echo stripslashes($_SESSION['admin_user']); ?>" />
                <input type="hidden" name="pass" value="<?php echo stripslashes($_SESSION['admin_pass']); ?>" />
                <input type="hidden" name="goto" value="mail.php?a=read&amp;id=1" />
                <input type="submit" value="Read HESK quick start guide" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" />
                </form>
            </td>
            <td> - or -</td>
            <td>
                <form action="<?php echo HESK_PATH . $hesk_settings['admin_dir']; ?>/index.php" method="post">
                <input type="hidden" name="a" value="do_login" />
                <input type="hidden" name="remember_user" value="JUSTUSER" />
                <input type="hidden" name="user" value="<?php echo stripslashes($_SESSION['admin_user']); ?>" />
                <input type="hidden" name="pass" value="<?php echo stripslashes($_SESSION['admin_pass']); ?>" />
                <input type="hidden" name="goto" value="admin_settings_general.php" />
                <input type="submit" value="Skip directly to settings" class="orangebuttonsec" onmouseover="hesk_btn(this,'orangebuttonsecover');" onmouseout="hesk_btn(this,'orangebuttonsec');" />
                </form>
            </td>
        </tr>
    </table>

    </ol>

    <p>&nbsp;</p>

	<?php
    hesk_iFooter();
} // End hesk_iFinish()


function hesk_iTables()
{
	global $hesk_db_link, $hesk_settings;

// -> Attachments
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (
  `att_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` varchar(13) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `saved_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `real_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `size` int(10) unsigned NOT NULL DEFAULT '0',
  `type` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`att_id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Temporary Attachments (Drag & Drop)
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` (
    `att_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    `unique_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `saved_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `real_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `size` int(10) unsigned NOT NULL DEFAULT '0',
    `expires_at` timestamp NOT NULL,
    PRIMARY KEY (`att_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` (
    `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `upload_count` int(10) unsigned NOT NULL DEFAULT 1,
    `last_upload_at` timestamp NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");

// -> Authentication tokens
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `selector` char(12) DEFAULT NULL,
  `token` char(64) DEFAULT NULL,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");

// -> Banned emails
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_emails` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `banned_by` smallint(5) unsigned NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8
");

// -> Banned IPs
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."banned_ips` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `ip_from` int(10) unsigned NOT NULL DEFAULT '0',
  `ip_to` int(10) unsigned NOT NULL DEFAULT '0',
  `ip_display` varchar(100) NOT NULL,
  `banned_by` smallint(5) unsigned NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8
");

// -> Categories
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `cat_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `autoassign` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `autoassign_config` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `priority` enum('0','1','2','3') COLLATE utf8_unicode_ci NOT NULL DEFAULT '3',
  `default_due_date_amount` INT DEFAULT NULL,
  `default_due_date_unit` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// ---> Insert default category
hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` (`id`, `name`, `cat_order`) VALUES (1, 'General', 10)");

// -> Custom fields
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_fields` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `use` enum('0','1','2') NOT NULL DEFAULT '0',
  `place` enum('0','1') NOT NULL DEFAULT '0',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `req` enum('0','1','2') NOT NULL DEFAULT '0',
  `category` text,
  `name` text,
  `value` text,
  `order` smallint(5) UNSIGNED NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`),
  KEY `useType` (`use`,`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// ---> Insert empty custom fields
hesk_dbQuery("
INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_fields` (`id`, `use`, `place`, `type`, `req`, `category`, `name`, `value`, `order`) VALUES
(1, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(2, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(3, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(4, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(5, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(6, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(7, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(8, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(9, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(10, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(11, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(12, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(13, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(14, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(15, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(16, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(17, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(18, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(19, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(20, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(21, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(22, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(23, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(24, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(25, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(26, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(27, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(28, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(29, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(30, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(31, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(32, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(33, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(34, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(35, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(36, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(37, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(38, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(39, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(40, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(41, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(42, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(43, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(44, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(45, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(46, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(47, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(48, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(49, '0', '0', 'text', '0', NULL, '', NULL, 1000),
(50, '0', '0', 'text', '0', NULL, '', NULL, 1000)
");

// -> Custom statuses
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `color` varchar(6) NOT NULL,
  `can_customers_change` enum('0','1') NOT NULL DEFAULT '1',
  `order` smallint(5) UNSIGNED NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> KB Articles
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `catid` smallint(5) unsigned NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author` smallint(5) unsigned NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `keywords` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `rating` float NOT NULL DEFAULT '0',
  `votes` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `views` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `type` enum('0','1','2') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `html` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `sticky` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `art_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `history` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `attachments` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `catid` (`catid`),
  KEY `sticky` (`sticky`),
  KEY `type` (`type`),
  FULLTEXT KEY `subject` (`subject`,`content`,`keywords`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> KB Attachments
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_attachments` (
  `att_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `saved_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `real_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `size` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`att_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> KB Categories
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `parent` smallint(5) unsigned NOT NULL,
  `articles` smallint(5) unsigned NOT NULL DEFAULT '0',
  `articles_private` smallint(5) unsigned NOT NULL DEFAULT '0',
  `articles_draft` smallint(5) unsigned NOT NULL DEFAULT '0',
  `cat_order` smallint(5) unsigned NOT NULL,
  `type` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `parent` (`parent`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// ---> Insert default KB category
hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` (`id`, `name`, `parent`, `cat_order`, `type`) VALUES (1, 'Knowledgebase', 0, 10, '0')");

// -> Login attempts
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."logins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `number` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Log of overdue tickets
hesk_dbQuery("
CREATE TABLE IF NOT EXISTS `".hesk_dbEscape($hesk_settings['db_pfix'])."log_overdue` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ticket` mediumint(8) UNSIGNED NOT NULL,
  `category` smallint(5) UNSIGNED NOT NULL,
  `priority` enum('0','1','2','3') NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `owner` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `due_date` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  `comments` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket` (`ticket`),
  KEY `category` (`category`),
  KEY `priority` (`priority`),
  KEY `status` (`status`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");

// -> Private messages
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from` smallint(5) unsigned NOT NULL,
  `to` smallint(5) unsigned NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `deletedby` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `from` (`from`),
  KEY `to` (`to`,`read`,`deletedby`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// ---> Insert welcome email
hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` (`id`, `from`, `to`, `subject`, `message`, `dt`, `read`, `deletedby`) VALUES (NULL, 9999, 1, 'Hesk quick start guide', '".hesk_dbEscape("</p><div style=\"text-align:justify; padding-left: 10px; padding-right: 10px;\">\n\n<h2 style=\"padding-left:0px\">Welcome to Hesk, an excellent tool for improving your customer support!</h2>\n\n<h3>Below is a short guide to help you get started.</h3>\n\n&nbsp;\n\n<div class=\"main__content notice-flash \">\n<div class=\"notification orange\">\nAn up-to-date and expanded guide is available at <a href=\"https://www.hesk.com/knowledgebase/?article=109\" target=\"_blank\">Hesk online Quick Start Guide</a>.</div>\n</div>\n\n&nbsp;\n\n<h3>&raquo; Step #1: Set up your profile</h3>\n\n<ol>\n<li>go to <a href=\"profile.php\">Profile</a>,</li>\n<li>set your name and email address.</li>\n</ol>\n\n&nbsp;\n\n<h3>&raquo; Step #2: Configure Hesk</h3>\n\n<ol>\n<li>go to <a href=\"admin_settings_general.php\">Settings</a>,</li>\n<li>for a quick start, modify these settings on the \"General\" tab:<br><br>\n<b>Website title</b> - enter the title of your main website (not your help desk),<br>\n<b>Website URL</b> - enter the URL of your main website,<br>\n<b>Webmaster email</b> - enter an alternative email address people can contact in case your Hesk database is down<br>&nbsp;\n</li>\n<li>you can come back to the settings page later and explore all the options. To view details about a setting, click the [?]</li>\n</ol>\n\n&nbsp;\n\n<h3>&raquo; Step #3: Add support categories</h3>\n\n<p>Go to <a href=\"manage_categories.php\">Categories</a> to add support ticket categories.</p>\n<p>You cannot delete the default category, but you can rename it.</p>\n\n&nbsp;\n\n<h3>&raquo; Step #4: Add your support team members</h3>\n\n<p>Go to <a href=\"manage_users.php\">Team</a> to create new support staff accounts.</p>\n<p>You can use two user types in Hesk:</p>\n<ul>\n<li><b>Administrators</b> who have full access to all Hesk features</li>\n<li><b>Staff</b> who you can restrict access to categories and features</li>\n</ul>\n\n&nbsp;\n\n<h3>&raquo; Step #5: Useful tools</h3>\n\n<p>You can do a lot in the <a href=\"banned_emails.php\">Tools</a> section, for example:</p>\n<ul>\n<li>create custom ticket statuses,</li>\n<li>add custom input fields to the &quot;Submit a ticket&quot; form,</li>\n<li>make public announcements (Service messages),</li>\n<li>modify email templates,</li>\n<li>ban disruptive customers,</li>\n<li>and more.</li>\n</ul>\n\n&nbsp;\n\n<h3>&raquo; Step #6: Create a Knowledgebase</h3>\n\n<p>A Knowledgebase is a collection of articles, guides, and answers to frequently asked questions, usually organized in multiple categories.</p>\n<p>A clear and comprehensive knowledgebase can drastically reduce the number of support tickets you receive, thereby saving you significant time and effort in the long run.</p>\n<p>Go to <a href=\"manage_knowledgebase.php\">Knowledgebase</a> to create categories and write articles for your knowledgebase.</p>\n\n&nbsp;\n\n<h3>&raquo; Step #7: Don\'t repeat yourself</h3>\n\n<p>Sometimes several support tickets address the same issues - allowing you to use pre-written (&quot;canned&quot;) responses.</p>\n<p>To compose canned responses, go to the <a href=\"manage_canned.php\">Templates &gt; Responses</a> page.</p>\n<p>Similarly, you can create <a href=\"manage_ticket_templates.php\">Templates &gt; Tickets</a> if your staff will be submitting support tickets on the client\'s behalf, for example, from telephone conversations.</p>\n\n&nbsp;\n\n<h3>&raquo; Step #8: Secure your help desk</h3>\n\n<p>Make sure your help desk is as secure as possible by going through the <a href=\"https://www.hesk.com/knowledgebase/?article=82\">Hesk security checklist</a>.</p>\n\n&nbsp;\n\n<h3>&raquo; Step #9: Stay updated</h3>\n\n<p>Hesk regularly receives improvements and bug fixes; make sure you know about them!</p>\n<ul>\n<li>for fast notifications, <a href=\"https://twitter.com/HESKdotCOM\">follow us on <b>Twitter</b></a></li>\n<li>for email notifications, subscribe to our low-volume zero-spam <a href=\"https://www.hesk.com/newsletter.php\">newsletter</a></li>\n</ul>\n\n&nbsp;\n\n<h3>&raquo; Step #10: Look professional</h3>\n\n<p>To not only support Hesk development but also look more professional, <a href=\"https://www.hesk.com/get/hesk3-license\">remove &quot;Powered by&quot; links</a> from your help desk.</p>\n\n&nbsp;\n\n<h3>&raquo; Step #11: Too much hassle? Switch to Hesk Cloud for the ultimate experience</h3>\n\n<p>Experience the best of Hesk by moving your help desk into the Hesk Cloud:</p>\n<ul>\n<li>exclusive advanced modules,</li>\n<li>automated updates,</li>\n<li>free migration of your existing Hesk tickets and settings,</li>\n<li>we take care of maintenance, server setup and optimization, backups, and more!</li>\n</ul>\n\n<p>&nbsp;<br><a href=\"https://www.hesk.com/get/hesk3-cloud\" class=\"btn btn--blue-border\" style=\"text-decoration:none\">Click here to learn more about Hesk Cloud</a></p>\n\n<p>&nbsp;</p>\n\n<p>&nbsp;</p>\n\n<p>Again, welcome to Hesk, and enjoy using it!</p>\n\n<p>Klemen Stirn<br>\nFounder<br>\n<a href=\"https://www.hesk.com\">https://www.hesk.com</a></p>\n\n<p>&nbsp;</p>\n\n</div><p>")."', NOW(), '0', 9999)");

// -> MFA Verification Tokens
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) UNSIGNED NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `verification_token` (`verification_token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");

// -> MFA Backup Codes
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` smallint(5) unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");

// -> Notes
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `ticket` mediumint(8) unsigned NOT NULL,
  `who` smallint(5) unsigned NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `attachments` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticketid` (`ticket`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> OAuth providers
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `authorization_url` text NOT NULL,
  `token_url` text NOT NULL,
  `client_id` text NOT NULL,
  `client_secret` text NOT NULL,
  `scope` text NOT NULL,
  `no_val_ssl` tinyint(4) NOT NULL DEFAULT '0',
  `verified` smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");

// -> OAuth tokens
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `token_value` text DEFAULT NULL,
  `token_type` varchar(32) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");

// -> Online
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."online` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) unsigned NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tmp` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `dt` (`dt`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Pipe loops
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."pipe_loops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `hits` smallint(1) unsigned NOT NULL DEFAULT '0',
  `message_hash` char(32) COLLATE utf8_unicode_ci NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`,`hits`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Replies
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `replyto` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `message_html` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attachments` mediumtext COLLATE utf8_unicode_ci,
  `staffid` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rating` enum('1','5') COLLATE utf8_unicode_ci DEFAULT NULL,
  `read` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `replyto` (`replyto`),
  KEY `dt` (`dt`),
  KEY `staffid` (`staffid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Reply drafts
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `owner` smallint(5) unsigned NOT NULL,
  `ticket` mediumint(8) unsigned NOT NULL,
  `message` mediumtext CHARACTER SET utf8 NOT NULL,
  `message_html` mediumtext CHARACTER SET utf8 DEFAULT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`),
  KEY `ticket` (`ticket`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Reset password
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."reset_password` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user` smallint(5) unsigned NOT NULL,
  `hash` char(40) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
");

// -> Service messages
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author` smallint(5) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `language` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` enum('0','1','2','3','4') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `type` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `order` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
");

// -> Canned Responses
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."std_replies` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `message_html` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `reply_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Tickets
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `trackid` varchar(13) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(1000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `category` smallint(5) unsigned NOT NULL DEFAULT '1',
  `priority` enum('0','1','2','3') COLLATE utf8_unicode_ci NOT NULL DEFAULT '3',
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `message_html` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  `lastchange` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `firstreply` timestamp NULL DEFAULT NULL,
  `closedat` timestamp NULL DEFAULT NULL,
  `articles` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `language` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `openedby` mediumint(8) DEFAULT '0',
  `firstreplyby` smallint(5) unsigned DEFAULT NULL,
  `closedby` mediumint(8) NULL DEFAULT NULL,
  `replies` smallint(5) unsigned NOT NULL DEFAULT '0',
  `staffreplies` smallint(5) unsigned NOT NULL DEFAULT '0',
  `owner` smallint(5) unsigned NOT NULL DEFAULT '0',
  `assignedby` mediumint(8) NULL DEFAULT NULL,
  `time_worked` time NOT NULL DEFAULT '00:00:00',
  `lastreplier` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `replierid` smallint(5) unsigned DEFAULT NULL,
  `archive` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `locked` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `attachments` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `merged` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `history` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom1` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom2` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom3` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom4` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom5` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom6` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom7` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom8` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom9` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom10` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom11` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom12` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom13` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom14` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom15` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom16` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom17` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom18` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom19` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom20` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom21` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom22` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom23` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom24` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom25` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom26` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom27` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom28` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom29` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom30` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom31` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom32` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom33` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom34` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom35` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom36` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom37` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom38` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom39` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom40` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom41` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom42` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom43` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom44` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom45` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom46` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom47` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom48` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom49` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `custom50` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `overdue_email_sent` tinyint(1) DEFAULT '0',
  `satisfaction_email_sent` tinyint(1) DEFAULT '0',
  `satisfaction_email_dt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trackid` (`trackid`),
  KEY `archive` (`archive`),
  KEY `categories` (`category`),
  KEY `statuses` (`status`),
  KEY `owner` (`owner`),
  KEY `openedby` (`openedby`,`firstreplyby`,`closedby`),
  KEY `dt` (`dt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Ticket templates
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_templates` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `message` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `message_html` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `tpl_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

// -> Users
hesk_dbQuery("
CREATE TABLE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `pass` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `isadmin` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `signature` varchar(1000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `language` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `categories` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `afterreply` enum('0','1','2') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `autostart` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `autoreload` smallint(5) unsigned NOT NULL DEFAULT '0',
  `notify_customer_new` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_customer_reply` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `show_suggested` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_new_unassigned` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_new_my` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_reply_unassigned` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_reply_my` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_assigned` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_pm` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_note` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_overdue_unassigned` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `notify_overdue_my` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `default_list` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `autoassign` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `heskprivileges` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ratingneg` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `ratingpos` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `rating` float NOT NULL DEFAULT '0',
  `replies` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `mfa_enrollment` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `mfa_secret` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `autoassign` (`autoassign`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
");

hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (`id`, `user`, `pass`, `isadmin`, `name`, `email`, `heskprivileges`) VALUES (1, '".hesk_dbEscape($_SESSION['admin_user'])."', '".hesk_dbEscape($_SESSION['admin_hash'])."', '1', '".hesk_dbEscape($_SESSION['admin_name'])."', '".hesk_dbEscape($_SESSION['admin_email'])."', '')");

	return true;

} // End hesk_iTables()


function hesk_iSaveSettings()
{
	global $hesk_settings, $hesklang;

	$spam_question = hesk_generate_SPAM_question();

	$hesk_settings['secimg_use'] = empty($_SESSION['set_captcha']) ? 0 : 1;
	$hesk_settings['use_spamq'] = empty($_SESSION['use_spamq']) ? 0 : 1;
	$hesk_settings['question_ask'] = $spam_question[0];
	$hesk_settings['question_ans'] = $spam_question[1];
	$hesk_settings['set_attachments'] = empty($_SESSION['set_attachments']) ? 0 : 1;
	$hesk_settings['hesk_version'] = HESK_NEW_VERSION;

	if (isset($_SERVER['HTTP_HOST']))
	{
        $protocol = HESK_SSL? 'https://' : 'http://';
		$hesk_settings['site_url']=$protocol . $_SERVER['HTTP_HOST'];

		if (isset($_SERVER['REQUEST_URI']))
		{
			$hesk_settings['hesk_url']=$protocol . $_SERVER['HTTP_HOST'] . str_replace('/install/install.php','',$_SERVER['REQUEST_URI']);
		}
	}

	/* Encode and escape characters */
	$set = $hesk_settings;
	foreach ($hesk_settings as $k=> $v)
	{
		if (is_array($v) || is_object($v))
		{
			continue;
		}
		$set[$k] = addslashes($v);
	}
	$set['debug_mode'] = 0;

	$set['email_providers'] = count($set['email_providers']) ?  "'" . implode("','", $set['email_providers']) . "'" : '';
	$set['notify_spam_tags'] = count($set['notify_spam_tags']) ?  "'" . implode("','", $set['notify_spam_tags']) . "'" : '';

    // If SSL is enabled, let's force it by default
    $set['force_ssl'] = HESK_SSL ? 1 : 0;

    // Generate an URL access key
    $length = mt_rand(20, 30);
    $result = '';
    $characters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ1234567890-_.';
    $charactersLength = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
        $result .=  $characters[mt_rand(0, $charactersLength-1)];
    }
    $set['url_key'] = $result;

	hesk_iSaveSettingsFile($set);

	return true;
} // End hesk_iSaveSettings()
?>
