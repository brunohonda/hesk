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
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_man_settings');

// Load priorities
require_once(HESK_PATH . 'inc/priorities.inc.php');

// What should we do?
if ( $action = hesk_REQUEST('a') )
{
    if ($action == 'edit_priority') {edit_priority();}
    elseif ( defined('HESK_DEMO') ) {hesk_process_messages($hesklang['ddemo'], 'custom_priorities.php', 'NOTICE');}
    elseif ($action == 'new_priority') {new_priority();}
    elseif ($action == 'save_priority') {save_priority();}
    elseif ($action == 'remove_priority') {remove_priority();}
    elseif ($action == 'sort_priority'){sort_priority();}
}

// Print header
require_once(HESK_PATH . 'inc/header.inc.php');

// Print main manage users page
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION('edit_priority') && !hesk_SESSION(array('new_priority','errors'))) {
    hesk_handle_messages();
}

// Number of custom priorities
$hesk_settings['num_custom_priorities'] = count($hesk_settings['priorities']) - 4;

$reached_priority_limit = $hesk_settings['num_custom_priorities'] >= 100;

// Did we reach the custom priorities limit?
if ($reached_priority_limit && $action !== 'edit_priority') {
    hesk_show_info($hesklang['priority_limit']);
}

?>
<div class='custom_ajax_msg'></div>
<div class="main__content tools">
    <section class="tools__between-head">
        <h2>
            <?php echo $hesklang['priorities']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['priority_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <?php if (!$reached_priority_limit && $action !== 'edit_priority'): ?>
        <div class="btn btn--blue-border" ripple="ripple" data-action="create-custom-status">
            <?php echo $hesklang['new_priority']; ?>
        </div>
        <?php endif; ?>
    </section>
    <div class="table-wrapper status">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['id']; ?></th>
                    <th><?php echo $hesklang['priority_title']; ?></th>
                    <th><?php echo $hesklang['csscl']; ?></th>
                    <th><?php echo $hesklang['tickets']; ?></th>
                    <th><?php echo $hesklang['selected_by_customer']; ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="priority_sort">
                <?php
                // Number of tickets per priority
                $tickets_all = array();

                if ($_SESSION['isadmin']) {
                    $res = hesk_dbQuery('SELECT COUNT(*) AS `cnt`, `priority` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` GROUP BY `priority`');
                } else {
                    $res = hesk_dbQuery("SELECT COUNT(*) AS `cnt`, `priority`
                                        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `ticket`
                                        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."ticket_to_collaborator` AS `w` ON (`ticket`.`id` = `w`.`ticket_id` AND `w`.`user_id` = ".intval($_SESSION['id']).")
                                        WHERE
                                        (
                                            `w`.`user_id`=".intval($_SESSION['id'])."
                                            OR
                                            (".hesk_myOwnership().")
                                        )
                                        AND ".hesk_myCategories()."
                                        GROUP BY `priority`");
                }

                while ($tmp = hesk_dbFetchAssoc($res)) {
                    $tickets_all[$tmp['priority']] = $tmp['cnt'];
                }

                $is_custom = false;

                $i = 1;

                foreach ($hesk_settings['priorities'] as $tmp_id => $priority) {
                    $p_color = $priority['color'];
                    $priority['span'] = isset($priority['class']) ? '<span class="' . $priority['class'] . '">' : '<span style="color: ' . $priority['color'] . '">';
                    $priority['color'] = isset($priority['class']) ? $priority['span'] . '.' . $priority['class'] . '</span>' : $priority['span'] . $priority['color'] . '</span>';
                    $priority['tickets'] = isset($tickets_all[$tmp_id]) ? $tickets_all[$tmp_id] : 0;
                    $priority['can_customers_select'] = ! isset($priority['can_customers_select']) ? '' : ($priority['can_customers_select'] == 1 ? $hesklang['yes'] : $hesklang['no']);
                    $icon_style = 'border-top-color:'.$p_color.';border-left-color:'.$p_color.';border-bottom-color:'.$p_color.';';
                    if (!$is_custom && $tmp_id > 1) {
                        $is_custom = true;
                    }

                    $table_row = '';
                    if (isset($_SESSION['priority_ord']) && $_SESSION['priority_ord'] == $priority['id']) {
                        $table_row = 'class="ticket-new"';
                        unset($_SESSION['priority_ord']);
                    }
                    ?>
                    <tr <?php echo $table_row; ?> data-id="<?php echo $priority['id']; ?>">
                        <td><?php echo $priority['id']; ?></td>
                        <td class="td-flex"><div class="priority_img" style=<?php echo $icon_style; ?>></div> <p class="p-title"><?php echo $priority['name']; ?></p></td>
                        <td><?php echo $priority['color']; ?></td>
                        <td><a class="tooltip" href="show_tickets.php?<?php echo 'p'.$tmp_id.'=1'; ?>&amp;s_all=1&amp;s_my=1&amp;s_ot=1&amp;s_un=1" alt="<?php echo $hesklang['list_tkt_priority']; ?>" title="<?php echo $hesklang['list_tkt_priority']; ?>"><?php echo $priority['tickets']; ?></a></td>
                        <td><?php echo $priority['can_customers_select']; ?></td>
                        <td class="nowrap buttons">
                            <?php $modal_id = hesk_generate_old_delete_modal($hesklang['confirm_deletion'],
                                $hesklang['confirm_delete_priority'],
                                'custom_priorities.php?a=remove_priority&amp;id='. $priority['id'] .'&amp;token='. hesk_token_echo(0)); ?>
                            <p>
                                <a href="custom_priorities.php?a=edit_priority&amp;id=<?php echo $priority['id']; ?>" class="edit tooltip" title="<?php echo $hesklang['edit']; ?>">
                                    <svg class="icon icon-edit-ticket">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                    </svg>
                                </a>
                                <a href="javascript:;" class="icon icon-drag-drop tooltip row_sort" title="<?php echo $hesklang['click_to_enable_drag_drop']; ?>">
                                    <svg class="icon icon-drag-drop">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg?#icon-drag-drop"></use>
                                    </svg>
                                </a>
                                <?php 
                                
                                if ($tmp_id == 0 || $priority['tickets'] > 0):
                                    $priority_del_txt = ($tmp_id == 0) ? $hesklang['deletion_priority_restricted']:$hesklang['priority_not_empty'];
                                ?>
                                    <a onclick="alert('<?php echo hesk_makeJsString($priority_del_txt); ?>');"
                                       class="delete tooltip not-allowed"
                                       title="<?php echo $priority_del_txt; ?>">
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
                    <?php
                } // End foreach
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script type="text/javascript" src="<?php echo HESK_PATH; ?>inc/jscolor/jscolor.min.js"></script>
<script type="text/javascript">
    function hesk_preview(jscolor) {
        document.getElementById('color_preview').style.color = "#" + jscolor;
    }
</script>
<script src="<?php echo HESK_PATH; ?>js/jquery-ui.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script type="text/javascript">
    $(function() {
        $('body').on('mouseover','.row_sort',function(){
            $( "#priority_sort" ).sortable({
                placeholder: "ui-state-highlight",
                cancel: ".ui-state-disabled",
                update: function( event, ui ) {
                    updatePriorityOrder();
                }
            });
            $( "#priority_sort" ).disableSelection();
        });
    });
    
    function updatePriorityOrder() {
        var priority_sort_data = [];
        var object_data = [];
        var j = 1;
        $('tbody#priority_sort tr').each(function() {
            if($(this).attr("data-id") > 0){
                priority_sort_data.push({id:$(this).attr("data-id"),priority_order:j});
                j++;
            }
        });
        var data = {
            'a':'sort_priority',
            'priority_order': JSON.stringify(priority_sort_data)
        }
        $.ajax({
            type: 'POST',
            url: 'custom_priorities.php',
            data: data,
            cache: false,
            success: function(data){
                var result = JSON.parse(data);
                if(result.status=='SUCCESS'){
                    $('.notice-flash').remove();
                    $('.custom_ajax_msg').html('');
                    $('.custom_ajax_msg').html(result.message);
                    $( "#priority_sort" ).sortable("destroy");
                    //$('tr').addClass('ui-state-disabled');
                }
            }
        });
    }
</script>
<div class="right-bar create-status" <?php echo hesk_SESSION('edit_priority') || hesk_SESSION(array('new_priority','errors')) ? 'style="display: block"' : ''; ?>>
    <form action="custom_priorities.php" method="post" name="form1" class="form <?php echo hesk_SESSION(array('new_priority','errors')) ? 'invalid' : ''; ?>">
        <div class="right-bar__body form">
            <h3>
                <a href="<?php echo hesk_SESSION('edit_priority') ? 'custom_priorities.php' : 'javascript:'; ?>">
                    <svg class="icon icon-back">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                    </svg>
                    <span><?php echo hesk_SESSION('edit_priority') ? $hesklang['edit_priority'] : $hesklang['new_priority']; ?></span>
                </a>
            </h3>
            <?php
            /* This will handle error, success and notice messages */
            if (hesk_SESSION(array('new_priority', 'errors'))) {
                echo '<div style="margin: -24px -24px 10px -16px;">';
                hesk_handle_messages();
                echo '</div>';
            }

            $names = hesk_SESSION(array('new_priority','names'));
            $id = hesk_SESSION(array('new_priority','id'));

            $errors = hesk_SESSION(array('new_priority','errors'));
            $errors = is_array($errors) ? $errors : array();
            
            if ($hesk_settings['can_sel_lang'] && count($hesk_settings['languages']) > 1) {
                echo '<h4>' . $hesklang['priority_title'] . '</h4>';
                foreach ($hesk_settings['languages'] as $lang => $info) {
                    
                    $lang_value = '';
                    if(isset($id) && $id !=''){
                        if((!isset($names[$lang]) && $id < 4) || (isset($names[$lang]) && strtolower($names[$lang]) == "null"  && $id < 4)){
                            hesk_setLanguage($lang);

                            //Check for default priority name is NULL
                            switch ($id) {
                                case 0:
                                    $lang_value = $hesklang['critical'];
                                    break;
                                case 1:
                                    $lang_value = $hesklang['high'];
                                    break;
                                case 2:
                                    $lang_value = $hesklang['medium'];
                                    break;
                                case 3:
                                    $lang_value = $hesklang['low'];
                                    break;    
                                default:
                                    $lang_value = '';
                            }
                        }else{
                            $lang_value = isset($names[$lang]) ? $names[$lang] : '';
                        }
                    }
                    ?>
                    <div class="form-group">
                        <label><?php echo $lang; ?></label>
                        <input type="text" class="form-control <?php echo in_array('names', $errors) ? 'isError' : ''; ?>" name="name[<?php echo $lang; ?>]" value="<?php echo $lang_value; ?>">
                    </div>
                <?php }
            } else { 
                    $lang = $hesk_settings['language'];
                    $lang_value = '';
                    if(isset($id) && $id !=''){
                        if((!isset($names[$lang]) && $id < 4) || (isset($names[$lang]) && strtolower($names[$lang]) == "null"  && $id < 4)){
                            //Check for default priority name is NULL
                            switch ($id) {
                                case 0:
                                    $lang_value = $hesklang['critical'];
                                    break;
                                case 1:
                                    $lang_value = $hesklang['high'];
                                    break;
                                case 2:
                                    $lang_value = $hesklang['medium'];
                                    break;
                                case 3:
                                    $lang_value = $hesklang['low'];
                                    break;    
                                default:
                                    $lang_value = '';
                            }
                        }else{
                            $lang_value = isset($names[$lang]) ? $names[$lang] : '';
                        }
                    }
                ?>
                <div class="form-group">
                    <label><?php echo $hesklang['priority_title']; ?></label>
                    <input type="text" class="form-control <?php echo in_array('names', $errors) ? 'isError' : ''; ?>" name="name[<?php echo $lang; ?>]"
                           value="<?php echo $lang_value; ?>">
                </div>
            <?php }
            hesk_resetLanguage();
            ?>
            <div class="form-group color">
                <?php $color = hesk_validate_color_hex(hesk_SESSION(array('new_priority','color'))); ?>
                <label><?php echo $hesklang['color']; ?></label>
                <input type="text" class="form-control jscolor {hash:true, uppercase:false, onFineChange:'hesk_preview(this)'}" name="color" value="<?php echo $color; ?>">
                <span id="color_preview" style="color:<?php echo $color; ?>"><?php echo $hesklang['clr_view']; ?></span>
            </div>
            <div class="form-switcher">
                <?php $can_customers_select = hesk_SESSION(array('new_priority','can_customers_select'), 0); ?>
                <label class="switch-checkbox">
                    <input type="checkbox" name="can_customers_select" <?php if ($can_customers_select) {echo 'checked';} ?>>
                    <div class="switch-checkbox__bullet">
                        <i>
                            <svg class="icon icon-close">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                            </svg>
                            <svg class="icon icon-tick">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                            </svg>
                        </i>
                    </div>
                    <span><?php echo $hesklang['can_customers_select_it']; ?></span>
                </label>
            </div>
            <?php if (isset($_SESSION['edit_priority'])): ?>
                <input type="hidden" name="a" value="save_priority">
                <input type="hidden" name="id" value="<?php echo intval($_SESSION['new_priority']['id']); ?>">
            <?php else: ?>
                <input type="hidden" name="a" value="new_priority">
            <?php endif; ?>
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
            <button type="submit" class="btn btn-full save" ripple="ripple"><?php echo $hesklang['status_save']; ?></button>
        </div>
    </form>
</div>
<?php

hesk_cleanSessionVars( array('new_priority', 'edit_priority') );

require_once(HESK_PATH . 'inc/footer.inc.php');

exit();


/*** START FUNCTIONS ***/


function save_priority()
{
    global $hesk_settings, $hesklang;
    global $hesk_error_buffer;

    // A security check
    # hesk_token_check('POST');

    // Get custom priority ID
    $id = intval( hesk_POST('id') );
    if ($id < 0) {
         hesk_error($hesklang['priority_e_id']);
    }

    // Validate inputs
    if (($priority = priority_validate()) == false)
    {
        $_SESSION['edit_priority'] = true;
        $_SESSION['new_priority']['id'] = $id;

        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $hesk_error_buffer = $tmp;

        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,'custom_priorities.php');
    }

    // Remove # from color
    $color = str_replace('#', '', $priority['color']);

    // Add custom priority data into database
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` SET
    `name` = '".hesk_dbEscape($priority['names'])."',
    `color` = '{$color}',
    `can_customers_select` = '{$priority['can_customers_select']}'
    WHERE `id`={$id}");

    // Clear cache
    hesk_purge_cache('priority');

    // Show success
    $_SESSION['priority_ord'] = $id;
    hesk_process_messages($hesklang['priority_mdf'],'custom_priorities.php','SUCCESS');

} // End save_priority()


function edit_priority()
{
    global $hesk_settings, $hesklang;

    // Get custom priority ID
    $id = intval( hesk_GET('id') );
    if ($id < 0) {
        hesk_error($hesklang['priority_e_id']);
    }

    // Get details from the database
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` WHERE `id`={$id} LIMIT 1");
    if ( hesk_dbNumRows($res) != 1 )
    {
        hesk_error($hesklang['priority_not_found']);
    }
    $priority = hesk_dbFetchAssoc($res);

    $priority['names'] = json_decode($priority['name'], true);

    unset($priority['name']);

    $priority['color'] = '#'.$priority['color'];
    
    $_SESSION['new_priority'] = $priority;
    $_SESSION['edit_priority'] = true;

} // End edit_priority()


function update_priority_order()
{
    global $hesk_settings, $hesklang;

    // Get list of current custom priorities
    $res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` ORDER BY `priority_order` ASC");

    // Update database
    $i = 1;
    while ( $priority = hesk_dbFetchAssoc($res) )
    {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` SET `priority_order`=".intval($i)." WHERE `id`='".intval($priority['id'])."'");
        $i++;
    }

    return true;

} // END update_priority_order()


function remove_priority()
{
    global $hesk_settings, $hesklang;

    // A security check
    hesk_token_check();

    // Get ID
    $id = intval( hesk_GET('id') ) or hesk_error($hesklang['priority_e_id']);

    // Any tickets with this priority?
    $res = hesk_dbQuery("SELECT COUNT(*) AS `cnt`, `priority` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `priority` = {$id}");
    if (hesk_dbResult($res) > 0)
    {
        hesk_process_messages($hesklang['priority_not_empty'],'./custom_priorities.php');
    }

    // Reset the custom priority
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` WHERE `id`={$id}");

    // Were we successful?
    if ( hesk_dbAffectedRows() == 1 )
    {
        // Update order
        update_priority_order();
        
        // Clear cache
        hesk_purge_cache('priority');

        // Show success message
        hesk_process_messages($hesklang['priority_deleted'],'./custom_priorities.php','SUCCESS');
    }
    else
    {
        hesk_process_messages($hesklang['priority_not_found'],'./custom_priorities.php');
    }

} // End remove_priority()


function priority_validate()
{
    global $hesk_settings, $hesklang;
    global $hesk_error_buffer;

    $hesk_error_buffer = array();

    // Get names
    $priority['names'] = hesk_POST_array('name');

    // Make sure only valid names pass
    foreach ($priority['names'] as $key => $name)
    {
        if ( ! isset($hesk_settings['languages'][$key]))
        {
            unset($priority['names'][$key]);
        }
        else
        {
            $name = is_array($name) ? '' : hesk_input($name, 0, 0, HESK_SLASH);

            if (strlen($name) < 1)
            {
                unset($priority['names'][$key]);
            }
            else
            {
                $priority['names'][$key] = stripslashes($name);
            }
        }
    }

    // No name entered?
    $errors = array();
    if ( ! count($priority['names']))
    {
        $hesk_error_buffer[] = $hesklang['err_priority'];
        $errors[] = 'names';
    }

    // Color
    $priority['color'] = hesk_validate_color_hex(hesk_POST('color'));

    // Can customers change it?
    $priority['can_customers_select'] = hesk_POST('can_customers_select') ? 1 : 0;

    // Any errors?
    if (count($hesk_error_buffer))
    {
        $_SESSION['new_priority'] = $priority;
        $_SESSION['new_priority']['errors'] = $errors;
        return false;
    }

    $priority['names'] = addslashes(json_encode($priority['names']));
   
    return $priority;
} // END priority_validate()


function new_priority()
{
    global $hesk_settings, $hesklang;
    global $hesk_error_buffer;

    // A security check
    # hesk_token_check('POST');

    // Validate inputs
    if (($priority = priority_validate()) == false)
    {
        $tmp = '';
        foreach ($hesk_error_buffer as $error)
        {
            $tmp .= "<li>$error</li>\n";
        }
        $hesk_error_buffer = $tmp;

        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
        hesk_process_messages($hesk_error_buffer,'custom_priorities.php');
    }

    // The lowest currently used ID
    $res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` ORDER BY `id` DESC LIMIT 1");
    $lowest_id = hesk_dbResult($res);
    $next_id = $lowest_id + 1;

    // Did we reach priority limit?
    if ($next_id > 255) {
        hesk_process_messages($hesklang['priority_limit'],'custom_priorities.php');
    }

    // Remove # from color
    $color = str_replace('#', '', $priority['color']);

    // Insert custom priority into database
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` (`id`, `name`, `color`, `can_customers_select`, `priority_order`) VALUES ({$next_id}, '".hesk_dbEscape($priority['names'])."', '{$color}', '{$priority['can_customers_select']}', 990)");

    // Update order
    update_priority_order();

    // Clear cache
    hesk_purge_cache('priority');

    $_SESSION['priority_ord'] = $next_id;

    // Show success
    hesk_process_messages($hesklang['priority_added'],'custom_priorities.php','SUCCESS');

} // End new_priority()

//Sort Priority Order
function sort_priority()
{
    global $hesk_settings, $hesklang;

    $priority['a'] = hesk_POST('a');
    $priority['priority_order'] = json_decode(hesk_POST('priority_order'),true);
    // Start building the priority query
    $q = "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."custom_priorities` SET `priority_order` = CASE `id`";

    // Add each update to the CASE statement
    foreach ($priority['priority_order'] as $update) {
        $q .= " WHEN {$update['id']} THEN {$update['priority_order']}";
    }
    $q .= " END";

    // Extract IDs for the WHERE clause
    $ids = array_column($priority['priority_order'], 'id');
    $q .= " WHERE `id` IN (" . implode(',', $ids) . ")";

    //Update priority order
    hesk_dbQuery($q);

    // Show success
    $array = [];
    $array['status'] = 'SUCCESS';
    $array['redirect'] = HESK_PATH.'custom_priorities.php';
    $html = '<div class="main__content notice-flash ">';
    $html .= '<div class="notification green">';
    $html .= '<b>'.$hesklang['success'].': </b>'.$hesklang['reordered_message'].'</div>';
    $html .= '</div>';
    $array['message'] = $html;
    echo json_encode($array);

    // Clear cache
    hesk_purge_cache('priority');

    exit();
} // End sort_priority()


function hesk_validate_color_hex($hex, $def = '#000000')
{
    $hex = strtolower($hex);
    return preg_match('/^\#[a-f0-9]{6}$/', $hex) ? $hex : $def;
} // END hesk_validate_color_hex()


function hesk_get_text_color($bg_color)
{
    // Get RGB values
    list($r, $g, $b) = sscanf($bg_color, "#%02x%02x%02x");

    // Is Black a good text color?
    if (hesk_color_diff($r, $g, $b, 0, 0, 0) >= 500)
    {
        return '#000000';
    }

    // Use white instead
    return '#ffffff';
} // END hesk_get_text_color()


function hesk_color_diff($R1,$G1,$B1,$R2,$G2,$B2)
{
    return max($R1,$R2) - min($R1,$R2) +
           max($G1,$G2) - min($G1,$G2) +
           max($B1,$B2) - min($B1,$B2);
} // END hesk_color_diff()
