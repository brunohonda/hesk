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
define('TEMPLATE_PATH', HESK_PATH . "theme/{$hesk_settings['site_theme']}/");
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');

// Are we in maintenance mode?
hesk_check_maintenance();

// Are we in "Knowledgebase only" mode?
hesk_check_kb_only();

// Connect to the database and start a session
hesk_load_database_functions();
hesk_dbConnect();
hesk_session_start('CUSTOMER');

// Do we require logged-in customers to view the help desk?
hesk_isCustomerLoggedIn($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required'] == 2);

// What should we do?
$action = hesk_REQUEST('a');

switch ($action)
{
	case 'add':
        print_add_ticket();
	    break;

	case 'forgot_tid':
        forgot_tid();
	    break;

	default:
		print_start();
}

// Print footer
exit();

/*** START FUNCTIONS ***/


function print_select_category($customerUserContext)
{
	global $hesk_settings, $hesklang;

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['select_category'];

	// A categoy needs to be selected
	if (isset($_GET['category']) && empty($_GET['category']))
	{
		hesk_process_messages($hesklang['sel_app_cat'],'NOREDIRECT','NOTICE');
	}

    /* This will handle error, success and notice messages */
    $messages = hesk_get_messages();

	$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/create-ticket/category-select.php', [
        'messages' => $messages,
        'serviceMessages' => hesk_get_service_messages('t-cat'),
        'accountRequired' => $hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required'],
        'customerLoggedIn' => $customerUserContext !== null,
        'customerUserContext' => $customerUserContext
    ]);

	return true;
} // END print_select_category()


function print_add_ticket()
{
	global $hesk_settings, $hesklang;

	// Load custom fields
	require_once(HESK_PATH . 'inc/custom_fields.inc.php');

    // Load priorities
    require_once(HESK_PATH . 'inc/priorities.inc.php');

	// Load calendar JS and CSS
    define('CALENDAR',1);

	// Auto-focus first empty or error field
	define('AUTOFOCUS', true);

    $customerUserContext = hesk_isCustomerLoggedIn($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required']);

	// Pre-populate fields
	// Customer name
	if (isset($_REQUEST['name'])) {
		$_SESSION['c_name'] = $_REQUEST['name'];
	}

	// Customer email address
	if (isset($_REQUEST['email'])) {
		$_SESSION['c_email']  = $_REQUEST['email'];
		$_SESSION['c_email2'] = $_REQUEST['email'];
	}

    // Followers / CC
    if (isset($_REQUEST['cc'])) {
        $_SESSION['c_followers'] = $_REQUEST['cc'];
    }

    // Priority
    if ( isset($_REQUEST['priority']) && isset($hesk_settings['priorities'][$_REQUEST['priority']])) {
        $_SESSION['c_priority'] = $hesk_settings['priorities'][$_REQUEST['priority']]['id'];
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
    $res = hesk_dbQuery("SELECT `id`, `name`, `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `type`='0' ORDER BY `cat_order` ASC");
    while ($row=hesk_dbFetchAssoc($res)) {
        $hesk_settings['categories'][$row['id']] = array(
            'name' => $row['name'],
            'priority' => $row['priority']
        );
    }

	$number_of_categories = count($hesk_settings['categories']);

	if ($number_of_categories == 0)
	{
		$category = 1;
        $res = hesk_dbQuery("SELECT `id`, `name`, `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`=1");

        while ($row=hesk_dbFetchAssoc($res)) {
            $hesk_settings['categories'][$row['id']] = array(
                'name' => $row['name'],
                'priority' => $row['priority']
            );
        }
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
			return print_select_category($customerUserContext);
		}
	}

    // Set the default category priority
    if ( ! isset($_SESSION['c_priority'])) {
        $_SESSION['c_priority'] = intval($hesk_settings['categories'][$category]['priority']);
    }

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['submit_ticket'];

	$messages = hesk_get_messages();

	$visible_custom_fields_before_message = array();
	$visible_custom_fields_after_message = array();
	$custom_fields_before_message = array();
	$custom_fields_after_message = array();
    foreach ($hesk_settings['custom_fields'] as $k=>$v) {
        if ($v['use'] == 1 && hesk_is_custom_field_in_category($k, $category)) {
            if ($v['type'] == 'checkbox') {
                $k_value = array();
                if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"])) {
                    foreach ($_SESSION["c_$k"] as $myCB) {
                        $k_value[] = stripslashes(hesk_input($myCB));
                    }
                }
            } elseif (isset($_SESSION["c_$k"])) {
                $k_value = stripslashes(hesk_input($_SESSION["c_$k"]));
            } else {
                $k_value = '';
            }

            switch ($v['type']) {
                /* Radio box */
                case 'radio':
                    $v['iserror'] = in_array($k, $_SESSION['iserror']);
                    $v['name'] = $k;

                    $v['value']['options'] = array();
                    foreach ($v['value']['radio_options'] as $option) {
                        if (strlen($k_value) == 0) {
                            $k_value = $option;
                            $checked = empty($v['value']['no_default']);
                        } elseif ($k_value == $option) {
                            $k_value = $option;
                            $checked = true;
                        } else {
                            $checked = false;
                        }

                        $v['value']['options'][] = array(
                            'value' => $option,
                            'selected' => $checked
                        );
                    }

                    if ($v['place'] == 0) {
                        $visible_custom_fields_before_message[] = $v;
                        $custom_fields_before_message[] = $v;
                    } else {
                        $visible_custom_fields_after_message[] = $v;
                        $custom_fields_after_message[] = $v;
                    }
                    break;

                /* Select drop-down box */
                case 'select':
                    $v['iserror'] = in_array($k, $_SESSION['iserror']);
                    $v['name'] = $k;

                    $v['value']['options'] = array();
                    foreach ($v['value']['select_options'] as $option) {
                        if ($k_value == trim($option)) {
                            $k_value = $option;
                            $selected = true;
                        } else {
                            $selected = false;
                        }

                        $v['value']['options'][] = array(
                            'value' => $option,
                            'selected' => $selected
                        );
                    }

                    if ($v['place'] == 0) {
                        $visible_custom_fields_before_message[] = $v;
                        $custom_fields_before_message[] = $v;
                    } else {
                        $visible_custom_fields_after_message[] = $v;
                        $custom_fields_after_message[] = $v;
                    }
                    break;

                /* Checkbox */
                case 'checkbox':
                    $v['iserror'] = in_array($k, $_SESSION['iserror']);
                    $v['name'] = $k;

                    $v['value']['options'] = array();
                    foreach ($v['value']['checkbox_options'] as $option) {
                        if (in_array($option, $k_value)) {
                            $checked = 'checked';
                        } else {
                            $checked = '';
                        }

                        $v['value']['options'][] = array(
                            'value' => $option,
                            'selected' => $checked
                        );
                    }

                    if ($v['place'] == 0) {
                        $visible_custom_fields_before_message[] = $v;
                        $custom_fields_before_message[] = $v;
                    } else {
                        $visible_custom_fields_after_message[] = $v;
                        $custom_fields_after_message[] = $v;
                    }
                    break;

                /* Large text box */
                // Date
                case 'textarea':
                case 'date':
                case 'email':
                    $v['original_value'] = $k_value;
                    $v['iserror'] = in_array($k, $_SESSION['iserror']);
                    $v['name'] = $k;

                    if ($v['place'] == 0) {
                        $visible_custom_fields_before_message[] = $v;
                        $custom_fields_before_message[] = $v;
                    } else {
                        $visible_custom_fields_after_message[] = $v;
                        $custom_fields_after_message[] = $v;
                    }
                    break;

                // Hidden
                case 'hidden':
                    if (strlen($k_value) != 0 || isset($_SESSION["c_$k"])) {
                        $v['value']['default_value'] = $k_value;
                    }

                    $v['name'] = $k;

                    if ($v['place'] == 0) {
                        $custom_fields_before_message[] = $v;
                    } else {
                        $custom_fields_after_message[] = $v;
                    }
                    break;

                /* Default text input */
                default:
                    if (strlen($k_value) != 0 || isset($_SESSION["c_$k"])) {
                        $v['value']['default_value'] = $k_value;
                    }

                    $v['iserror'] = in_array($k, $_SESSION['iserror']);
                    $v['name'] = $k;

                    if ($v['place'] == 0) {
                        $visible_custom_fields_before_message[] = $v;
                        $custom_fields_before_message[] = $v;
                    } else {
                        $visible_custom_fields_after_message[] = $v;
                        $custom_fields_after_message[] = $v;
                    }
            }
        }
    }

	$hesk_settings['render_template'](TEMPLATE_PATH . 'customer/create-ticket/create-ticket.php', array(
	        'categoryId' => $category,
	        'categoryName' => $hesk_settings['categories'][$category]['name'],
            'messages' => $messages,
            'serviceMessages' => hesk_get_service_messages('t-add'),
            'visibleCustomFieldsBeforeMessage' => $visible_custom_fields_before_message,
            'visibleCustomFieldsAfterMessage' => $visible_custom_fields_after_message,
            'customFieldsBeforeMessage' => $custom_fields_before_message,
            'customFieldsAfterMessage' => $custom_fields_after_message,
            'accountRequired' => $hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required'],
            'customerLoggedIn' => $customerUserContext !== null,
            'customerUserContext' => $customerUserContext
    ));

    hesk_cleanSessionVars('iserror');
    hesk_cleanSessionVars('isnotice');
    hesk_cleanSessionVars('c_priority');

    return true;
} // End print_add_ticket()


function print_start()
{
	global $hesk_settings, $hesklang;

    // Include KB functionality only if we have any public articles

    $top_articles = array();
    $latest_articles = array();
    has_public_kb();
    if ($hesk_settings['kb_enable'])
    {
        require(HESK_PATH . 'inc/knowledgebase_functions.inc.php');

        /* Get list of top articles */
        $top_articles = hesk_kbTopArticles($hesk_settings['kb_index_popart']);

        /* Get list of latest articles */
        $latest_articles = hesk_kbLatestArticles($hesk_settings['kb_index_latest']);
    }

    // Service Messages
    $service_messages = hesk_get_service_messages('home');

    // If a customer is logged in, provide the user context for the navbar.
    if ($hesk_settings['customer_accounts'] === 0) {
        $user_context = null;
        $customer_logged_in = false;
    } else {
        $user_context = hesk_isCustomerLoggedIn(false);
        $customer_logged_in = $user_context !== null;
    }

    $messages = hesk_get_messages();


    $hesk_settings['render_template'](TEMPLATE_PATH . 'customer/index.php', array(
        //region Deprecated variables -- other pages use camelCase variable names, but not the index page for whatever reason.
        //     keeping this around though so that custom templates don't instantly break on update.
        'top_articles' => $top_articles,
        'latest_articles' => $latest_articles,
        'service_messages' => $service_messages,
        //endregion
        'topArticles' => $top_articles,
        'latestArticles' => $latest_articles,
        'serviceMessages' => $service_messages,
        'messages' => $messages,
        'accountRequired' => $hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required'],
        'customerLoggedIn' => $customer_logged_in,
        'customerUserContext' => $user_context
    ));
} // End print_start()


function forgot_tid()
{
	global $hesk_settings, $hesklang;

	require(HESK_PATH . 'inc/email_functions.inc.php');

    // Check anti-SPAM image
    if ($hesk_settings['secimg_use'])
    {
        // Using reCAPTCHA?
        if ($hesk_settings['recaptcha_use'])
        {
            require(HESK_PATH . 'inc/recaptcha/recaptchalib_v2.php');

            $resp = null;
            $reCaptcha = new ReCaptcha($hesk_settings['recaptcha_private_key']);

            // Was there a reCAPTCHA response?
            if ( isset($_POST["g-recaptcha-response"]) )
            {
                $resp = $reCaptcha->verifyResponse(hesk_getClientIP(), hesk_POST("g-recaptcha-response") );
            }

            if ($resp != null && $resp->success)
            {
                // OK
            }
            else
            {
                hesk_process_messages($hesklang['recaptcha_error'],'ticket.php?remind=1');
            }
        }
        // Using PHP generated image
        else
        {
            $mysecnum = intval( hesk_POST('mysecnum', 0) );

            if ( empty($mysecnum) )
            {
                hesk_process_messages($hesklang['sec_miss'],'ticket.php?remind=1');
            }
            else
            {
                require(HESK_PATH . 'inc/secimg.inc.php');
                $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                if ( isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum']) )
                {
                    unset($_SESSION['checksum']);
                }
                else
                {
                    hesk_process_messages($hesklang['sec_wrng'],'ticket.php?remind=1');
                }
            }
        }
    }

	$email = hesk_emailCleanup( hesk_validateEmail( hesk_POST('email'), 'ERR' ,0) ) or hesk_process_messages($hesklang['enter_valid_email'],'ticket.php?remind=1');

	if ( isset($_POST['open_only']) )
	{
    	$hesk_settings['open_only'] = $_POST['open_only'] == 1 ? 1 : 0;
	}

	/* Get ticket(s) from database */
	hesk_load_database_functions();
	hesk_dbConnect();

    // Get tickets from the database
	$res = hesk_dbQuery('SELECT DISTINCT `tickets`.`trackid`, `tickets`.`subject`, `tickets`.`status`, `tickets`.`lastchange`, `customers`.`name`
        FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` AS `tickets` FORCE KEY (`statuses`) 
	    INNER JOIN `'.hesk_dbEscape($hesk_settings['db_pfix']).'ticket_to_customer` AS `ticket_to_customer`
	        ON `tickets`.`id` = `ticket_to_customer`.`ticket_id`
	    INNER JOIN `'.hesk_dbEscape($hesk_settings['db_pfix']).'customers` AS `customers`
	        ON `ticket_to_customer`.`customer_id` = `customers`.`id`
	    WHERE ' . ($hesk_settings['open_only'] ? "`status` <> '3' AND " : '') . ' ' . hesk_dbFormatEmail($email) . ' 
	    ORDER BY `tickets`.`status` ASC, `tickets`.`lastchange` DESC ');

	$num = hesk_dbNumRows($res);
	if ($num < 1)
	{
		if ($hesk_settings['open_only'])
        {
            hesk_process_messages($hesklang['noopen'],'ticket.php?remind=1&e='.rawurlencode($email).(hesk_POST('forgot') ? '&forgot=1#forgot-modal' : ''));
        }
        else
        {
            hesk_process_messages($hesklang['tid_not_found'],'ticket.php?remind=1&e='.rawurlencode($email).(hesk_POST('forgot') ? '&forgot=1#forgot-modal' : ''));
        }
	}

	$tid_list = '';
    $tid_list_html = '';
	$name = '';

    $email_param = $hesk_settings['email_view_ticket'] ? '&e='.rawurlencode($email) : '';

	while ($my_ticket=hesk_dbFetchAssoc($res))
	{
		$name = $name ? $name : hesk_msgToPlain($my_ticket['name'], 1, 0);
        $tid_list .= "
        $hesklang[trackID]: "	. $my_ticket['trackid'] . "
        $hesklang[subject]: "	. hesk_msgToPlain($my_ticket['subject'], 1, 0) . "
        $hesklang[status]: "	. hesk_get_status_name($my_ticket['status']) . "
        $hesk_settings[hesk_url]/ticket.php?track={$my_ticket['trackid']}{$email_param} " . "
        ";

        $tid_list_html .= "
        $hesklang[trackID]: "   . $my_ticket['trackid'] . "
        $hesklang[subject]: "   . hesk_msgToPlain($my_ticket['subject'], 1, 0) . "
        $hesklang[status]: "    . hesk_get_status_name($my_ticket['status']) . "
        <a href=\"$hesk_settings[hesk_url]/ticket.php?track={$my_ticket['trackid']}" . str_replace('&e=', '&amp;e=', $email_param) . "\">{$hesklang['view_ticket']}</a>
        ";
	}

	/* Get e-mail message for customer */
	list($msg, $html_msg) = hesk_getEmailMessage('forgot_ticket_id','',0,0,1);
    list($msg, $html_msg) = hesk_replace_email_tag('%%NAME%%', $name, $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%NUM%%', $num, $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%LIST_TICKETS%%', $tid_list, $msg, $html_msg, true, $tid_list_html);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_TITLE%%',	hesk_msgToPlain($hesk_settings['site_title'], 1),	$msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%SITE_URL%%', $hesk_settings['site_url'], $msg, $html_msg);
    list($msg, $html_msg) = hesk_replace_email_tag('%%FIRST_NAME%%', hesk_full_name_to_first_name($name), $msg, $html_msg);

    $subject = hesk_getEmailSubject('forgot_ticket_id');

	/* Send e-mail */
	hesk_mail($email, [], $subject, $msg, $html_msg);

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
