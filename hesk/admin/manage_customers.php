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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/privacy_functions.inc.php');
require(HESK_PATH . 'inc/manage_customers_functions.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
$can_man_customers = hesk_checkPermission('can_man_customers', false);
$can_edit_tickets = hesk_checkPermission('can_edit_tickets', false);
$can_view_customers = hesk_checkPermission('can_view_customers', false);
if ($can_man_customers || (!$hesk_settings['customer_accounts'] && $can_edit_tickets && ! empty(hesk_REQUEST('a')))) {
    $elevation_target = !isset($_GET['track']) ?
        'manage_customers.php' :
        'manage_customers.php?a=edit&track='.hesk_cleanID().'&id='.hesk_GET('id');
    hesk_check_user_elevation($elevation_target);
} else {
    hesk_checkPermission('can_view_customers');
}

/* Set default values */
$default_userdata = array(
    'name' => '',
    'email' => '',
    'cleanpass' => ''
);

/* Use any set values, default otherwise */
foreach ($default_userdata as $k => $v)
{
    if ( ! isset($_SESSION['userdata'][$k]) )
    {
        $_SESSION['userdata'][$k] = $v;
    }
}

$_SESSION['userdata'] = hesk_stripArray($_SESSION['userdata']);

/* What should we do? */
if ( $action = hesk_REQUEST('a') )
{
    if ($hesk_settings['customer_accounts']) {
        // Check permission again - required manage users permission for all actions
        hesk_checkPermission('can_man_customers');

        if ($action == 'reset_form')
        {
            $_SESSION['edit_userdata'] = TRUE;
            header('Location: ./manage_customers.php');
        }
        elseif ($action == 'edit')       {edit_user();}
        elseif ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_customers.php', 'NOTICE');}
        elseif ($action == 'new')        {new_user();}
        elseif ($action == 'save')       {update_user();}
        elseif ($action == 'remove')     {remove();}
        elseif ($action == 'resetmfa')   {reset_mfa();}
        elseif ($action === 'approve')   {approve_registration();}
        elseif ($action === 'reject')    {reject_registration();}
        elseif ($action === 'delete')    {delete_registration();}
        elseif ($action === 'bulk')      {handle_bulk_action();}
        elseif ($action === 'resend_verification_email') {resend_verification_email();}
        else 							 {hesk_error($hesklang['invalid_action']);}
    } else {
        // When customer accounts disabled, we can only edit customers here
        if ( ! $can_man_customers) {
            hesk_checkPermission('can_edit_tickets');
        }
        if ($action === 'edit') {edit_user();}
        elseif ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_customers.php', 'NOTICE');}
        elseif ($action == 'save')       {update_user();}
        elseif ($action == 'remove')     {remove();}
        else 							 {hesk_error($hesklang['invalid_action']);}
    }
} else {
    /* If one came from the Edit page make sure we reset user values */
    if (isset($_SESSION['save_userdata']))
    {
        $_SESSION['userdata'] = $default_userdata;
        $_SESSION['save_customer_search'] = true;
        unset($_SESSION['save_userdata']);
    }
    if (isset($_SESSION['edit_userdata']))
    {
        $_SESSION['save_customer_search'] = true;
        $_SESSION['userdata'] = $default_userdata;
        unset($_SESSION['edit_userdata']);
    }

    // Clear the saved search unless we're told to keep it
    if (!isset($_SESSION['save_customer_search'])) {
        unset($_SESSION['saved_customer_search']);
    } else {
        unset($_SESSION['save_customer_search']);
    }
    $saved_search = hesk_SESSION_array('saved_customer_search');

    /* Print header */
    require_once(HESK_PATH . 'inc/header.inc.php');

    /* Print main manage users page */
    require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

    /* This will handle error, success and notice messages */
    if (!hesk_SESSION(array('userdata', 'errors'))) {
        hesk_handle_messages();
    }

    // If POP3 fetching is active, no customer should have the same email address
    if ($hesk_settings['pop3'] && hesk_validateEmail($hesk_settings['pop3_user'], 'ERR', 0))
    {
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['pop3_user'])."'");

        if ($myuser = hesk_dbFetchAssoc($res))
        {
            hesk_show_notice(sprintf($hesklang['pop3_warning'], $myuser['name'], $hesk_settings['pop3_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
        }
    }

    // If IMAP fetching is active, no user should have the same email address
    if ($hesk_settings['imap'] && hesk_validateEmail($hesk_settings['imap_user'], 'ERR', 0))
    {
        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['imap_user'])."'");

        if ($myuser = hesk_dbFetchAssoc($res))
        {
            hesk_show_notice(sprintf($hesklang['imap_warning'], $myuser['name'], $hesk_settings['imap_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
        }
    }

    $approval_res = hesk_dbQuery("SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `verified` = 2");
    $pending_approval_count = hesk_dbNumRows($approval_res);
    if($pending_approval_count > 0) {
        hesk_show_notice(sprintf($hesklang['customer_manage_approvals'], $pending_approval_count));
    }
?>
<div class="main__content team">
    <section class="team__head">
        <h2>
            <?php echo $hesklang['customers']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['customers_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <?php if ($hesk_settings['customer_accounts'] && $can_man_customers): ?>
        <div class="buttons">
            <button class="btn btn btn--blue-border" ripple="ripple" data-action="team-create"><?php echo $hesklang['new_customer']; ?></button>
            <a href="import_customers.php" class="btn btn btn--blue-border" ripple="ripple"><?php echo $hesklang['import_customers']; ?></a>
        </div>
        <?php endif; ?>
    </section>
    <?php
    $search_name = isset($saved_search['search_name']) ? $saved_search['search_name'] : hesk_REQUEST('search_name');
    $url_name = urlencode($search_name);
    $search_email = isset($saved_search['search_email']) ? $saved_search['search_email'] : hesk_REQUEST('search_email');
    $url_email = urlencode($search_email);
    $search_pagesize = isset($saved_search['search_pagesize']) ? intval($saved_search['search_pagesize']) : intval(hesk_REQUEST('search_pagesize', 20));
    $search_pagenumber = isset($saved_search['search_pagenumber']) ? intval($saved_search['search_pagenumber']) : intval(hesk_REQUEST('search_pagenumber', 1));
    $search_sort_column = isset($saved_search['search_sort_column']) ? $saved_search['search_sort_column'] : hesk_REQUEST('search_sort_column', 'id');
    $url_sort_column = urlencode($search_sort_column);
    $search_sort_direction = isset($saved_search['search_sort_direction']) ? $saved_search['search_sort_direction'] : hesk_REQUEST('search_sort_direction', 'DESC');
    $url_sort_direction = urlencode($search_sort_direction);
    $query_url = "manage_customers.php?search_name={$url_name}&search_email={$url_email}&search_pagesize={$search_pagesize}&search_sort_column={$url_sort_column}&search_sort_direction={$url_sort_direction}";
    $sort_query_url = $query_url . "&search_pagenumber={$search_pagenumber}";
    ?>
    <form action="manage_customers.php" method="get" name="form1">
        <div class="table-wrap customers__search">
            <?php
                // Do we have any customers in the database?
                $res = hesk_dbQuery("SELECT EXISTS (SELECT 1 FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`)");
                if( ! hesk_dbResult($res)) {
                    hesk_show_notice(
                        $hesklang['no_customers'] . '<br><br>' .
                        (
                            $hesk_settings['customer_accounts'] ?
                            $hesklang['no_customers_enabled'] . ($can_man_customers ? '<br><br>' . $hesklang['no_customers_enabled2'] : '') :
                            $hesklang['no_customers_disabled']
                        ), ' ', false
                    );
                }
            ?>
            <h3><?php echo $hesklang['search_customers']; ?></h3>
            <div class="customers__search_form form">
                <div class="filters">
                    <div class="form-group">
                        <label for="search_name">
                            <?php echo $hesklang['name']; ?>:
                        </label>
                        <input type="text" id="search_name"
                               value="<?php echo stripslashes(hesk_input($search_name)); ?>"
                               name="search_name"
                               class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="search_email">
                            <?php echo $hesklang['email']; ?>:
                        </label>
                        <input type="text" id="search_email"
                               value="<?php echo stripslashes(hesk_input($search_email)); ?>"
                               name="search_email"
                               class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="search_pagesize">
                            <?php echo $hesklang['page_size']; ?>:
                        </label>
                        <?php
                        $pagesizes = [10, 25, 50, 100, 250, 500];
                        ?>
                        <select id="search_pagesize" name="search_pagesize">
                            <?php foreach ($pagesizes as $pagesize): ?>
                            <option value="<?php echo $pagesize; ?>" <?php echo $pagesize === $search_pagesize ? 'selected' : '' ?>>
                                <?php echo $pagesize; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <script>$('#search_pagesize').selectize();</script>
                    </div>
                </div>
                <button type="submit" class="btn btn-full"><?php echo $hesklang['search']; ?></button>
            </div>
        </div>
        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
    </form>
    <?php if ($can_man_customers || $can_view_customers): ?>
    <?php
    $offset = ($search_pagenumber - 1) * $search_pagesize;

    $where_clause = 'WHERE 1=1 ';
    if ($search_name) {
        $where_clause .= "AND `hc`.`name` LIKE '%".hesk_dbEscape($search_name)."%' ";
    }
    if ($search_email) {
        $where_clause .= "AND `hc`.`email` LIKE '%".hesk_dbEscape($search_email)."%'";
    }
    $count_res = hesk_dbQuery("SELECT COUNT(1) AS `cnt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `hc`
                    {$where_clause}");
    $total_count = 0;
    if ($row = hesk_dbFetchAssoc($count_res)) {
        $_SESSION['saved_customer_search'] = [
            'search_name' => $search_name,
            'search_email' => $search_email,
            'search_pagesize' => $search_pagesize,
            'search_pagenumber' => $search_pagenumber,
            'search_sort_column' => $search_sort_column,
            'search_sort_direction' => $search_sort_direction
        ];
        $total_count = intval($row['cnt']);
    }

    if (!in_array($search_sort_column, ['id', 'name', 'email', 'tickets'])) {
        $search_sort_column = 'name';
    }
    $sql_sort = 'DESC';
    if ($search_sort_direction !== '') {
        $sql_sort = $search_sort_direction === 'ASC' ? 'ASC' : 'DESC';
    }
    $res = hesk_dbQuery("SELECT `hc`.*, COUNT(CASE WHEN htc.customer_type = 'REQUESTER' THEN 1 END) AS `tickets`, COUNT(CASE WHEN htc.customer_type = 'FOLLOWER' THEN 1 END) AS `following`
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `hc`
                    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` AS `htc` ON `hc`.`id` = `htc`.`customer_id`
                    {$where_clause}
                    GROUP BY `hc`.`id`, `hc`.`name`, `hc`.`email`
                    ORDER BY CASE WHEN `hc`.`verified` = 2 THEN 0 ELSE 1 END ASC, `{$search_sort_column}` {$search_sort_direction}
                    LIMIT {$search_pagesize} OFFSET {$offset}");
    $customers = [];
    while ($customer = hesk_dbFetchAssoc($res)) {
        $customers[$customer['id']] = $customer;
    }
    $customer_ids = count($customers) > 0 ?
        array_map(function($customer) { return intval($customer['id']); }, $customers) :
        [-1];

    $delete_modal_ids = [];
    foreach ($customers as $customer) {
        if ($can_man_customers) {
            $modal_body = $hesklang['sure_remove_customer']."<br>".$hesklang['sure_remove_customer_additional_note']."<br>";

            // What to do with tickets opened by this customer?
            if (isset($customer['tickets']) && $customer['tickets'] > 0) {
                $modal_body .= '<br><div class="notification orange" style="margin-bottom: 5px">';
                $modal_body .= sprintf($hesklang['deleting_customer_tickets'], $customer['tickets']);
                $modal_body .= '</div>';
                $modal_body .= '
                                <div class="radio-center">
                                    <div class="radio-list">
                                        <div class="radio-custom">
                                            <input type="radio" id="delete-method-retain-'.$customer['id'].'" name="delete-method" value="retain" checked>
                                            <label for="delete-method-retain-'.$customer['id'].'">
                                                <strong>'. $hesklang['deleting_customer_retain_tickets2'] .'</strong><br>
                                            </label>
                                        </div>
                                        <div class="radio-custom">
                                            <input type="radio" id="delete-method-anonymize-'.$customer['id'].'" name="delete-method" value="anonymize">
                                            <label for="delete-method-anonymize-'.$customer['id'].'">
                                                <strong>'. $hesklang['deleting_customer_anonymize_tickets2'] .'</strong><br>
                                            </label>
                                        </div>
                                        <div class="radio-custom">
                                            <input type="radio" id="delete-method-delete-'.$customer['id'].'" name="delete-method" value="delete">
                                            <label for="delete-method-delete-'.$customer['id'].'">
                                                <strong>'. $hesklang['deleting_customer_delete_tickets2'] .'</strong><br>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                ';
            }

            // Tickets folowed by this customer:
            if (isset($customer['following']) && $customer['following'] > 0) {
                $modal_body .= '<br><div class="notification blue" style="margin-bottom: 5px">';
                $modal_body .= sprintf($hesklang['deleting_customer_follower'], $customer['following']);
                $modal_body .= '</div>';
            }

            $modal_body .= '<input type="hidden" name="a" value="remove">
                            <input type="hidden" name="id" value="'.$customer['id'].'">
                            <input type="hidden" name="token" value="'.hesk_token_echo(0).'">';

            $modal_id = hesk_generate_delete_modal([
                'title' => $hesklang['confirm_deletion'],
                'body' => $modal_body,
                'confirm_action' => 'manage_customers.php',
                'use_form' => true,
                'form_method' => 'GET'
            ]);
            $delete_modal_ids[$customer['id']] = $modal_id;
        }
    }
    ?>
    <form action="manage_customers.php" method="post" name="customersTable">
        <input type="hidden" name="a" value="bulk">
        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
        <section class="team__head bulk-actions" id="bulk-buttons" style="display: none">
            <div class="buttons">
                <button class="btn btn--blue-border" type="submit" name="bulk_approve"><?php echo $hesklang['customer_manage_bulk_approve']; ?></button>
                <button class="btn btn--blue-border" type="submit" name="bulk_reject"><?php echo $hesklang['customer_manage_bulk_reject']; ?></button>
                <button class="btn btn--blue-border" type="submit" name="bulk_delete"><?php echo $hesklang['customer_manage_bulk_delete']; ?></button>
            </div>
        </section>
        <?php endif; ?>
        <div class="table-wrap">
            <div class="table">
                <table id="default-table" class="table sindu-table">
                    <thead>
                    <tr>
                        <?php if ($pending_approval_count > 0 && $can_man_customers): ?>
                            <th>
                                <div class="checkbox-custom">
                                    <input type="checkbox" id="customer_checkall" onclick="toggleCheckboxes()">
                                    <label for="customer_checkall">&nbsp;</label>
                                </div>
                            </th>
                        <?php endif; ?>
                        <th class="sindu-handle <?php echo $search_sort_column === 'id' ? hesk_mb_strtolower($search_sort_direction) : '' ?>">
                            <a href="<?php echo build_sort_url($sort_query_url, $url_sort_column, 'id', $search_sort_direction); ?>">
                                <div class="sort">
                                    <span><?php echo $hesklang['id']; ?></span>
                                    <i class="handle"></i>
                                </div>
                            </a>

                        </th>
                        <th class="sindu-handle <?php echo $search_sort_column === 'name' ? hesk_mb_strtolower($search_sort_direction) : '' ?>">
                            <a href="<?php echo build_sort_url($sort_query_url, $url_sort_column, 'name', $search_sort_direction); ?>">
                                <div class="sort">
                                    <span><?php echo $hesklang['name']; ?></span>
                                    <i class="handle"></i>
                                </div>
                            </a>
                        </th>
                        <th class="sindu-handle <?php echo $search_sort_column === 'email' ? hesk_mb_strtolower($search_sort_direction) : '' ?>">
                            <a href="<?php echo build_sort_url($sort_query_url, $url_sort_column, 'email', $search_sort_direction); ?>">
                                <div class="sort">
                                    <span><?php echo $hesklang['email']; ?></span>
                                    <i class="handle"></i>
                                </div>
                            </a>
                        </th>
                        <th class="sindu-handle <?php echo $search_sort_column === 'tickets' ? hesk_mb_strtolower($search_sort_direction) : '' ?>">
                            <a href="<?php echo build_sort_url($sort_query_url, $url_sort_column, 'tickets', $search_sort_direction); ?>">
                                <div class="sort">
                                    <span><?php echo $hesklang['not']; ?></span>
                                    <i class="handle"></i>
                                </div>
                            </a>
                        </th>
                        <th><?php echo $hesklang['mfa_short']; ?></th>
                        <?php if ($can_man_customers): ?>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($customers as $myuser) {
                        if (defined('HESK_DEMO')) {
                            $myuser['email'] = 'hidden@demo.com';
                        }

                        $table_row = '';
                        if (isset($_SESSION['seluser']) && is_array($_SESSION['seluser']) && in_array($myuser['id'], $_SESSION['seluser'])) {
                            $table_row = 'class="ticket-new"';
                            $index = array_search($myuser['id'], $_SESSION['seluser']);
                            unset($_SESSION['seluser'][$index]);
                        }

                        $checkbox_code = $pending_approval_count > 0 && $can_man_customers ? '<td></td>' : '';
                        if ($can_man_customers && intval($myuser['verified']) === 2) {
                            $table_row = 'class="pending-approval"';
                            $checkbox_code = '<td class="table__first_th sindu_handle"><div class="checkbox-custom">
                            <input type="checkbox" id="customer_check_'.$myuser['id'].'" name="id[]" value="'.$myuser['id'].'" class="customer-checkbox" onchange="updateBulkButtonState()">
                            <label for="customer_check_'.$myuser['id'].'">&nbsp;</label>
                        </div></td>';
                            $approval_code = '
                            <a href="manage_customers.php?a=approve&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" class="edit tooltip"
                                title="'.$hesklang['customer_manage_approve'].'">
                                <svg class="icon icon-tick">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-tick"></use>
                                </svg>
                            </a>
                            <a href="manage_customers.php?a=reject&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" class="edit tooltip" title="'.$hesklang['customer_manage_reject'].'">
                                <svg class="icon icon-cross">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-cross"></use>
                                </svg>
                            </a>
                            <a href="manage_customers.php?a=delete&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" class="edit tooltip" title="'.$hesklang['customer_manage_delete'].'">
                                <svg class="icon icon-cross">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-delete"></use>
                                </svg>
                            </a>';
                        } else {
                            $approval_code = '';
                        }

                        if ($can_man_customers && intval($myuser['verified']) !== 2) {
                            $edit_code = '
                            <a href="manage_customers.php?a=edit&amp;id='.$myuser['id'].'" class="edit tooltip" title="'.$hesklang['edit'].'">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>';
                        } else {
                            $edit_code = '';
                        }

                        if ($can_man_customers && intval($myuser['verified']) !== 2) {
                            $remove_code = '
                        <a href="javascript:" data-modal="[data-modal-id=\''.$delete_modal_ids[$myuser['id']].'\']"
                            title="'.$hesklang['remove'].'"
                            class="delete tooltip">
                            <svg class="icon icon-delete">
                                <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-delete"></use>
                            </svg>
                        </a>';
                        } else {
                            $remove_code = '';
                        }
                        if ($can_man_customers && intval($myuser['verified']) === 0 && $myuser['verification_token'] !== null) {
                            $resend_email_code = '
                        <a href="manage_customers.php?a=resend_verification_email&amp;id='.$myuser['id'].'"
                            title="'.$hesklang['customer_login_resend_verification_email'].'"
                            class="delete tooltip">
                            <svg class="icon icon-mail">
                                <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-mail"></use>
                            </svg>
                        </a>';
                        } else {
                            $resend_email_code = '';
                        }

                        echo <<<EOC
<tr $table_row>
$checkbox_code
<td>$myuser[id]</td>
<td>$myuser[name]</td>
<td><a href="mailto:$myuser[email]">$myuser[email]</a></td>
<td><a href="find_tickets.php?what=customer&amp;q={$myuser['id']}&amp;s_my=1&amp;s_ot=1&amp;s_un=1">$myuser[tickets]</a></td>

EOC;

                        $mfa_enrollment = intval($myuser['mfa_enrollment']);
                        $mfa_status = $hesklang['mfa_method_none'];
                        $mfa_reset = '';
                        $modal_id = hesk_generate_old_delete_modal($hesklang['mfa_reset_to_default'],
                            $hesklang['mfa_reset_confirm'],
                            'manage_customers.php?a=resetmfa&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0),
                            $hesklang['mfa_reset_yes']);

                        if ($mfa_enrollment === 1) {
                            $mfa_status = $hesklang['mfa_method_email'];

                            if (!$hesk_settings['require_mfa'] && $can_man_customers) {

                                $mfa_reset = '<div class="tooltype right out-close">
                                <a href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']"
                                    title="'.$hesklang['mfa_reset_to_default'].'"
                                    class="delete tooltip">
                                    <svg class="icon icon-refresh">
                                        <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-refresh"></use>
                                    </svg>
                                </a>
                            </div>';
                            }
                        } elseif ($mfa_enrollment === 2) {
                            $mfa_status = $hesklang['mfa_method_auth_app_short'];

                            if ($can_man_customers) {
                                $mfa_reset = '<div class="tooltype right out-close">
                                    <a href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']"
                                        title="'.$hesklang['mfa_reset_to_default'].'"
                                        class="delete tooltip">
                                        <svg class="icon icon-refresh">
                                            <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-refresh"></use>
                                        </svg>
                                    </a>
                                </div>';
                            }
                        }
                        $actions_html = $can_man_customers ? '<td class="nowrap buttons"><p>'.$approval_code.' '.$resend_email_code.' '.$edit_code.' '.$remove_code.'</p></td>' : '';
                        echo <<<EOC
<td>$mfa_status $mfa_reset</td>
$actions_html
</tr>

EOC;
                    } // End while
                    ?>
                    </tbody>
                </table>
                <?php
                $total_pages = intval($total_count / $search_pagesize);
                if ($total_count % $search_pagesize !== 0) {
                    $total_pages++;
                }
                hesk_output_pager($total_count, $total_pages, $search_pagenumber, $query_url, 'search_pagenumber');
                ?>
            </div>
        </div>
    </form>
</div>
<?php if ($can_man_customers): ?>
<div class="right-bar team-create customer" <?php echo hesk_SESSION(array('userdata','errors')) ? 'style="display: block"' : ''; ?>>
    <div class="right-bar__body form" data-step="1">
        <h3>
            <a href="manage_customers.php?a=reset_form">
                <svg class="icon icon-back">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                </svg>
                <span><?php echo $hesklang['add_user']; ?></span>
            </a>
        </h3>
        <?php
        if (hesk_SESSION(array('userdata', 'errors'))) {
            hesk_handle_messages();
        }
        ?>
        <form name="form1" method="post" action="manage_customers.php" class="form <?php echo hesk_SESSION(array('userdata','errors')) ? 'invalid' : ''; ?>">
            <?php hesk_customer_tab('userdata'); ?>

            <!-- Submit -->
            <div class="right-bar__footer">
                <input type="hidden" name="a" value="new">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['create_user']; ?></button>
            </div>
        </form>
    </div>
</div>
    <script>
        function toggleCheckboxes() {
            var d = document.customersTable;
            var setTo = !!document.getElementById('customer_checkall').checked;

            for (var i = 0; i < d.elements.length; i++)
            {
                if(d.elements[i].type === 'checkbox' && d.elements[i].name !== 'customer_checkall')
                {
                    d.elements[i].checked = setTo;
                }
            }
            updateBulkButtonState();
        }

        function updateBulkButtonState() {
            let atLeastOneCheckboxSelected = !!document.querySelectorAll('input[class="customer-checkbox"]:checked').length;
            document.getElementById('bulk-buttons').style.display = atLeastOneCheckboxSelected ? 'flex' : 'none';
        }
    </script>
<?php
endif;
unset($_SESSION['seluser']);

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

} // End else


/*** START FUNCTIONS ***/


function compare_user_permissions($compare_id, $compare_isadmin = null, $compare_categories = null, $compare_features = null)
{
	global $hesk_settings;

    /* Comparing myself? */
    if ($compare_id == $_SESSION['id'])
    {
    	return true;
    }

    /* Admins have full access, no need to compare */
	if ($_SESSION['isadmin'])
    {
    	return true;
    }
    elseif ($compare_isadmin)
    {
    	return false;
    }

    // Do we need to get data from the database?
    if ($compare_categories === null)
    {
        $res = hesk_dbQuery("SELECT `isadmin`, `categories`, `heskprivileges` AS `features` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($compare_id)."' LIMIT 1");
        $row = hesk_dbFetchAssoc($res);

        // If this user is an admin and we're not - no need to check further
        if ($row['isadmin'])
        {
            return false;
        }

        $compare_features = explode(',', $row['features']);
        $compare_categories = explode(',', $row['categories']);
    }

	/* Compare categories */
    foreach ($compare_categories as $catid)
    {
    	if ( ! array_key_exists($catid, $hesk_settings['categories']) )
        {
        	return false;
        }
    }

	/* Compare features */
    foreach ($compare_features as $feature)
    {
    	if ( ! in_array($feature, $hesk_settings['features']) )
        {
        	return false;
        }
    }

    return true;

} // END compare_user_permissions()


function edit_user()
{
	global $hesk_settings, $hesklang, $default_userdata;

	$id = intval( hesk_GET('id') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");
    $trackingID = hesk_cleanID();
    $return_url = $trackingID ? "admin_ticket.php?track={$trackingID}" : 'manage_customers.php';

    $_SESSION['edit_userdata'] = TRUE;

    if ( ! isset($_SESSION['save_userdata']))
    {
		$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id`= {$id} LIMIT 1");
    	$_SESSION['userdata'] = hesk_dbFetchAssoc($res);

        if (defined('HESK_DEMO')) {
            $_SESSION['userdata']['email'] = 'hidden@demo.com';
        }

        /* Store original username for display until changes are saved successfully */
        $_SESSION['original_user'] = $_SESSION['userdata']['email'];
        $_SESSION['userdata']['cleanpass'] = '';
    }

    /* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	/* Print main manage users page */
	require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
	?>
    <div class="right-bar team-create customer" style="display: block">
        <div class="right-bar__body form" data-step="1">
            <h3>
                <a href="<?php echo $return_url; ?>">
                    <svg class="icon icon-back">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                    </svg>
                    <span><?php echo $hesklang['editing_user'].' '.$_SESSION['original_user']; ?></span>
                </a>
            </h3>
            <?php
            if (!hesk_SESSION(array('userdata', 'errors'))) {
                /* This will handle error, success and notice messages */
                echo '<div style="margin: -24px -24px 10px -16px;">';
                hesk_handle_messages();
                echo '</div>';
            }
            ?>
            <form name="form1" method="post" action="manage_customers.php" class="form <?php echo hesk_SESSION(array('userdata','errors')) ? 'invalid' : ''; ?>">
                <?php hesk_customer_tab('userdata', intval($_SESSION['userdata']['verified']) === 1); ?>

                <!-- Submit -->
                <div class="right-bar__footer">
                    <input type="hidden" name="a" value="save">
                    <input type="hidden" name="userid" value="<?php echo $id; ?>" />
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    <input type="hidden" name="track" value="<?php echo $trackingID; ?>">
                    <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['save_changes']; ?></button>
                </div>
            </form>
        </div>
    </div>

	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End edit_user()


function new_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$myuser = hesk_validateUserInfo();

    // Check for duplicate emails. Don't care about registration state as the staff member can update an existing record
    if (strlen($myuser['email'])) {
        $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `email` = '".hesk_dbEscape($myuser['email'])."' LIMIT 1");
        if (hesk_dbNumRows($result) != 0) {
            hesk_process_messages($hesklang['customer_email_exists'],'manage_customers.php');
        }
    }

    $pass = $myuser['pass'] === null ? 'NULL' : "'".hesk_dbEscape($myuser['pass'])."'";

	hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` (
	`pass`,
	`name`,
	`email`,
	`language`,
	`verified`,
	`verification_token`,
	`mfa_enrollment`,
	`mfa_secret`
	) VALUES (
	".$pass.",
	'".hesk_dbEscape($myuser['name'])."',
	'".hesk_dbEscape($myuser['email'])."',
	NULL,
	".intval($myuser['verified']).",
	NULL,
	0,
	NULL
	)" );

    $_SESSION['seluser'] = [hesk_dbInsertID()];

    unset($_SESSION['userdata']);

    $success_message = $myuser['pass'] === null ?
        sprintf($hesklang['user_added_success_no_pass'],$myuser['email']) :
        sprintf($hesklang['user_added_success'],$myuser['email'],$myuser['cleanpass']);
    hesk_process_messages($success_message,'./manage_customers.php','SUCCESS');
} // End new_user()


function update_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    $_SESSION['save_userdata'] = TRUE;

	$tmp = intval( hesk_POST('userid') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");
    $trackingID = hesk_cleanID();

    $_SERVER['PHP_SELF'] = './manage_customers.php?a=edit&track='.$trackingID.'&id='.$tmp;
	$myuser = hesk_validateUserInfo($_SERVER['PHP_SELF']);
    $myuser['id'] = $tmp;

    /* Check for duplicate emails.  Don't care about registration state as the staff member can update an existing record */
    if ( ! empty($myuser['email'])) {
        $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
            WHERE `email` = '".hesk_dbEscape($myuser['email'])."'
                AND `id` <> ".intval($myuser['id'])."
            LIMIT 1");
        if (hesk_dbNumRows($result) != 0) {
            hesk_process_messages($hesklang['customer_email_exists'],'manage_customers.php');
        }
    }

	$res = hesk_dbQuery("SELECT `id`, `verified` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($tmp));
	if (hesk_dbNumRows($res) == 1)
	{
    	$tmp = hesk_dbFetchAssoc($res);
        $myuser['verified'] = $myuser['verified'] === 1 ? 1 : intval($tmp['verified']);
	}

    $password_part = '';
    if ($myuser['pass'] !== null && $hesk_settings['customer_accounts']) {
        $password_part = "`pass`='".hesk_dbEscape($myuser['pass'])."', `verification_token` = NULL, ";
        $myuser['verified'] = 1;
    }

    hesk_dbQuery(
    "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` SET
    `name`='".hesk_dbEscape($myuser['name'])."',
    `email`='".hesk_dbEscape($myuser['email'])."',
    {$password_part}
    `verified`=".$myuser['verified']."
    WHERE `id`='".intval($myuser['id'])."'");

    // Is the customer verified? Merge accounts if needed
    if ($myuser['verified'] === 1) {
        $merging_needed_rs = hesk_dbQuery("SELECT 1 AS `counter` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` 
        WHERE `email` = '".hesk_dbEscape($myuser['email'])."'");
        if (hesk_dbNumRows($merging_needed_rs) > 1) {
            hesk_merge_customer_accounts($myuser['email']);
        }
    }

    unset($_SESSION['save_userdata']);
    unset($_SESSION['userdata']);

    $_SESSION['seluser'] = [$myuser['id']];

    $return_url = $trackingID !== '' ? "./admin_ticket.php?track={$trackingID}" : './manage_customers.php';
    hesk_process_messages( $hesklang['user_profile_updated_success'], $return_url,'SUCCESS');
} // End update_profile()


function hesk_validateUserInfo($redirect_to = './manage_customers.php')
{
	global $hesk_settings, $hesklang;

    $hesk_error_buffer = '';
    $errors = array();

    if (hesk_input(hesk_POST('name'))) {
        $myuser['name'] = hesk_input(hesk_POST('name'));
    } else {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_real_name'] . '</li>';
        $errors[] = 'name';
    }

    $myuser['email'] = hesk_validateEmail( hesk_POST('email'), 'ERR', 0);
    if (empty($myuser['email'])) {
        if (! $hesk_settings['require_email']) {
            $myuser['email'] = '';
        } else {
            $hesk_error_buffer .= '<li>' . $hesklang['enter_valid_email'] . '</li>';
            $errors[] = 'email';
        }
    }

    /* Password */
	$myuser['cleanpass'] = '';
    $myuser['pass'] = null;
    $myuser['verified'] = 0;

	$newpass = hesk_input( hesk_POST('newpass') );
	$passlen = strlen($newpass);

	if ($passlen > 0)
	{
        /* At least 5 chars? */
        if ($passlen < 5)
        {
        	$hesk_error_buffer .= '<li>' . $hesklang['password_not_valid'] . '</li>';
        	$errors[] = 'passwords';
        }
        // Too long?
        elseif ($passlen > 64)
        {
            $hesk_error_buffer .= '<li>' . $hesklang['pass_len'] . '</li>';
            $errors[] = 'passwords';
        }
        /* Check password confirmation */
        else
        {
        	$newpass2 = hesk_input( hesk_POST('newpass2') );

			if ($newpass != $newpass2)
			{
				$hesk_error_buffer .= '<li>' . $hesklang['passwords_not_same'] . '</li>';
                $errors[] = 'passwords';
			}
            else
            {
                $myuser['pass'] = hesk_password_hash($newpass);
                $myuser['cleanpass'] = $newpass;
                $myuser['verified'] = 1;
                define('PASSWORD_CHANGED', true);
            }
        }
	}

    /* Save entered info in session so we don't lose it in case of errors */
	$_SESSION['userdata'] = $myuser;

    /* Any errors */
    if (strlen($hesk_error_buffer))
    {
        $_SESSION['userdata']['errors'] = $errors;

        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    	hesk_process_messages($hesk_error_buffer,$redirect_to);
    }

	return $myuser;
} // End hesk_validateUserInfo()


function remove()
{
	global $hesk_settings, $hesklang, $can_man_customers;

	/* A security check */
	hesk_token_check();
    $_SESSION['save_customer_search'] = true;

	$myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);

    // Make sure we have permission to edit this user
    if (!$can_man_customers) {
        hesk_process_messages($hesklang['customer_permission_denied'],'manage_customers.php');
    }

    // Should we delete or anonymize tickets opened by this customer?
    $delete_method = hesk_GET('delete-method');
    if ($delete_method === 'delete') {
        hesk_deleteTicketsForCustomer($myuser);
    } elseif ($delete_method === 'anonymize') {
        hesk_anonymizeTicketsForCustomer($myuser);
    } else {
        // Keep tickets
    }

    // Remove customer from all tickets
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_customer` WHERE `customer_id` = ".$myuser);

    // Delete user info
	$res = hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id`='".$myuser."'");
	if (hesk_dbAffectedRows() != 1) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_customers.php');
    }

    // Clear users' MFA tokens and backup codes
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id` = {$myuser} AND `user_type` = 'CUSTOMER'");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_backup_codes` WHERE `user_id` = {$myuser} AND `user_type` = 'CUSTOMER'");

    hesk_process_messages($hesklang['sel_user_removed'],'./manage_customers.php','SUCCESS');
} // End remove()

function reset_mfa() {
    global $hesk_settings, $hesklang;

    /* A security check */
    hesk_token_check();

    require(HESK_PATH . 'inc/mfa_functions.inc.php');

    $myuser = intval(hesk_GET('id')) or hesk_error($hesklang['no_valid_id']);

    // Make sure we have permission to edit this user
    if ( ! compare_user_permissions($myuser))
    {
        hesk_process_messages($hesklang['npea'],'manage_customers.php');
    }

    $_SESSION['seluser'] = [$myuser];
    $_SESSION['save_customer_search'] = true;

    $target_enrollment = 0;
    if ($hesk_settings['require_mfa']) {
        $target_enrollment = 1;
    }

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `mfa_enrollment` = {$target_enrollment}, `mfa_secret` = NULL WHERE `id` = {$myuser}");

    if (hesk_dbAffectedRows() != 1) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_customers.php');
    }

    delete_mfa_backup_codes($myuser);
    delete_mfa_codes($myuser);

    hesk_process_messages($hesklang['mfa_reset'], './manage_customers.php', 'SUCCESS');
}

function approve_registration($redirect = true) {
    global $hesk_settings, $hesklang;

    hesk_token_check();

    $myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);
    $_SESSION['seluser'] = [$myuser];
    $_SESSION['save_customer_search'] = true;

    $user_rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($myuser));
    if (!hesk_dbNumRows($user_rs)) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_customers.php');
    }
    $user = hesk_dbFetchAssoc($user_rs);

    // Approve the registration
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` SET `verified` = 1 WHERE `id` = ".intval($myuser));

    // Send approval email
    if (!function_exists('hesk_sendCustomerRegistrationApprovedEmail')) {
        require(HESK_PATH . 'inc/email_functions.inc.php');
    }
    hesk_sendCustomerRegistrationApprovedEmail($user);

    if ($redirect) {
        hesk_process_messages($hesklang['customer_account_approved'], 'manage_customers.php', 'SUCCESS');
    }

}

function reject_registration($redirect = true, $send_email_notification = true) {
    global $hesk_settings, $hesklang;

    hesk_token_check();

    $myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);
    $_SESSION['seluser'] = [$myuser];
    $_SESSION['save_customer_search'] = true;

    $user_rs = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($myuser));
    if (!hesk_dbNumRows($user_rs)) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_customers.php');
    }
    $user = hesk_dbFetchAssoc($user_rs);

    // Reject the registration
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        WHERE `id` = ".intval($myuser));

    // Send email notification
    if ($send_email_notification) {
        if (!function_exists('hesk_sendCustomerRegistrationRejectedEmail')) {
            require(HESK_PATH . 'inc/email_functions.inc.php');
        }
        hesk_sendCustomerRegistrationRejectedEmail($user);
    }

    if ($redirect) {
        hesk_process_messages($hesklang['customer_account_rejected'], 'manage_customers.php', 'SUCCESS');
    }
}

function delete_registration($redirect = true) {
    global $hesk_settings, $hesklang;

    hesk_token_check();
    $myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);

    $user_rs = hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` WHERE `id` = ".intval($myuser));
    if (hesk_dbAffectedRows($user_rs) != 1) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_customers.php');
    }

    if ($redirect) {
        hesk_process_messages($hesklang['customer_account_deleted'], 'manage_customers.php', 'SUCCESS');
    }
}

function handle_bulk_action() {
    global $hesk_settings, $hesklang;

    $_SESSION['save_customer_search'] = true;

    $ids = hesk_POST_array('id');
    $ids = array_map('intval', $ids);
    $ids = array_unique($ids);
    $ids = array_filter($ids, function ($x) {return $x > 0;});

    if (isset($_POST['bulk_approve'])) {
        foreach ($ids as $customer_id) {
            $_GET['id'] = $customer_id;
            approve_registration(false);
        }
        $message = $hesklang['customer_manage_bulk_approve_complete'];
    } elseif (isset($_POST['bulk_reject'])) {
        foreach ($ids as $customer_id) {
            $_GET['id'] = $customer_id;
            reject_registration(false);
        }
        $message = $hesklang['customer_manage_bulk_reject_complete'];
    } elseif (isset($_POST['bulk_delete'])) {
        foreach ($ids as $customer_id) {
            $_GET['id'] = $customer_id;
            delete_registration(false);
        }
        $message = $hesklang['customer_manage_bulk_delete_complete'];
    } else {
        hesk_error($hesklang['int_error'].': '.$hesklang['invalid_action']);
    }

    $_SESSION['seluser'] = $ids;
    hesk_process_messages(sprintf($message, count($ids)), 'manage_customers.php', 'SUCCESS');
}

function resend_verification_email() {
    global $hesklang, $hesk_settings;

    $_SESSION['save_customer_search'] = true;
    $id = intval(hesk_GET('id', 0));
    if (!$id) {
        hesk_process_messages($hesklang['no_valid_id'], 'manage_customers.php');
        return;
    }

    $user_info_rs = hesk_dbQuery("SELECT *
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customer` 
        WHERE `id` = {$id} 
        AND `verified` = 0 
        AND `verification_token` IS NOT NULL
        LIMIT 1");

    if (hesk_dbNumRows($user_info_rs) !== 1) {
        hesk_process_messages($hesklang['no_valid_id'], 'manage_customers.php');
        return;
    }

    if (!function_exists('hesk_sendCustomerRegistrationEmail')) {
        require_once(HESK_PATH . 'inc/email_functions.inc.php');
    }

    $user_info = hesk_dbFetchAssoc($user_info_rs);
    hesk_sendCustomerRegistrationEmail($user_info, $user_info['verification_token']);
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."customers`
        SET `verification_email_sent_at` = NOW()
        WHERE `id` = ".intval($user_info['id']));

    if (isset($_SESSION['img_verified']))
    {
        unset($_SESSION['img_verified']);
    }
    hesk_process_messages(sprintf($hesklang['customer_manage_verification_email_sent'], $user_info['email']), 'manage_customers.php', 'SUCCESS');
}

function build_sort_url($original_url, $current_sort_field, $sort_field, $current_sort_direction) {
    $target_sort_direction = $current_sort_direction === 'ASC' && $sort_field === $current_sort_field ? 'DESC' : 'ASC';
    $encoded_field = urlencode($sort_field);

    $new_url = str_replace("search_sort_column={$current_sort_field}", "search_sort_column={$encoded_field}", $original_url);
    $new_url = str_replace("search_sort_direction={$current_sort_direction}", "search_sort_direction=", $new_url);
    return str_replace("search_sort_direction=", "search_sort_direction={$target_sort_direction}", $new_url);
}
?>
