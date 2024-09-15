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
require(HESK_PATH . 'inc/setup_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_man_cat');

// Possible priorities
$priorities = array(
    'low' => array('id' => 3, 'value' => 'low', 'text' => $hesklang['low'], 'formatted' => $hesklang['low']),
    'medium' => array('id' => 2, 'value' => 'medium', 'text' => $hesklang['medium'], 'formatted' => $hesklang['medium']),
    'high' => array('id' => 1, 'value' => 'high', 'text' => $hesklang['high'], 'formatted' => $hesklang['high']),
    'critical' => array('id' => 0, 'value' => 'critical', 'text' => $hesklang['critical'], 'formatted' => $hesklang['critical']),
);

// Populate default values for creation
$category = array(
    'id' => 0,
    'name' => '',
    'priority' => $priorities['low']['id'],
    'autoassign' => $hesk_settings['autoassign'],
    'autoassign_config' => null,
    'type' => 0,
    'default_due_date_unit' => 'day',
    'default_due_date_amount' => ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (defined('HESK_DEMO')) {
        hesk_process_messages($hesklang['ddemo'], 'manage_categories.php', 'NOTICE');
    }

    // Attempt to save. If problematic, we'll get back the form data entered.
    $category = try_save_category();
} elseif (hesk_REQUEST('id')) {
    // Fetch category information
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id` = ".intval(hesk_REQUEST('id')));
    if ($row = hesk_dbFetchAssoc($res)) {
        $category['id'] = $row['id'];
        $category['name'] = $row['name'];
        $category['priority'] = intval($row['priority']);
        $category['autoassign'] = intval($row['autoassign']);
        $category['autoassign_config'] = $row['autoassign_config'];
        $category['type'] = intval($row['type']);
        $category['default_due_date_amount'] = $row['default_due_date_amount'] ? intval($row['default_due_date_amount']) : '';
        $category['default_due_date_unit'] = $row['default_due_date_unit'];
    }

    // If we're still on ID 0, then the category ID passed in doesn't exist
    if ($category['id'] === 0) {
        hesk_process_messages($hesklang['cat_not_found'], 'manage_categories.php');
    }
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (hesk_SESSION('iserror')) {
    hesk_handle_messages();
}
?>
<div class="main__content categories category-create">
    <section class="categories__head">
        <h2>
            <?php echo $category['id'] !== 0 ? $hesklang['edit_category'] : $hesklang['create_category']; ?>
        </h2>
    </section>
    <div class="table-wrap">
        <form method="post" class="form <?php echo isset($_SESSION['iserror']) ? 'invalid' : ''; ?>" action="manage_category.php" name="create-form">
            <div class="form-group">
                <label for="name">
                    <?php echo $hesklang['cat_name']; ?>: <span class="important">*</span>
                </label>
                <input type="text"
                       name="name"
                       class="form-control"
                       id="name"
                       maxlength="100"
                       value="<?php echo stripslashes($category['name']); ?>">
            </div>
            <div class="category-create__select">
                <span><?php echo $hesklang['def_pri']; ?></span>
                <div class="dropdown-select center out-close priority">
                    <select name="priority">
                        <?php foreach ($priorities as $id => $priority): ?>
                            <option value="<?php echo $priority['id']; ?>"
                                    <?php if ($priority['id'] === intval($category['priority'])): ?>selected<?php endif; ?>>
                                <?php echo $priority['text']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($hesk_settings['autoassign']): ?>
            <div class="form-group">
                <label style="text-align: left"><?php echo $hesklang['aa_cat']; ?>:</label>
                <div class="radio-group">
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio"
                                   id="autoassign_on_all"
                                   name="autoassign"
                                   value="1"
                                   onclick="hesk_toggleLayer('select-users-window','none')"
                                   <?php if ($category['autoassign'] === 1 && $category['autoassign_config'] === null): ?>checked<?php endif; ?>>
                            <label for="autoassign_on_all"><?php echo $hesklang['autoassign_on_all_users']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio"
                                   id="autoassign_on_some"
                                   name="autoassign"
                                   value="2"
                                   onclick="hesk_toggleLayer('select-users-window','block')"
                                   <?php if ($category['autoassign'] === 1 && $category['autoassign_config'] !== null): ?>checked<?php endif; ?>>
                            <label for="autoassign_on_some"><?php echo $hesklang['autoassign_on_select_users']; ?></label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio"
                                   id="autoassign_off"
                                   name="autoassign"
                                   value="0"
                                   onclick="hesk_toggleLayer('select-users-window','none')"
                                   <?php if ($category['autoassign'] === 0): ?>checked<?php endif; ?>>
                            <label for="autoassign_off"><?php echo $hesklang['autoassign_off']; ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div id="select-users-window" style="display: <?php echo $category['autoassign'] === 1 && $category['autoassign_config'] !== null ? 'block' : 'none'; ?>">
                <hr>
                <div class="form-group">
                    <?php
                    $users_all = hesk_dbQuery("SELECT COUNT(*) FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users` WHERE `isadmin` = '1' OR FIND_IN_SET('can_view_tickets', `heskprivileges`) > 0");
                    $users_num = hesk_dbResult($users_all);
                    $users_res = hesk_dbQuery("SELECT `id`, `name`
                                                  FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "users`
                                                  WHERE (`isadmin` = '1' OR (FIND_IN_SET(".intval($category['id']).", `categories`) > 0) AND FIND_IN_SET('can_view_tickets', `heskprivileges`) > 0)");
                    $users_found = hesk_dbNumRows($users_res);

                    if ($users_num > $users_found): ?>
                    <div class="notice-flash">
                        <div class="notification blue">
                            <?php echo sprintf($hesklang['ouwa'], $hesklang['team']); ?>
                        </div>
                    </div>
                    <?php endif;

                    echo '<label>'.$hesklang['autoassign_users'].':</label>';

                    if ($users_found > 19) {
                        // Only show search box if we have 20+ users
                    ?>
                    <div class="form-group">
                        <input type="text"
                               id="search-for-user"
                               class="form-control"
                               placeholder="<?php echo $hesklang['search_for_user']; ?>">
                    </div>
                    <?php } ?>
                    <div class="autoassign-users">
                        <?php
                        while ($user = hesk_dbFetchAssoc($users_res)) { ?>
                            <div class="checkbox-custom <?php if (user_is_involved_in_autoassign_config($user['id'], $category['autoassign_config'])): ?>checked<?php endif; ?>" data-name="<?php echo hesk_htmlspecialchars($user['name']); ?>">
                                <input type="checkbox"
                                       id="autoassign_user_<?php echo $user['id']; ?>"
                                       name="autoassign_user[]"
                                       value="<?php echo $user['id']; ?>"
                                       <?php if (user_is_involved_in_autoassign_config($user['id'], $category['autoassign_config'])): ?>checked<?php endif; ?>>
                                <label for="autoassign_user_<?php echo $user['id']; ?>"><?php echo $user['name']; ?></label>
                            </div>
                        <?php } ?>
                        <p id="search-no-results" style="display: none"><?php echo $hesklang['no_results_found']; ?></p>
                    </div>
                    <a href="javascript:" id="select-all"><?php echo $hesklang['a_select']; ?></a>
                    &nbsp;
                    <a href="javascript:" id="deselect-all"><?php echo $hesklang['a_deselect']; ?></a>
                </div>
                <div class="form-group">
                    <div class="radio-group">
                        <div class="radio-list" style="text-align: left">
                            <div class="radio-custom">
                                <input type="radio"
                                       id="autoassign_user_include"
                                       name="autoassign_user_operator"
                                       value="="
                                       <?php if ($category['autoassign_config'] === null || (is_string($category['autoassign_config']) && substr($category['autoassign_config'], 0, 1) === '=')): ?>checked<?php endif; ?>>
                                <label for="autoassign_user_include"><?php echo $hesklang['autoassign_selected_include']; ?></label>
                            </div>
                            <div class="radio-custom">
                                <input type="radio"
                                       id="autoassign_user_exclude"
                                       name="autoassign_user_operator"
                                       value="!"
                                       <?php if (is_string($category['autoassign_config']) && substr($category['autoassign_config'], 0, 1) === '!'): ?>checked<?php endif; ?>>
                                <label for="autoassign_user_exclude"><?php echo $hesklang['autoassign_selected_exclude']; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
            </div>
            <?php endif; ?>
            <div class="category-create__select">
                <span><?php echo $hesklang['cat_type']; ?>:</span>
                <div class="dropdown-select center out-close">
                    <select name="type">
                        <option value="0" <?php if ($category['type'] === 0): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['cat_public']; ?>
                        </option>
                        <option value="1" <?php if ($category['type'] === 1): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['cat_private']; ?>
                        </option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label style="text-align: left"><?php echo $hesklang['category_default_due_date']; ?>:</label>
                <input type="text"
                       class="form-control"
                       id="due-date-amount"
                       name="due-date-amount"
                       style="width:100px; margin-left:6px; margin-right:6px"
                       value="<?php echo $category['default_due_date_amount']; ?>">
                <div class="dropdown-select center out-close" id="id1">
                    <select name="due-date-unit" id="due-date-unit" class="form-control selectized">
                        <option value="day" <?php if ($category['default_due_date_unit'] === 'day'): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['d_day']; ?>
                        </option>
                        <option value="week" <?php if ($category['default_due_date_unit'] === 'week'): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['d_week']; ?>
                        </option>
                        <option value="month" <?php if ($category['default_due_date_unit'] === 'month'): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['d_month']; ?>
                        </option>
                        <option value="year" <?php if ($category['default_due_date_unit'] === 'year'): ?>selected<?php endif; ?>>
                            <?php echo $hesklang['d_year']; ?>
                        </option>
                    </select>
                </div>
                <div><?php echo $hesklang['category_leave_blank_for_no_default_due_date']; ?></div>
            </div>
            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
            <button class="btn btn-full" type="submit" ripple="ripple"><?php echo $hesklang['create_cat']; ?></button>
        </form>
    </div>
</div>

<script type="text/javascript"><!--

function hesk_toggleLayer(nr,setto) {
    if (document.all)
        document.all[nr].style.display = setto;
    else if (document.getElementById)
        document.getElementById(nr).style.display = setto;
}

$(document).ready(function() {
    $('#select-all').click(function() {
        $('.checkbox-custom[data-name]').each(function() {
            clickCheckbox($(this), true);
        });
    });

    function clickCheckbox($el, shouldBeChecked) {
        if ($el.hasClass('checked') !== shouldBeChecked) {
            $el.find('input').click();
        }
    }

    $('#deselect-all').click(function() {
        $('.checkbox-custom[data-name]').each(function() {
            clickCheckbox($(this), false);
        });
    });

    $('#search-for-user').keyup(function() {
        var criteria = $(this).val().toLowerCase();
        var foundResult = false;

        $('.checkbox-custom[data-name]').each(function() {
            if ($(this).attr('data-name').toLowerCase().indexOf(criteria) === -1) {
                $(this).hide();
            } else {
                foundResult = true;
                $(this).show();
            }
        });

        if (foundResult) {
            $('#search-no-results').hide();
        } else {
            $('#search-no-results').show();
        }
    });
})
//-->
</script>
<?php
hesk_cleanSessionVars('iserror');
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/
function user_is_involved_in_autoassign_config($user_id, $autoassign_config) {
    if ($autoassign_config === null) {
        return false;
    }

    preg_match('/([!=])?\((.+)\)/', $autoassign_config, $matches);

    return in_array($user_id, explode(',', $matches[2]));
}


function try_save_category()
{
    global $hesk_settings, $hesklang, $priorities;

    /* A security check */
    hesk_token_check('POST');

    /* Options */
    $category = array();
    $category['id'] = intval(hesk_POST('id'));

    $category['autoassign'] = intval(hesk_checkMinMax(hesk_POST('autoassign'), 0, 2, $hesk_settings['autoassign']));
    $category['autoassign_config'] = null;
    if ($category['autoassign'] === 2) {
        // Handle inclusions/exclusions
        $autoassign_setup = get_autoassign_state($category['id'],
            intval($category['autoassign']),
            hesk_POST('autoassign_user_operator'),
            hesk_POST_array('autoassign_user'));

        $category['autoassign'] = $autoassign_setup['autoassign'];
        $category['autoassign_config'] = $autoassign_setup['autoassign_config'];
    }

    $category['type'] = hesk_POST('type') === '1' ? 1 : 0;

    // Default priority
    $category['priority'] = hesk_checkMinMax(hesk_POST('priority'), 0, 3, $priorities['low']['id']);

    // Default due date
    $category['default_due_date_amount'] = intval(hesk_POST('due-date-amount', -1));
    if ($category['default_due_date_amount'] < 1) {
        $category['default_due_date_amount'] = '';
    }

    $category['default_due_date_unit'] = get_valid_date_unit(hesk_POST('due-date-unit'));

    /* Category name */
    $category['name'] = hesk_input(hesk_POST('name'));

    if ($category['name'] === '') {
        $_SESSION['iserror'] = 1;
        hesk_process_messages($hesklang['enter_cat_name'], 'NOREDIRECT');
    }

    if ($category['id'] === 0) {
        /* Do we already have a category with this name? */
        $res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `name` LIKE '".hesk_dbEscape( hesk_dbLike($category['name']) )."' LIMIT 1");
        if (hesk_dbNumRows($res) != 0)
        {
            hesk_process_messages($hesklang['cndupl'], 'NOREDIRECT');
        }
    }

    // Do we have errors? If so, just return the category to the page.
    if (isset($_SESSION['iserror'])) {
        return $category;
    }

    /* Get the latest cat_order */
    $res = hesk_dbQuery("SELECT `cat_order` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` DESC LIMIT 1");
    $row = hesk_dbFetchRow($res);
    $my_order = isset($row[0]) ? intval($row[0]) + 10 : 10;

    // Prepare autoassign config for saving
    $sql_friendly_autoassign_config = $category['autoassign_config'] === null ? 'NULL' : "'".hesk_dbEscape($category['autoassign_config'])."'";
    $sql_friendly_due_date_amount = $category['default_due_date_amount'] === '' ? 'NULL' : $category['default_due_date_amount'];
    $sql_friendly_due_date_unit = $sql_friendly_due_date_amount === 'NULL' ? 'NULL' : "'".hesk_dbEscape($category['default_due_date_unit'])."'";
    if ($category['id'] === 0) {
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` (`name`,`cat_order`,`autoassign`,
                      `autoassign_config`,`type`, `priority`,`default_due_date_amount`,`default_due_date_unit`) 
                    VALUES ('".hesk_dbEscape($category['name'])."',
                            '".intval($my_order)."',
                            '".intval($category['autoassign'])."',
                            ".$sql_friendly_autoassign_config.",
                            '".intval($category['type'])."',
                            '".intval($category['priority'])."',
                            ".$sql_friendly_due_date_amount.",
                            ".$sql_friendly_due_date_unit.")");
        $_SESSION['selcat2'] = hesk_dbInsertID();
    } else {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories`
                      SET `name` = '".hesk_dbEscape($category['name'])."',
                          `autoassign` = '".intval($category['autoassign'])."',
                          `autoassign_config` = {$sql_friendly_autoassign_config},
                          `type` = '".intval($category['type'])."',
                          `priority` = '".intval($category['priority'])."',
                          `default_due_date_amount` = {$sql_friendly_due_date_amount},
                          `default_due_date_unit` = {$sql_friendly_due_date_unit}
                      WHERE `id` = ".intval($category['id']));
        $_SESSION['selcat2'] = $category['id'];
    }

    hesk_cleanSessionVars('iserror');

    $success_language_key = $category['id'] === 0 ? 'cat_name_added' : 'cat_edited';
    hesk_process_messages(sprintf($hesklang[$success_language_key],'<i>'.stripslashes($category['name']).'</i>'),'manage_categories.php','SUCCESS');
    exit();
} // End new_cat()


function get_autoassign_state($id, $autoassign_setting, $autoassign_user_operator, $autoassign_users) {
    /*
     * 1 -> On, All
     * 2 -> On, Some
     * 3 -> Off
     */
    // "On - All Users" or "On - Some Users" with 0 users being excluded
    if ($autoassign_setting === 1 ||
        ($autoassign_setting === 2 &&
            $autoassign_user_operator === '!' &&
            count($autoassign_users) === 0)) {
        $autoassign = 1;
        $autoassign_config = null;
    } elseif ($autoassign_setting === 2 && count($autoassign_users) !== 0) {
        // "On - Some Users" with at least one user selected. Otherwise it'll be treated as "On - All Users" above if 0 exclusions, or "Off" if 0 inclusions
        $autoassign = 1;
        $autoassign_config = build_autoassign_config($id, $autoassign_users, $autoassign_user_operator);

        // All excluded == off
        // All included == on - all users
        if ($autoassign_config === 'ALL_EXCLUDED') {
            $autoassign = 0;
            $autoassign_config = null;
        } elseif ($autoassign_config === 'ALL_INCLUDED') {
            $autoassign_config = null;
        }
    } else {
        $autoassign = 0;
        $autoassign_config = null;
    }

    return array(
        'autoassign' => $autoassign,
        'autoassign_config' => $autoassign_config
    );
} // End update_autoassign()

function build_autoassign_config($catid, $selected_users, $operator) {
    global $hesk_settings;

    // Make sure the entered operator is valid
    $operator = $operator === '=' ? '=' : '!';
    $formatted_users = array();

    $user_verification_clause = array_map(function($x) {
        return intval($x);
    }, $selected_users);
    $user_verification_rs = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` 
        WHERE (`isadmin` = '1' OR FIND_IN_SET(".intval($catid).", `categories`) > 0) AND `id` IN (".implode(',', $user_verification_clause).")");
    while ($user = hesk_dbFetchAssoc($user_verification_rs)) {
        $formatted_users[] = $user['id'];
    }

    // Make sure we're not including/excluding the entire list of possible users, as we can simplify
    $users_with_category_access = hesk_dbQuery("SELECT 1 AS `cnt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users`
        WHERE (`isadmin` = '1' OR FIND_IN_SET(".intval($catid).", `categories`) > 0)");
    if (hesk_dbNumRows($users_with_category_access) === count($formatted_users)) {
        return $operator === '=' ? 'ALL_INCLUDED' : 'ALL_EXCLUDED';
    }

    $formatted_users = implode(',', $formatted_users);


    return "{$operator}({$formatted_users})";
} // End build_autoassign_config()

function get_valid_date_unit($unit) {
    switch ($unit) {
        case 'week':
            return 'week';
            break;
        case 'month':
            return 'month';
            break;
        case 'year':
            return 'year';
            break;
        default:
            return 'day';
    }
} // END get_valid_date_unit()
?>
