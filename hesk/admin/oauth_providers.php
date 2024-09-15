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

// Get all the req files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');
require(HESK_PATH . 'inc/oauth_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_man_settings');

// Load statuses
require_once(HESK_PATH . 'inc/statuses.inc.php');

$help_folder = '../language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/help_files/';

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
	if ($action == 'edit_provider') {edit_provider();}
	elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'oauth_providers.php', 'NOTICE');}
	elseif ($action == 'new_provider') {new_provider();}
	elseif ($action == 'save_provider') {save_provider();}
	elseif ($action == 'remove_provider') {remove_provider();}
    elseif ($action == 'verify_provider') {verify_provider();}
} elseif (hesk_GET('state') !== '') {
    //-- OAuth response
    $provider_id = intval(str_replace('provider', '', hesk_GET('state')));

    //-- Get provider data and confirm the provider actually exists
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` WHERE `id`={$provider_id}");

    if (hesk_dbNumRows($res) != 1) {
        hesk_process_messages($hesklang['oauth_provider_not_found'], './oauth_providers.php');
        exit();
    }

    $provider = hesk_dbFetchAssoc($res);

    //-- Mark the provider as valid and grab the initial token
    hesk_oauth_fetch_and_store_initial_token($provider, hesk_GET('code'));
}

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION('edit_provider') && !hesk_SESSION(array('new_provider','errors'))) {
    hesk_handle_messages();
}

$oauth_providers_rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers`");
?>
<div class="main__content tools">
    <section class="tools__between-head">
        <h2>
            <?php echo $hesklang['email_oauth_providers']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['email_oauth_providers_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <?php if ($action !== 'edit_provider'): ?>
        <div class="btn btn--blue-border" ripple="ripple" data-action="create-custom-status">
            <?php echo $hesklang['email_oauth_new_provider']; ?>
        </div>
        <?php endif; ?>
    </section>
    <div class="table-wrapper status">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['email_oauth_provider_name']; ?></th>
                    <th><?php echo $hesklang['email_oauth_provider_being_used_for']; ?></th>
                    <th><?php echo $hesklang['oauth_provider_verified']; ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (hesk_dbNumRows($oauth_providers_rs) === 0): ?>
                <tr>
                    <td colspan="4"><?php echo $hesklang['email_oauth_providers_none']; ?></td>
                </tr>
                <?php endif; ?>
                <?php while ($row = hesk_dbFetchAssoc($oauth_providers_rs)):
                    $in_use = getProviderInUseArray($row['id']);
                    if (count($in_use)) {
                        $uses = implode(', ', $in_use);
                    } else {
                        $uses = $hesklang['none'];
                    }
                    ?>
                <tr>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $uses; ?></td>
                    <td>
                        <?php if ($row['verified']):
                            echo $hesklang['yes'];
                        else:
                            echo $hesklang['no']; ?>
                            <a href="oauth_providers.php?a=verify_provider&id=<?php echo $row['id'] ?>&token=<?php hesk_token_echo(); ?>" class="link">
                                (<?php echo $hesklang['oauth_provider_click_to_verify']; ?>)
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="nowrap buttons">
                        <?php $modal_id = hesk_generate_delete_modal($hesklang['confirm_deletion'],
                            $hesklang['email_oauth_confirm_delete_provider'],
                            'oauth_providers.php?a=remove_provider&amp;id='. $row['id'] .'&amp;token='. hesk_token_echo(0)); ?>
                        <p>
                            <a href="oauth_providers.php?a=edit_provider&amp;id=<?php echo $row['id']; ?>" class="edit tooltip" title="<?php echo $hesklang['edit']; ?>">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>
                            <?php if ($uses !== $hesklang['none']): ?>
                                <a onclick="alert('<?php echo hesk_makeJsString($hesklang['email_oauth_provider_cannot_be_deleted']); ?>');"
                                   class="delete tooltip not-allowed"
                                   title="<?php echo $hesklang['email_oauth_provider_cannot_be_deleted']; ?>">
                                    <svg class="icon icon-delete">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <a class="delete tooltip" title="<?php echo $hesklang['delete']; ?>" href="javascript:" data-modal="[data-modal-id='<?php echo $modal_id; ?>']">
                                    <svg class="icon icon-delete">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                    </svg>
                                </a>
                            <?php
                            endif;
                            ?>
                        </p>
                    </td>
                </tr>
                <?php endwhile;?>
                </tbody>
            </table>
            <?php hesk_show_notice(sprintf($hesklang['email_oauth_provider_guide'], 'https://www.hesk.com/knowledgebase/?article=111'), ' ', false); ?>
        </div>
    </div>
</div>
<div class="right-bar create-status" <?php echo hesk_SESSION('edit_provider') || hesk_SESSION(array('new_provider','errors')) ? 'style="display: block"' : ''; ?>>
    <form action="oauth_providers.php" method="post" name="form1" class="form <?php echo hesk_SESSION(array('new_provider','errors')) ? 'invalid' : ''; ?>" autocomplete="off">
        <div class="right-bar__body form">
            <h3>
                <a href="<?php echo hesk_SESSION('edit_provider') ? 'oauth_providers.php' : 'javascript:'; ?>">
                    <svg class="icon icon-back">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                    </svg>
                    <span><?php echo hesk_SESSION('edit_provider') ? $hesklang['email_oauth_edit_provider'] : $hesklang['email_oauth_new_provider']; ?></span>
                </a>
            </h3>
            <?php
            /* This will handle error, success and notice messages */
            if (hesk_SESSION(array('new_provider', 'errors'))) {
                echo '<div style="margin: -24px -24px 10px -16px;">';
                hesk_handle_messages();
                echo '</div>';
            }

            $provider_name = hesk_SESSION(array('new_provider','name'));
            $authorization_url = hesk_SESSION(array('new_provider','authorization_url'));
            $token_url = hesk_SESSION(array('new_provider','token_url'));
            $client_id = hesk_SESSION(array('new_provider','client_id'));
            $client_secret = hesk_SESSION(array('new_provider','client_secret'));
            $scope = hesk_SESSION(array('new_provider','scope'));
            $no_val_ssl = hesk_SESSION(array('new_provider','no_val_ssl'));
            $errors = hesk_SESSION(array('new_provider','errors'));
            $errors = is_array($errors) ? $errors : array();

            if ( ! hesk_SESSION('edit_provider') && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
                $oauth_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                $oauth_link = hesk_clean_utf8($oauth_link);
                ?>
                <p><?php echo sprintf($hesklang['email_oauth_provider_uri'], '<a href="oauth_providers.php">' . hesk_htmlspecialchars($oauth_link) . '</a>'); ?></p>
                <p>&nbsp;</p>
                <?php
            }
            ?>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_provider_name']; ?></label>
                <input type="text" class="form-control <?php echo in_array('name', $errors) ? 'isError' : ''; ?>" name="name"
                       value="<?php echo $provider_name; ?>" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_auth_url']; ?></label>
                <input type="text" class="form-control <?php echo in_array('authorization_url', $errors) ? 'isError' : ''; ?>" name="authorization_url"
                       value="<?php echo $authorization_url; ?>" placeholder="https://">
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_token_url']; ?></label>
                <input type="text" class="form-control <?php echo in_array('token_url', $errors) ? 'isError' : ''; ?>" name="token_url"
                       value="<?php echo $token_url; ?>" placeholder="https://">
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_client_id']; ?></label>
                <input type="text" class="form-control <?php echo in_array('client_id', $errors) ? 'isError' : ''; ?>" name="client_id"
                       value="<?php echo $client_id; ?>">
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_client_secret']; ?></label>
                <input type="text" class="form-control <?php echo in_array('client_secret', $errors) ? 'isError' : ''; ?>" name="client_secret"
                       value="<?php echo $client_secret; ?>">
            </div>
            <div class="form-group">
                <label><?php echo $hesklang['email_oauth_scope']; ?></label>
                <input type="text" class="form-control <?php echo in_array('scope', $errors) ? 'isError' : ''; ?>" name="scope"
                       value="<?php echo $scope; ?>">
            </div>
            <div id="form-group">
                <div class="checkbox-custom">
                    <input type="checkbox" id="no_val_ssl" name="no_val_ssl" value="1" <?php if ($no_val_ssl) {echo 'checked';} ?>>
                    <label for="no_val_ssl"><?php echo $hesklang['noval_cert']; ?></label>
                    <a onclick="hesk_window('<?php echo $help_folder; ?>email.html#68','400','500')">
                        <div class="tooltype right" style="margin-left: 8px;">
                            <svg class="icon icon-info">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
            <?php if (isset($_SESSION['edit_provider'])): ?>
                <input type="hidden" name="a" value="save_provider">
                <input type="hidden" name="id" value="<?php echo intval($_SESSION['new_provider']['id']); ?>">
            <?php else: ?>
                <input type="hidden" name="a" value="new_provider">
            <?php endif; ?>
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
            <a href="oauth_providers.php" class="btn btn-border save" style=""><?php echo $hesklang['cancel']; ?></a>
            <button type="submit" class="btn btn-full save" ripple="ripple"><?php echo $hesklang['status_save']; ?></button>
            </div>
        </div>
    </form>
</div>
<?php

hesk_cleanSessionVars( array('new_provider', 'edit_provider') );

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function save_provider()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

	// Get custom status ID
	$id = intval( hesk_POST('id') ) or hesk_error($hesklang['status_e_id']);

	// Validate inputs
	if (($provider = provider_validate()) == false)
	{
		$_SESSION['edit_provider'] = true;
		$_SESSION['new_provider']['id'] = $id;

		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'oauth_providers.php');
	}

	// Save the provider
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` SET
	`name` = '".hesk_dbEscape($provider['name'])."',
	`authorization_url` = '".hesk_dbEscape($provider['authorization_url'])."',
	`token_url` = '".hesk_dbEscape($provider['token_url'])."',
	`client_id` = '".hesk_dbEscape($provider['client_id'])."',
	`client_secret` = '".hesk_dbEscape($provider['client_secret'])."',
	`scope` = '".hesk_dbEscape($provider['scope'])."',
	`no_val_ssl` = ".intval($provider['no_val_ssl']).",
    `verified` = 0
	WHERE `id`={$id}");

    // Redirect to OAuth provider for verification
    redirect_to_provider($provider, $id);

    /* TODO: only verify if needed?
    // If something changed in the DB, redirect to OAuth provider for verification
    if (hesk_dbAffectedRows() > 0) {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` SET `verified` = 0 WHERE `id`={$id}");
        redirect_to_provider($provider, $id);
    }

    // No changes to the DB
    hesk_process_messages($hesklang['oauth_provider_saved'], 'NOREDIRECT', 'SUCCESS');
    */

} // End save_provider()


function edit_provider()
{
	global $hesk_settings, $hesklang;

	// Get custom status ID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['status_e_id']);

	// Get details from the database
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` WHERE `id`={$id} LIMIT 1");
	if ( hesk_dbNumRows($res) != 1 )
	{
		hesk_error($hesklang['status_not_found']);
	}
	$provider = hesk_dbFetchAssoc($res);

    if (defined('HESK_DEMO')) {
        $provider['authorization_url'] = 'https://api.example.com/oauth2/authorization';
        $provider['token_url'] = 'https://api.example.com/oauth2/token';
        $provider['client_id'] = $hesklang['hdemo'];
        $provider['client_secret'] = $hesklang['hdemo'];
    }

	$_SESSION['new_provider'] = $provider;
	$_SESSION['edit_provider'] = true;

} // End edit_provider()


function remove_provider()
{
	global $hesk_settings, $hesklang;

	// A security check
	hesk_token_check();

	// Get ID
	$id = intval( hesk_GET('id') ) or hesk_error($hesklang['status_e_id']);

    // Provider being used?
    if (count(getProviderInUseArray($id))) {
        hesk_process_messages($hesklang['email_oauth_provider_cannot_be_deleted'], './oauth_providers.php');
        return;
    }

	// Delete the provider
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_tokens` WHERE `provider_id`={$id}");
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` WHERE `id`={$id}");

	// Were we successful?
	if ( hesk_dbAffectedRows() == 1 )
	{
		// Show success message
		hesk_process_messages($hesklang['oauth_provider_deleted'],'./oauth_providers.php','SUCCESS');
	}
	else
	{
		hesk_process_messages($hesklang['status_not_found'],'./oauth_providers.php');
	}

} // End remove_provider()


function provider_validate()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	$hesk_error_buffer = array();
    $provider = array();
    $errors = array();

	// Get name
	$provider['name'] = hesk_input(hesk_POST('name'));
    if (strlen($provider['name']) < 1) {
        $errors[] = 'name';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_name'];
    }

    // Auth URL
    $provider['authorization_url'] = hesk_validateURL(hesk_POST('authorization_url'));
    if (strlen($provider['authorization_url']) < 1) {
        $errors[] = 'authorization_url';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_auth_url'];
    }

    // Token URL
    $provider['token_url'] = hesk_validateURL(hesk_POST('token_url'));
    if (strlen($provider['token_url']) < 1) {
        $errors[] = 'token_url';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_token_url'];
    }

    // Client ID
    $provider['client_id'] = hesk_input(hesk_POST('client_id'), 0, 0, HESK_SLASH);
    if (strlen($provider['client_id']) < 1) {
        $errors[] = 'client_id';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_client_id'];
    }

    // Client Secret
    $provider['client_secret'] = hesk_input(hesk_POST('client_secret'), 0, 0, HESK_SLASH);
    if (strlen($provider['client_secret']) < 1) {
        $errors[] = 'client_secret';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_client_secret'];
    }

    // Scope
    $provider['scope'] = hesk_input(hesk_POST('scope'), 0, 0, HESK_SLASH);
    if (strlen($provider['scope']) < 1) {
        $errors[] = 'scope';
        $hesk_error_buffer[] = $hesklang['oauth_provider_err_scope'];
    }

    // Skip SSL certificate verification?
    $provider['no_val_ssl'] = (hesk_POST('no_val_ssl', 0) == 1) ? 1 : 0;

	// Any errors?
	if (count($hesk_error_buffer))
	{
        foreach ($provider as $k => $v) {
            $provider[$k] = stripslashes($v);
        }

		$_SESSION['new_provider'] = $provider;
		$_SESSION['new_provider']['errors'] = $errors;
		return false;
	}

	return $provider;
} // END provider_validate()


function new_provider()
{
	global $hesk_settings, $hesklang;
	global $hesk_error_buffer;

	// A security check
	# hesk_token_check('POST');

	// Validate inputs
	if (($provider = provider_validate()) == false)
	{
		$tmp = '';
		foreach ($hesk_error_buffer as $error)
		{
			$tmp .= "<li>$error</li>\n";
		}
		$hesk_error_buffer = $tmp;

		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'oauth_providers.php');
	}

	// Insert provider into database
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` (`name`, `authorization_url`, `token_url`, `client_id`, `client_secret`, `scope`, `no_val_ssl`, `verified`)
        VALUES ('".hesk_dbEscape($provider['name'])."',
                '".hesk_dbEscape($provider['authorization_url'])."',
                '".hesk_dbEscape($provider['token_url'])."',
                '".hesk_dbEscape($provider['client_id'])."',
                '".hesk_dbEscape($provider['client_secret'])."',
                '".hesk_dbEscape($provider['scope'])."',
                ".intval($provider['no_val_ssl']).",
                0)");

    $inserted_id = hesk_dbInsertID();
    $_SESSION['providerord'] = $inserted_id;

    //-- Send user to OAuth provider
    redirect_to_provider($provider, $inserted_id);
} // End new_provider()

function redirect_to_provider($provider, $id) {
    $redirect_url = hesk_get_oauth_redirect_url();
    $return_location = $provider['authorization_url'] .
        "?client_id={$provider['client_id']}" .
        "&response_type=code" .
        "&redirect_uri={$redirect_url}" .
        "&response_mode=query" .
        "&access_type=offline" .
        "&scope={$provider['scope']}" .
        "&state=provider{$id}";

    header('Location: '.$return_location);
    exit();
}

function verify_provider() {
    global $hesk_settings, $hesklang;

    // Get ID
    $id = intval( hesk_GET('id') ) or hesk_error($hesklang['status_e_id']);

    $rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."oauth_providers` WHERE `id`=".$id);

    if (hesk_dbNumRows($rs) === 0) {
        hesk_process_messages($hesklang['oauth_provider_not_found'], './oauth_providers.php');
    }
    $row = hesk_dbFetchAssoc($rs);

    redirect_to_provider($row, $row['id']);
}

function getProviderInUseArray($id) {
    global $hesk_settings, $hesklang;

    $in_use = array();

    if ($hesk_settings['smtp'] && $hesk_settings['smtp_conn_type'] == 'oauth' && $hesk_settings['smtp_oauth_provider'] == $id) {
        $in_use[] = $hesklang['email_sending'];
    }

    if ($hesk_settings['imap'] && $hesk_settings['imap_conn_type'] == 'oauth' && $hesk_settings['imap_oauth_provider'] == $id) {
        $in_use[] = $hesklang['imap'];
    }

    if ($hesk_settings['pop3'] && $hesk_settings['pop3_conn_type'] == 'oauth' && $hesk_settings['pop3_oauth_provider'] == $id) {
        $in_use[] = $hesklang['pop3'];
    }

    return $in_use;
}

